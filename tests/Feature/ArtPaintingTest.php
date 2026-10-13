<?php

namespace Tests\Feature;

use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ToolVisibility;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filament art from a photo or a painting: the colours are chosen so that a small saturated one survives, the areas
 * are simplified to millimetres, the page gets the picture in its colours long before the model. A drawing is read
 * exactly as it was before.
 */
class ArtPaintingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ArtGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    /**
     * A "painting": a night sky of hundreds of short strokes in many blues, a dark tree, and two small yellow moons
     * (one per cent of the picture). Uploaded like a visitor's photo; returns its reference.
     */
    private function painting(): string
    {
        mt_srand(7);
        [$w, $h] = [480, 360];
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, (int) (30 + 40 * $t), (int) (60 + 50 * $t), (int) (150 - 40 * $t)));
        }
        imagesetthickness($img, 3);
        for ($i = 0; $i < 2600; $i++) {
            [$x, $y] = [mt_rand(0, $w), mt_rand(0, $h)];
            $c = imagecolorallocate($img, mt_rand(20, 120), mt_rand(50, 150), mt_rand(110, 220));
            imageline($img, $x, $y, $x + mt_rand(-14, 14), $y + mt_rand(-5, 5), $c);
        }
        imagefilledpolygon($img, [90, 360, 130, 90, 175, 360], imagecolorallocate($img, 22, 26, 24));
        $yellow = imagecolorallocate($img, 245, 200, 30);
        imagefilledellipse($img, 380, 80, 44, 44, $yellow);
        imagefilledellipse($img, 250, 60, 26, 26, $yellow);
        $path = sys_get_temp_dir().'/mp_painting_'.uniqid().'.jpg';
        imagejpeg($img, $path, 88);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($path, 'night.jpg', 'image/jpeg', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    private function meta($response): array
    {
        return PreviewMeta::whole($response->headers->get('X-Model-Meta'));
    }

    private function saturation(string $hex): float
    {
        [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($hex, $i, 2)), [1, 3, 5]);

        return (max($r, $g, $b) - min($r, $g, $b)) / max(1, max($r, $g, $b));
    }

    public function test_a_painting_becomes_few_clean_areas_and_keeps_its_small_bright_colour(): void
    {
        $night = $this->painting();
        $params = ['artwork' => $night, 'mode' => 'layered', 'shape' => 'rect', 'frame' => 'none', 'remove_bg' => false, 'width' => 160, 'height' => 120, 'colors_n' => 6];

        // read as a drawing it is what it was before this mode: the strokes become hundreds of thousands of triangles
        $old = $this->meta($this->postJson('/api/tools/art/preview', ['params' => $params])->assertOk());
        $this->assertSame('graphic', $old['notes']['look']);
        $this->assertSame('painting', $old['notes']['suggest']);

        $m = $this->meta($this->postJson('/api/tools/art/preview', ['params' => $params + ['look' => 'painting', 'detail' => 2]])->assertOk());
        $n = $m['notes'];
        $this->assertSame('painting', $n['look']);
        $this->assertLessThanOrEqual(300, $n['regions']);
        $this->assertGreaterThan(3, $n['regions']);
        $this->assertLessThanOrEqual(150000, $m['triangles']);
        $this->assertLessThan($old['triangles'] / 4, $m['triangles']);
        $this->assertNotContains('many_areas', $n['warnings']);
        // the moons are one per cent of the picture and still a colour of their own: yellow, not one more blue
        $yellow = collect($n['colors'])->first(function ($c) {
            [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($c['rgb'], $i, 2)), [1, 3, 5]);

            return $r > 170 && $g > 140 && $b < 110;
        });
        $this->assertNotNull($yellow, json_encode(array_column($n['colors'], 'rgb')));
        $this->assertLessThan(0.05, $yellow['share']);
        $this->assertGreaterThan(40, $yellow['area_mm2']);
        // the plates lie back to front by area, and the dark tree is the plate in front
        $guide = $n['guide'];
        $this->assertSame($guide[0]['area_mm2'], max(array_column($guide, 'area_mm2')));
        $front = end($guide);
        $this->assertLessThan(60, array_sum(array_map(fn ($i) => hexdec(substr($front['rgb'], $i, 2)), [1, 3, 5])) / 3);

        // a bigger smallest detail: fewer areas and fewer triangles
        $coarse = $this->meta($this->postJson('/api/tools/art/preview', ['params' => $params + ['look' => 'painting', 'detail' => 4]])->assertOk());
        $this->assertLessThan($n['regions'], $coarse['notes']['regions']);
        $this->assertLessThan($m['triangles'], $coarse['triangles']);

        // one print with the colours as steps is simplified the same way
        $stack = $this->meta($this->postJson('/api/tools/art/preview', ['params' => ['mode' => 'stack', 'look' => 'painting', 'detail' => 2] + $params])->assertOk());
        $this->assertSame($n['regions'], $stack['notes']['regions']);
        $this->assertLessThanOrEqual(150000, $stack['triangles']);
    }

    public function test_the_picture_in_its_colours_comes_without_a_model_and_proposes_how_to_read_it(): void
    {
        $night = $this->painting();
        $params = ['artwork' => $night, 'width' => 160, 'height' => 150, 'colors_n' => 6];

        // a picture just chosen: the server looks at it, proposes, and the poster is already made that way
        $r = $this->postJson('/api/tools/art/poster', ['params' => $params, 'propose' => true])->assertOk();
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $info = json_decode((string) $r->headers->get('X-Art-Poster'), true);
        $this->assertSame(['look' => 'painting', 'remove_bg' => false, 'shape' => 'rect', 'height' => 120], $info['proposed']);      // 160 wide, in the photo's 4 : 3
        $this->assertSame('painting', $info['look']);
        $this->assertLessThanOrEqual(300, $info['regions']);
        $this->assertLessThanOrEqual(6, count($info['colors']));
        $this->assertEqualsWithDelta(1.0, array_sum(array_column($info['colors'], 'share')), 0.01);
        $png = getimagesizefromstring((string) file_get_contents($r->baseResponse->getFile()->getPathname()));
        $this->assertSame('image/png', $png['mime']);
        $this->assertEqualsWithDelta((160 - 2 * 6) / (120 - 2 * 6), $png[0] / $png[1], 0.03);      // the window of the plate: its size without the margin

        // the poster and the model agree on the areas
        $model = $this->meta($this->postJson('/api/tools/art/preview', ['params' => ['look' => 'painting', 'shape' => 'rect', 'remove_bg' => false, 'height' => 120] + $params])->assertOk());
        $this->assertSame($info['regions'], $model['notes']['regions']);

        // a drawing of the library is proposed as what it is, and without `propose` nothing is proposed
        $snow = json_decode((string) $this->postJson('/api/tools/art/poster', ['params' => ['artwork' => 'lib:colour/snowman'], 'propose' => true])->assertOk()->headers->get('X-Art-Poster'), true);
        $this->assertSame(['look' => 'graphic', 'remove_bg' => true, 'shape' => 'image'], $snow['proposed']);
        $this->assertSame(0, $snow['regions']);
        $plain = json_decode((string) $this->postJson('/api/tools/art/poster', ['params' => ['artwork' => 'lib:colour/snowman']])->assertOk()->headers->get('X-Art-Poster'), true);
        $this->assertNull($plain['proposed']);
        $this->assertSame('graphic', $plain['suggest']);

        // bad input is a sentence, a hidden tool is not there
        $this->postJson('/api/tools/art/poster', ['params' => ['artwork' => 'lib:colour/snowman', 'detail' => 9]])->assertStatus(422);
        $this->postJson('/api/tools/art/poster', ['params' => ['artwork' => 'lib:colour/snowman', 'look' => 'cubist']])->assertStatus(422);
        DB::table('tool_flags')->insert(['tool' => 'filament_art', 'public' => false, 'created_at' => now(), 'updated_at' => now()]);
        ToolVisibility::forget();
        $this->postJson('/api/tools/art/poster', ['params' => ['artwork' => 'lib:colour/snowman']])->assertStatus(404);
    }

    public function test_a_drawing_is_read_exactly_as_before(): void
    {
        // the numbers of the tool before the painting mode existed (13 Oct 2026): a drawing must not move by a triangle
        $cases = [
            [['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'round', 'width' => 160, 'colors_n' => 5, 'plate' => 2, 'gap' => 3], 228205.9, 14384, ['#f4f4f2', '#1e1e20', '#d1252c', '#f28e2c']],
            [['artwork' => 'lib:colour/snowman', 'mode' => 'stack', 'width' => 120, 'colors_n' => 6], 14938.0, 4220, ['#f4f4f2', '#1e1e20', '#d1252c', '#f28e2c']],
            [['artwork' => 'lib:colour/happy-ghost', 'mode' => 'layered', 'shape' => 'rect', 'frame' => 'square', 'width' => 150, 'height' => 120, 'colors_n' => 4], 306177.4, 9060, ['#f3f3f2', '#2d2742', '#f49bc2']],
        ];
        foreach ($cases as [$params, $volume, $triangles, $colors]) {
            // (the sizes the page sends by default are named, so the numbers are those of the tool's own defaults)
            $m = $this->meta($this->postJson('/api/tools/art/preview', ['params' => $params + ['margin' => 0, 'height' => 150, 'base' => 1.2, 'step' => 0.4]])->assertOk());
            $this->assertSame('graphic', $m['notes']['look']);
            $this->assertSame(0, $m['notes']['regions']);
            $this->assertSame('graphic', $m['notes']['suggest']);
            $this->assertEqualsWithDelta($volume, $m['volume_mm3'], $volume * 0.002, json_encode($params));
            $this->assertEqualsWithDelta($triangles, $m['triangles'], $triangles * 0.01, json_encode($params));
            $this->assertSame($colors, array_column($m['notes']['colors'], 'rgb'));
        }
    }

    public function test_the_page_offers_the_look_and_the_detail_and_a_design_keeps_them(): void
    {
        $html = (string) $this->get('/tools/filament-art')->assertOk()->getContent();
        $this->assertStringContainsString('data-choice="look" value="graphic" class="sr-only" checked', $html);
        $this->assertStringContainsString('data-choice="look" value="painting"', $html);
        $this->assertStringContainsString('data-param="detail"', $html);
        $this->assertStringContainsString('id="art-poster"', $html);
        $this->assertStringContainsString(json_encode(route('api.tools.art.poster')), $html);
        foreach (['cs', 'en', 'es'] as $locale) {
            $page = (string) $this->get(($locale === 'cs' ? '' : '/'.$locale).'/tools/filament-art')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('edit.o.look.painting', [], $locale)), $page);
            $this->assertDoesNotMatchRegularExpression('/>\s*(edit|tools|toolpage|param)\.[a-z_.]+\s*</', $page, $locale);
        }

        $night = $this->painting();
        $file = $this->postJson('/api/tools/art', ['params' => ['artwork' => $night, 'look' => 'painting', 'detail' => 3, 'shape' => 'rect', 'remove_bg' => false, 'width' => 120, 'height' => 90, 'colors_n' => 4]])->assertCreated()->json('file');
        $this->assertSame('painting', $file['tool']['params']['look']);
        $this->assertEquals(3, $file['tool']['params']['detail']);
        $this->assertSame('painting', $file['tool']['params']['report']['look']);
        $this->assertGreaterThan(0, $file['tool']['params']['report']['regions']);
        // a design from before the choice existed is a drawing
        $this->assertSame('graphic', ArtGenerator::clean(['artwork' => 'lib:colour/snowman'])['look']);
        $this->assertEquals(2, ArtGenerator::clean(['artwork' => 'lib:colour/snowman'])['detail']);
    }
}
