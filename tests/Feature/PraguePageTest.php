<?php

namespace Tests\Feature;

use App\Support\Money;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** docs/AP.md §8: the local entry page "3D tisk Praha" says only what is true, in three languages, indexable and in the sitemap. */
class PraguePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_prague_page_has_its_texts_tools_prices_and_questions_in_three_languages(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true]);
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $locale) {
            $page = $this->get($prefix.'/3d-tisk-praha')->assertOk();
            $html = (string) $page->getContent();
            preg_match('/<title>(.*?)<\/title>/s', $html, $title);
            preg_match('/<meta name="description" content="([^"]*)">/', $html, $description);
            $this->assertLessThanOrEqual(60, mb_strlen(html_entity_decode(str_replace(' · matplace', '', $title[1]))), "$locale title: $title[1]");
            $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($description[1])), "$locale description: $description[1]");
            $page->assertSee('<h1 class="text-2xl font-extrabold text-ink">'.__('pages.praha.h1', [], $locale).'</h1>', false);
            $this->assertStringContainsString($locale === 'cs' ? 'Praze' : ($locale === 'en' ? 'Prague' : 'Praga'), $html);
            // the tools as cards, the questions as structured data, the language twins, indexable
            $this->assertGreaterThanOrEqual(6, substr_count(substr($html, strpos($html, 'data-page-tools')), 'data-key="'), "$locale: the tools");
            foreach (['figure', 'relief', 'qr', 'organizer'] as $key) {
                $this->assertStringContainsString('data-key="'.$key.'"', $html);
            }
            $this->assertStringContainsString('"@type":"FAQPage"', $html);
            $this->assertStringContainsString('rel="alternate" hreflang="en"', $html);
            $this->assertStringContainsString('rel="canonical"', $html);
            $this->assertStringNotContainsString('noindex', $html);
            $this->assertStringNotContainsString(':cz_point', $html, 'the delivery prices are filled in');
            // nothing is promised that is not true: no personal pickup yet
            $this->assertStringContainsString($locale === 'cs' ? 'Osobní odběr na farmě zatím nenabízíme' : ($locale === 'en' ? 'Pickup at the farm is not offered yet' : 'La recogida en la granja todavía no se ofrece'), $html);
        }
        // prices of real calculations in crowns, delivery from the configuration
        $cs = (string) $this->get('/3d-tisk-praha')->getContent();
        $this->assertStringContainsString('data-page-prices', $cs);
        $this->assertMatchesRegularExpression('/130\s*Kč/u', $cs);
        // the registered office is a legal fact and stays; the order minimum is a setting of the farm, not a sentence
        $this->assertStringContainsString('se sídlem Rybná 716/24, 110 00 Praha 1', $cs);
        $this->assertStringNotContainsString('nejnižší cena zakázky', $cs);
        $eur = (string) $this->withCookie('currency', 'EUR')->get('/en/3d-tisk-praha')->getContent();
        $this->assertStringContainsString(Money::of(4.0, 'EUR')->format('en'), $eur);
        // the sitemap lists it
        $dir = sys_get_temp_dir().'/mp_praha_'.uniqid();
        config(['seo.sitemap_dir' => $dir]);
        $this->artisan('matplace:sitemap')->assertSuccessful();
        $this->assertStringContainsString(route('pages.praha'), (string) file_get_contents($dir.'/sitemap-tools-cs.xml'));
        File::deleteDirectory($dir);
    }

    /** 13 Nov 2026: nothing prints in Prague. The page keeps its address, title and H1 (we deliver there) and names no place of the farm. */
    public function test_no_text_says_the_farm_is_in_prague_or_offers_a_pickup_there(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true]);
        $claims = '/farm[ěy] v Praze|tiskneme v Praze|v Praze tiskneme|farma stojí v Praze|vyzvedn\w+ (osobně )?v Praze|farm in Prague|print in Prague|in person in Prague|pick it up in Prague|granja en Praga|imprimimos en Praga|recoge\w* en (persona en )?Praga/iu';
        foreach (['cs', 'en', 'es'] as $locale) {
            $page = (string) $this->get(($locale === 'cs' ? '' : '/'.$locale).'/3d-tisk-praha')->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression($claims, $page, $locale.': the Prague page');
            $this->assertDoesNotMatchRegularExpression($claims, (string) $this->get($locale === 'cs' ? '/' : '/'.$locale)->assertOk()->getContent(), $locale.': the home page');
            foreach (['pages', 'landing', 'gifts', 'site', 'tools', 'farm'] as $file) {
                $this->assertDoesNotMatchRegularExpression($claims, (string) json_encode(trans($file, [], $locale), JSON_UNESCAPED_UNICODE), "lang/{$locale}/{$file}.php");
            }
            $this->assertDoesNotMatchRegularExpression($claims, (string) file_get_contents(lang_path($locale.'.json')), "lang/{$locale}.json");
        }
        foreach (File::glob(resource_path('blog/cs/*.md')) as $article) {
            $this->assertDoesNotMatchRegularExpression($claims, (string) file_get_contents($article), basename($article));
        }
        // the place a visitor reads the page for stays in its title and H1, and the search data name the city as the area served only
        $cs = (string) $this->get('/3d-tisk-praha')->getContent();
        $this->assertStringContainsString('<title>3D tisk Praha', $cs);
        $this->assertStringContainsString('"areaServed":[{"@type":"City","name":"Praha"}', $cs);
        $this->assertStringNotContainsString('"streetAddress"', $cs);
    }

    /** 13 Nov 2026: the order minimum is a setting of the farm. No title, description or heading of a tool names a price, no text the old minimum. */
    public function test_no_tool_page_types_a_from_price_or_the_order_minimum(): void
    {
        foreach (['cs', 'en', 'es'] as $locale) {
            foreach (array_keys((array) config('tools')) as $tool) {
                $texts = ToolSeo::texts($tool, $locale);
                if (! $texts) {
                    continue;
                }
                foreach (['title', 'heading', 'description', 'h1'] as $part) {
                    $this->assertDoesNotMatchRegularExpression('/\d\s*(Kč|CZK|EUR|€)/u', (string) ($texts[$part] ?? ''), "{$tool} ({$locale}): {$part}");
                }
                $all = (string) json_encode([$texts, trans('landing.'.$tool, [], $locale)], JSON_UNESCAPED_UNICODE);
                $this->assertDoesNotMatchRegularExpression('/\b99\s*(Kč|CZK)|\b(od|from|desde) \d+\s*(Kč|CZK|EUR)/u', $all, "{$tool} ({$locale})");
                $this->assertDoesNotMatchRegularExpression('/nejnižší cena zakázky je|minimum order is|lowest price of an order is|pedido mínimo es de|precio mínimo de un pedido es/u', $all, "{$tool} ({$locale})");
            }
        }
    }
}
