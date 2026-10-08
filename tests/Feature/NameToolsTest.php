<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Things made of a name (engines/python/name_kinds.py): the pen holder in the shape of a name, letter beads. */
class NameToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    private function meta($response): array
    {
        return json_decode((string) $response->headers->get('X-Model-Meta'), true);
    }

    private function preview(string $kind, array $params, bool $pieces = false)
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'pieces' => $pieces]);
    }

    public function test_the_page_renders_in_three_languages(): void
    {
        foreach (['cs', 'en', 'es'] as $lang) {
            app()->setLocale($lang);
            $page = $this->get($this->localized('/tools/name-organizer', $lang))->assertOk();
            $page->assertSee(__('tools.name_cup.title'))->assertSee(__('param.name_cup.lead'))->assertSee(__('param.flag.base'));
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $page->getContent(), $lang);
            foreach (['cup_narrow', 'pieces_tied'] as $w) {
                $this->assertNotSame('param.warn.'.$w, __('param.warn.'.$w), "{$w} ({$lang})");
            }
        }
    }

    public function test_a_name_becomes_a_pen_holder_as_wide_and_as_tall_as_asked(): void
    {
        $cup = $this->meta($this->preview('name_cup', ['line1' => 'Jana', 'width' => 160, 'height' => 80, 'wall' => 1.6, 'floor' => 2, 'base' => false], true)->assertOk());
        $this->assertEqualsWithDelta(160, $cup['bbox']['x'], 0.5);
        $this->assertEqualsWithDelta(80, $cup['bbox']['z'], 0.01);
        $this->assertCount(1, $cup['parts']);                                     // one piece, whatever the letters do
        // a cup, not a block: walls and a bottom, far less plastic than the solid shape would be
        $this->assertLessThan(0.35 * $cup['bbox']['x'] * $cup['bbox']['y'] * 80, $cup['volume_mm3']);
        $this->assertGreaterThan(9, $cup['notes']['pocket_mm']);
        $this->assertArrayNotHasKey('color_change_mm', $cup['notes']);

        // a base under it: 3 mm taller, wider all round, and the place where a second colour may start
        $based = $this->meta($this->preview('name_cup', ['line1' => 'Jana', 'width' => 160, 'height' => 80, 'base' => true])->assertOk());
        $this->assertEqualsWithDelta(83, $based['bbox']['z'], 0.02);
        $this->assertEqualsWithDelta(160, $based['bbox']['x'], 0.5);
        $this->assertEqualsWithDelta(3, $based['notes']['color_change_mm'], 0.001);
        $this->assertGreaterThan($cup['volume_mm3'], $based['volume_mm3']);

        // thicker walls and bottom take more plastic; a taller cup is taller
        $thick = $this->meta($this->preview('name_cup', ['line1' => 'Jana', 'width' => 160, 'height' => 100, 'wall' => 3, 'floor' => 4, 'base' => false])->assertOk());
        $this->assertEqualsWithDelta(100, $thick['bbox']['z'], 0.01);
        $this->assertGreaterThan($cup['volume_mm3'] * 1.5, $thick['volume_mm3']);

        // letters of a printed typeface are pockets of their own, still one piece
        $tom = $this->meta($this->preview('name_cup', ['line1' => 'T O M', 'typeface' => 'sans', 'width' => 200, 'base' => false], true)->assertOk());
        $this->assertCount(1, $tom['parts']);
        $this->assertContains('pieces_tied', $tom['notes']['warnings']);
        // a long name on the narrowest holder still has pockets: every letter is grown by 3 mm to each side, neighbours run together
        $long = $this->meta($this->preview('name_cup', ['line1' => 'Maxmiliánkovi', 'typeface' => 'mono', 'width' => 80, 'base' => false])->assertOk());
        $this->assertGreaterThan(6, $long['notes']['pocket_mm']);
        $this->assertEqualsWithDelta(80, $long['bbox']['x'], 0.5);

        $this->assertSame(__('param.text_required'), $this->preview('name_cup', ['line1' => ''])->assertStatus(422)->json('errors')['params.line1'][0]);
        $this->preview('name_cup', ['line1' => 'Jana', 'width' => 400])->assertStatus(422);
    }

    public function test_a_text_becomes_a_row_of_beads_with_a_letter_each(): void
    {
        foreach (['cs', 'en', 'es'] as $lang) {
            app()->setLocale($lang);
            $page = $this->get($this->localized('/tools/letter-beads', $lang))->assertOk()->assertSee(__('tools.beads.title'))->assertSee(__('param.o.beads.heart'));
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $page->getContent(), $lang);
        }
        app()->setLocale('cs');
        $b = $this->meta($this->preview('beads', ['line1' => 'JANA', 'shape' => 'cube', 'size' => 10, 'hole' => 2.5, 'relief' => 0.6, 'style' => 'raised'], true)->assertOk());
        $n = $b['notes'];
        $this->assertSame(4, $n['count']);
        $this->assertSame(['body', 'text'], $n['parts']);
        $this->assertSame([10.0, 10.0, 10.6], array_map('floatval', $n['each']));
        $this->assertEqualsWithDelta(4 * 10 + 3 * 3, $b['bbox']['x'], 0.05);            // four beads and three gaps
        $this->assertEqualsWithDelta(10, $n['color_change_mm'], 0.001);                  // the letters start where the beads end: the second colour
        // a bead is a cube with a hole through its side and a letter sunk into its bottom: less than the cube, most of it
        $one = $this->meta($this->preview('beads', ['line1' => 'A', 'shape' => 'cube', 'size' => 10, 'hole' => 2.5, 'style' => 'engraved', 'two_sides' => false])->assertOk());
        $this->assertLessThan(1000 - M_PI * 1.25 * 1.25 * 10 * 0.9, $one['volume_mm3']);
        $this->assertGreaterThan(850, $one['volume_mm3']);
        $this->assertArrayNotHasKey('color_change_mm', $one['notes']);
        $this->assertSame([], $one['notes']['parts']);
        $both = $this->meta($this->preview('beads', ['line1' => 'A', 'shape' => 'cube', 'size' => 10, 'hole' => 2.5, 'style' => 'engraved', 'two_sides' => true])->assertOk());
        $this->assertLessThan($one['volume_mm3'], $both['volume_mm3']);
        // a space is a bead without a letter; a long text goes on in a second row
        $spaced = $this->meta($this->preview('beads', ['line1' => 'A B', 'size' => 10])->assertOk())['notes'];
        $this->assertSame(3, $spaced['count']);
        $rows = $this->meta($this->preview('beads', ['line1' => 'ABCDEFGHIJ', 'size' => 10])->assertOk());
        $this->assertEqualsWithDelta(8 * 10 + 7 * 3, $rows['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(2 * 10 + 3, $rows['bbox']['y'], 0.05);
        // the other shapes; a hole too big for a low bead is refused in words
        foreach (['ball', 'heart', 'star'] as $shape) {
            $s = $this->meta($this->preview('beads', ['line1' => 'EVA', 'shape' => $shape, 'size' => 12, 'hole' => 2])->assertOk());
            $this->assertSame(3, $s['notes']['count'], $shape);
            $this->assertLessThan(60, $s['bbox']['x'], $shape);
        }
        $big = $this->preview('beads', ['line1' => 'EVA', 'shape' => 'heart', 'size' => 8, 'hole' => 4])->assertStatus(422);
        $this->assertSame(__('param.error.bead_hole_big'), $big->json('errors.params.0'));
        $this->preview('beads', ['line1' => ''])->assertStatus(422);

        // stored: the beads and their letters are two parts, each a file of its own
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);
        $r = $this->postJson('/api/tools/param', ['kind' => 'beads', 'params' => ['line1' => 'EVA', 'size' => 12]])->assertCreated();
        $this->assertSame(['body', 'text'], $r->json('file.parts'));
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(12, $file->colorChangeMm(), 0.001);
        $this->get('/api/tools/param/'.$file->uuid.'/text.stl')->assertOk();
    }

    public function test_a_created_holder_opens_again_and_offers_its_second_colour(): void
    {
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);
        $r = $this->postJson('/api/tools/param', ['kind' => 'name_cup', 'params' => ['line1' => 'Ela', 'width' => 110, 'height' => 60]])->assertCreated();
        $r->assertJsonPath('file.kind', 'name_cup')->assertJsonPath('file.tool.kind', 'name_cup');
        $this->assertStringContainsString('/tools/name-organizer?from=', $r->json('file.tool.url'));
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertEqualsWithDelta(3, $file->colorChangeMm(), 0.001);
        $this->assertCount(1, $file->colorChanges());
        $this->assertSame(['supports' => false], $file->printHints());
    }
}
