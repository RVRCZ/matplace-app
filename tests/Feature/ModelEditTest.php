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
        $this->postJson('/api/files/'.$cube.'/edit/analysis', ['op' => 'melt'])->assertStatus(422);
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

    public function test_a_hollow_model_keeps_its_shape_and_its_wall_and_tells_what_it_saved(): void
    {
        $this->get('/tools/hollow')->assertOk()->assertSee(__('tools.hollow.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.flag.drain'));
        $uuid = $this->box(60, 60, 60, 'cube.stl');
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'hollow', 'wall' => 2, 'drains' => 1])->assertCreated();
        $r->assertJsonPath('file.kind', 'hollow')->assertJsonPath('file.status', 'ready');
        $this->assertSame(['body'], $r->json('file.parts'));
        $h = $r->json('file.edit.hollow');
        $this->assertSame(2.0, (float) $h['wall']);
        $this->assertSame(1, $h['drains']);
        // a cube of 60 with a wall of 2: a cavity of about 56³, the shell about 60³ − 56³ (the grid rounds the corners a little)
        $this->assertEqualsWithDelta(56 ** 3, $h['cavity_mm3'], 56 ** 3 * 0.06);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(60 ** 3 - 56 ** 3, $file->volume_mm3, (60 ** 3 - 56 ** 3) * 0.1);
        $this->assertEquals([60, 60, 60], [round($file->bbox['x']), round($file->bbox['y']), round($file->bbox['z'])]);  // the outside is untouched
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        $this->assertEqualsWithDelta($h['cavity_mm3'] / 1000 * 1.24, $h['saved_g'], 1);
        // no drain holes when asked so; a wall thicker than the model has nothing to take out
        $closed = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'hollow', 'wall' => 2, 'drain' => false])->assertCreated();
        $this->assertSame(0, $closed->json('file.edit.hollow.drains'));
        $small = $this->box(10, 10, 10, 'tiny.stl');
        $none = $this->postJson('/api/files/'.$small.'/edit', ['op' => 'hollow', 'wall' => 6])->assertCreated();
        $this->assertContains('nothing_to_hollow', $none->json('file.edit.warnings'));
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'hollow', 'wall' => 0.5])->assertStatus(422);
    }

    public function test_life_size_scales_hollows_and_splits_in_one_go(): void
    {
        $this->get('/tools/life-size')->assertOk()->assertSee(__('tools.life_size.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.f.height_cm'));
        $uuid = $this->box(30, 20, 60, 'post.stl');
        // what it would come to, before anything is built
        $a = $this->postJson('/api/files/'.$uuid.'/edit/analysis', ['op' => 'life_size', 'height_cm' => 40])->assertOk()->json('analysis');
        $this->assertEqualsWithDelta(400 / 60, $a['factor'], 0.001);
        $this->assertEquals([200, 133.3, 400], array_map(fn ($v) => round($v, 1), $a['scaled']));
        $this->assertTrue($a['hollow']);                                                           // 200 × 133 × 400 mm is far over 200 cm³
        $this->assertSame(2, $a['pieces']);                                                         // one cut across the height for a 250 mm bed
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'life_size', 'height_cm' => 40, 'joint' => 'pins'])->assertCreated();
        $r->assertJsonPath('file.kind', 'life_size')->assertJsonPath('file.status', 'ready');
        $e = $r->json('file.edit');
        $this->assertEqualsWithDelta(400 / 60, $e['factor'], 0.001);
        $this->assertSame(2, $e['pieces']);
        $this->assertGreaterThan(0, $e['pins']);
        $this->assertNotNull($e['hollow']);
        $this->assertGreaterThan(0, $e['hollow']['cavity_mm3']);
        $this->assertLessThan(200 * 133.3 * 400 * 1.24 / 1000, $e['grams']);                      // lighter than solid
        $this->assertSame(['piece_1', 'piece_2', 'pins'], $r->json('file.parts'));
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        $this->assertEqualsWithDelta(200, $file->tool_params['each'][0][2], 0.5);                 // the pieces stand on their cut faces, 200 mm high
        // a small target fits the bed whole and stays solid
        $one = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'life_size', 'height_cm' => 10])->assertCreated();
        $this->assertSame(1, $one->json('file.edit.pieces'));
        $this->assertNull($one->json('file.edit.hollow'));
        $this->assertSame(['piece_1'], $one->json('file.parts'));
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'life_size', 'height_cm' => 300])->assertStatus(422);
    }

    public function test_a_flat_model_becomes_a_jigsaw_with_knobs_or_hidden_pins(): void
    {
        $this->get('/tools/puzzle')->assertOk()->assertSee(__('tools.puzzle.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.o.lock.tabs'));
        $uuid = $this->box(120, 90, 6, 'plate.stl');
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'puzzle', 'rows' => 3, 'cols' => 4, 'lock' => 'tabs', 'frame' => true])->assertCreated();
        $r->assertJsonPath('file.kind', 'puzzle')->assertJsonPath('file.status', 'ready');
        $parts = $r->json('file.parts');
        $this->assertCount(13, $parts);                                                             // 12 pieces and the tray
        $this->assertSame('frame', end($parts));
        $e = $r->json('file.edit');
        $this->assertSame([3, 4, 'tabs', 12], [$e['rows'], $e['cols'], $e['lock'], $e['pieces']]);
        $this->assertSame([], $e['warnings']);
        $this->assertCount(12, $e['map']);
        $this->assertNotNull($e['map'][0]['number_at']);                                            // numbered underneath
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        // the pieces together are the plate less the play of the knobs; every piece is 6 mm high and the tray a little more
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        $this->assertEqualsWithDelta(120 * 90 * 6, $file->volume_mm3 - 36125, 120 * 90 * 6 * 0.03);
        $sizes = array_slice($file->tool_params['each'], 0, 12);
        foreach ($sizes as $size) {
            $this->assertEqualsWithDelta(6, $size[2], 0.01);
            $this->assertGreaterThanOrEqual(30 - 0.01, min($size[0], $size[1]));                   // a 30 mm cell, never smaller
        }
        $this->assertGreaterThan(30, max(array_column($sizes, 0)));                                 // with its knobs reaching out
        // hidden pins: straight cuts, pins as a piece of their own; a tall model gets pins whatever was asked
        $pins = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'puzzle', 'rows' => 2, 'cols' => 3, 'lock' => 'pins'])->assertCreated();
        $this->assertSame(['piece_1', 'piece_2', 'piece_3', 'piece_4', 'piece_5', 'piece_6', 'pins'], $pins->json('file.parts'));
        $this->assertGreaterThan(0, $pins->json('file.edit.pins'));
        $tall = $this->box(100, 100, 40, 'block.stl');
        $forced = $this->postJson('/api/files/'.$tall.'/edit', ['op' => 'puzzle', 'rows' => 2, 'cols' => 2, 'lock' => 'tabs'])->assertCreated();
        $this->assertSame('pins', $forced->json('file.edit.lock'));
        $this->assertContains('tall_gets_pins', $forced->json('file.edit.warnings'));
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'puzzle', 'rows' => 9])->assertStatus(422);
        $tiny = $this->box(20, 20, 4, 'tiny.stl');
        $this->assertStringStartsWith('pieces_too_small', (string) $this->postJson('/api/files/'.$tiny.'/edit', ['op' => 'puzzle', 'rows' => 2, 'cols' => 2])->assertCreated()->json('file.error'));
    }

    public function test_a_holder_is_cut_out_of_a_model_and_its_wall_is_measured(): void
    {
        $this->get('/tools/holder-from-model')->assertOk()->assertSee(__('tools.holder_model.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.o.cavity.pint'));
        $uuid = $this->box(80, 80, 100, 'block.stl');
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'holder', 'cavity' => 'can330', 'clearance' => 0.6])->assertCreated();
        $r->assertJsonPath('file.kind', 'holder')->assertJsonPath('file.status', 'ready');
        $e = $r->json('file.edit');
        $this->assertSame('can330', $e['cavity']);
        $this->assertEqualsWithDelta(90, $e['cavity_mm']['depth'], 0.01);
        $this->assertEqualsWithDelta(10, $e['floor'], 0.01);                                        // 100 − 90
        $this->assertEqualsWithDelta((80 - 66.3 - 1.2) / 2, $e['min_wall'], 0.2);                 // the wall of a square block round a round can
        $this->assertSame([], $e['warnings']);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(80 * 80 * 100 - M_PI * (67.5 / 2) ** 2 * 90, $file->volume_mm3, 2000);
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        // too small a model: scaled up by the height it is given; too low a model is refused with the height it needs
        $small = $this->box(40, 40, 50, 'small.stl');
        $this->assertStringStartsWith('too_short', (string) $this->postJson('/api/files/'.$small.'/edit', ['op' => 'holder', 'cavity' => 'can330'])->assertCreated()->json('file.error'));
        $big = $this->postJson('/api/files/'.$small.'/edit', ['op' => 'holder', 'cavity' => 'slim330', 'height' => 130])->assertCreated();
        $this->assertEqualsWithDelta(2.6, $big->json('file.edit.factor'), 0.001);
        $this->assertSame([], $big->json('file.edit.warnings'));                                     // 104 mm wide round a 58 mm can
        $thin = $this->postJson('/api/files/'.$small.'/edit', ['op' => 'holder', 'cavity' => 'can330', 'height' => 120])->assertCreated();   // 96 mm wide, a 67.5 mm hole moved aside
        $this->assertLessThan(15, $thin->json('file.edit.min_wall'));
        $moved = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'holder', 'cavity' => 'can330', 'cav_x' => 10])->assertCreated();
        $this->assertContains('wall_thin', $moved->json('file.edit.warnings'));
        $this->assertGreaterThan(0, $moved->json('file.edit.grow_to'));
        // a soap dish: a box cavity and a push-out hole through the floor
        $soap = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'holder', 'cavity' => 'soap'])->assertCreated();
        $this->assertEqualsWithDelta(70, $soap->json('file.edit.floor'), 0.01);
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'holder', 'cavity' => 'bucket'])->assertStatus(422);
    }

    public function test_a_model_becomes_a_potion_bottle_with_a_neck_a_cork_and_a_label(): void
    {
        $this->get('/tools/potion')->assertOk()->assertSee(__('tools.potion.title'))->assertSee('data-module="edit"', false)->assertSee('data-text="text"', false);
        $uuid = $this->box(50, 40, 80, 'block.stl');
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'potion', 'height' => 100, 'neck_d' => 20, 'neck_h' => 25, 'wall' => 2, 'cut' => 5, 'text' => 'Elixir'])->assertCreated();
        $r->assertJsonPath('file.kind', 'potion')->assertJsonPath('file.status', 'ready');
        $this->assertSame(['body', 'cork', 'label'], $r->json('file.parts'));
        $e = $r->json('file.edit');
        $this->assertEqualsWithDelta(100 / 80, $e['factor'], 0.001);
        $this->assertEqualsWithDelta(5, $e['cut_mm'], 0.01);
        $this->assertEqualsWithDelta(95 + 25, $e['bottle'][2], 0.5);                                // the model less its bottom, plus the neck
        $this->assertGreaterThan(0, $e['hollow']['cavity_mm3']);
        $this->assertSame('Elixir', $e['label']['text']);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        $this->assertLessThan(62.5 * 50 * 95 * 0.5, $file->volume_mm3);                             // hollow: well under half of the solid block
        foreach (['body', 'cork', 'label'] as $part) {
            $this->get('/api/tools/edit/'.$file->uuid.'/'.$part.'.stl')->assertOk();
        }
        $plain = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'potion', 'label' => false])->assertCreated();
        $this->assertSame(['body', 'cork'], $plain->json('file.parts'));
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'potion', 'neck_d' => 5])->assertStatus(422);
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
