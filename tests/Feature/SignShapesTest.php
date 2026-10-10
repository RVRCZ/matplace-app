<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\PreviewMeta;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The sign's plates in drawn shapes, the picture next to the text, the eyelet on any side; the card "nameplate". */
class SignShapesTest extends TestCase
{
    use RefreshDatabase;

    private const PLAIN = ['rounded', 'rect', 'oval'];

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
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => $params + ['line1' => 'Jana', 'text_height' => 14, 'margin' => 4, 'border' => false]])->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
    }

    public function test_every_drawn_shape_grows_round_the_text_until_it_lies_inside(): void
    {
        $shapes = array_values(array_diff(ParametricGenerator::CHOICES['sign']['shape'], self::PLAIN));
        $this->assertGreaterThanOrEqual(15, count($shapes));
        // raised letters add their volume, sunk ones take it away: twice the letters lie between the two, on any plate
        // that holds all of them (letters hanging over an edge could not be cut out of it)
        $letters = $this->meta(['shape' => 'rect', 'style' => 'emboss', 'relief' => 1])['volume_mm3'] - $this->meta(['shape' => 'rect', 'style' => 'engrave', 'relief' => 1])['volume_mm3'];
        $box = $this->meta(['shape' => 'rect']);
        $seen = [];
        foreach ($shapes as $shape) {
            $this->assertFileExists(base_path('engines/shapes/'.$shape.'.svg'));
            $this->assertFileExists(public_path('img/shapes/'.$shape.'.svg'));
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('param.o.sign.'.$shape, __('param.o.sign.'.$shape, [], $locale));
            }
            $raised = $this->meta(['shape' => $shape, 'style' => 'emboss', 'relief' => 1]);
            $sunk = $this->meta(['shape' => $shape, 'style' => 'engrave', 'relief' => 1]);
            $this->assertEqualsWithDelta($letters, $raised['volume_mm3'] - $sunk['volume_mm3'], 0.005 * $letters, $shape);
            // the plate holds the box of the text, so it is at least as big as the plain plate; and it is not a giant
            $this->assertGreaterThanOrEqual($box['bbox']['x'] - 0.5, $raised['bbox']['x'], $shape);
            $this->assertGreaterThanOrEqual($box['bbox']['y'] - 0.5, $raised['bbox']['y'], $shape);
            $this->assertLessThan(3 * $box['bbox']['x'], $raised['bbox']['x'], $shape);
            $seen[$shape] = round($raised['bbox']['x'], 1).'×'.round($raised['bbox']['y'], 1);
        }
        $this->assertCount(count($shapes), array_unique($seen));
        // a raised rim follows the shape too
        $rim = $this->meta(['shape' => 'cloud', 'border' => true]);
        $this->assertGreaterThan($this->meta(['shape' => 'cloud'])['volume_mm3'], $rim['volume_mm3']);
        foreach (self::PLAIN as $shape) {
            $this->assertFileExists(public_path('img/shapes/'.$shape.'.svg'));
        }
    }

    public function test_a_picture_stands_next_to_the_text_and_is_printed_with_it(): void
    {
        $plain = $this->meta(['two_color' => true]);
        $left = $this->meta(['two_color' => true, 'artwork' => 'lib:hearts-stars/star']);
        $above = $this->meta(['two_color' => true, 'artwork' => 'lib:hearts-stars/star', 'motif_at' => 'above']);
        // beside the text the plate is wider and no taller; above it, taller and no wider
        $this->assertGreaterThan($plain['bbox']['x'] + 10, $left['bbox']['x']);
        $this->assertEqualsWithDelta($plain['bbox']['y'], $left['bbox']['y'], 3);
        $this->assertGreaterThan($plain['bbox']['y'] + 10, $above['bbox']['y']);
        $this->assertEqualsWithDelta($plain['bbox']['x'], $above['bbox']['x'], 0.5);
        $this->assertSame($this->meta(['artwork' => 'lib:hearts-stars/star', 'motif_at' => 'right'])['bbox'], $this->meta(['artwork' => 'lib:hearts-stars/star'])['bbox']);
        // a name without a plate takes the picture as one more letter and stays one piece
        $name = $this->meta(['style' => 'name', 'typeface' => 'script', 'artwork' => 'lib:hearts-stars/star', 'motif_at' => 'above']);
        $alone = $this->meta(['style' => 'name', 'typeface' => 'script']);
        $this->assertGreaterThan($alone['bbox']['y'] + 10, $name['bbox']['y']);
        $this->assertGreaterThanOrEqual(1, $name['notes']['links']);
        // a design is stored with its picture and can be opened again
        $created = $this->postJson('/api/tools/param', ['kind' => 'sign', 'params' => ['line1' => 'Jana', 'shape' => 'heart', 'artwork' => 'lib:hearts-stars/star']])->assertCreated();
        $this->assertNotNull(ParametricGenerator::artworkPath((string) $created->json('file.tool.params.artwork')));      // its own copy of the picture
        $this->assertSame('heart', $created->json('file.tool.params.shape'));
        // the text is still what a sign is about
        $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['line1' => '', 'artwork' => 'lib:hearts-stars/star']])->assertStatus(422);
    }

    public function test_the_eyelet_goes_on_the_side_asked_for(): void
    {
        $none = $this->meta([]);
        $left = $this->meta(['keyring' => true]);
        $right = $this->meta(['keyring' => true, 'ring_at' => 'right']);
        $top = $this->meta(['keyring' => true, 'ring_at' => 'top']);
        // the tab sticks out on its side only; left is what the sign always did when nothing was asked
        $this->assertGreaterThan($none['bbox']['x'] + 5, $left['bbox']['x']);
        $this->assertSame($left['bbox'], $right['bbox']);
        $this->assertEqualsWithDelta($left['volume_mm3'], $right['volume_mm3'], 1);
        $this->assertEqualsWithDelta($none['bbox']['x'], $top['bbox']['x'], 0.01);
        $this->assertGreaterThan($none['bbox']['y'] + 5, $top['bbox']['y']);
        $this->assertEqualsWithDelta($none['bbox']['y'], $left['bbox']['y'], 0.01);
        // on a heart the eyelet of the top sits in the notch: hardly any taller, but more plastic and a hole through it
        $heart = $this->meta(['shape' => 'heart']);
        $hung = $this->meta(['shape' => 'heart', 'keyring' => true, 'ring_at' => 'top']);
        $this->assertLessThan($heart['bbox']['y'] + 8, $hung['bbox']['y']);
        $this->assertNotEqualsWithDelta($heart['volume_mm3'], $hung['volume_mm3'], 20);
        // a name without a plate has its eyelet where its letters end on that side
        $name = ['style' => 'name', 'typeface' => 'script', 'keyring' => true];
        $l = $this->meta($name);
        $t = $this->meta($name + ['ring_at' => 'top']);
        $this->assertGreaterThan($t['bbox']['x'] + 3, $l['bbox']['x']);
        $this->assertGreaterThan($l['bbox']['y'] + 3, $t['bbox']['y']);
    }

    public function test_the_quick_form_of_the_nameplate_is_the_sign_with_a_shape_and_a_picture(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            // the card itself opens the composer of layers (ComposeToolTest); the form it used to be stays under ?form=1
            $html = $this->get($prefix.'/tools/nameplate?form=1')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.nameplate.title', [], $locale)), $html);
            $this->assertStringContainsString(e(__('param.nameplate.lead_form', [], $locale)), $html);
            $this->assertStringContainsString(e(ToolSeo::texts('nameplate', $locale)['h1']), $html);
            $this->assertStringContainsString('kind: "sign"', $html);
            $this->assertStringContainsString('preset: "shaped"', $html);
            $this->assertStringContainsString('sample: "lib:hearts-stars\/star"', $html);
            // a tile for every shape, a place for the picture and for the side of the eyelet
            $this->assertSame(count(ParametricGenerator::CHOICES['sign']['shape']), preg_match_all('/data-choice="shape"/', $html));
            $this->assertStringContainsString('img/shapes/cloud.svg', $html);
            $this->assertStringContainsString('data-choice="motif_at"', $html);
            $this->assertStringContainsString('data-when="keyring=on"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
        // the plain sign opens as it always did
        $sign = $this->get('/tools/sign')->assertOk()->getContent();
        $this->assertStringContainsString('preset: null', $sign);
        $this->assertStringContainsString('sample: null', $sign);
        $this->assertSame(1, preg_match('/data-choice="shape" value="([a-z]+)"[^>]* checked/', $sign, $m));
        $this->assertSame('rounded', $m[1]);
    }
}
