<?php

namespace Tests\Feature;

use App\Domain\Farm\BigModel;
use App\Domain\Farm\OrderService;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Mesh\MeshDecimator;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\ScriptRunner;
use App\Models\Calculation;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;
use Tests\Unit\PlateLayoutTest;

/**
 * Several copies of a fine piece (TODO #68, docs/AL.md §11.9): the piece is thinned once before it is copied onto the
 * plate, never the plate as a whole; the plates of copies are built from the thinned piece and its thinning is
 * remembered for the slice after a change of colour; a plate of pieces under the threshold is not thinned at all; and
 * an order of several copies is judged big by the expected slice of all of them. Fake slicer, a stand-in for the
 * Python thinning, sync queue.
 */
final class FarmDecimateCopiesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $dir;

    /** @var list<array{0: string, 1: array, 2: int}> what the thinning script was asked */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        $this->dir = sys_get_temp_dir().'/mp_copies_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /**
     * The thinning as a stand-in: above `$above` triangles the "thinned" piece is an octagonal prism of 28 triangles
     * inscribed in the box of the piece it replaces, so the plates built from it can be told apart by their triangle count.
     */
    private function fakeThinning(int $above): void
    {
        $calls = &$this->calls;
        $python = new class($calls) implements ScriptRunner
        {
            public function __construct(private array &$calls) {}

            public function runScript(string $script, array $args, int $timeout = 90): array
            {
                $this->calls[] = [$script, $args, $timeout];
                [$lo, $hi] = StlFile::bounds($args[0]);
                $w = $hi[0] - $lo[0];
                $d = $hi[1] - $lo[1];
                $outline = [];
                foreach ([[0.3, 0], [0.7, 0], [1, 0.3], [1, 0.7], [0.7, 1], [0.3, 1], [0, 0.7], [0, 0.3]] as [$fx, $fy]) {
                    $outline[] = [round($lo[0] + $fx * $w, 3), round($lo[1] + $fy * $d, 3)];
                }
                PlateLayoutTest::prism($args[1], $outline, $hi[2] - $lo[2]);
                if (abs($lo[2]) > 1e-6) {
                    StlFile::translate($args[1], $args[1].'.moved', [0.0, 0.0, $lo[2]]);
                    rename($args[1].'.moved', $args[1]);
                }

                return ['ok' => true, 'before' => StlFile::triangleCount($args[0]), 'after' => 28, 'deviation_mm' => 0.01, 'peak_mm' => 0.02, 'watertight' => true, 'seconds' => 0.3];
            }
        };
        $this->app->instance(MeshDecimator::class, new MeshDecimator($python, ['decimate_above' => $above, 'decimate_cache' => $this->dir.'/cache'] + (array) config('engines.mesh')));
    }

    private function upload(float $size = 100.0): string
    {
        $path = $this->dir.'/cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $size);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function order(array $body): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', $body)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function state(FarmOrder $order): array
    {
        return $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    private function triangles(string $relative): int
    {
        return iterator_count(StlFile::triangles(Storage::disk('farm')->path($relative)));
    }

    public function test_a_plate_of_pieces_under_the_threshold_is_not_thinned_although_their_sum_is_over_it(): void
    {
        // a cube has 12 triangles, four of them 48: the piece is under, the plate over – and nothing is thinned
        $this->fakeThinning(above: 40);
        $order = $this->order(['file' => $this->upload(20.0), 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, json_encode($order->check));
        $this->assertSame([], $this->calls, 'the thinning script never ran');
        $this->assertSame(48, $this->triangles($order->print_stl_path), 'the plate holds the four cubes as they are');
        $this->assertArrayNotHasKey('decimate_s', $order->timings);
        $this->assertArrayNotHasKey('decimate_skipped', $order->timings);
        $this->assertArrayNotHasKey('triangles_in', $order->timings);
    }

    public function test_a_fine_piece_is_thinned_once_and_the_plates_and_the_slice_after_a_change_of_colour_take_the_thinned_piece(): void
    {
        $this->fakeThinning(above: 10);
        // a 100 mm cube: four on a plate of the Kobra S1, a fifth on a plate of its own; the plates are built from the
        // thinned piece (28 triangles each), the piece itself (print.stl, 12) stays for the preview and the record
        $order = $this->order(['file' => $this->upload(), 'copies' => 5]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, json_encode($order->check));
        $this->assertSame([2, 4, 1], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertCount(1, $this->calls, 'the piece was thinned once for the full plate and the last one alike');
        $this->assertSame(4 * 28, $this->triangles($order->print_stl_path), 'the full plate is four thinned pieces');
        $this->assertSame(28, $this->triangles($order->dir().'/rest.stl'), 'the last plate is one thinned piece');
        $this->assertSame(12, $this->triangles($order->dir().'/print.stl'), 'the piece as prepared is untouched');
        $this->assertFileDoesNotExist(Storage::disk('farm')->path($order->dir().'/print.thin.stl'), 'the thinned piece is not kept with the order');
        $t = $order->timings;
        $this->assertSame([12, 28, 0.01], [$t['triangles_in'], $t['triangles_sliced'], $t['decimate_deviation_mm']]);
        $this->assertArrayHasKey('decimate_s', $t);
        $this->assertArrayNotHasKey('decimate_cached', $t);
        $this->assertArrayNotHasKey('decimate_skipped', $t);
        $this->assertSame(['0.05', '0.2'], array_slice($this->calls[0][1], 3), 'the limits of the standard layer');
        $this->assertSame(min((int) config('engines.mesh.decimate_timeout'), BigModel::estimateSeconds(12, null, 5)), $this->calls[0][2], 'the time budget of the piece counts the copies that will be sliced from it');
        $this->assertSame(4 * 28, $order->check['mesh']['triangles']);

        // another colour of the same machine: the piece is thinned from the remembered copy, no Python
        $state = $this->state($order);
        $other = collect($state['colors'])->first(fn ($c) => $c['slot'] !== $state['slot']);
        $this->assertNotNull($other);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => $state['quality'], 'strength' => $state['strength'], 'unit' => $state['unit'], 'copies' => 5, 'supports' => $state['supports_mode'], 'slot' => $other['slot']])->assertOk()->assertJsonPath('status', 'sliced');
        $order->refresh();
        $this->assertCount(1, $this->calls, 'the second thinning came from the remembered copy');
        $this->assertTrue($order->timings['decimate_cached']);
        $this->assertSame($order->plate_copies * 28, $this->triangles($order->print_stl_path), 'the plate (of whichever machine holds the colour) is built from the thinned piece again');

        // one piece of the same file: the slicer gets the thinned piece, the order keeps the piece as prepared
        $one = $this->order(['file' => $order->modelFile->uuid, 'copies' => 1]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $one->status);
        $this->assertCount(1, $this->calls);
        $this->assertTrue($one->timings['decimate_cached']);
        $this->assertSame(28, $one->timings['triangles_sliced']);
        $this->assertSame(12, $this->triangles($one->print_stl_path));
        $this->assertSame(12, $one->check['mesh']['triangles']);
    }

    public function test_an_order_by_parts_thins_every_part_before_its_copies_are_laid_out(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $this->fakeThinning(above: 10);
        // the box with a lid of FarmOrderFlowTest, the lid from a red spool of the same machine: a plate per part
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $white = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail()->color;
        $red = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'like', 'PLA%'))->where('id', '!=', $white->id)->firstOrFail();
        $red->update(['hex' => '#C01818', 'enabled' => true]);
        $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->firstOrFail()->update(['farm_color_id' => $red->id, 'remaining_g' => 800, 'enabled' => true]);
        $uuid = $this->actingAs($this->user)->postJson('/api/tools/param', ['kind' => 'box', 'params' => ['lid' => 1, 'inner_w' => 30, 'inner_d' => 20, 'inner_h' => 12]])->assertCreated()->json('file.uuid');
        $this->assertSame(['body', 'lid'], OrderService::designParts(ModelFile::where('uuid', $uuid)->firstOrFail()));

        $order = $this->order(['file' => $uuid, 'color' => $white->id, 'by_parts' => 1, 'part_color' => ['lid' => $red->id], 'copies' => 3]);
        $this->assertTrue($order->isByParts());
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, json_encode($order->check));
        $this->assertSame(2, $order->plates);
        $this->assertCount(2, $this->calls, 'every part thinned once, before its copies were laid out');
        foreach ($order->partPlates() as $i => $plate) {
            $this->assertSame(3, $plate['copies']);
            $this->assertSame(3 * 28, $this->triangles($plate['stl_path']), 'the plate of the part '.$plate['part'].' is built from the thinned part');
            $this->assertFileDoesNotExist(Storage::disk('farm')->path($order->dir().'/part-'.($i + 1).'.stl.thin'), 'the thinned part is not kept with the order');
            $this->assertGreaterThan(28, $this->triangles($order->dir().'/part-'.($i + 1).'.stl'), 'the part as prepared is untouched');
        }
        $this->assertSame([28, 28], [$order->timings['triangles_sliced_1'], $order->timings['triangles_sliced_2']]);
        $this->assertArrayNotHasKey('decimate_skipped_1', $order->timings);
        $this->assertArrayHasKey('decimate_s', $order->timings);
    }

    public function test_the_verdict_on_a_big_model_counts_the_copies(): void
    {
        // a cube of 12 triangles made to weigh 5 s per triangle-million would slice in 60 s alone, 240 s four times
        config(['farm.big_model.seconds_per_million' => 5_000_000]);
        $uuid = $this->upload(20.0);
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();

        $one = BigModel::judge($file, null, 1);
        $this->assertSame([false, null, 12, 1, 120], [$one['big'], $one['reason'], $one['triangles'], $one['copies'], $one['estimate_s']], 'one piece under the threshold is small, as before (the floor of two minutes)');
        $four = BigModel::judge($file, null, 4);
        $this->assertSame([true, 'copies', 4, 240], [$four['big'], $four['reason'], $four['copies'], $four['estimate_s']], 'four pieces: the expected slice of the plate, four times the piece');
        $this->assertSame(2 * $four['estimate_s'], BigModel::estimateSeconds(12, null, 8), 'the estimate grows in step with the copies');

        // the order: one piece is sliced at once, four wait for the choices and promise the minutes of all four
        $single = $this->order(['file' => $uuid, 'copies' => 1]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $single->status);
        $this->assertFalse($single->big_model);
        $this->assertNull($single->slice_estimate_s);
        $many = $this->order(['file' => $uuid, 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $many->status);
        $this->assertTrue($many->big_model);
        $this->assertSame(240, $many->slice_estimate_s);
        $this->assertSame(4, $this->state($many)['slice_minutes']);
        $this->assertStringContainsString('the slice waits for the choices', (string) $many->events()->first()->note);

        // the threshold is the same setting as for a single big piece; a change of the copies later does not flip the verdict
        config(['engines.mesh.big_slice_s' => 300]);
        $this->assertFalse(BigModel::judge($file, null, 4)['big']);
        $this->assertSame(FarmOrder::STATUS_SLICED, $this->order(['file' => $uuid, 'copies' => 4])->status);
        config(['engines.mesh.big_slice_s' => 60]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$many->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'copies' => 1])->assertOk()->assertJsonPath('status', 'sliced');
        $this->assertTrue($many->fresh()->big_model);

        // a calculation that measured the slice of one piece: four pieces take four slices and the farm's margin
        config(['farm.big_model.seconds_per_million' => 150]);
        $token = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->assertCreated()->json('calculation.token');
        $calc = Calculation::where('token', $token)->firstOrFail();
        $calc->forceFill(['timings' => ['slice_s' => 30.0] + (array) $calc->timings])->save();
        $this->assertSame(FarmOrder::STATUS_SLICED, $this->order(['calc' => $token, 'copies' => 1])->status, '30 s is a quick slice: one piece is sliced at once');
        $fourOfCalc = $this->order(['calc' => $token, 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $fourOfCalc->status);
        $this->assertSame((int) ceil(30.0 * 4 * 1.2 + 15), $fourOfCalc->slice_estimate_s, 'four slices of the calculation\'s 30 s, the farm\'s margin once');
    }
}
