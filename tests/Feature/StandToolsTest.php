<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Figures that stand on something useful (engines/python/stand_kinds.py): the stand for sticky notes. */
class StandToolsTest extends TestCase
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
    private function meta(string $kind, array $params, string $part = 'all', string $view = 'print'): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'part' => $part, 'view' => $view])->assertOk();

        return json_decode((string) $r->headers->get('X-Model-Meta'), true);
    }

    public function test_the_notes_stand_is_a_tray_for_the_pad_and_a_figure_in_its_slot(): void
    {
        $cat = ['artwork' => 'lib:animals/cat', 'width' => 90, 'thickness' => 3, 'pad' => 76, 'depth' => 12];
        $tray = $this->meta('notes', $cat, 'stand');
        $figure = $this->meta('notes', $cat, 'body');
        // the tray: the pad with a millimetre of air and walls of 2 mm; a floor of 2 mm under 12 mm of depth
        $this->assertEqualsWithDelta(76 + 2 + 4, min($tray['bbox']['x'], 82), 0.01);
        $this->assertGreaterThanOrEqual(82, $tray['bbox']['x']);
        $this->assertEqualsWithDelta(14, $tray['bbox']['z'], 0.01);
        $this->assertGreaterThan(76 + 2 + 2 + 12, $tray['bbox']['y']);
        // the figure lies flat, as wide and as thick as asked; its foot makes it taller than the cat alone
        $this->assertEqualsWithDelta(90, $figure['bbox']['x'], 0.1);
        $this->assertEqualsWithDelta(3, $figure['bbox']['z'], 0.01);
        // put together, the figure stands in the slot: the foot's 6 mm are out of sight
        $use = $this->meta('notes', $cat, 'all', 'use');
        $this->assertEqualsWithDelta(14 - 6 + $figure['bbox']['y'], $use['bbox']['z'], 0.05);
        $this->assertEqualsWithDelta($use['bbox']['z'], $use['notes']['outer'][2], 0.05);
        $this->assertContains('glue_optional', $use['notes']['needs']);
        // a bigger pad is a bigger tray, a deeper tray a taller one; without the groove the block behind is shorter
        $big = $this->meta('notes', ['pad' => 101, 'depth' => 25] + $cat, 'stand');
        $this->assertEqualsWithDelta(27, $big['bbox']['z'], 0.01);
        $this->assertGreaterThan($tray['bbox']['x'] + 20, $big['bbox']['x']);
        $plain = $this->meta('notes', ['pen' => false] + $cat, 'stand');
        $this->assertEqualsWithDelta($tray['bbox']['y'] - 12, $plain['bbox']['y'], 0.01);
        $this->assertGreaterThan($plain['volume_mm3'], $tray['volume_mm3'] + 500);
        // a name stands as well as a picture; nothing at all is refused in words
        $name = $this->meta('notes', ['line1' => 'Jana', 'typeface' => 'script', 'width' => 100], 'body');
        $this->assertEqualsWithDelta(100, $name['bbox']['x'], 0.5);
        $this->postJson('/api/tools/param/preview', ['kind' => 'notes', 'params' => ['width' => 90]])->assertStatus(422);
        // a design is stored with both parts, each of which has to fit a printer by itself
        $created = $this->postJson('/api/tools/param', ['kind' => 'notes', 'params' => $cat])->assertCreated();
        $this->assertSame(['body', 'stand'], array_keys($created->json('file.tool.params.parts_bbox')));
    }

    public function test_the_page_opens_with_a_cat_in_three_languages(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/sticky-notes')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.notes.title', [], $locale)), $html);
            $this->assertStringContainsString(e(ToolSeo::texts('notes', $locale)['h1']), $html);
            $this->assertStringContainsString('sample: "lib:animals\/cat"', $html);
            $this->assertStringContainsString('data-param="pad"', $html);
            $this->assertStringContainsString('data-flag="pen"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
    }
}
