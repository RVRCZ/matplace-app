<?php

namespace Tests\Feature;

use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\StlTopology;
use App\Engines\Repair\PythonTool;
use App\Models\ModelFile;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Casting mold around a ready model: two halves on one plate, parting face up, report of what the tool measured. */
class MoldToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
    }

    /** The bust of the fixtures (60 × 52 × 78 mm, undercuts under the chin and the ears): what the silicone molds are for. */
    private function bust(): string
    {
        return $this->postJson('/api/uploads', ['file' => new UploadedFile(base_path('tests/fixtures/mold/bust.stl'), 'bust.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function cube(): string
    {
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    public function test_tool_page_renders_and_is_listed(): void
    {
        $this->get('/tools')->assertOk()->assertSee(route('tools.mold'));
        $this->get('/tools/mold')->assertOk()->assertSee(__('mold.wall'))->assertSee('mold-drop', false)->assertSee('id="tool-viewer"', false)->assertSee(__('mold.type.silicone'))
            // round 2: the gallery of the five kinds above the upload (a click sets the kind), the new choices, the advanced ones folded
            ->assertSee('id="mold-gallery"', false)->assertSee('img/tool-examples/mold-1.png', false)->assertSee('data-variant="two_part"', false)->assertSee(__('mold.gallery.tray'))
            ->assertSeeInOrder(['id="mold-gallery"', 'id="mold-drop"', 'id="mold-variant"', 'id="mold-vents"', 'id="mold-core"', '<details', 'id="mold-clearance"', 'id="mold-keys-type"', 'id="mold-funnel-at"'], false)
            ->assertSee(__('tools.which.title'))->assertSee(ToolSeo::texts('mold')['which'][0]['kind'])
            ->assertSee('#tool-panel { order: 1; }', false);
        $this->get('/en/tools/mold')->assertOk()->assertSee('Casting mold');
        $this->get('/tools/mold?from=not-a-uuid')->assertOk()->assertSee('from: null', false);
    }

    public function test_mold_is_a_new_ready_file_with_two_halves_and_a_report(): void
    {
        $uuid = $this->cube();
        $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 7])->assertStatus(422);          // not one of the offered walls

        $r = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'axis' => 'y']);
        $r->assertCreated()->assertJsonPath('file.kind', 'mold')->assertJsonPath('file.status', 'ready')->assertJsonPath('file.mold.axis', 'y');
        $this->assertNotSame($uuid, $r->json('file.uuid'));
        $this->assertSame('cube-mold.stl', $r->json('file.name'));
        $this->assertNotContains('multiple_shells', $r->json('file.issues'));                  // two halves on one plate by design
        $this->assertFalse($r->json('file.hints.supports'));

        $mold = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $report = $mold->tool_params['report'];
        $this->assertEquals([36, 36, 36], $report['box']);                                       // 20 mm cube + 8 mm wall all round
        $this->assertGreaterThan(8.0, $report['resin_ml']);                                       // the cube (8 ml) plus the funnel through the wall
        $this->assertLessThan(14.0, $report['resin_ml']);
        $this->assertSame(3, $report['keys']);
        $this->assertEquals(0, $report['undercut_pct']);
        $this->assertSame($uuid, $mold->tool_params['source']);
        // the mold takes the box volume minus the cavity, and both halves lie on the plate
        $this->assertEqualsWithDelta(36 * 36 * 36 - $report['resin_ml'] * 1000, $mold->volume_mm3, 800);
        $this->assertEqualsWithDelta(36 + 10 + 36, $mold->bbox['x'], 4.5);                        // side by side with a 10 mm gap (+ keys)

        $this->assertSame('rigid', $report['verdict']);                                         // a cube comes out of a hard mold

        // a mold of a mold makes no sense
        $this->postJson('/api/files/'.$mold->uuid.'/mold', ['wall' => 8])->assertNotFound();
    }

    /** A spool standing on its flange: halves pulled apart sideways let it go, halves pulled up and down do not. */
    public function test_the_tool_says_whether_the_shape_comes_out_of_a_hard_mold(): void
    {
        $uuid = $this->spool();

        $auto = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8])->assertCreated();
        $this->assertSame('rigid', $auto->json('file.mold.verdict'));
        $this->assertNotSame('z', $auto->json('file.mold.axis'));
        $this->assertNotContains('undercuts', $auto->json('file.mold.warnings'));

        $level = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'axis' => 'z'])->assertCreated();
        $this->assertSame('silicone', $level->json('file.mold.verdict'));
        $this->assertGreaterThan(10, $level->json('file.mold.undercut_pct'));
        $this->assertContains('undercuts', $level->json('file.mold.warnings'));
        $this->assertTrue(StlTopology::check(ModelFile::where('uuid', $level->json('file.uuid'))->firstOrFail()->absoluteStlPath())['watertight']);
    }

    private function spool(): string
    {
        $path = sys_get_temp_dir().'/mp_spool_'.uniqid().'.stl';
        MeshFixtures::spoolStl($path);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'spool.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    /** Before any mold is made: how much a mold would hold on to, for 2, 3 and 4 parts, and which triangles. */
    public function test_analysis_marks_the_triangles_a_mold_would_hold_on_to(): void
    {
        $uuid = $this->spool();
        $this->get('/tools/mold')->assertOk()->assertSee('mold-analysis', false)->assertSee('Počet dílů formy')->assertSee('Vyplnit podřezy')->assertSee('id="mold-cast"', false);
        $this->postJson('/api/files/'.$uuid.'/mold/analysis', ['parts' => 5])->assertStatus(422);

        $a = $this->postJson('/api/files/'.$uuid.'/mold/analysis', ['parts' => 2, 'axis' => 'z'])->assertOk()->json('analysis');
        $this->assertSame('silicone', $a['verdict']);
        $this->assertGreaterThan(10, $a['undercut_pct']);
        $this->assertLessThan(3, $a['options']['3']);                                            // wedges pulled off sideways let the spool go
        $this->assertLessThan(3, $a['options']['4']);
        $flags = array_values(unpack('C*', base64_decode($a['flags'])));
        $model = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertCount(iterator_count((function () use ($model) {
            yield from StlFile::triangles($model->absoluteStlPath());
        })()), $flags);   // one for every triangle of the file
        $this->assertSame($a['hidden_faces'], count(array_filter($flags, fn ($f) => $f & 8)));
        $this->assertGreaterThan(0, $a['hidden_faces']);
        $this->assertEqualsCanonicalizing([0, 1], array_values(array_unique(array_map(fn ($f) => $f & 7, $flags))));

        // stored beside the model: the same answer again, without the tool
        $files = glob(dirname($model->absoluteStlPath()).'/mold-analysis-*.json');
        $this->assertCount(1, $files);
        $this->assertSame($a, $this->postJson('/api/files/'.$uuid.'/mold/analysis', ['parts' => 2, 'axis' => 'z'])->assertOk()->json('analysis'));

        $four = $this->postJson('/api/files/'.$uuid.'/mold/analysis', ['parts' => 4])->assertOk()->json('analysis');
        $this->assertSame('rigid', $four['verdict']);
        $this->assertEqualsCanonicalizing([0, 1, 2, 3], array_values(array_unique(array_map(fn ($f) => $f & 7, array_values(unpack('C*', base64_decode($four['flags'])))))));
    }

    public function test_mold_of_three_and_four_parts_is_that_many_closed_bodies(): void
    {
        $uuid = $this->cube();
        foreach ([3, 4] as $n) {
            $r = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'parts' => $n])->assertCreated();
            $m = $r->json('file.mold');
            $this->assertSame($n, $m['parts']);
            $this->assertSame($n, $m['pieces']);
            $this->assertSame(2 * $n, $m['keys']);                                               // two cone keys on every parting face
            $this->assertSame('rigid', $m['verdict']);
            $mold = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
            $this->assertTrue(StlTopology::check($mold->absoluteStlPath())['watertight'], $n.' parts');
            // the pieces together are the box less the cavity and the funnel (the keys add and take the same)
            $this->assertEqualsWithDelta(36 * 36 * 36 - $m['resin_ml'] * 1000, $mold->volume_mm3, 900, $n.' parts');
            $this->assertNotContains('multiple_shells', $r->json('file.issues'));
        }
    }

    public function test_filled_undercuts_let_the_mold_go_and_the_casting_can_be_looked_at(): void
    {
        $uuid = $this->spool();
        $plain = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'axis' => 'z'])->assertCreated();
        $this->assertArrayNotHasKey('cast_url', $plain->json('file.mold'));
        $this->get('/api/files/'.$plain->json('file.uuid').'/mold/cast.stl')->assertNotFound();

        $r = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'axis' => 'z', 'fill' => true])->assertCreated();
        $m = $r->json('file.mold');
        $this->assertTrue($m['fill']);
        $this->assertGreaterThan(10, $m['undercut_before_pct']);
        $this->assertLessThan(3, $m['undercut_pct']);
        $this->assertSame('rigid', $m['verdict']);
        // the space between the flanges round the core is filled: a ring 36 mm outside, 20 mm inside, 28 mm high
        $this->assertEqualsWithDelta(M_PI * (18 ** 2 - 10 ** 2) * 28 / 1000, $m['added_ml'], 1.5);
        $mold = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue(StlTopology::check($mold->absoluteStlPath())['watertight']);

        $cast = dirname($mold->absoluteStlPath()).'/cast.stl';
        $this->assertFileExists($cast);
        $this->assertTrue(StlTopology::check($cast)['watertight']);
        $this->get($m['cast_url'])->assertOk();
        $flags = $this->get($m['cast_flags_url'])->assertOk();
        $bytes = file_get_contents($flags->baseResponse->getFile()->getPathname());
        $this->assertSame((filesize($cast) - 84) / 50, strlen($bytes));                          // one for every triangle of the casting
        $this->assertGreaterThan(0, substr_count($bytes, "\x01"));
        $this->assertGreaterThan(0, substr_count($bytes, "\x00"));
        $this->get('/api/files/'.$mold->uuid.'/mold/model.stl')->assertNotFound();
    }

    public function test_mold_for_silicone_is_a_base_with_the_model_and_a_sleeve(): void
    {
        $uuid = $this->cube();
        $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'wax'])->assertStatus(422);
        $r = $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'wall' => 10])->assertCreated();
        $r->assertJsonPath('file.kind', 'mold')->assertJsonPath('file.mold.type', 'silicone');
        $this->assertSame('cube-silicone-mold.stl', $r->json('file.name'));
        $this->assertTrue($r->json('file.hints.supports'));                                      // the master prints the way the model needs
        $m = $r->json('file.mold');
        // 20 mm cube, 10 mm of silicone round it and above it: 40 × 40 × 30 inside with round corners, less the cube
        $this->assertEqualsWithDelta(40 * 40 * 30 / 1000 - 8, $m['silicone_ml'], 3.0);
        $this->assertEqualsWithDelta(8.0, $m['resin_ml'], 0.1);
        $this->assertEqualsWithDelta(40 + 2 * 2.4, $m['sleeve'][0], 0.2);
        $this->assertEqualsWithDelta(30 + 1.5, $m['sleeve'][2], 0.01);
        $mold = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue(StlTopology::check($mold->absoluteStlPath())['watertight']);
        $this->assertNotContains('multiple_shells', $r->json('file.issues'));
    }

    // ── round 2 (docs/AB.md) ────────────────────────────────────────────────────────────────────────────────────

    /** Before anything is built: the casting in ml, the silicone a sleeve and a shell would take, where air would stay. */
    public function test_analysis_says_the_volumes_and_the_pockets(): void
    {
        $uuid = $this->bust();
        $a = $this->postJson('/api/files/'.$uuid.'/mold/analysis', ['parts' => 2])->assertOk()->json('analysis');
        $this->assertEqualsWithDelta(80.6, $a['resin_ml'], 2.0);
        $this->assertGreaterThan($a['silicone_shell_ml'], $a['silicone_sleeve_ml'], 'a shell takes less silicone than a sleeve');
        $this->assertGreaterThan(150, $a['silicone_sleeve_ml']);
        $this->assertGreaterThan(0, $a['pockets']);                                              // the nose, the chin, the ears
        $this->assertArrayHasKey('verdict', $a);                                                  // the bust's undercuts are mild: the verdict stays the tool's
    }

    /** The printed mold of two parts gets vents in its parting plane, and its report says what the caster needs. */
    public function test_rigid_mold_has_vents_from_the_pockets_and_a_funnel_where_asked(): void
    {
        $uuid = $this->bust();
        $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'vents' => 'maybe'])->assertStatus(422);
        $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'funnel_at' => 'back'])->assertStatus(422);
        $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'keys_type' => 'pins'])->assertStatus(422);
        $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'clearance' => '0.5'])->assertStatus(422);

        $r = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'parts' => 2, 'keys_type' => 'cone', 'clearance' => '0.2'])->assertCreated();
        $m = $r->json('file.mold');
        $this->assertSame(['rigid', 'bottom', 'cone'], [$m['variant'], $m['funnel_at'], $m['keys_type']]);
        $this->assertGreaterThan(0, $m['vents'], 'air would stay in the pockets of the bust');
        $this->assertEqualsWithDelta(80.6, $m['cast_ml'], 2.0);                                 // the casting alone; resin_ml adds the funnel
        $this->assertGreaterThan($m['cast_ml'], $m['resin_ml']);
        $mold = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue(StlTopology::check($mold->absoluteStlPath())['watertight']);
        $this->assertSame(0.2, $mold->tool_params['clearance']);

        $off = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'parts' => 2, 'vents' => 'off'])->assertCreated();
        $this->assertSame(0, $off->json('file.mold.vents'));
        $on = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'parts' => 2])->assertCreated();
        $volume = fn ($r) => ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail()->volume_mm3;
        $this->assertGreaterThan($volume($on), $volume($off), 'the vents take material out of the mold');

        $top = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'parts' => 2, 'funnel_at' => 'top'])->assertCreated()->json('file.mold');
        $this->assertSame('top', $top['funnel_at']);
        $side = $this->postJson('/api/files/'.$uuid.'/mold', ['wall' => 8, 'parts' => 2, 'funnel_at' => 'side'])->assertCreated()->json('file.mold');
        $this->assertSame('side', $side['funnel_at']);
    }

    /** The silicone mold as a shell: two halves that follow the bust, a base with the master and its vent pins; less silicone than the sleeve. */
    public function test_silicone_shell_saves_silicone_and_prints_in_three_pieces(): void
    {
        $uuid = $this->bust();
        $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'variant' => 'box'])->assertStatus(422);
        $r = $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'variant' => 'shell', 'wall' => 8])->assertCreated();
        $this->assertSame('bust-silicone-shell-mold.stl', $r->json('file.name'));
        $m = $r->json('file.mold');
        $this->assertSame('shell', $m['variant']);
        $this->assertSame(3, $m['pieces']);
        $this->assertGreaterThan(30, $m['saving_pct']);
        $this->assertLessThan($m['silicone_box_ml'], $m['silicone_ml']);
        $this->assertEqualsWithDelta(80.6, $m['resin_ml'], 2.0);
        $this->assertContains('shell_supports', $m['warnings']);
        $this->assertGreaterThan(0, $m['vents']);
        $mold = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue(StlTopology::check($mold->absoluteStlPath())['watertight']);
    }

    /** The tray: one piece, the model on its floor; the two-part mold: the box, the parting plate with cone keys, the master. */
    public function test_silicone_tray_is_one_piece_and_two_part_has_a_plate_with_keys(): void
    {
        $uuid = $this->bust();
        $tray = $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'variant' => 'tray', 'wall' => 8])->assertCreated();
        $this->assertSame('bust-tray-mold.stl', $tray->json('file.name'));
        $t = $tray->json('file.mold');
        $this->assertSame(['tray', 1], [$t['variant'], $t['pieces']]);
        $this->assertGreaterThan(100, $t['silicone_ml']);
        $this->assertTrue(StlTopology::check(ModelFile::where('uuid', $tray->json('file.uuid'))->firstOrFail()->absoluteStlPath())['watertight']);

        $two = $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'variant' => 'two_part', 'wall' => 8])->assertCreated();
        $this->assertSame('bust-silicone-two-part-mold.stl', $two->json('file.name'));
        $m = $two->json('file.mold');
        $this->assertSame(['two_part', 3], [$m['variant'], $m['pieces']]);
        $this->assertGreaterThanOrEqual(2, $m['keys']);
        $this->assertGreaterThan($t['silicone_ml'], $m['silicone_ml'], 'two halves take more than one open tray');
        $this->assertTrue(StlTopology::check(ModelFile::where('uuid', $two->json('file.uuid'))->firstOrFail()->absoluteStlPath())['watertight']);
    }

    /** A core makes the casting hollow: the core's volume comes off the material per casting, and the core is one more piece on the plate. */
    public function test_core_makes_the_casting_hollow(): void
    {
        $uuid = $this->bust();
        $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'core' => true, 'core_wall' => 7])->assertStatus(422);
        $plain = $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'wall' => 8])->assertCreated()->json('file.mold');
        $this->assertSame(0.0, (float) $plain['core_ml']);
        $r = $this->postJson('/api/files/'.$uuid.'/mold', ['type' => 'silicone', 'wall' => 8, 'core' => true, 'core_wall' => 5])->assertCreated();
        $m = $r->json('file.mold');
        $this->assertGreaterThan(10, $m['core_ml']);
        $this->assertEqualsWithDelta($m['resin_ml'] - $m['core_ml'], $m['cast_ml'], 0.15);
        $this->assertNotContains('core_none', $m['warnings']);
        $mold = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue($mold->tool_params['core']);
        $this->assertSame(5, $mold->tool_params['core_wall']);
        $this->assertTrue(StlTopology::check($mold->absoluteStlPath())['watertight']);
    }
}
