<?php

namespace Tests\Feature;

use App\Domain\Files\PaintedColors;
use App\Domain\Tools\ModelPainter;
use App\Engines\Generator\FakeGenerator;
use App\Engines\Repair\PythonTool;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A generated bust or pet goes to print in colours without the paint tool (Roman, 24 Nov 2026): the figure light, the
 * base dark, the raised name gold; the engine names every triangle's part and the file gets a painted twin
 * (ModelPainter::auto) that the order prints by its colours, or (unticked) in one.
 */
class AutoColorsTest extends TestCase
{
    use RefreshDatabase;

    private string $glb = '';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 20, 'ai.daily_limits.generate_user' => 20]);
        Storage::fake('models');
        Storage::fake('local');
        Storage::fake('farm');
        Mail::fake();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'person', 'reason' => ''])]]])]);
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        FakeGenerator::$glb = null;
        if ($this->glb !== '') {
            @unlink($this->glb);
        }
        parent::tearDown();
    }

    private function python(): string
    {
        return (string) (config('engines.python.bin') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'));
    }

    private function generate(): ModelFile
    {
        $probe = trim((string) shell_exec(escapeshellcmd($this->python()).' -c "import manifold3d, trimesh, scipy; print(1)" 2>&1'));
        if ($probe !== '1') {
            $this->markTestSkipped('manifold3d / trimesh / scipy are not installed.');
        }
        $stl = sys_get_temp_dir().'/mp_auto_'.uniqid().'.stl';
        $this->glb = sys_get_temp_dir().'/mp_auto_'.uniqid().'.glb';
        shell_exec(escapeshellcmd($this->python()).' '.escapeshellarg(base_path('tests/Support/synthetic_bust.py')).' '.escapeshellarg($stl));
        $code = 'import sys,trimesh,numpy as np; m=trimesh.load(sys.argv[1]); m.apply_transform(trimesh.transformations.rotation_matrix(-np.pi/2,[1,0,0])); m.export(sys.argv[2])';
        shell_exec(escapeshellcmd($this->python()).' -c '.escapeshellarg($code).' '.escapeshellarg($stl).' '.escapeshellarg($this->glb));
        @unlink($stl);
        FakeGenerator::$glb = $this->glb;
        $r = $this->actingAs($this->user)->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'pedestal' => 'round'], ['Accept' => 'application/json'])->assertCreated();

        return ModelFile::where('uuid', $r->json('generation.file.uuid'))->firstOrFail();
    }

    /** The S1 holds white; black and gold go into two of its free positions, and it takes orders. */
    private function spools(): array
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        [$runner] = FarmAgent::issue('test agent');
        $s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $runner->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE]);
        $white = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail();
        $pla = FarmColor::findOrFail($white->farm_color_id)->farm_material_id;
        $free = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->get();
        $black = FarmColor::create(['farm_material_id' => $pla, 'name' => 'Černá test', 'hex' => '#1b1b1d', 'enabled' => true]);
        $gold = FarmColor::create(['farm_material_id' => $pla, 'name' => 'Zlatá test', 'hex' => '#d4a531', 'enabled' => true]);
        $free[0]->update(['farm_color_id' => $black->id, 'remaining_g' => 800, 'enabled' => true]);
        $free[1]->update(['farm_color_id' => $gold->id, 'remaining_g' => 800, 'enabled' => true]);

        return [(int) $white->farm_color_id, $black->id, $gold->id];
    }

    /** @param  list<string>  $hexes */
    private function sorted(array $hexes): array
    {
        $hexes = array_map('strtolower', $hexes);
        sort($hexes);

        return $hexes;
    }

    public function test_a_generated_bust_gets_a_painted_twin_and_a_named_base_three_colours(): void
    {
        $src = $this->generate();
        $twin = ModelPainter::twinOf($src);
        $this->assertNotNull($twin, 'the engine named the parts and the twin was made');
        $twin->refresh();
        $this->assertSame(ModelFile::STATUS_READY, $twin->status, (string) $twin->error);
        $this->assertSame(ModelPainter::KIND, $twin->origin);
        $this->assertTrue((bool) ($twin->tool_params['paint']['auto'] ?? false));
        // the figure light and the base dark, read back from the written 3MF
        $this->assertSame(['#1b1b1d', '#f4f4f2'], $this->sorted(PaintedColors::printed($twin)));
        $this->assertTrue(PaintedColors::painted($twin));
        $this->assertFileDoesNotExist($src->absolutePath().'.parts');          // the engine's answer is not kept twice
        $this->getJson(route('api.files.show', $src->uuid))->assertOk()->assertJsonPath('file.print_uuid', $twin->uuid);
        // the customer's models list the bust once, not its twin
        $listed = app(\App\Domain\Account\ModelLibrary::class)->query($this->user)->pluck('uuid')->all();
        $this->assertContains($src->uuid, $listed);
        $this->assertNotContains($twin->uuid, $listed);
        // "Vybarvit model" starts from these colours, not from a grey model
        $this->actingAs($this->user)->get(route('tools.paint', ['from' => $src->uuid]))->assertOk()->assertSee($twin->uuid, false);

        // a new base with a name: the name a third colour
        $res = $this->actingAs($this->user)->postJson('/api/files/'.$src->uuid.'/pedestal', ['type' => 'classic', 'name' => 'EVA', 'tidy' => false])->assertCreated();
        $second = ModelFile::where('uuid', $res->json('file.uuid'))->firstOrFail();
        $named = ModelPainter::twinOf($second);
        $this->assertNotNull($named);
        $this->assertSame(ModelFile::STATUS_READY, $named->refresh()->status, (string) $named->error);
        $this->assertSame(['#1b1b1d', '#d4a531', '#f4f4f2'], $this->sorted(PaintedColors::printed($named)));
        $res->assertJsonPath('file.print_uuid', $named->uuid);
    }

    public function test_the_twin_orders_by_its_colours_and_switched_on_again_picks_the_nearest_spools(): void
    {
        [$white, $black] = $this->spools();
        $twin = ModelPainter::twinOf($this->generate());
        $this->assertNotNull($twin);

        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $twin->uuid])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertTrue($order->isByParts(), 'in its colours from the start');
        // every part from the spool of its own colour; the page knows the design's colour of every part (the preview, the picks)
        $state = $this->actingAs($this->user)->getJson(route('farm.orders.status', $order))->assertOk();
        $want = ['#f4f4f2' => $white, '#1b1b1d' => $black];
        $hexes = array_column($state->json('parts'), 'hex', 'part');
        $this->assertSame(['#1b1b1d', '#f4f4f2'], $this->sorted(array_values($hexes)));
        // the parts are named by what they are, and the box says "print in colours", not "in the painted colours"
        $labels = array_column($state->json('parts'), 'label', 'part');
        foreach ($hexes as $part => $hex) {
            $this->assertSame(strtolower($hex) === '#1b1b1d' ? 'Podstavec' : 'Figura', $labels[$part]);
        }
        $state->assertJsonPath('painted_auto', true);
        // before its parts are made the preview takes the painted model by its colours, ranges named after the parts
        $state->assertJsonPath('pieces_url', route('api.files.pieces', $twin->uuid));
        $pieces = json_decode((string) $this->actingAs($this->user)->get(route('api.files.pieces', $twin->uuid))->assertOk()->headers->get('X-Pieces'), true);
        $this->assertSame(array_keys($hexes), array_column($pieces, 'name'));
        $this->assertSame((int) $twin->refresh()->triangles, $pieces[count($pieces) - 1]['tris'][1]);
        $byPart = fn (FarmOrder $o) => array_map('intval', array_column($o->partPlates(), 'color_id', 'part'));
        $this->assertSame(array_map(fn ($h) => $want[strtolower($h)], $hexes), $byPart($order));

        // in one colour, then in colours again: every part its own spool again, not all the first colour (Baku, order 509)
        $this->actingAs($this->user)->postJson(route('farm.orders.reslice', $order), ['quality' => $order->quality, 'strength' => $order->strength, 'by_parts' => false])->assertOk();
        $this->assertFalse($order->refresh()->isByParts());
        $this->actingAs($this->user)->postJson(route('farm.orders.reslice', $order), ['quality' => $order->quality, 'strength' => $order->strength, 'by_parts' => true])->assertOk();
        $this->assertSame(array_map(fn ($h) => $want[strtolower($h)], $hexes), $byPart($order->refresh()));
    }

    public function test_without_the_switch_there_is_no_twin(): void
    {
        config(['engines.paint.auto' => false]);
        $src = $this->generate();
        $this->assertNull(ModelPainter::twinOf($src));
        $this->assertSame(ModelFile::STATUS_READY, $src->refresh()->status);
        $this->getJson(route('api.files.show', $src->uuid))->assertOk()->assertJsonPath('file.print_uuid', null);
    }
}
