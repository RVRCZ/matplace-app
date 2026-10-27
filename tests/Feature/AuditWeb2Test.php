<?php

namespace Tests\Feature;

use App\Domain\Farm\PublicStats;
use App\Domain\Stats\Funnel;
use App\Domain\Stats\SearchConsole;
use App\Domain\Stats\ToolUse;
use App\Mail\GenerationReady;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\FarmVideo;
use App\Models\GenerationRequest;
use App\Models\Post;
use App\Models\User;
use App\Support\NextStep;
use App\Support\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The audit of 26 Oct 2026 and the traffic priority of 27 Oct (docs/prompts/audit-web-2.md, docs/AN.md §12): a shared
 * result is a visit that costs nothing, the calculator other sites embed, and the quality fixes of the audit.
 */
class AuditWeb2Test extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    public function test_a_shared_result_is_one_event_with_its_kind_and_tool(): void
    {
        $this->get('/', ['User-Agent' => self::BROWSER, 'Accept-Language' => 'cs'])->assertOk()->assertSee('shared:', false);
        $this->postJson('/api/shared', ['kind' => 'calc', 'tool' => 'box', 'how' => 'copy'])->assertNoContent();
        $this->postJson('/api/shared', ['kind' => 'tool', 'tool' => 'not-a-tool', 'how' => 'share'])->assertNoContent();
        $this->postJson('/api/shared', ['kind' => 'photo'])->assertStatus(422);
        $events = Event::where('type', 'share')->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame(['kind' => 'calc', 'tool' => 'box', 'code' => 'copy'], $events[0]->meta);
        $this->assertSame(['kind' => 'tool', 'code' => 'share'], $events[1]->meta, 'a tool nobody has is not written down');
        $this->assertContains('share', Track::SHARED, 'Google Analytics hears about it when allowed');
        // every tool's page has the button, hidden until a result exists
        $this->get('/tools/box')->assertOk()->assertSee('id="tool-share"', false)->assertSee(__('toolpage.share'));
        $this->get('/en/tools/qr')->assertOk()->assertSee('Share');
    }

    public function test_the_embedded_calculator_has_a_page_with_the_code_and_a_small_page_for_the_frame(): void
    {
        $page = $this->get('/embed')->assertOk();
        $page->assertSee(__('embed.h1'))->assertSee('&lt;iframe src=&quot;'.route('embed.calc').'&quot;', false)->assertSee('<iframe src="'.route('embed.calc').'"', false);
        $page->assertDontSee('noindex', false);
        $this->get('/en/embed')->assertOk()->assertSee('Copy the code')->assertSee(route('embed.calc', [], true));
        $this->get('/es/embed')->assertOk()->assertSee('Copiar el código');

        // the frame: no site header, no cookie bar, no third-party script; noindex; the routes its script needs
        $frame = (string) $this->get('/embed/calc', ['Referer' => 'https://blog.example.com/3d-tisk/'])->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $frame);
        $this->assertStringContainsString('id="embed-file"', $frame);
        $this->assertStringContainsString('window.MP_ROUTES = { uploads: ', $frame);
        $this->assertStringContainsString('utm_source=embed', $frame);
        $this->assertStringContainsString('utm_campaign=blog.example.com', $frame);
        $this->assertStringNotContainsString('id="site-nav"', $frame);
        $this->assertStringNotContainsString('MP_MEASURE', $frame);
        $this->assertStringNotContainsString('googletagmanager', $frame);
        // where the frame was shown is recorded (the host only), not when the frame is on matplace itself
        $embed = Event::where('type', 'embed')->get();
        $this->assertCount(1, $embed);
        $this->assertSame(['host' => 'blog.example.com'], $embed[0]->meta);
        $this->get('/embed/calc', ['Referer' => url('/embed')])->assertOk();
        $this->assertCount(1, Event::where('type', 'embed')->get());
        // a visit that came out of the frame is its own source
        $this->assertSame('embed', Track::classify('embed', 'https://blog.example.com/', 'matplace.com'));
        $this->assertSame('other', Track::classify('newsletter', 'https://blog.example.com/', 'matplace.com'));
        $this->assertContains('embed', Funnel::SOURCES);
        $this->assertDatabaseCount('anonymous_sessions', AnonymousSession::count());   // nothing special is needed of the visitor
    }

    public function test_a_visitor_may_ask_for_the_result_by_mail_and_gets_one_mail_with_the_link(): void
    {
        Mail::fake();
        $running = GenerationRequest::create(['token' => 'tok123running', 'type' => 'image', 'status' => 'running', 'description' => ['kind' => 'pet']]);
        $this->postJson('/api/generate/tok123running/notify', ['email' => 'not-an-email'])->assertStatus(422);
        $this->postJson('/api/generate/tok123running/notify', ['email' => 'jana@example.com'], ['X-Locale' => 'en'])->assertOk()->assertJson(['ok' => true]);
        $running->refresh();
        $this->assertSame('jana@example.com', $running->notify_email);
        $this->assertSame('en', $running->notify_locale);
        $this->assertSame(1, Event::where('type', 'notify')->count());
        // nothing goes out before the model is done; then one mail with the pet's page and the token, in the visitor's language, and the address is forgotten
        GenerationReady::sendFor($running);
        Mail::assertNothingQueued();
        $running->update(['status' => 'done']);
        GenerationReady::sendFor($running);
        Mail::assertQueued(GenerationReady::class, function (GenerationReady $mail) {
            $url = $mail->url();

            return $mail->hasTo('jana@example.com') && str_contains($url, '/en/') && str_contains($url, 'tools/pet') && str_contains($url, 'generation=tok123running');
        });
        $running->refresh();
        $this->assertNull($running->notify_email);
        $this->assertNotNull($running->notified_at);
        GenerationReady::sendFor($running);
        Mail::assertQueuedCount(1);
        // a model already done needs no mail; a failed one says so
        $this->postJson('/api/generate/tok123running/notify', ['email' => 'jana@example.com'])->assertOk()->assertJson(['done' => true]);
        $failed = GenerationRequest::create(['token' => 'tok123failed', 'type' => 'image', 'status' => 'failed']);
        $this->postJson('/api/generate/tok123failed/notify', ['email' => 'jana@example.com'])->assertStatus(410);
        // the form on both photo tools, hidden until a model is being made; the mail's words exist in three languages
        $this->get('/tools/figure')->assertOk()->assertSee('id="figure-notify"', false)->assertSee(__('figure.notify.lead'));
        $pet = $this->get('/tools/pet');   // the pet is not on offer in every environment (ToolGate): the same markup as the figure's
        if ($pet->status() === 200) {
            $pet->assertSee('id="figure-notify"', false);
        }
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertStringContainsString(':url', trans('figure.mail.body', [], $locale));
            $this->assertNotSame('figure.mail.subject', trans('figure.mail.subject', [], $locale));
        }
    }

    public function test_the_feed_lists_articles_and_verified_tools_in_the_language_of_the_address(): void
    {
        Post::create(['slug' => 'krabicka-na-miru-test', 'title' => ['cs' => 'Krabička na míru za minutu', 'en' => 'A box to measure in a minute'], 'excerpt' => ['cs' => 'Jak na to.', 'en' => 'How to.'], 'body' => ['cs' => 'Text článku.', 'en' => 'The article.'], 'format' => 'markdown', 'published_at' => now()->subDay()]);
        Post::create(['slug' => 'koncept-test', 'title' => ['cs' => 'Koncept'], 'body' => ['cs' => 'Ještě ne.'], 'format' => 'markdown', 'published_at' => null]);
        $feed = $this->get('/feed')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $feed->assertSee('<rss version="2.0"', false)->assertSee('Krabička na míru za minutu')->assertDontSee('Koncept')->assertSee(__('tools.box.title'))->assertSee(route('tools.box'));
        $this->get('/en/feed')->assertOk()->assertSee('A box to measure in a minute')->assertSee(trans('tools.box.title', [], 'en'))->assertDontSee('Krabička na míru');
        $this->get('/')->assertOk()->assertSee('<link rel="alternate" type="application/rss+xml"', false)->assertSee('href="'.route('feed').'"', false);
    }

    public function test_the_admin_sees_who_used_a_tool_each_week_beside_search_console(): void
    {
        $sessions = [AnonymousSession::start('203.0.113.1', self::BROWSER), AnonymousSession::start('203.0.113.2', self::BROWSER), AnonymousSession::start('203.0.113.3', self::BROWSER)];
        $thisWeek = now()->startOfWeek()->addHours(5);
        $lastWeek = now()->startOfWeek()->subWeek()->addHours(5);
        Event::forceCreate(['session_id' => $sessions[0]->id, 'type' => 'calculation', 'locale' => 'cs', 'meta' => ['tool' => 'box'], 'created_at' => $thisWeek]);
        Event::forceCreate(['session_id' => $sessions[0]->id, 'type' => 'download', 'locale' => 'cs', 'meta' => ['tool' => 'box'], 'created_at' => $thisWeek]);   // the same visitor twice is one
        Event::forceCreate(['session_id' => $sessions[1]->id, 'type' => 'generate', 'locale' => 'en', 'meta' => ['tool' => 'figure'], 'created_at' => $thisWeek]);
        Event::forceCreate(['session_id' => $sessions[2]->id, 'type' => 'calculation', 'locale' => 'cs', 'meta' => ['tool' => 'box', 'staff' => true], 'created_at' => $thisWeek]);   // us
        Event::forceCreate(['session_id' => $sessions[2]->id, 'type' => 'view', 'subject_type' => 'tool', 'locale' => 'cs', 'meta' => ['tool' => 'qr'], 'created_at' => $thisWeek]);   // a look is no use
        Event::forceCreate(['session_id' => $sessions[1]->id, 'type' => 'calculation', 'locale' => 'en', 'meta' => ['tool' => 'qr'], 'created_at' => $lastWeek]);
        Event::forceCreate(['type' => 'embed', 'meta' => ['host' => 'blog.example.com'], 'created_at' => $thisWeek]);
        $use = app(ToolUse::class)->weekly(4);
        $this->assertSame(2, $use['weeks'][0]['users']);
        $this->assertSame(['cs' => 1, 'en' => 1], $use['weeks'][0]['by_locale']);
        $this->assertSame(1, $use['weeks'][1]['users']);
        $this->assertSame(1, $use['tools']['box'][$use['keys'][0]]);
        $this->assertSame(1, $use['tools']['qr'][$use['keys'][1]]);
        $this->assertArrayNotHasKey($use['keys'][0], $use['tools']['qr']);

        // the export of Search Console: pages in every language add up to the tool; queries when the export has them
        $dir = sys_get_temp_dir().'/mp_sc_'.uniqid();
        mkdir($dir);
        config(['seo.search_console_dir' => $dir]);
        $this->assertNull(SearchConsole::read());
        file_put_contents($dir.'/search-console-2026-10-20.csv', "Top pages,Clicks,Impressions,CTR,Position\nhttps://matplace.com/tools/box,5,100,5%,8.2\n");
        file_put_contents($dir.'/search-console-2026-10-27.csv', "Stránka;Dotaz;Kliknutí;Zobrazení;CTR;Pozice\nhttps://matplace.com/tools/box;krabička na míru;12;\"1 200\";1 %;6,5\nhttps://matplace.com/en/tools/box;box generator;3;300;1 %;9,0\nhttps://matplace.com/;cena 3d tisku;20;2000;1 %;4,0\nhttps://matplace.com/blog/krabicka;krabička;1;50;2 %;12,0\n");
        $console = SearchConsole::read();
        $this->assertSame('search-console-2026-10-27.csv', $console['file']);
        $this->assertSame(['impressions' => 1500, 'clicks' => 15], ['impressions' => $console['tools']['box']['impressions'], 'clicks' => $console['tools']['box']['clicks']]);
        $this->assertSame(7.0, $console['tools']['box']['position']);
        $this->assertSame('krabička na míru', $console['tools']['box']['queries'][0]['query']);
        $this->assertSame(2000, $console['tools']['calc']['impressions']);
        $this->assertSame(['impressions' => 50, 'clicks' => 1], $console['other']['/blog/krabicka']);

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get(route('admin.stats.tools'))->assertOk()->assertSee('Nástroje týdně')->assertSee(__('tools.box.title'))->assertSee('1 500')->assertSee('blog.example.com');
        File::deleteDirectory($dir);
    }

    /** @return list<array<string, mixed>> every JSON-LD thing on the page */
    private function things(string $html): array
    {
        preg_match_all('~application/ld\+json"[^>]*>(.*?)</script>~s', $html, $m);

        return array_values(array_filter(array_map(fn (string $json) => json_decode($json, true), $m[1]), 'is_array'));
    }

    public function test_sentences_with_numbers_follow_the_count_in_three_languages(): void
    {
        config(['farm.enabled' => true, 'farm.public' => true]);
        // the farm's figures as PublicStats remembers them, and two photographed prints (one from a tool, one a customer's own file)
        Cache::put(PublicStats::CACHE_KEY, ['printed' => 1, 'handed_over_days_median' => 0.5, 'last_30_days_printed' => 1, 'videos_published' => 1], 3600);
        Cache::put(PublicStats::CACHE_KEY.':photos', [
            ['url' => 'https://cdn.example/p1.jpg', 'webp' => 'https://cdn.example/p1.webp', 'short' => null, 'tool' => 'box', 'number' => 'F26-000001', 'at' => '2026-10-20'],
            ['url' => 'https://cdn.example/p2.jpg', 'webp' => null, 'short' => null, 'tool' => null, 'number' => 'F26-000002', 'at' => '2026-10-19'],
        ], 3600);
        FarmVideo::create(['kind' => FarmVideo::KIND_WEEKLY, 'status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => 'abc123XYZ', 'title' => 'Mystický drak – 10 h 59 min tisku za 29 s', 'published_at' => now()]);

        $cs = (string) $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->getContent();
        $this->assertStringContainsString('1 zaplacený výtisk dokončen,', $cs);
        $this->assertStringContainsString('do jednoho dne', $cs);
        $this->assertStringContainsString('1 video z tisku', $cs);
        $this->assertStringContainsString('Mystický drak', $cs);   // the title from the database on the Czech page
        $this->assertStringContainsString('data-trust-photos', $cs);
        $this->assertStringContainsString('p1.webp', $cs);
        $this->assertStringContainsString('alt="Výtisk z farmy matplace: '.e(__('tools.box.title')).'"', $cs);
        $this->assertStringContainsString('alt="Výtisk z farmy matplace: model zákazníka"', $cs);

        $en = (string) $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('1 paid print finished,', $en);
        $this->assertStringContainsString('within a day', $en);
        $this->assertStringNotContainsString('1 days', $en);
        $this->assertStringNotContainsString('Mystický drak', $en);   // a Czech title stays off the English page
        $this->assertStringContainsString('A print from our farm', $en);
        $this->assertStringContainsString('data-youtube="abc123XYZ"', $en);

        $es = (string) $this->get('/es')->assertOk()->getContent();
        $this->assertStringContainsString('en un día', $es);
        $this->assertStringNotContainsString('1 días', $es);
        $this->assertStringNotContainsString('Mystický drak', $es);
        $this->assertStringContainsString('Una impresión de nuestra granja', $es);

        // other counts, other forms; the median is rounded up and read as "within"
        Cache::put(PublicStats::CACHE_KEY, ['printed' => 3, 'handed_over_days_median' => 3.4, 'last_30_days_printed' => 2, 'videos_published' => 2], 3600);
        $cs = (string) $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->getContent();
        $this->assertStringContainsString('3 zaplacené výtisky dokončeny,', $cs);
        $this->assertStringContainsString('do 4 dnů', $cs);
        $this->assertStringContainsString('2 videa z tisku', $cs);

        // the texts for the home page's benefit line and the photo tools' limit are written for trans_choice in all three languages
        foreach (['cs' => ['3 modely denně', '1 model denně'], 'en' => ['3 models a day', '1 model a day'], 'es' => ['3 modelos al día', '1 modelo al día']] as $locale => [$three, $one]) {
            app()->setLocale($locale);
            $this->assertStringContainsString($three, trans_choice('home.benefit.free_tools_n', 3, ['n' => 3]), $locale);
            $this->assertStringContainsString($one, trans_choice('home.benefit.free_tools_n', 1, ['n' => 1]), $locale);
            $this->assertStringContainsString($three, trans_choice('figure.limits', 3, ['n' => 3, 'm' => 9]), $locale);
            $this->assertStringContainsString($one, trans_choice('figure.limits', 1, ['n' => 1, 'm' => 3]), $locale);
        }
        app()->setLocale('cs');
    }

    public function test_the_calculator_and_the_tool_pages_have_no_dead_links(): void
    {
        foreach (['/', '/tools/qr', '/tools/box'] as $path) {
            $html = (string) $this->get($path, ['Accept-Language' => 'cs'])->assertOk()->getContent();
            $this->assertStringNotContainsString('href="#"', $html, $path);
        }
    }

    public function test_the_calculator_reads_its_constants_and_texts_from_one_cached_json(): void
    {
        $html = (string) $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->getContent();
        $this->assertStringNotContainsString('window.MP_CONFIG =', $html);
        $this->assertStringNotContainsString('window.MP_I18N =', $html);
        $this->assertStringContainsString('rel="modulepreload"', $html);
        $this->assertSame(1, preg_match('/window\.MP_CONFIG_URL = "([^"]+)"/', $html, $m));
        $url = (string) json_decode('"'.$m[1].'"');
        $this->assertStringContainsString('l=cs', $url);
        $this->assertSame(1, preg_match('/[?&]v=[0-9a-f]{12}\b/', $url), 'a version of the content in the address: '.$url);

        $json = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'max-age=86400, public')->json();
        $this->assertNotEmpty($json['config']['materials']);
        $this->assertArrayHasKey('shipping_hint', $json['config']);
        $this->assertSame(NextStep::text('calc.status.reading'), $json['i18n']['calc.status.reading']);
        $this->assertStringContainsString('|', $json['i18n']['calc.days'], 'the forms of the plural travel as one text; the script picks one');

        $en = $this->get(str_replace('l=cs', 'l=en', $url))->assertOk()->json();
        $this->assertNotSame($json['i18n']['calc.status.reading'], $en['i18n']['calc.status.reading']);
        $this->assertSame('PLA', $en['config']['default_material']);
        $this->get(str_replace('l=cs', 'l=xx', $url))->assertNotFound();
        $this->get(preg_replace('/m=\w+/', 'm=other', $url))->assertNotFound();
    }

    public function test_the_first_two_picks_of_the_tools_page_load_at_once(): void
    {
        $html = (string) $this->get('/tools', ['Accept-Language' => 'cs'])->assertOk()->getContent();
        $picks = substr($html, (int) strpos($html, 'id="tool-picks"'));
        $picks = substr($picks, 0, (int) strpos($picks, '</section>'));
        $this->assertSame(2, preg_match_all('/loading="eager"/', $picks));
        $this->assertSame(0, preg_match_all('/fetchpriority="high"/', $picks), 'the header\'s logo has the page\'s one priority already');
        $this->assertSame(1, preg_match_all('/fetchpriority="high"/', $html), 'one picture per page gets the priority');
    }

    public function test_about_contact_and_materials_carry_structured_data(): void
    {
        foreach (['/about', '/contact'] as $path) {
            $org = collect($this->things((string) $this->get($path, ['Accept-Language' => 'cs'])->assertOk()->getContent()))->first(fn (array $t) => ($t['@type'] ?? null) === 'Organization');
            $this->assertNotNull($org, $path);
            $this->assertSame('matplace s.r.o.', $org['legalName']);
            $this->assertSame(['@type' => 'PropertyValue', 'propertyID' => 'IČO', 'value' => '22499008'], $org['identifier']);
            $this->assertSame('Rybná 716/24', $org['address']['streetAddress']);
            $this->assertSame('CZ', $org['address']['addressCountry']);
            $this->assertSame('info@matplace.com', $org['email']);
            $this->assertStringEndsWith('/img/logo.png', $org['logo']);
            $this->assertArrayNotHasKey('vatID', $org, 'nothing invented: no VAT id is configured');
        }
        // the home page keeps the short form
        $home = collect($this->things((string) $this->get('/', ['Accept-Language' => 'cs'])->assertOk()->getContent()))->first(fn (array $t) => ($t['@type'] ?? null) === 'Organization');
        $this->assertArrayNotHasKey('legalName', $home);

        $list = collect($this->things((string) $this->get('/en/materials')->assertOk()->getContent()))->first(fn (array $t) => ($t['@type'] ?? null) === 'ItemList');
        $this->assertNotNull($list);
        $this->assertSame(count($list['itemListElement']), $list['numberOfItems']);
        $this->assertGreaterThan(1, $list['numberOfItems']);
        $this->assertStringContainsString('/en/materials#', $list['itemListElement'][0]['url']);
        $this->assertSame(1, $list['itemListElement'][0]['position']);
    }
}
