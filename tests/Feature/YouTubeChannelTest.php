<?php

namespace Tests\Feature;

use App\Domain\YouTube\ChannelCurator;
use App\Domain\YouTube\FarmVideos;
use App\Mail\FarmVideoPublished;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\User;
use App\Models\YouTubeAccount;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Housekeeping of the channel (ChannelCurator, docs/AL.md §13.5): the table of titles, the renaming that only runs
 * with --apply, the playlists after the tools, and the mail to the customer whose video went public. Google is faked.
 */
final class YouTubeChannelTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{method: string, url: string, body: array}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('farm');
        Storage::fake('local');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['youtube.client_id' => 'cid', 'youtube.client_secret' => 'secret']);
        YouTubeAccount::create(['channel_id' => 'UC123', 'channel_title' => 'Matplace 3D', 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);
        $this->calls = [];
        Http::fake(function (HttpRequest $r) {
            $this->calls[] = ['method' => $r->method(), 'url' => $r->url(), 'body' => $r->data()];
            $url = $r->url();
            if (str_contains($url, '/youtube/v3/videos') && $r->method() === 'PUT') {
                return Http::response(['id' => $r['id'], 'status' => ['privacyStatus' => $r['status']['privacyStatus'] ?? 'public']]);
            }
            if (str_contains($url, '/youtube/v3/channels')) {
                return Http::response(['items' => [['id' => 'UC123', 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU123']]]]]);
            }
            if (str_contains($url, '/youtube/v3/playlistItems') && $r->method() === 'GET') {
                return Http::response(['items' => [
                    ['snippet' => ['title' => 'Časosběr – PLA Silk měděná', 'publishedAt' => '2026-09-01T18:00:00Z', 'resourceId' => ['videoId' => 'hand1']], 'status' => ['privacyStatus' => 'public']],
                    ['snippet' => ['title' => 'Busta', 'publishedAt' => '2026-10-01T18:00:00Z', 'resourceId' => ['videoId' => 'vid1']], 'status' => ['privacyStatus' => 'public']],
                ]]);
            }
            if (str_contains($url, '/youtube/v3/playlistItems') && $r->method() === 'POST') {
                return Http::response(['id' => 'item-'.$r['snippet']['resourceId']['videoId']]);
            }
            if (str_contains($url, '/youtube/v3/playlists') && $r->method() === 'GET') {
                return Http::response(['items' => [['id' => 'PLhome', 'snippet' => ['title' => 'Pro domácnost']]]]);
            }
            if (str_contains($url, '/youtube/v3/playlists') && $r->method() === 'POST') {
                return Http::response(['id' => 'PL-'.md5($r['snippet']['title'])]);
            }

            return Http::response(['error' => 'unexpected '.$r->method().' '.$url], 500);
        });
    }

    /** A published Short of a print of the given tool (null = the customer's own file). */
    private function video(string $id, ?string $tool, string $title, int $views, string $kind = FarmVideo::KIND_SHORT, ?FarmOrder $order = null): FarmVideo
    {
        $order ??= FarmOrder::factory()->paid()->done()->create(['video_consent' => true]);
        if ($tool !== null) {
            $order->modelFile->forceFill(['origin' => $tool === 'figure' ? 'generated' : 'tool', 'origin_ref' => $tool === 'figure' ? 'abc' : $tool])->save();
        } else {
            $order->modelFile->forceFill(['original_name' => 'cat.stl'])->save();
        }
        $order->forceFill(['timelapse_short_path' => $order->dir().'/short.mp4', 'timelapse_wide_path' => $order->dir().'/wide.mp4'])->save();
        Storage::disk('farm')->put($order->dir().'/short.mp4', 'x');

        return FarmVideo::create(['farm_order_id' => $order->id, 'kind' => $kind, 'status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => $id, 'title' => $title, 'description' => 'Popis '.$id, 'views' => $views, 'published_at' => now()->subDays(3)]);
    }

    public function test_the_table_proposes_titles_by_the_tool_marks_twins_and_the_same_titles_and_the_uploads_made_by_hand(): void
    {
        $bust = $this->video('vid1', 'figure', 'Busta nebo figurka z fotky – 1 h 33 min tisku za 29 s', 1100);
        $this->video('vid2', 'vase', 'Váza – 5 h tisku za 30 s', 265);
        $this->video('vid3', null, 'Cat – 6 h 33 min tisku za 31 s', 344);
        $this->video('vid4', 'figure', 'Busta nebo figurka z fotky – 1 h 33 min tisku za 29 s', 538);
        $twin = $this->video('vid5', 'figure', 'Busta 16:9', 20, FarmVideo::KIND_WIDE, $bust->order);
        $curator = app(ChannelCurator::class);

        $rows = $curator->rows();
        $this->assertSame(['vid1', 'vid4', 'vid3', 'vid2', 'vid5'], array_column($rows, 'id'), 'the most viewed first');
        $byId = array_column($rows, null, 'id');
        $this->assertSame(ChannelCurator::COLUMNS, array_keys($byId['vid1']));
        $this->assertStringStartsWith('Busta z jedné fotky – 1 h', $byId['vid1']['novy_titulek']);
        $this->assertStringStartsWith('Váza bez jediného švu – ', $byId['vid2']['novy_titulek']);
        $this->assertStringStartsWith('Cat – ', $byId['vid3']['novy_titulek'], 'a customer\'s own file keeps its tidied name');
        $this->assertSame(['Figurky z fotky', 'Pro domácnost', ''], [$byId['vid1']['playlist'], $byId['vid2']['playlist'], $byId['vid3']['playlist']]);
        $this->assertSame(['stejný titul', 'stejný titul'], [$byId['vid1']['duplikat'], $byId['vid4']['duplikat']], 'two videos called the same');
        $this->assertSame(['ano', 'unlisted'], [$byId['vid5']['duplikat'], $byId['vid5']['navrh']], 'the 16:9 twin of a Short');
        $this->assertStringContainsString('utm_content=vid1', $byId['vid1']['novy_popis'], 'the proposed description names the video');
        $this->assertStringContainsString('#Shorts #3dtisk #3dtiskarna #dárek #figurka', $byId['vid1']['novy_popis']);
        $this->assertStringNotContainsString('Tiskárna', $byId['vid1']['novy_popis'], 'the printer line is gone (Roman, 27 Oct 2026)');
        $this->assertStringContainsString("\nMateriál: ", $byId['vid1']['novy_popis']);
        $this->assertStringNotContainsString('#Shorts', $byId['vid5']['novy_popis']);
        $this->assertSame([], $this->calls, 'the table from the database asks YouTube nothing');

        // with --api the uploads made by hand come too, with empty proposals
        $rows = $curator->rows(api: true);
        $hand = collect($rows)->firstWhere('id', 'hand1');
        $this->assertSame(['ručně', 'Časosběr – PLA Silk měděná', '', '2026-09-01'], [$hand['druh'], $hand['dnesni_titulek'], $hand['novy_titulek'], $hand['zverejneno']]);
        $this->assertCount(6, $rows);

        // written and read back as a semicolon CSV, the way Roman's spreadsheet hands it back
        $csv = sys_get_temp_dir().'/mp_titles_'.uniqid().'.csv';
        $this->artisan('youtube:titles', ['--out' => $csv])->expectsOutputToContain('5 videos')->assertSuccessful();
        $read = $curator->readCsv($csv);
        $this->assertCount(5, $read);
        $this->assertSame($byId['vid1']['novy_popis'], collect($read)->firstWhere('id', 'vid1')['novy_popis'], 'a multi-line description survives the round trip');
        @unlink($csv);
    }

    public function test_the_retitle_is_a_dry_run_until_applied_and_then_renames_and_unlists_what_the_table_says(): void
    {
        $this->video('vid1', 'figure', 'Stará busta', 1100);
        $this->video('vid2', 'vase', 'Váza', 265);
        $twin = $this->video('vid5', 'figure', 'Busta 16:9', 20, FarmVideo::KIND_WIDE);
        $csv = sys_get_temp_dir().'/mp_retitle_'.uniqid().'.csv';
        $rows = [
            ['id' => 'vid1', 'dnesni_titulek' => 'Stará busta', 'novy_titulek' => 'Busta z jedné fotky – 1 h 33 min tisku', 'novy_popis' => "Udělejte si vlastní: https://matplace.com/tools/figure?utm_source=youtube&utm_medium=social&utm_campaign=video_short\n\n#Shorts #3dtisk", 'navrh' => ''],
            ['id' => 'vid2', 'dnesni_titulek' => 'Váza', 'novy_titulek' => 'Váza', 'novy_popis' => '', 'navrh' => ''],   // nothing to do
            ['id' => 'vid5', 'dnesni_titulek' => 'Busta 16:9', 'novy_titulek' => '', 'novy_popis' => '', 'navrh' => 'unlisted'],
            ['id' => '', 'dnesni_titulek' => 'řádek bez id', 'novy_titulek' => 'x', 'novy_popis' => '', 'navrh' => ''],
        ];
        app(ChannelCurator::class)->writeCsv($csv, $rows);

        $this->artisan('youtube:retitle', ['csv' => $csv])->expectsOutputToContain('1 to rename, 1 to unlist, of 4 rows')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame([], $this->calls, 'a dry run touches nothing');
        $this->assertSame('Stará busta', FarmVideo::where('youtube_id', 'vid1')->value('title'));

        $this->artisan('youtube:retitle', ['csv' => $csv, '--apply' => true])->expectsOutputToContain('renamed 1, unlisted 0')->assertSuccessful();
        $puts = array_values(array_filter($this->calls, fn ($c) => $c['method'] === 'PUT'));
        $this->assertCount(1, $puts);
        $this->assertStringEndsWith('/videos?part=snippet', $puts[0]['url']);
        $this->assertSame(['vid1', 'Busta z jedné fotky – 1 h 33 min tisku'], [$puts[0]['body']['id'], $puts[0]['body']['snippet']['title']]);
        $this->assertStringContainsString('utm_campaign=video_short&utm_content=vid1', $puts[0]['body']['snippet']['description'], 'the links name the video');
        $video = FarmVideo::where('youtube_id', 'vid1')->first();
        $this->assertSame('Busta z jedné fotky – 1 h 33 min tisku', $video->title);
        $this->assertStringContainsString('utm_content=vid1', (string) $video->description);

        $this->calls = [];
        $this->artisan('youtube:retitle', ['csv' => $csv, '--apply' => true, '--unlist' => true])->expectsOutputToContain('renamed 0, unlisted 1')->assertSuccessful();
        $puts = array_values(array_filter($this->calls, fn ($c) => $c['method'] === 'PUT'));
        $this->assertCount(1, $puts);
        $this->assertSame(['vid5', 'unlisted'], [$puts[0]['body']['id'], $puts[0]['body']['status']['privacyStatus']]);
        $this->assertNotNull($twin->refresh()->meta['unlisted_at']);
        @unlink($csv);
    }

    public function test_the_playlists_are_planned_from_the_tools_and_built_only_on_apply(): void
    {
        $this->video('vid1', 'figure', 'Busta', 1100);
        $this->video('vid2', 'vase', 'Váza', 265);
        $this->video('vid3', null, 'Cat', 344);
        $this->video('vid4', 'medallion', 'Medaile', 934);
        FarmVideo::create(['kind' => FarmVideo::KIND_HOWTO, 'status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => 'how1', 'title' => 'Jak', 'meta' => ['name' => 'printables', 'variant' => 'short'], 'published_at' => now()]);

        $this->artisan('youtube:playlists')->expectsOutputToContain('Figurky z fotky (to be made): 0 in it, 1 to add: vid1')
            ->expectsOutputToContain('Jak to funguje (to be made): 0 in it, 1 to add: how1')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame([], $this->calls, 'the plan asks YouTube nothing');

        $this->artisan('youtube:playlists', ['--apply' => true])->assertSuccessful();
        $posts = array_values(array_filter($this->calls, fn ($c) => $c['method'] === 'POST'));
        $made = array_values(array_filter($posts, fn ($c) => str_contains($c['url'], '/playlists?')));
        $this->assertSame(['Figurky z fotky', 'Dárky se jménem', 'Jak to funguje'], array_map(fn ($c) => $c['body']['snippet']['title'], $made), '"Pro domácnost" exists on the channel already');
        $added = array_values(array_filter($posts, fn ($c) => str_contains($c['url'], '/playlistItems?')));
        $this->assertSame(['vid1', 'vid4', 'vid2', 'how1'], array_map(fn ($c) => $c['body']['snippet']['resourceId']['videoId'], $added));
        $this->assertSame('PLhome', collect($added)->firstWhere('body.snippet.resourceId.videoId', 'vid2')['body']['snippet']['playlistId']);
        $this->assertSame('Pro domácnost', FarmVideo::where('youtube_id', 'vid2')->first()->meta['playlist']);
        $ids = app(ChannelCurator::class)->playlistIds();
        $this->assertSame('PLhome', $ids['Pro domácnost']);
        $this->assertArrayHasKey('Figurky z fotky', $ids);

        // again: nothing to make, nothing to add, the ids remembered
        $this->calls = [];
        $this->artisan('youtube:playlists')->expectsOutputToContain('Pro domácnost (PLhome): 1 in it, 0 to add')->assertSuccessful();
        $this->assertSame([], $this->calls);
    }

    public function test_the_customer_hears_once_that_the_video_is_public_with_the_links_marked_by_the_video(): void
    {
        $customer = User::factory()->create(['email' => 'zakaznik@example.com', 'locale' => 'cs']);
        $order = FarmOrder::factory()->paid()->done()->create(['video_consent' => true, 'user_id' => $customer->id]);
        $order->modelFile->forceFill(['origin' => 'tool', 'origin_ref' => 'vase'])->save();
        $video = $this->video('vid9', 'vase', 'Váza', 0, FarmVideo::KIND_SHORT, $order);
        $videos = app(FarmVideos::class);

        $videos->tellCustomer($video);
        Mail::assertQueued(FarmVideoPublished::class, function (FarmVideoPublished $m) use ($video) {
            return $m->hasTo('zakaznik@example.com') && $m->video->is($video)
                && $m->toolLink === url('/tools/vase').'?utm_source=email&utm_medium=social&utm_campaign=video_short&utm_content=vid9';
        });
        $this->assertNotNull($video->refresh()->meta['customer_mailed_at']);
        $html = (new FarmVideoPublished($video, 'https://x/tools/vase?utm_content=vid9'))->render();
        $this->assertStringContainsString('https://www.youtube.com/watch?v=vid9', $html);
        $this->assertStringContainsString('Vaše video je venku', $html);
        $this->assertStringContainsString('utm_content=vid9', $html);

        // once: a second call, and the 16:9 twin, say nothing more
        $videos->tellCustomer($video->refresh());
        $wide = $this->video('vid10', 'vase', 'Váza 16:9', 0, FarmVideo::KIND_WIDE, $order);
        $videos->tellCustomer($wide);
        Mail::assertQueuedCount(1);

        // no consent, mails switched off, a weekly cut: nobody is written to
        $quiet = User::factory()->create(['email' => 'ticho@example.com', 'notify_email' => false]);
        $other = $this->video('vid11', 'vase', 'Váza', 0, FarmVideo::KIND_SHORT, FarmOrder::factory()->paid()->done()->create(['video_consent' => true, 'user_id' => $quiet->id]));
        $videos->tellCustomer($other);
        $noConsent = $this->video('vid12', 'vase', 'Váza', 0, FarmVideo::KIND_SHORT, FarmOrder::factory()->paid()->done()->create(['video_consent' => false]));
        $videos->tellCustomer($noConsent);
        Mail::assertQueuedCount(1);
    }
}
