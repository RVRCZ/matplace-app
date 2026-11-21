<?php

namespace Tests\Feature;

use App\Domain\Stats\CampaignFunnel;
use App\Domain\Tools\ArtworkCleaner;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\Generator\FakeGenerator;
use App\Http\Controllers\Api\StepController;
use App\Models\User;
use App\Support\PreviewMeta;
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
        foreach (['charm', 'coaster', 'keychain', 'ornament', 'relief', 'filament-art', 'sign', 'medallion', 'cookie-cutter'] as $tool) {
            $html = $this->get('/tools/'.$tool)->assertOk()->getContent();
            $this->assertStringContainsString('imgwin\u0022:{\u0022clean\u0022:false', $html, $tool);
        }
        $this->assertStringContainsString('imgwin\u0022:null', $this->get('/tools/box')->assertOk()->getContent());
        foreach (['bust', 'figure', 'pet'] as $own) {
            $this->assertNotContains($own, ParametricGenerator::IMAGE_WINDOW);
        }
    }

    /** Three blocks of colour on a light ground: what a photographed logo is to the pipeline. */
    private function colourful(): string
    {
        $img = imagecreatetruecolor(400, 240);
        imagefill($img, 0, 0, imagecolorallocate($img, 245, 245, 245));
        imagefilledrectangle($img, 20, 20, 190, 220, imagecolorallocate($img, 200, 30, 30));
        imagefilledrectangle($img, 200, 20, 380, 120, imagecolorallocate($img, 30, 60, 190));
        imagefilledrectangle($img, 200, 130, 380, 220, imagecolorallocate($img, 25, 25, 28));
        $path = sys_get_temp_dir().'/mp_imgwin_c_'.uniqid().'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($path, 'logo.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    /** A black disc: the outline a cutter takes. */
    private function disc(): string
    {
        $img = imagecreatetruecolor(300, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledellipse($img, 150, 150, 180, 180, imagecolorallocate($img, 0, 0, 0));
        $path = sys_get_temp_dir().'/mp_imgwin_d_'.uniqid().'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($path, 'disc.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    private function preview(string $kind, array $params)
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'view' => 'print'])->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
    }

    private function needsEngine(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    public function test_an_uploaded_picture_becomes_the_sign(): void
    {
        $this->needsEngine();
        $this->visitor();
        $art = $this->colourful();
        $text = $this->preview('sign', ['line1' => 'Jana', 'style' => 'emboss']);
        $picture = $this->preview('sign', ['style' => 'picture', 'artwork' => $art, 'width' => 90, 'colors_n' => 3, 'line1' => '']);
        // the plate is the picture: 90 mm wide, in the proportions of the picture, in several colours
        $this->assertEqualsWithDelta(90, $picture['bbox']['x'], 1.5);
        $this->assertGreaterThan(1, (int) ($picture['notes']['found'] ?? 0));
        $this->assertNotEquals($text['bbox'], $picture['bbox']);
        // the width is the visitor's
        $narrow = $this->preview('sign', ['style' => 'picture', 'artwork' => $art, 'width' => 60, 'colors_n' => 3, 'line1' => '']);
        $this->assertLessThan($picture['bbox']['x'], $narrow['bbox']['x']);
        // a text is optional and stands under the picture, which makes the sign taller
        $captioned = $this->preview('sign', ['style' => 'picture', 'artwork' => $art, 'width' => 90, 'colors_n' => 3, 'line1' => 'Pizza']);
        $this->assertGreaterThan($picture['bbox']['y'], $captioned['bbox']['y']);
        // without a picture a picture sign has nothing to be
        $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['style' => 'picture', 'line1' => '']])->assertStatus(422);
    }

    public function test_an_uploaded_picture_gives_the_cutter_its_outline(): void
    {
        $this->needsEngine();
        $this->visitor();
        $text = $this->preview('cutter', ['line1' => 'Ela', 'width' => 70]);
        $disc = $this->preview('cutter', ['artwork' => $this->disc(), 'width' => 70]);
        $size = fn (array $m) => [$m['bbox']['x'], $m['bbox']['y']];
        // a disc is as tall as it is wide; the word is a long strip
        $this->assertEqualsWithDelta($size($disc)[0], $size($disc)[1], 1.5);
        $this->assertNotEquals($size($text), $size($disc));
    }

    public function test_the_medal_takes_the_picture_in_layers_or_as_a_relief_of_the_photo(): void
    {
        $this->needsEngine();
        $this->visitor();
        $art = $this->colourful();
        $number = $this->preview('medallion', ['line1' => '1']);
        $layers = $this->preview('medallion', ['artwork' => $art, 'line1' => '', 'colors_n' => 3, 'motif' => 'flush']);
        $this->assertGreaterThan(1, (int) ($layers['notes']['found'] ?? 0));
        $this->assertLessThan((int) $layers['notes']['found'], (int) ($number['notes']['found'] ?? 0));
        // the other way: the photo as a relief on the front (the decoration of the photo, docs/AR.md)
        $relief = $this->preview('medallion', ['line1' => '', 'decor' => [['kind' => 'photo', 'face' => 'front', 'image' => $art, 'image_name' => 'logo.png']]]);
        $this->assertNotEquals($number['volume_mm3'], $relief['volume_mm3']);
    }

    /** The sign's text is `required` in the page, but only until the style is a picture: the script flips it (applyWhen), so the preview is not silently held back. */
    public function test_the_sign_text_is_optional_for_a_picture_in_the_page_itself(): void
    {
        $html = $this->get('/tools/sign')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input data-text="line1"[^>]*required[^>]*data-req[^>]*data-optional-when="style=picture"/', $html);
        // a field that is optional on the server too stays without the mark
        $this->assertDoesNotMatchRegularExpression('/<input data-text="line2"[^>]*required/', $html);
        // and the style is offered
        $this->assertStringContainsString('value="picture"', $html);
        $js = (string) file_get_contents(base_path('resources/js/calc/param.ts'));
        $this->assertStringContainsString("[data-req]", $js);
        $this->assertStringContainsString('optionalWhen', $js);
    }

    /** A picture of a red disc and a blue square on white, the logo Roman tried the sign with. */
    private function redBlue(): string
    {
        $img = imagecreatetruecolor(400, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledellipse($img, 110, 150, 150, 150, imagecolorallocate($img, 210, 30, 30));
        imagefilledrectangle($img, 230, 70, 370, 230, imagecolorallocate($img, 30, 60, 200));
        $path = sys_get_temp_dir().'/mp_imgwin_rb_'.uniqid().'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($path, 'rb.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    /**
     * The colours of a sign that is a picture are the picture's own, as on the charm (Roman, 21 Nov 2026: red disc and blue
     * square came out white on a green plate): the model says which part is which colour of the picture and which filament
     * it prints in, and the page treats the sign as a picture tool while its style is `picture`.
     */
    public function test_the_sign_that_is_a_picture_carries_the_colours_of_the_picture(): void
    {
        $this->needsEngine();
        $this->visitor();
        $art = $this->redBlue();
        $sign = $this->preview('sign', ['style' => 'picture', 'artwork' => $art, 'width' => 90, 'colors_n' => 3, 'line1' => '']);
        $charm = $this->preview('charm', ['body' => 'rect', 'artwork' => $art, 'width' => 90, 'colors_n' => 3, 'remove_bg' => false, 'flush' => false, 'rim' => false, 'eyelet' => false, 'line1' => '']);
        // the same parts and the same colours as the charm made of this picture, not the sign's plate and lettering
        $this->assertSame(array_column($charm['notes']['colors'], 'part'), array_column($sign['notes']['colors'], 'part'));
        $this->assertSame($charm['notes']['paint'] ?? null, $sign['notes']['paint'] ?? null);
        $near = function (array $colors, array $rgb): bool {
            foreach ($colors as $c) {
                $hex = ltrim((string) $c['rgb'], '#');
                $v = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
                if (sqrt(($v[0] - $rgb[0]) ** 2 + ($v[1] - $rgb[1]) ** 2 + ($v[2] - $rgb[2]) ** 2) < 90) {
                    return true;
                }
            }

            return false;
        };
        $this->assertTrue($near($sign['notes']['colors'], [210, 30, 30]), 'the red of the picture');
        $this->assertTrue($near($sign['notes']['colors'], [30, 60, 200]), 'the blue of the picture');
        $this->assertNotEmpty($sign['notes']['paint'] ?? [], 'pieces painted by the picture colours');
        // the page: the sign is a picture tool (colours panel, painting by the model's notes) while its style is picture
        $html = $this->get('/tools/sign')->assertOk()->getContent();
        $this->assertStringContainsString('familyWhen', $html);
        $this->assertStringContainsString('style=picture', $html);
        $js = (string) file_get_contents(base_path('resources/js/calc/param.ts'));
        $this->assertStringContainsString('cfg.familyWhen', $js);
        $this->assertStringContainsString('let shape', $js);
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
