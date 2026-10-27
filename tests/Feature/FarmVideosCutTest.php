<?php

namespace Tests\Feature;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\TimelapseCutter;
use App\Jobs\BuildFarmTimelapse;
use App\Mail\FarmAdminAlert;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * How the farm videos are cut (TimelapseCutter): an even pace inside each kind's range, the order's track under
 * every version, the three videos of a print and the weekly cut with its chapters. Planned without ffmpeg; the last
 * two tests render for real when FFMPEG_BIN is set.
 */
class FarmVideosCutTest extends TestCase
{
    use RefreshDatabase;

    private string $music;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->music = sys_get_temp_dir().'/mp_tracks_'.uniqid();
        mkdir($this->music);
        config(['youtube.music_dir' => $this->music]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->music.'/*') ?: []);
        @rmdir($this->music);
        parent::tearDown();
    }

    public function test_the_print_grows_at_an_even_pace_inside_each_kinds_range(): void
    {
        $cutter = app(TimelapseCutter::class);
        $frames = fn (int $n) => collect(range(1, $n))->map(fn ($i) => "f{$i}.jpg");
        foreach (['short' => [15, 25, 24], 'wide' => [20, 28, 30], 'weekly' => [8.5, 10, 30]] as $kind => [$min, $max, $fps]) {
            foreach ([200, 600, 5000] as $n) {
                [$shown, $seconds] = $cutter->paced($frames($n), config("youtube.cut.$kind.growth"), $fps);
                $growth = $shown->count() * $seconds;
                $this->assertGreaterThanOrEqual($min - 1 / $fps, $growth, "$kind, $n frames");
                $this->assertLessThanOrEqual($max + 1 / $fps, $growth, "$kind, $n frames");
                $this->assertGreaterThanOrEqual(1 / $fps - 1e-9, $seconds, 'no frame shorter than a video frame');
                $this->assertSame(['f1.jpg', "f{$n}.jpg"], [$shown->first(), $shown->last()], 'first and last frame always');
            }
        }
        // a long print: every n-th frame, evenly
        [$shown] = $cutter->paced($frames(5000), [20, 28], 30);
        $this->assertSame(840, $shown->count());
        // a short print stays short: never slower than 0.2 s a frame
        [$shown, $seconds] = $cutter->paced($frames(40), [20, 28], 30);
        $this->assertSame([40, 0.2], [$shown->count(), $seconds]);
    }

    public function test_an_order_gets_three_cuts_each_with_the_orders_track_cut_to_it_and_faded_out(): void
    {
        $order = $this->order();
        $this->frames($order, 600);
        touch($this->music.'/Artist - Song.mp3');

        $cuts = (new BuildFarmTimelapse($order->id))->cuts($order);
        $this->assertSame(['customer', 'short', 'wide'], array_keys($cuts));

        // the Short: the hook card, 25 s of growing, the piece held, the end with the price (no photo yet: the print stays); the 16:9: 28 s of growing, under 45 s
        $this->assertEqualsWithDelta(25.0, $cuts['short']->growth, 0.05);
        $this->assertEqualsWithDelta(1 + 25 + 1.5 + 3, $cuts['short']->seconds, 0.05);
        $this->assertSame([1080, 1920, 24], [config('youtube.cut.short.width'), config('youtube.cut.short.height'), $cuts['short']->fps]);
        $this->assertStringContainsString('crop=1080:1920,boxblur', $cuts['short']->graph, 'the camera picture blurred behind the square of the bed');
        $this->assertSame(app(FarmVideos::class)->hook($order), str_replace('
', ' ', $cuts['short']->texts[array_key_first(array_filter($cuts['short']->texts, fn ($k) => str_ends_with($k, 'short_hook.txt'), ARRAY_FILTER_USE_KEY))]), 'the hook card, wrapped to the width');
        $this->assertContains(app(FarmVideos::class)->address($order), $cuts['short']->texts, 'the address of the tool at the end');
        $this->assertContains('Co vytisknout příště?', $cuts['short']->texts, 'the question at the end');
        $this->assertEqualsWithDelta(28.0, $cuts['wide']->growth, 0.05);
        $this->assertEqualsWithDelta(1.5 + 28 + 2 + 3, $cuts['wide']->seconds, 0.05);
        $this->assertEqualsWithDelta(7 + 28 + 3 + 5, $cuts['customer']->seconds, 0.05);
        foreach ($cuts as $kind => $cut) {
            // the frame lists add up to the planned growth (the Short also holds the first frame under the hook and the last one)
            $listed = array_sum(array_column(array_merge(...array_values($cut->lists)), 1));
            $this->assertEqualsWithDelta($cut->growth + ($kind === 'short' ? 2.5 : 0), $listed, 0.01, $kind);
            $this->assertStringEndsWith('Artist - Song.mp3', (string) $cut->music, $kind);
            $this->assertStringContainsString(sprintf('atrim=duration=%s,', rtrim(rtrim(sprintf('%.6F', $cut->seconds), '0'), '.')), $cut->graph);
            $this->assertStringContainsString(sprintf('afade=t=out:st=%s:d=1.5[a]', rtrim(rtrim(sprintf('%.6F', $cut->seconds - 1.5), '0'), '.')), $cut->graph);
            $command = $cut->command('ffmpeg');
            $this->assertContains('-stream_loop', $command, 'a shorter track loops, a longer one is cut');
            $this->assertContains('[a]', $command);
        }
        $this->assertStringContainsString('scale=1920:1080', $cuts['wide']->graph);
        $this->assertSame(30, $cuts['wide']->fps);
        $this->assertContains(app(FarmVideos::class)->address($order), $cuts['wide']->texts, 'the end card names the address');

        // the track the order's videos already name wins, for every version
        touch($this->music.'/Other - Band.mp3');
        FarmVideo::create(['farm_order_id' => $order->id, 'kind' => 'short', 'status' => 'pending', 'title' => 'T', 'music' => 'Other - Band.mp3']);
        $this->assertStringEndsWith('Other - Band.mp3', (string) app(TimelapseCutter::class)->wide($order)->music);
    }

    public function test_without_a_music_library_the_videos_are_silent_and_the_log_says_so_once(): void
    {
        Log::spy();
        $order = $this->order();
        $this->frames($order, 100);

        $cuts = app(TimelapseCutter::class)->cuts($order);
        foreach ($cuts as $cut) {
            $this->assertNull($cut->music);
            $this->assertStringNotContainsString('[a]', $cut->graph);
            $this->assertNotContains('[a]', $cut->command('ffmpeg'));
        }
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, 'without music'))->once();
    }

    public function test_the_end_card_names_the_tool_that_made_the_model(): void
    {
        $order = $this->order();
        $videos = app(FarmVideos::class);
        $host = preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/'));
        $this->assertSame($host, $videos->address($order), 'a customer\'s own file: the home page');
        $order->modelFile->forceFill(['origin' => 'tool', 'origin_ref' => 'vase'])->save();
        $this->assertSame($host.'/tools/vase', $videos->address($order->refresh()));
        $this->assertSame(rtrim((string) config('app.url'), '/').'/tools/vase?utm_source=youtube&utm_medium=social&utm_campaign=video_weekly', $videos->link($order, 'weekly'));
        $order->modelFile->forceFill(['origin' => 'generated', 'origin_ref' => null])->save();
        $this->assertSame($host.'/tools/figure', $videos->address($order->refresh()));
    }

    public function test_the_weekly_cut_takes_the_weeks_shareable_prints_with_a_chapter_each_and_waits_for_the_admin(): void
    {
        $this->travelTo(Carbon::parse('2026-10-18 18:00', 'Europe/Prague'));   // Sunday evening: the week 2026-W42 ends
        touch($this->music.'/Artist - Song.mp3');
        $first = $this->order();
        $week = [$first, $this->copyOf($first), $this->copyOf($first)];
        foreach ($week as $i => $o) {
            $o->forceFill(['finished_at' => Carbon::parse('2026-10-13 10:00', 'Europe/Prague')->addDays($i)])->save();
            $this->frames($o, 300 + $i * 100);
        }
        $week[1]->modelFile->forceFill(['origin' => 'tool', 'origin_ref' => 'vase'])->save();
        $private = $this->copyOf($first, ['finished_at' => now()->subDay()]);                  // a bust from a photo
        $this->frames($private, 300);
        $private->forceFill(['model_file_id' => $this->copyFile($first, 'generated')])->save();
        $silent = $this->copyOf($first, ['finished_at' => now()->subDay(), 'video_consent' => false]);
        $this->frames($silent, 300);
        $before = $this->copyOf($first, ['finished_at' => Carbon::parse('2026-10-11 20:00', 'Europe/Prague')]);   // the week before
        $this->frames($before, 300);
        Process::fake(fn (PendingProcess $p) => file_put_contents(end($p->command), 'mp4') ? Process::result() : Process::result(exitCode: 1));

        $this->artisan('farm:weekly-video', ['--dry-run' => true])->expectsOutputToContain('2026-W42 (12. 10.–18. 10. 2026): 3 prints')->assertSuccessful();
        $this->assertSame(0, FarmVideo::count(), 'a dry run builds nothing');
        Process::assertNothingRan();

        $this->artisan('farm:weekly-video')->expectsOutputToContain('Waiting for approval')->assertSuccessful();
        $video = FarmVideo::firstOrFail();
        $this->assertSame([FarmVideo::KIND_WEEKLY, FarmVideo::STATUS_PENDING, null, 'weekly/2026-W42.mp4'], [$video->kind, $video->status, $video->farm_order_id, $video->path]);
        $this->assertSame(['week' => '2026-W42', 'orders' => array_map(fn ($o) => $o->id, $week)], $video->meta);
        $this->assertSame('Tento týden na farmě – 12. 10.–18. 10. 2026', $video->title);
        $this->assertSame('Artist - Song.mp3', $video->music);
        // chapters: from 0:00 (the first print takes the intro in), one per print, at least 10 s each, with the tool's page and the campaign
        preg_match_all('/^(\d+):(\d\d) (.+)$/m', (string) $video->description, $m, PREG_SET_ORDER);
        $this->assertCount(3, $m);
        $this->assertStringStartsWith('0:00 ', $m[0][0]);
        $starts = array_map(fn ($c) => (int) $c[1] * 60 + (int) $c[2], $m);
        foreach (array_slice($starts, 1) as $i => $start) {
            $this->assertGreaterThanOrEqual(10, $start - $starts[$i]);
        }
        $this->assertStringContainsString('/tools/vase?utm_source=youtube&utm_medium=social&utm_campaign=video_weekly', $m[1][3]);
        $this->assertStringContainsString('Hudba: Artist – Song (YouTube Audio Library)', (string) $video->description);
        Process::assertRan(fn (PendingProcess $p) => in_array('-stream_loop', $p->command, true) && str_contains(implode(' ', $p->command), 'concat=n=5'));
        Mail::assertQueued(FarmAdminAlert::class, fn ($mail) => str_contains($mail->subjectLine, 'Video ke schválení'));

        // built again for the same week: the same row takes the new file
        $this->artisan('farm:weekly-video', ['--week' => '2026-W42'])->assertSuccessful();
        $this->assertSame(1, FarmVideo::count());

        // a customer takes the consent back: the cut is marked for the admin
        app(FarmVideos::class)->setConsent($week[0]->refresh(), false);
        $this->assertStringContainsString('odvolal souhlas', (string) $video->refresh()->error);
        $this->assertFalse(app(FarmVideos::class)->consented($video));

        // a week with fewer than three prints: nothing, one line in the log
        Log::spy();
        $this->artisan('farm:weekly-video', ['--week' => '2026-W41'])->expectsOutputToContain('no weekly video')->assertSuccessful();
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, 'not enough prints'))->once();
        $this->assertSame(1, FarmVideo::count());
    }

    public function test_real_videos_from_numbered_frames_have_the_planned_lengths_sizes_and_music(): void
    {
        $ffmpeg = $this->ffmpeg();
        Process::run([$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=330:duration=20', $this->music.'/Artist - Song.mp3'])->throw();
        $order = $this->order();
        $order->forceFill(['status' => FarmOrder::STATUS_DONE])->save();
        $this->frames($order, 200, real: true, layer: true);

        $cuts = (new BuildFarmTimelapse($order->id))->cuts($order);
        (new BuildFarmTimelapse($order->id, rebuild: true))->handle();
        $order->refresh();
        $disk = Storage::disk('farm');
        $report = [];
        foreach (['customer' => $order->timelapse_path, 'short' => $order->timelapse_short_path, 'wide' => $order->timelapse_wide_path] as $kind => $path) {
            $this->assertNotNull($path, $kind);
            $info = $this->probe($disk->path($path));
            $this->assertEqualsWithDelta($cuts[$kind]->seconds, $info['seconds'], 0.3, $kind);
            $this->assertTrue($info['audio'], "$kind has the track");
            $report[$kind] = $info + ['planned' => round($cuts[$kind]->seconds, 2), 'growth' => round($cuts[$kind]->growth, 2), 'frames' => $cuts[$kind]->frameCount()];
        }
        $this->assertSame([1080, 1920], [$report['short']['width'], $report['short']['height']]);
        $this->assertSame([1920, 1080], [$report['wide']['width'], $report['wide']['height']]);
        $this->assertSame([1280, 720], [$report['customer']['width'], $report['customer']['height']]);
        $this->samples($report, [[$order->timelapse_short_path, 8, 'short'], [$order->timelapse_wide_path, 0.8, 'wide-title'], [$order->timelapse_wide_path, 14, 'wide'],
            [$order->timelapse_wide_path, $report['wide']['seconds'] - 1, 'wide-end'], [$order->timelapse_path, 20, 'customer']], $disk);

        // the photo of the cleaned piece: the Short crossfades into it instead of holding the plate (0.4 s shorter), the 16:9 and the customer's + 3 s
        $disk->put($order->dir().'/photos/left.jpg', $this->picture(200, 'photo'));
        $order->forceFill(['test_params' => ['photos' => [['file' => $order->dir().'/photos/left.jpg', 'view' => 'left']]]])->save();
        $this->artisan('farm:timelapse', ['number' => $order->number])->assertSuccessful();
        $order->refresh();
        $this->assertEqualsWithDelta($report['short']['seconds'] - 0.4, $this->probe($disk->path($order->timelapse_short_path))['seconds'], 0.3);
        $this->assertEqualsWithDelta($report['wide']['seconds'] + 3.0, $this->probe($disk->path($order->timelapse_wide_path))['seconds'], 0.3);
        $this->assertEqualsWithDelta($report['customer']['seconds'] + 3.0, $this->probe($disk->path($order->timelapse_path))['seconds'], 0.3);
    }

    public function test_a_real_weekly_cut_of_three_prints(): void
    {
        $ffmpeg = $this->ffmpeg();
        Process::run([$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=330:duration=20', $this->music.'/Artist - Song.mp3'])->throw();
        $first = $this->order();
        foreach ([$first, $this->copyOf($first), $this->copyOf($first)] as $i => $o) {
            $o->forceFill(['finished_at' => Carbon::parse('2026-10-13 10:00', 'Europe/Prague')->addDays($i)])->save();
            $this->frames($o, 200, real: true);
        }
        $this->artisan('farm:weekly-video', ['--week' => '2026-W42'])->assertSuccessful();
        $video = FarmVideo::where('kind', 'weekly')->firstOrFail();
        $info = $this->probe(Storage::disk('farm')->path($video->path));
        $this->assertSame([1920, 1080, true], [$info['width'], $info['height'], $info['audio']]);
        $this->assertEqualsWithDelta(3 + 3 * (10 + 1) + 4, $info['seconds'], 0.5, 'intro, three pieces of 10 s growing and 1 s held, the closing card');
        $this->samples(['weekly' => $info], [[$video->path, 1.6, 'weekly-intro'], [$video->path, 9, 'weekly']], Storage::disk('farm'));
    }

    // ── helpers ──

    private function ffmpeg(): string
    {
        $ffmpeg = (string) env('FFMPEG_BIN', '');
        if ($ffmpeg === '' || ! is_file($ffmpeg)) {
            $this->markTestSkipped('FFMPEG_BIN not set: rendering needs a real ffmpeg');
        }
        config(['farm.ffmpeg' => $ffmpeg]);

        return $ffmpeg;
    }

    /** @return array{seconds: float, width: int, height: int, audio: bool, kb: int} */
    private function probe(string $file): array
    {
        $info = Process::run([(string) config('farm.ffmpeg'), '-hide_banner', '-i', $file])->errorOutput();
        $this->assertMatchesRegularExpression('/Duration: \d/', $info, $info);
        preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/', $info, $d);
        preg_match('/Video: h264.*?, (\d{2,5})x(\d{2,5})/', $info, $s);

        return ['seconds' => round($d[1] * 3600 + $d[2] * 60 + (float) $d[3], 2), 'width' => (int) ($s[1] ?? 0), 'height' => (int) ($s[2] ?? 0),
            'audio' => str_contains($info, 'Audio: aac'), 'kb' => (int) round(filesize($file) / 1024)];
    }

    /** With FARM_VIDEO_SAMPLES set: one picture of every video and the numbers, for docs/AD.md. */
    private function samples(array $report, array $stills, $disk): void
    {
        $dir = (string) env('FARM_VIDEO_SAMPLES', '');
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }
        foreach ($stills as [$path, $at, $name]) {
            Process::run([(string) config('farm.ffmpeg'), '-y', '-loglevel', 'error', '-ss', (string) $at, '-i', $disk->path($path), '-frames:v', '1', '-vf', 'scale=960:-2', $dir.'/'.$name.'.jpg'])->throw();
        }
        $file = $dir.'/report.json';
        $old = is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
        file_put_contents($file, json_encode($report + $old, JSON_PRETTY_PRINT));
    }

    /** Frames of a print: time-based ones (one a minute) or layer ones; `real` = growing pictures with the frame's number. */
    private function frames(FarmOrder $order, int $n, bool $real = false, bool $layer = false): void
    {
        $disk = Storage::disk('farm');
        $t = 1_790_000_000;
        for ($i = 0; $i < $n; $i++) {
            $disk->put(sprintf('%s/%s/%.3f.jpg', $order->dir(), $layer ? 'frames_layer' : 'frames', $t + $i * 60), $real ? $this->picture((int) round($i * 400 / $n), (string) ($i + 1)) : 'x');
        }
    }

    /** A 1280×720 camera picture: a bar of the given height and a big number. */
    private function picture(int $height, string $label): string
    {
        $im = imagecreatetruecolor(1280, 720);
        imagefill($im, 0, 0, imagecolorallocate($im, 40, 40, 40));
        imagefilledrectangle($im, 560, 620 - $height, 720, 620, imagecolorallocate($im, 201, 71, 20));
        imagettftext($im, 64, 0, 60, 130, imagecolorallocate($im, 240, 240, 240), base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'), $label);
        ob_start();
        imagejpeg($im, null, 85);

        return (string) ob_get_clean();
    }

    /** A paid customer order whose customer agreed to the videos (fake slicer, fake gateway). */
    private function order(): FarmOrder
    {
        $user = User::factory()->create();
        $path = sys_get_temp_dir().'/mp_cut_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $order = FarmOrder::where('token', basename($this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
        $this->actingAs($user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $this->postJson('/webhooks/payments/fake', ['ref' => Payment::latest('id')->firstOrFail()->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", [
            'slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total'], 'video_consent' => true,
        ])->assertOk();
        $order->refresh()->forceFill(['timelapse_path' => $order->dir().'/timelapse.mp4'])->save();

        return $order;
    }

    /** Another order like this one (its own token and number, its own copy of the model file row). */
    private function copyOf(FarmOrder $order, array $set = []): FarmOrder
    {
        $copy = $order->replicate();
        $copy->forceFill(['token' => Str::random(32), 'number' => 'F26-T'.Str::random(6), 'model_file_id' => $this->copyFile($order)] + $set)->save();
        $copy->forceFill(['timelapse_path' => $copy->dir().'/timelapse.mp4'])->save();

        return $copy;
    }

    private function copyFile(FarmOrder $order, ?string $origin = null): int
    {
        $file = $order->modelFile->replicate();
        $file->forceFill(['uuid' => (string) Str::uuid()] + ($origin ? ['origin' => $origin] : []))->save();

        return $file->id;
    }
}
