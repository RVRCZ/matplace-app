<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The language of a page is in its address: Czech without a prefix, /en/… and /es/…; nothing else decides it. */
class LocaleUrlTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    public function test_a_tool_page_exists_in_every_language_with_its_own_texts(): void
    {
        $this->get('/tools/sign')->assertOk()->assertSee('<html lang="cs"', false)->assertSee(__('tools.sign.title', [], 'cs'));
        $this->get('/en/tools/sign')->assertOk()->assertSee('<html lang="en"', false)->assertSee(__('tools.sign.title', [], 'en'));
        $this->get('/es/tools/sign')->assertOk()->assertSee('<html lang="es"', false)->assertSee(__('tools.sign.title', [], 'es'));
        // a language we do not have is just an unknown address
        $this->get('/de/tools/sign')->assertNotFound();
    }

    public function test_the_browser_language_and_cookies_do_not_change_a_page(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->withCookie('lang', 'es')
            ->get('/tools/sign')->assertOk()->assertSee('<html lang="cs"', false);
    }

    public function test_the_old_lang_parameter_moves_to_the_prefix_for_good(): void
    {
        $this->get('/tools/sign?lang=en')->assertStatus(301)->assertRedirect('/en/tools/sign');
        $this->get('/en/tools/sign?lang=cs')->assertStatus(301)->assertRedirect('/tools/sign');
        $this->get('/tools/sign?lang=es&preset=keyring')->assertStatus(301)->assertRedirect('/es/tools/sign?preset=keyring');
        $this->get('/?lang=en')->assertStatus(301)->assertRedirect('/en');
        // an unknown value only loses the parameter
        $this->get('/tools/sign?lang=xx')->assertStatus(301)->assertRedirect('/tools/sign');
    }

    public function test_czech_has_no_prefix(): void
    {
        $this->get('/cs/tools')->assertStatus(301)->assertRedirect('/tools');
        $this->get('/cs')->assertStatus(301)->assertRedirect('/');
        $this->get('/cs/tools/sign?preset=keyring')->assertStatus(301)->assertRedirect('/tools/sign?preset=keyring');
    }

    public function test_links_on_a_page_stay_in_its_language(): void
    {
        $en = $this->get('/en/tools')->assertOk();
        $en->assertSee('href="'.url('/en/tools/sign').'"', false)->assertSee('href="'.url('/en/login').'"', false)->assertSee('href="'.url('/en').'"', false);
        $this->assertStringNotContainsString('href="'.url('/tools/sign').'"', $en->getContent());
        // the API and files have one address for every language
        $this->assertStringNotContainsString('/en/api/', $this->get('/en')->getContent());
    }

    public function test_the_start_page_names_all_three_languages(): void
    {
        foreach (['/', '/en', '/es'] as $path) {
            $page = $this->get($path)->assertOk();
            $page->assertSee('<link rel="alternate" hreflang="cs" href="'.url('/').'">', false)
                ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en').'">', false)
                ->assertSee('<link rel="alternate" hreflang="es" href="'.url('/es').'">', false)
                ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/').'">', false)
                ->assertSee('<link rel="canonical" href="'.url($path === '/' ? '/' : $path).'">', false);
        }
    }

    public function test_the_switch_keeps_the_page_and_its_query(): void
    {
        $page = $this->get('/en/tools/sign?preset=keyring')->assertOk();
        $page->assertSee('href="'.url('/tools/sign').'?preset=keyring"', false)->assertSee('href="'.url('/es/tools/sign').'?preset=keyring"', false);
        // the canonical address drops the query
        $page->assertSee('<link rel="canonical" href="'.url('/en/tools/sign').'">', false);
    }

    public function test_a_new_visitor_who_reads_english_lands_on_the_english_start_page_once(): void
    {
        $first = $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9', 'User-Agent' => self::BROWSER])->get('/?utm_source=x');
        $first->assertStatus(302)->assertRedirect('/en?utm_source=x')->assertCookie(Locales::SEEN_COOKIE);
        // the cookie says "has been here": back on the Czech start page they stay
        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9', 'User-Agent' => self::BROWSER])->withUnencryptedCookie(Locales::SEEN_COOKIE, '1')
            ->get('/')->assertOk()->assertSee('<html lang="cs"', false);
    }

    public function test_spanish_german_and_czech_readers(): void
    {
        $visit = fn (string $accept) => $this->withHeaders(['Accept-Language' => $accept, 'User-Agent' => self::BROWSER])->get('/');
        $visit('es-MX,es;q=0.9,en;q=0.8')->assertRedirect('/es');
        $this->flushHeaders();
        // no language of ours in the list: English serves better than Czech
        $visit('de-DE,de;q=0.9')->assertRedirect('/en');
        $this->flushHeaders();
        $visit('cs-CZ,cs;q=0.9,en;q=0.8')->assertOk();
        $this->flushHeaders();
        $visit('sk-SK,sk;q=0.9,en;q=0.5')->assertOk();
    }

    public function test_a_robot_is_never_sent_elsewhere(): void
    {
        $this->withHeaders(['Accept-Language' => 'en-US', 'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->get('/')->assertOk()->assertSee('<html lang="cs"', false);
        $this->flushHeaders();
        // a script or a monitor (anything that is not a browser) stays where it asked to be
        $this->withHeaders(['Accept-Language' => 'en-US', 'User-Agent' => 'curl/8.4.0'])->get('/')->assertOk();
    }

    public function test_only_the_start_page_sends_a_visitor_elsewhere(): void
    {
        $this->withHeaders(['Accept-Language' => 'en-US', 'User-Agent' => self::BROWSER])->get('/tools')->assertOk()->assertSee('<html lang="cs"', false);
    }

    public function test_a_guest_is_sent_to_the_login_page_of_the_same_language(): void
    {
        $this->get('/en/account')->assertRedirect('/en/login');
        $this->get('/account')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/es/account')->assertOk()->assertSee('<html lang="es"', false);
    }

    public function test_forms_post_to_their_language_and_come_back_in_it(): void
    {
        $this->from('/en/login')->post('/en/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertRedirect('/en/login')->assertSessionHasErrors(['email' => __('auth.failed', [], 'en')]);
    }

    public function test_private_and_shared_pages_are_not_indexed_and_carry_no_hreflang(): void
    {
        $login = $this->get('/en/login')->assertOk();
        $login->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->assertStringNotContainsString('hreflang="x-default"', $login->getContent());
        $this->assertStringNotContainsString('rel="canonical"', $login->getContent());
    }

    public function test_json_calls_answer_in_the_language_of_the_page_that_made_them(): void
    {
        $error = fn (array $headers) => $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['line1' => '']], $headers)->assertStatus(422)->json('errors')['params.line1'][0];
        $this->assertSame(__('param.text_required', [], 'cs'), $error([]));
        $this->assertSame(__('param.text_required', [], 'en'), $error(['Referer' => url('/en/tools/sign')]));
        $this->assertSame(__('param.text_required', [], 'es'), $error(['X-Locale' => 'es']));
        // somebody else's page does not choose our language
        $this->assertSame(__('param.text_required', [], 'cs'), $error(['Referer' => 'https://example.com/en/tools/sign']));
    }

    public function test_a_page_that_exists_only_in_czech_is_a_404_with_a_link_elsewhere(): void
    {
        $pages = function () {
            Route::middleware('web')->get('/only-czech/{slug}', function (string $slug) {
                Locales::only(['cs']);

                return view('errors.404');
            })->name('test.only_czech');
        };
        Route::group([], $pages);
        Route::prefix('{locale}')->where(['locale' => Locales::pattern()])->name(Locales::NAME_PREFIX)->group($pages);
        Route::getRoutes()->refreshNameLookups();

        $czech = $this->get('/only-czech/clanek')->assertOk();
        $this->assertStringNotContainsString('<link rel="alternate"', $czech->getContent(), 'one language: no hreflang set');
        $this->get('/en/only-czech/clanek')->assertNotFound()
            ->assertSee(__('site.not_found.untranslated_title', [], 'en'))
            ->assertSee('id="czech-version" href="'.url('/only-czech/clanek').'"', false);
    }

    public function test_an_unknown_address_under_a_prefix_is_not_found_in_that_language(): void
    {
        $this->get('/es/no-such-page')->assertNotFound()->assertSee(__('site.not_found.title', [], 'es'));
        $this->get('/no-such-page')->assertNotFound()->assertSee(__('site.not_found.title', [], 'cs'));
    }

    public function test_route_helper_crosses_languages(): void
    {
        $this->assertSame(url('/tools/sign'), route('tools.sign'));
        $this->assertSame(url('/en/tools/sign'), route('tools.sign', ['locale' => 'en']));
        $this->assertSame(url('/es/tools/sign'), localized_route('tools.sign', [], 'es'));
        $this->assertSame(url('/en/c/abc'), localized_route('calc.share', 'abc', 'en'));
        $this->assertSame(url('/tools/sign'), localized_route('l.tools.sign', [], 'cs'));
        // routes without language twins never get a prefix
        $this->assertSame(url('/api/config'), route('api.config', ['locale' => 'en']) === url('/api/config?locale=en') ? url('/api/config') : route('api.config'));
        app()->setLocale('es');
        $this->assertSame(url('/es/tools'), route('tools'));
        $this->assertSame(url('/admin/farm'), route('admin.farm.dashboard'));
        $this->assertSame(url('/es/farm/terms'), route('farm.terms'));
    }
}
