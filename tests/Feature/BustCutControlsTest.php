<?php

namespace Tests\Feature;

use App\Domain\Generation\ModelNormalizer;
use App\Domain\Generation\PedestalChanger;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Height of the chest cut and tilt of a generated bust (Roman, 22 Nov 2026): both start from the whole figure kept
 * beside the file (source_full.stl), like a change of base, so they cost no new generation.
 */
class BustCutControlsTest extends TestCase
{
    use RefreshDatabase;

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

    private function python(): string
    {
        return (string) (config('engines.python.bin') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'));
    }

    /** A bust made generation-style, then its whole figure replaced by a synthetic head, neck and shoulders. */
    private function bust(): string
    {
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'pedestal' => 'none'], ['Accept' => 'application/json'])->assertCreated();
        $uuid = $r->json('generation.file.uuid');
        $dir = Storage::disk('models')->path('files/'.$uuid);
        Storage::disk('models')->assertExists('files/'.$uuid.'/source_full.stl');          // kept next to the cut one
        $probe = shell_exec(escapeshellcmd($this->python()).' -c "import manifold3d, trimesh; print(1)" 2>&1');
        if (trim((string) $probe) !== '1') {
            $this->markTestSkipped('manifold3d is not installed.');
        }
        shell_exec(escapeshellcmd($this->python()).' '.escapeshellarg(base_path('tests/Support/synthetic_bust.py')).' '.escapeshellarg($dir.'/source_full.stl'));
        $this->assertFileExists($dir.'/source_full.stl');

        return $uuid;
    }

    /** x size of the stored print: it is scaled to one height, so the width shows where the chest was cut */
    private function width(string $uuid): float
    {
        return StlFile::stats(Storage::disk('models')->path('files/'.$uuid.'/original.stl'))->bbox->x;
    }

    public function test_a_bad_step_is_refused_and_only_a_bust_with_its_whole_figure_takes_the_controls(): void
    {
        $uuid = $this->bust();
        foreach ([['cut_shift' => 15], ['cut_shift' => 'x'], ['pitch' => 7], ['pitch' => 20], ['pitch' => -16]] as $bad) {
            $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none'] + $bad)->assertStatus(422);
        }
        $this->assertSame(1, GenerationRequest::count());

        // a bust made before the whole figure was kept: no controls, and a non-zero step is refused
        unlink(Storage::disk('models')->path('files/'.$uuid.'/source_full.stl'));
        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none', 'cut_shift' => 10])->assertStatus(422)->assertJsonPath('error', 'cut_unavailable');
        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none', 'pitch' => -5])->assertStatus(422)->assertJsonPath('error', 'cut_unavailable');
        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none', 'cut_shift' => 0, 'pitch' => 0])->assertCreated();
        $this->assertFalse(app(PedestalChanger::class)->state(ModelFile::where('uuid', $uuid)->firstOrFail())['cut_controls']);
    }

    public function test_the_steps_reach_the_engine_and_are_remembered(): void
    {
        $uuid = $this->bust();
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertTrue(app(PedestalChanger::class)->state($file)['cut_controls']);

        // straight to the changer: the engine's report says what it did with the two extras
        $normalizer = app(ModelNormalizer::class);
        $new = (new PedestalChanger($normalizer))->change($file, ['type' => 'none'], 'keep', 0, false, -20, 10);
        $report = $normalizer->report;
        $this->assertEquals(-0.2, $report['cut_shift'] ?? null);
        $this->assertEquals(10.0, $report['pitch'] ?? null);
        $this->assertTrue($report['bust_cut'] ?? false);
        $this->assertSame(['cut_shift' => -20, 'pitch' => 10], array_intersect_key($new->tool_params, ['cut_shift' => 1, 'pitch' => 1]));
        $this->assertFileExists(Storage::disk('models')->path('files/'.$new->uuid.'/source_full.stl'));      // the next change can move the cut again

        // through the API: the values come back in the page's state; a cut higher leaves a smaller piece, scaled up to the same height
        $high = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none', 'cut_shift' => -20, 'tidy' => false])->assertCreated()
            ->assertJsonPath('file.generation.pedestal.cut_shift', -20)->assertJsonPath('file.generation.pedestal.pitch', 0)->assertJsonPath('file.generation.pedestal.cut_controls', true);
        $mid = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none', 'tidy' => false])->assertCreated();
        $low = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none', 'cut_shift' => 10, 'tidy' => false])->assertCreated();
        $this->assertGreaterThan($this->width($mid->json('file.uuid')) + 3, $this->width($high->json('file.uuid')));
        $this->assertLessThan($this->width($mid->json('file.uuid')) - 2, $this->width($low->json('file.uuid')));
        $this->assertSame(1, GenerationRequest::count());                                   // no new generation, no credits
    }

    public function test_the_tilt_leans_the_head_over_the_front_and_the_print_stays_on_the_bed(): void
    {
        $uuid = $this->bust();
        $script = 'import sys,trimesh; m=trimesh.load(sys.argv[1]); v=m.vertices; z=v[:,2]; print(v[z>z.max()-12][:,1].mean()-v[z<z.min()+6][:,1].mean(), z.min())';
        $lean = function (int $pitch) use ($uuid, $script): float {
            $r = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'none', 'pitch' => $pitch, 'tidy' => false])->assertCreated();
            $path = Storage::disk('models')->path('files/'.$r->json('file.uuid').'/original.stl');
            $out = shell_exec(escapeshellcmd($this->python()).' -c '.escapeshellarg($script).' '.escapeshellarg($path));
            [$d, $floor] = array_map('floatval', explode(' ', trim((string) $out)));
            $this->assertEqualsWithDelta(0.0, $floor, 0.05);                                  // stands on the bed whatever the tilt

            return $d;
        };
        $straight = $lean(0);
        $this->assertLessThan($straight - 4, $lean(15));      // the head lies further to the front (-Y) than the chest
        $this->assertGreaterThan($straight + 4, $lean(-15));
    }

    public function test_the_calculator_page_has_the_step_buttons_in_three_languages(): void
    {
        foreach (['/' => ['Výška řezu', 'Náklon'], '/en' => ['Height of the cut', 'Tilt'], '/es' => ['Altura del corte', 'Inclinación']] as $url => $labels) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($labels as $label) {
                $this->assertStringContainsString($label, $html);
            }
            $this->assertSame(5, substr_count($html, 'data-step="') - 7);                  // five cuts, seven tilts
            $this->assertStringContainsString('data-step="-20"', $html);
            $this->assertStringContainsString('data-step="15"', $html);
        }
    }
}
