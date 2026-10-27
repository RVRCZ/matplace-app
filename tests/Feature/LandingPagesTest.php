<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Support\LandingPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The landing pages (docs/AH.md §11): the home page (the calculation), the figure and the pet from a photo, the QR
 * sign, the organiser and the box carry "before you order" (also as FAQPage data), "who it is for" and "where next";
 * the results with photos wait behind config('site.examples'); all of it in three languages.
 */
class LandingPagesTest extends TestCase
{
    use RefreshDatabase;

    /** page → its address */
    private const PAGES = ['calc' => '/', 'figure' => '/tools/figure', 'pet' => '/tools/pet-figurine', 'qr' => '/tools/qr', 'organizer' => '/tools/organizer', 'box' => '/tools/box',
        // the search entry pages of session N1 (docs/AP.md §1)
        'svg_to_stl' => '/tools/svg-to-stl', 'repair' => '/tools/repair', 'check' => '/tools/check'];

    /** @return list<array<string, mixed>> the JSON-LD blocks of a page */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(function (string $json) {
            $data = json_decode($json, true);
            $this->assertIsArray($data, 'valid JSON-LD: '.substr($json, 0, 80));

            return $data;
        }, $m[1]);
    }

    public function test_every_landing_page_carries_its_blocks_and_the_faq_data_in_three_languages(): void
    {
        config(['ai.daily_limits.generate_guest' => 1, 'ai.daily_limits.generate_user' => 3]);
        foreach (self::PAGES as $tool => $path) {
            foreach (['cs', 'en', 'es'] as $lang) {
                $r = $this->get($this->localized($path, $lang));
                if ($tool === 'pet' && $r->status() === 404) {
                    continue;   // the pet waits for the generator and its public switch
                }
                $html = (string) $r->assertOk()->getContent();
                app()->setLocale($lang);
                $this->assertStringContainsString('id="landing-order"', $html, "{$tool} {$lang}");
                $this->assertStringContainsString('id="landing-who"', $html, "{$tool} {$lang}");
                $this->assertStringContainsString('id="landing-next"', $html, "{$tool} {$lang}");
                $this->assertStringContainsString(e(__('landing.titles.order')), $html);

                // the questions on the page are the ones in the FAQPage data (no question only for robots)
                $faq = collect($this->jsonLd($html))->firstWhere('@type', 'FAQPage');
                $this->assertNotNull($faq, "{$tool} {$lang}: FAQPage");
                $names = array_column($faq['mainEntity'], 'name');
                $order = LandingPage::order($tool);
                $this->assertGreaterThanOrEqual(3, count($order), "{$tool} {$lang}");
                foreach ($order as $item) {
                    $this->assertContains($item['q'], $names);
                    $this->assertStringContainsString(e($item['q']), $html);
                    $this->assertStringNotContainsString(':cz_point', $item['a'], 'the numbers are filled in');
                    $this->assertStringNotContainsString(':guest', $item['a']);
                }
                $this->assertSame(count($names), count(array_unique($names)), "{$tool} {$lang}: no question twice");
                $this->assertCount(3, LandingPage::who($tool));
            }
        }
    }

    public function test_the_questions_about_printing_with_us_show_only_while_the_farm_is_offered(): void
    {
        config(['farm.public' => false]);
        $closed = array_column(LandingPage::order('qr'), 'q');
        $this->assertNotContains(__('landing.qr.order.1.q'), $closed, 'how many pieces: a question about ordering');
        $this->assertContains(__('landing.qr.order.0.q'), $closed, 'the size of the code: for everybody');
        $this->assertStringNotContainsString(e(__('landing.qr.order.1.q')), (string) $this->get('/tools/qr')->getContent());

        config(['farm.public' => true]);
        $open = LandingPage::order('qr');
        $this->assertContains(__('landing.qr.order.1.q'), array_column($open, 'q'));
        // delivery in the visitor's currency, from config/farm.php
        $delivery = collect(LandingPage::order('calc'))->firstWhere('q', __('landing.calc.order.1.q'));
        $this->assertMatchesRegularExpression('/99\s*Kč.*149\s*Kč.*149\s*Kč.*199\s*Kč/u', $delivery['a']);
        $this->assertStringContainsString(e(__('landing.qr.order.1.q')), (string) $this->get('/tools/qr')->getContent());
        // an admin sees the farm while it is closed to the public
        config(['farm.public' => false]);
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin);
        $this->assertContains(__('landing.qr.order.1.q'), array_column(LandingPage::order('qr'), 'q'));
    }

    public function test_where_next_leads_to_public_tools_and_published_articles_only(): void
    {
        foreach (array_keys(self::PAGES) as $tool) {
            foreach (LandingPage::related($tool) as $r) {
                $this->assertTrue(Route::has(config('tools.'.$r['key'].'.route')), $r['key']);
                $this->get(parse_url($r['url'], PHP_URL_PATH))->assertOk();
            }
        }
        // no article yet: no "further reading"; a published one in this language: there
        $this->assertSame([], LandingPage::posts('figure'));
        $post = Post::create(['slug' => 'jak-vznika-busta-z-fotky', 'title' => ['cs' => 'Jak vzniká busta z fotky'], 'body' => ['cs' => 'Text.'], 'format' => 'markdown', 'published_at' => now()->subDay()]);
        $html = (string) $this->get('/tools/figure')->getContent();
        $this->assertStringContainsString('Jak vzniká busta z fotky', $html);
        $this->assertStringContainsString(route('blog.show', $post), $html);
        $this->get(parse_url(route('blog.show', $post), PHP_URL_PATH))->assertOk();
        // not in English: not on the English page; unpublished: nowhere
        $this->assertStringNotContainsString('Jak vzniká busta z fotky', (string) $this->get('/en/tools/figure')->getContent());
        $post->update(['published_at' => null]);
        $this->assertSame([], LandingPage::posts('figure'));
    }

    public function test_the_results_wait_for_the_photos_behind_a_switch(): void
    {
        $this->get('/tools/figure')->assertOk()->assertDontSee('id="landing-result"', false);
        config(['site.examples' => true]);
        $html = (string) $this->get('/tools/figure')->assertOk()->getContent();
        $this->assertStringContainsString('id="landing-result"', $html);
        $this->assertStringContainsString(e(__('landing.titles.result')), $html);
        $this->assertStringContainsString('Busta z fotky, výška 150 mm', $html);
        // only the prints of this tool, and the home page keeps its own block
        config(['site.example_items' => array_values(array_filter(config('site.example_items'), fn ($i) => $i['tool'] === 'figure'))]);
        $this->assertStringNotContainsString('id="landing-result"', (string) $this->get('/tools/organizer')->getContent(), 'no prints of the organiser yet');
        $this->assertStringContainsString('id="home-examples"', (string) $this->get('/')->getContent());
    }

    public function test_no_text_of_the_landing_pages_is_missing_in_any_language(): void
    {
        $missing = [];
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertTrue(Lang::hasForLocale('landing.titles.order', $locale), $locale);
            foreach (array_keys(config('landing.pages')) as $tool) {
                $order = Lang::get('landing.'.$tool.'.order', [], $locale);
                $who = Lang::get('landing.'.$tool.'.who', [], $locale);
                if (! is_array($order) || count($order) < 5 || count($order) > 7) {
                    $missing[] = "{$tool}.order ({$locale}): 5–7 questions";
                }
                if (! is_array($who) || count($who) !== 3) {
                    $missing[] = "{$tool}.who ({$locale}): three uses";
                }
                // the languages ask the same questions about the farm
                $this->assertSame(array_map(fn ($i) => ! empty($i['farm']), (array) Lang::get('landing.'.$tool.'.order', [], 'cs')), array_map(fn ($i) => ! empty($i['farm']), (array) $order), "{$tool} {$locale}");
            }
        }
        $this->assertSame([], $missing);
        foreach (array_keys(config('landing.pages')) as $tool) {
            $this->assertArrayHasKey($tool, config('tools'), 'every landing page is a tool (the home page is the tool "calc")');
        }
    }
}
