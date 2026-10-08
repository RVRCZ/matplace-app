<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A text that stands by itself (the sign's style `stand`) and its card /tools/text. */
class StandingTextTest extends TestCase
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
    private function meta(array $params, string $view = 'print'): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'view' => $view, 'params' => $params + ['style' => 'stand', 'line1' => 'HOME', 'typeface' => 'archivo', 'text_height' => 30, 'thickness' => 12]])->assertOk();

        return json_decode((string) $r->headers->get('X-Model-Meta'), true);
    }

    public function test_the_text_lies_for_printing_and_stands_in_use(): void
    {
        $lying = $this->meta([]);
        $standing = $this->meta([], 'use');
        // printed on its back: as thick as asked; in use the same solid stands on its foot
        $this->assertEqualsWithDelta(12, $lying['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta(12, $standing['bbox']['y'], 0.01);
        $this->assertEqualsWithDelta($lying['bbox']['y'], $standing['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta($lying['volume_mm3'], $standing['volume_mm3'], 1);
        // capitals of 30 mm and a foot under them: a little over 30 mm tall, one piece, one colour, nothing to hang it by
        $this->assertGreaterThan(32, $lying['bbox']['y']);
        $this->assertLessThan(36, $lying['bbox']['y']);
        $this->assertSame([], $lying['notes']['warnings']);
        $this->assertFalse($lying['notes']['two_color']);
        $this->assertArrayNotHasKey('color_change_mm', $lying['notes']);
        // twice as deep is twice the plastic: one outline pulled up
        $this->assertEqualsWithDelta(2 * $lying['volume_mm3'], $this->meta(['thickness' => 24])['volume_mm3'], 0.01 * $lying['volume_mm3']);
        // the options of a plate mean nothing here
        $this->assertSame($lying['bbox'], $this->meta(['keyring' => true, 'border' => true, 'two_color' => true, 'shape' => 'heart'])['bbox']);
        // too thin for its height: it is said
        $this->assertContains('stand_tippy', $this->meta(['thickness' => 5])['notes']['warnings']);
    }

    public function test_lines_one_above_another_are_one_piece(): void
    {
        $one = $this->meta(['line1' => 'KAVÁRNA', 'typeface' => 'bebas', 'thickness' => 20]);
        $two = $this->meta(['line1' => 'KAVÁRNA', 'line2' => 'u Jany', 'typeface' => 'bebas', 'thickness' => 20]);
        $three = $this->meta(['line1' => 'KAVÁRNA', 'line2' => 'u Jany', 'line3' => 'od 8 do 18', 'typeface' => 'bebas', 'thickness' => 20]);
        // every line adds its height and the rail between; the accent of the Á is tied without a warning
        $this->assertGreaterThan($one['bbox']['y'] + 15, $two['bbox']['y']);
        $this->assertGreaterThan($two['bbox']['y'] + 15, $three['bbox']['y']);
        $this->assertGreaterThanOrEqual(1, $one['notes']['links']);
        $this->assertNotContains('letters_tied', $one['notes']['warnings']);
        // the third line is a line of every sign, not only of the standing one
        $plate = fn (array $p) => json_decode((string) $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => $p])->assertOk()->headers->get('X-Model-Meta'), true);
        $this->assertGreaterThan($plate(['line1' => 'Jana', 'line2' => 'Nová'])['bbox']['y'] + 5, $plate(['line1' => 'Jana', 'line2' => 'Nová', 'line3' => 'byt 12'])['bbox']['y']);
    }

    public function test_a_picture_stands_on_the_same_foot(): void
    {
        $text = $this->meta(['line1' => 'Ela', 'typeface' => 'script', 'text_height' => 40, 'thickness' => 15]);
        $heart = $this->meta(['line1' => 'Ela', 'typeface' => 'script', 'text_height' => 40, 'thickness' => 15, 'artwork' => 'lib:hearts-stars/heart', 'motif_at' => 'right']);
        // wider by the heart, hardly taller, and still one piece that needed no tie worth a word
        $this->assertGreaterThan($text['bbox']['x'] + 30, $heart['bbox']['x']);
        $this->assertLessThan($text['bbox']['y'] + 6, $heart['bbox']['y']);
        $this->assertNotContains('letters_tied', $heart['notes']['warnings']);
        $this->assertGreaterThan($text['volume_mm3'] * 1.3, $heart['volume_mm3']);
        // above the text it hangs in the air: tied, and said
        $above = $this->meta(['line1' => 'Ela', 'typeface' => 'script', 'text_height' => 40, 'thickness' => 15, 'artwork' => 'lib:hearts-stars/heart', 'motif_at' => 'above']);
        $this->assertContains('letters_tied', $above['notes']['warnings']);
    }

    public function test_the_text_card_opens_the_sign_standing(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/text')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.text.title', [], $locale)), $html);
            $this->assertStringContainsString(e(ToolSeo::texts('text', $locale)['h1']), $html);
            $this->assertStringContainsString('kind: "sign"', $html);
            $this->assertStringContainsString('preset: "stand"', $html);
            $this->assertStringContainsString('data-text="line3"', $html);
            $this->assertStringContainsString('value="stand"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
        // a design made standing is stored as a sign and comes without supports
        $created = $this->postJson('/api/tools/param', ['kind' => 'sign', 'params' => ['style' => 'stand', 'line1' => 'HOME', 'typeface' => 'archivo', 'text_height' => 30, 'thickness' => 12, 'two_color' => true]])->assertCreated();
        $this->assertSame('sign', $created->json('file.kind'));
        $this->assertFalse($created->json('file.hints.supports'));
        $this->assertSame('stand', $created->json('file.tool.params.style'));
    }
}
