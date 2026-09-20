<?php

namespace Tests\Feature;

use App\Domain\Tools\SignGenerator;
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
}
