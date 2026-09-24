<?php

namespace Tests\Feature;

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
        $this->get('/tools/mold')->assertOk()->assertSee(__('mold.wall'))->assertSee('mold-drop', false);
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

        // a mold of a mold makes no sense
        $this->postJson('/api/files/'.$mold->uuid.'/mold', ['wall' => 8])->assertNotFound();
    }
}
