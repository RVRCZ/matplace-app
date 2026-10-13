<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The phone stand after round 2 (docs/AA.md): the six stands of round 1 as they were, a shape cut out of the rest of
 * the four that have one, and the four new stands (corrugated sheet, flat plate with a leg, Z ribbon, silhouette).
 */
class PhoneStandTest extends TestCase
{
    use RefreshDatabase;

    /** what the six stands of round 1 were with their default numbers before round 2 (tinker over build(), 13 Oct 2026): nothing may move them */
    private const ROUND_ONE = [
        'plate' => ['print' => [[70, 62.6, 97.73], 70346.1, 496], 'use' => [[70, 62.6, 97.73], 70346.1, 496]],
        'wave' => ['print' => [[68.19, 95.59, 70], 72782.4, 1164], 'use' => [[68.19, 70, 95.59], 72782.4, 1164]],
        'desk' => ['print' => [[81.44, 105.97, 70], 122460.5, 588], 'use' => [[81.44, 70, 105.97], 122460.5, 588]],
        'wedge' => ['print' => [[60, 28.13, 70], 87762.4, 200], 'use' => [[60, 70, 28.13], 87762.4, 200]],
        'wall' => ['print' => [[82, 70, 23.5], 49646.5, 404], 'use' => [[82, 23.5, 70], 49646.5, 404]],
        'car' => ['print' => [[45, 55, 77], 31567.3, 636], 'use' => [[45, 77, 55], 31567.3, 636]],
    ];

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
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => $params, 'part' => $part, 'view' => $view])->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
    }

    public function test_the_six_stands_of_round_one_are_what_they_were(): void
    {
        foreach (self::ROUND_ONE as $style => $views) {
            foreach ($views as $view => [$bbox, $volume, $triangles]) {
                $m = $this->meta(['style' => $style], 'all', $view);
                $this->assertEqualsWithDelta($bbox, [$m['bbox']['x'], $m['bbox']['y'], $m['bbox']['z']], 0.01, "$style $view bbox");
                $this->assertEqualsWithDelta($volume, $m['volume_mm3'], 0.5, "$style $view volume");
                $this->assertSame($triangles, $m['triangles'], "$style $view triangles");
            }
        }
        // the A-frame's window is a cut-out now: still its default, and still a switch for old designs
        $this->assertSame('window', ParametricGenerator::clean('phone_stand', ['style' => 'desk'])['cutout']);
        $this->assertSame('none', ParametricGenerator::clean('phone_stand', ['style' => 'desk', 'window' => false])['cutout']);
        $this->assertSame('none', ParametricGenerator::clean('phone_stand', ['style' => 'wave'])['cutout']);
        $noWindow = $this->meta(['style' => 'desk', 'cutout' => 'none']);
        $this->assertGreaterThan(self::ROUND_ONE['desk']['print'][1] + 50000, $noWindow['volume_mm3'], 'without the window the A-frame is a solid wedge');
    }

    public function test_a_shape_can_be_cut_out_of_every_rest(): void
    {
        foreach (['plate', 'wave', 'desk', 'wall'] as $style) {
            $plain = $this->meta(['style' => $style]);
            $cat = $this->meta(['style' => $style, 'cutout' => 'shape', 'artwork' => 'lib:animals/cat']);
            $this->assertSame($plain['bbox'], $cat['bbox'], "$style: a cut-out changes nothing outside");
            $this->assertLessThan($plain['volume_mm3'] - 500, $cat['volume_mm3'], "$style: the cat is cut out");
            $this->assertNotEmpty($cat['notes']['cutout'], "$style tells the cut-out's size");
            $this->assertLessThanOrEqual(0.75 * 70, $cat['notes']['cutout'][0], "$style: the cut-out keeps a frame");
        }
        // a smaller share is a smaller hole; the limits are the form's
        $small = $this->meta(['style' => 'desk', 'cutout' => 'shape', 'artwork' => 'lib:animals/cat', 'cutout_size' => 30]);
        $big = $this->meta(['style' => 'desk', 'cutout' => 'shape', 'artwork' => 'lib:animals/cat', 'cutout_size' => 75]);
        $this->assertGreaterThan($small['notes']['cutout'][0], $big['notes']['cutout'][0]);
        $this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => ['style' => 'desk', 'cutout' => 'shape', 'cutout_size' => 20]])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => ['style' => 'desk', 'cutout' => 'shape', 'cutout_size' => 80]])->assertStatus(422);
        // the wall pocket with screws: the shape stays between the holes and says it had to be small
        $wall = $this->meta(['style' => 'wall', 'cutout' => 'shape', 'artwork' => 'lib:animals/cat', 'screws' => true]);
        $this->assertContains('stand_cutout_small', $wall['notes']['warnings']);
        // without a picture the shape is the library's cat
        $this->assertSame('lib:animals/cat', ParametricGenerator::clean('phone_stand', ['style' => 'desk', 'cutout' => 'shape'])['artwork']);
        // an uploaded picture: a blob with a hole, cut as its outer outline only (the hole would fall out)
        $png = imagecreatetruecolor(240, 240);
        imagefill($png, 0, 0, imagecolorallocate($png, 255, 255, 255));
        imagefilledellipse($png, 120, 120, 200, 160, imagecolorallocate($png, 0, 0, 0));
        imagefilledellipse($png, 120, 120, 60, 50, imagecolorallocate($png, 255, 255, 255));
        ob_start();
        imagepng($png);
        $bytes = ob_get_clean();
        $ref = $this->post('/api/tools/artwork', ['file' => UploadedFile::fake()->createWithContent('blob.png', $bytes)])->assertCreated()->json('artwork');
        $blob = $this->meta(['style' => 'desk', 'cutout' => 'shape', 'artwork' => $ref]);
        $this->assertLessThan($this->meta(['style' => 'desk'])['volume_mm3'] - 500, $blob['volume_mm3']);
        $this->assertEqualsWithDelta(0.8, $blob['notes']['cutout'][1] / $blob['notes']['cutout'][0], 0.05, 'the ellipse, not its hole');
    }

    public function test_the_four_new_stands_build_with_their_parts(): void
    {
        $ribbed = $this->meta(['style' => 'ribbed']);
        $this->assertEqualsWithDelta(70, $ribbed['bbox']['z'], 0.01, 'lying on its side, the width along z');
        $this->assertSame(6.0, (float) $ribbed['notes']['rib_pitch']);
        $this->assertGreaterThan(2000, $ribbed['triangles'], 'the ribs are real geometry');
        $wide = $this->meta(['style' => 'ribbed', 'rib_pitch' => 10]);
        $this->assertLessThan($ribbed['notes']['ribs'], $wide['notes']['ribs'], 'a wider pitch, fewer ribs');
        $this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => ['style' => 'ribbed', 'rib_pitch' => 3]])->assertStatus(422);

        // the flat plate and its leg: two parts, flat on the bed, standing together in use
        $plate = $this->meta(['style' => 'flatpack'], 'body');
        $leg = $this->meta(['style' => 'flatpack'], 'stand');
        $this->assertEqualsWithDelta([70, 100], [$plate['bbox']['x'], $plate['bbox']['y']], 0.01);
        $this->assertEqualsWithDelta(5 + 12 + 4, $plate['bbox']['z'], 0.01, 'the shelf for the phone stands on the plate');
        $this->assertEqualsWithDelta(5, $leg['bbox']['z'], 0.01, 'the leg lies flat');
        $use = $this->meta(['style' => 'flatpack'], 'all', 'use');
        $this->assertGreaterThan(80, $use['bbox']['z'], 'the plate leans up');
        $this->assertEqualsWithDelta($plate['volume_mm3'] + $leg['volume_mm3'], $use['volume_mm3'], 1.0, 'both parts, nothing fused');
        $this->assertSame(['body', 'stand'], ParametricGenerator::partsOf('phone_stand', ['style' => 'flatpack']));
        $this->assertSame([5.5, 12.5], $use['notes']['leg_slot'], 'the slot takes the leg with a quarter of a millimetre of play');
        $hearts = $this->meta(['style' => 'flatpack', 'pattern' => 'hearts'], 'body');
        $this->assertNotEquals($plate['volume_mm3'], $hearts['volume_mm3'], 'another pattern, other holes');

        // the Z ribbon: the band and its stripe, the stripe lying beside it on the bed and in its groove in use
        $body = $this->meta(['style' => 'ribbon'], 'body');
        $stripe = $this->meta(['style' => 'ribbon'], 'stripe');
        $this->assertEqualsWithDelta(70, $body['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta(1.5, $stripe['bbox']['z'], 0.01, 'the stripe is 1.5 mm, for a groove of 1.6');
        $this->assertSame(['body', 'stripe'], ParametricGenerator::partsOf('phone_stand', ['style' => 'ribbon']));
        $all = $this->meta(['style' => 'ribbon']);
        $this->assertGreaterThan($body['bbox']['x'] + 8, $all['bbox']['x'], 'on the bed the stripe lies beside the band');
        $thick = $this->meta(['style' => 'ribbon', 'band' => 14], 'body');
        $this->assertGreaterThan($body['volume_mm3'], $thick['volume_mm3']);
        $this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => ['style' => 'ribbon', 'band' => 6]])->assertStatus(422);

        // the silhouette: the library's cat by default, a dog or a heart when asked, as tall as the rest, as wide as `across`
        $cat = $this->meta(['style' => 'silhouette']);
        $this->assertEqualsWithDelta(100, $cat['bbox']['y'], 0.05, 'as tall as the rest');
        $this->assertEqualsWithDelta(25, $cat['bbox']['z'], 0.01, 'across the phone');
        $this->assertGreaterThanOrEqual($cat['notes']['silhouette'][0] - 0.1, $cat['bbox']['x'], 'the pad for the phone stays within the cat or in front of it');
        $this->assertSame(5.0, (float) $cat['notes']['ring'], 'the ring is as thick as the material');
        $dog = $this->meta(['style' => 'silhouette', 'artwork' => 'lib:animals/dog', 'across' => 40, 'back' => 80]);
        $this->assertEqualsWithDelta([80, 40], [$dog['bbox']['y'], $dog['bbox']['z']], 0.05);
        $heart = $this->meta(['style' => 'silhouette', 'artwork' => 'lib:hearts-stars/heart'], 'all', 'use');
        $this->assertEqualsWithDelta(100, $heart['bbox']['z'], 0.05, 'standing: the height is up');
        $low = $this->meta(['style' => 'silhouette', 'back' => 60]);
        $this->assertNotContains('stand_silhouette_low', $low['notes']['warnings'] ?? []);
        $this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => ['style' => 'silhouette', 'across' => 10]])->assertStatus(422);
    }

    public function test_the_page_offers_the_new_stands_and_the_cut_out(): void
    {
        $html = $this->get('/tools/phone-stand')->assertOk()->getContent();
        foreach (['ribbed', 'flatpack', 'ribbon', 'silhouette'] as $style) {
            $this->assertStringContainsString('data-choice="style" value="'.$style.'"', $html);
        }
        $this->assertStringContainsString('data-choice="cutout" value="shape"', $html);
        $this->assertStringContainsString('data-choice="pattern" value="honeycomb"', $html);
        $this->assertStringContainsString('data-when="style=ribbon"', $html);
        $this->assertStringContainsString('data-when="style=silhouette"', $html);
        $this->assertStringContainsString('id="param-artwork-open"', $html, 'the library and the upload for the shape');
        $this->assertStringNotContainsString('data-flag="window"', $html, 'the window is a cut-out now');
        $this->assertDoesNotMatchRegularExpression('/>\s*param\.[a-z_.]+\s*</', $html);
        foreach (['en', 'es'] as $lang) {
            $this->get('/'.$lang.'/tools/phone-stand')->assertOk()->assertDontSee('param.o.phone_stand.ribbed');
        }
    }
}
