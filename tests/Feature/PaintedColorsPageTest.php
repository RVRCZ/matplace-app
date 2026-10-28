<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Colours painted in a slicer, the calculator's and the colours tool's side (docs/prompts/import-3mf-barvy.md,
 * docs/AN.md §19): the places the scripts fill, the texts in three languages, the colours tool saying which slicers'
 * painted 3MF it reads. The colour runs themselves (file.groups, file.colors, calculation.colors) come from session 2's
 * analysis on upload; resources/js/calc/painted.ts paints by them.
 */
final class PaintedColorsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_calculation_has_the_line_of_painted_colours_and_the_one_colour_button_with_the_farm(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true]);
        $html = (string) $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->getContent();
        $this->assertStringContainsString('id="calc-colors" class="mt-3 hidden', $html, 'hidden until the file has painted colours');
        $this->assertStringContainsString('id="calc-colors-line"', $html);
        $this->assertStringContainsString('id="cta-farm-one" data-colors="one" class="btn-secondary mt-2 hidden', $html);
        $this->assertLessThan(strpos($html, 'id="cta-farm-one"'), strpos($html, 'id="cta-farm"'), 'the one-colour button under the farm\'s');
        $this->assertGreaterThan(strpos($html, 'id="price-card"'), strpos($html, 'id="calc-colors"'), 'in the price card');

        $i18n = $this->calcConfig('/', ['Accept-Language' => 'cs'])['i18n'];
        // price_same: one line when the price in colour and in one colour come out the same (the turtle at the price list's minimum)
        foreach (['calc.colors.count', 'calc.colors.merged', 'calc.colors.price_one', 'calc.colors.price_multi', 'calc.colors.price_same', 'calc.colors.order'] as $key) {
            $this->assertArrayHasKey($key, $i18n, $key);
        }
        $this->assertStringContainsString('[2,4] Soubor má :n namalované barvy', $i18n['calc.colors.count'], 'the forms travel for the script\'s plural()');
        foreach (['cs', 'en', 'es'] as $locale) {
            foreach (['calc.colors.count', 'calc.colors.merged', 'calc.colors.price_same', 'calc.colors.order', 'calc.colors.order_one', 'edit.colors.painted'] as $key) {
                $this->assertNotSame($key, trans($key, [], $locale), "{$key} ({$locale})");
            }
        }

        config(['farm.public' => false]);
        $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->assertDontSee('id="cta-farm-one"', false);
    }

    public function test_the_colours_tool_says_which_slicers_painted_3mf_it_reads(): void
    {
        $html = (string) $this->get('/tools/colors', ['Accept-Language' => 'cs'])->assertOk()->getContent();
        $this->assertStringContainsString('data-painted-intro', $html);
        foreach (['Bambu Studio', 'OrcaSlicer', 'Anycubic Slicer Next', 'PrusaSlicer', 'namalovali'] as $word) {
            $this->assertStringContainsString($word, $html, $word);
        }
        $this->assertLessThan(strpos($html, 'id="edit-drop"'), strpos($html, 'data-painted-intro'), 'above the upload');
        $this->get('/tools/split', ['Accept-Language' => 'cs'])->assertOk()->assertDontSee('data-painted-intro', false);
        $this->get('/en/tools/colors')->assertOk()->assertSee('painted colours');
    }
}
