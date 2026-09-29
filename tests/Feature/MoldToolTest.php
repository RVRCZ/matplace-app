<?php

namespace Tests\Feature;

use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\StlTopology;
use App\Engines\Repair\PythonTool;
use App\Models\ModelFile;
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

    private function cube(): string
    {
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    public function test_tool_page_renders_and_is_listed(): void
    {
        $this->get('/tools')->assertOk()->assertSee(route('tools.mold'));
        $this->get('/tools/mold')->assertOk()->assertSee(__('mold.wall'))->assertSee('mold-drop', false)->assertSee('mold-model-viewer', false)->assertSee(__('mold.type.silicone'));
        $this->get('/tools/mold?lang=en')->assertOk()->assertSee('Casting mold');
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
        $this->get('/tools/mold?lang=cs')->assertOk()->assertSee('mold-analysis', false)->assertSee('Počet dílů formy')->assertSee('Vyplnit podřezy')->assertSee('mold-cast-viewer', false);
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
}
