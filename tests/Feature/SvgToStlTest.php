<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\PreviewMeta;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "SVG to STL": the logo tool's plain extrusion as a tool of its own in the catalogue (/tools/svg-to-stl). */
class SvgToStlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    /** @return array<string, mixed> */
    private function meta(array $params): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'logo', 'params' => $params + ['mode' => 'cutout', 'line1' => '']])->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
    }

    public function test_the_page_is_the_logo_tool_opened_as_a_plain_extrusion(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/svg-to-stl')->assertOk()->getContent();
            $this->assertStringContainsString(__('tools.svg_to_stl.title', [], $locale), $html);
            $this->assertStringContainsString(e(ToolSeo::texts('svg_to_stl', $locale)['h1']), $html);
            // the same form and the same generator as the logo, started with the preset and a picture of ours
            $this->assertStringContainsString('kind: "logo"', $html);
            $this->assertStringContainsString('preset: "extrude"', $html);
            $this->assertStringContainsString('sample: "lib:animals\/cat"', $html);
        }
        // the logo's own page starts as it always did
        $logo = $this->get('/tools/logo')->assertOk()->getContent();
        $this->assertStringContainsString('preset: null', $logo);
        $this->assertStringContainsString('sample: null', $logo);
        $this->assertStringContainsString(__('tools.logo.title', [], 'cs'), $logo);
    }

    public function test_an_outline_is_pulled_up_as_high_as_asked(): void
    {
        $cat = ['artwork' => 'lib:animals/cat', 'width' => 80];
        $low = $this->meta($cat + ['thickness' => 5]);
        $this->assertEqualsWithDelta(80, $low['bbox']['x'], 0.2);
        $this->assertEqualsWithDelta(5, $low['bbox']['z'], 0.01);
        // six times the height is six times the plastic: a prism, nothing else
        $tall = $this->meta($cat + ['thickness' => 30]);
        $this->assertEqualsWithDelta(30, $tall['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta(6 * $low['volume_mm3'], $tall['volume_mm3'], 0.01 * $tall['volume_mm3']);
        $this->postJson('/api/tools/param/preview', ['kind' => 'logo', 'params' => $cat + ['mode' => 'cutout', 'thickness' => 51]])->assertStatus(422);
    }

    public function test_a_bevel_takes_a_little_off_the_top_edge_and_nothing_else(): void
    {
        $cat = ['artwork' => 'lib:animals/cat', 'width' => 80, 'thickness' => 5];
        $plain = $this->meta($cat);
        $bevel = $this->meta($cat + ['bevel' => true]);
        $this->assertSame($plain['bbox'], $bevel['bbox']);
        $this->assertLessThan($plain['volume_mm3'], $bevel['volume_mm3']);
        $this->assertGreaterThan(0.9 * $plain['volume_mm3'], $bevel['volume_mm3']);
        // on a plate there is no edge of the shape to bevel: the flag changes nothing
        $relief = ['artwork' => 'lib:animals/cat', 'width' => 80, 'mode' => 'relief'];
        $this->assertEqualsWithDelta($this->meta($relief)['volume_mm3'], $this->meta($relief + ['bevel' => true])['volume_mm3'], 0.01);
    }
}
