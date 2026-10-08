<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A drawer insert from a photo of things on a sheet of A4 (engines/python/sheet_kinds.py). The photos here are drawn:
 * a table, a sheet seen askew, things of known size on it. Real photos are for a person to try, so the tool stays out
 * of the catalogue until then.
 */
class InsertToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    /**
     * A "photo" of 1200 × 1500: a brown table and a white sheet whose corners are given (so it can lie askew); the things
     * are boxes and discs in millimetres of the sheet, drawn where a straight sheet of 3 px per mm would have them and
     * sheared with it.
     *
     * @param  list<array{0: string, 1: array<int, float>, 2: array{0: int, 1: int, 2: int}}>  $things
     */
    private function photo(array $things, float $shear = 0.0, bool $sheet = true): string
    {
        $img = imagecreatetruecolor(1200, 1500);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 95, 70));
        $at = fn (float $x, float $y) => [(int) round(280 + 3 * $x + $shear * 3 * $y), (int) round(300 + 3 * $y)];
        if ($sheet) {
            imagefilledpolygon($img, [...$at(0, 0), ...$at(210, 0), ...$at(210, 297), ...$at(0, 297)], imagecolorallocate($img, 240, 240, 236));
        }
        foreach ($things as [$kind, $v, $rgb]) {
            $ink = imagecolorallocate($img, ...$rgb);
            if ($kind === 'box') {
                imagefilledpolygon($img, [...$at($v[0], $v[1]), ...$at($v[0] + $v[2], $v[1]), ...$at($v[0] + $v[2], $v[1] + $v[3]), ...$at($v[0], $v[1] + $v[3])], $ink);
            } else {
                [$cx, $cy] = $at($v[0], $v[1]);
                imagefilledellipse($img, $cx, $cy, (int) (6 * $v[2]), (int) (6 * $v[2]), $ink);
            }
        }
        imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
        $tmp = tempnam(sys_get_temp_dir(), 'sheet').'.jpg';
        imagejpeg($img, $tmp, 90);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($tmp, 'things.jpg', 'image/jpeg', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    private function preview(array $params)
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => 'insert', 'params' => $params]);
    }

    public function test_things_on_a_sheet_become_pockets_of_their_size(): void
    {
        // a bar of 20 × 160 mm, a red disc of 44 mm and a square of 50 mm, on a sheet that lies a little askew
        $photo = $this->photo([['box', [30, 40, 20, 160], [40, 40, 45]], ['disc', [130, 80, 22], [180, 50, 40]], ['box', [110, 180, 50, 50], [50, 70, 100]]], 0.06);
        $r = $this->preview(['artwork' => $photo, 'depth' => 15, 'gap' => 1.5, 'margin' => 5, 'floor' => 1.6])->assertOk();
        $m = json_decode((string) $r->headers->get('X-Model-Meta'), true);
        $things = $m['notes']['things'];
        // three things, each within a millimetre and a half of what was drawn (the sheet is the ruler: 210 × 297)
        $this->assertSame([210, 297], $m['notes']['sheet']);
        $this->assertCount(3, $things);
        usort($things, fn ($a, $b) => $b[1] <=> $a[1]);
        foreach ([[20, 160], [50, 50], [44, 44]] as $i => [$w, $h]) {
            $this->assertEqualsWithDelta($w, $things[$i][0], 1.8, "thing {$i} width");
            $this->assertEqualsWithDelta($h, $things[$i][1], 1.8, "thing {$i} height");
        }
        // the tray: a floor and the pockets' depth high; without the finger notches, the things together plus play and margin wide
        $this->assertEqualsWithDelta(16.6, $m['bbox']['z'], 0.01);
        $whole = json_decode((string) $this->preview(['artwork' => $photo, 'notch' => false])->assertOk()->headers->get('X-Model-Meta'), true);
        $this->assertEqualsWithDelta(130 + 2 + 10, $whole['bbox']['x'], 3);
        $this->assertEqualsWithDelta(190 + 2 + 10, $whole['bbox']['y'], 3);
        // a notch is a bite of 9 mm beside its pocket: the tray grows by it where a pocket lies at its edge
        $this->assertGreaterThan($whole['bbox']['x'] + 5, $m['bbox']['x']);
        $block = $m['bbox']['x'] * $m['bbox']['y'] * 16.6;
        $this->assertLessThan($block - (20 * 160 + M_PI * 22 * 22 + 50 * 50) * 15, $m['volume_mm3']);
        // more play is less plastic under the same things
        $loose = json_decode((string) $this->preview(['artwork' => $photo, 'gap' => 3])->assertOk()->headers->get('X-Model-Meta'), true);
        $this->assertLessThan($m['volume_mm3'] / ($m['bbox']['x'] * $m['bbox']['y']), $loose['volume_mm3'] / ($loose['bbox']['x'] * $loose['bbox']['y']));
    }

    public function test_a_photo_that_cannot_be_measured_is_refused_in_plain_words(): void
    {
        $said = fn ($response) => $response->assertStatus(422)->json('errors.params.0');
        // a table without a sheet, a sheet with nothing on it, a drawing instead of a photo, nothing at all
        $this->assertSame(__('param.error.sheet_not_found'), $said($this->preview(['artwork' => $this->photo([['box', [30, 40, 60, 60], [40, 40, 45]]], 0.0, false)])));
        $this->assertSame(__('param.error.sheet_empty'), $said($this->preview(['artwork' => $this->photo([])])));
        $this->assertSame(__('param.error.photo_needed'), $said($this->preview(['artwork' => 'lib:animals/cat'])));
        $this->preview([])->assertStatus(422);
    }

    public function test_it_has_a_page_but_no_card_until_real_photos_were_tried(): void
    {
        $this->assertFalse(config('tools.insert.available'));
        $this->get('/tools')->assertOk()->assertDontSee('/tools/drawer-insert', false);
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            app()->setLocale($locale);
            $html = $this->get($prefix.'/tools/drawer-insert')->assertOk()->assertSee(__('tools.insert.title'))->assertSee(__('param.insert.artwork.hint'))->getContent();
            $this->assertStringContainsString('data-param="gap"', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
        app()->setLocale('cs');
    }
}
