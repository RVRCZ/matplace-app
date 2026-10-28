<?php

namespace Tests\Feature;

use App\Support\Money;
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
        $this->assertMatchesRegularExpression('/99\s*Kč/u', $cs);
        $this->assertStringContainsString('Rybná 716/24, 110 00 Praha 1', $cs);
        $eur = (string) $this->withCookie('currency', 'EUR')->get('/en/3d-tisk-praha')->getContent();
        $this->assertStringContainsString(Money::of(4.0, 'EUR')->format('en'), $eur);
        // the sitemap lists it
        $dir = sys_get_temp_dir().'/mp_praha_'.uniqid();
        config(['seo.sitemap_dir' => $dir]);
        $this->artisan('matplace:sitemap')->assertSuccessful();
        $this->assertStringContainsString(route('pages.praha'), (string) file_get_contents($dir.'/sitemap-tools-cs.xml'));
        File::deleteDirectory($dir);
    }
}
