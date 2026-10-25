<?php

namespace Tests\Feature;

use App\Domain\Farm\Palette;
use App\Support\ShippingHint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The sales path of the site (Roman, 25 Oct 2026; docs/prompts/prodejni-cesta-web.md, docs/AN.md): no blind alley
 * into an empty catalogue, pagination in the language of the page, what delivery adds under the price to orient by.
 */
class SalesPathWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_designers_catalogue_is_no_blind_alley_until_it_has_models(): void
    {
        // empty: not in the menu, not named by the inspiration catalogue, noindex (canonical stays), the page leads on
        $models = $this->get('/models')->assertOk();
        $models->assertSee('<meta name="robots" content="noindex, follow">', false)->assertSee('rel="alternate" hreflang="en"', false);   // the language twins stay, like the inspiration pages
        $models->assertSee(__('models.own_file'))->assertSee(__('models.inspiration_link'))->assertSee(__('models.tools_link'))->assertSee('href="'.route('tools').'"', false);
        $this->get('/model')->assertOk()->assertSee(__('models.inspiration.lead_alone'))->assertDontSee(__('models.inspiration.printable_link'));
        $this->get('/')->assertOk()->assertDontSee(__('models.nav'));
        $dir = sys_get_temp_dir().'/mp_spw_'.uniqid();
        config(['seo.sitemap_dir' => $dir]);
        $this->artisan('matplace:sitemap')->assertSuccessful();
        $this->assertStringNotContainsString(route('models.index'), (string) file_get_contents($dir.'/sitemap-tools-cs.xml'));

        // with enough cards (the count is cached under nav.models) everything comes back by itself
        Cache::put('nav.models', (int) config('catalog.menu_from'), 600);
        $this->get('/models')->assertOk()->assertDontSee('noindex', false);
        $this->get('/model')->assertOk()->assertSee(__('models.inspiration.printable_link'));
        $this->get('/')->assertOk()->assertSee(__('models.nav'));
        $this->artisan('matplace:sitemap')->assertSuccessful();
        $this->assertStringContainsString(route('models.index'), (string) file_get_contents($dir.'/sitemap-tools-cs.xml'));
        File::deleteDirectory($dir);
    }

    public function test_pagination_speaks_the_language_of_the_page(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 48), 100, 48, 2, ['path' => '/model']);
        $words = [
            'cs' => ['Zobrazeno', 'až', 'výsledků', 'Předchozí', 'Další', 'Stránkování', 'Přejít na stránku 3'],
            'en' => ['Showing', 'results', 'Previous', 'Next', 'Pagination Navigation', 'Go to page 3'],
            'es' => ['Mostrando', 'resultados', 'Anterior', 'Siguiente', 'Paginación', 'Ir a la página 3'],
        ];
        foreach ($words as $locale => $expected) {
            app()->setLocale($locale);
            $html = (string) $paginator->links();
            foreach ($expected as $word) {
                $this->assertStringContainsString($word, $html, "$locale: $word");
            }
            if ($locale !== 'en') {
                $this->assertStringNotContainsString('Showing', $html, "$locale keeps no English");
            }
        }
    }

    public function test_the_calculator_names_what_delivery_adds_in_the_visitors_currency(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true]);
        // the hint is worded on the server for the request at hand (the calculator page carries it in MP_CONFIG)
        $this->get('/')->assertOk()->assertSee('shipping_hint', false);
        $cs = (string) ShippingHint::text();
        $this->assertStringContainsString('99', $cs);   // Czechia to a pickup point, config/farm.php
        $this->assertStringContainsString('Kč', $cs);
        $this->assertStringContainsString('zdarma', $cs);   // pickup in person is offered by default
        $this->withCookie('currency', 'EUR')->get('/')->assertOk();
        $eur = (string) ShippingHint::text();
        $this->assertStringContainsString('€', $eur);
        $this->assertStringContainsString('5,90', $eur);   // the cheapest zone abroad (Slovakia) for a visitor of unknown country
        $this->withCookie('currency', 'EUR')->get('/es')->assertOk();
        $es = (string) ShippingHint::text();
        $this->assertStringContainsString('9,90', $es);   // Spain's zone
        $this->assertStringContainsString('envío', $es);

        config(['farm.public' => false]);
        $this->assertNull(ShippingHint::text());   // the farm is not offered: nothing to add
    }

    public function test_the_tools_are_found_by_what_the_visitor_comes_for(): void
    {
        $page = $this->get('/tools')->assertOk();
        $html = (string) $page->getContent();
        foreach (['gift', 'photo', 'home', 'spare', 'business'] as $aim) {
            $page->assertSee('data-aim="'.$aim.'"', false)->assertSee(__('tools.aim.'.$aim));
        }
        $tools = fn (string $aim) => explode(' ', (string) preg_replace('/.*data-aim="'.$aim.'" data-tools="([^"]*)".*/s', '$1', $html));
        $this->assertContains('gifts', $tools('gift'));
        $this->assertContains('sign', $tools('gift'));
        foreach (['figure', 'relief', 'papel', 'filament_art', 'logo'] as $key) {
            $this->assertContains($key, $tools('photo'), $key.' takes a picture');
        }
        $this->assertNotContains('box', $tools('photo'));
        foreach (['repair', 'check', 'calc', 'box'] as $key) {
            $this->assertContains($key, $tools('spare'));
        }
        foreach (['qr', 'logo', 'badge', 'keychain'] as $key) {
            $this->assertContains($key, $tools('business'));
        }
        $this->assertContains('organizer', $tools('home'));
        // the words of a purpose, without accents, on the card that answers it; the phone rows carry no words of their own
        $page->assertSee('darek pro kolegu', false)->assertSee('krabicka vicko pouzdro', false);
        $this->assertSame('darek pro kolegu', Palette::plain('dárek pro kolegu'));
        $this->assertStringNotContainsString('data-words', substr($html, strpos($html, 'id="tool-cats"'), strpos($html, '<div class="hidden lg:block">') - strpos($html, 'id="tool-cats"')));
        $this->get('/en/tools')->assertOk()->assertSee('From a photo')->assertSee('for a colleague');
        $this->get('/es/tools')->assertOk()->assertSee('De una foto')->assertSee('para un colega');
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertGreaterThanOrEqual(15, count(trans('tools.synonyms', [], $locale)), $locale);
        }
    }
}
