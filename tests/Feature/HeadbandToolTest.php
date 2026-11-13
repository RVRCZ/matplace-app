<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The headband with a decoration on top (engines/python/headband_kind.py, docs/P.md §13): sized by the head, printed
 * on its side, the decoration a flat part of its own pushed into a pocket, or grown together with the band.
 */
class HeadbandToolTest extends TestCase
{
    use RefreshDatabase;

    private const DECORATIONS = ['horns', 'cat', 'bunny', 'antlers', 'crown', 'halo', 'unicorn'];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    /** @return array<string, mixed> */
    private function meta(array $params, string $part = 'all', string $view = 'print'): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'headband', 'params' => $params, 'part' => $part, 'view' => $view])->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
    }

    public function test_the_band_follows_the_head_and_lies_on_its_side(): void
    {
        $child = $this->meta(['decoration' => 'none', 'head' => 52]);
        // on its side: the band's width is the height of the print, every layer runs along the arc
        $this->assertEqualsWithDelta(12, $child['bbox']['z'], 0.01);
        // a head of 52 cm is about 140 mm across; the band is narrower inside, so that it holds, and never wider than the head
        $this->assertEqualsWithDelta(0.83 * 0.27 * 520, $child['notes']['inner'][0], 0.6);
        $this->assertLessThan(0.27 * 520, $child['bbox']['x']);
        $this->assertSame([], $child['notes']['parts']);
        $this->assertSame([], $child['notes']['warnings']);
        // an adult's is bigger by the same rule, a wider band is a taller print, and both fit a 250 mm bed
        $adult = $this->meta(['decoration' => 'none', 'head' => 57, 'band' => 20]);
        $this->assertEqualsWithDelta(0.83 * 0.27 * 570, $adult['notes']['inner'][0], 0.6);
        $this->assertEqualsWithDelta(20, $adult['bbox']['z'], 0.01);
        $this->assertGreaterThan($child['volume_mm3'], $adult['volume_mm3']);
        $biggest = $this->meta(['decoration' => 'none', 'head' => 64, 'band' => 25, 'thickness' => 4]);
        $this->assertLessThan(240, max($biggest['bbox']['x'], $biggest['bbox']['y']));
        // the teeth are plastic added inside, nothing else changes
        $smooth = $this->meta(['decoration' => 'none', 'head' => 52, 'teeth' => false]);
        $this->assertLessThan($child['volume_mm3'], $smooth['volume_mm3']);
        $this->assertGreaterThan($child['volume_mm3'] - 400, $smooth['volume_mm3']);
        $this->assertEqualsWithDelta($child['bbox']['x'], $smooth['bbox']['x'], 0.3);
        // putting it on stretches the plastic: the default stays well under what PLA takes, a thick band on a small head says so
        $this->assertLessThan(1.4, $child['notes']['strain_pct']['put_on']);
        $this->assertLessThan($child['notes']['strain_pct']['put_on'], $child['notes']['strain_pct']['worn']);
        $stiff = $this->meta(['decoration' => 'none', 'head' => 46, 'thickness' => 4]);
        $this->assertContains('headband_stiff', $stiff['notes']['warnings']);
    }

    public function test_every_decoration_is_a_flat_part_pushed_into_a_pocket_of_the_band(): void
    {
        $plain = $this->meta(['decoration' => 'none']);
        foreach (self::DECORATIONS as $decoration) {
            $p = ['decoration' => $decoration];
            $laid = $this->meta($p);
            $this->assertSame(['body', 'details'], $laid['notes']['parts'], $decoration);
            $this->assertSame([], $laid['notes']['warnings'], $decoration);
            // laid on the bed together they fit one 250 mm plate, nothing is taller than the band is wide
            $this->assertLessThan(240, max($laid['bbox']['x'], $laid['bbox']['y']), $decoration);
            $this->assertEqualsWithDelta(12, $laid['bbox']['z'], 0.01, $decoration);
            $band = $this->meta($p, 'body');
            $flat = $this->meta($p, 'details');
            // the band carries a boss with a pocket for every tongue: a little more plastic than the plain band
            $this->assertGreaterThan($plain['volume_mm3'], $band['volume_mm3'], $decoration);
            $this->assertLessThan($plain['volume_mm3'] + 2500, $band['volume_mm3'], $decoration);
            // the decoration lies flat, 4 mm thick; the parts add up to the plate
            $this->assertEqualsWithDelta(4, $flat['bbox']['z'], 0.01, $decoration);
            $this->assertEqualsWithDelta($laid['volume_mm3'], $band['volume_mm3'] + $flat['volume_mm3'], 1.0, $decoration);
            // put together it stands as worn: the arc upright, the decoration above it, nothing lost
            $worn = $this->meta($p, 'all', 'use');
            $this->assertEqualsWithDelta(12, $worn['bbox']['y'], 0.01, $decoration);
            $this->assertGreaterThan($band['bbox']['y'] + 10, $worn['bbox']['z'], $decoration);
            $this->assertEqualsWithDelta($laid['volume_mm3'], $worn['volume_mm3'], 1.0, $decoration);
        }
        // the size of a decoration is a share of its usual one
        $small = $this->meta(['decoration' => 'horns', 'deco' => 60], 'details');
        $big = $this->meta(['decoration' => 'horns', 'deco' => 160], 'details');
        $this->assertGreaterThan(2 * $small['volume_mm3'], $big['volume_mm3']);
        $this->assertEqualsWithDelta(45 * 1.6, $this->meta(['decoration' => 'horns', 'deco' => 160])['notes']['deco_mm'], 0.01);
        // a band narrower than the decoration is thick: the pocket goes through and the decoration is as thick as the band
        $through = $this->meta(['decoration' => 'cat', 'band' => 8, 'deco_t' => 8]);
        $this->assertTrue($through['notes']['pocket']['through']);
        $this->assertEqualsWithDelta(8, $through['bbox']['z'], 0.01);
        // two parts cannot share a place: the tool says what to change
        $this->postJson('/api/tools/param/preview', ['kind' => 'headband', 'params' => ['decoration' => 'unicorn', 'deco' => 160, 'spacing' => 30]])
            ->assertStatus(422)->assertJsonPath('errors.params.0', __('param.error.headband_overlap'));
    }

    public function test_grown_together_it_is_one_piece_in_one_colour(): void
    {
        foreach (self::DECORATIONS as $decoration) {
            $one = $this->meta(['decoration' => $decoration, 'mount' => 'fused']);
            $this->assertSame([], $one['notes']['parts'], $decoration);
            $this->assertEqualsWithDelta(12, $one['bbox']['z'], 0.01, $decoration);
            $pieces = $this->postJson('/api/tools/param/preview', ['kind' => 'headband', 'params' => ['decoration' => $decoration, 'mount' => 'fused'], 'pieces' => true])->assertOk();
            $this->assertCount(1, PreviewMeta::whole($pieces->headers->get('X-Model-Meta'))['parts'], $decoration.' is one body');
        }
        $this->assertSame([], ParametricGenerator::partsOf('headband', ['mount' => 'fused', 'decoration' => 'horns']));
        $this->assertSame([], ParametricGenerator::partsOf('headband', ['mount' => 'slot', 'decoration' => 'none']));
        $this->assertSame(['body', 'details'], ParametricGenerator::partsOf('headband', ['mount' => 'slot', 'decoration' => 'horns']));
    }

    public function test_a_stored_headband_keeps_its_parts_their_colours_and_their_places(): void
    {
        $created = $this->postJson('/api/tools/param', ['kind' => 'headband', 'params' => ['decoration' => 'horns', 'head_size' => 'child', 'head' => 60,
            'part_colors' => ['body' => 'black', 'details' => 'red']]])->assertCreated();
        $params = $created->json('file.tool.params');
        // the age brings its own number, whatever the form sent
        $this->assertSame('child', $params['head_size']);
        $this->assertEquals(51, $params['head']);
        $this->assertSame(['body', 'details'], $params['parts']);
        $this->assertSame(['body', 'details'], array_keys($params['parts_bbox']));
        $this->assertSame(['body', 'details'], array_keys($params['part_colors']));
        $file = ModelFile::where('uuid', $created->json('file.uuid'))->firstOrFail();
        // the farm prints the band and the decoration as parts, each from its own spool
        $this->assertSame(['body', 'details'], OrderService::designParts($file));
        $this->assertSame(__('param.part.body.headband'), OrderService::partLabel($file, 'body'));
        $this->assertSame(__('param.part.details.headband'), OrderService::partLabel($file, 'details'));
        $this->assertFalse($file->onePlateRequired());
        // each part alone stands where the plate has it: together on one plate they do not lie on one another
        $band = $this->meta($params, 'body');
        $flat = $this->meta($params, 'details');
        $laid = $this->meta($params);
        $this->assertEqualsWithDelta($laid['volume_mm3'], $band['volume_mm3'] + $flat['volume_mm3'], 1.0);
        // the pictures of the tool are drawn in the colours picked
        $this->assertSame($params['part_colors']['details']['hex'], $laid['notes']['paint']['details']);
        $this->assertSame($params['part_colors']['body']['hex'], $laid['notes']['paint']['body']);
        // one piece: no parts for the farm
        $one = $this->postJson('/api/tools/param', ['kind' => 'headband', 'params' => ['decoration' => 'cat', 'mount' => 'fused']])->assertCreated();
        $this->assertSame([], OrderService::designParts(ModelFile::where('uuid', $one->json('file.uuid'))->firstOrFail()));
    }

    public function test_the_page_opens_as_a_devil_and_says_everything_in_three_languages(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = (string) $this->get($prefix.'/tools/headband')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.headband.title', [], $locale)), $html);
            foreach (['head', 'band', 'thickness', 'deco', 'spacing', 'tilt', 'deco_t', 'fit'] as $field) {
                $this->assertStringContainsString('data-param="'.$field.'"', $html, $field);
            }
            foreach ([...self::DECORATIONS, 'none'] as $decoration) {
                $this->assertStringContainsString('data-choice="decoration" value="'.$decoration.'"', $html);
                $this->assertStringContainsString(e(__('param.o.headband.'.$decoration, [], $locale)), $html);
            }
            foreach (array_keys(ParametricGenerator::PRESETS['headband']) as $preset) {
                $this->assertStringContainsString('data-preset="'.$preset.'"', $html);
                $this->assertStringContainsString(e(__('param.preset.'.$preset, [], $locale)), $html);
            }
            $this->assertStringContainsString('data-flag="teeth"', $html);
            $this->assertStringContainsString('data-unit="cm"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
            // the script gets the names of the parts and the texts of the tool's own warnings
            foreach (['param.part.body.headband', 'param.part.details.headband', 'param.warn.headband_stiff', 'param.warn.headband_plate'] as $key) {
                $this->assertNotSame($key, __($key, [], $locale));
                $this->assertStringContainsString($key, $html, $key);
            }
        }
        $this->get('/tools/headband')->assertSee('PETG');      // which plastic to choose is said before anything is ordered
        // the page starts with the devil: a black band, red horns
        $this->assertSame('hb_horns', Route::getRoutes()->getByName('tools.headband')->defaults['preset']);
        $devil = ParametricGenerator::presets('headband')['hb_horns'];
        $this->assertSame('horns', $devil['decoration']);
        $this->assertSame(['body', 'details'], array_keys($devil['part_colors']));
        // every costume names a decoration the tool has
        foreach (ParametricGenerator::PRESETS['headband'] as $name => $preset) {
            $this->assertContains($preset['decoration'], self::DECORATIONS, $name);
        }
    }
}
