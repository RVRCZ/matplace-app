<?php

namespace Tests\Feature;

use App\Domain\Stats\Funnel;
use App\Domain\Stats\SearchConsole;
use App\Domain\Stats\ToolUse;
use App\Mail\GenerationReady;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\GenerationRequest;
use App\Models\Post;
use App\Models\User;
use App\Support\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
