<?php

namespace Tests\Feature;

use App\Domain\Generation\ModelNormalizer;
use App\Engines\Generator\FakeGenerator;
use App\Engines\Repair\PythonTool;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Where the coloured GLB lies over the printed STL is measured by the engine (24 Nov 2026), kept with the file and handed to the
 * browser; a file made by changing the base has no GLB of its own and takes the one of the first file of its generation.
 */
class GlbFitTest extends TestCase
{
    use RefreshDatabase;

    private string $glb = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 20]);
        Storage::fake('models');
        Storage::fake('local');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'person', 'reason' => ''])]]])]);
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
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

    /** A Y-up GLB of a synthetic bust, the way the generator hands one over. */
    private function makeGlb(): void
    {
        $probe = trim((string) shell_exec(escapeshellcmd($this->python()).' -c "import manifold3d, trimesh, scipy; print(1)" 2>&1'));
        if ($probe !== '1') {
            $this->markTestSkipped('manifold3d / trimesh / scipy are not installed.');
        }
        $stl = sys_get_temp_dir().'/mp_glbfit_'.uniqid().'.stl';
        $this->glb = sys_get_temp_dir().'/mp_glbfit_'.uniqid().'.glb';
        shell_exec(escapeshellcmd($this->python()).' '.escapeshellarg(base_path('tests/Support/synthetic_bust.py')).' '.escapeshellarg($stl));
        $code = 'import sys,trimesh,numpy as np; m=trimesh.load(sys.argv[1]); m.apply_transform(trimesh.transformations.rotation_matrix(-np.pi/2,[1,0,0])); m.export(sys.argv[2])';
        shell_exec(escapeshellcmd($this->python()).' -c '.escapeshellarg($code).' '.escapeshellarg($stl).' '.escapeshellarg($this->glb));
        @unlink($stl);
        $this->assertFileExists($this->glb);
        FakeGenerator::$glb = $this->glb;
    }

    private function generate(string $pedestal = 'round'): ModelFile
    {
        $this->makeGlb();
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'pedestal' => $pedestal], ['Accept' => 'application/json'])->assertCreated();

        return ModelFile::where('uuid', $r->json('generation.file.uuid'))->firstOrFail();
    }

    /** Median distance (mm) of the top third of the coloured GLB, laid over the STL by `matrix`, from the surface of that STL. */
    private function median(ModelFile $f, array $matrix): float
    {
        $code = implode("\n", [
            'import sys, json, numpy as np, trimesh',
            'from scipy.spatial import cKDTree',
            'src = trimesh.load(sys.argv[1], force="mesh", skip_materials=True)',
            'out = trimesh.load(sys.argv[2], force="mesh")',
            'M = np.array(json.loads(sys.argv[3])).reshape(4, 4)',
            'w = trimesh.transformations.transform_points(src.vertices, M)',
            'z = w[:, 2]',
            'sel = w[z > z.max() - (z.max() - z.min()) / 3]',
            'sel = sel[np.random.default_rng(1).choice(len(sel), min(3000, len(sel)), replace=False)]',
            'd, _ = cKDTree(out.sample(200000)).query(sel)',
            'print(float(np.median(d)))',
        ]);
        $file = sys_get_temp_dir().'/mp_median_'.uniqid().'.py';
        file_put_contents($file, $code);
        $out = shell_exec(escapeshellcmd($this->python()).' '.escapeshellarg($file).' '.escapeshellarg((string) $f->glbPath()).' '.escapeshellarg($f->absolutePath()).' '.escapeshellarg((string) json_encode($matrix)));
        @unlink($file);

        return (float) trim((string) $out);
    }

    public function test_a_generated_file_keeps_the_matrix_and_the_page_gets_it(): void
    {
        $f = $this->generate('round');
        $glb = $f->tool_params['glb'] ?? null;
        $this->assertIsArray($glb);
        $this->assertCount(16, $glb['matrix']);
        $this->assertCount(16, $glb['to_full']);              // a bust keeps its whole figure
        $this->assertCount(16, $glb['to_source']);
        $this->assertGreaterThan(1.0, $glb['base_top_z']);    // it stands on a base
        $this->assertLessThan(0.5, $this->median($f, $glb['matrix']));

        $this->getJson(route('api.files.show', $f->uuid))->assertOk()
            ->assertJsonPath('file.glb_matrix', $glb['matrix'])
            ->assertJsonPath('file.glb_base_top_z', $glb['base_top_z']);
    }

    public function test_a_file_without_a_base_has_a_zero_height_and_an_old_file_no_matrix(): void
    {
        $f = $this->generate('none');
        $this->assertEquals(0.0, $f->tool_params['glb']['base_top_z']);

        $f->forceFill(['tool_params' => null])->save();
        $this->getJson(route('api.files.show', $f->uuid))->assertOk()->assertJsonPath('file.glb_matrix', null)->assertJsonPath('file.glb_base_top_z', null)
            ->assertJsonPath('file.glb_url', route('api.files.glb', $f->uuid));
    }

    public function test_a_changed_base_composes_the_matrix_and_reads_the_glb_of_the_first_file(): void
    {
        $first = $this->generate('round');
        $res = $this->postJson('/api/files/'.$first->uuid.'/pedestal', ['type' => 'classic', 'cut_shift' => 10, 'pitch' => 10, 'tidy' => false])->assertCreated();
        $second = ModelFile::where('uuid', $res->json('file.uuid'))->firstOrFail();

        $this->assertFileDoesNotExist(Storage::disk('models')->path($second->dir().'/raw.glb'));       // 60 MB are not copied
        $this->assertSame($first->glbPath(), $second->glbPath());
        $glb = $second->tool_params['glb'];
        $this->assertCount(16, $glb['matrix']);
        $this->assertNotEquals($first->tool_params['glb']['matrix'], $glb['matrix']);
        $this->assertLessThan(0.5, $this->median($second, $glb['matrix']));                            // composed with the first file's to_full: exact
        $this->getJson(route('api.files.show', $second->uuid))->assertOk()->assertJsonPath('file.glb_url', route('api.files.glb', $second->uuid))
            ->assertJsonPath('file.glb_matrix', $glb['matrix']);
        $this->get(route('api.files.glb', $second->uuid))->assertOk()->assertHeader('Content-Type', 'model/gltf-binary');

        // and once more, from the second file
        $res = $this->postJson('/api/files/'.$second->uuid.'/pedestal', ['type' => 'round', 'cut_shift' => -10, 'tidy' => false])->assertCreated();
        $third = ModelFile::where('uuid', $res->json('file.uuid'))->firstOrFail();
        $this->assertSame($first->glbPath(), $third->glbPath());
        $this->assertLessThan(0.5, $this->median($third, $third->tool_params['glb']['matrix']));
    }

    public function test_a_file_made_before_the_matrix_stays_without_one_after_a_change(): void
    {
        $first = $this->generate('round');
        $first->forceFill(['tool_params' => null])->save();
        $res = $this->postJson('/api/files/'.$first->uuid.'/pedestal', ['type' => 'round', 'tidy' => false])->assertCreated();
        $second = ModelFile::where('uuid', $res->json('file.uuid'))->firstOrFail();
        $this->assertArrayNotHasKey('glb', (array) $second->tool_params);
        $this->assertNull($second->glbFit());
        $this->assertNotNull($second->glbPath());           // the colours are still offered, fitted by estimate in the browser
    }

    public function test_the_step_buttons_are_on_the_bust_page_and_the_calculator_panel_comes_before_the_material(): void
    {
        $this->get('/tools/figure')->assertOk()->assertSee('id="pedestal-cut"', false)->assertSee('id="pedestal-pitch"', false)->assertSee('data-step="-20"', false);
        $calc = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($calc, 'id="materials"'), strpos($calc, 'id="pedestal-box"'));
        $this->assertLessThan(strpos($calc, 'id="mold-box"'), strpos($calc, 'id="pedestal-box"'));
        $this->assertSame(1, substr_count($calc, 'id="pedestal-cut"'));
    }

    public function test_the_matrices_compose_as_the_second_applied_after_the_first(): void
    {
        $a = [1, 0, 0, 5, 0, 1, 0, 0, 0, 0, 2, 0, 0, 0, 0, 1];     // move x by 5, scale z by 2
        $b = [0, -1, 0, 0, 1, 0, 0, 3, 0, 0, 1, 0, 0, 0, 0, 1];    // a quarter turn about z, move y by 3
        $m = ModelNormalizer::multiply($a, $b);                      // b first, then a
        $p = [1.0, 0.0, 0.0, 1.0];                                   // b: (0, 4, 0); then a: (5, 4, 0)
        $out = [];
        for ($i = 0; $i < 4; $i++) {
            $out[] = $m[$i * 4] * $p[0] + $m[$i * 4 + 1] * $p[1] + $m[$i * 4 + 2] * $p[2] + $m[$i * 4 + 3] * $p[3];
        }
        $this->assertEqualsWithDelta([5.0, 4.0, 0.0, 1.0], $out, 1e-9);
    }
}
