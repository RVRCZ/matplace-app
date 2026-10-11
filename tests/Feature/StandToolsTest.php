<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\PreviewMeta;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Figures that stand on something useful (engines/python/stand_kinds.py): the stand for sticky notes, the holder for hair ties, the stand for a candle. */
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

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
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

    public function test_the_hair_tie_holder_is_a_post_on_a_base_with_a_figure_behind(): void
    {
        $rabbit = ['artwork' => 'lib:hearts-stars/crown', 'width' => 90, 'thickness' => 3, 'post_d' => 14, 'post_h' => 90];
        $base = $this->meta('hair_tie', $rabbit, 'stand');
        // a base of 8 mm with the post on it: as tall as asked, one piece, wide and deep enough to stand
        $this->assertEqualsWithDelta(8 + 90, $base['bbox']['z'], 0.05);
        $this->assertGreaterThanOrEqual(70, $base['bbox']['x']);
        $this->assertGreaterThanOrEqual(60, $base['bbox']['y']);
        // a thicker and taller post is more plastic by about its cylinder
        $thick = $this->meta('hair_tie', ['post_d' => 24, 'post_h' => 120] + $rabbit, 'stand');
        $this->assertEqualsWithDelta(8 + 120, $thick['bbox']['z'], 0.05);
        $this->assertEqualsWithDelta(M_PI * 144 * 120 - M_PI * 49 * 90, $thick['volume_mm3'] - $base['volume_mm3'], 0.25 * M_PI * 144 * 120);
        // put together it is as tall as the taller of the two: the post or the figure in its slot
        $figure = $this->meta('hair_tie', $rabbit, 'body');
        $use = $this->meta('hair_tie', $rabbit, 'all', 'use');
        $this->assertEqualsWithDelta(max(98, 8 - 6 + $figure['bbox']['y']), $use['bbox']['z'], 0.05);
        $created = $this->postJson('/api/tools/param', ['kind' => 'hair_tie', 'params' => $rabbit])->assertCreated();
        $this->assertSame(['body', 'stand'], array_keys($created->json('file.tool.params.parts_bbox')));
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/hair-tie-holder')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.hair_tie.title', [], $locale)), $html);
            $this->assertStringContainsString('data-param="post_h"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
    }

    public function test_the_candle_stand_seats_a_jar_in_front_of_a_figure(): void
    {
        $tree = ['artwork' => 'lib:holidays/christmas-tree', 'width' => 100, 'thickness' => 3, 'jar_d' => 80];
        $base = $this->meta('candle_stand', $tree, 'stand');
        // a round platform for the jar (half a millimetre of play, a rim of 3 mm) with a block behind for the slot: 8 mm high
        $this->assertEqualsWithDelta(8, $base['bbox']['z'], 0.01);
        // (the block is as wide as the foot of the figure asks: a tree stands on its lowest branches, wider than the disc)
        $this->assertGreaterThanOrEqual(80 + 1 + 6, $base['bbox']['x']);
        $this->assertEqualsWithDelta(87 + 3 + 0.5 + 8, $base['bbox']['y'], 0.2);
        // the seat is a recess 3 mm deep: a solid disc of that size would hold its cylinder more
        $solid = M_PI * 43.5 * 43.5 * 8;
        $this->assertLessThan($solid + 12 * $base['bbox']['x'] * 8, $base['volume_mm3']);
        $this->assertGreaterThan($solid - M_PI * 40.5 * 40.5 * 3 - 500, $base['volume_mm3']);
        // a bigger jar is a bigger platform by just that
        $big = $this->meta('candle_stand', ['jar_d' => 103] + $tree, 'stand');
        $this->assertGreaterThanOrEqual(103 + 1 + 6, $big['bbox']['x']);
        $this->assertEqualsWithDelta($base['bbox']['y'] + 23, $big['bbox']['y'], 0.2);
        // put together, the figure stands behind the seat, its foot out of sight
        $figure = $this->meta('candle_stand', $tree, 'body');
        $use = $this->meta('candle_stand', $tree, 'all', 'use');
        $this->assertEqualsWithDelta(8 - 6 + $figure['bbox']['y'], $use['bbox']['z'], 0.05);
        $created = $this->postJson('/api/tools/param', ['kind' => 'candle_stand', 'params' => $tree])->assertCreated();
        $this->assertSame(['body', 'stand'], array_keys($created->json('file.tool.params.parts_bbox')));
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/candle-stand')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.candle_stand.title', [], $locale)), $html);
            $this->assertStringContainsString('data-param="jar_d"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
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
