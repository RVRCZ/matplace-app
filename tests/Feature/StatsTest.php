<?php

namespace Tests\Feature;

use App\Domain\Stats\Funnel;
use App\Domain\Stats\Humans;
use App\Domain\Stats\Overview;
use App\Mail\StatsReport as StatsReportMail;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\MissingPage;
use App\Models\SearchQuery;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\Bots;
use App\Support\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Statistics that count people: robots apart, visits confirmed by the browser, the staff left out (docs/O.md). */
class StatsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Humans::forget();
    }

    /** A visitor nobody saw before: no cookie, an empty browser session. */
    private function newVisitor(): void
    {
        $this->flushSession();
        $this->app['session']->flush();
        $this->app['auth']->forgetGuards();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }

    /** Open a page as a browser and keep its anonymous-session cookie for the next requests. */
    private function open(string $path, array $headers = []): AnonymousSession
    {
        $response = $this->get($path, ['User-Agent' => self::BROWSER] + $headers)->assertOk();
        $token = $response->getCookie(AnonymousSession::COOKIE, false)?->getValue();
        if ($token) {
            $this->withUnencryptedCookie(AnonymousSession::COOKIE, $token);
        }

        return AnonymousSession::where('token', $token ?? $this->unencryptedCookies[AnonymousSession::COOKIE])->firstOrFail();
    }

    private function seen(string $path, array $headers = []): void
    {
        $this->post('/api/seen', ['path' => $path], ['User-Agent' => self::BROWSER] + $headers)->assertNoContent();
    }

    public function test_robots_are_told_by_their_name_and_by_how_they_ask(): void
    {
        $this->assertNull(Bots::byAgent(self::BROWSER));
        $this->assertNull(Bots::byAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/480.0]'), 'the browser inside the Facebook app is a person');
        $this->assertNull(Bots::byAgent('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile DuckDuckGo/5 Safari/537.36'));
        foreach ([
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' => 'google',
            'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36 (compatible; GoogleOther)' => 'google',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)' => 'bing',
            'Mozilla/5.0 (compatible; SeznamBot/4.0; +https://o-seznam.cz/napoveda/vyhledavani/en/seznambot-crawler/)' => 'seznam',
            'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)' => 'ahrefs',
            'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)' => 'openai',
            'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)' => 'anthropic',
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' => 'meta',
            'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)' => 'meta',
            'Mozilla/5.0 (compatible; SomeNewBot/1.0)' => 'other',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/140.0 Safari/537.36' => 'other',
            'curl/8.5.0' => 'other',
            'python-requests/2.32' => 'other',
            '' => 'other',
        ] as $agent => $family) {
            $this->assertSame($family, Bots::byAgent($agent), $agent);
        }
    }

    public function test_a_page_fetched_by_a_robot_is_a_crawl_and_never_a_visit(): void
    {
        $this->get('/tools/vase', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();
        $this->get('/en/tools/vase', ['User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0)'])->assertOk();
        $this->get('/tools/vase', ['User-Agent' => 'curl/8.5.0'])->assertOk();
        // a browser's name is not enough: a browser opening a page asks for HTML, and it does not ask with HEAD
        $this->get('/tools/vase', ['User-Agent' => self::BROWSER, 'Accept' => '*/*'])->assertOk();
        $this->call('HEAD', '/tools/vase', server: ['HTTP_USER_AGENT' => self::BROWSER])->assertOk();

        $this->assertSame(0, Event::whereIn('type', ['visit', 'view'])->count());
        $crawls = Event::where('type', 'crawl')->orderBy('id')->get();
        $this->assertSame(['google', 'bing', 'other', 'other', 'other'], $crawls->pluck('source')->all());
        $this->assertSame(['/tools/vase', '/en/tools/vase'], [$crawls[0]->meta['path'], $crawls[1]->meta['path']]);
        $this->assertSame(['cs', 'en'], [$crawls[0]->locale, $crawls[1]->locale]);
        $this->assertNull($crawls[0]->session_id, 'a crawl belongs to no visitor');
        $this->assertSame(['path'], array_keys($crawls[0]->meta), 'nothing about the machine is kept');

        // the admin's pages are nobody's visit, and a robot's script call confirms nothing
        $this->post('/api/seen', ['path' => '/tools/vase'], ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertNoContent();
        $this->assertSame(0, Event::whereIn('type', ['visit', 'page'])->count());
    }

    public function test_a_visit_is_a_persons_once_the_browser_or_the_visitor_confirmed_it(): void
    {
        // before any visit was ever confirmed nothing can be asked of the old ones: they all count
        $this->open('/tools/vase');
        $this->assertSame(1, Event::people()->count());
        $this->assertNull(Humans::since());

        // the page's script calls home: the visit is confirmed, the page is one page view
        $this->seen('/tools/vase');
        $visit = Event::where('type', 'visit')->sole();
        $this->assertTrue($visit->meta['js']);
        $this->assertSame('/tools/vase', $visit->meta['path']);
        $this->assertNotNull(Humans::since());
        $this->seen('/tools/vase');   // a reload
        $this->seen('/en/tools/box', ['Referer' => 'http://localhost/en/tools/box']);
        $pages = Event::where('type', 'page')->orderBy('id')->get();
        $this->assertSame([['/tools/vase', 'cs'], ['/tools/box', 'en']], $pages->map(fn ($e) => [$e->meta['path'], $e->locale])->all(), 'one page = one line in every language');
        $this->assertSame($visit->session_id, $pages[0]->session_id);

        // a robot dressed as a browser: a page, a new "visitor" every time, never a script
        foreach (range(1, 3) as $i) {
            $this->newVisitor();
            $this->open('/tools/box');
        }
        $this->assertSame(4, Event::where('type', 'visit')->count());
        $this->assertSame(1, Event::people()->count());
        $this->assertSame(1, app(Funnel::class)->compute(30)['sessions']);

        // a visitor whose script was blocked but who searched: only a person does that
        $this->newVisitor();
        $this->open('/tools');
        $this->withCredentials()->postJson('/api/search', ['q' => 'stojan na telefon'], ['User-Agent' => self::BROWSER])->assertOk();
        $this->assertTrue(Event::where('type', 'visit')->latest('id')->first()->meta['act']);
        $this->assertSame(2, Event::people()->count());
        $this->assertSame(2, app(Funnel::class)->compute(30)['sessions']);

        // visits marked as a robot's afterwards (matplace:events-bots) leave as well
        Event::people()->oldest('id')->first()->forceFill(['meta' => ['bot' => 'burst', 'js' => true]])->save();
        $this->assertSame(1, Event::people()->count());
        $this->assertSame(1, app(Funnel::class)->compute(30)['sessions']);
    }

    public function test_a_script_calling_home_without_a_visit_is_the_visit(): void
    {
        // a browser session that began before visits were confirmed, or whose first page was not counted
        $this->seen('/blog');
        $visit = Event::where('type', 'visit')->sole();
        $this->assertSame(['/blog', true], [$visit->meta['path'], $visit->meta['js']]);
        $this->seen('/tools');
        $this->assertSame(1, Event::where('type', 'visit')->count());
        $this->assertSame(2, Event::where('type', 'page')->count());
    }

    public function test_private_addresses_are_kept_as_their_pattern(): void
    {
        $this->assertSame('/c/{calculation}', Track::pathOfAddress('/c/Abc123Secret'));
        $this->assertSame('/c/{calculation}', Track::pathOfAddress('/en/c/Abc123Secret'));
        $this->assertSame('/farm/orders/{order}', Track::pathOfAddress('/farm/orders/42'));
        $this->assertSame('/model/draci-privesek', Track::pathOfAddress('/es/model/draci-privesek'), 'public content keeps its address');
        $this->assertSame('/', Track::pathOfAddress('/en'));
        $this->assertSame('/tools/vase', Track::pathOfAddress('https://elsewhere.example/tools/vase?x=1#y'));
        $this->assertSame('/?', Track::pathOfAddress('/no/such/page'));
    }

    public function test_the_staff_is_not_a_visitor_before_during_or_after_signing_in(): void
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        $anon = $this->open('/tools/vase');
        $this->seen('/tools/vase');
        $this->assertSame(1, Event::people()->count());

        // signs in: the browser is remembered and what it did before leaves the numbers
        $this->actingAs($admin)->get('/tools/box', ['User-Agent' => self::BROWSER])->assertOk();
        $this->assertTrue($anon->fresh()->staff);
        $this->assertSame(0, Event::people()->count());
        $this->assertSame(0, app(Funnel::class)->compute(30)['sessions']);
        $count = Event::count();
        $this->assertTrue(Event::where('session_id', $anon->id)->get()->every(fn ($e) => $e->meta['staff'] === true));

        // signed in or signed out again, the browser writes nothing
        $this->seen('/tools/box');
        $this->actingAs($admin)->get('/admin/stats', ['User-Agent' => self::BROWSER])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->app['session']->flush();
        $this->get('/tools/vase', ['User-Agent' => self::BROWSER])->assertOk();
        $this->seen('/tools/vase');
        $this->assertSame($count, Event::count());

        // another customer is still counted
        $this->newVisitor();
        $this->open('/tools/vase');
        $this->seen('/tools/vase');
        $this->assertSame(1, Event::people()->count());
    }

    public function test_addresses_nothing_answers_to_are_collected_for_a_decision(): void
    {
        $this->get('/stary-cenik', ['User-Agent' => self::BROWSER, 'Referer' => 'https://www.seznam.cz/hledani?q=3d+tisk'])->assertNotFound();
        $this->get('/stary-cenik', ['User-Agent' => self::BROWSER])->assertNotFound();
        $this->get('/stary-cenik', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertNotFound();
        // probes for other systems and missing files are noise
        foreach (['/wp-login.php', '/.env', '/img/logo-old.png', '/admin/nothing', '/cgi-bin/test'] as $noise) {
            $this->get($noise, ['User-Agent' => self::BROWSER]);
        }
        // an old address the site still knows is redirected, not missing
        $this->get('/kontakt', ['User-Agent' => self::BROWSER])->assertRedirect();

        $row = MissingPage::sole();
        $this->assertSame(['/stary-cenik', 3, 1, 'www.seznam.cz/hledani'], [$row->path, $row->hits, $row->bot_hits, $row->referer]);
        $this->assertNotNull($row->first_at);
    }

    public function test_old_visits_of_robots_are_marked_from_what_is_left_of_them(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $visit = function (string $ip, string $agent, string $at, string $path = '/tools/vase', array $meta = []): Event {
            $event = Event::create(['session_id' => AnonymousSession::start($ip, $agent)->id, 'type' => 'visit', 'source' => 'direct', 'locale' => 'cs', 'meta' => ['path' => $path] + $meta]);
            $event->forceFill(['created_at' => $at])->save();

            return $event;
        };
        // a person: the page and then the site's stylesheet, as a browser does
        $person = $visit('192.0.2.10', self::BROWSER, '2026-10-03 10:00:00');
        // a robot whose name was not on the list when it came
        $named = $visit('192.0.2.20', 'Mozilla/5.0 (Linux; Android 6.0.1) AppleWebKit/537.36 Chrome/140.0 Mobile Safari/537.36 (compatible; GoogleOther)', '2026-10-03 10:05:00');
        // a robot dressed as a browser: seven "new visitors" from one address in a day
        $burst = array_map(fn (int $i) => $visit('203.0.113.9', self::BROWSER, "2026-10-03 11:0{$i}:00", '/model/vec-'.$i), range(1, 7));
        // one page from a browser's name, but nothing a browser fetches with it
        $bare = $visit('198.51.100.4', self::BROWSER, '2026-10-03 12:00:00', '/blog');
        // the same, but this one went on to count a price: a person with a blocked stylesheet, whatever the log says
        $acted = $visit('198.51.100.5', self::BROWSER, '2026-10-03 12:30:00', '/blog');
        Event::create(['session_id' => $acted->session_id, 'type' => 'calculation']);
        // confirmed by its browser: never judged
        $confirmed = $visit('203.0.113.9', self::BROWSER, '2026-10-04 09:00:00', '/tools', ['js' => true]);
        // before the chosen day: not looked at
        $old = $visit('192.0.2.20', 'curl/8', '2026-08-01 10:00:00');

        $log = storage_path('framework/testing/access-'.uniqid().'.log');
        @mkdir(dirname($log), 0777, true);
        $line = fn (string $ip, string $at, string $path, int $status = 200, string $agent = self::BROWSER, string $referer = '-') => sprintf('%s - - [%s +0000] "GET %s HTTP/2.0" %d 512 "%s" "%s"', $ip, $at, $path, $status, $referer, $agent);
        file_put_contents($log, implode("\n", [
            $line('192.0.2.10', '03/Oct/2026:10:00:01', '/tools/vase?utm_source=x'),
            $line('192.0.2.10', '03/Oct/2026:10:00:02', '/build/assets/app-abc.css'),
            $line('198.51.100.4', '03/Oct/2026:12:00:00', '/blog'),
            $line('198.51.100.5', '03/Oct/2026:12:30:00', '/blog'),
            $line('198.51.100.7', '03/Oct/2026:13:00:00', '/stary-cenik', 404, self::BROWSER, 'https://www.seznam.cz/hledani?q=cenik'),
            $line('198.51.100.8', '03/Oct/2026:13:10:00', '/stary-cenik', 404, 'Mozilla/5.0 (compatible; Googlebot/2.1)'),
            $line('198.51.100.8', '03/Oct/2026:13:11:00', '/wp-login.php', 404),
            'a line of something else',
        ])."\n");

        $marks = fn () => Event::where('type', 'visit')->orderBy('id')->get()->map(fn (Event $e) => $e->meta['bot'] ?? null)->all();
        $this->artisan('matplace:events-bots', ['--since' => '2026-09-01', '--logs' => $log, '--missing' => true, '--dry-run' => true])
            ->expectsOutputToContain('9 are robots, 2 stay')->assertSuccessful();
        $this->assertSame(array_fill(0, 13, null), $marks());
        $this->assertSame(0, MissingPage::count());

        $expected = [null, 'agent', 'burst', 'burst', 'burst', 'burst', 'burst', 'burst', 'burst', 'no-assets', null, null, null];
        $this->artisan('matplace:events-bots', ['--since' => '2026-09-01', '--logs' => $log, '--missing' => true])->assertSuccessful();
        $this->assertSame($expected, $marks());
        $this->assertSame(['path' => '/tools/vase'], $person->fresh()->meta);
        $this->assertTrue($confirmed->fresh()->meta['js']);
        $this->assertSame([2, 1, 'www.seznam.cz/hledani'], [($page = MissingPage::sole())->log_hits, $page->log_bot_hits, $page->referer]);
        $this->assertSame('/stary-cenik', $page->path);
        // people of the period: the person, the one who acted and the confirmed one
        $this->assertSame(3, Event::people()->where('created_at', '>=', '2026-09-01')->count());

        // a second run finds nothing new and counts nothing twice — also after the application began to count the address itself
        $this->get('/stary-cenik', ['User-Agent' => self::BROWSER])->assertNotFound();
        $this->artisan('matplace:events-bots', ['--since' => '2026-09-01', '--logs' => $log, '--missing' => true])->assertSuccessful();
        $this->assertSame($expected, $marks());
        $this->assertSame([2, 1, 1], [($page = MissingPage::sole())->log_hits, $page->log_bot_hits, $page->hits]);
        // without the log the name and the burst are still told
        Event::where('type', 'visit')->get()->each(fn (Event $e) => $e->forceFill(['meta' => array_diff_key((array) $e->meta, ['bot' => 1])])->save());
        $this->artisan('matplace:events-bots', ['--since' => '2026-09-01'])->assertSuccessful();
        $this->assertSame([null, 'agent', 'burst', 'burst', 'burst', 'burst', 'burst', 'burst', 'burst', null, null, null, null], $marks());
        @unlink($log);
    }

    public function test_the_overview_and_the_weekly_report_speak_of_people_only(): void
    {
        $this->travelTo('2026-10-10 12:00:00');
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $did = function (string $at, string $source, string $locale, array $visit, array $then = [], ?array $utm = null): int {
            $session = AnonymousSession::start('192.0.2.1', self::BROWSER)->id;
            foreach (array_merge([['visit', $visit]], $then) as [$type, $meta]) {
                Event::create(['session_id' => $session, 'type' => $type, 'source' => $source, 'locale' => $locale, 'utm' => $utm, 'meta' => $meta ?: null, 'subject_type' => $type === 'page' ? 'page' : null])
                    ->forceFill(['created_at' => $at])->save();
            }

            return $session;
        };
        // the week before: one visit from the time nothing was confirmed yet, and the first confirmed one
        $did('2026-09-30 09:00:00', 'google', 'cs', ['path' => '/tools/vase']);
        $did('2026-10-01 09:00:00', 'google', 'cs', ['path' => '/', 'js' => true]);
        // this week: a customer from Google who paid, somebody from our video on Facebook who counted a price
        $did('2026-10-08 10:00:00', 'google', 'cs', ['path' => '/tools/vase', 'js' => true], [['page', ['path' => '/tools/vase']], ['page', ['path' => '/model']], ['calculation', []], ['upload', []], ['order_created', []], ['order_paid', []]]);
        $did('2026-10-09 10:00:00', 'facebook', 'en', ['path' => '/', 'js' => true], [['page', ['path' => '/']], ['page', ['path' => '/tools/vase']], ['calculation', []]], ['utm_source' => 'facebook', 'utm_medium' => 'social', 'utm_campaign' => 'video']);
        // and those who are not people: a robot in a browser's clothes, the staff, a marked robot
        $did('2026-10-09 11:00:00', 'direct', 'cs', ['path' => '/model/drak']);
        $did('2026-10-09 12:00:00', 'seznam', 'cs', ['path' => '/', 'js' => true, 'staff' => true], [['calculation', ['staff' => true]]]);
        $did('2026-10-09 13:00:00', 'direct', 'cs', ['path' => '/', 'js' => true, 'bot' => 'burst']);
        foreach ([['google', '/model/a'], ['google', '/model/a'], ['google', '/model/b'], ['bing', '/blog']] as [$bot, $path]) {
            Event::create(['type' => 'crawl', 'source' => $bot, 'locale' => 'cs', 'meta' => ['path' => $path]])->forceFill(['created_at' => '2026-10-08 03:00:00'])->save();
        }
        foreach ([['drak', 0], ['drak', 0], ['váza', 3]] as [$query, $found]) {
            SearchQuery::create(['locale' => 'cs', 'query' => $query, 'results_local' => $found, 'results_external' => 0])->forceFill(['created_at' => '2026-10-08 10:00:00'])->save();
        }
        MissingPage::create(['path' => '/stary-cenik', 'hits' => 3, 'bot_hits' => 1, 'log_hits' => 2, 'log_bot_hits' => 2, 'referer' => 'www.seznam.cz/hledani', 'first_at' => '2026-10-04 10:00:00', 'last_at' => '2026-10-09 10:00:00']);
        SocialPost::create(['platform' => 'facebook', 'subject_type' => 'catalog_model', 'subject_id' => 1, 'text' => 'Drak', 'status' => 'posted', 'posted_at' => '2026-10-08 18:00:00']);

        $o = app(Overview::class)->compute(7, now()->startOfDay());
        $this->assertSame([2, 2, 1], [$o['people'], $o['people_before'], $o['unconfirmed']]);
        $this->assertSame('2026-10-01', $o['confirmed_since']->toDateString());
        $this->assertSame(['google' => ['people' => 1, 'before' => 2], 'facebook' => ['people' => 1, 'before' => 0]], $o['sources']);
        $this->assertSame(['cs' => ['people' => 1, 'before' => 2], 'en' => ['people' => 1, 'before' => 0]], $o['locales']);
        $this->assertSame([['source' => 'facebook', 'medium' => 'social', 'campaign' => 'video', 'people' => 1, 'orders' => 0]], $o['campaigns']);
        $this->assertSame([['path' => '/tools/vase', 'views' => 2, 'people' => 2], ['path' => '/', 'views' => 1, 'people' => 1], ['path' => '/model', 'views' => 1, 'people' => 1]], $o['pages']['rows']);
        $this->assertFalse($o['pages']['landing']);
        $this->assertSame([['people', 2, null], ['calculation', 2, 100.0], ['upload', 1, 50.0], ['order_created', 1, 50.0], ['order_paid', 1, 50.0]], array_map(fn ($s) => [$s['step'], $s['people'], $s['rate']], $o['funnel']));
        $this->assertSame(['total' => 3, 'empty' => [['query' => 'drak', 'searches' => 2]]], $o['searches']);
        $this->assertSame(['/stary-cenik', 2, 3, 'www.seznam.cz/hledani'], [$o['missing'][0]['path'], $o['missing'][0]['people'], $o['missing'][0]['robots'], $o['missing'][0]['referer']]);
        $this->assertSame(['pages' => 4, 'by' => ['google' => ['pages' => 3, 'addresses' => 2], 'bing' => ['pages' => 1, 'addresses' => 1]]], $o['robots']);
        $this->assertSame([7, 1, 4], [count($o['daily']), collect($o['daily'])->firstWhere('day', '2026-10-08')['people'], collect($o['daily'])->firstWhere('day', '2026-10-08')['robots']]);

        // the page
        $page = $this->actingAs($admin)->get('/admin/stats')->assertOk();
        $page->assertSee('Lidé na webu')->assertSee('Spočítali cenu')->assertSee('/stary-cenik')->assertSee('Roboti:')->assertSee('roboti v převleku')->assertSee('od tohoto dne se počítají jen potvrzené návštěvy');
        $this->actingAs($admin)->get('/admin/stats?days=30')->assertOk()->assertSee('Odkud přišli');

        // the same by e-mail
        $this->artisan('matplace:stats-report --print')
            ->expectsOutputToContain('Matplace za týden 3. 10. – 9. 10. 2026: 2 lidí (minule 2, +0 %), 1 zaplacených zakázek')
            ->expectsOutputToContain('Spočítali cenu: 2 (100 % lidí)')
            ->expectsOutputToContain('Google: 1 (minule 2, −50 %)')
            ->expectsOutputToContain('Facebook: 1 příspěvků')
            ->expectsOutputToContain('Přes odkaz facebook / video: 1 lidí, 0 zaplacených zakázek')
            ->expectsOutputToContain('/stary-cenik — 2× lidé, 3× roboti, odkaz z www.seznam.cz/hledani')
            ->expectsOutputToContain('google: 3 stránek (2 různých adres)')
            ->assertSuccessful();
        Mail::fake();
        $this->artisan('matplace:stats-report --to=roman@example.com')->assertSuccessful();
        Mail::assertSent(StatsReportMail::class, fn ($mail) => $mail->hasTo('roman@example.com') && str_contains($mail->render(), 'Odkud přišli') && str_contains($mail->report['subject'], '2 lidí'));
    }

    public function test_robot_pages_are_folded_after_a_month_and_the_rest_stays(): void
    {
        $at = now()->subDays(40);
        foreach (['google', 'google', 'bing'] as $bot) {
            Event::create(['type' => 'crawl', 'source' => $bot, 'locale' => 'cs', 'meta' => ['path' => '/model/a']])->forceFill(['created_at' => $at])->save();
        }
        Event::create(['session_id' => AnonymousSession::start('192.0.2.1', self::BROWSER)->id, 'type' => 'visit', 'source' => 'google', 'locale' => 'cs'])->forceFill(['created_at' => $at])->save();
        Event::create(['type' => 'crawl', 'source' => 'google', 'locale' => 'cs', 'meta' => ['path' => '/model/a']]);

        $this->artisan('matplace:events-rollup')->expectsOutputToContain('Folded 3 robot pages of 1 days')->assertSuccessful();
        $this->assertSame([1, 1], [Event::where('type', 'crawl')->count(), Event::where('type', 'visit')->count()]);
        $this->assertSame(['google' => 2, 'bing' => 1], DB::table('events_daily')->where('type', 'crawl')->orderByDesc('events')->pluck('events', 'source')->all());
    }
}
