<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** Four places where a tool said one thing and did another: the sign, the illuminated sign, the phone stand, the cap. */
class ToolFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    private function notes(string $kind, array $params): array
    {
        $response = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params])->assertOk();

        return (array) PreviewMeta::whole($response->headers->get('X-Model-Meta'))['notes'];
    }

    public function test_the_second_line_of_a_sign_is_smaller_as_its_label_says(): void
    {
        $this->assertStringContainsString('menší', __('param.t.sign.line2'));
        $sign = ['style' => 'emboss', 'shape' => 'rect', 'text_height' => 12, 'margin' => 5];
        // the plate is as wide as its widest line plus the margins: ten letters alone, and the same ten under one letter
        $alone = $this->notes('sign', $sign + ['line1' => 'HHHHHHHHHH'])['outer'];
        $under = $this->notes('sign', $sign + ['line1' => 'H', 'line2' => 'HHHHHHHHHH'])['outer'];
        $this->assertEqualsWithDelta(0.7, ($under[0] - 10) / ($alone[0] - 10), 0.01, 'the second line is 70 % of the first');
        // … and the plate is lower than it would be with two lines of the same height
        $one = $alone[1] - 10;                           // the height of one line of capitals
        $this->assertGreaterThan($one * 1.7, $under[1] - 10);
        $this->assertLessThan($one * 2.3, $under[1] - 10);
        // two lines as wide as each other: the plate is as wide as the first, the second does not reach its edges
        $this->assertSame($alone[0], $this->notes('sign', $sign + ['line1' => 'HHHHHHHHHH', 'line2' => 'HHHHHHHHHH'])['outer'][0]);
    }

    public function test_the_phone_stand_says_when_it_cannot_stand_at_the_angle_that_was_asked_for(): void
    {
        $stand = fn (string $style, float $angle) => $this->notes('phone_stand', ['style' => $style, 'width' => 70, 'device' => 12, 'angle' => $angle]);

        foreach ([['plate', 40, 55, 'stand_angle_55'], ['wave', 35, 55, 'stand_angle_55'], ['desk', 40, 45, 'stand_angle_45'], ['wedge', 80, 70, 'stand_angle_70']] as [$style, $asked, $used, $warning]) {
            $notes = $stand($style, $asked);
            $this->assertSame([(float) $used, [$warning]], [(float) $notes['angle'], $notes['warnings']], $style);
            // the same model as when that angle is asked for directly
            $this->assertSame($notes['outer'], $stand($style, $used)['outer'], $style);
        }
        // inside the range nothing is changed and nothing is said
        foreach ([['plate', 65], ['wave', 60], ['desk', 50], ['wedge', 60]] as [$style, $asked]) {
            $notes = $stand($style, $asked);
            $this->assertSame([(float) $asked, []], [(float) $notes['angle'], $notes['warnings'] ?? []], $style);
        }
        // the page knows the sentences in every language
        foreach (['cs' => 'stojí pevně až od 55°', 'en' => 'stable only from 55°', 'es' => 'estable a partir de 55°'] as $locale => $words) {
            $this->assertStringContainsString($words, __('param.warn.stand_angle_55', [], $locale));
            $this->get($this->localized('/tools/phone-stand', $locale))->assertOk()->assertSee('param.warn.stand_angle_55')->assertSee('param.warn.stand_angle_70');
        }
        // the new group file does not hide the dictionary's own `param.*` texts
        $this->assertSame('První řádek', __('param.t.sign.line1', [], 'cs'));
    }

    public function test_the_illuminated_sign_has_its_cable_hole_where_the_text_says(): void
    {
        foreach (['cs' => 'tělo s otvorem na kabel', 'en' => 'body with a cable hole', 'es' => 'cuerpo con orificio para el cable'] as $locale => $words) {
            $this->assertStringContainsString($words, __('tools.lightbox.hint', [], $locale));
            $this->get($this->localized('/tools', $locale))->assertOk()->assertSee($words);
        }
        // the hole is in the body: a thicker cable takes more out of the body and nothing out of the back cover
        $volume = function (string $part, float $cable): float {
            $response = $this->postJson('/api/tools/param/preview', ['kind' => 'lightbox', 'params' => ['line1' => 'OPEN', 'cable' => $cable], 'part' => $part])->assertOk();

            return (float) PreviewMeta::whole($response->headers->get('X-Model-Meta'))['volume_mm3'];
        };
        $this->assertLessThan($volume('body', 4), $volume('body', 8));
        $this->assertSame($volume('back', 4), $volume('back', 8));
    }

    public function test_the_qr_sign_really_gets_its_hanging_hole(): void
    {
        $qr = ['url' => 'https://matplace.com', 'size' => 50, 'plate' => 2.4];
        $meta = function (array $params): array {
            $response = $this->postJson('/api/tools/param/preview', ['kind' => 'qr', 'params' => $params])->assertOk();

            return (array) PreviewMeta::whole($response->headers->get('X-Model-Meta'));
        };
        $plain = $meta($qr);
        $hole = $meta($qr + ['hole' => true]);
        // a strip of 9 mm above the code carries the hole, so the quiet zone of the code stays blank
        $this->assertEqualsWithDelta(9.0, $hole['notes']['outer'][1] - $plain['notes']['outer'][1], 0.01);
        $this->assertSame($plain['notes']['outer'][0], $hole['notes']['outer'][0]);
        // … and the hole is really cut: the strip minus a circle of 4.4 mm
        $this->assertEqualsWithDelta((9 * 50 - M_PI * 2.2 ** 2) * 2.4, $hole['volume_mm3'] - $plain['volume_mm3'], 2.0);
        // in a stand the sign has no hole, whatever the box says
        $stand = $meta($qr + ['stand' => true]);
        $this->assertSame($stand['notes']['outer'], $meta($qr + ['stand' => true, 'hole' => true])['notes']['outer']);
        $this->assertEqualsWithDelta($stand['volume_mm3'], $meta($qr + ['stand' => true, 'hole' => true])['volume_mm3'], 0.01);
    }

    public function test_a_threaded_cap_may_be_round_square_or_hexagonal_and_no_text_says_otherwise(): void
    {
        foreach (['round', 'rect', 'hex'] as $shape) {
            $notes = $this->notes('cap', ['style' => 'thread', 'shape' => $shape, 'size_a' => 28, 'size_b' => 40, 'height' => 12, 'pitch' => 3]);
            $this->assertSame([$shape, [28.0]], [$notes['shape'], array_map('floatval', $notes['fits'])]);
            $this->assertArrayHasKey('thread', $notes);
        }
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertFalse(Lang::has('param.error.cap_thread_round', $locale, false), 'the message about a round cap only was never true');
        }
    }
}
