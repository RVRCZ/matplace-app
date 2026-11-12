<?php

namespace Tests\Feature;

use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Exceptions\EngineException;
use App\Engines\Exceptions\SupersededException;
use App\Engines\Mesh\SupportsCheck;
use App\Engines\Repair\PythonTool;
use App\Engines\Repair\ScriptRunner;
use App\Models\FarmOrder;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * "Automatic" supports ask the model first (docs/AL.md §22): a piece of which nothing can hang in the air is sliced
 * with supports off, so the slicer skips its search; anything unclear is sliced with supports on as before. The
 * customer's "without supports" and an admin's override never ask.
 */
final class FarmSupportsCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create();
        $this->dir = sys_get_temp_dir().'/mp_supports_'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** A check that answers what the test says, and counts how often it was asked. */
    private function answering(array|\Throwable $answer, bool $enabled = true): object
    {
        $runner = new class($answer) implements ScriptRunner
        {
            /** @var list<array{0: string, 1: array}> */
            public array $asked = [];

            public function __construct(private array|\Throwable $answer) {}

            public function runScript(string $script, array $args, int $timeout = 90): array
            {
                $this->asked[] = [$script, $args];
                if ($this->answer instanceof \Throwable) {
                    throw $this->answer;
                }

                return $this->answer;
            }
        };
        $this->app->instance(SupportsCheck::class, new SupportsCheck($runner, $enabled));

        return $runner;
    }

    /** A slicer that only notes what it was asked. */
    private function recordingSlicer(): object
    {
        $inner = app(Slicer::class);
        $spy = new class($inner) implements Slicer
        {
            /** @var list<SliceParams> */
            public array $asked = [];

            public function __construct(private Slicer $inner) {}

            public function name(): string
            {
                return $this->inner->name();
            }

            public function supportedFormats(): array
            {
                return $this->inner->supportedFormats();
            }

            public function measure(string $meshPath): Dimensions
            {
                return $this->inner->measure($meshPath);
            }

            public function slice(string $meshPath, SliceParams $params): SliceResult
            {
                $this->asked[] = $params;

                return $this->inner->slice($meshPath, $params);
            }
        };
        $this->app->instance(Slicer::class, $spy);

        return $spy;
    }

    private function order(array $body = []): FarmOrder
    {
        $path = $this->dir.'/cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($this->user)->postJson('/farm/orders', $body + ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    public function test_the_check_says_not_needed_only_when_the_tool_says_so_in_as_many_words(): void
    {
        $check = fn (array|\Throwable $answer) => (new SupportsCheck(new class($answer) implements ScriptRunner
        {
            public function __construct(private array|\Throwable $answer) {}

            public function runScript(string $script, array $args, int $timeout = 90): array
            {
                return $this->answer instanceof \Throwable ? throw $this->answer : $this->answer;
            }
        }))->check('piece.stl', 25.0, 0.2);

        $this->assertFalse($check(['ok' => true, 'needed' => false, 'reason' => 'none'])['needed']);
        $this->assertSame('none', $check(['ok' => true, 'needed' => false, 'reason' => 'none'])['reason']);
        $this->assertTrue($check(['ok' => true, 'needed' => true, 'reason' => 'overhang'])['needed']);
        $this->assertTrue($check(['ok' => false, 'needed' => false, 'reason' => 'error'])['needed'], 'a tool that failed decides nothing');
        $this->assertTrue($check(['ok' => true])['needed'], 'no answer is no "not needed"');
        $this->assertTrue($check(['ok' => true, 'needed' => 0])['needed'], 'only a real false counts');
        $this->assertTrue($check([])['needed']);
        $broken = $check(new EngineException('supports_tool.py returned no JSON'));
        $this->assertTrue($broken['needed'], 'a broken tool: the slicer decides, as before');
        $this->assertSame('error', $broken['reason']);
    }

    public function test_a_check_stopped_because_the_order_moved_on_ends_the_run(): void
    {
        $check = new SupportsCheck(new class implements ScriptRunner
        {
            public function runScript(string $script, array $args, int $timeout = 90): array
            {
                throw new SupersededException('stopped');
            }
        });
        $this->expectException(SupersededException::class);
        $check->check('piece.stl', 25.0, 0.2);
    }

    public function test_a_piece_that_needs_no_supports_is_sliced_with_them_left_out(): void
    {
        $runner = $this->answering(['ok' => true, 'needed' => false, 'reason' => 'none', 'seconds' => 0.01]);
        $spy = $this->recordingSlicer();

        $order = $this->order();

        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertCount(1, $runner->asked);
        $this->assertSame('supports_tool.py', $runner->asked[0][0]);
        $this->assertSame('check', $runner->asked[0][1][0]);
        $this->assertSame('25', $runner->asked[0][1][2], 'the farm\'s threshold');
        $this->assertSame('0.2', $runner->asked[0][1][3], 'the layer of this slice');
        $params = end($spy->asked);
        $this->assertArrayNotHasKey('enable_support', $params->overrides['process'], 'the printer\'s "supports on" is left out');
        $this->assertNull($params->supports, 'the slicer may still add them when it cannot slice without');
        $this->assertSame('tree(auto)', $params->overrides['process']['support_type'], 'and then as trees, as the printer says');
        $this->assertFalse($order->supports_used, 'the customer and the admin see "supports: no"');
        $this->assertFalse($order->timings['supports_needed']);
        $this->assertSame('none', $order->timings['supports_check']);
        $this->assertArrayHasKey('supports_check_s', $order->timings);
        // what the order was sliced with is on record as it was sliced
        $this->assertArrayNotHasKey('enable_support', $order->slice_params['overrides']['process']);
    }

    public function test_a_piece_that_may_need_them_is_sliced_with_supports_on_as_before(): void
    {
        $runner = $this->answering(['ok' => true, 'needed' => true, 'reason' => 'overhang']);
        $spy = $this->recordingSlicer();

        $order = $this->order();

        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertCount(1, $runner->asked);
        $this->assertSame('1', end($spy->asked)->overrides['process']['enable_support']);
        $this->assertTrue($order->timings['supports_needed']);
        $this->assertSame('overhang', $order->timings['supports_check']);
    }

    public function test_a_broken_check_changes_nothing(): void
    {
        $this->answering(new EngineException('no numpy here'));
        $spy = $this->recordingSlicer();

        $order = $this->order();

        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertSame('1', end($spy->asked)->overrides['process']['enable_support']);
        $this->assertTrue($order->timings['supports_needed']);
        $this->assertSame('error', $order->timings['supports_check']);
    }

    public function test_the_customers_without_supports_and_a_switched_off_check_never_ask(): void
    {
        $runner = $this->answering(['ok' => true, 'needed' => false, 'reason' => 'none']);
        $spy = $this->recordingSlicer();
        $order = $this->order();
        $this->assertCount(1, $runner->asked);

        // "without supports": the customer's word, nothing to ask
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'supports' => 'off'])->assertOk();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertCount(1, $runner->asked, 'not asked again');
        $this->assertSame('0', end($spy->asked)->overrides['process']['enable_support']);
        $this->assertFalse(end($spy->asked)->supports);
        $this->assertArrayNotHasKey('supports_needed', $order->timings);

        // an admin's own word on supports: nothing to ask either
        $order->update(['supports' => 'auto', 'admin_overrides' => ['enable_support' => '1']]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'fine', 'strength' => 'standard', 'supports' => 'auto'])->assertOk();
        $this->assertCount(1, $runner->asked, 'the admin forced supports: not asked');
        $this->assertSame('1', end($spy->asked)->overrides['process']['enable_support']);

        // the check switched off (as shipped, or a machine without the Python engines): supports on, as always
        $off = $this->answering(['ok' => true, 'needed' => false, 'reason' => 'none'], enabled: false);
        $other = $this->order();
        $this->assertSame(FarmOrder::STATUS_SLICED, $other->status);
        $this->assertCount(0, $off->asked);
        $this->assertSame('1', end($spy->asked)->overrides['process']['enable_support']);
        $this->assertArrayNotHasKey('supports_needed', $other->timings);
    }

    public function test_the_check_is_off_as_shipped_and_without_the_python_engines(): void
    {
        // off as shipped (the measurement of 12 Nov 2026 found nothing to save, docs/AL.md §22) …
        $this->assertFalse(config('engines.mesh.supports_check'));
        $this->assertFalse(app(SupportsCheck::class)->enabled());
        // … and switched on (MESH_SUPPORTS_CHECK=true) it still asks only where the Python engines run: phpunit has
        // ENGINE_REPAIR=php, so no farm test starts an interpreter for it
        config(['engines.mesh.supports_check' => true]);
        $this->app->forgetInstance(SupportsCheck::class);
        $this->assertFalse(app(SupportsCheck::class)->enabled());
        config(['engines.repair' => 'trimesh']);
        $this->app->forgetInstance(SupportsCheck::class);
        $this->assertTrue(app(SupportsCheck::class)->enabled());
        config(['engines.mesh.supports_check' => false]);
        $this->app->forgetInstance(SupportsCheck::class);
        $this->assertFalse(app(SupportsCheck::class)->enabled());
    }

    /** A box with the cube's eight corners moved: bottom `$b` mm square, top `$t` mm square at height `$h`, the bottom lifted to `$z`. */
    private function frustum(float $b, float $t, float $h, float $z = 0.0): array
    {
        $v = [
            [-$b / 2, -$b / 2, $z], [$b / 2, -$b / 2, $z], [$b / 2, $b / 2, $z], [-$b / 2, $b / 2, $z],
            [-$t / 2, -$t / 2, $z + $h], [$t / 2, -$t / 2, $z + $h], [$t / 2, $t / 2, $z + $h], [-$t / 2, $t / 2, $z + $h],
        ];
        $faces = [[0, 2, 1], [0, 3, 2], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [0, 4, 7], [0, 7, 3], [1, 2, 6], [1, 6, 5]];

        return array_map(fn ($f) => [$v[$f[0]], $v[$f[1]], $v[$f[2]]], $faces);
    }

    private function stl(string $name, array ...$solids): string
    {
        $tris = array_merge(...$solids);
        $path = $this->dir.'/'.$name.'.stl';
        $fh = fopen($path, 'wb');
        fwrite($fh, str_pad($name, 80, "\0").pack('V', count($tris)));
        foreach ($tris as [$a, $b, $c]) {
            fwrite($fh, pack('f3', 0, 0, 0).pack('f3', ...$a).pack('f3', ...$b).pack('f3', ...$c).pack('v', 0));
        }
        fclose($fh);

        return $path;
    }

    /** The tool itself on shapes whose answer Orca 2.5 gave on 12 Nov 2026 (docs/AL.md §22). Needs Python with numpy. */
    public function test_the_tool_on_real_shapes(): void
    {
        $python = new PythonTool((array) config('engines.python'));
        try {
            $probe = $python->runScript('supports_tool.py', ['check', $this->stl('cube', $this->frustum(20, 20, 20)), '25', '0.2'], 60);
        } catch (\Throwable $e) {
            $this->markTestSkipped('The supports check needs Python with numpy: '.mb_substr($e->getMessage(), 0, 120));
        }
        if (($probe['ok'] ?? false) !== true) {
            $this->markTestSkipped('The supports check needs Python with numpy: '.json_encode($probe));
        }
        $ask = fn (string $path, string $threshold = '25') => $python->runScript('supports_tool.py', ['check', $path, $threshold, '0.2'], 60);
        $same = fn (array $r, bool $needed, string $reason, string $what) => $this->assertSame([$needed, $reason], [$r['needed'], $r['reason']], $what.' '.json_encode($r));

        $same($probe, false, 'none', 'a cube');
        $same($ask($this->stl('widening45', $this->frustum(10, 30, 10))), false, 'none', 'walls leaning out at 45° from a 10 mm base');
        $same($ask($this->stl('narrowing', $this->frustum(30, 10, 10))), false, 'none', 'a pyramid stump: nothing looks down');
        $same($ask($this->stl('flat22', $this->frustum(10, 60, 10))), true, 'overhang', 'walls at 22° from the horizontal');
        $same($ask($this->stl('flat28', $this->frustum(10, 47.6, 10))), true, 'overhang', '28°: inside the margin above the threshold');
        $same($ask($this->stl('steep32', $this->frustum(10, 42, 10))), false, 'none', '32°: clear of threshold and margin');
        $same($ask($this->stl('steep32', $this->frustum(10, 42, 10)), '35'), true, 'overhang', 'the same walls for a printer whose threshold is 35°');
        $same($ask($this->stl('tip', $this->frustum(0, 30, 15))), true, 'base', 'a pyramid on its tip: Orca props it up as a sharp tail');
        $same($ask($this->stl('small_base', $this->frustum(5, 30, 15))), true, 'base', 'leaning walls from a 5 mm base: a sharp tail too (under 6 mm)');
        $same($ask($this->stl('good_base', $this->frustum(9, 30, 15))), false, 'none', 'from a 9 mm base: none');
        $same($ask($this->stl('ceiling', $this->frustum(20, 20, 20), $this->frustum(40, 40, 5, 20))), true, 'overhang', 'a slab on a post: its underside is a ceiling');
        $same($ask($this->stl('spike', $this->frustum(20, 20, 20), $this->frustum(0, 12, 10, 20))), true, 'hanging', 'a spike that starts on top of a box');
        $same($ask($this->stl('floating', $this->frustum(20, 20, 20), $this->frustum(10, 10, 5, 30))), true, 'overhang', 'a box in the air');

        $table = $this->dir.'/table.stl';
        MeshFixtures::tableStl($table);
        $this->assertTrue($ask($table)['needed'], 'a table: the underside of its top');
        $ascii = $this->dir.'/ascii.stl';
        MeshFixtures::cubeStlAscii($ascii);
        $same($ask($ascii), true, 'format', 'not a binary STL: no telling');
    }
}
