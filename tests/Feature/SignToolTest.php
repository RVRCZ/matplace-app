<?php

namespace Tests\Feature;

use App\Domain\Tools\SignGenerator;
use App\Engines\Mesh\StlTopology;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Sign generator: real CadQuery run when Python + cadquery are installed, otherwise skipped. */
class SignToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders(): void
    {
        $this->get('/tools/sign')->assertOk();
        $this->get('/tools/sign?lang=es')->assertOk();
    }

    public function test_validation(): void
    {
        $this->postJson('/api/tools/sign', ['line1' => ''])->assertStatus(422);
        $this->postJson('/api/tools/sign', ['line1' => 'x', 'shape' => 'star'])->assertStatus(422);
    }

    public function test_generates_exact_watertight_model_with_diacritics(): void
    {
        if (! app(SignGenerator::class)->available()) {
            $this->markTestSkipped('Python with cadquery is not installed.');
        }
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);

        $r = $this->postJson('/api/tools/sign', ['line1' => 'Žluťoučký kůň', 'line2' => 'č. p. 12', 'text_height' => 10, 'style' => 'emboss', 'shape' => 'rounded', 'hole' => true, 'thickness' => 3, 'relief' => 1.2]);
        $r->assertCreated();
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame('tool', $file->origin);
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $this->assertEqualsWithDelta(4.2, $file->bbox['z'], 0.05);           // 3 mm plate + 1.2 mm letters, exact
        $this->assertGreaterThan(40, $file->bbox['x']);
        $this->assertSame('zlutoucky-kun-c-p-12.stl', $file->original_name);

        // the design can be reopened with the same settings (our own geometry, no AI credits)
        $r->assertJsonPath('file.tool.params.line1', 'Žluťoučký kůň')->assertJsonPath('file.tool.params.hole', true);
        $this->assertStringContainsString('/tools/sign?from='.$file->uuid, $r->json('file.tool.url'));

        // prices like any other model
        $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PLA'])->assertCreated()->assertJsonPath('calculation.status', 'done');
    }

    public function test_live_sign_has_two_colour_parts_and_an_outline_style(): void
    {
        if (! app(SignGenerator::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        Storage::fake('models');
        $this->get('/tools/sign')->assertOk()->assertSee('data-choice="style"', false);
        $meta = fn ($r) => json_decode($r->headers->get('X-Model-Meta'), true);
        $full = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['line1' => 'Žluťoučký kůň', 'style' => 'emboss', 'two_color' => true, 'keyring' => true, 'radius' => 8]])->assertOk());
        $this->assertEqualsWithDelta(3 + 1.2, $full['bbox']['z'], 0.05);                     // plate plus raised letters, exact
        $this->assertNotEmpty($full['notes']['regions']);                                      // the preview colours the letters
        $text = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['line1' => 'Žluťoučký kůň', 'two_color' => true], 'part' => 'text'])->assertOk());
        $this->assertSame('text', $text['part']);
        $this->assertEqualsWithDelta(1.2, $text['bbox']['z'], 0.05);
        $outline = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['line1' => 'Roman', 'style' => 'outline']])->assertOk());
        $this->assertLessThan($full['volume_mm3'], $outline['volume_mm3']);

        // the default sign (raised text, rim) is one closed body: the rim used to be a second solid glued onto the same outer wall
        foreach ([['line1' => 'Jana'], ['line1' => 'Jana', 'shape' => 'oval'], ['line1' => 'Jana', 'shape' => 'rect', 'keyring' => true], ['line1' => 'Jana', 'bevel' => true, 'border' => false]] as $params) {
            $stl = tempnam(sys_get_temp_dir(), 'sign').'.stl';
            file_put_contents($stl, $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => $params])->assertOk()->streamedContent());
            $topo = StlTopology::check($stl);
            @unlink($stl);
            $this->assertTrue($topo['watertight'], json_encode($params).' open '.$topo['open_edges'].' non-manifold '.$topo['non_manifold_edges']);
        }
        $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['line1' => '']])->assertStatus(422);

        $r = $this->postJson('/api/tools/param', ['kind' => 'sign', 'params' => ['line1' => 'Eva', 'two_color' => true]])->assertCreated();
        $this->assertSame(['plate', 'text'], $r->json('file.parts'));
        $this->assertStringContainsString('/tools/sign?from=', $r->json('file.tool.url'));
    }
}
