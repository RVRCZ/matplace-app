<?php

namespace Tests\Feature;

use App\Domain\Tools\ReliefGenerator;
use App\Engines\DTO\SliceParams;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Lithophane / relief from a photo: real run when Python with numpy + Pillow is installed, otherwise skipped. */
class ReliefToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders(): void
    {
        $this->get('/tools/relief')->assertOk()->assertSee('name="shape-pick"', false)->assertSee('name="socket"', false)->assertSee(__('relief.make.lamp'));
        $this->get('/es/tools/relief')->assertOk()->assertSee(__('relief.shape.heart', [], 'es'));
    }

    public function test_validation(): void
    {
        $this->postJson('/api/tools/relief', [])->assertStatus(422);
        $this->post('/api/tools/relief', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $photo = UploadedFile::fake()->image('a.jpg', 100, 100);
        $this->post('/api/tools/relief', ['photo' => $photo, 'shape' => 'star'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('shape');
        $this->post('/api/tools/relief', ['photo' => $photo, 'socket' => 'gu10'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('socket');
        $this->post('/api/tools/relief', ['photo' => $photo, 'silhouette' => '../x'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('silhouette');
        $this->post('/api/tools/relief', ['photo' => $photo, 'gamma' => 3], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('gamma');
    }

    public function test_clean_keeps_the_lamp_and_the_plate_apart(): void
    {
        $c = ReliefGenerator::clean(['shape' => 'cylinder', 'width' => 350, 'frame' => 3, 'stand' => 1, 'hang' => 'eyelet', 'socket' => 'e14']);
        $this->assertSame(350.0, $c['width']);
        $this->assertSame(0.0, $c['frame']);
        $this->assertFalse($c['stand']);
        $this->assertSame('none', $c['hang']);
        $this->assertSame('e14', $c['socket']);
        // a plate: the lamp's socket does not apply, the width stops at the bed, the old tick of a frame means 2 mm
        $c = ReliefGenerator::clean(['mode' => 'relief', 'width' => 350, 'frame' => true, 'socket' => 'led', 'hang' => 'hole']);
        $this->assertSame(300.0, $c['width']);
        $this->assertSame(2.0, $c['frame']);
        $this->assertSame('e27', $c['socket']);
        $this->assertSame('hole', $c['hang']);
        $this->assertFalse($c['standing']);
        $this->assertSame(1.2, $c['min_thickness']);
    }

    public function test_hints_and_tree_supports_by_kind(): void
    {
        $f = new ModelFile(['origin' => 'tool', 'origin_ref' => 'lithophane']);
        $this->assertSame('lithophane', $f->kind());
        $this->assertSame(100, $f->printHints()['infill']);
        $g = new ModelFile(['origin' => 'generated', 'origin_ref' => 'tok']);
        $this->assertTrue($g->printHints()['supports']);
        $this->assertTrue($g->wantsTreeSupports());
        // the pipeline flag wins over whatever is stored with the calculation
        $this->assertTrue(SliceParams::fromArray(['tree' => true] + ['material' => 'PLA', 'tree' => false])->treeSupports);
        $this->assertSame([], (new ModelFile(['origin' => 'upload']))->printHints());
    }

    public function test_lithophane_stands_and_relief_lies(): void
    {
        if (! app(ReliefGenerator::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);

        $r = $this->post('/api/tools/relief', ['photo' => UploadedFile::fake()->image('Babička.jpg', 400, 300), 'mode' => 'lithophane', 'width' => 80], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('file.kind', 'lithophane')->assertJsonPath('file.hints.infill', 100);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $this->assertEqualsWithDelta(80, $file->bbox['x'], 1.0);
        $this->assertEqualsWithDelta(3.0, $file->bbox['y'], 0.05);     // standing: thickness along Y
        $this->assertGreaterThan(50, $file->bbox['z']);
        $this->assertSame('lithophane-babicka.stl', $file->original_name);
        $this->assertSame(12, $file->tool_params['report']['shades']);    // 0.8 … 3.0 mm in 0.2 mm layers
        $this->assertSame('rect', $file->tool_params['shape']);

        // photo gift: a lithophane with its own desk stand, printed beside it
        $r = $this->post('/api/tools/relief', ['photo' => UploadedFile::fake()->image('Děda.jpg', 400, 300), 'mode' => 'lithophane', 'width' => 80, 'stand' => 1], ['Accept' => 'application/json']);
        $r->assertCreated();
        $withStand = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertGreaterThan(80 + 8 + 40, $withStand->bbox['x']);                  // plate + gap + stand
        $this->assertNotContains('multiple_shells', $r->json('file.issues') ?? []);

        $r = $this->post('/api/tools/relief', ['photo' => UploadedFile::fake()->image('a.png', 300, 300), 'mode' => 'relief', 'width' => 60, 'frame' => 1], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('file.kind', 'relief');
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(4.0, $file->bbox['z'], 0.05);     // lying: thickness along Z (the frame is the highest point)

        $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PLA'])->assertCreated()->assertJsonPath('calculation.status', 'done');
    }

    public function test_shapes_hanging_and_the_lamp(): void
    {
        if (! app(ReliefGenerator::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);
        $photo = fn (string $name = 'a.jpg') => UploadedFile::fake()->image($name, 400, 300);

        // a heart with an eyelet, lying (relief): the plate's size is what was asked, the eyelet reaches above it
        $r = $this->post('/api/tools/relief', ['photo' => $photo(), 'mode' => 'relief', 'shape' => 'heart', 'width' => 80, 'height' => 70, 'frame' => 2, 'hang' => 'eyelet'], ['Accept' => 'application/json']);
        $r->assertCreated();
        $heart = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $heart->status);
        $this->assertEqualsWithDelta(80, $heart->bbox['x'], 1.0);
        $this->assertEqualsWithDelta(70 + 7, $heart->bbox['y'], 1.5);                   // the ring (Ø 10) sits 2 mm above the plate
        $this->assertEqualsWithDelta(4.0, $heart->bbox['z'], 0.05);
        $this->assertNotContains('multiple_shells', $r->json('file.issues') ?? []);
        $this->assertSame('heart', $heart->tool_params['shape']);
        $this->assertSame('eyelet', $heart->tool_params['hang']);

        // a circle with a hole, standing: the hole goes through the thickness (Y), so the width stays the plate's
        $r = $this->post('/api/tools/relief', ['photo' => $photo(), 'shape' => 'circle', 'width' => 60, 'frame' => 3, 'hang' => 'hole'], ['Accept' => 'application/json']);
        $r->assertCreated();
        $circle = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(60, $circle->bbox['x'], 1.0);
        $this->assertEqualsWithDelta(60, $circle->bbox['z'], 1.0);                      // a circle is as high as wide whatever the photo's ratio
        $this->assertEqualsWithDelta(3.0, $circle->bbox['y'], 0.05);
        // a circle holds less than its square: the hole and the round outline take volume away
        $this->assertLessThan(60 * 60 * 3.0 * 0.85, $circle->volume_mm3);

        // the frame cannot eat the picture
        $this->post('/api/tools/relief', ['photo' => $photo(), 'width' => 40, 'frame' => 12], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('reason', 'frame_too_wide');
        // a custom silhouette needs a picture
        $this->post('/api/tools/relief', ['photo' => $photo(), 'shape' => 'custom'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('reason', 'silhouette');

        // the lamp: a tube of the circumference asked, a floor with the E27 hole, one closed shell
        $r = $this->post('/api/tools/relief', ['photo' => $photo('Lampa.jpg'), 'shape' => 'cylinder', 'width' => 160, 'height' => 60, 'socket' => 'e27', 'contrast' => 20, 'gamma' => 1.3], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('file.kind', 'lithophane');
        $lamp = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $lamp->status);
        $this->assertSame('lamp-lampa.stl', $lamp->original_name);
        $d = 160 / M_PI;
        $this->assertEqualsWithDelta($d + 2 * 3.0, $lamp->bbox['x'], 1.0);            // the relief adds up to the thickest wall on both sides
        $this->assertEqualsWithDelta($d + 2 * 3.0, $lamp->bbox['y'], 1.0);
        $this->assertEqualsWithDelta(62.0, $lamp->bbox['z'], 0.5);                      // 60 of picture on a 2 mm floor
        $this->assertNotContains('multiple_shells', $r->json('file.issues') ?? []);
        $this->assertSame('e27', $lamp->tool_params['socket']);
        $this->assertEqualsWithDelta(round($d, 1), $lamp->tool_params['report']['diameter'], 0.2);
        // the floor's hole: less material than a closed floor would give
        $r = $this->post('/api/tools/relief', ['photo' => $photo(), 'shape' => 'cylinder', 'width' => 160, 'height' => 60, 'socket' => 'none'], ['Accept' => 'application/json']);
        $r->assertCreated();
        $closed = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertGreaterThan($lamp->volume_mm3 + M_PI * 20 * 20 * 2 * 0.9, $closed->volume_mm3);
        // a socket wider than the tube is refused
        $this->post('/api/tools/relief', ['photo' => $photo(), 'shape' => 'cylinder', 'width' => 120, 'socket' => 'e27'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('reason', 'socket_too_big');
    }
}
