<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
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
        // the tray is one body (see docs/Y.md): two parts, and the farm prints them by parts like a box and its lid
        $this->assertSame(['body', 'stand'], OrderService::designParts(ModelFile::where('uuid', $created->json('file.uuid'))->firstOrFail()));
    }

    public function test_the_hair_tie_holder_is_a_post_on_a_base_with_a_figure_behind(): void
    {
        $rabbit = ['artwork' => 'lib:hearts-stars/crown', 'width' => 90, 'thickness' => 3, 'post_d' => 14, 'post_h' => 90];
        // three parts, each one colour: the base (8 mm, wide and deep enough to stand), the post, the figure
        $base = $this->meta('hair_tie', $rabbit, 'stand');
        $this->assertEqualsWithDelta(8, $base['bbox']['z'], 0.05);
        $this->assertGreaterThanOrEqual(70, $base['bbox']['x']);
        $this->assertGreaterThanOrEqual(60, $base['bbox']['y']);
        // the post goes through the base from below: as tall as the base and what was asked for above it, and its foot
        // is a cone 3 mm wider all round, which is what holds it when a tie is pulled off upwards
        $post = $this->meta('hair_tie', $rabbit, 'post');
        $this->assertEqualsWithDelta(8 + 90, $post['bbox']['z'], 0.05);
        $this->assertEqualsWithDelta(14 + 2 * 3, $post['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(14 + 2 * 3, $post['bbox']['y'], 0.05);
        $this->assertEqualsWithDelta(M_PI * 49 * 98, $post['volume_mm3'], 0.08 * M_PI * 49 * 98);
        // a thicker and taller post is more plastic by about its cylinder, and its hole takes more out of the base
        $thick = $this->meta('hair_tie', ['post_d' => 24, 'post_h' => 120] + $rabbit, 'post');
        $this->assertEqualsWithDelta(8 + 120, $thick['bbox']['z'], 0.05);
        $this->assertEqualsWithDelta(M_PI * 144 * 128 - M_PI * 49 * 98, $thick['volume_mm3'] - $post['volume_mm3'], 0.1 * M_PI * 144 * 128);
        $this->assertLessThan($base['volume_mm3'], $this->meta('hair_tie', ['post_d' => 24] + $rabbit, 'stand')['volume_mm3']);
        // the hole is the post with a quarter of a millimetre of play all round (the base without it would be heavier by more than the post's part in it)
        $inBase = M_PI * 49 * 8;
        $solid = $base['bbox']['x'] * $base['bbox']['y'] * 8;
        $this->assertGreaterThan($inBase, $solid - $base['volume_mm3']);
        // put together it is as tall as the taller of the two: the post or the figure in its slot
        $figure = $this->meta('hair_tie', $rabbit, 'body');
        $use = $this->meta('hair_tie', $rabbit, 'all', 'use');
        $this->assertEqualsWithDelta(max(98, 8 - 6 + $figure['bbox']['y']), $use['bbox']['z'], 0.05);
        // the preview says which piece is which part and where each colour lies: the figure, the post above the base, the base
        $shown = PreviewMeta::whole($this->postJson('/api/tools/param/preview', ['kind' => 'hair_tie', 'params' => $rabbit, 'view' => 'use', 'pieces' => true])->assertOk()->headers->get('X-Model-Meta'));
        $this->assertEqualsCanonicalizing(['body', 'stand', 'post'], array_values(array_unique(array_column($shown['parts'], 'name'))));
        $this->assertSame(['body', 'post', 'stand'], array_column($shown['notes']['regions'], 'part'));
        // laid out for printing the three lie apart on the bed: the post stands on its foot behind the base
        $plate = PreviewMeta::whole($this->postJson('/api/tools/param/preview', ['kind' => 'hair_tie', 'params' => $rabbit, 'pieces' => true])->assertOk()->headers->get('X-Model-Meta'));
        $this->assertCount(3, $plate['parts']);
        $this->assertEqualsWithDelta(98, $plate['bbox']['z'], 0.05);
        // a design is stored with its three parts, each of which has to fit a printer by itself, and with their colours
        $created = $this->postJson('/api/tools/param', ['kind' => 'hair_tie', 'params' => $rabbit + ['part_colors' => ['body' => '#e91e63', 'stand' => '#222222', 'post' => '#f5f5f5']]])->assertCreated();
        $this->assertSame(['body', 'stand', 'post'], array_keys($created->json('file.tool.params.parts_bbox')));
        $this->assertSame(['body' => '#e91e63', 'stand' => '#222222', 'post' => '#f5f5f5'], array_map(fn ($c) => $c['hex'], $created->json('file.tool.params.part_colors')));
        $this->assertSame(['body', 'stand', 'post'], $created->json('file.parts'));
        // and every part comes in the ZIP
        $zip = $this->postJson('/api/tools/param/zip', ['kind' => 'hair_tie', 'params' => $rabbit])->assertOk();
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($zip->baseResponse->getFile()->getPathname()));
        $this->assertSame(['hair-tie.stl', 'hair-tie-body.stl', 'hair-tie-stand.stl', 'hair-tie-post.stl'], array_map(fn ($i) => $archive->getNameIndex($i), range(0, $archive->numFiles - 1)));
        $archive->close();
        // the farm prints such a design by parts, each from its own spool: it can build every part by itself
        $file = ModelFile::where('uuid', $created->json('file.uuid'))->firstOrFail();
        $this->assertSame(['body', 'stand', 'post'], OrderService::designParts($file));
        foreach (['stand' => 8, 'post' => 98] as $part => $tall) {
            $stl = app(OrderService::class)->partStl($file, $part);
            $this->assertNotNull($stl, $part);
            $this->assertGreaterThan(1000, filesize($stl['path']));
            @unlink($stl['path']);
        }
        $this->assertSame(__('param.part.post.hair_tie'), OrderService::partLabel($file, 'post'));
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
