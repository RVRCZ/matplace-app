<?php

namespace Tests\Feature;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\HowtoCutter;
use App\Engines\Social\FakeMetaClient;
use App\Models\FarmVideo;
use App\Models\User;
use App\Models\YouTubeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** How-to videos cut from a JSON spec (farm:howto-video): the plan, the outputs, approval as kind howto, the Short to FB/IG. */
class HowtoVideoTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('farm');
        Mail::fake();
        $this->dir = sys_get_temp_dir().'/mp_howto_'.uniqid();
        mkdir($this->dir.'/music', 0777, true);
        config(['youtube.music_dir' => $this->dir.'/music']);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse(glob($this->dir.'/{,*/}*', GLOB_BRACE) ?: []) as $f) {
            is_dir($f) ? @rmdir($f) : @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** Two segments of a "recording", the title and the closing card. */
    private function spec(array $extra = []): array
    {
        foreach (['a', 'b'] as $name) {
            if (! is_file($this->dir."/{$name}.mp4")) {
                file_put_contents($this->dir."/{$name}.mp4", 'x');
            }
        }

        return array_replace([
            'name' => 'test-howto',
            'out_dir' => $this->dir.'/out',
            'title' => ['cs' => 'Zkušební návod', 'en' => 'Test how-to'],
            'youtube_title' => 'Zkušební návod',
            'description' => 'Popis videa.',
            'model' => ['name' => 'Cell Phone Stand', 'author' => 'Pit Demon', 'licence' => 'CC0', 'url' => 'https://www.printables.com/model/767024-cell-phone-stand'],
            'music_volume' => 0.6,
            'sources' => ['screen' => ['path' => $this->dir.'/a.mp4', 'crop' => [42, 108, 1878, 947]], 'lapse' => ['path' => $this->dir.'/b.mp4'], 'phone' => ['path' => $this->dir.'/none.mp4']],
            'blur' => [['source' => 'screen', 'from' => 2, 'to' => 3, 'box' => [100, 200, 300, 40]]],
            'wide' => [
                ['card' => 'intro', 'seconds' => 2],
                ['source' => 'screen', 'from' => 1, 'to' => 5, 'speed' => 2, 'caption' => ['cs' => 'Krok jedna', 'en' => 'Step one']],
                ['source' => 'lapse', 'from' => 0, 'to' => 3, 'caption_at' => 'top', 'caption' => ['cs' => 'Krok dva', 'en' => 'Step two']],
                ['source' => 'phone', 'from' => 0, 'to' => 2, 'missing' => ['seconds' => 1.5, 'text' => ['cs' => 'Doplníme', 'en' => 'Later']]],
                ['source' => 'phone', 'from' => 0, 'to' => 2],
                ['card' => 'end', 'seconds' => 2],
            ],
            'short' => [
                ['source' => 'screen', 'from' => 1, 'to' => 5, 'speed' => 2, 'short_crop' => [900, 500, 1000], 'caption' => ['cs' => 'Krok jedna s dlouhým popisem, který se nevejde', 'en' => 'Step one']],
                ['card' => 'end', 'seconds' => 2],
            ],
        ], $extra);
    }

    public function test_the_plan_trims_speeds_up_captions_and_blurs_from_the_spec(): void
    {
        touch($this->dir.'/music/Artist - Song.mp3');
        $howto = app(HowtoCutter::class);
        $plan = $howto->plan($this->spec(), 'wide', $this->dir.'/out/test.mp4');
        $cut = $plan['cut'];

        // 2 s title + 4 s at 2× + 3 s + 1.5 s placeholder (the phone shot is not there) + 2 s closing card; the second phone segment is left out
        $this->assertEqualsWithDelta(2 + 2 + 3 + 1.5 + 2, $cut->seconds, 0.001);
        $this->assertSame(['phone: '.$this->dir.'/none.mp4', 'phone: '.$this->dir.'/none.mp4'], $plan['skipped']);
        $this->assertStringContainsString('concat=n=5:v=1:a=0[v]', $cut->graph);
        $this->assertStringContainsString('setpts=(PTS-STARTPTS)/2,fps=30', $cut->graph);
        $this->assertStringContainsString('crop=1878:947:42:108,scale=1920:1080', $cut->graph, 'the page without the browser around it');
        // the blur at 2-3 s of the recording = 1-2 s into the segment that starts at 1 s
        $this->assertStringContainsString("crop=300:40:100:200,boxblur=14:3:9:3[s1_b0];[s1_m0][s1_b0]overlay=100:200:enable='between(t,1,2)'", $cut->graph);
        $this->assertSame(['-ss', '1', '-t', '4', '-i', $this->dir.'/a.mp4'], array_slice($cut->inputs, 6, 6), 'the logo first, then the segments');
        // captions: on screen for their first seconds, the subtitles say the same
        $this->assertStringContainsString('drawbox=x=0:y=0:w=1920', $cut->graph, 'a caption at the top where the bottom has buttons');
        $this->assertSame([[2.0, 4.0, ['cs' => 'Krok jedna', 'en' => 'Step one']], [4.0, 7.0, ['cs' => 'Krok dva', 'en' => 'Step two']]], $plan['captions']);
        $this->assertSame("1\n00:00:02,000 --> 00:00:04,000\nKrok jedna\n\n2\n00:00:04,000 --> 00:00:07,000\nKrok dva\n", $howto->srt($plan['captions'], 'cs'));
        $this->assertContains('Zkušební návod', $cut->texts);
        // music: the library's track, quieter than the time-lapses, faded out
        $this->assertStringEndsWith('Artist - Song.mp3', (string) $cut->music);
        $this->assertStringContainsString('volume=0.6,afade=t=in:st=0:d=0.5,afade=t=out:st=9:d=1.5[a]', $cut->graph);

        // the Short: vertical, the window of the page across the middle, the long caption wrapped
        $short = $howto->plan($this->spec(), 'short', $this->dir.'/out/test-short.mp4');
        $this->assertStringContainsString('scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920', $short['cut']->graph);
        $this->assertStringContainsString('crop=1000:563:400:219,scale=1080:-2', $short['cut']->graph);
        $this->assertGreaterThan(1, substr_count((string) collect($short['cut']->texts)->first(fn ($t) => str_starts_with($t, 'Krok jedna')), "\n") + 1);

        // the description: the call with our link first (YouTube shows two lines), our text, the model's credit, the music credit
        ['title' => $title, 'description' => $text] = $howto->description($this->spec(), $cut->music);
        $this->assertSame('Zkušební návod', $title);
        $this->assertStringContainsString('Model: Cell Phone Stand od Pit Demon (Printables, CC0) – https://www.printables.com/model/767024-cell-phone-stand', $text);
        $this->assertStringContainsString('utm_campaign=video_howto', $text);
        $this->assertStringStartsWith("Nahrajte model a hned uvidíte cenu: https://matplace.com/?utm_source=youtube&utm_medium=social&utm_campaign=video_howto\nPopis videa.", $howto->description($this->spec(['site' => 'https://matplace.com']), null)['description']);
        $this->assertStringStartsWith('Udělejte si vlastní: https://matplace.com/tools/vase?utm_source=youtube&utm_medium=social&utm_campaign=video_howto', $howto->description($this->spec(['site' => 'https://matplace.com', 'link' => 'tools/vase']), null)['description'], 'the spec names where it leads');
        $this->assertStringContainsString('Hudba: Artist – Song (YouTube Audio Library)', $text);
        $this->assertStringContainsString('MODEL: …', $howto->description($this->spec(['model' => []]), null)['description'], 'no model yet: a line to fill in');
    }

    public function test_the_command_builds_both_videos_and_queues_them_for_approval_as_howto(): void
    {
        YouTubeAccount::create(['channel_id' => 'UC1', 'channel_title' => 'Matplace', 'refresh_token' => 'r']);
        file_put_contents($this->dir.'/spec.json', json_encode($this->spec()));
        Process::fake(fn (PendingProcess $p) => file_put_contents(end($p->command), 'mp4') ? Process::result() : Process::result(exitCode: 1));

        $this->artisan('farm:howto-video', ['spec' => $this->dir.'/spec.json', '--dry-run' => true])->expectsOutputToContain('missing, left out')->assertSuccessful();
        Process::assertNothingRan();

        $this->artisan('farm:howto-video', ['spec' => $this->dir.'/spec.json', '--queue' => true])->assertSuccessful();
        Process::assertRan(fn (PendingProcess $p) => str_ends_with(end($p->command), '/out/test-howto.mp4'));
        Process::assertRan(fn (PendingProcess $p) => str_ends_with(end($p->command), '/out/test-howto-short.mp4'));
        foreach (['test-howto.mp4', 'test-howto-short.mp4', 'popis.txt', 'titulky.cs.srt', 'titulky.en.srt'] as $file) {
            $this->assertFileExists($this->dir.'/out/'.$file);
        }
        $this->assertStringStartsWith("Zkušební návod\n\nNahrajte model a hned uvidíte cenu: ".url('/').'/?utm_source=youtube&utm_medium=social&utm_campaign=video_howto'."\nPopis videa.", (string) file_get_contents($this->dir.'/out/popis.txt'));

        $rows = FarmVideo::where('kind', FarmVideo::KIND_HOWTO)->orderBy('id')->get();
        $home = url('/').'/';
        $this->assertSame([['name' => 'test-howto', 'variant' => 'wide', 'link' => $home], ['name' => 'test-howto', 'variant' => 'short', 'link' => $home]], $rows->pluck('meta')->all());
        $this->assertSame($home.'?utm_source=facebook&utm_medium=social&utm_campaign=video_howto', app(FarmVideos::class)->linkOf($rows[1], 'facebook'), 'Facebook goes where the spec leads');
        $this->assertSame([FarmVideo::STATUS_PENDING, FarmVideo::STATUS_PENDING], $rows->pluck('status')->all());
        $this->assertSame([null, null], $rows->pluck('farm_order_id')->all());
        $this->assertSame(['howto/test-howto.mp4', 'howto/test-howto-short.mp4'], $rows->pluck('path')->all());
        Storage::disk('farm')->assertExists('howto/test-howto-short.mp4');
        $this->assertStringEndsWith('#Shorts', (string) $rows[1]->description);
        $this->assertTrue(app(FarmVideos::class)->consented($rows[0]), 'our own video needs no customer');

        // built again: the same rows take the new files
        $this->artisan('farm:howto-video', ['spec' => $this->dir.'/spec.json', '--queue' => true])->assertSuccessful();
        $this->assertSame(2, FarmVideo::where('kind', FarmVideo::KIND_HOWTO)->count());

        // the approval page: the pair side by side, one button each, Facebook/Instagram only at the Short
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $page = $this->actingAs($admin)->get('/admin/youtube')->assertOk()->assertSee('Instruktážní video · 16:9')->assertSee('Instruktážní video · Short 9:16')->getContent();
        $this->assertSame(2, substr_count($page, 'Nahrát na YouTube a zveřejnit'));
        $this->actingAs($admin)->get("/admin/youtube/videos/{$rows[1]->id}/video.mp4")->assertOk();
    }

    public function test_only_the_vertical_short_of_a_howto_goes_to_facebook_and_instagram(): void
    {
        FakeMetaClient::reset();
        Storage::disk('farm')->put('howto/a.mp4', 'mp4');
        Storage::disk('farm')->put('howto/a-short.mp4', 'mp4');
        $wide = FarmVideo::create(['kind' => 'howto', 'status' => 'published', 'title' => 'A', 'path' => 'howto/a.mp4', 'meta' => ['name' => 'a', 'variant' => 'wide'], 'share' => ['facebook'], 'youtube_id' => 'w1']);
        $short = FarmVideo::create(['kind' => 'howto', 'status' => 'published', 'title' => 'A', 'path' => 'howto/a-short.mp4', 'meta' => ['name' => 'a', 'variant' => 'short'], 'share' => ['facebook'], 'youtube_id' => 's1']);

        $this->artisan('social:videos')->expectsOutputToContain('posted: 1')->assertSuccessful();
        $this->assertStringEndsWith('a-short.mp4', FakeMetaClient::$posts[0]['image']);
        $this->assertDatabaseHas('social_posts', ['subject_type' => 'farm_video', 'subject_id' => $short->id, 'status' => 'posted']);
        $this->assertDatabaseMissing('social_posts', ['subject_id' => $wide->id]);
    }

    public function test_a_real_howto_video_from_two_clips(): void
    {
        $ffmpeg = (string) env('FFMPEG_BIN', '');
        if ($ffmpeg === '' || ! is_file($ffmpeg)) {
            $this->markTestSkipped('FFMPEG_BIN not set: rendering needs a real ffmpeg');
        }
        config(['farm.ffmpeg' => $ffmpeg]);
        Process::run([$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=size=1920x1080:rate=60:duration=6', '-pix_fmt', 'yuv420p', $this->dir.'/a.mp4'])->throw();
        Process::run([$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=1920x1080:rate=30:duration=4', '-pix_fmt', 'yuv420p', $this->dir.'/b.mp4'])->throw();
        Process::run([$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=330:duration=5', $this->dir.'/music/Artist - Song.mp3'])->throw();
        file_put_contents($this->dir.'/spec.json', json_encode($this->spec()));

        $this->artisan('farm:howto-video', ['spec' => $this->dir.'/spec.json'])->assertSuccessful();
        $probe = fn (string $f) => Process::run([$ffmpeg, '-hide_banner', '-i', $f])->errorOutput();
        $wide = $probe($this->dir.'/out/test-howto.mp4');
        $this->assertMatchesRegularExpression('/Duration: 00:00:10\.[2-7]/', $wide, '10.5 s planned');
        $this->assertMatchesRegularExpression('/Video: h264.*1920x1080/', $wide);
        $this->assertStringContainsString('Audio: aac', $wide, 'the 5 s track loops under the 10.5 s video');
        $short = $probe($this->dir.'/out/test-howto-short.mp4');
        $this->assertMatchesRegularExpression('/Video: h264.*1080x1920/', $short);
        $this->assertMatchesRegularExpression('/Duration: 00:00:0(3\.[7-9]|4\.[0-3])/', $short, '2 s + 2 s');
    }
}
