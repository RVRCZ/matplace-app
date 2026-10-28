<?php

namespace Tests\Feature;

use App\Domain\Stats\SearchConsole;
use App\Domain\Stats\SearchConsoleClient;
use App\Domain\YouTube\YouTubeClient;
use App\Models\SearchConsoleAccount;
use App\Models\SearchConsoleRow;
use App\Models\User;
use App\Models\YouTubeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Search Console through the API (Roman, 27 Oct 2026, docs/AN.md §14): the account is connected like the YouTube
 * channel, the rows come day by day into the database and are written over, the tools page reads them (with the
 * country filter) and falls back to the CSV export only while the database is empty. Google is faked.
 */
final class SearchConsoleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var list<array{method: string, url: string, body: array}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->setRole(User::ROLE_ADMIN, true);
        // the same OAuth client as the YouTube channel, as on the server
        config(['youtube.client_id' => 'cid', 'youtube.client_secret' => 'secret', 'seo.search_console.client_id' => 'cid', 'seo.search_console.client_secret' => 'secret', 'seo.search_console.row_limit' => 2, 'seo.search_console.retry_seconds' => 0]);
    }

    /** Google as the tests see it: the token exchange, two properties, and search analytics with two rows a page for the pages, three for the queries. */
    private function fakeGoogle(array $sites = ['https://matplace.com/', 'sc-domain:matplace.com'], bool $rateLimitOnce = false, bool $revoked = false): void
    {
        $this->calls = [];
        $limited = false;
        Http::fake(function (HttpRequest $r) use ($sites, $rateLimitOnce, $revoked, &$limited) {
            $this->calls[] = ['method' => $r->method(), 'url' => $r->url(), 'body' => $r->data()];
            $url = $r->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return $revoked
                    ? Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been revoked.'], 400)
                    : Http::response(['access_token' => 'access-2', 'refresh_token' => 'refresh-1', 'expires_in' => 3600]);
            }
            if (str_contains($url, 'oauth2.googleapis.com/revoke')) {
                return Http::response([], 200);
            }
            if (str_ends_with($url, '/webmasters/v3/sites')) {
                return Http::response(['siteEntry' => array_map(fn ($s) => ['siteUrl' => $s, 'permissionLevel' => 'siteOwner'], $sites)]);
            }
            if (str_contains($url, '/searchAnalytics/query')) {
                if ($rateLimitOnce && ! $limited) {
                    $limited = true;

                    return Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded', 'errors' => [['reason' => 'rateLimitExceeded']]]], 429);
                }
                $dims = $r['dimensions'];
                $start = (int) $r['startRow'];
                $date = $r['startDate'];
                if ($dims === ['page', 'country']) {
                    $all = [
                        ['keys' => ['https://matplace.com/tools/box', 'cze'], 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.05, 'position' => 6.0],
                        ['keys' => ['https://matplace.com/en/tools/box', 'usa'], 'clicks' => 2, 'impressions' => 100, 'ctr' => 0.02, 'position' => 12.0],
                        ['keys' => ['https://matplace.com/', 'cze'], 'clicks' => 20, 'impressions' => 400, 'ctr' => 0.05, 'position' => 3.0],
                        ['keys' => ['https://matplace.com/blog/krabicka', 'cze'], 'clicks' => 1, 'impressions' => 50, 'ctr' => 0.02, 'position' => 20.0],
                    ];
                } else {
                    $all = [
                        ['keys' => ['https://matplace.com/tools/box', 'krabička na míru', 'cze'], 'clicks' => 8, 'impressions' => 120, 'ctr' => 0.066, 'position' => 5.0],
                        ['keys' => ['https://matplace.com/tools/box', 'krabička s víkem', 'cze'], 'clicks' => 1, 'impressions' => 30, 'ctr' => 0.033, 'position' => 9.0],
                        ['keys' => ['https://matplace.com/en/tools/box', 'box generator', 'usa'], 'clicks' => 2, 'impressions' => 80, 'ctr' => 0.025, 'position' => 11.0],
                        ['keys' => ['https://matplace.com/tools/box', 'krabička zdarma', 'cze'], 'clicks' => 0, 'impressions' => 60, 'ctr' => 0.0, 'position' => 15.0],
                    ];
                }
                // the last day has nothing yet, as Search Console has not finished it
                if ($date === now()->toDateString()) {
                    $all = [];
                }

                return Http::response(['rows' => array_slice($all, $start, 2), 'responseAggregationType' => 'byPage']);
            }

            return Http::response(['error' => 'unexpected '.$r->method().' '.$url], 500);
        });
    }

    private function connected(string $site = 'sc-domain:matplace.com'): SearchConsoleAccount
    {
        return SearchConsoleAccount::create(['site_url' => $site, 'sites' => ['https://matplace.com/', 'sc-domain:matplace.com'], 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);
    }

    public function test_the_account_is_connected_like_the_youtube_channel_read_only_and_the_property_is_picked_or_chosen(): void
    {
        $this->fakeGoogle();
        $this->actingAs($this->admin)->get('/admin/search-console')->assertOk()->assertSee('Připojit Search Console')->assertSee(route('admin.search_console.callback'));
        $go = $this->actingAs($this->admin)->post('/admin/search-console/connect');
        $go->assertRedirect();
        parse_str((string) parse_url($go->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('https://www.googleapis.com/auth/webmasters.readonly', $q['scope']);
        $this->assertSame('offline', $q['access_type']);
        $this->assertSame(route('admin.search_console.callback'), $q['redirect_uri']);
        $this->assertStringEndsWith('/admin/search-console/callback', $q['redirect_uri']);

        // a forged state is refused
        $this->actingAs($this->admin)->get('/admin/search-console/callback?code=x&state=wrong')->assertRedirect('/admin/search-console')->assertSessionHas('error');
        $this->assertSame(0, SearchConsoleAccount::count());

        $this->actingAs($this->admin)->post('/admin/search-console/connect');
        $state = session('search_console_oauth_state');
        $this->actingAs($this->admin)->get('/admin/search-console/callback?code=abc&state='.$state)->assertRedirect('/admin/search-console')->assertSessionHas('status');
        $account = SearchConsoleAccount::current();
        $this->assertSame('refresh-1', $account->refresh_token);
        $this->assertStringNotContainsString('refresh-1', (string) DB::table('search_console_accounts')->value('refresh_token'), 'encrypted at rest');
        $this->assertSame(['https://matplace.com/', 'sc-domain:matplace.com'], $account->sites);
        $this->assertNull($account->site_url, 'two properties, none of them ours in tests (APP_URL is localhost): the admin picks');
        $this->assertFalse($account->ready());
        $this->actingAs($this->admin)->get('/admin/search-console')->assertOk()->assertSee('data-account="no-site"', false)->assertSee('sc-domain:matplace.com');

        $this->actingAs($this->admin)->post('/admin/search-console/site', ['site' => 'https://elsewhere.example/'])->assertStatus(422);
        $this->actingAs($this->admin)->post('/admin/search-console/site', ['site' => 'sc-domain:matplace.com'])->assertRedirect('/admin/search-console')->assertSessionHas('status');
        $this->assertSame('sc-domain:matplace.com', $account->fresh()->site_url);
        $this->actingAs($this->admin)->get('/admin/search-console')->assertOk()->assertSee('data-account="ready"', false)->assertSee('Stáhnout teď');

        // one property only: taken at once; the domain property of this host is preferred among several
        $this->assertSame('sc-domain:one.example', SearchConsoleClient::pick(['sc-domain:one.example']));
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $this->assertSame('sc-domain:'.$host, SearchConsoleClient::pick(['https://other.example/', 'sc-domain:'.$host]));

        // disconnecting forgets the token here and leaves the grant with Google: a revoke would take the YouTube channel's token too (28 Oct 2026)
        $this->actingAs($this->admin)->post('/admin/search-console/disconnect')->assertRedirect('/admin/search-console');
        $this->assertSame(0, SearchConsoleAccount::count());
        $this->assertFalse(collect($this->calls)->contains(fn ($c) => str_contains($c['url'], '/revoke')), 'a shared client is never revoked');
    }

    public function test_only_admins_reach_the_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/search-console')->assertRedirect();
        $this->actingAs($user)->post('/admin/search-console/connect')->assertRedirect();
        $this->assertNull(session('search_console_oauth_state'));
        $this->artisan('searchconsole:pull')->expectsOutputToContain('not connected')->assertFailed();
    }

    public function test_the_pull_walks_the_days_and_the_pages_of_the_answer_and_writes_every_row_once(): void
    {
        $this->fakeGoogle(rateLimitOnce: true);
        $this->connected();
        $since = now()->subDays(2)->toDateString();

        $this->artisan("searchconsole:pull --since={$since}")->expectsOutputToContain('Done: 3 days')->assertSuccessful();   // one substring: Mockery hands a line to the first expectation it fits
        // two finished days × (4 page rows + 4 query rows); today is empty
        $this->assertSame(16, SearchConsoleRow::count());
        $this->assertSame(8, SearchConsoleRow::whereNull('query')->count());
        $this->assertSame(['cze', 'usa'], SearchConsoleRow::whereNotNull('country')->distinct()->orderBy('country')->pluck('country')->all());
        // the answer came in pages of two: 3 requests for 4 page rows (the third finds the end), 3 for 4 query rows, 1 for each empty kind of today, plus the 429 that was tried again
        $queries = array_values(array_filter($this->calls, fn ($c) => str_contains($c['url'], '/searchAnalytics/query')));
        $this->assertCount(2 * (3 + 3) + 2 + 1, $queries);
        $this->assertSame([0, 2], array_values(array_unique(array_map(fn ($c) => (int) $c['body']['startRow'], array_slice($queries, 0, 3)))));
        $this->assertStringContainsString('sc-domain%3Amatplace.com', $queries[0]['url'], 'the property is URL-encoded in the address');
        $this->assertSame('web', $queries[0]['body']['type']);
        $account = SearchConsoleAccount::current();
        $this->assertNotNull($account->last_pulled_at);
        $this->assertSame($since, $account->last_pulled_from->toDateString());
        $this->assertNull($account->last_error);

        // the same again: written over, not doubled; a changed number wins
        SearchConsoleRow::where('page', 'https://matplace.com/')->whereNull('query')->update(['clicks' => 1]);
        $this->artisan("searchconsole:pull --since={$since}")->assertSuccessful();
        $this->assertSame(16, SearchConsoleRow::count());
        $this->assertSame(20, SearchConsoleRow::where('page', 'https://matplace.com/')->whereNull('query')->first()->clicks);

        // the daily run: the last three days by default, one of them (three days ago) new here
        $this->artisan('searchconsole:pull')->expectsOutputToContain('4 days')->assertSuccessful();
        $this->assertSame(24, SearchConsoleRow::count());

        // "pull now" from the admin page does the same
        $this->actingAs($this->admin)->post('/admin/search-console/pull')->assertRedirect('/admin/search-console')->assertSessionHas('status');
        $this->actingAs($this->admin)->get('/admin/search-console')->assertOk()->assertSee('data-days', false)->assertSee('24 řádků');
    }

    public function test_the_first_run_of_a_fresh_account_goes_sixteen_months_back_and_a_refused_grant_asks_for_a_new_connection(): void
    {
        $this->fakeGoogle();
        $this->connected();
        config(['seo.search_console.first_pull_months' => 1]);
        $this->artisan('searchconsole:pull')->expectsOutputToContain('First run of this account')->assertSuccessful();
        $this->assertSame(now()->startOfDay()->subMonth()->toDateString(), SearchConsoleAccount::current()->last_pulled_from->toDateString());
    }

    public function test_a_refused_grant_is_written_to_the_account_and_asks_for_a_new_connection(): void
    {
        $this->fakeGoogle(revoked: true);   // its own test: a second Http::fake would only add stubs behind the first
        $this->connected()->forceFill(['access_expires_at' => now()->subMinute(), 'last_pulled_at' => now()])->save();
        $this->artisan('searchconsole:pull')->expectsOutputToContain('Connect the account again')->assertFailed();
        $this->assertStringContainsString('revoked', (string) SearchConsoleAccount::current()->last_error);
        $this->actingAs($this->admin)->get('/admin/search-console')->assertOk()->assertSee('Chyba:');
    }

    public function test_the_tools_page_reads_the_database_with_the_country_filter_and_the_csv_only_without_rows(): void
    {
        $dir = sys_get_temp_dir().'/mp_sc_'.uniqid();
        mkdir($dir);
        config(['seo.search_console_dir' => $dir]);
        file_put_contents($dir.'/search-console-2026-10-20.csv', "Top pages,Clicks,Impressions,CTR,Position\nhttps://matplace.com/tools/box,5,100,5%,8.2\n");
        $csv = SearchConsole::current(now()->subWeeks(4), now());
        $this->assertSame('csv', $csv['source']);
        $this->assertSame(100, $csv['tools']['box']['impressions']);
        $this->assertSame(['cs' => ['impressions' => 100, 'clicks' => 5]], $csv['tools']['box']['by_locale']);
        $this->actingAs($this->admin)->get(route('admin.stats.tools'))->assertOk()->assertSee('search-console-2026-10-20.csv')->assertSee('připojit přes API');

        $this->fakeGoogle();
        $this->connected();
        $this->artisan('searchconsole:pull --since='.now()->subDays(2)->toDateString())->assertSuccessful();

        $all = SearchConsole::current(now()->subWeeks(4), now());
        $this->assertSame('api', $all['source']);
        $this->assertSame(now()->subDays(1)->toDateString(), $all['until']);
        // two finished days: the page totals count, the queries only name themselves
        $this->assertSame(['impressions' => 600, 'clicks' => 24], ['impressions' => $all['tools']['box']['impressions'], 'clicks' => $all['tools']['box']['clicks']]);
        $this->assertSame(['cs' => ['impressions' => 400, 'clicks' => 20], 'en' => ['impressions' => 200, 'clicks' => 4]], $all['tools']['box']['by_locale']);
        $this->assertSame(8.0, $all['tools']['box']['position']);
        $this->assertSame('krabička na míru', $all['tools']['box']['queries'][0]['query']);
        $this->assertSame(16, $all['tools']['box']['queries'][0]['clicks']);
        $this->assertSame(800, $all['tools']['calc']['impressions']);
        $this->assertSame(['impressions' => 100, 'clicks' => 2], $all['other']['/blog/krabicka']);
        $this->assertSame(['impressions' => 1500, 'clicks' => 66], $all['total']);

        $cz = SearchConsole::current(now()->subWeeks(4), now(), 'cz');
        $this->assertSame(400, $cz['tools']['box']['impressions']);
        $this->assertArrayNotHasKey('en', $cz['tools']['box']['by_locale']);
        $other = SearchConsole::current(now()->subWeeks(4), now(), 'other');
        $this->assertSame(200, $other['tools']['box']['impressions']);
        $this->assertArrayNotHasKey('calc', $other['tools']);
        // a period before the rows: the database has rows, so no CSV, just nothing for the period
        $old = SearchConsole::current(now()->subYears(2), now()->subYear());
        $this->assertSame('api', $old['source']);
        $this->assertSame([], $old['tools']);

        // the weeks of every tool and the queries nobody clicked (docs/AN.md §15)
        $weekly = SearchConsole::weeklyByTool(4);
        $this->assertSame(600, array_sum(array_column($weekly['box'], 'impressions')));
        $this->assertSame(24, array_sum(array_column($weekly['box'], 'clicks')));
        $this->assertSame(800, array_sum(array_column($weekly['calc'], 'impressions')));
        $zero = SearchConsole::zeroClickQueries(28, 20);
        $this->assertSame([['query' => 'krabička zdarma', 'impressions' => 120, 'position' => 15.0, 'page' => '/tools/box']], $zero);
        $this->assertSame([], SearchConsole::zeroClickQueries(28, 20, 'other'));
        $this->assertSame([], SearchConsole::modelPages(90, 10), 'no /model/ pages in these rows');

        $page = $this->actingAs($this->admin)->get(route('admin.stats.tools', ['country' => 'cz']))->assertOk();
        $page->assertSee('Dotazy bez kliknutí')->assertSee('krabička zdarma')->assertSee('data-zero-clicks', false);
        $page->assertSee('Data ze Search Console')->assertSee(now()->subDays(1)->format('j. n. Y'))->assertSee(__('tools.box.title'))->assertSee('400')->assertDontSee('search-console-2026-10-20.csv');
        $page->assertSee(route('admin.stats.tools', ['weeks' => 12, 'country' => 'other']));
        File::deleteDirectory($dir);
    }

    public function test_a_grant_shared_between_search_console_and_youtube_is_revoked_by_neither_side_while_the_other_uses_it(): void
    {
        $this->fakeGoogle();
        $revokes = fn () => collect($this->calls)->filter(fn ($c) => str_contains($c['url'], '/revoke'))->count();
        $this->assertTrue(SearchConsoleClient::sharesClientWithYouTube());

        // YouTube disconnects while Search Console reads with the same client and account: the grant stays
        $this->connected();
        YouTubeAccount::create(['channel_id' => 'UC123', 'channel_title' => 'Matplace 3D', 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);
        app(YouTubeClient::class)->disconnect();
        $this->assertSame(0, YouTubeAccount::count());
        $this->assertSame(0, $revokes());

        // nobody else uses the grant: YouTube revokes as before
        SearchConsoleAccount::query()->delete();
        YouTubeAccount::create(['channel_id' => 'UC123', 'channel_title' => 'Matplace 3D', 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);
        app(YouTubeClient::class)->disconnect();
        $this->assertSame(1, $revokes());

        // Search Console with a client of its own revokes its own grant
        config(['seo.search_console.client_id' => 'own-client']);
        $this->assertFalse(SearchConsoleClient::sharesClientWithYouTube());
        $this->connected();
        app(SearchConsoleClient::class)->disconnect();
        $this->assertSame(0, SearchConsoleAccount::count());
        $this->assertSame(2, $revokes());
    }
}
