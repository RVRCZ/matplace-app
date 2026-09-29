<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\YouTube\FarmVideos;
use App\Jobs\UploadFarmVideo;
use App\Mail\FarmAdminAlert;
use App\Models\FarmAgent;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmVideo;
use App\Models\Payment;
use App\Models\User;
use App\Models\YouTubeAccount;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
                return Http::response(['id' => 'vid123', 'status' => ['privacyStatus' => $privacy]]);
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

            return Http::response(['error' => 'unexpected '.$r->method().' '.$url], 500);
        });
    }

    public function test_consented_video_is_uploaded_private_published_by_an_admin_and_removed_when_the_consent_is_withdrawn(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle();
        $order = $this->paidOrder(consent: true);
        $this->assertTrue($order->video_consent);
        $this->assertNotNull($order->video_consent_at);

        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();   // what BuildFarmTimelapse does at its end

        $this->assertSame(FarmVideo::STATUS_UPLOADED, $video->status);
        $this->assertSame('vid123', $video->youtube_id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'uploadType=resumable') && $r['status']['privacyStatus'] === 'private');
        $this->assertStringContainsString('3D tisku', $video->title);      // no readable name in "part.stl": material and colour, then the print time
        Mail::assertQueued(FarmAdminAlert::class, fn ($m) => str_contains($m->subjectLine, 'Video ke schválení') && $m->url === route('admin.youtube.index'));

        // the customer can download the square Short once it exists
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/short.mp4")->assertNotFound();
        Storage::disk('farm')->put($order->dir().'/short.mp4', 'mp4');
        $order->forceFill(['timelapse_short_path' => $order->dir().'/short.mp4'])->save();
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}/short.mp4")->assertOk()->assertDownload('matplace-'.$order->number.'-short.mp4');
        $this->assertStringContainsString('short.mp4', (string) $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json('short_url'));
        $this->actingAs(User::factory()->create())->get("/farm/orders/{$order->token}/short.mp4")->assertNotFound();

        // built again before approval: the private copy is deleted and the new file uploaded
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/replace")->assertRedirect()->assertSessionHas('status');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'id=vid123'));
        $this->assertSame(FarmVideo::STATUS_UPLOADED, $video->refresh()->status);

        // the admin page lists it; publishing uses the edited title
        $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()->assertSee('Zveřejnit na YouTube');
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'Váza ve spirále', 'description' => 'Popis'])->assertRedirect();
        $video->refresh();
        $this->assertSame(FarmVideo::STATUS_PUBLISHED, $video->status);
        $this->assertSame('Váza ve spirále', $video->title);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), 'youtube/v3/videos?part=snippet,status') && $r['status']['privacyStatus'] === 'public');

        // rebuilt videos replace the private copy (only before publishing)
        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/replace")->assertRedirect()->assertSessionHas('error');

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

        // allowed later from the order page: uploaded at once, because the time-lapse already exists
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee(__('youtube.order.agree'));
        $this->actingAs($this->user)->post("/farm/orders/{$order->token}/video-consent", ['consent' => 1])->assertRedirect();
        $this->assertSame(FarmVideo::STATUS_UPLOADED, $order->video()->first()->status);
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
        $this->fakeGoogle();
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();
        $this->assertSame(FarmVideo::STATUS_UPLOADED, $video->status);

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
        app(FarmVideos::class)->upload($video->refresh());

        $this->assertSame(FarmVideo::STATUS_QUEUED, $video->refresh()->status);
        $this->assertStringContainsString('quota', (string) $video->error);
        Queue::assertPushed(UploadFarmVideo::class, fn ($job) => $job->delay !== null);
    }

    public function test_an_unaudited_project_leaves_the_video_private_and_the_admin_is_told(): void
    {
        $this->connectedChannel();
        $this->fakeGoogle(privacy: 'private');
        $order = $this->paidOrder(consent: true);
        $this->filmed($order);
        $video = app(FarmVideos::class)->queueFor($order)->refresh();

        $this->actingAs($this->admin)->post("/admin/youtube/videos/{$video->id}/publish", ['title' => 'T'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(FarmVideo::STATUS_UPLOADED, $video->refresh()->status);
        $this->assertStringContainsString('private', (string) $video->error);
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
}
