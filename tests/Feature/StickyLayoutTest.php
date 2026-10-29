<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The layouts without jumps (Roman, 28 Oct 2026, docs/prompts/rozlozeni-bez-skoku.md, docs/AN.md §18): on the tool
 * page the options scroll inside their column and the preview with the way on stays; on the calculation the price card
 * with "print it with us" stays at the top of its column, which scrolls inside a faint frame (docs/AN.md §20); on a phone the main action sits in a bar fixed to the bottom
 * that mirrors the page's own button. The scrolling itself is checked in headless Chrome (scripts/review_round2.mjs
 * flows jumps-tool and jumps-calc), here the markup the scripts hang on.
 */
final class StickyLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tool_page_scrolls_its_options_inside_and_carries_the_action_bar_for_its_button(): void
    {
        $html = (string) $this->get('/tools/box')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<div id="tool-page"[^>]*class="([^"]*)"/', $html, $m));
        $this->assertStringContainsString('stick-fit', $m[1], 'the grid fills the window under the header');
        $this->assertSame(1, preg_match('/<div id="tool-panel"[^>]*class="([^"]*)"/', $html, $m));
        $this->assertStringContainsString('stick-scroll', $m[1], 'the options scroll inside their column');
        $this->assertStringContainsString('lg:rounded-2xl lg:border lg:border-slate-200', $m[1], 'in a faint frame, so it shows that it scrolls (docs/AN.md §20)');
        $this->assertSame(1, preg_match('/<div id="tool-stage"[^>]*class="([^"]*)"/', $html, $m));
        $this->assertStringNotContainsString('lg:sticky', $m[1], 'the stage fills the grid\'s height instead of sticking past it');
        $this->assertStringContainsString('lg:h-full', $m[1]);
        // the step bar stays at the top of the scrolling panel; its links lead inside the panel, the script takes the clicks
        $this->assertStringContainsString('<nav id="tool-nav" class="sticky top-0', $html);
        $this->assertMatchesRegularExpression('/data-action-bar data-for="tool-go"/', $html);
        $this->assertSame(1, preg_match('/<div[^>]*data-action-bar[^>]*>/', $html, $bar));
        foreach (['class="action-bar lg:hidden"', 'data-for="tool-go"', 'data-sub-from="tool-status"'] as $piece) {
            $this->assertStringContainsString($piece, $bar[0]);
        }
        $this->assertStringContainsString('data-bar-button disabled', $html);
        $this->assertStringContainsString('id="tool-go-card"', $html, 'the card with the buttons stays under the preview');
        $this->assertStringContainsString('<span class="max-lg:hidden lg:contents"><button type="button" id="tool-go"', $html, 'on a phone the button lives in the bar alone');
    }

    public function test_the_calculation_keeps_the_price_card_with_the_way_on_at_the_top_and_the_bar_only_with_the_farm(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true, 'features.marketplace' => false]);   // the farm alone, as on the site
        $html = (string) $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<div id="price-card" class="([^"]*)"/', $html, $m));
        $this->assertStringContainsString('lg:sticky lg:top-0', $m[1], 'at the top of the scrolling column');
        $this->assertStringContainsString('max-lg:order-first', $m[1], 'on a phone the price still comes first');
        // from lg the grid fills the window, the viewer stands and the column with the price and the settings scrolls in a faint frame (docs/AN.md §20)
        $this->assertSame(1, preg_match('/<div id="calc-grid" class="([^"]*)"/', $html, $m));
        $this->assertStringContainsString('stick-fit', $m[1]);
        $this->assertSame(1, preg_match('/<div id="calc-panel" class="([^"]*)"/', $html, $m));
        foreach (['stick-scroll', 'max-lg:contents', 'lg:rounded-2xl', 'lg:border', 'lg:border-slate-200'] as $piece) {
            $this->assertStringContainsString($piece, $m[1], $piece);
        }
        $this->assertLessThan(strpos($html, 'id="price-card"'), strpos($html, 'id="calc-panel"'), 'the price card is the first thing in the column');
        // the way on moved into the price card: before the size and the materials, which scroll under it
        $card = strpos($html, 'id="price-card"');
        $this->assertLessThan(strpos($html, 'id="size-x"'), strpos($html, 'id="cta-farm"'));
        $this->assertGreaterThan($card, strpos($html, 'id="cta-farm"'));
        $this->assertLessThan(strpos($html, 'id="materials"'), strpos($html, 'id="size-x"'), 'the size block still comes before the material');
        $this->assertSame(1, preg_match('/<div id="calc-stage" class="[^"]*lg:h-full[^"]*">\s*<div class="relative lg:min-h-0 lg:flex-1">\s*<canvas id="viewer" class="[^"]*lg:h-full/s', $html), 'the viewer fills its side of the grid and stays on the screen');
        // the bar for a phone mirrors the farm's button and the orientation price; hidden until a result shows
        $this->assertSame(1, preg_match('/<div[^>]*data-action-bar[^>]*>/', $html, $bar));
        foreach (['id="calc-bar"', 'lg:hidden', 'hidden="hidden"', 'data-for="cta-farm"', 'data-price-from="price-orient-amount"', 'data-sub-from="price-main"'] as $piece) {
            $this->assertStringContainsString($piece, $bar[0], $piece);
        }
        $this->assertStringContainsString('id="price-orient-amount"', $html);
        // with the marketplace the bar mirrors the headline price with its currency
        config(['features.marketplace' => true]);
        $this->assertSame(1, preg_match('/<div[^>]*data-action-bar[^>]*>/', (string) $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->getContent(), $bar));
        $this->assertStringContainsString('data-price-from="price-main" data-price-suffix=" Kč"', $bar[0]);
        config(['features.marketplace' => false]);

        // no farm: no bar at all
        config(['farm.public' => false]);
        $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->assertDontSee('data-action-bar', false)->assertDontSee('id="cta-farm"', false);
    }
}
