<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The five ways in on the home page (TODO #45): a model, a photo or an idea, a gift, a part, a business. */
class HomeWaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_offers_the_five_ways_in_every_language(): void
    {
        config(['features.marketplace' => false]);
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $lang) {
            $r = $this->get($prefix.'/');
            $r->assertOk();
            $html = $r->getContent();
            foreach (['file', 'idea', 'gift', 'part', 'firm'] as $way) {
                $this->assertStringContainsString('data-way="'.$way.'"', $html, "$lang: $way");
                $this->assertStringNotContainsString('home.start.'.$way.'"', $html, "$lang: text of $way missing");
            }
            $this->assertStringNotContainsString('data-way="spare"', $html);
            // the links speak the page's language (/en/tools, /es/gifts)
            $this->assertMatchesRegularExpression('~data-way="part"[^>]*>|href="[^"]*/tools#parts"~', $html);
            $this->assertStringContainsString('/tools#parts"', $html);
            $this->assertStringContainsString('/gifts"', $html);
        }
        $this->assertStringContainsString('Mám 3D model', $this->get('/')->getContent());
    }

    public function test_the_order_of_the_ways_puts_the_file_first_and_the_business_last(): void
    {
        config(['features.marketplace' => false]);
        $html = $this->get('/')->getContent();
        $positions = array_map(fn ($w) => strpos($html, 'data-way="'.$w.'"'), ['file', 'idea', 'gift', 'part', 'firm']);
        $this->assertSame($positions, array_values(array_filter($positions, fn ($p) => $p !== false)));
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }
}
