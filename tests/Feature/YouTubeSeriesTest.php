<?php

namespace Tests\Feature;

use App\Domain\Farm\PrintPhotos;
use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\SeriesCutter;
use App\Domain\YouTube\TimelapseCutter;
use App\Engines\Photo\FakeBackgroundRemover;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\User;
use App\Models\YouTubeAccount;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The two videos cut from what the farm already has (SeriesCutter, docs/AL.md §13.7): the Short "Kolik stojí 3D tisk?"
 * of photographed prints with their prices and the monthly "Jak funguje naše 3D tisková farma". Planned without ffmpeg;
 * the last test renders both for real when FFMPEG_BIN is set (and writes the samples with FARM_VIDEO_SAMPLES).
 */
final class YouTubeSeriesTest extends TestCase
{
    use RefreshDatabase;

    private string $music;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        FakeBackgroundRemover::reset();
        $this->music = sys_get_temp_dir().'/mp_series_'.uniqid();
        mkdir($this->music);
        file_put_contents($this->music.'/Artist - Song.mp3', '');
        config(['youtube.music_dir' => $this->music]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->music.'/*') ?: []);
        @rmdir($this->music);
        parent::tearDown();
    }

    /**
     * A print the videos may show: the customer agreed, the time-lapses are on the disk (empty files: the plan reads none),
     * the piece photographed and composed (files on the public disk), a price. $tool null = the customer's own file.
     */
    private function print(?string $tool, float $price = 1190, ?Carbon $finished = null, array $set = []): FarmOrder
    {
        $order = FarmOrder::factory()->paid()->done($finished ?? now()->subDays(3))->create($set + ['video_consent' => true, 'price_total' => $price, 'currency' => 'CZK']);
        $order->modelFile->forceFill($tool === null ? ['original_name' => 'cat.stl'] : ['origin' => $tool === 'figure' ? 'generated' : 'tool', 'origin_ref' => $tool === 'figure' ? 'abc' : $tool])->save();
        $disk = Storage::disk('farm');
        foreach (['timelapse_path' => 'timelapse.mp4', 'timelapse_short_path' => 'short.mp4', 'timelapse_wide_path' => 'wide.mp4'] as $column => $file) {
            $disk->put($order->dir().'/'.$file, 'x');
            $order->forceFill([$column => $order->dir().'/'.$file]);
        }
        $order->save();
        $this->photographed($order);

        return $order;
    }

    private function photographed(FarmOrder $order): void
    {
        $dir = 'prints/'.$order->token;
        foreach (['web', 'short', 'square'] as $v) {
            Storage::disk('public')->put($dir.'/photo-'.$v.'.jpg', 'jpeg');
        }
        $order->forceFill(['photo_path' => $dir.'/photo-web.jpg', 'photo_meta' => ['bg' => 'studio', 'cut' => true, 'coverage' => 0.5, 'files' => ['web' => $dir.'/photo-web.jpg', 'short' => $dir.'/photo-short.jpg', 'square' => $dir.'/photo-square.jpg']]])->save();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        YouTubeAccount::firstOrCreate(['channel_id' => 'UC123'], ['channel_title' => 'Matplace 3D', 'refresh_token' => 'r', 'access_token' => 'a', 'access_expires_at' => now()->addHour()]);

        return $admin;
    }

    public function test_the_price_short_takes_photographed_prints_of_different_tools_at_random_and_waits_for_the_admin(): void
    {
        $vase = $this->print('vase', 490);
        $box = $this->print('box', 320);
        $qr = $this->print('qr', 890);
        $medal = $this->print('medallion', 1190);
        $stand = $this->print('phone_stand', 260);
        $vase2 = $this->print('vase', 510);
        $file = $this->print(null, 2200);
        $this->print('figure', 1500);                                         // a face: never
        $this->print('box', 300, null, ['video_consent' => false]);          // no consent
        $noPhoto = $this->print('qr', 400);
        app(PrintPhotos::class)->remove($noPhoto);                           // no composed photo
        $free = $this->print('vase', 0);                                     // no price (a showcase)

        $series = app(SeriesCutter::class);
        $byTool = $series->priceCandidates();
        $this->assertSame(['', 'box', 'medallion', 'phone_stand', 'qr', 'vase'], collect($byTool->keys())->sort()->values()->all());
        $this->assertSame(7, $byTool->sum(fn ($c) => $c->count()));
        $picked = $series->pickForPrice($byTool, 5, seed: 7);
        $this->assertCount(5, $picked);
        $this->assertCount(5, $picked->map(fn (FarmOrder $o) => (string) (app(FarmVideos::class)->toolKind($o) ?? ''))->unique(), 'one of every tool');

        // a customer's own file never shows its file name: the title the admin gave the video, else a plain label
        FarmVideo::create(['farm_order_id' => $file->id, 'kind' => FarmVideo::KIND_SHORT, 'status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => 'cat1', 'title' => 'Kočka z vlastního souboru – 6 h 33 min tisku', 'published_at' => now()]);
        $named = $series->priceShort(collect([$file, $vase2]), Storage::disk('farm')->path('series/price-names.mp4'));
        $names = array_values(array_filter($named->texts, fn ($t, $f) => str_contains($f, '_name_'), ARRAY_FILTER_USE_BOTH));
        $this->assertSame(['Kočka z vlastního souboru', 'Váza bez jediného švu'], array_map(fn ($n) => str_replace('
', ' ', $n), $names), 'the card wraps the words, the title stays');
        FarmVideo::where('youtube_id', 'cat1')->delete();
        $plain = $series->priceShort(collect([$file]), Storage::disk('farm')->path('series/price-names.mp4'));
        $this->assertStringStartsWith('Vlastní model zákazníka', str_replace('
', ' ', array_values(array_filter($plain->texts, fn ($t, $f) => str_contains($f, '_name_'), ARRAY_FILTER_USE_BOTH))[0]), 'no video title yet: a label with the material, never "cat.stl"');
        $this->assertStringNotContainsString('Cat', implode(' ', $plain->texts));

        $cut = $series->priceShort($picked, Storage::disk('farm')->path('series/price-test.mp4'));
        $this->assertSame(FarmVideo::KIND_PRICE, $cut->kind);
        $this->assertEqualsWithDelta(1.2 + 5 * (1 + 4 - 0.3) + 3.5, $cut->seconds, 0.01, 'the opening card, five pieces of a second of growing and four of the photo, the end card');
        $this->assertCount(12, array_keys($cut->inputs, '-i', true), 'a clip and a photo per piece, the logo, the track');
        $this->assertContains('-sseof', $cut->inputs, 'the second of growing comes from the end of the rendered Short');
        $this->assertSame('Artist - Song.mp3', basename((string) $cut->music));
        $prices = array_values(array_filter($cut->texts, fn ($t, $f) => str_contains($f, '_price_'), ARRAY_FILTER_USE_BOTH));
        $this->assertCount(5, $prices);
        $this->assertMatchesRegularExpression('/^Cena: \d/u', $prices[0]);
        $this->assertSame('Kolik stojí 3D tisk?', $cut->texts[Storage::disk('farm')->path('series/price-test_open.txt')]);
        $this->assertSame('5 věcí s cenou', $cut->texts[Storage::disk('farm')->path('series/price-test_open_sub.txt')]);
        $this->assertStringEndsWith('/tools', $cut->texts[Storage::disk('farm')->path('series/price-test_url.txt')]);
        $this->assertStringContainsString('concat=n=7', $cut->graph);
        $this->assertStringContainsString('xfade', $cut->graph);

        $texts = $series->priceTexts($cut, 'Artist - Song.mp3');
        $this->assertSame('Kolik stojí 3D tisk? 5 věcí s cenou', $texts['title']);
        $this->assertStringStartsWith('Udělejte si vlastní: '.rtrim((string) config('app.url'), '/').'/tools?utm_source=youtube&utm_medium=social&utm_campaign=video_price', $texts['description']);
        $this->assertStringContainsString('5 kusů z naší 3D tiskové farmy', $texts['description']);
        $this->assertMatchesRegularExpression('/\n[^\n]+ – \d[^\n]*[\s\x{a0}]Kč – http[^\n]+utm_campaign=video_price/u', $texts['description'], 'one line per piece with its price and its tool');
        $this->assertStringContainsString('Hudba: Artist – Song', $texts['description']);
        $this->assertStringEndsWith('#Shorts #3dtisk #3dtiskarna #kolikstoji #cena', $texts['description']);

        // the command plans it, then (built elsewhere) the row waits for the admin
        $this->artisan('youtube:price-short', ['--n' => 5, '--dry-run' => true, '--seed' => 7])->expectsOutputToContain('7 prints with a photo and a price, 6 tools; picked 5')
            ->expectsOutputToContain('title: Kolik stojí 3D tisk? 5 věcí s cenou')->assertSuccessful();
        $this->assertSame(0, FarmVideo::count(), 'a dry run queues nothing');
        // --check-inputs: every file the Short is cut from (the fixture's stand-ins are there, their lengths unknown without
        // ffmpeg); the table is read through Artisan::output (the test runner's output mock does not see table rows)
        $check = function (): string {
            $this->assertSame(0, Artisan::call('youtube:price-short', ['--n' => 5, '--dry-run' => true, '--seed' => 7, '--check-inputs' => true]));

            return Artisan::output();
        };
        $out = $check();
        foreach (['short.mp4', 'photo-short.jpg', 'from the end -5.7 s, 1.3 s', 'picture × 4.5 s', 'logo.png', 'track, looped', 'every input is there'] as $needle) {
            $this->assertStringContainsString($needle, $out, $needle);
        }
        // without a Short the customer's video is the source (both vases: the draw may take either of them)
        foreach ([$vase, $vase2] as $v) {
            Storage::disk('farm')->delete($v->timelapse_short_path);
            $v->forceFill(['timelapse_short_path' => null])->save();
        }
        $this->assertStringContainsString('from the end -9.5 s', $check(), 'without a Short the customer\'s video is the source');
        // a file gone between the plan and the render (the command itself never picks a print without one)
        Storage::disk('farm')->delete($vase->timelapse_path);
        $rows = app(TimelapseCutter::class)->checkInputs($series->priceShort(collect([$vase, $box]), Storage::disk('farm')->path('series/price-check.mp4')));
        $this->assertSame(['missing'], array_values(array_filter(array_column($rows, 'problem'))));
        $this->assertFalse($rows[0]['exists']);
        $this->assertSame('picture × 4.5 s', $rows[1]['window']);
        Storage::disk('farm')->put($vase->timelapse_path, 'x');
        // a render that fails says what ffmpeg said and, with --keep, leaves the texts next to the video
        config(['farm.ffmpeg' => 'no-such-ffmpeg-binary']);
        $this->artisan('youtube:price-short', ['--n' => 5, '--seed' => 7, '--keep' => true])->expectsOutputToContain('ffmpeg failed:')->expectsOutputToContain('stayed next to the video')->assertFailed();
        $cutter = app(TimelapseCutter::class);
        $this->assertFalse($cutter->render($cut, 30));
        $this->assertNotEmpty($cutter->lastError(), 'what went wrong is kept for the command to print');
        $kept = array_filter(Storage::disk('farm')->files('series'), fn ($f) => str_ends_with($f, '_open.txt'));
        $this->assertCount(1, $kept, 'the open card text stayed');
        $this->assertSame(0, FarmVideo::count(), 'nothing queued after a failed render');
        config(['farm.ffmpeg' => 'ffmpeg']);
        $video = app(FarmVideos::class)->queueCollection(FarmVideo::KIND_PRICE, ['key' => 'price-2026-10-28-1', 'orders' => $picked->pluck('id')->all()], 'series/price-2026-10-28-1.mp4', $texts['title'], $texts['description'], 'Artist - Song.mp3');
        $this->assertSame([FarmVideo::STATUS_PENDING, null, 'series/price-2026-10-28-1.mp4'], [$video->status, $video->farm_order_id, $video->path]);
        $this->assertSame('series/price-2026-10-28-1.mp4', app(FarmVideos::class)->pathOf($video));
        $this->assertTrue(app(FarmVideos::class)->consented($video));
        $this->assertStringContainsString('/tools?utm_source=youtube&utm_medium=social&utm_campaign=video_price', app(FarmVideos::class)->linkOf($video));
        $this->actingAs($this->admin())->get('/admin/youtube')->assertOk()->assertSee('Seriál „Kolik stojí 3D tisk?“')->assertSee('5 kusů s cenou');

        // the next Short of the series takes the prints no Short showed yet before the others
        $rest = $picked->pluck('id')->all();
        $next = $series->pickForPrice($series->priceCandidates(), 5, seed: 3);
        $unseen = $next->take(2)->pluck('id')->all();
        $this->assertCount(0, array_intersect($unseen, $rest), 'the two unseen prints come first');
        $this->assertTrue(in_array($file->id, $unseen, true) || in_array($vase2->id, $unseen, true) || in_array($qr->id, $unseen, true));

        // a customer takes the consent back: the Short with the piece in it says so
        $gone = $picked->first();
        app(FarmVideos::class)->setConsent($gone, false);
        $this->assertStringContainsString('youtube:price-short', (string) $video->refresh()->error);
        $this->assertFalse(app(FarmVideos::class)->consented($video));
    }

    public function test_the_monthly_video_tells_the_steps_between_the_months_prints_with_chapters_and_the_numbers(): void
    {
        foreach (range(1, 8) as $i) {
            $this->print(['vase', 'box', null, 'qr'][$i % 4], 300 + $i, Carbon::parse('2026-10-'.($i * 3).' 10:00', 'Europe/Prague'));
        }
        $this->print('vase', 400, Carbon::parse('2026-09-28 10:00', 'Europe/Prague'));                    // last month
        $this->print('figure', 1500, Carbon::parse('2026-10-20 10:00', 'Europe/Prague'));                  // a face
        $rejected = $this->print('box', 320, Carbon::parse('2026-10-21 10:00', 'Europe/Prague'));
        FarmVideo::create(['farm_order_id' => $rejected->id, 'kind' => FarmVideo::KIND_SHORT, 'status' => FarmVideo::STATUS_REJECTED, 'title' => 'Zamítnuto']);
        $noFile = $this->print('qr', 500, Carbon::parse('2026-10-22 10:00', 'Europe/Prague'));
        Storage::disk('farm')->delete([$noFile->timelapse_path, $noFile->timelapse_wide_path]);

        $series = app(SeriesCutter::class);
        $month = Carbon::create(2026, 10, 1, 0, 0, 0, 'Europe/Prague');
        $orders = $series->monthCandidates($month);
        $this->assertCount(8, $orders, 'the month\'s shareable prints with a file');
        $numbers = ['printed' => 412, 'handed_over_days_median' => 3.0, 'last_30_days_printed' => 37, 'videos_published' => 31];
        $cut = $series->monthly($orders, $month, $numbers, Storage::disk('farm')->path('series/month-2026-10.mp4'));
        $this->assertSame(FarmVideo::KIND_MONTHLY, $cut->kind);
        $fixed = 4 + 5 * 3 + 6 + 4;
        $per = (180 - $fixed - 6 * 3) / 8;
        $this->assertEqualsWithDelta(16.625, $per, 0.001);
        $this->assertEqualsWithDelta(180, $cut->seconds, 0.01, 'a quiet month: the pieces stretch until the video reaches 3 minutes, six photos of 3 s');
        $this->assertCount(8 + 6 + 2, array_keys($cut->inputs, '-i', true), 'eight prints, six photos, the logo, the track');
        $this->assertSame(['0:00 Úvod', '0:04 1. Nahrajete model nebo fotku', sprintf('%d:%02d 2. Cena hned', intdiv((int) (7 + 3 * $per), 60), (int) (7 + 3 * $per) % 60)],
            array_map(fn ($c) => sprintf('%d:%02d %s', intdiv((int) $c[0], 60), (int) $c[0] % 60, $c[1]), array_slice($cut->chapters, 0, 3)), 'three prints under the first step, then the second card');
        $this->assertCount(6, $cut->chapters);
        $this->assertSame("Za posledních 30 dní: 37 výtisků\nCelkem vytištěno: 412 kusů\nOd zaplacení k předání: 3 dny\n31 videí na našem kanálu", $cut->texts[Storage::disk('farm')->path('series/month-2026-10_numbers.txt')]);
        $this->assertSame('říjen 2026', $cut->texts[Storage::disk('farm')->path('series/month-2026-10_month.txt')]);
        $this->assertStringContainsString('-ss', $cut->inputs[0]);
        $this->assertSame('1.5', $cut->inputs[1], 'the 16:9 of a print is taken after its title card');
        $this->assertSame('17.125', $cut->inputs[3], 'the window: the piece plus the half second the trim cuts');

        // a short print measured by ffmpeg: the window never reaches the held piece or the customer's thank-you card
        $brief = $this->print('box', 350, Carbon::parse('2026-10-26 10:00', 'Europe/Prague'));
        Storage::disk('farm')->delete($brief->timelapse_wide_path);
        $brief->forceFill(['timelapse_wide_path' => null])->save();
        $long = $orders->first();
        $tight = $orders->last();
        $this->partialMock(TimelapseCutter::class, function ($mock) use ($brief, $long, $tight) {
            $mock->shouldReceive('duration')->andReturnUsing(fn (string $file) => match (true) {
                str_contains($file, $brief->token) => 18.8,                    // 7 s intro, 3 s held, 3 s photo, 5 s thanks: 0.8 s of print
                str_contains($file, $long->token) => 30.0,                      // a 16:9: 1.5 s title, 2 s held, 3 s photo, 3 s end: 20.5 s of print
                str_contains($file, $tight->token) => 15.0,                     // a 16:9 of a quick print: 5.5 s of growing
                default => null,
            });
        });
        $measured = app(SeriesCutter::class);
        $this->assertFalse($measured->monthCandidates($month)->contains('id', $brief->id), '0.8 s of growing print is too little for a segment');
        $brief->forceFill(['timelapse_wide_path' => $brief->dir().'/wide.mp4'])->save();
        Storage::disk('farm')->put($brief->timelapse_wide_path, 'x');
        $found = $measured->monthCandidates($month);
        $this->assertCount(9, $found);
        $cutMeasured = $measured->monthly($found, $month, $numbers, Storage::disk('farm')->path('series/month-2026-10.mp4'));
        $per9 = (180 - $fixed - 6 * 3) / 9;
        $this->assertEqualsWithDelta(min($per9, 30.0 - 1.5 - 8.0) + 0.5, (float) $cutMeasured->inputs[3], 0.01, 'a measured 16:9 with 20.5 s of growing: the piece takes its share');
        $rows = app(TimelapseCutter::class)->checkInputs($cutMeasured);
        $tightRow = collect($rows)->first(fn ($r) => str_contains($r['file'], $tight->token));
        $this->assertSame('from 1.5 s, 6 s', $tightRow['window'], 'a quick print gives only its 5.5 s of growing, never the held piece or the end card');
        $this->assertEmpty(array_filter($rows, fn ($r) => $r['problem'] !== null), 'no window past the end');
        $this->assertStringContainsString('concat=n=22', $cut->graph, 'intro, five cards, eight prints, six photos, the numbers, the end');

        $texts = $series->monthlyTexts($cut, $month, $numbers, 'Artist - Song.mp3');
        $this->assertSame('Jak funguje 3D tisková farma: od modelu k zásilce (říjen 2026)', $texts['title']);
        $this->assertStringContainsString('jak zjistit cenu 3D tisku', $texts['description']);
        $this->assertStringContainsString('Naše čísla: Za posledních 30 dní: 37 výtisků · Celkem vytištěno: 412 kusů', $texts['description']);
        $this->assertStringContainsString("Kapitoly:\n\n0:00 Úvod\n0:04 1. Nahrajete model nebo fotku", $texts['description']);
        $this->assertStringContainsString('utm_campaign=video_monthly', $texts['description']);
        $this->assertStringEndsWith('#3dtisk #3dtiskarna #3dtiskovafarma #jakfunguje3dtisk #cena3dtisku', $texts['description']);

        $this->artisan('farm:monthly-video', ['--month' => '2026-10', '--dry-run' => true])->expectsOutputToContain('2026-10 (říjen 2026): 9 prints')
            ->expectsOutputToContain('0:04 1. Nahrajete model nebo fotku')->expectsOutputToContain('title: Jak funguje 3D tisková farma')->assertSuccessful();
        $this->artisan('farm:monthly-video', ['--month' => '2026-09', '--dry-run' => true])->expectsOutputToContain('no monthly video')->assertSuccessful();
        $this->artisan('farm:monthly-video', ['--month' => 'říjen'])->assertFailed();
        $this->assertSame(0, FarmVideo::where('kind', FarmVideo::KIND_MONTHLY)->count());

        // a month with more prints than fit: the likely hits stay, the length holds
        foreach (range(9, 30) as $i) {
            $this->print('box', 300, Carbon::parse('2026-10-'.min(28, $i).' 11:00', 'Europe/Prague'));
        }
        $all = $series->monthCandidates($month);
        $fitted = $series->fitMonth($all);
        $this->assertCount((int) floor((300 - $fixed - 6 * 3) / 12), $fitted, 'the photos come off the top too');
        $this->assertLessThanOrEqual(300, $series->monthly($fitted, $month, $numbers, Storage::disk('farm')->path('series/month-2026-10.mp4'))->seconds);
    }

    public function test_the_two_videos_render_for_real_from_rendered_shorts_and_composed_photos(): void
    {
        $ffmpeg = (string) env('FFMPEG_BIN', '');
        if ($ffmpeg === '' || ! is_file($ffmpeg)) {
            $this->markTestSkipped('FFMPEG_BIN not set: rendering needs a real ffmpeg');
        }
        config(['farm.ffmpeg' => $ffmpeg, 'youtube.cut.monthly.min_prints' => 3]);
        Process::run([$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=330:duration=20', $this->music.'/Artist - Song.mp3'])->throw();
        $cutter = app(TimelapseCutter::class);
        $disk = Storage::disk('farm');
        $orders = collect();
        foreach ([['vase', 490], ['box', 320], ['qr', 890]] as $i => [$tool, $price]) {
            $order = $this->print($tool, $price, Carbon::parse('2026-10-'.(5 + $i * 7).' 10:00', 'Europe/Prague'));
            $disk->delete([$order->timelapse_path, $order->timelapse_short_path, $order->timelapse_wide_path]);
            foreach (range(0, 59) as $f) {
                $disk->put(sprintf('%s/frames/%d.jpg', $order->dir(), 1_790_000_000 + $f * 60), $this->picture((int) round($f * 400 / 60), (string) ($f + 1), [60 + $i * 80, 120, 200 - $i * 60]));
            }
            // the piece photographed first (the fixture's stand-in files are no pictures: ffmpeg would hang on them):
            // a dark shape on a light picture, through the fake remover onto the studio backdrop
            Storage::disk('public')->deleteDirectory('prints/'.$order->token);
            app(PrintPhotos::class)->attach($order, $this->photoOf($i));
            $order->refresh();
            $cuts = $cutter->cuts($order);
            $this->assertTrue($cutter->render($cuts['short']), 'the Short renders');
            $this->assertTrue($cutter->render($cuts['wide']), 'the 16:9 renders');
            $this->assertTrue($cutter->render($cuts['customer']), 'the customer\'s video renders');
            $orders->push($order->refresh());
        }

        $this->artisan('youtube:price-short', ['--n' => 3, '--seed' => 1])->expectsOutputToContain('Waiting for approval')->assertSuccessful();
        $short = FarmVideo::where('kind', FarmVideo::KIND_PRICE)->firstOrFail();
        $info = $this->probe($disk->path($short->path));
        $this->assertSame([1080, 1920, true], [$info['width'], $info['height'], $info['audio']]);
        $this->assertEqualsWithDelta(1.2 + 3 * 4.7 + 3.5, $info['seconds'], 0.5);

        $this->artisan('farm:monthly-video', ['--month' => '2026-10'])->expectsOutputToContain('Waiting for approval')->assertSuccessful();
        $monthly = FarmVideo::where('kind', FarmVideo::KIND_MONTHLY)->firstOrFail();
        $info2 = $this->probe($disk->path($monthly->path));
        $this->assertSame([1920, 1080, true], [$info2['width'], $info2['height'], $info2['audio']]);
        $this->assertEqualsWithDelta(4 + 15 + 6 + 4 + 3 * 20 + 3 * 3, $info2['seconds'], 0.8);

        $dir = (string) env('FARM_VIDEO_SAMPLES', '');
        if ($dir !== '' && is_dir($dir)) {
            copy($disk->path($short->path), $dir.'/price-short.mp4');
            copy($disk->path($monthly->path), $dir.'/monthly.mp4');
            foreach ([[$short->path, 0.6, 'price-open'], [$short->path, 2.0, 'price-clip'], [$short->path, 4.5, 'price-piece'], [$short->path, $info['seconds'] - 1, 'price-end'],
                [$monthly->path, 2, 'monthly-intro'], [$monthly->path, 5.5, 'monthly-card'], [$monthly->path, 20, 'monthly-print'], [$monthly->path, $info2['seconds'] - 15, 'monthly-photo'], [$monthly->path, $info2['seconds'] - 7, 'monthly-numbers']] as [$path, $at, $name]) {
                Process::run([$ffmpeg, '-y', '-loglevel', 'error', '-ss', (string) $at, '-i', $disk->path($path), '-frames:v', '1', '-vf', 'scale=960:-2', $dir.'/'.$name.'.jpg'])->throw();
            }
            file_put_contents($dir.'/series-report.json', json_encode(['price' => $info, 'monthly' => $info2], JSON_PRETTY_PRINT));
        }
    }

    // ── helpers ──

    /** A 1280×720 camera picture: a bar of the given height in the piece's colour and a big number. */
    private function picture(int $height, string $label, array $rgb): string
    {
        $im = imagecreatetruecolor(1280, 720);
        imagefill($im, 0, 0, imagecolorallocate($im, 40, 40, 40));
        imagefilledrectangle($im, 560, 620 - $height, 720, 620, imagecolorallocate($im, ...$rgb));
        imagettftext($im, 64, 0, 60, 130, imagecolorallocate($im, 240, 240, 240), base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'), $label);
        ob_start();
        imagejpeg($im, null, 85);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** A photo of a piece as the photo box takes it: a dark shape in the middle of a light picture. */
    private function photoOf(int $i): string
    {
        $path = sys_get_temp_dir().'/mp_series_photo_'.$i.'.jpg';
        $img = imagecreatetruecolor(960, 720);
        imagefill($img, 0, 0, imagecolorallocate($img, 235, 235, 240));
        imagefilledellipse($img, 480, 380, 400, 420, imagecolorallocate($img, 60 + $i * 80, 120, 200 - $i * 60));
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }

    /** @return array{seconds: float, width: int, height: int, audio: bool} */
    private function probe(string $file): array
    {
        $info = Process::run([(string) config('farm.ffmpeg'), '-hide_banner', '-i', $file])->errorOutput();
        $this->assertMatchesRegularExpression('/Duration: \d/', $info, $info);
        preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/', $info, $d);
        preg_match('/Video: h264.*?, (\d{2,5})x(\d{2,5})/', $info, $s);

        return ['seconds' => round($d[1] * 3600 + $d[2] * 60 + (float) $d[3], 2), 'width' => (int) ($s[1] ?? 0), 'height' => (int) ($s[2] ?? 0), 'audio' => str_contains($info, 'Audio: aac')];
    }
}
