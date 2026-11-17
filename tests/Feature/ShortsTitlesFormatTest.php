<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Stats\Funnel;
use App\Domain\YouTube\ChannelCurator;
use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\TimelapseCutter;
use App\Domain\YouTube\VideoClips;
use App\Domain\YouTube\VideoTitles;
use App\Domain\YouTube\YouTubeError;
use App\Engines\Ai\FakeAssistant;
use App\Models\CatalogModel;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\FarmVideoClip;
use App\Models\User;
use App\Models\YouTubeAccount;
use App\Support\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShortsTitlesFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('farm');
        Storage::fake('models');
        Mail::fake();
        FakeAssistant::reset();
        YouTubeAccount::create(['channel_id' => 'UC1', 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);
        $music = sys_get_temp_dir().'/mp_shorts_'.uniqid();
        mkdir($music);
        touch($music.'/Artist - Song.mp3');
        config(['youtube.music_dir' => $music]);
    }

    private function order(array $attributes = []): FarmOrder
    {
        $order = FarmOrder::factory()->create(['video_consent' => true, 'est_minutes' => 267] + $attributes);
        Storage::disk('farm')->put($order->dir().'/timelapse.mp4', 'video');
        $order->forceFill(['timelapse_path' => $order->dir().'/timelapse.mp4'])->save();

        return $order->refresh();
    }

    private function answer(array $override = []): void
    {
        FakeAssistant::$answers[VideoTitles::KIND] = $override + ['title_cs' => 'Dekorativní váza z 3D tiskárny', 'title_en' => 'Decorative 3D printed vase',
            'description_cs' => 'Váza vznikla na naší 3D tiskové farmě. Prohlédněte si její hotový tvar.', 'subject' => 'váza', 'confidence' => 0.9];
    }

    public function test_visual_title_is_cached_and_low_confidence_waits_for_a_human(): void
    {
        $this->answer();
        $order = $this->order();
        $videos = app(FarmVideos::class);
        $video = $videos->queueFor($order);
        $this->assertSame('Dekorativní váza z 3D tiskárny', $video->title);
        $this->assertSame('váza', $video->subject);
        $this->assertSame('Váza vznikla na naší 3D tiskové farmě. Prohlédněte si její hotový tvar.', explode("\n", $video->description)[2]);
        $this->assertSame('Decorative 3D printed vase', $video->meta['title_en']);
        $this->assertStringNotContainsString('cube', (string) $video->description);
        $videos->defaultTitle($order);
        $this->assertCount(1, array_filter(FakeAssistant::$calls, fn ($call) => $call['kind'] === VideoTitles::KIND));

        $low = $this->order();
        $this->answer(['confidence' => 0.59]);
        $pending = app(FarmVideos::class)->queueFor($low);
        $this->assertSame(FarmVideo::STATUS_PENDING, $pending->status);
        $this->assertTrue($pending->meta['title_needed']);
        $this->assertSame(__('youtube.video.missing_title'), $pending->error);
        $this->expectException(YouTubeError::class);
        app(FarmVideos::class)->publish($pending, $pending->title, $pending->description, 1);
    }

    public function test_tool_and_catalogue_win_over_ai_and_file_names_are_rejected(): void
    {
        $tool = $this->order();
        $tool->modelFile->update(['origin' => 'tool', 'origin_ref' => 'vase']);
        $title = app(FarmVideos::class)->defaultTitle($tool->refresh());
        $this->assertStringStartsWith('Váza', $title);
        $this->assertStringContainsString('4,5 hodiny tisku', $title);
        $this->assertSame([], array_values(array_filter(FakeAssistant::$calls, fn ($call) => $call['kind'] === VideoTitles::KIND)));

        $catalog = CatalogModel::create(['slug' => 'drak', 'title' => 'Čínský drak', 'source' => 'test', 'external_id' => '1']);
        $order = $this->order(['catalog_model_id' => $catalog->id]);
        $video = app(FarmVideos::class)->queueFor($order);
        $this->assertSame('catalog:'.$catalog->id, $video->subject);
        $this->assertStringStartsWith('Čínský drak – ', $video->title);

        $this->answer(['title_cs' => 'cube.stl jako dekorace']);
        $raw = $this->order();
        $this->assertTrue(app(FarmVideos::class)->queueFor($raw)->meta['title_needed']);
        $this->expectException(YouTubeError::class);
        app(FarmVideos::class)->publish($video, 'cube.stl', 'Popis', 1);
    }

    public function test_tests_repeats_and_daily_short_slot_follow_the_new_queue_rules(): void
    {
        $this->answer();
        $test = $this->order(['kind' => FarmOrder::KIND_TEST]);
        $this->assertNull(app(FarmVideos::class)->queueFor($test));
        $allowed = app(FarmVideos::class)->queueFor($test->refresh(), true);
        $this->assertTrue($allowed->meta['allow_test']);
        $this->assertSame(2, app(FarmVideos::class)->queueRank($allowed));

        $ordinary = $this->order();
        $waiting = FarmVideo::factory()->create(['farm_order_id' => $ordinary->id, 'subject' => 'drak']);
        FarmVideo::factory()->published()->create(['farm_order_id' => $this->order()->id, 'subject' => 'drak', 'published_at' => now()->subDays(5)]);
        $this->assertNotNull(app(FarmVideos::class)->repeated($waiting));
        $this->assertSame(2, app(FarmVideos::class)->queueRank($waiting));

        $this->travelTo(Carbon::parse('2026-10-05 10:00', 'Europe/Prague'));
        app(FarmSettings::class)->set('shorts_publish_hour', '06:30');
        $this->assertSame('06:30', app(FarmVideos::class)->nextSlot($waiting)->timezone('Europe/Prague')->format('H:i'));
        $this->assertFalse(config('farm.settings.video_prices'));
    }

    public function test_clip_upload_replacement_bindings_and_missing_list(): void
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $video = FarmVideo::factory()->create(['farm_order_id' => $this->order()->id]);
        $url = "/admin/youtube/videos/{$video->id}/clips/turn";
        $this->actingAs(User::factory()->create())->post($url, ['clip' => UploadedFile::fake()->create('turn.mp4', 10, 'video/mp4')])->assertRedirect();
        $this->assertSame(0, FarmVideoClip::count());

        $this->actingAs($admin)->post($url, ['clip' => UploadedFile::fake()->create('turn.mp4', 10, 'video/mp4')])->assertRedirect()->assertSessionHas('status');
        $clip = FarmVideoClip::firstOrFail();
        $this->assertSame([$video->id, $video->farm_order_id, 'turn'], [$clip->farm_video_id, $clip->farm_order_id, $clip->kind]);
        $old = $clip->path;
        $this->actingAs($admin)->get("/admin/youtube/videos/{$video->id}/clips/turn")->assertOk();
        $this->actingAs($admin)->post($url, ['clip' => UploadedFile::fake()->create('new-turn.mp4', 20, 'video/mp4')])->assertRedirect();
        $this->assertSame(1, FarmVideoClip::count());
        $this->assertFalse(Storage::disk('farm')->exists($old));
        $this->assertSame(['removal', 'detail', 'with_source', 'in_hand'], app(VideoClips::class)->missing($video->refresh()));
        $this->actingAs($admin)->get('/admin/youtube')->assertOk()->assertSee('Natočit')->assertSee('Nahrát soubor');
    }

    public function test_short_plan_has_loop_ordered_clips_original_sound_and_music(): void
    {
        $order = $this->order();
        $video = FarmVideo::factory()->create(['farm_order_id' => $order->id, 'title' => 'Veřejný název']);
        foreach (range(0, 599) as $i) {
            Storage::disk('farm')->put($order->dir()."/frames/{$i}.jpg", 'x');
        }
        foreach (FarmVideoClip::KINDS as $kind) {
            $path = $order->dir()."/clips/{$kind}.mp4";
            Storage::disk('farm')->put($path, $kind);
            $video->clips()->create(['farm_order_id' => $order->id, 'kind' => $kind, 'path' => $path, 'mime' => 'video/mp4', 'size' => 10]);
        }
        $cut = app(TimelapseCutter::class)->short($order);
        $inputs = array_values(array_map(fn ($i) => $cut->inputs[$i + 1], array_keys($cut->inputs, '-i', true)));
        $this->assertStringEndsWith('/turn.mp4', $inputs[0]);
        $this->assertStringEndsWith('/short.txt', $inputs[1]);
        $this->assertStringEndsWith('/removal.mp4', $inputs[2]);
        $this->assertStringEndsWith('/detail.mp4', $inputs[3]);
        $this->assertStringEndsWith('/with_source.mp4', $inputs[4]);
        $this->assertStringEndsWith('Artist - Song.mp4', str_replace('.mp3', '.mp4', (string) end($inputs)));
        $this->assertStringNotContainsString('in_hand.mp4', implode(' ', $cut->inputs), 'with_source wins over in_hand');
        $this->assertLessThan(strpos($cut->graph, '[growth]'), strpos($cut->graph, '[opening]'));
        $this->assertLessThan(strpos($cut->graph, '[removal]'), strpos($cut->graph, '[growth]'));
        $this->assertStringContainsString('[music][removal_sound]amix=', $cut->graph);
        $this->assertStringContainsString('adelay=', $cut->graph);
        $this->assertSame('matplace.com', $cut->texts[array_search('matplace.com', $cut->texts, true)]);
        $this->assertContains('Veřejný název', $cut->texts);
        $this->assertGreaterThanOrEqual(8, $cut->seconds);
        $this->assertLessThanOrEqual(15, $cut->seconds);

        $fallback = $this->order();
        foreach (range(0, 99) as $i) {
            Storage::disk('farm')->put($fallback->dir()."/frames/{$i}.jpg", 'x');
        }
        $plain = app(TimelapseCutter::class)->short($fallback);
        $this->assertStringEndsWith('/99.jpg', $plain->inputs[array_keys($plain->inputs, '-i', true)[0] + 1]);
        $this->assertStringNotContainsString('removal_sound', $plain->graph);
        $this->assertGreaterThanOrEqual(8, $plain->seconds);
    }

    public function test_analytics_utm_audit_funnel_and_retitles_csv(): void
    {
        $video = FarmVideo::factory()->published()->create(['farm_order_id' => $this->order()->id, 'youtube_id' => 'vid1']);
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), 'engagedViews')) {
                return Http::response(['columnHeaders' => array_map(fn ($name) => ['name' => $name], ['video', 'views', 'averageViewPercentage', 'averageViewDuration', 'engagedViews']), 'rows' => [['vid1', 100, 62.5, 8.2, 70]]]);
            }

            return Http::response(['rows' => [['vid1', 'YT_SEARCH', 30], ['vid1', 'SHORTS', 70]]]);
        });
        $this->artisan('youtube:analytics')->assertSuccessful();
        $analytics = $video->refresh()->analytics;
        $this->assertSame([100, 62.5, 70, 0.7, 0.3], [$analytics['views'], $analytics['averageViewPercentage'], $analytics['engagedViews'], $analytics['engagedRatio'], $analytics['skippedRatio']]);
        $this->assertSame(['YT_SEARCH' => 30, 'SHORTS' => 70], $analytics['trafficSources']);
        $this->assertStringContainsString('engaged 70 %', app(ChannelCurator::class)->rows()[0]['analytics']);

        $request = Request::create('/?utm_source=youtube&utm_medium=shorts&utm_campaign=short&utm_content=vid1');
        $request->setLaravelSession(app('session')->driver());
        $origin = Track::origin($request);
        $this->assertSame(['youtube', 'vid1'], [$origin['source'], $origin['utm']['utm_content']]);
        foreach ([['visit', ['js' => true]], ['intake_submitted', []], ['order_started', []], ['order_paid', []]] as [$type, $meta]) {
            Event::create(['session_id' => 1, 'source' => 'youtube', 'type' => $type, 'meta' => $meta]);
        }
        $this->assertSame([1, 1, 1, 1], array_column(app(Funnel::class)->compute(30)['paths']['youtube'], 'sessions'));

        $rows = app(ChannelCurator::class)->readCsv(base_path('docs/data/youtube-titles-2026-11.csv'));
        $this->assertCount(10, $rows);
        $this->assertSame('beze změny', collect($rows)->firstWhere('id', 'sxBdxwG3JPI')['navrh']);
        $this->assertSame('Dekorační koule z 3D tiskárny – 17 hodin tisku', collect($rows)->firstWhere('id', 'i6kDnHotrQI')['novy_titulek']);
        foreach ($rows as $row) {
            $this->assertStringNotContainsString('Snail planter2', $row['novy_titulek'].$row['novy_popis']);
            $this->assertStringNotContainsString('Hogwartstrain', $row['novy_titulek'].$row['novy_popis']);
        }
    }
}
