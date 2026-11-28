<?php

namespace Tests\Feature;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\StudioCutter;
use App\Domain\YouTube\StudioMedia;
use App\Engines\Repair\ScriptRunner;
use App\Jobs\BuildStudioVideos;
use App\Jobs\UploadFarmVideo;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\FarmVideoClip;
use App\Models\FarmVideoMedia;
use App\Models\User;
use App\Models\YouTubeAccount;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The video studio (docs/AL.md §13.10): the list of print videos, one print's media in the admin's order, both videos
 * planned from them, the texts, and the choice of what goes to YouTube. ffmpeg and python are faked.
 */
class VideoStudioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        Queue::fake();
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
        // python: a picture is "normalised" by copying it; the model drawings fail (the Short goes on without them)
        $this->app->instance(ScriptRunner::class, new class implements ScriptRunner
        {
            public function runScript(string $script, array $args, int $timeout = 90): array
            {
                if ($script === 'photo_tool.py' && $args[0] === 'normalize') {
                    copy($args[1], $args[2]);

                    return ['ok' => true, 'w' => 10, 'h' => 10];
                }

                return ['ok' => false, 'error' => 'no python here'];
            }
        });
        // ffmpeg -i of a clip: four seconds with sound
        Process::fake(fn () => Process::result(errorOutput: "Duration: 00:00:04.20, start: 0\n  Stream #0:1(und): Audio: aac"));
        $music = sys_get_temp_dir().'/mp_studio_music_'.uniqid();
        mkdir($music);
        touch($music.'/Artist - Song.mp3');
        config(['youtube.music_dir' => $music]);
    }

    private function filmedOrder(int $frames = 40): FarmOrder
    {
        $order = FarmOrder::factory()->paid()->done()->create(['video_consent' => true, 'video_consent_at' => now(), 'number' => 'F26-'.random_int(100000, 999999)]);
        for ($i = 0; $i < $frames; $i++) {
            Storage::disk('farm')->put(sprintf('%s/frames/%d.jpg', $order->dir(), 1_790_000_000 + $i * 60), 'x');
        }
        foreach (['timelapse', 'short', 'wide'] as $name) {
            Storage::disk('farm')->put($order->dir()."/{$name}.mp4", str_repeat("\0", 64));
        }
        $order->forceFill(['timelapse_path' => $order->dir().'/timelapse.mp4', 'timelapse_short_path' => $order->dir().'/short.mp4', 'timelapse_wide_path' => $order->dir().'/wide.mp4'])->save();

        return $order;
    }

    public function test_the_list_shows_the_print_videos_with_whether_they_are_published_and_opens_the_studio(): void
    {
        $waiting = $this->filmedOrder();
        FarmVideo::factory()->create(['farm_order_id' => $waiting->id, 'title' => 'Čekající váza']);
        $published = $this->filmedOrder();
        FarmVideo::factory()->published()->create(['farm_order_id' => $published->id, 'title' => 'Slavná soška', 'views' => 1234]);
        FarmVideo::factory()->create(['farm_order_id' => $published->id, 'kind' => FarmVideo::KIND_WIDE, 'title' => 'Slavná soška 16:9']);
        FarmVideo::create(['kind' => FarmVideo::KIND_WEEKLY, 'status' => FarmVideo::STATUS_PENDING, 'title' => 'Týden', 'path' => 'weekly/w.mp4', 'meta' => ['week' => '2026-W48', 'orders' => []]]);

        $this->actingAs(User::factory()->create())->get('/admin/youtube')->assertRedirect();
        $page = $this->actingAs($this->admin)->get('/admin/youtube')->assertOk()
            ->assertSee('Čekající váza')->assertSee('Slavná soška')->assertSee('✓ zveřejněno')->assertSee('nezveřejněno')->assertSee('1 234 zhl.')
            ->assertSee(route('admin.youtube.studio', $waiting), false)
            ->assertSee('sestřihy a návody ke schválení (1)')
            ->assertDontSee('>Týden<', false);
        $this->assertSame(1, substr_count($page->getContent(), 'Short: Zveřejněno'));
        $this->actingAs($this->admin)->get('/admin/youtube?show=published')->assertOk()->assertSee('Slavná soška')->assertDontSee('Čekající váza');
        $this->actingAs($this->admin)->get('/admin/youtube?show=waiting')->assertOk()->assertSee('Čekající váza')->assertDontSee('Slavná soška');
        $this->actingAs($this->admin)->get(route('admin.youtube.studio', $waiting))->assertOk()->assertSee('Video '.$waiting->number)->assertSee('Co je ve videu');
    }

    public function test_both_versions_wait_for_the_admin_after_a_print(): void
    {
        YouTubeAccount::create(['channel_id' => 'UC1', 'channel_title' => 'Kanál', 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);
        $order = $this->filmedOrder();
        app(FarmVideos::class)->queueFor($order);
        $this->assertEqualsCanonicalizing([FarmVideo::KIND_SHORT, FarmVideo::KIND_WIDE], $order->videos()->pluck('kind')->all());
        $this->assertSame([FarmVideo::STATUS_PENDING], $order->videos()->pluck('status')->unique()->values()->all());
    }

    public function test_the_media_list_starts_with_what_the_app_has_and_takes_the_old_clips(): void
    {
        $order = $this->filmedOrder();
        $video = FarmVideo::factory()->create(['farm_order_id' => $order->id]);
        foreach (['detail', 'removal'] as $kind) {
            Storage::disk('farm')->put($order->dir()."/clips/{$kind}.mp4", 'clip');
            FarmVideoClip::create(['farm_video_id' => $video->id, 'farm_order_id' => $order->id, 'kind' => $kind, 'path' => $order->dir()."/clips/{$kind}.mp4", 'mime' => 'video/mp4', 'size' => 4]);
        }
        $items = app(StudioMedia::class)->items($order);
        $this->assertSame(['design', 'sliced', 'growth', 'clip', 'clip', 'photo'], $items->pluck('kind')->all());
        $this->assertSame(['removal', 'detail'], $items->where('kind', 'clip')->map(fn ($m) => $m->meta['from_clip'])->values()->all(), 'in the old Short\'s order');
        $this->assertSame([true, false], $items->where('kind', 'clip')->pluck('sound')->values()->all(), 'the removal kept its sound');
        $this->assertSame(4.2, $items->firstWhere('kind', 'clip')->meta['duration']);
        $this->assertSame(6, app(StudioMedia::class)->items($order)->count(), 'made once');
    }

    public function test_the_admin_adds_drags_trims_and_deletes_media_and_every_change_builds_again(): void
    {
        $order = $this->filmedOrder();
        $media = app(StudioMedia::class);
        $media->items($order);

        // a picture and a clip at once: both go in before the photographed piece
        $png = UploadedFile::fake()->image('ruka.png', 40, 40);
        $mov = UploadedFile::fake()->create('otoceni.mov', 200, 'video/quicktime');
        $this->actingAs(User::factory()->create())->post(route('admin.youtube.media.store', $order), ['media' => [$png]])->assertRedirect();
        $this->assertSame(4, FarmVideoMedia::count());
        $this->actingAs($this->admin)->post(route('admin.youtube.media.store', $order), ['media' => [$png, $mov]])->assertRedirect()->assertSessionHas('status');
        $items = $media->items($order);
        $this->assertSame(['design', 'sliced', 'growth', 'image', 'clip', 'photo'], $items->pluck('kind')->all());
        $image = $items->firstWhere('kind', 'image');
        $clip = $items->firstWhere('kind', 'clip');
        $this->assertStringEndsWith('.jpg', $image->path);
        $this->assertSame(['ruka.png', 'otoceni.mov'], [$image->meta['name'], $clip->meta['name']]);
        $this->assertTrue($clip->meta['has_audio']);
        Queue::assertPushed(BuildStudioVideos::class, fn ($job) => $job->orderId === $order->id);
        $this->actingAs($this->admin)->get(route('admin.youtube.media.file', $image))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // a recording from the browser comes as `clip` and answers in JSON
        $this->actingAs($this->admin)->post(route('admin.youtube.media.store', $order), ['clip' => UploadedFile::fake()->create('clip.webm', 50, 'video/webm')], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['ok' => true]);
        // a file that is neither is refused
        $this->actingAs($this->admin)->post(route('admin.youtube.media.store', $order), ['media' => [UploadedFile::fake()->create('model.stl', 5)]])->assertSessionHasErrors('media');

        // dragged: the photographed piece first, the rest as it was
        $photo = $media->items($order)->firstWhere('kind', 'photo');
        $this->actingAs($this->admin)->postJson(route('admin.youtube.media.order', $order), ['ids' => [$photo->id]])->assertOk();
        $this->assertSame(['photo', 'design', 'sliced', 'growth', 'image', 'clip', 'clip'], $media->items($order)->pluck('kind')->all());

        // seconds (kept in range), the clip's own sound; a picture has no sound
        $this->actingAs($this->admin)->postJson(route('admin.youtube.media.update', $clip), ['seconds' => 2.5, 'sound' => true])->assertOk();
        $this->assertSame([2.5, true], [$clip->fresh()->seconds, $clip->fresh()->sound]);
        $this->actingAs($this->admin)->postJson(route('admin.youtube.media.update', $clip), ['seconds' => 90])->assertStatus(422);
        $this->actingAs($this->admin)->postJson(route('admin.youtube.media.update', $image), ['sound' => true])->assertOk();
        $this->assertFalse($image->fresh()->sound);

        // deleted with its file
        $this->actingAs($this->admin)->postJson(route('admin.youtube.media.delete', $image))->assertOk();
        $this->assertNull($image->fresh());
        $this->assertFalse(Storage::disk('farm')->exists($image->path));
    }

    public function test_the_short_opens_with_the_logo_and_the_opening_text_and_shows_the_media_in_the_admins_order(): void
    {
        $order = $this->filmedOrder();
        $video = FarmVideo::factory()->create(['farm_order_id' => $order->id, 'title' => 'Měděná váza', 'meta' => ['intro' => 'Váza z jedné fotky']]);
        $media = app(StudioMedia::class);
        $media->items($order);
        $this->actingAs($this->admin)->post(route('admin.youtube.media.store', $order), ['media' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->create('b.mp4', 100, 'video/mp4')]]);
        $clip = FarmVideoMedia::where('kind', 'clip')->firstOrFail();
        $image = FarmVideoMedia::where('kind', 'image')->firstOrFail();
        $clip->update(['sound' => true, 'seconds' => 2.0]);
        $growth = FarmVideoMedia::where('kind', 'growth')->firstOrFail();
        // the clip first, then the picture, then the print growing
        $media->reorder($order, [$clip->id, $image->id, $growth->id]);

        $cut = app(StudioCutter::class)->plan($order, FarmVideo::KIND_SHORT, $video);
        $inputs = implode(' ', $cut->inputs);
        $this->assertLessThan(strpos($inputs, (string) Storage::disk('farm')->path($image->path)), strpos($inputs, (string) Storage::disk('farm')->path($clip->path)));
        $this->assertStringContainsString('logo.png', $inputs);
        $this->assertSame('Váza z jedné fotky', str_replace("\n", ' ', $cut->texts[array_key_first(array_filter($cut->texts, fn ($f) => str_ends_with($f, '_intro.txt'), ARRAY_FILTER_USE_KEY))]));
        $this->assertStringContainsString('overlay=(W-w)/2:', $cut->graph);
        $this->assertStringContainsString("enable='lt(t,1.5)'", $cut->graph);
        // 2 s of the clip (its own sound under the ducked music), 1.5 s of the picture, the growth: 40 frames at 0.1 s = 4 s,
        // stretched to 4.5 s so the Short has its least 8 s
        $this->assertEqualsWithDelta(8.0, $cut->seconds, 0.01);
        $this->assertEqualsWithDelta(4.5, $cut->growth, 0.01);
        $this->assertStringContainsString('amix=inputs=2', $cut->graph);
        $this->assertStringContainsString("volume='if(between(t,0,2),0.18,1)'", $cut->graph);
        $this->assertNotNull($cut->music);

        // switched off: out of the video
        $media->update($image->fresh(), ['included' => false]);
        $this->assertStringNotContainsString((string) Storage::disk('farm')->path($image->path), implode(' ', app(StudioCutter::class)->plan($order, FarmVideo::KIND_SHORT, $video)->inputs));

        // the 16:9: title card with the opening text, the logo in the corner, the closing card; the clip's sound after the card
        $wide = app(StudioCutter::class)->plan($order, FarmVideo::KIND_WIDE, $video);
        $this->assertSame([1920, 1080], [(int) config('youtube.cut.studio.wide.width'), (int) config('youtube.cut.studio.wide.height')]);
        $this->assertStringContainsString('[title][shown][end]concat=n=3', $wide->graph);
        $this->assertStringContainsString("volume='if(between(t,1.5,3.5),0.18,1)'", $wide->graph);
        $this->assertSame('Váza z jedné fotky', $wide->texts[array_key_first(array_filter($wide->texts, fn ($f) => str_ends_with($f, '_name.txt'), ARRAY_FILTER_USE_KEY))]);

        // nothing switched on: nothing to build
        FarmVideoMedia::query()->update(['included' => false]);
        $this->assertNull(app(StudioCutter::class)->plan($order, FarmVideo::KIND_SHORT, $video));
    }

    public function test_texts_rebuild_when_the_opening_text_changes_and_go_to_youtube_for_a_published_video(): void
    {
        $order = $this->filmedOrder();
        $video = FarmVideo::factory()->create(['farm_order_id' => $order->id, 'title' => 'Váza', 'description' => 'Popis', 'music' => 'Artist - Song.mp3']);

        // the title alone: the Short opens with it (no own opening text), so it is built again
        $this->actingAs($this->admin)->post(route('admin.youtube.texts', $video), ['title' => 'Váza ve spirále', 'description' => 'Nový popis'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(['Váza ve spirále', 'Nový popis'], [$video->fresh()->title, $video->fresh()->description]);
        Queue::assertPushed(BuildStudioVideos::class, 1);
        // an own opening text: built again; the same text again: not
        $this->actingAs($this->admin)->post(route('admin.youtube.texts', $video), ['title' => 'Váza ve spirále', 'description' => 'Nový popis', 'intro' => 'Ze tří barev'])->assertRedirect();
        $this->assertSame('Ze tří barev', $video->fresh()->meta['intro']);
        Queue::assertPushed(BuildStudioVideos::class, 2);
        $this->actingAs($this->admin)->post(route('admin.youtube.texts', $video), ['title' => 'Jiný název', 'description' => 'Nový popis', 'intro' => 'Ze tří barev'])->assertRedirect();
        Queue::assertPushed(BuildStudioVideos::class, 2);

        // publish from the same form: at the next free slot
        $this->actingAs($this->admin)->post(route('admin.youtube.texts', $video), ['title' => 'Jiný název', 'description' => 'Nový popis', 'intro' => 'Ze tří barev', 'publish' => 'slot', 'when' => 'slot'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame(FarmVideo::STATUS_QUEUED, $video->fresh()->status);
        $this->assertNotNull($video->fresh()->scheduled_at);
        Queue::assertPushed(UploadFarmVideo::class);

        // on YouTube: the title and description change there, the picture is locked (no build)
        YouTubeAccount::create(['channel_id' => 'UC1', 'channel_title' => 'Kanál', 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);
        Http::fake(['*' => Http::response(['id' => 'yt1'])]);
        $video->update(['status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => 'yt1']);
        $this->actingAs($this->admin)->post(route('admin.youtube.texts', $video), ['title' => 'Opravený název', 'description' => 'Opravený popis', 'intro' => 'Nový úvod'])->assertRedirect()->assertSessionHas('status');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_contains($r->url(), 'videos?part=snippet') && $r['id'] === 'yt1' && $r['snippet']['title'] === 'Opravený název');
        $this->assertSame(['Opravený název', 'Ze tří barev'], [$video->fresh()->title, $video->fresh()->meta['intro']]);
        Queue::assertPushed(BuildStudioVideos::class, 2);
        $this->actingAs($this->admin)->get(route('admin.youtube.studio', $order))->assertOk()->assertSee('obraz je zamčený');
    }
}
