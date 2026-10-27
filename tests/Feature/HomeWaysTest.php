<?php

namespace Tests\Feature;

use App\Models\AnonymousSession;
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
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Mám 3D model', $html);
        // the two ways that only run a script are buttons, the others real links (audit 26 Oct 2026, A3)
        $this->assertMatchesRegularExpression('~<button\s+type="button"\s+data-way="file"~', $html);
        $this->assertMatchesRegularExpression('~<button\s+type="button"\s+data-way="idea"~', $html);
        $this->assertMatchesRegularExpression('~<a\s+href="[^"]+"\s+data-way="gift"~', $html);
        // no dead link inside the ways themselves (the calculator's two "#" links are session 3's, docs/prompts/audit-web-2.md)
        preg_match('~<nav[^>]*data-ways>.*?</nav>~s', $html, $nav);
        $this->assertNotEmpty($nav);
        $this->assertStringNotContainsString('href="#"', $nav[0]);
        // the hero names the price a print by us starts from (the cheapest priced example, crowns)
        $this->assertStringContainsString('Nebo tisk u nás od 99', $html);
    }

    public function test_a_robot_fetching_a_page_gets_no_session_row_and_no_cookie(): void
    {
        $before = AnonymousSession::count();
        $r = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/');
        $this->assertSame(200, $r->status(), 'robot answer, Location: '.$r->headers->get('Location'));
        $this->assertSame($before, AnonymousSession::count());
        $this->assertNull($r->headers->getCookies() ? collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === AnonymousSession::COOKIE) : null);
        // a person gets both
        // a Czech-reading person (the first visit of someone else is sent to their language, SetLocale)
        $r = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36', 'Accept-Language' => 'cs-CZ,cs;q=0.9'])->get('/');
        $r->assertOk();
        $this->assertSame($before + 1, AnonymousSession::count());
        $this->assertNotNull(collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === AnonymousSession::COOKIE));
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
