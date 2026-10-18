<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\YouTubeClient;
use App\Engines\Social\FakeMetaClient;
use App\Jobs\UploadFarmVideo;
use App\Mail\FarmAdminAlert;
use App\Models\FarmAgent;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmVideo;
use App\Models\Payment;
use App\Models\SocialPost;
use App\Models\User;
use App\Models\YouTubeAccount;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Print videos: consent → private upload → admin publishes or rejects; the customer can take the consent back. Google is faked. */
class YouTubeVideosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    /** What Google says to a link comment (commentThreads.insert): 200 or 403 for a token without youtube.force-ssl */
    private int $commentStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        config(['youtube.client_id' => 'cid', 'youtube.client_secret' => 'secret']);
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->setRole(User::ROLE_ADMIN, true);
        app(FarmSettings::class)->set('admin_email', 'farm@example.com');
    }

    /** A paid customer order (fake slicer, fake gateway), optionally with the YouTube checkbox ticked. */
    private function paidOrder(bool $consent): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_yt_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();

        $this->actingAs($this->user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();

        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", [
            'slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total'],
            'video_consent' => $consent,
        ])->assertOk();

        return $order->refresh();
    }

    /** The print is done and BuildFarmTimelapse produced the video. */
    private function filmed(FarmOrder $order): void
    {
        Storage::disk('farm')->put($order->dir().'/timelapse.mp4', str_repeat("\0", 2048));
        $order->forceFill(['status' => FarmOrder::STATUS_DONE, 'timelapse_path' => $order->dir().'/timelapse.mp4'])->save();
    }

    private function connectedChannel(): YouTubeAccount
    {
        return YouTubeAccount::create(['channel_id' => 'UC123', 'channel_title' => 'Matplace – 3D', 'refresh_token' => 'refresh-1', 'access_token' => 'old', 'access_expires_at' => now()->subMinute()]);
    }

    /** Google as the tests need it; $privacy = what YouTube reports after a publish. */
    private function fakeGoogle(string $privacy = 'public'): void
    {
        Http::fake(function (HttpRequest $r) use ($privacy) {
            $url = $r->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'access-2', 'refresh_token' => 'refresh-1', 'expires_in' => 3600]);
            }
            if (str_contains($url, 'oauth2.googleapis.com/revoke')) {
                return Http::response([], 200);
            }
            if (str_contains($url, '/upload/youtube/v3/videos') && $r->method() === 'POST') {
                return Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=abc']);
            }
            if (str_contains($url, 'upload_id=abc') && $r->method() === 'PUT') {
                return Http::response(['id' => 'vid123', 'status' => ['privacyStatus' => 'private']]);
            }
            if (str_contains($url, '/youtube/v3/videos') && $r->method() === 'PUT') {
                // a scheduled video stays private and YouTube echoes the time
                $at = $r['status']['publishAt'] ?? null;

                return Http::response(['id' => 'vid123', 'status' => ['privacyStatus' => $at ? 'private' : $privacy] + ($at ? ['publishAt' => $at] : [])]);
            }
            if (str_contains($url, '/youtube/v3/videos') && $r->method() === 'GET') {
                return Http::response(['items' => [['id' => 'vid123', 'statistics' => ['viewCount' => '1520', 'likeCount' => '87', 'commentCount' => '4'], 'status' => ['privacyStatus' => 'public']]]]);
            }
            if (str_contains($url, '/youtube/v3/videos') && $r->method() === 'DELETE') {
                return Http::response('', 204);
            }
            if (str_contains($url, '/youtube/v3/channels')) {
                return Http::response(['items' => [['id' => 'UC123', 'snippet' => ['title' => 'Matplace – 3D']]]]);
            }
            if (str_contains($url, '/youtube/v3/commentThreads') && $r->method() === 'POST') {
                return $this->commentStatus === 200 ? Http::response(['id' => 'thread-1'])
                    : Http::response(['error' => ['code' => 403, 'message' => 'Request had insufficient authentication scopes.', 'errors' => [['reason' => 'insufficientPermissions']]]], 403);
            }

            return Http::response(['error' => 'unexpected '.$r->method().' '.$url], 500);
        });
    }

    public function test_consented_video_waits_for_an_admin_goes_up_public_on_approval_and_is_removed_when_the_consent_is_withdrawn(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle();
        $order = $this->paidOrder(consent: true);
        $this->assertTrue($order->video_consent);
        $this->assertNotNull($order->video_consent_at);

        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();   // what BuildFarmTimelapse does at its end

        // nothing goes to YouTube before the admin says so (every upload counts against the channel's daily limit)
        $this->assertSame(FarmVideo::STATUS_PENDING, $video->status);
        $this->assertNull($video->youtube_id);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=resumable'));
        $this->assertStringContainsString('3D tisku', $video->title);      // no readable name in "part.stl": material and colour, then the print time
        Mail::assertQueued(FarmAdminAlert::class, fn ($m) => str_contains($m->subjectLine, 'Video ke schválení') && $m->url === route('admin.youtube.index'));

        // the customer can download the square Short once it exists
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/short.mp4")->assertNotFound();
        Storage::disk('farm')->put($order->dir().'/short.mp4', 'mp4');
        $order->forceFill(['timelapse_short_path' => $order->dir().'/short.mp4'])->save();
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/short.mp4")->assertOk()->assertDownload('matplace-'.$order->number.'-short.mp4');
        $this->assertStringContainsString('short.mp4', (string) $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json('short_url'));
        $this->actingAs(User::factory()->create())->get("/farm/orders/{$order->token}/short.mp4")->assertNotFound();

        // nothing on YouTube yet: there is nothing to replace
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/replace")->assertRedirect()->assertSessionHas('error');
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'DELETE');

        // the admin page lists it; the approval uses the edited title: uploaded private, then made public, in one go (sync queue)
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Nahrát na YouTube a zveřejnit')->assertSee('Bez fotky hotového kusu');
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'Váza ve spirále', 'description' => 'Popis', 'when' => 'now'])->assertRedirect()->assertSessionHas('status');
        $video->refresh();
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $video->status);
        $this->assertSame(['vid123', 'Váza ve spirále', $this->admin->id], [$video->youtube_id, $video->title, $video->decided_by]);
        $this->assertNotNull($video->approved_at);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=resumable') && $r['status']['privacyStatus'] === 'private' && $r['snippet']['title'] === 'Váza ve spirále');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), 'youtube/v3/videos?part=snippet,status') && $r['status']['privacyStatus'] === 'public');

        // a published video can be replaced (e.g. with the finish photo): the copy goes, the new file waits for approval again
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/replace")->assertRedirect()->assertSessionHas('status');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'id=vid123'));
        $this->assertSame(FarmVideo::STATUS_PENDING, $video->refresh()->status);
        $this->assertNull($video->published_at);
        $this->assertNull($video->approved_at);
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'Váza ve spirále', 'description' => 'Popis', 'when' => 'now'])->assertRedirect();
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $video->refresh()->status);

        // the customer sees the link, then takes the consent back → deleted on YouTube
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee('watch?v=vid123', false);
        $this->actingAs($this->user)->post("/farm/orders/{$order->token}/video-consent", ['consent' => 0])->assertRedirect();
        $this->assertFalse($order->refresh()->video_consent);
        $this->assertSame(FarmVideo::STATUS_WITHDRAWN, $video->refresh()->status);
        $this->assertNull($video->youtube_id);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'id=vid123'));
    }

    public function test_without_consent_nothing_goes_to_youtube_until_the_customer_allows_it(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle();
        $order = $this->paidOrder(consent: false);
        $this->filmed($order);

        $this->assertNull(app(FarmVideos::class)->queueFor($order));
        $this->assertSame(0, FarmVideo::count());
        Http::assertNothingSent();

        // allowed later from the order page: waits for the admin at once, because the time-lapse already exists
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee(__('youtube.order.agree'));
        $this->actingAs($this->user)->post("/farm/orders/{$order->token}/video-consent", ['consent' => 1])->assertRedirect();
        $this->assertSame(FarmVideo::STATUS_PENDING, $order->video()->first()->status);
        Http::assertNothingSent();
    }

    public function test_the_consent_box_starts_ticked_except_for_models_from_photos(): void
    {
        $order = $this->paidOrder(consent: false);
        $order->forceFill(['status' => FarmOrder::STATUS_SLICED])->save();
        $page = fn () => $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="farm-video-consent"[^>]*checked/', $page());
        $order->modelFile->forceFill(['origin' => 'generated'])->save();
        $this->assertDoesNotMatchRegularExpression('/id="farm-video-consent"[^>]*checked/', $page());
    }

    public function test_statistics_come_from_youtube_and_a_video_made_public_in_studio_counts_as_published(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle(privacy: 'private');   // YouTube keeps the approved video private (unaudited project)
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();
        app(FarmVideos::class)->publish($video, 'T', '', $this->admin->id, now: true);
        $this->assertSame(FarmVideo::STATUS_UPLOADED, $video->refresh()->status);

        $this->artisan('youtube:stats')->assertSuccessful();
        $video->refresh();
        $this->assertSame([1520, 87, 4], [$video->views, $video->likes, $video->comments]);
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $video->status, 'made public by hand in YouTube Studio');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'GET' && str_contains($r->url(), 'part=statistics%2Cstatus') && str_contains($r->url(), 'vid123'));

        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('1 520')->assertSee('Nejsledovanější');
        $this->actingAs($this->admin)->post('/admin/youtube/stats')->assertRedirect()->assertSessionHas('status');
    }

    public function test_the_title_names_what_was_printed(): void
    {
        $order = $this->paidOrder(consent: true);
        $videos = app(FarmVideos::class);
        $name = function (string $file, string $origin = 'upload', ?string $ref = null) use ($order, $videos) {
            $order->modelFile->forceFill(['original_name' => $file, 'origin' => $origin, 'origin_ref' => $ref])->save();

            return $videos->modelName($order->refresh(), 'cs');
        };
        $this->assertSame('Louskacek', $name('louskacek (1).stl'));
        $this->assertSame('Greek godess statue', $name('greek-godess-statue_0.16.3mf'));
        $this->assertSame('Felpa', $name('felpa100mm.stl'));
        $this->assertSame('Desk organizer', $name('desk-organizer-v3_desk-organizer-v3_body1.stl'));
        $this->assertSame(__('tools.holder.title', [], 'cs'), $name('holder-52x35x84.stl', 'tool', 'holder'));
        $this->assertSame(__('tools.figure.title', [], 'cs'), $name('bust.stl', 'generated', 'abc'));
        $this->assertNull($name('part.stl'));
        $this->assertStringStartsWith('Louskacek – ', (function () use ($name, $order, $videos) {
            $name('louskacek.stl');

            return $videos->defaultTitle($order->refresh());
        })());
    }

    public function test_a_showcase_print_costs_nothing_goes_to_the_queue_and_counts_for_youtube(): void
    {
        $printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        [$agent] = FarmAgent::issue('test agent');
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id, 'enabled' => true, 'timelapse' => ['mode' => 'consent', 'park_x' => 250, 'park_y' => 250]]);
        $slot = $printer->slots()->whereNotNull('farm_color_id')->firstOrFail();

        $path = sys_get_temp_dir().'/mp_show_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 30.0);
        $uuid = $this->actingAs($this->admin)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'gear-reducer_v2.stl', null, null, true)])->assertCreated()->json('file.uuid');

        $this->actingAs($this->admin)->post('/admin/youtube/showcase', ['model' => 'nonsense', 'slot' => $slot->id, 'quality' => 'fine'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post('/admin/youtube/showcase', ['model' => $uuid, 'slot' => $slot->id, 'quality' => 'fine'])->assertSessionHas('status');

        $order = FarmOrder::where('kind', FarmOrder::KIND_SHOWCASE)->firstOrFail();
        $this->assertSame('U'.now()->format('y').'-000001', $order->number);
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status, 'no payment: straight to the queue');
        $this->assertNull($order->price_total);
        $this->assertTrue($order->video_consent);
        $this->assertNotNull($printer->refresh()->timelapseFor($order), 'the head parks for the showcase');
        $this->assertSame('Gear reducer', app(FarmVideos::class)->modelName($order, 'cs'));
        $this->actingAs($this->user)->post('/admin/youtube/showcase', ['model' => $uuid, 'slot' => $slot->id, 'quality' => 'fine'])->assertRedirect();
        $this->assertSame(1, FarmOrder::where('kind', FarmOrder::KIND_SHOWCASE)->count(), 'admins only');
    }

    public function test_the_score_ranks_long_detailed_prints_above_simple_ones(): void
    {
        $order = $this->paidOrder(consent: true);
        $videos = app(FarmVideos::class);
        $layer = fn (int $moves) => ';LAYER_CHANGE
'.str_repeat('G1 X1 Y1 E.01
', $moves);
        Storage::disk('farm')->put($order->gcode_path, str_repeat($layer(40), 30));        // a flat simple part
        $order->forceFill(['est_minutes' => 25])->save();
        $simple = $videos->score($order);
        Storage::disk('farm')->put($order->gcode_path, str_repeat($layer(1500), 400));     // a tall busy one
        $order->forceFill(['est_minutes' => 480])->save();
        $complex = $videos->score($order);
        $this->assertLessThan(20, $simple);
        $this->assertGreaterThan(90, $complex);
        $this->assertLessThanOrEqual(100, $complex);
    }

    public function test_the_short_and_the_wide_wait_side_by_side_carry_their_own_campaign_and_go_up_as_they_were_built(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle();
        $dir = sys_get_temp_dir().'/mp_music_'.uniqid();
        mkdir($dir);
        touch($dir.'/Artist - Song.mp3');
        config(['youtube.music_dir' => $dir]);
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);
        foreach (['short', 'wide'] as $name) {
            Storage::disk('farm')->put($order->dir()."/{$name}.mp4", str_repeat("\0", 2048));
        }
        $order->forceFill(['timelapse_short_path' => $order->dir().'/short.mp4', 'timelapse_wide_path' => $order->dir().'/wide.mp4'])->save();

        $short = app(FarmVideos::class)->queueFor($order)->refresh();
        $wide = $order->videos()->where('kind', FarmVideo::KIND_WIDE)->firstOrFail();
        $this->assertSame([FarmVideo::KIND_SHORT, FarmVideo::STATUS_PENDING, FarmVideo::STATUS_PENDING], [$short->kind, $short->status, $wide->status]);
        $this->assertSame(['Artist - Song.mp3', 'Artist - Song.mp3'], [$short->music, $wide->music], 'one track for both versions');
        $this->assertStringContainsString('utm_campaign=video_short', (string) $short->description);
        $this->assertStringContainsString('#Shorts', (string) $short->description);
        $this->assertStringContainsString('utm_campaign=video_wide', (string) $wide->description);
        $this->assertStringNotContainsString('#Shorts', (string) $wide->description);
        $this->assertCount(1, Mail::queued(FarmAdminAlert::class, fn ($m) => str_contains($m->subjectLine, 'Video ke schválení')), 'one mail for the print, not one per version');

        // side by side on the approval page, one button each; the page and Instagram only for the Short
        $page = $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Short (čtverec)')->assertSee('Klasické video 16:9');
        $this->assertSame(2, substr_count($page->getContent(), 'Nahrát na YouTube a zveřejnit'));
        $this->actingAs($this->admin)->get("/admin/youtube/videos/{$wide->id}/video.mp4")->assertOk()->assertHeader('Content-Type', 'video/mp4');

        // the files carry their music already: the upload sends them as they are, ffmpeg does not run
        Process::fake();
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$wide->id}/publish", ['title' => 'Široké', 'description' => 'Popis', 'when' => 'now'])->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$short->id}/publish", ['title' => 'Short', 'description' => 'Popis', 'when' => 'now'])->assertRedirect();
        Process::assertNothingRan();
        $this->assertSame([FarmVideo::STATUS_PUBLISHED, FarmVideo::STATUS_PUBLISHED], [$wide->refresh()->status, $short->refresh()->status]);
        $this->assertCount(2, Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=resumable')));

        // the customer sees both; taking the consent back removes both
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee(__('youtube.order.watch_wide'));
        $this->actingAs($this->user)->post("/farm/orders/{$order->token}/video-consent", ['consent' => 0])->assertRedirect();
        $this->assertSame([FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_WITHDRAWN], [$short->refresh()->status, $wide->refresh()->status]);
        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    public function test_rows_from_before_the_kinds_are_shorts_and_the_summary_counts_by_kind(): void
    {
        $this->connectedChannel();
        $order = $this->paidOrder(consent: true);
        DB::table('farm_videos')->insert(['farm_order_id' => $order->id, 'status' => 'published', 'title' => 'Staré', 'youtube_id' => 'old1', 'views' => 900, 'likes' => 40,
            'published_at' => now()->subDays(20), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(FarmVideo::KIND_SHORT, $order->video()->firstOrFail()->kind, 'an old row is a Short');
        FarmVideo::create(['farm_order_id' => $order->id, 'kind' => 'wide', 'status' => 'published', 'title' => 'Nové', 'youtube_id' => 'new1', 'views' => 120, 'likes' => 7, 'published_at' => now()->subDays(2)]);
        FarmVideo::create(['kind' => 'weekly', 'status' => 'published', 'title' => 'Týden', 'youtube_id' => 'wk1', 'views' => 55, 'likes' => 3, 'published_at' => now()->subDays(40), 'path' => 'weekly/2026-W38.mp4', 'meta' => ['week' => '2026-W38', 'orders' => [$order->id]]]);

        $page = $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Podle druhu videa')->getContent();
        $row = fn (string $name) => preg_match('/'.preg_quote($name, '/').'<\/td>(.*?)<\/tr>/s', $page, $m) ? preg_replace('/\s+/', ' ', strip_tags($m[1])) : '';
        $this->assertSame(' 0 vid. · 0 zhl. · 0 lajků 1 vid. · 900 zhl. · 40 lajků 1 vid. · 900 zhl. · 40 lajků ', $row('Short (čtverec)'));
        $this->assertSame(' 1 vid. · 120 zhl. · 7 lajků 1 vid. · 120 zhl. · 7 lajků 1 vid. · 120 zhl. · 7 lajků ', $row('Klasické video 16:9'));
        $this->assertSame(' 0 vid. · 0 zhl. · 0 lajků 0 vid. · 0 zhl. · 0 lajků 1 vid. · 55 zhl. · 3 lajků ', $row('Týdenní sestřih'));
    }

    public function test_a_weekly_cut_goes_up_only_while_every_customer_in_it_agrees(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle();
        $a = $this->paidOrder(consent: true);
        $b = $this->paidOrder(consent: true);
        Storage::disk('farm')->put('weekly/2026-W42.mp4', str_repeat("\0", 2048));
        $videos = app(FarmVideos::class);
        $video = $videos->queueWeekly('2026-W42', 'weekly/2026-W42.mp4', [$a->id, $b->id], 'Tento týden na farmě', "0:00 A\n0:13 B\n0:24 C", null);
        $this->assertSame([FarmVideo::KIND_WEEKLY, null], [$video->kind, $video->farm_order_id]);
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Týdenní sestřih')->assertSee('Týden 2026-W42');

        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'Tento týden na farmě', 'description' => 'Kapitoly', 'when' => 'now'])->assertRedirect();
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $video->refresh()->status);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=resumable') && $r['snippet']['title'] === 'Tento týden na farmě');
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Týdenní sestřih');

        // a second week, and one customer changes their mind before the approval: it does not go up
        Storage::disk('farm')->put('weekly/2026-W43.mp4', str_repeat("\0", 2048));
        $next = $videos->queueWeekly('2026-W43', 'weekly/2026-W43.mp4', [$a->id, $b->id], 'Další týden', '', null);
        $this->actingAs($this->user)->post("/farm/orders/{$b->token}/video-consent", ['consent' => 0])->assertRedirect();
        $this->assertStringContainsString('odvolal souhlas', (string) $next->refresh()->error);
        $this->assertStringContainsString('odvolal souhlas', (string) $video->refresh()->error, 'the published week is marked for the admin too');
        $videos->publish($next, 'Další týden', '', $this->admin->id, now: true);
        $this->assertSame(FarmVideo::STATUS_WITHDRAWN, $next->refresh()->status);
    }

    public function test_somebody_else_cannot_change_the_consent(): void
    {
        $order = $this->paidOrder(consent: false);
        $other = User::factory()->create();
        $this->actingAs($other)->post("/farm/orders/{$order->token}/video-consent", ['consent' => 1])->assertNotFound();
        $this->assertFalse($order->refresh()->video_consent);
    }

    public function test_quota_error_keeps_the_video_queued_for_a_later_try(): void
    {
        $this->connectedChannel();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'expires_in' => 3600]),
            'www.googleapis.com/upload/*' => Http::response(['error' => ['code' => 403, 'message' => 'quota', 'errors' => [['reason' => 'quotaExceeded']]]], 403),
        ]);
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);

        Queue::fake();
        $video = app(FarmVideos::class)->queueFor($order);
        app(FarmVideos::class)->publish($video->refresh(), 'T', '', $this->admin->id);
        Queue::assertPushed(UploadFarmVideo::class, fn ($job) => $job->delay === null);
        app(FarmVideos::class)->upload($video->refresh());

        $this->assertSame(FarmVideo::STATUS_QUEUED, $video->refresh()->status);
        $this->assertNotNull($video->approved_at, 'the approval holds through the wait');
        $this->assertStringContainsString('quota', (string) $video->error);
        $this->assertStringContainsString('zkusíme to znovu '.now()->addMinutes(360)->timezone('Europe/Prague')->format('j. n. H:i'), (string) $video->error, 'the admin reads when the next try is');
        Queue::assertPushed(UploadFarmVideo::class, fn ($job) => $job->delay !== null);

        // "try again" by hand keeps the approval; an unapproved one would go back to waiting for the admin
        app(FarmVideos::class)->retry($video->refresh());
        $this->assertSame(FarmVideo::STATUS_QUEUED, $video->refresh()->status);
        $video->update(['approved_at' => null, 'status' => FarmVideo::STATUS_FAILED]);
        app(FarmVideos::class)->retry($video->refresh());
        $this->assertSame(FarmVideo::STATUS_PENDING, $video->refresh()->status);
    }

    public function test_an_approved_video_takes_the_next_free_evening_slot_and_youtube_makes_it_public(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00', 'Europe/Prague'));   // a Monday morning
        $this->connectedChannel();
        $this->fakeGoogle();
        $videos = app(FarmVideos::class);
        $this->assertSame('2026-10-05 16:00:00', $videos->nextSlot()->toDateTimeString(), '18:00 Prague in UTC');

        $a = $this->paidOrder(consent: true);
        $this->filmed($a);
        $first = $videos->queueFor($a)->refresh();
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Po 5. 10. 18:00');
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$first->id}/publish", ['title' => 'První', 'description' => ''])->assertRedirect()->assertSessionHas('status');
        $first->refresh();
        $this->assertSame([FarmVideo::STATUS_SCHEDULED, 'vid123', '2026-10-05 16:00:00'], [$first->status, $first->youtube_id, $first->scheduled_at->toDateTimeString()]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=resumable') && $r['status']['privacyStatus'] === 'private' && $r['status']['publishAt'] === '2026-10-05T16:00:00Z');
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), 'youtube/v3/videos?part=snippet,status'));

        // the next approval the same day goes to the next day: one video a day
        $b = $this->paidOrder(consent: true);
        $this->filmed($b);
        $second = $videos->queueFor($b)->refresh();
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$second->id}/publish", ['title' => 'Druhé', 'description' => ''])->assertRedirect();
        $this->assertSame('2026-10-06 16:00:00', $second->refresh()->scheduled_at->toDateTimeString());
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Naplánováno na Po 5. 10. 18:00')->assertSee('Naplánováno na Út 6. 10. 18:00')->assertSee('Zveřejnit hned');
        $this->actingAs($this->user)->get("/farm/orders/{$a->token}")->assertOk()->assertDontSee('watch?v=vid123', false);

        // the evening came, YouTube flipped the first one: the hourly stats run writes it down with the planned time
        $this->travelTo(Carbon::parse('2026-10-05 18:30', 'Europe/Prague'));
        $this->artisan('youtube:stats')->assertSuccessful();
        $first->refresh();
        $this->assertSame([FarmVideo::STATUS_PUBLISHED, '2026-10-05 16:00:00'], [$first->status, $first->published_at->toDateTimeString()]);
        $this->actingAs($this->user)->get("/farm/orders/{$a->token}")->assertOk()->assertSee('watch?v=vid123', false);

        // "now" on a scheduled one: public at once; that day is full, so the next slot is the day after
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$second->id}/publish", ['title' => 'Druhé', 'description' => '', 'when' => 'now'])->assertRedirect()->assertSessionHas('status', 'Video je zveřejněné.');
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $second->refresh()->status);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), 'youtube/v3/videos?part=snippet,status') && $r['status']['privacyStatus'] === 'public' && ! isset($r['status']['publishAt']));
        $this->assertSame('2026-10-06 16:00:00', $videos->nextSlot()->toDateTimeString());
    }

    public function test_a_public_video_goes_to_the_facebook_page_and_instagram_when_the_admin_ticked_them(): void
    {
        FakeMetaClient::reset();
        $this->connectedChannel();
        $this->fakeGoogle();
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Facebook stránka (video)')->assertSee('Instagram (Reel)');

        // approved for YouTube now, Facebook and Instagram ticked: they follow once the video is public
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'Váza', 'description' => 'Popis', 'when' => 'now', 'share_choice' => 1, 'share' => ['facebook', 'facebook_link', 'instagram']])->assertRedirect();
        $this->assertSame([FarmVideo::STATUS_PUBLISHED, ['facebook', 'facebook_link', 'instagram']], [$video->refresh()->status, $video->share]);
        $this->assertSame([], FakeMetaClient::$posts, 'nothing before the scheduler runs');

        $this->artisan('social:videos')->expectsOutputToContain('posted: 3')->assertSuccessful();
        $this->assertSame(['facebook_video', 'facebook', 'instagram_reel'], array_column(FakeMetaClient::$posts, 'platform'));
        $this->assertStringEndsWith('timelapse.mp4', FakeMetaClient::$posts[0]['image'], 'the page gets the file itself');
        $this->assertStringContainsString("Váza\n\nPopis", FakeMetaClient::$posts[0]['message']);
        $this->assertSame(url('/').'/?utm_source=facebook&utm_medium=social&utm_campaign=video_short', FakeMetaClient::$posts[1]['link'], 'the link post points where YouTube does');
        $reelUrl = FakeMetaClient::$posts[2]['image'];
        $this->assertStringContainsString("/social/videos/{$video->id}.mp4?", $reelUrl, 'Instagram fetches the video from a signed address');
        $this->get($reelUrl)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get("/social/videos/{$video->id}.mp4")->assertForbidden();
        $posts = SocialPost::where('subject_type', 'farm_video')->where('subject_id', $video->id)->orderBy('id')->get();
        $this->assertSame(['facebook' => 'posted', 'facebook_link' => 'posted', 'instagram' => 'posted'], $posts->pluck('status', 'platform')->all());
        $this->assertSame(['facebook_video_1', 'facebook_2', 'instagram_reel_3'], $posts->pluck('external_id')->all());
        $this->artisan('social:videos')->expectsOutputToContain('posted: 0');   // done is done
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Facebook ✓')->assertSee('FB odkaz ✓')->assertSee('Instagram ✓');

        // a refusal is kept for the admin; "try again" asks once more at the next run
        $other = $this->paidOrder(consent: true);
        $this->filmed($other);
        $second = app(FarmVideos::class)->queueFor($other)->refresh();
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$second->id}/publish", ['title' => 'Druhé', 'description' => '', 'when' => 'now', 'share_choice' => 1, 'share' => ['facebook']])->assertRedirect();
        FakeMetaClient::$refuse = 'Page token expired';
        $this->artisan('social:videos')->expectsOutputToContain('posted: 0');
        $this->assertSame('Page token expired', SocialPost::where('subject_id', $second->id)->value('error'));
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Facebook ✗ Page token expired')->assertSee('zkusit znovu');
        FakeMetaClient::$refuse = null;
        $this->artisan('social:videos')->expectsOutputToContain('posted: 0', 'a failure is not retried by itself');
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$second->id}/share/facebook/retry")->assertRedirect();
        $this->artisan('social:videos')->expectsOutputToContain('posted: 1');
        $this->assertSame('posted', SocialPost::where('subject_id', $second->id)->value('status'));

        // nothing ticked: nothing goes anywhere
        $third = $this->paidOrder(consent: true);
        $this->filmed($third);
        $v3 = app(FarmVideos::class)->queueFor($third)->refresh();
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$v3->id}/publish", ['title' => 'Třetí', 'description' => '', 'when' => 'now', 'share_choice' => 1])->assertRedirect();
        $this->assertSame([], $v3->refresh()->share);
        $this->artisan('social:videos')->expectsOutputToContain('posted: 0');
    }

    public function test_every_kind_and_channel_leads_to_the_tool_or_the_home_page_from_one_place(): void
    {
        $order = $this->paidOrder(consent: true);
        $videos = app(FarmVideos::class);
        $home = url('/').'/';
        $tag = fn (string $source, string $kind) => '?utm_source='.$source.'&utm_medium=social&utm_campaign=video_'.$kind;

        // a customer's own file: the home page (the calculator) everywhere
        foreach (['youtube', 'facebook', 'instagram'] as $source) {
            foreach (['short', 'wide', 'weekly'] as $kind) {
                $this->assertSame($home.$tag($source, $kind), $videos->link($order, $kind, $source));
            }
        }
        // a model from a tool: the tool's page for the print's own videos, the weekly cut keeps the home page
        $order->modelFile->forceFill(['origin' => 'tool', 'origin_ref' => 'lithophane'])->save();
        $order->refresh();
        $short = FarmVideo::create(['title' => 'V', 'farm_order_id' => $order->id, 'kind' => FarmVideo::KIND_SHORT, 'status' => FarmVideo::STATUS_PENDING]);
        $weekly = FarmVideo::create(['title' => 'T', 'kind' => FarmVideo::KIND_WEEKLY, 'status' => FarmVideo::STATUS_PENDING, 'meta' => ['week' => '2026-W42', 'orders' => [$order->id]]]);
        $howto = FarmVideo::create(['title' => 'N', 'kind' => FarmVideo::KIND_HOWTO, 'status' => FarmVideo::STATUS_PENDING, 'meta' => ['name' => 'x', 'variant' => 'short', 'link' => 'https://matplace.com/']]);
        $this->assertSame(url('/tools/relief').$tag('instagram', 'short'), $videos->linkOf($short, 'instagram'), 'lithophane and relief share the relief tool');
        $this->assertSame($home.$tag('facebook', 'weekly'), $videos->linkOf($weekly, 'facebook'));
        $this->assertSame('https://matplace.com/'.$tag('youtube', 'howto'), $videos->linkOf($howto));
        $this->assertSame(rtrim((string) preg_replace('#^https?://#', '', url('/tools/relief')), '/'), $videos->address($order), 'the end card names the page the link opens');

        // a YouTube text for another channel: the same pages, that channel's mark
        $this->assertSame('Vlastní: '.url('/tools/relief').$tag('facebook', 'short'), $videos->forChannel('Vlastní: '.url('/tools/relief').$tag('youtube', 'short'), 'facebook'));
    }

    public function test_the_link_is_in_the_first_two_lines_of_every_description(): void
    {
        $order = $this->paidOrder(consent: true);
        $order->forceFill(['timelapse_short_path' => $order->dir().'/short.mp4'])->save();
        $videos = app(FarmVideos::class);
        $firstTwo = fn (string $text) => implode("\n", array_slice(explode("\n", $text), 0, 2));

        foreach (['cs', 'en', 'es'] as $locale) {
            config(['youtube.language' => $locale]);
            foreach (['short', 'wide'] as $kind) {
                $text = $videos->defaultDescription($order, 'Artist - Song.mp3', $kind);
                $this->assertStringContainsString(url('/').'/?utm_source=youtube&utm_medium=social&utm_campaign=video_'.$kind, $firstTwo($text), "$locale $kind");
                $this->assertStringNotContainsString(':cta', $text);
            }
            $weekly = $videos->weeklyDescription('12.–18. 10.', [[0.0, 'Váza', null]], null);
            $this->assertStringContainsString('utm_campaign=video_weekly', $firstTwo($weekly), "$locale weekly");
            $this->assertStringContainsString('0:00 Váza', $weekly, 'the chapters stay');
        }
        config(['youtube.language' => 'cs']);
        $short = $videos->defaultDescription($order, 'Artist - Song.mp3', 'short');
        $this->assertStringStartsWith('Nahrajte model a hned uvidíte cenu: http', $short);
        $this->assertStringEndsWith("Hudba: Artist – Song (YouTube Audio Library)\n\n#Shorts #3Dprinting #timelapse", $short, 'the music credit, then the hashtags last');
        $order->modelFile->forceFill(['origin' => 'tool', 'origin_ref' => 'vase'])->save();
        $this->assertStringStartsWith('Udělejte si vlastní: '.url('/tools/vase').'?utm_source=youtube', $videos->defaultDescription($order->refresh(), null, 'wide'));
    }

    public function test_the_facebook_and_instagram_texts_carry_their_own_mark_to_the_same_page(): void
    {
        FakeMetaClient::reset();
        $this->connectedChannel();
        $this->fakeGoogle();
        $order = $this->paidOrder(consent: true);
        $order->modelFile->forceFill(['origin' => 'tool', 'origin_ref' => 'vase'])->save();
        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'Váza', 'description' => (string) $video->description, 'when' => 'now', 'share_choice' => 1, 'share' => ['facebook', 'facebook_link', 'instagram']])->assertRedirect();
        $this->artisan('social:videos')->expectsOutputToContain('posted: 3')->assertSuccessful();

        $page = url('/tools/vase').'?utm_source=';
        $this->assertStringContainsString($page.'facebook&utm_medium=social&utm_campaign=video_short', FakeMetaClient::$posts[0]['message']);
        $this->assertSame($page.'facebook&utm_medium=social&utm_campaign=video_short', FakeMetaClient::$posts[1]['link']);
        $this->assertStringContainsString($page.'instagram&utm_medium=social&utm_campaign=video_short', FakeMetaClient::$posts[2]['message']);
        $this->assertStringNotContainsString('utm_source=youtube', implode(' ', array_column(FakeMetaClient::$posts, 'message')));
        $this->assertStringContainsString('utm_source=youtube', (string) $video->refresh()->description, 'YouTube keeps its own');
    }

    public function test_the_link_comment_waits_behind_its_switch_and_its_scope(): void
    {
        $this->connectedChannel();
        $client = app(YouTubeClient::class);
        $this->assertStringNotContainsString('force-ssl', urldecode($client->authUrl('https://x/cb', 's')), 'today: the scope the channel was connected with');

        // off (the default): a public video gets no comment, Google is not asked
        $this->fakeGoogle();
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'Váza', 'description' => 'Popis', 'when' => 'now'])->assertRedirect();
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $video->refresh()->status);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'commentThreads'));

        // on: connecting asks for youtube.force-ssl too; a video that turns public gets the call with the link, once
        config(['youtube.pin_comment' => true]);
        $this->assertStringContainsString('https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/youtube.force-ssl', urldecode($client->authUrl('https://x/cb', 's')));
        $video->update(['status' => FarmVideo::STATUS_UPLOADED]);
        app(FarmVideos::class)->refreshStats();   // made public in Studio
        app(FarmVideos::class)->commentLink($video->refresh());
        $comments = fn () => Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'commentThreads'));
        $this->assertCount(1, $comments());
        [$request] = $comments()->first();
        $this->assertStringEndsWith('/commentThreads?part=snippet', $request->url());
        $this->assertSame(['channelId' => 'UC123', 'videoId' => 'vid123', 'topLevelComment' => ['snippet' => ['textOriginal' => 'Nahrajte model a hned uvidíte cenu: '.url('/').'/?utm_source=youtube&utm_medium=social&utm_campaign=video_short']]], $request['snippet']);
        $this->assertSame([FarmVideo::STATUS_PUBLISHED, 'thread-1'], [$video->status, $video->meta['comment_id']]);

        // a token without the scope: the video stays as it is, the log says why
        $this->commentStatus = 403;
        $other = FarmVideo::create(['title' => 'W', 'farm_order_id' => $order->id, 'kind' => FarmVideo::KIND_WIDE, 'status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => 'vid456']);
        app(FarmVideos::class)->commentLink($other);
        $this->assertCount(2, $comments());
        $this->assertNull($other->refresh()->meta);
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $other->status);
    }

    public function test_an_unaudited_project_leaves_the_video_private_and_the_admin_is_told(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle(privacy: 'private');
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();

        // approved: uploaded, but YouTube keeps it private; the admin reads why on the page and can publish it again from there
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'T', 'when' => 'now'])->assertRedirect();
        $this->assertSame(FarmVideo::STATUS_UPLOADED, $video->refresh()->status);
        $this->assertStringContainsString('private', (string) $video->error);
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Zveřejnit na YouTube')->assertSee('private');
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'T', 'when' => 'now'])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_connecting_the_channel_keeps_the_refresh_token_encrypted(): void
    {
        $this->fakeGoogle();
        $go = $this->actingAs($this->admin)->post('/admin/youtube/connect');
        $go->assertRedirect();
        parse_str((string) parse_url($go->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('https://www.googleapis.com/auth/youtube', $q['scope']);
        $this->assertSame('offline', $q['access_type']);

        // a forged state is refused
        $this->actingAs($this->admin)->get('/admin/youtube/callback?code=x&state=wrong')->assertRedirect('/admin/youtube')->assertSessionHas('error');
        $this->assertSame(0, YouTubeAccount::count());

        $this->actingAs($this->admin)->post('/admin/youtube/connect');
        $state = session('youtube_oauth_state');
        $this->actingAs($this->admin)->get('/admin/youtube/callback?code=abc&state='.$state)->assertRedirect('/admin/youtube')->assertSessionHas('status');

        $account = YouTubeAccount::current();
        $this->assertSame('UC123', $account->channel_id);
        $this->assertSame('refresh-1', $account->refresh_token);
        $this->assertStringNotContainsString('refresh-1', (string) DB::table('youtube_accounts')->value('refresh_token'));
    }

    public function test_privacy_page_explains_the_youtube_use_and_every_page_links_it(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('YouTube API Services')
            ->assertSee('https://policies.google.com/privacy', false)
            ->assertSee('https://www.youtube.com/t/terms', false)
            ->assertSee('https://myaccount.google.com/permissions', false);
        $this->get('/farm/terms')->assertOk()->assertSee(route('privacy'), false)->assertSee(config('youtube.channel_url'), false);
    }

    public function test_only_admins_reach_the_video_page(): void
    {
        $this->actingAs($this->user)->get('/admin/youtube')->assertRedirect();
        $this->actingAs($this->user)->post('/admin/youtube/connect')->assertRedirect();
        $this->assertNull(session('youtube_oauth_state'));
    }

    public function test_the_music_credit_names_the_author_of_a_library_track_and_of_a_pixabay_track(): void
    {
        $this->assertSame('Sly Sky – Telecasted (YouTube Audio Library)', FarmVideos::musicCredit('Sly Sky - Telecasted.mp3'));
        $this->assertSame('Pop Party – alex_makemusic (Pixabay)', FarmVideos::musicCredit('/srv/music/alex_makemusic-pop-party-232332.mp3'));
        $this->assertSame('Melodic Techno 03 Extended Version Moogify – taureon (Pixabay)', FarmVideos::musicCredit('taureon-melodic-techno-03-extended-version-moogify-9867.mp3'));
        $this->assertSame('nameless', FarmVideos::musicCredit('nameless.mp3'));
    }

    public function test_a_track_does_not_come_again_before_the_others_had_their_turn(): void
    {
        $dir = sys_get_temp_dir().'/mp_music_'.uniqid();
        mkdir($dir);
        foreach (['A - One.mp3', 'B - Two.mp3', 'C - Three.mp3'] as $f) {
            touch($dir.'/'.$f);
        }
        config(['youtube.music_dir' => $dir]);
        $videos = app(FarmVideos::class);
        $picked = [];
        for ($i = 0; $i < 3; $i++) {
            $order = $this->paidOrder(consent: true);
            $picked[] = $track = $videos->pickMusic($order);
            FarmVideo::forceCreate(['farm_order_id' => $order->id, 'status' => FarmVideo::STATUS_PENDING, 'title' => 'T', 'music' => $track]);
        }
        // three prints in a row, three different tracks (17 Oct 2026: two prints sixteen orders apart shared one of sixteen)
        $this->assertCount(3, array_unique($picked));
        // the fourth starts the round again
        $this->assertContains($videos->pickMusic($this->paidOrder(consent: true)), $picked);
    }
}
