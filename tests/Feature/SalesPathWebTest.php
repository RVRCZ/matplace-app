<?php

namespace Tests\Feature;

use App\Domain\Farm\Palette;
use App\Domain\Farm\PublicStats;
use App\Domain\Stats\Funnel;
use App\Domain\Stats\Humans;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\FarmVideo;
use App\Models\User;
use App\Support\Money;
use App\Support\ShippingHint;
use App\Support\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        // the hint is worded on the server for the request at hand (the calculator page fetches it in its JSON, docs/AN.md §12.4)
        $this->assertSame(1, preg_match('/window\.MP_CONFIG_URL = "([^"]+)"/', (string) $this->get('/')->assertOk()->getContent(), $m));
        $this->get((string) json_decode('"'.$m[1].'"'))->assertOk()->assertSee('shipping_hint', false);
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

    public function test_trust_comes_only_from_true_things(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true]);
        // prices of real calculations, text only (no picture of a print exists); the home page's own examples stay behind their switch
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="calc-trust"', $html);
        $this->assertStringContainsString('Busta z fotky, výška 80 mm', $html);
        $this->assertStringContainsString(url('/c/vxauvlvidinz'), $html);
        $this->assertStringNotContainsString('id="home-examples"', $html);
        $this->assertStringNotContainsString('id="trust-video"', $html);   // no published video yet
        $this->assertStringNotContainsString('data-trust-numbers', $html);  // no numbers while PublicStats is not there (or says nothing)
        // in euros no price is invented: the examples are priced in crowns only
        $this->assertStringNotContainsString('data-trust-examples', (string) $this->withCookie('currency', 'EUR')->get('/en')->assertOk()->getContent());

        // one published video from the farm: its title from the database, YouTube's no-cookie player only after a click
        FarmVideo::create(['kind' => FarmVideo::KIND_WEEKLY, 'status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => 'abc123XYZ', 'title' => 'Tento týden na farmě', 'published_at' => now()]);
        FarmVideo::create(['kind' => FarmVideo::KIND_HOWTO, 'status' => FarmVideo::STATUS_PENDING, 'youtube_id' => null, 'title' => 'Čeká na schválení']);
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-youtube="abc123XYZ"', $html);
        $this->assertStringContainsString('Tento týden na farmě', $html);
        $this->assertStringNotContainsString('Čeká na schválení', $html);
        $this->assertStringContainsString('youtube-nocookie.com/embed/', $html);
        $this->assertStringNotContainsString('<iframe', substr($html, strpos($html, 'id="calc-trust"')));

        // the farm's numbers once PublicStats exists (session 2's class; a stand-in here while it is not merged)
        if (! class_exists(PublicStats::class)) {
            eval('namespace App\Domain\Farm; final class PublicStats { public static array $fake = []; public static function summary(): array { return self::$fake; } }');
            PublicStats::$fake = ['printed' => 100, 'handed_over_days_median' => 3.4, 'last_30_days_printed' => 12, 'videos_published' => 31];
            $html = (string) $this->get('/')->assertOk()->getContent();
            $this->assertStringContainsString('100 zaplacených výtisků', $html);
            $this->assertStringContainsString('12 z toho', $html);
            $this->assertStringContainsString('obvykle 3 dní', $html);
            $this->assertStringContainsString('31 videí', $html);
        }

        // the farm not offered to this visitor: no block at all
        config(['farm.public' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="calc-trust"', false);
    }

    public function test_the_catalogue_pages_say_what_they_are_within_the_lengths_search_engines_show(): void
    {
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $locale) {
            foreach (['/tools', '/model', '/models'] as $path) {
                $html = (string) $this->get($prefix.$path)->assertOk()->getContent();
                preg_match('/<title>(.*?)<\/title>/s', $html, $title);
                preg_match('/<meta name="description" content="([^"]*)">/', $html, $description);
                $this->assertLessThanOrEqual(60, mb_strlen(html_entity_decode($title[1])), "$locale $path title: $title[1]");
                $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($description[1])), "$locale $path description: $description[1]");
                $this->assertGreaterThanOrEqual(80, mb_strlen($description[1]), "$locale $path description says something: $description[1]");
                $this->assertStringContainsString('<meta property="og:title" content="'.$title[1].'">', $html);
                $this->assertStringContainsString('<meta property="og:description" content="'.$description[1].'">', $html);
            }
            // the tools: the count comes from the configuration, the wording is the site's formal one
            $html = (string) $this->get($prefix.'/tools')->getContent();
            preg_match('/<meta name="description" content="(\d+) /', $html, $count);
            $this->assertNotEmpty($count, "$locale: the description starts with the number of tools");
            $this->assertSame(substr_count($html, 'data-cats="'), (int) $count[1] + substr_count($html, 'data-cats="') - (int) $count[1]);
        }
        $this->assertStringNotContainsString('vidíš', (string) $this->get('/tools')->getContent());
        $this->assertStringContainsString('Nástroje pro 3D tisk', (string) $this->get('/tools')->getContent());
    }

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    /** Open a page as a browser, confirm it, and keep the anonymous-session cookie for the next requests (as StatsTest does). */
    private function openAsBrowser(string $path): AnonymousSession
    {
        // a Czech reader: the test client otherwise says it reads English and a new visitor of the home page is sent to /en
        $response = $this->get($path, ['User-Agent' => self::BROWSER, 'Accept-Language' => 'cs'])->assertOk();
        $token = $response->getCookie(AnonymousSession::COOKIE, false)?->getValue();
        if ($token) {
            $this->withUnencryptedCookie(AnonymousSession::COOKIE, $token)->withCredentials();   // the JSON requests carry the cookie too
        }
        $this->post('/api/seen', ['path' => $path], ['User-Agent' => self::BROWSER])->assertNoContent();

        return AnonymousSession::where('token', $token ?? $this->unencryptedCookies[AnonymousSession::COOKIE])->firstOrFail();
    }

    public function test_a_refused_upload_counts_in_the_funnel_as_an_upload_that_began(): void
    {
        Humans::forget();
        $session = $this->openAsBrowser('/');
        // the server refuses: a format we do not read, a file over the limit, no file at all
        $path = sys_get_temp_dir().'/mp_spw_'.uniqid().'.txt';
        file_put_contents($path, 'not a model');
        $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'notes.txt', null, null, true)], ['User-Agent' => self::BROWSER])->assertStatus(422);
        config(['uploads.max_mb' => 0]);
        $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'model.stl', null, null, true)], ['User-Agent' => self::BROWSER])->assertStatus(422);
        $this->postJson('/api/uploads', [], ['User-Agent' => self::BROWSER])->assertStatus(422);
        // the browser refuses before any upload (the size, the format): told through the small endpoint, which takes nothing else
        $this->postJson('/api/refused', ['step' => 'upload', 'kind' => 'too_big'], ['User-Agent' => self::BROWSER])->assertNoContent();
        $this->postJson('/api/refused', ['step' => 'upload', 'kind' => 'virus'], ['User-Agent' => self::BROWSER])->assertStatus(422);
        $this->postJson('/api/refused', ['step' => 'checkout', 'kind' => 'too_big'], ['User-Agent' => self::BROWSER])->assertStatus(422);
        $kinds = Event::where('session_id', $session->id)->where('type', 'failed')->get()->map(fn ($e) => ($e->meta['step'] ?? '').':'.($e->meta['kind'] ?? ''))->sort()->values()->all();
        $this->assertSame(['upload:format', 'upload:missing', 'upload:too_big', 'upload:too_big'], $kinds);

        // the funnel: an upload began (and was refused), nothing uploaded yet
        $funnel = app(Funnel::class)->compute(30);
        $sales = array_column($funnel['paths']['sales'], 'sessions', 'step');
        $this->assertSame(1, $sales['visit']);
        $this->assertSame(1, $sales['upload|failed:upload']);
        $this->assertSame(0, $sales['upload']);
        $this->assertSame(['n' => 1, 'k' => 1, 'rate' => 100.0], $funnel['audit']['errors']['upload']);

        // then the way on: the registration page as the gate, a top-up of credit as a payment begun, a refused payment from credit as its failure
        foreach (['upload', 'calculation', 'price_shown', 'order_started'] as $type) {
            Event::create(['session_id' => $session->id, 'type' => $type, 'locale' => 'cs', 'source' => 'direct']);
        }
        Event::create(['session_id' => $session->id, 'type' => Event::PAGE, 'locale' => 'cs', 'source' => 'direct', 'meta' => ['path' => '/register']]);
        Event::create(['session_id' => $session->id, 'type' => 'order_created', 'locale' => 'cs', 'source' => 'direct']);
        Event::create(['session_id' => $session->id, 'type' => 'topup_started', 'locale' => 'cs', 'source' => 'direct']);
        Event::create(['session_id' => $session->id, 'type' => 'failed', 'locale' => 'cs', 'source' => 'direct', 'meta' => ['step' => 'pay']]);
        $funnel = app(Funnel::class)->compute(30);
        $sales = array_column($funnel['paths']['sales'], 'sessions', 'step');
        $this->assertSame(1, $sales['upload']);
        $this->assertSame(1, $sales['register|register_visit|order_created']);
        $this->assertSame(1, $sales['checkout_started|topup_started']);
        $this->assertSame(0, $sales['order_paid']);
        $this->assertSame(['n' => 1, 'k' => 1, 'rate' => 100.0], $funnel['audit']['errors']['checkout'], 'a payment the farm refused from credit counts as a failed payment');
        $this->assertSame(['n' => 1, 'k' => 0, 'rate' => 0.0], $funnel['audit']['shares']['topup']);

        // the admin's page names the steps
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get(route('admin.stats.funnel'))->assertOk()->assertSee('Prodejní cesta')->assertSee('Nahrání začalo')->assertSee('Platba začala')->assertSee('Dobití kreditu');
    }

    public function test_businesses_get_a_page_of_their_own_with_tools_prices_and_the_way_to_order(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true]);
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $locale) {
            $page = $this->get($prefix.'/business')->assertOk();
            $html = (string) $page->getContent();
            preg_match('/<title>(.*?)<\/title>/s', $html, $title);
            preg_match('/<meta name="description" content="([^"]*)">/', $html, $description);
            $this->assertLessThanOrEqual(60, mb_strlen(html_entity_decode($title[1])), "$locale title: $title[1]");
            $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($description[1])), "$locale description: $description[1]");
            $page->assertSee('<h1 class="text-2xl font-extrabold text-ink">'.__('pages.business.h1', [], $locale).'</h1>', false);
            // the tools of the business intent as cards (those on offer), at least six of them
            $this->assertGreaterThanOrEqual(6, substr_count(substr($html, strpos($html, 'data-page-tools')), 'data-key="'), "$locale: the tools");
            foreach (['qr', 'logo', 'badge'] as $key) {
                $this->assertStringContainsString('data-key="'.$key.'"', $html);
            }
            // the questions are structured data too, the language twins are named, the page is indexable
            $this->assertStringContainsString('"@type":"FAQPage"', $html);
            $this->assertStringContainsString('rel="alternate" hreflang="en"', $html);
            $this->assertStringNotContainsString('noindex', $html);
        }
        // prices of real calculations in crowns; in euros no price is invented; delivery prices come from the configuration
        $cs = (string) $this->get('/business')->getContent();
        $this->assertStringContainsString('data-page-prices', $cs);
        $this->assertStringContainsString('QR cedulka se stojánkem', $cs);
        $this->assertMatchesRegularExpression('/99\s*Kč/u', $cs);
        $this->assertStringContainsString('výdejní místo (v Česku 99', $cs);
        $this->assertStringNotContainsString(':cz_point', $cs);
        $eur = (string) $this->withCookie('currency', 'EUR')->get('/en/business')->getContent();
        $this->assertStringNotContainsString('data-page-prices', $eur);
        $this->assertStringContainsString(__('site.examples.other_currency', [], 'en'), $eur);
        $this->assertStringContainsString(Money::of(4.0, 'EUR')->format('en'), $eur);   // the pickup point in Czechia in euros, written the English way
        // the sitemap lists it, the tools catalogue leads to it from the business intent
        $dir = sys_get_temp_dir().'/mp_spw_'.uniqid();
        config(['seo.sitemap_dir' => $dir]);
        $this->artisan('matplace:sitemap')->assertSuccessful();
        $this->assertStringContainsString(route('tools.business'), (string) file_get_contents($dir.'/sitemap-tools-cs.xml'));
        File::deleteDirectory($dir);
        $this->get('/tools')->assertOk()->assertSee('data-aim-page="business"', false)->assertSee('href="'.route('tools.business').'"', false);
    }

    public function test_a_guest_who_pays_without_an_account_walks_the_whole_sales_path_and_is_counted_once(): void
    {
        // guest checkout (session G, docs/AO.md): no register before the order; the account comes from the payment's webhook,
        // so the register event (kind checkout) is written after order_paid into the visit that made the order
        Humans::forget();
        $session = $this->openAsBrowser('/');
        foreach (['upload', 'calculation', 'price_shown', 'order_started', 'order_created', 'checkout_started', 'order_paid'] as $type) {
            Event::create(['session_id' => $session->id, 'type' => $type, 'locale' => 'cs', 'source' => 'direct', 'meta' => $type === 'order_paid' ? ['kind' => 'card'] : null]);
        }
        $user = User::factory()->create();
        Event::create(['session_id' => $session->id, 'user_id' => $user->id, 'type' => 'register', 'locale' => 'cs', 'source' => 'direct', 'meta' => ['kind' => 'checkout']]);
        Event::create(['session_id' => $session->id, 'user_id' => $user->id, 'type' => 'refund', 'locale' => 'cs', 'source' => 'direct', 'meta' => ['kind' => 'print_failed', 'value' => 99, 'currency' => 'CZK']]);

        $funnel = app(Funnel::class)->compute(30);
        $sales = array_column($funnel['paths']['sales'], 'sessions', 'step');
        foreach (['visit', 'upload', 'calculation', 'price_shown', 'order_started', 'register|register_visit|order_created', 'order_created', 'checkout_started|topup_started', 'order_paid'] as $step) {
            $this->assertSame(1, $sales[$step], $step);
        }
        $this->assertSame(1, $funnel['sessions'], 'one visit, however many events and whoever it became');
        $this->assertSame(['sessions' => 1, 'order_paid' => 1, 'download' => 0, 'designer_file_uploaded' => 0, 'register' => 1], $funnel['by_source']['direct']);
        $this->assertSame(['n' => 1, 'k' => 1, 'rate' => 100.0], $funnel['audit']['shares']['paid_of_started']);
        $this->assertNotContains('refund', Track::ACTIONS, 'a refund is not a person\'s act');
    }
}
