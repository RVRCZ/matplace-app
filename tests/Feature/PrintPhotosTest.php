<?php

namespace Tests\Feature;

use App\Domain\Farm\PrintPhotos;
use App\Domain\Farm\PublicStats;
use App\Domain\Farm\TestPhotos;
use App\Domain\YouTube\TimelapseCutter;
use App\Engines\Photo\BackgroundRemover;
use App\Engines\Photo\FakeBackgroundRemover;
use App\Jobs\AddFinishPhotoToVideo;
use App\Models\FarmOrder;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The photo of a finished print made presentable (App\Domain\Farm\PrintPhotos, TODO #48): the background off (the drawn
 * fake stands in for rembg), the piece on a backdrop in every size, on the order, on its pages, at the end of its
 * videos, in the trust block; the command for one order and for a whole folder. Needs the engines' Python with Pillow.
 */
final class PrintPhotosTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('farm');
        Storage::fake('public');
        $this->seed(FarmSeeder::class);
        FakeBackgroundRemover::reset();
        $this->tmp = sys_get_temp_dir().'/mp_photo_'.uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /** A photo of a piece: a dark object in the middle of a light picture, as the photo box sees it. */
    private function photo(string $name = 'piece.jpg', int $w = 640, int $h = 480): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 235, 235, 240));
        imagefilledellipse($img, (int) ($w / 2), (int) ($h / 2), (int) ($w * 0.5), (int) ($h * 0.6), imagecolorallocate($img, 40, 90, 160));
        imagejpeg($img, $this->tmp.'/'.$name, 90);
        imagedestroy($img);

        return $this->tmp.'/'.$name;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    public function test_a_photo_is_cut_put_on_a_backdrop_in_every_size_and_remembered_on_the_order(): void
    {
        $order = FarmOrder::factory()->paid()->done()->create();
        $photos = app(PrintPhotos::class);
        $this->assertTrue($photos->available(), 'the fake remover stands in for rembg');
        $meta = $photos->attach($order, $this->photo(), 'wood');
        $this->assertTrue($meta['cut']);
        $this->assertSame('wood', $meta['bg']);
        $this->assertGreaterThan(0.3, $meta['coverage']);
        $this->assertSame(['cutout', 'web', 'webp', 'short', 'square'], array_keys($meta['files']));
        $order->refresh();
        $this->assertSame('prints/'.$order->token.'/photo-web.jpg', $order->photo_path);
        foreach (['web' => [1200, 800], 'short' => [1080, 1920], 'square' => [1080, 1080]] as $variant => [$w, $h]) {
            $file = PrintPhotos::file($order, $variant);
            $this->assertNotNull($file, $variant);
            $this->assertSame([$w, $h], array_slice(getimagesize($file), 0, 2), $variant);
            $this->assertStringContainsString('/storage/prints/'.$order->token.'/photo-'.$variant.'.jpg', (string) $order->photoUrl($variant));
        }
        $this->assertNotNull(PrintPhotos::file($order, 'cutout'));
        $this->assertSame(IMAGETYPE_PNG, getimagesize(PrintPhotos::file($order, 'cutout'))[2]);
        $this->assertSame(IMAGETYPE_WEBP, getimagesize(PrintPhotos::file($order, 'webp'))[2]);
        $this->assertTrue($order->hasPhoto());
        // the piece stands in the lower part of the web render, the backdrop above it is light (the texture)
        $web = imagecreatefromjpeg(PrintPhotos::file($order, 'web'));
        $top = imagecolorsforindex($web, imagecolorat($web, 600, 40));
        $this->assertGreaterThan(120, $top['red'], 'the wood above the piece is lighter than the piece');
        imagedestroy($web);

        // another photo replaces the first; the files of the first are gone
        $first = $meta['files']['web'];
        $photos->attach($order, $this->photo('second.jpg', 480, 640), 'white');
        $this->assertSame('white', $order->fresh()->photo_meta['bg']);
        $this->assertCount(5, Storage::disk('public')->files('prints/'.$order->token));
        $this->assertSame($first, $order->fresh()->photo_meta['files']['web'], 'the same names, the new picture');
        // and goes
        $photos->remove($order);
        $this->assertNull($order->fresh()->photo_path);
        $this->assertFalse($order->fresh()->hasPhoto());
        $this->assertSame([], Storage::disk('public')->files('prints/'.$order->token));
    }

    public function test_without_a_background_remover_the_photo_is_fitted_as_it_is(): void
    {
        config(['engines.photo' => 'none']);
        $this->app->forgetInstance(PrintPhotos::class);
        $this->app->forgetInstance(BackgroundRemover::class);
        $photos = app(PrintPhotos::class);
        $this->assertFalse($photos->available());
        $order = FarmOrder::factory()->paid()->done()->create();
        $meta = $photos->attach($order, $this->photo());
        $this->assertFalse($meta['cut']);
        $this->assertNull($meta['coverage']);
        $this->assertSame(['web', 'webp', 'short', 'square'], array_keys($meta['files']), 'no cut-out');
        $this->assertSame([1080, 1920], array_slice(getimagesize(PrintPhotos::file($order, 'short')), 0, 2));
    }

    public function test_the_command_serves_one_order_and_a_whole_folder(): void
    {
        $order = FarmOrder::factory()->paid()->done()->create(['number' => 'F26-000777']);
        $this->artisan('farm:photo', ['--probe' => true])->expectsOutputToContain('available')->assertSuccessful();
        $this->artisan('farm:photo', ['path' => $this->photo(), '--order' => 'F26-000777', '--bg' => 'paper'])->assertSuccessful();
        $order->refresh();
        $this->assertTrue($order->hasPhoto());
        $this->assertSame('paper', $order->photo_meta['bg']);
        $this->artisan('farm:photo', ['path' => $this->photo(), '--order' => 'F26-000000'])->assertFailed();
        $this->artisan('farm:photo', ['path' => $this->photo(), '--order' => 'F26-000777', '--bg' => 'neon'])->assertFailed();

        // the batch: three photos of a camera roll, two of one day, into a folder
        $this->photo('WIN_20261010_19_58_43_Pro.jpg');
        $this->photo('WIN_20261010_20_04_12_Pro.jpg');
        $this->photo('WIN_20261011_09_00_00_Pro.jpg');
        $out = $this->tmp.'/out';
        $this->artisan('farm:photo', ['path' => $this->tmp, '--match' => '20261010', '--out' => $out])->expectsOutputToContain('2 photos')->assertSuccessful();
        $files = array_map('basename', glob($out.'/*'));
        sort($files);
        $this->assertSame(['WIN_20261010_19_58_43_Pro-cutout.png', 'WIN_20261010_19_58_43_Pro-short.jpg', 'WIN_20261010_19_58_43_Pro-square.jpg', 'WIN_20261010_19_58_43_Pro-web.jpg', 'WIN_20261010_19_58_43_Pro-web.webp',
            'WIN_20261010_20_04_12_Pro-cutout.png', 'WIN_20261010_20_04_12_Pro-short.jpg', 'WIN_20261010_20_04_12_Pro-square.jpg', 'WIN_20261010_20_04_12_Pro-web.jpg', 'WIN_20261010_20_04_12_Pro-web.webp'], $files);
    }

    public function test_the_admin_uploads_a_photo_the_worker_composes_it_and_the_customer_and_the_videos_see_it(): void
    {
        $customer = User::factory()->create();
        $order = FarmOrder::factory()->paid()->done()->create(['user_id' => $customer->id]);
        $this->actingAs($customer)->getJson("/farm/orders/{$order->token}/status")->assertOk()->assertJsonPath('photo_url', null);
        $this->actingAs($this->admin())->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee('Nahrát fotku výtisku')->assertSee('Zatím žádná');

        $upload = new UploadedFile($this->photo('phone.jpg'), 'IMG_0001.jpg', 'image/jpeg', null, true);
        $this->actingAs($this->admin())->post(route('admin.farm.orders.print_photo', $order), ['photo' => $upload, 'bg' => 'studio'])->assertRedirect();
        Storage::disk('farm')->assertExists($order->dir().'/photos/print-source.jpg');
        $order->refresh();   // sync queue: the worker's job already ran
        $this->assertTrue($order->hasPhoto());
        $this->assertSame('studio', $order->photo_meta['bg']);
        $this->assertSame([FakeBackgroundRemover::$calls[0]], FakeBackgroundRemover::$calls, 'cut once');

        // the customer's page and its state show the photo
        $url = $this->actingAs($customer)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('photo_url');
        $this->assertStringContainsString('/storage/prints/'.$order->token.'/photo-web.jpg', $url);
        $this->actingAs($customer)->get("/farm/orders/{$order->token}")->assertOk()->assertSee('farm-photo-box')->assertSee(__('farm.order.photo_of_print'));
        $this->actingAs($this->admin())->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee('pozadí odstraněno');

        // the videos end with the composed photo: the 9:16 render for the vertical Short, the web render elsewhere
        Storage::disk('farm')->put($order->dir().'/frames/f0001.jpg', file_get_contents($this->photo('frame.jpg', 1280, 720)));
        foreach (range(2, 60) as $i) {
            Storage::disk('farm')->copy($order->dir().'/frames/f0001.jpg', $order->dir().'/frames/f'.str_pad((string) $i, 4, '0', STR_PAD_LEFT).'.jpg');
        }
        $cuts = app(TimelapseCutter::class)->cuts($order->fresh());
        $this->assertNotNull($cuts);
        $this->assertContains(PrintPhotos::file($order, 'short'), $cuts['short']->inputs);
        $this->assertContains(PrintPhotos::file($order, 'web'), $cuts['wide']->inputs);
        $this->assertContains(PrintPhotos::file($order, 'web'), $cuts['customer']->inputs);

        // a bad file is refused before anything runs
        $this->actingAs($this->admin())->post(route('admin.farm.orders.print_photo', $order), ['photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertSessionHasErrors('photo');
    }

    public function test_the_photo_box_photo_of_a_customer_print_is_composed_for_its_videos_and_older_ones_can_be_redone(): void
    {
        Queue::fake([AddFinishPhotoToVideo::class]);   // the compose job runs at once (sync); the rebuild of the videos is only counted
        $admin = $this->admin();
        $order = FarmOrder::factory()->paid()->done()->create(['timelapse_path' => 'orders/x/timelapse.mp4']);
        $upload = fn (string $name) => new UploadedFile($this->photo($name), $name, 'image/jpeg', null, true);
        $post = fn (FarmOrder $o, string $name, string $view) => $this->actingAs($admin)->post(route('admin.farm.photos.store', $o), ['photos' => [$upload($name)], 'views' => [$view]])->assertRedirect()->assertSessionHas('status');

        // the side view from the photo box: cut, on the studio backdrop, remembered with its source, the videos built again
        $post($order, 'left.jpg', 'left');
        $order->refresh();
        $this->assertTrue($order->hasPhoto());
        $this->assertSame($order->test_params['photos'][0]['file'], $order->photo_meta['source']);
        $this->assertSame('studio', $order->photo_meta['bg']);
        $this->assertCount(1, FakeBackgroundRemover::$calls);
        Queue::assertPushed(AddFinishPhotoToVideo::class, 1);
        $this->actingAs($admin)->get(route('admin.farm.photobox', ['order' => $order->token]))->assertOk()->assertSee('do videa');

        // another view of the same piece changes nothing: the top view, and a second side view while the left one
        // (the videos end with the left view first, then the right, the phone, the top) is there
        $post($order, 'top.jpg', 'top');
        $post($order, 'right.jpg', 'right');
        $this->assertCount(1, FakeBackgroundRemover::$calls, 'the videos still end with the left view');
        Queue::assertPushed(AddFinishPhotoToVideo::class, 1);

        // the photo it was made from goes: made again from the next one (the right view); a view that was not the
        // source changes nothing; the last one gone, the piece has no photo and the videos are built without it
        $this->actingAs($admin)->post(route('admin.farm.photos.destroy', [$order, 0]))->assertRedirect();
        $order->refresh();
        $this->assertSame($order->test_params['photos'][1]['file'], $order->photo_meta['source'], 'now the right view');
        $this->assertStringContainsString('-right-', $order->photo_meta['source']);
        $this->assertCount(2, FakeBackgroundRemover::$calls);
        Queue::assertPushed(AddFinishPhotoToVideo::class, 2);
        $this->actingAs($admin)->post(route('admin.farm.photos.destroy', [$order, 0]))->assertRedirect();   // the top view
        $this->assertCount(2, FakeBackgroundRemover::$calls);
        $this->assertTrue($order->refresh()->hasPhoto());
        $this->actingAs($admin)->post(route('admin.farm.photos.destroy', [$order, 0]))->assertRedirect();   // the right view
        $order->refresh();
        $this->assertFalse($order->hasPhoto());
        $this->assertNull($order->photo_path);
        Queue::assertPushed(AddFinishPhotoToVideo::class, 3);

        // the redo for prints photographed before this existed: one without a composed photo, one fitted with its
        // background on (rembg had no model that day), one cut already
        $none = FarmOrder::factory()->paid()->done()->create(['timelapse_path' => 'orders/y/timelapse.mp4']);
        $fitted = FarmOrder::factory()->paid()->done()->create();
        $cut = FarmOrder::factory()->paid()->done()->create();
        $add = fn (FarmOrder $o, string $name) => app(TestPhotos::class)->add($o, $this->photo($name), 'left')['file'];
        $add($none, 'none.jpg');
        $fittedFile = $add($fitted, 'fitted.jpg');
        Storage::disk('public')->put('prints/'.$fitted->token.'/photo-web.jpg', 'jpeg');
        $fitted->forceFill(['photo_path' => 'prints/'.$fitted->token.'/photo-web.jpg', 'photo_meta' => ['bg' => 'studio', 'cut' => false, 'coverage' => null, 'source' => $fittedFile, 'files' => ['web' => 'prints/'.$fitted->token.'/photo-web.jpg']]])->save();
        $post($cut, 'cut.jpg', 'right');
        $calls = count(FakeBackgroundRemover::$calls);

        // the plan names the two and touches nothing
        $this->artisan('farm:photo', ['--redo-videos' => true])->expectsOutputToContain('2 to compose')->expectsOutputToContain('kept')->assertSuccessful();
        $this->assertFalse($none->refresh()->hasPhoto());
        $this->assertCount($calls, FakeBackgroundRemover::$calls);
        $this->artisan('farm:photo', ['--redo-videos' => true, '--all' => true])->expectsOutputToContain('3 to compose')->assertSuccessful();

        $this->artisan('farm:photo', ['--redo-videos' => true, '--apply' => true])->expectsOutputToContain('2 composed')->assertSuccessful();
        $this->assertTrue($none->refresh()->hasPhoto());
        $this->assertTrue($fitted->refresh()->photo_meta['cut']);
        $this->assertSame($fittedFile, $fitted->photo_meta['source'], 'made from the same photo');
        $this->assertCount($calls + 2, FakeBackgroundRemover::$calls, 'the cut one is kept');
        Queue::assertPushed(AddFinishPhotoToVideo::class, 4);   // only the print with a time-lapse has videos to build
        $this->artisan('farm:photo', ['--redo-videos' => true])->expectsOutputToContain('0 to compose')->assertSuccessful();

        // /admin/youtube says the videos end with the composed photo
        $this->actingAs($admin)->get(route('admin.farm.orders.show', $none))->assertOk()->assertSee('pozadí odstraněno');
    }

    public function test_a_cut_that_keeps_nothing_or_everything_is_a_failed_cut_and_the_photo_is_fitted(): void
    {
        $photos = app(PrintPhotos::class);
        $admin = $this->admin();
        $order = FarmOrder::factory()->paid()->done()->create();
        FakeBackgroundRemover::$coverage = 0.004;   // what rembg made of F26-000097 on 27 Oct 2026: an empty mask
        $meta = $photos->attach($order, $this->photo('empty.jpg'));
        $this->assertFalse($meta['cut']);
        $this->assertStringStartsWith('empty', $meta['cut_error']);
        $this->assertSame(0.004, $meta['coverage'], 'the share stays for the record');
        $this->assertSame(['web', 'webp', 'short', 'square'], array_keys($meta['files']), 'no cut-out kept');
        $this->assertSame('uncut', PrintPhotos::cutState($meta));
        $this->assertSame([1200, 800], array_slice(getimagesize(PrintPhotos::file($order, 'web')), 0, 2), 'fitted as it is');
        FakeBackgroundRemover::$coverage = 0.995;
        $this->assertStringStartsWith('whole', $photos->attach($order, $this->photo('whole.jpg'))['cut_error']);
        $this->actingAs($admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee('výřez nic nenašel');

        // the photo box does not try again for another view of the same piece
        FakeBackgroundRemover::$coverage = 0.0;
        $box = FarmOrder::factory()->paid()->done()->create();
        $upload = fn (string $name) => new UploadedFile($this->photo($name), $name, 'image/jpeg', null, true);
        $this->actingAs($admin)->post(route('admin.farm.photos.store', $box), ['photos' => [$upload('box-left.jpg')], 'views' => ['left']])->assertRedirect();
        $this->assertSame('uncut', PrintPhotos::cutState($box->refresh()->photo_meta));
        $tries = count(FakeBackgroundRemover::$calls);
        $this->actingAs($admin)->post(route('admin.farm.photos.store', $box), ['photos' => [$upload('box-top.jpg')], 'views' => ['top']])->assertRedirect();
        $this->assertCount($tries, FakeBackgroundRemover::$calls, 'settled: the remover had its go');

        // a photo cut before the threshold existed (an empty backdrop on the order and in the video): the redo takes
        // it, this time fitted; afterwards left alone unless named by --ids or --all
        $legacy = FarmOrder::factory()->paid()->done()->create(['number' => 'F26-000097']);
        $file = app(TestPhotos::class)->add($legacy, $this->photo('legacy.jpg'), 'top')['file'];
        $photos->attach($legacy, Storage::disk('farm')->path($file), null, $file);
        $legacy->forceFill(['photo_meta' => ['cut' => true, 'cut_error' => null] + $legacy->photo_meta])->save();   // as the 27 Oct run stored it
        $this->assertSame('empty', PrintPhotos::cutState($legacy->photo_meta));
        $this->artisan('farm:photo', ['--redo-videos' => true])->expectsOutputToContain('empty')->expectsOutputToContain('1 to compose')->assertSuccessful();
        $this->artisan('farm:photo', ['--redo-videos' => true, '--apply' => true, '--ids' => 'F26-000097'])->expectsOutputToContain('1 composed')->expectsOutputToContain('fitted – empty')->assertSuccessful();
        $legacy->refresh();
        $this->assertFalse($legacy->photo_meta['cut']);
        $this->assertSame('uncut', PrintPhotos::cutState($legacy->photo_meta));
        $this->assertSame($file, $legacy->photo_meta['source']);
        $this->artisan('farm:photo', ['--redo-videos' => true])->expectsOutputToContain('0 to compose')->assertSuccessful();
        $this->artisan('farm:photo', ['--redo-videos' => true, '--ids' => 'F26-000097,F26-999999'])->expectsOutputToContain('1 to compose')->expectsOutputToContain('No order F26-999999')->assertSuccessful();
        FakeBackgroundRemover::$coverage = null;
    }

    public function test_the_trust_block_gets_the_latest_photographed_prints(): void
    {
        Cache::flush();
        $this->assertSame([], PublicStats::latestPhotos());
        $old = FarmOrder::factory()->paid()->handedOver(now()->subDays(10))->create(['number' => 'F26-000001']);
        $new = FarmOrder::factory()->paid()->done(now()->subDay())->create(['number' => 'F26-000002']);
        $unpaid = FarmOrder::factory()->done()->create(['kind' => FarmOrder::KIND_SHOWCASE]);
        $photos = app(PrintPhotos::class);
        foreach ([$old, $new, $unpaid] as $o) {
            $photos->attach($o, $this->photo());
        }
        FarmOrder::factory()->paid()->done()->create();   // printed, no photo
        PublicStats::forget();
        $list = PublicStats::latestPhotos(8);
        $this->assertSame(['F26-000002', 'F26-000001'], array_column($list, 'number'), 'newest first, paid prints with a photo only');
        $this->assertSame(['url', 'webp', 'short', 'tool', 'number', 'at'], array_keys($list[0]));
        $this->assertStringContainsString('photo-web.jpg', $list[0]['url']);
        $this->assertStringContainsString('photo-short.jpg', (string) $list[0]['short']);
        $this->assertNull($list[0]['tool'], 'a customer\'s own file names no tool');
        $this->assertCount(1, PublicStats::latestPhotos(1));
    }
}
