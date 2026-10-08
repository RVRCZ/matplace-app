<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Papel picado: a picture as a cut-out panel with a pierced border (/tools/papel-picado). */
class PapelPicadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    /** @return array<string, mixed> */
    private function meta(array $params): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'papel', 'params' => $params + ['width' => 150, 'height' => 200, 'thickness' => 1.2]])->assertOk();

        return json_decode((string) $r->headers->get('X-Model-Meta'), true);
    }

    /** A "photo": a light face with two dark eyes on a dark ground, soft at the edges as a camera would give it. */
    private function photo(): string
    {
        $img = imagecreatetruecolor(300, 400);
        imagefill($img, 0, 0, imagecolorallocate($img, 40, 30, 50));
        imagefilledellipse($img, 150, 190, 200, 260, imagecolorallocate($img, 235, 215, 200));
        imagefilledellipse($img, 115, 170, 30, 30, imagecolorallocate($img, 30, 30, 30));
        imagefilledellipse($img, 185, 170, 30, 30, imagecolorallocate($img, 30, 30, 30));
        for ($i = 0; $i < 2; $i++) {
            imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'papel').'.jpg';
        imagejpeg($img, $tmp, 90);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($tmp, 'face.jpg', 'image/jpeg', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    public function test_a_drawing_hangs_in_its_window_held_by_ties(): void
    {
        $skull = ['artwork' => 'lib:holidays/sugar-skull'];
        $m = $this->meta($skull);
        // the panel as wide as asked, the scallops hang below it; thin, one piece of plastic
        $this->assertEqualsWithDelta(150, $m['bbox']['x'], 0.1);
        $this->assertGreaterThan(205, $m['bbox']['y']);
        $this->assertEqualsWithDelta(1.2, $m['bbox']['z'], 0.01);
        $this->assertSame([], $m['notes']['warnings']);
        // the skull is a piece of paper in a cut-out window: without ties it would fall out
        $this->assertGreaterThanOrEqual(1, $m['notes']['ties']);
        $frame = $this->meta(['artwork' => 'lib:holidays/sugar-skull', 'invert' => true]);
        // reversed, the skull is the hole and the window round it stays: more plastic; its eyes, nose and teeth are now
        // pieces of paper in that hole, every one of them tied
        $this->assertGreaterThanOrEqual(10, $frame['notes']['ties']);
        $this->assertGreaterThan($m['volume_mm3'], $frame['volume_mm3']);
        $this->assertGreaterThan($frame['notes']['open_pct'], $m['notes']['open_pct']);
        // without scallops and string holes the panel is exactly as tall as asked; a plain border holds more plastic
        $plain = $this->meta($skull + ['scallop' => false, 'string_holes' => false]);
        $this->assertEqualsWithDelta(200, $plain['bbox']['y'], 0.1);
        $none = $this->meta($skull + ['scallop' => false, 'string_holes' => false, 'border' => 'none']);
        $seen = [];
        foreach (ParametricGenerator::CHOICES['papel']['border'] as $border) {
            $seen[$border] = round($this->meta($skull + ['border' => $border])['volume_mm3']);
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('param.o.papel.'.$border, __('param.o.papel.'.$border, [], $locale));
            }
        }
        $this->assertCount(count($seen), array_unique($seen));
        $this->assertEqualsWithDelta(1.2, $none['bbox']['z'], 0.01);
    }

    public function test_a_photo_is_split_into_paper_and_holes(): void
    {
        $face = ['artwork' => $this->photo()];
        $m = $this->meta($face);
        // the dark ground is paper and one with the border; the light face is a hole; each eye is held by a tie
        $this->assertSame(2, $m['notes']['ties']);
        $this->assertSame([], $m['notes']['warnings']);
        $this->assertGreaterThan(20, $m['notes']['open_pct']);
        $this->assertLessThan(60, $m['notes']['open_pct']);
        // more paper asked for: less is cut away; reversed: the face is paper joined to nothing but its own ties
        $this->assertLessThan($m['notes']['open_pct'], $this->meta($face + ['darkness' => 85])['notes']['open_pct']);
        $reversed = $this->meta($face + ['invert' => true]);
        $this->assertGreaterThan($m['notes']['open_pct'], $reversed['notes']['open_pct']);
        $this->assertGreaterThanOrEqual(1, $reversed['notes']['ties']);
        // nothing but a picture makes a panel
        $this->postJson('/api/tools/param/preview', ['kind' => 'papel', 'params' => ['width' => 150]])->assertStatus(422);
    }

    public function test_the_page_opens_with_the_sugar_skull_in_three_languages(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/papel-picado')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.papel.title', [], $locale)), $html);
            $this->assertStringContainsString(e(ToolSeo::texts('papel', $locale)['h1']), $html);
            $this->assertStringContainsString('sample: "lib:holidays\/sugar-skull"', $html);
            $this->assertStringContainsString('data-choice="border"', $html);
            $this->assertStringContainsString('data-flag="scallop"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
        $this->assertNotNull(ParametricGenerator::artworkPath('lib:holidays/sugar-skull'));
    }
}
