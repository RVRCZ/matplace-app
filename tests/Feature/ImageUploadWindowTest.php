<?php

namespace Tests\Feature;

use App\Domain\Stats\CampaignFunnel;
use App\Domain\Tools\ArtworkCleaner;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\Generator\FakeGenerator;
use App\Http\Controllers\Api\StepController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The picture-upload window of the image tools (21 Nov 2026): which tools get it, its measuring steps, the optional
 * redrawing of a photographed logo behind AI_CLEAN_ARTWORK (off by default), the 60 mm charm and key ring.
 */
class ImageUploadWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // the uploads of these tests lie in storage/app/artwork under the owner's id: not left for the next test of the library
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/artwork'));
        parent::tearDown();
    }

    private function upload(): string
    {
        $img = imagecreatetruecolor(60, 40);
        imagefill($img, 0, 0, imagecolorallocate($img, 230, 230, 230));
        $path = sys_get_temp_dir().'/mp_imgwin_'.uniqid().'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($path, 'logo.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    private function visitor(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function fakeGenerator(): void
    {
        config(['engines.generator' => 'fake']);
        $this->app->singleton(ModelGenerator::class, fn () => new FakeGenerator);
    }

    public function test_the_window_steps_pass_the_whitelist(): void
    {
        foreach (['img_seen', 'img_pick', 'img_camera', 'img_close', 'img_qty'] as $step) {
            $this->assertContains($step, StepController::STEPS);
            $this->postJson('/api/step', ['tool' => 'charm', 'step' => $step])->assertNoContent();
        }
        // what was there stays
        foreach (['scroll', 'pick', 'photo', 'o_color', 'o_left', 'dl_click'] as $step) {
            $this->assertContains($step, StepController::STEPS);
        }
        $this->postJson('/api/step', ['tool' => 'charm', 'step' => 'img_bogus'])->assertUnprocessable();
    }

    public function test_the_funnel_counts_the_window_steps(): void
    {
        $this->assertSame(['img_seen', 'img_pick', 'img_camera', 'img_close', 'img_qty'], array_keys(CampaignFunnel::WINDOW));
    }

    public function test_charm_and_key_ring_open_at_60_mm_and_the_others_stay(): void
    {
        $this->assertSame(60, ParametricGenerator::FIELDS['charm']['width'][2]);
        $this->assertSame(60, ParametricGenerator::FIELDS['keychain']['width'][2]);
        $this->assertSame(60, ParametricGenerator::PRESETS['charm']['matplace']['width']);
        $this->assertSame(100, ParametricGenerator::FIELDS['coaster']['width'][2]);
        $this->assertSame(80, ParametricGenerator::FIELDS['ornament']['width'][2]);
    }

    public function test_the_window_is_configured_on_the_picture_tools_only(): void
    {
        foreach (['charm', 'coaster', 'keychain', 'ornament', 'relief', 'filament-art'] as $tool) {
            $html = $this->get('/tools/'.$tool)->assertOk()->getContent();
            $this->assertStringContainsString('imgwin\u0022:{\u0022clean\u0022:false', $html, $tool);
        }
        $this->assertStringContainsString('imgwin\u0022:null', $this->get('/tools/box')->assertOk()->getContent());
        foreach (['bust', 'figure', 'pet'] as $own) {
            $this->assertNotContains($own, ParametricGenerator::IMAGE_WINDOW);
        }
    }

    public function test_the_window_texts_exist_in_three_languages(): void
    {
        $keys = array_keys(json_decode((string) file_get_contents(base_path('lang/src/image_upload_window.json')), true, 512, JSON_THROW_ON_ERROR));
        $this->assertContains('imgwin.title', $keys);
        foreach (['cs', 'en', 'es'] as $lang) {
            $all = json_decode((string) file_get_contents(base_path('lang/'.$lang.'.json')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $all, $lang.' '.$key);
                $this->assertNotSame('', $all[$key]);
            }
        }
    }

    public function test_the_ai_cleaning_is_off_by_default(): void
    {
        $this->assertFalse((bool) config('ai.clean_artwork'));
        $this->visitor();
        $this->fakeGenerator();
        $this->assertFalse(ArtworkCleaner::enabled());
        $this->postJson('/api/tools/artwork/clean', ['artwork' => $this->upload()])->assertStatus(409)->assertJson(['error' => 'off']);
    }

    public function test_the_cleaning_redraws_the_upload_and_keeps_it_among_my_pictures(): void
    {
        config(['ai.clean_artwork' => true]);
        $this->visitor();
        $this->fakeGenerator();
        FakeGenerator::$restyled = [];
        $ref = $this->upload();
        $task = $this->postJson('/api/tools/artwork/clean', ['artwork' => $ref])->assertStatus(202)->json('task');
        $this->assertSame([ArtworkCleaner::PROMPT], FakeGenerator::$restyled);
        $done = $this->getJson('/api/tools/artwork/clean/'.$task)->assertOk()->assertJson(['state' => 'done'])->json('artwork');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $done);
        $this->assertNotSame($ref, $done);
        $this->get('/api/artwork/file/'.$done)->assertOk();
        // the task is used up
        $this->getJson('/api/tools/artwork/clean/'.$task)->assertJson(['state' => 'failed']);
    }

    public function test_a_failed_cleaning_says_so_and_a_library_picture_is_not_redrawn(): void
    {
        config(['ai.clean_artwork' => true]);
        $this->visitor();
        $this->fakeGenerator();
        FakeGenerator::$imageFails = true;
        try {
            $task = $this->postJson('/api/tools/artwork/clean', ['artwork' => $this->upload()])->assertStatus(202)->json('task');
            $this->getJson('/api/tools/artwork/clean/'.$task)->assertJson(['state' => 'failed']);
        } finally {
            FakeGenerator::$imageFails = false;
        }
        $this->postJson('/api/tools/artwork/clean', ['artwork' => 'lib:colour/matplace'])->assertUnprocessable();
        $this->postJson('/api/tools/artwork/clean', ['artwork' => '11111111-1111-1111-1111-111111111111'])->assertStatus(409)->assertJson(['error' => 'no_picture']);
    }

    public function test_the_cleaning_has_a_daily_limit_per_visitor(): void
    {
        config(['ai.clean_artwork' => true, 'ai.artwork_clean.per_visitor' => 1]);
        Cache::flush();
        $this->visitor();
        $this->fakeGenerator();
        $this->postJson('/api/tools/artwork/clean', ['artwork' => $this->upload()])->assertStatus(202);
        $this->postJson('/api/tools/artwork/clean', ['artwork' => $this->upload()])->assertStatus(429)->assertJson(['error' => 'limit']);
    }
}
