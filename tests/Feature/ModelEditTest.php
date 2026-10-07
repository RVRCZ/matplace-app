<?php

namespace Tests\Feature;

use App\Domain\Tools\ModelEditor;
use App\Engines\Mesh\StlTopology;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Editing a model file: the split (planes the bed asks for, pieces laid cut face down, pins or dovetail keys, numbers,
 * the map), the analysis before it, the job in the queue, the pieces as files, the page.
 */
class ModelEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        if (! app(ModelEditor::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
    }

    /** A box of w × d × h mm as an upload; the model files of the tests. */
    private function box(float $w, float $d, float $h, string $name = 'bar.stl'): string
    {
        $path = sys_get_temp_dir().'/mp_box_'.uniqid().'.stl';
        $v = [[0, 0, 0], [$w, 0, 0], [$w, $d, 0], [0, $d, 0], [0, 0, $h], [$w, 0, $h], [$w, $d, $h], [0, $d, $h]];
        $faces = [[0, 2, 1], [0, 3, 2], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [0, 4, 7], [0, 7, 3], [1, 2, 6], [1, 6, 5]];
        $fh = fopen($path, 'wb');
        fwrite($fh, str_pad('box', 80, "\0").pack('V', count($faces)));
        foreach ($faces as [$a, $b, $c]) {
            fwrite($fh, pack('f3', 0, 0, 0).pack('f3', ...$v[$a]).pack('f3', ...$v[$b]).pack('f3', ...$v[$c]).pack('v', 0));
        }
        fclose($fh);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, $name, null, null, true)])->assertCreated()->json('file.uuid');
    }

    public function test_the_page_renders_in_three_languages_and_is_in_the_catalogue(): void
    {
        foreach (['cs' => '/tools/split', 'en' => '/en/tools/split', 'es' => '/es/tools/split'] as $lang => $path) {
            app()->setLocale($lang);
            $page = $this->get($path)->assertOk()->assertSee(__('tools.split.title'))->assertSee(__('edit.split.lead'))->assertSee('data-module="edit"', false)->assertSee('window.MP_EDIT', false)->assertSee('edit-drop', false)->assertSee(__('edit.o.joint.dovetail'));
            $this->assertDoesNotMatchRegularExpression('/>\s*(edit|tools|toolpage|param)\.[a-z_.]+\s*</', $page->getContent(), $lang);
        }
        app()->setLocale('cs');
        $this->get('/tools')->assertOk()->assertSee(route('tools.split'));
        $this->get('/tools/split?from=not-a-uuid')->assertOk()->assertSee('from: null', false);
    }

    public function test_the_analysis_plans_the_fewest_cuts_for_the_bed(): void
    {
        $uuid = $this->box(300, 60, 40);
        $a = $this->postJson('/api/files/'.$uuid.'/edit/analysis', ['op' => 'split'])->assertOk()->json('analysis');
        $this->assertFalse($a['fits']);
        $this->assertSame([150.0], array_map('floatval', $a['planes']['x']));            // one cut across the width is enough for a 240 mm bed
        $this->assertSame([], $a['planes']['y']);
        $this->assertSame(2, $a['pieces']);
        // a smaller bed asks for more; the visitor's own planes are kept where they put them
        $small = $this->postJson('/api/files/'.$uuid.'/edit/analysis', ['op' => 'split', 'bed' => 'custom', 'bed_x' => 100, 'bed_y' => 100, 'bed_z' => 100])->assertOk()->json('analysis');
        $this->assertSame(2, count($small['planes']['x']));                                   // 90 mm usable: three pieces of 100 mm stand 100 mm high
        $this->assertSame([90.0, 90.0, 100.0], array_map('floatval', $small['bed']));
        $own = $this->postJson('/api/files/'.$uuid.'/edit/analysis', ['op' => 'split', 'planes' => ['x' => [100, 200]]])->assertOk()->json('analysis');
        $this->assertSame([100.0, 200.0], array_map('floatval', $own['planes']['x']));
        // a model that fits says so
        $cube = $this->box(20, 20, 20, 'cube.stl');
        $this->assertTrue($this->postJson('/api/files/'.$cube.'/edit/analysis', ['op' => 'split'])->assertOk()->json('analysis.fits'));
        $this->postJson('/api/files/'.$cube.'/edit/analysis', ['op' => 'hollow'])->assertStatus(422);
        // stored beside the model: the same answer again
        $this->assertSame($a, $this->postJson('/api/files/'.$uuid.'/edit/analysis', ['op' => 'split'])->assertOk()->json('analysis'));
    }

    public function test_a_split_is_a_new_file_of_pieces_laid_cut_face_down_with_pins_and_numbers(): void
    {
        $uuid = $this->box(300, 60, 40);
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'split', 'joint' => 'pins'])->assertCreated();
        $r->assertJsonPath('file.kind', 'split')->assertJsonPath('file.status', 'ready');        // the queue is synchronous in tests
        $this->assertSame('bar-split.stl', $r->json('file.name'));
        $this->assertSame(['piece_1', 'piece_2', 'pins'], $r->json('file.parts'));
        $edit = $r->json('file.edit');
        $this->assertSame('split', $edit['op']);
        $this->assertSame($uuid, $edit['source']);
        $this->assertSame(2, $edit['pieces']);
        $this->assertSame(2, $edit['pins']);
        $this->assertSame([150.0], array_map('floatval', $edit['planes']['x']));
        $this->assertCount(2, $edit['map']);
        $this->assertSame(['x', 1], $edit['map'][0]['down']);                                      // piece 1 lies on the face it was cut at
        $this->assertNotNull($edit['map'][0]['number_at']);
        $this->assertCount(1, $edit['joints']);
        $this->assertCount(2, $edit['joints'][0]['pins']);
        $this->assertCount(3, $edit['pieces_tris']);
        $this->assertSame($r->json('file.triangles'), end($edit['pieces_tris'])['tris'][1]);     // the processed file keeps the triangles in the tool's order
        $this->assertNotContains('multiple_shells', $r->json('file.issues'));

        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue($file->builtForPrinting());
        // the pieces stand on the bed 150 mm high (the cut face down), the pins beside them
        $this->assertEqualsWithDelta(150, $file->bbox['z'], 0.1);
        $each = $file->tool_params['each'];
        $this->assertEqualsWithDelta(150, $each[0][2], 0.1);
        $this->assertEqualsWithDelta(12, $each[2][2], 0.1);
        // what was cut is what there was: the pieces together are the box less the holes, the pins fill the holes
        $this->assertEqualsWithDelta(300 * 60 * 40, $file->volume_mm3, 300 * 60 * 40 * 0.01);
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        $this->assertGreaterThan(0, $file->timings['edit_s']);
        // every piece as its own file; a part the design does not have is not handed out
        foreach (['piece_1', 'piece_2', 'pins'] as $part) {
            $this->get('/api/tools/edit/'.$file->uuid.'/'.$part.'.stl')->assertOk();
        }
        $this->get('/api/tools/edit/'.$file->uuid.'/piece_9.stl')->assertNotFound();
        // the result opens its page again, which finds its source
        $this->get('/tools/split?from='.$file->uuid)->assertOk()->assertSee($file->uuid, false);
        $this->assertSame('split', $this->getJson('/api/files/'.$file->uuid)->json('file.tool.kind'));
    }

    public function test_dovetail_keys_and_plain_cuts_and_the_visitors_own_planes(): void
    {
        $uuid = $this->box(300, 60, 40);
        $keys = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'split', 'joint' => 'dovetail'])->assertCreated();
        $this->assertSame(['piece_1', 'piece_2', 'keys'], $keys->json('file.parts'));
        $this->assertSame(1, $keys->json('file.edit.keys'));
        $this->assertNotNull($keys->json('file.edit.joints.0.key'));

        $plain = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'split', 'joint' => 'none', 'planes' => ['x' => [100, 200]], 'numbers' => false])->assertCreated();
        $this->assertSame(['piece_1', 'piece_2', 'piece_3'], $plain->json('file.parts'));
        $this->assertSame([100.0, 200.0], array_map('floatval', $plain->json('file.edit.planes.x')));
        $this->assertNull($plain->json('file.edit.map.0.number_at'));
        $this->assertEqualsWithDelta(300 * 60 * 40, ModelFile::where('uuid', $plain->json('file.uuid'))->firstOrFail()->volume_mm3, 50);

        // a model that fits is not split; a bad joint is refused before anything runs
        $cube = $this->box(20, 20, 20, 'cube.stl');
        $made = $this->postJson('/api/files/'.$cube.'/edit', ['op' => 'split'])->assertCreated();
        $this->assertSame('failed', $made->json('file.status'));
        $this->assertStringStartsWith('fits_already', (string) $made->json('file.error'));
        $this->postJson('/api/files/'.$cube.'/edit', ['op' => 'split', 'joint' => 'glue'])->assertStatus(422);
        $this->postJson('/api/files/'.$cube.'/edit', ['op' => 'melt'])->assertStatus(422);
    }

    public function test_a_model_with_a_hole_is_closed_before_it_is_cut(): void
    {
        $path = sys_get_temp_dir().'/mp_bust_'.uniqid().'.stl';
        MeshFixtures::tornBustStl($path);
        $uuid = $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'bust.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'split', 'bed' => 'custom', 'bed_x' => 100, 'bed_y' => 100, 'bed_z' => 60, 'joint' => 'pins'])->assertCreated();
        $this->assertSame('ready', $r->json('file.status'), (string) $r->json('file.error'));
        $this->assertGreaterThanOrEqual(2, $r->json('file.edit.pieces'));
        $this->assertIsBool($r->json('file.edit.repaired'));                                   // the fixture's notches are closed as it is built: nothing to repair, but the tool says so either way
        $this->assertTrue(StlTopology::check(ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail()->absoluteStlPath())['watertight']);
    }
}
