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

    public function test_a_long_model_becomes_a_flexi_with_ball_joints_printed_in_place(): void
    {
        $this->get('/tools/flexi-cut')->assertOk()->assertSee(__('tools.flexi_cut.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.o.axis.auto'));
        $uuid = $this->box(120, 20, 14, 'bar.stl');
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'flexi_cut', 'segments' => 5, 'ball_d' => 8])->assertCreated();
        $r->assertJsonPath('file.kind', 'flexi_cut')->assertJsonPath('file.status', 'ready');
        $this->assertSame(['segment_1', 'segment_2', 'segment_3', 'segment_4', 'segment_5'], $r->json('file.parts'));
        $e = $r->json('file.edit');
        $this->assertSame(['x', 5, 4], [$e['axis'], $e['segments'], $e['joined']]);
        $this->assertSame([24.0, 48.0, 72.0, 96.0], array_map('floatval', $e['cuts']));
        $this->assertSame([], $e['warnings']);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        // printed assembled: the whole bar is still 120 mm, the segments sit in their places with a gap between them
        $this->assertEqualsWithDelta(120, $file->bbox['x'], 0.1);
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        $this->assertCount(5, $e['pieces_tris']);                                                     // five bodies in one file
        $this->assertEqualsWithDelta(120 * 20 * 14, $file->volume_mm3, 120 * 20 * 14 * 0.05);           // the gaps and the sockets take a little, the balls give a little
        // a thin model has no room for joints; too many segments are refused
        $thin = $this->box(80, 8, 6, 'thin.stl');
        $touch = $this->postJson('/api/files/'.$thin.'/edit', ['op' => 'flexi_cut', 'segments' => 4, 'ball_d' => 6])->assertCreated();
        $this->assertSame(0, $touch->json('file.edit.joined'));
        $this->assertContains('joint_no_room', $touch->json('file.edit.warnings'));
        $this->assertStringStartsWith('segments_too_short', (string) $this->postJson('/api/files/'.$thin.'/edit', ['op' => 'flexi_cut', 'segments' => 20, 'ball_d' => 10])->assertCreated()->json('file.error'));
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'flexi_cut', 'axis' => 'w'])->assertStatus(422);
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

    /** A 3MF with colours of every kind: a material on an object, a material on triangles, slicer paint with the project's filaments. */
    private function colored3mf(): string
    {
        $cube = function (int $id, string $attrs, callable $tri): string {
            $v = [[0, 0, 0], [20, 0, 0], [20, 20, 0], [0, 20, 0], [0, 0, 20], [20, 0, 20], [20, 20, 20], [0, 20, 20]];
            $faces = [[0, 2, 1], [0, 3, 2], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [2, 3, 7], [2, 7, 6], [0, 4, 7], [0, 7, 3], [1, 2, 6], [1, 6, 5]];
            $xml = '<object id="'.$id.'" type="model"'.$attrs.'><mesh><vertices>';
            foreach ($v as [$x, $y, $z]) {
                $xml .= sprintf('<vertex x="%d" y="%d" z="%d"/>', $x, $y, $z);
            }
            $xml .= '</vertices><triangles>';
            foreach ($faces as $i => [$a, $b, $c]) {
                $xml .= sprintf('<triangle v1="%d" v2="%d" v3="%d"%s/>', $a, $b, $c, $tri($i));
            }

            return $xml.'</triangles></mesh></object>';
        };
        // the faces 2 and 3 are the top of a cube
        $model = '<?xml version="1.0" encoding="UTF-8"?><model unit="millimeter" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02"><resources>'
            .'<basematerials id="1"><base name="Red" displaycolor="#FF0000FF"/><base name="Blue" displaycolor="#0000FFFF"/></basematerials>'
            .$cube(1, ' pid="1" pindex="0"', fn ($i) => in_array($i, [2, 3], true) ? ' pid="1" p1="1"' : '')          // red, a blue top painted by material
            .$cube(2, ' pid="1" pindex="1"', fn () => '')                                                         // all blue
            .$cube(3, '', fn ($i) => in_array($i, [2, 3], true) ? ' paint_color="0C"' : ' paint_color="4"')       // slicer paint: filament 1, the top filament 3
            .'</resources><build><item objectid="1"/><item objectid="2" transform="1 0 0 0 1 0 0 0 1 40 0 0"/><item objectid="3" transform="1 0 0 0 1 0 0 0 1 80 0 0"/></build></model>';
        $path = sys_get_temp_dir().'/mp_colors_'.uniqid().'.3mf';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>');
        $zip->addFromString('3D/3dmodel.model', $model);
        $zip->addFromString('Metadata/project_settings.config', json_encode(['filament_colour' => ['#00FF00', '#FFFFFF', '#FFA500']]));
        $zip->close();

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'colors.3mf', null, null, true)])->assertCreated()->json('file.uuid');
    }

    public function test_a_coloured_3mf_becomes_a_part_per_colour_with_painted_colours_as_inlays(): void
    {
        $this->get('/tools/colors')->assertOk()->assertSee(__('tools.colors.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.f.depth'));
        $uuid = $this->colored3mf();
        // the analysis: four colours by area (blue: a whole cube and a top; red: five faces; filament 1: five faces; filament 3: a top)
        $a = $this->postJson('/api/files/'.$uuid.'/edit/analysis', ['op' => 'colors'])->assertOk()->json('analysis');
        $this->assertTrue($a['has_colors']);
        $this->assertSame(['Blue', 'Red', '', ''], array_column($a['colors'], 'name'));
        $this->assertSame(['#0000FF', '#FF0000', '#00FF00', '#FFA500'], array_column($a['colors'], 'hex'));
        $this->assertSame([null, null, 1, 3], array_column($a['colors'], 'extruder'));
        $this->assertEqualsWithDelta(38.9, $a['colors'][0]['share'], 0.2);
        $this->assertSame(['materials'], $a['colors'][0]['sources']);
        $this->assertSame(['paint'], $a['colors'][3]['sources']);
        $this->assertSame(0, $a['split_triangles']);

        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'colors', 'depth' => 1.2])->assertCreated();
        $this->assertSame('ready', $r->json('file.status'), (string) $r->json('file.error'));
        $this->assertSame(['color_1', 'color_2', 'color_3', 'color_4'], $r->json('file.parts'));
        $e = $r->json('file.edit');
        $this->assertSame(['mixed', 'base', 'base', 'inlay'], array_column($e['colors'], 'kind'));   // blue: a shell and an inlay
        $this->assertSame([1, 2, 0], [$e['shells'], $e['bases'], $e['inlays'] - 2]);
        $this->assertSame([], $e['warnings']);
        $this->assertCount(4, $e['pieces_tris']);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        // the parts sit where the bodies were: three cubes in a row; the whole holds what the cubes held
        $this->assertEqualsWithDelta(100, $file->bbox['x'], 0.2);
        $this->assertEqualsWithDelta(3 * 8000, $file->volume_mm3, 3 * 8000 * 0.02);
        $bbox = $file->tool_params['parts_bbox'];
        $this->assertEqualsWithDelta(60, $bbox['color_1'][0], 0.2);           // blue: the cube at 40 and the inlay on the first cube
        $this->assertEqualsWithDelta(1.25, $bbox['color_4'][2], 0.1);         // the painted top as an inlay: 1.2 deep and 0.05 proud
        $this->assertEqualsWithDelta(18.8, $bbox['color_2'][2], 0.1);         // the red body lost its painted top: a recess 1.2 mm deep
        $this->get('/api/tools/edit/'.$file->uuid.'/color_4.stl')->assertOk();
        $this->get('/api/tools/edit/'.$file->uuid.'/color_5.stl')->assertNotFound();

        // an STL carries no colours: the analysis says so, the split is refused
        $plain = $this->box(20, 20, 20, 'plain.stl');
        $this->assertFalse($this->postJson('/api/files/'.$plain.'/edit/analysis', ['op' => 'colors'])->assertOk()->json('analysis.has_colors'));
        $this->assertStringStartsWith('no_colors', (string) $this->postJson('/api/files/'.$plain.'/edit', ['op' => 'colors'])->assertCreated()->json('file.error'));
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'colors', 'depth' => 9])->assertStatus(422);
    }

    public function test_a_soap_dish_is_built_round_the_footprint_of_a_model(): void
    {
        $this->get('/tools/soap-from-model')->assertOk()->assertSee(__('tools.soap_model.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.o.drain.ribs'));
        $uuid = $this->box(90, 60, 30, 'bar.stl');
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'soap', 'height' => 20, 'clearance' => 2, 'wall' => 2.4, 'floor' => 2, 'drain' => 'grooves'])->assertCreated();
        $this->assertSame('ready', $r->json('file.status'), (string) $r->json('file.error'));
        $r->assertJsonPath('file.kind', 'soap')->assertJsonPath('file.parts', ['body']);
        $e = $r->json('file.edit');
        $this->assertSame(['grooves', 'widest'], [$e['drain'], $e['foot']]);
        $this->assertEqualsWithDelta(94, $e['pocket'][0], 0.2);                  // the bar plus 2 mm of play each side
        $this->assertEqualsWithDelta(98.8, $e['dish'][0], 0.2);                  // plus the wall
        $this->assertEqualsWithDelta(22, $e['dish'][2], 0.01);                   // the floor and the wall's height
        $this->assertGreaterThanOrEqual(7, $e['drains']);                        // one every 10 mm along 94 mm, 3 mm of margin kept
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(98.8, $file->bbox['x'], 0.2);
        $this->assertEqualsWithDelta(22, $file->bbox['z'], 0.05);
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        // the dish is a shell: the walls, the floor less the grooves
        $shell = 98.8 * 68.8 * 22 - 94 * 64 * 20;
        $this->assertLessThan($shell, $file->volume_mm3);
        $this->assertGreaterThan($shell * 0.6, $file->volume_mm3);
        // a solid floor holds more, ribs more still (they sit on the floor); the bottom slice of a box is the same footprint
        $solid = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'soap', 'drain' => 'none', 'foot' => 'bottom'])->assertCreated();
        $this->assertGreaterThan($file->volume_mm3, ModelFile::where('uuid', $solid->json('file.uuid'))->firstOrFail()->volume_mm3);
        $this->assertEqualsWithDelta(94, $solid->json('file.edit.pocket.0'), 0.2);
        $ribs = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'soap', 'drain' => 'ribs'])->assertCreated();
        $this->assertSame('ready', $ribs->json('file.status'), (string) $ribs->json('file.error'));
        $this->assertGreaterThanOrEqual(8, $ribs->json('file.edit.drains'));
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'soap', 'drain' => 'holes'])->assertStatus(422);
    }

    /** A dome (half a sphere of radius r, flat underneath) as an upload: a helmet's shape. */
    private function dome(float $r, string $name = 'dome.stl'): string
    {
        $rings = 24;
        $segs = 48;
        $pt = fn (int $i, int $j) => [$r * cos(M_PI / 2 * $i / $rings) * cos(2 * M_PI * $j / $segs), $r * cos(M_PI / 2 * $i / $rings) * sin(2 * M_PI * $j / $segs), $r * sin(M_PI / 2 * $i / $rings)];
        $tri = [];
        for ($i = 0; $i < $rings; $i++) {
            for ($j = 0; $j < $segs; $j++) {
                $tri[] = [$pt($i, $j), $pt($i, $j + 1), $pt($i + 1, $j + 1)];
                if ($i < $rings - 1) {
                    $tri[] = [$pt($i, $j), $pt($i + 1, $j + 1), $pt($i + 1, $j)];
                }
            }
        }
        for ($j = 0; $j < $segs; $j++) {
            $tri[] = [[0, 0, 0], $pt(0, $j + 1), $pt(0, $j)];        // the flat bottom, facing down
        }
        $path = sys_get_temp_dir().'/mp_dome_'.uniqid().'.stl';
        $fh = fopen($path, 'wb');
        fwrite($fh, str_pad('dome', 80, "\0").pack('V', count($tri)));
        foreach ($tri as [$a, $b, $c]) {
            fwrite($fh, pack('f3', 0, 0, 0).pack('f3', ...$a).pack('f3', ...$b).pack('f3', ...$c).pack('v', 0));
        }
        fclose($fh);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, $name, null, null, true)])->assertCreated()->json('file.uuid');
    }

    public function test_a_dome_becomes_a_helmet_to_a_head_girth_hollow_with_a_window_and_strap_slots(): void
    {
        $this->get('/tools/wearable')->assertOk()->assertSee(__('tools.wearable.title'))->assertSee('data-module="edit"', false)->assertSee(__('edit.o.measure.head'))->assertSee('data-window="3"', false);
        $uuid = $this->dome(60);
        $solidVolume = 2 / 3 * M_PI * 60 ** 3;
        // the analysis: a head of 56 cm plus 10 mm of play wants an inner girth of 570; the solid dome's girth less a 3 mm wall is 358 → 1.59×
        $a = $this->postJson('/api/files/'.$uuid.'/edit/analysis', ['op' => 'wearable', 'measure' => 'head', 'circumference' => 56, 'play' => 10, 'wall' => 3])->assertOk()->json('analysis');
        $this->assertEqualsWithDelta(1.592, $a['factor'], 0.01);
        $this->assertEqualsWithDelta(570, $a['girth'], 2);
        $this->assertTrue($a['hollow']);
        $this->assertFalse($a['already_hollow']);
        $this->assertTrue($a['fits']);                                            // 191 mm across fits the farm's bed
        $this->assertEqualsWithDelta(191, $a['scaled'][0], 1);

        $windows = json_encode([['side' => 'front', 'shape' => 'rect', 'w' => 60, 'h' => 30], ['side' => 'top', 'shape' => 'ellipse', 'w' => 40, 'h' => 25]]);
        $r = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'wearable', 'measure' => 'head', 'circumference' => 56, 'play' => 10, 'wall' => 3, 'windows' => $windows, 'straps' => true, 'strap_h' => 35])->assertCreated();
        $this->assertSame('ready', $r->json('file.status'), (string) $r->json('file.error'));
        $r->assertJsonPath('file.kind', 'wearable')->assertJsonPath('file.parts', ['body']);
        $e = $r->json('file.edit');
        $this->assertEqualsWithDelta(1.592, $e['factor'], 0.01);
        $this->assertTrue($e['hollowed']);
        $this->assertTrue($e['opened']);
        $this->assertSame(2, $e['straps']);
        $this->assertCount(2, $e['windows']);
        $this->assertSame('top', $e['windows'][1]['side']);
        $this->assertEqualsWithDelta(570, $e['girth_inner'], 570 * 0.04);           // the hollow is measured on a grid
        $this->assertSame(1, $e['pieces']);
        $this->assertSame([], $e['warnings']);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(191, $file->bbox['x'], 1.5);
        $this->assertEqualsWithDelta(95.5, $file->bbox['z'], 1.0);
        $this->assertTrue(StlTopology::check($file->absoluteStlPath())['watertight']);
        // a shell 3 mm thick holds a small share of the scaled solid dome
        $scaledSolid = $solidVolume * 1.592 ** 3;
        $this->assertLessThan($scaledSolid * 0.2, $file->volume_mm3);
        $this->assertGreaterThan($scaledSolid * 0.04, $file->volume_mm3);

        // not scaled, not hollowed, no windows: the dome as it is
        $same = $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'wearable', 'measure' => 'none', 'hollow' => false, 'windows' => '[]'])->assertCreated();
        $this->assertSame('ready', $same->json('file.status'), (string) $same->json('file.error'));
        $this->assertSame(1.0, (float) $same->json('file.edit.factor'));
        $this->assertEqualsWithDelta($solidVolume, ModelFile::where('uuid', $same->json('file.uuid'))->firstOrFail()->volume_mm3, $solidVolume * 0.02);
        $this->assertSame([], ModelEditor::windowsOf('nonsense'));
        $this->assertSame('front', ModelEditor::windowsOf('[{"side":"inside","w":999}]')[0]['side']);
        $this->assertSame(300.0, ModelEditor::windowsOf('[{"side":"inside","w":999}]')[0]['w']);
        $this->postJson('/api/files/'.$uuid.'/edit', ['op' => 'wearable', 'wall' => 9])->assertStatus(422);
    }
}
