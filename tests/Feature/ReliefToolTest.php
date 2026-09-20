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
        $this->get('/tools/relief')->assertOk();
        $this->get('/tools/relief?lang=es')->assertOk();
    }

    public function test_validation(): void
    {
        $this->postJson('/api/tools/relief', [])->assertStatus(422);
        $this->post('/api/tools/relief', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
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
}
