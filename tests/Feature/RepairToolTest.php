<?php

namespace Tests\Feature;

use App\Domain\Tools\ModelRepair;
use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\StlTopology;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** "Repair my model": a cube with a missing face comes back closed, a clean cube is told to be fine, the original stays. */
class RepairToolTest extends TestCase
{
    use RefreshDatabase;

    private function upload(callable $writer, string $name = 'part.stl'): string
    {
        $path = sys_get_temp_dir().'/mp_repair_'.uniqid().'.stl';
        $writer($path);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, $name, null, null, true)])->assertCreated()->json('file.uuid');
    }

    /** A 20 mm cube without its top (two triangles missing) and with one face written twice. */
    private function brokenCube(string $path): void
    {
        $full = $path.'.full.stl';
        MeshFixtures::cubeStl($full, 20.0);
        $fh = StlFile::beginBinary($path);
        $n = 0;
        $first = null;
        foreach (StlFile::triangles($full) as $tri) {
            $top = $tri[0][2] > 19.9 && $tri[1][2] > 19.9 && $tri[2][2] > 19.9;
            if ($top) {
                continue;
            }
            $first ??= $tri;
            StlFile::writeTriangle($fh, ...$tri);
            $n++;
        }
        StlFile::writeTriangle($fh, ...$first);
        StlFile::endBinary($fh, $n + 1);
        @unlink($full);
    }

    public function test_page_renders_in_three_languages_and_is_listed(): void
    {
        $this->get('/tools/repair?lang=cs')->assertOk()->assertSee('Oprava 3D modelu')->assertSee('id="repair-file"', false);
        $this->get('/tools/repair?lang=en')->assertOk()->assertSee('Repair a 3D model');
        $this->get('/tools/repair?lang=es')->assertOk()->assertSee('Reparar un modelo 3D');
        $this->get('/tools?lang=cs')->assertOk()->assertSee('Oprava 3D modelu');
    }

    public function test_a_cube_with_a_hole_comes_back_closed_and_the_original_stays(): void
    {
        if (! app(ModelRepair::class)->available()) {
            $this->markTestSkipped('Python with trimesh is not installed.');
        }
        Storage::fake('models');
        $uuid = $this->upload(fn ($p) => $this->brokenCube($p), 'Broken Cube.stl');
        $src = ModelFile::where('uuid', $uuid)->firstOrFail();
        $sha = hash_file('sha256', $src->absoluteStlPath());

        $r = $this->postJson("/api/files/{$uuid}/repair")->assertCreated();
        $r->assertJsonPath('file.kind', 'repaired')->assertJsonPath('file.status', 'ready')->assertJsonPath('file.name', 'broken-cube-fixed.stl');
        $report = $r->json('file.repair');
        $this->assertSame('repaired', $report['verdict']);
        $this->assertFalse($report['before']['watertight']);
        $this->assertTrue($report['after']['watertight']);
        $this->assertGreaterThan(0, $report['before']['open_edges']);
        $this->assertSame(0, $report['after']['open_edges']);
        $this->assertSame(1, $report['actions']['removed_duplicate']);
        $this->assertGreaterThan(0, $report['actions']['closed_edges']);

        $fixed = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertTrue(StlTopology::check($fixed->absoluteStlPath())['watertight']);
        $this->assertEqualsWithDelta(20.0, $fixed->bbox['z'], 0.01, 'the repair does not change the size');
        $this->assertEqualsWithDelta(8000.0, $fixed->volume_mm3, 5.0);
        $this->assertSame($sha, hash_file('sha256', $src->fresh()->absoluteStlPath()), 'the original file is untouched');
        $this->assertSame($uuid, $fixed->tool_params['source']);

        // it prices and downloads like any other model
        $this->get("/api/files/{$fixed->uuid}/model.stl")->assertOk();
        $this->postJson('/api/calculations', ['file' => $fixed->uuid, 'material' => 'PLA'])->assertCreated();
    }

    public function test_a_clean_model_is_told_to_be_fine(): void
    {
        if (! app(ModelRepair::class)->available()) {
            $this->markTestSkipped('Python with trimesh is not installed.');
        }
        Storage::fake('models');
        $uuid = $this->upload(fn ($p) => MeshFixtures::cubeStl($p, 20.0));
        $report = $this->postJson("/api/files/{$uuid}/repair")->assertCreated()->json('file.repair');
        $this->assertSame('clean', $report['verdict']);
        $this->assertTrue($report['before']['watertight']);
        $this->assertSame(0, array_sum(array_map('intval', $report['actions'])));
    }

    public function test_a_file_that_is_not_ready_cannot_be_repaired(): void
    {
        $file = ModelFile::create(['uuid' => (string) Str::uuid(), 'original_name' => 'x.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64), 'storage_path' => 'files/x/original.stl', 'status' => ModelFile::STATUS_FAILED]);
        $this->postJson("/api/files/{$file->uuid}/repair")->assertNotFound();
    }
}
