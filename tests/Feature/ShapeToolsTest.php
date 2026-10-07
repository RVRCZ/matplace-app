<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A picture or a name in the colours of filaments: pendant, earrings, Christmas ornament, fridge magnet, coaster.
 * The picture's colours become parts, each a filament of the palette; what each product adds; how the design prints.
 */
class ShapeToolsTest extends TestCase
{
    use RefreshDatabase;

    private const KINDS = ['charm', 'earrings', 'ornament', 'magnet', 'coaster'];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    private function meta($response): array
    {
        return json_decode((string) $response->headers->get('X-Model-Meta'), true);
    }

    private function preview(string $kind, array $params, string $part = 'all', bool $pieces = false)
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'part' => $part, 'pieces' => $pieces]);
    }

    /** A drawing on a plain blue background: a white face with two black eyes and a red nose. */
    private function face(string $name = 'face.png'): string
    {
        $img = imagecreatetruecolor(400, 400);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 170, 230));
        imagefilledellipse($img, 200, 200, 320, 320, imagecolorallocate($img, 245, 243, 238));
        $black = imagecolorallocate($img, 25, 25, 28);
        imagefilledellipse($img, 140, 160, 50, 70, $black);
        imagefilledellipse($img, 260, 160, 50, 70, $black);
        imagefilledellipse($img, 200, 250, 70, 50, imagecolorallocate($img, 190, 30, 30));
        $tmp = tempnam(sys_get_temp_dir(), 'mpface').'.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($tmp, $name, 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    public function test_pages_render_in_three_languages_with_their_texts(): void
    {
        foreach (self::KINDS as $kind) {
            foreach (['cs', 'en', 'es'] as $lang) {
                app()->setLocale($lang);
                $page = $this->get($this->localized('/tools/'.$kind, $lang))->assertOk();
                $page->assertSee(__('tools.'.$kind.'.title'))->assertSee(__('param.'.$kind.'.lead'))->assertSee(__('param.shape.picture'));
                // no key is shown instead of a text
                $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $page->getContent(), "{$kind} ({$lang})");
            }
            app()->setLocale('cs');
            $this->assertSame(route('tools.'.$kind), route(config('tools')[$kind]['route']));
            $this->assertNotNull(ParametricGenerator::artworkPath(ParametricGenerator::SAMPLE[$kind]), "{$kind}: the picture a visitor starts with is in the library");
        }
    }

    public function test_a_picture_becomes_parts_in_the_colours_of_filaments(): void
    {
        $art = $this->face();
        $r = $this->preview('charm', ['artwork' => $art, 'width' => 60, 'frame' => 1, 'thickness' => 3, 'relief' => 0.6, 'colors_n' => 4], 'all', true)->assertOk();
        $m = $this->meta($r);
        $n = $m['notes'];
        // the blue background is gone, three colours are left, each one the nearest filament of the palette
        $this->assertSame('removed', $n['background']);
        $this->assertSame(['white', 'black', 'red'], array_column($n['colors'], 'code'));
        $this->assertSame(['body', 'color_1', 'color_2', 'color_3'], $n['parts']);
        $this->assertSame(array_column($m['parts'], 'name'), $n['parts']);
        $this->assertSame(count($m['parts']) ? $m['parts'][count($m['parts']) - 1]['tris'][1] : 0, $m['triangles']);
        $this->assertGreaterThan(0.7, $n['colors'][0]['share']);
        // the plate is the picture's lowest colour: three filaments, two changes, every layer of the print one filament
        $this->assertSame('white', $n['body_color']['code']);
        $this->assertSame(3, $n['filaments']);
        $this->assertFalse($n['multi_material']);
        $this->assertSame([3.6, 4.2], array_column($n['color_changes'], 'z'));
        $this->assertArrayNotHasKey('color_change_mm', $n);
        // as wide as asked (the eyelet sits on top), plate + three steps high
        $this->assertEqualsWithDelta(60, $m['bbox']['x'], 0.4);
        $this->assertEqualsWithDelta(3 + 3 * 0.6, $m['bbox']['z'], 0.01);
        $this->assertGreaterThan($m['bbox']['x'], $m['bbox']['y']);
        $this->assertCount(120, $n['outline']);
        $this->assertSame(strtolower(ParametricGenerator::COLOR_HEX['red']), strtolower($n['paint']['color_3']));

        // one part alone is what a download of it holds
        $eyes = $this->meta($this->preview('charm', ['artwork' => $art, 'width' => 60], 'color_2')->assertOk());
        $this->assertSame('color_2', $eyes['part']);
        $this->assertLessThan(35, $eyes['bbox']['x']);

        // two colours asked: one change of filament, the one the farm and every slicer project already know
        $two = $this->meta($this->preview('charm', ['artwork' => $art, 'width' => 60, 'colors_n' => 2])->assertOk())['notes'];
        $this->assertCount(2, $two['colors']);
        $this->assertCount(1, $two['color_changes']);
        $this->assertSame($two['color_changes'][0]['z'], $two['color_change_mm']);

        // the whole photo when the background stays
        $kept = $this->meta($this->preview('charm', ['artwork' => $art, 'width' => 60, 'frame' => 0, 'remove_bg' => false, 'eyelet' => false])->assertOk());
        $this->assertSame('kept', $kept['notes']['background']);
        $this->assertEqualsWithDelta(60, $kept['bbox']['y'], 0.6);
        $this->assertCount(4, $kept['notes']['colors']);                              // the background is a colour now
    }

    public function test_the_visitor_decides_filaments_order_and_which_colours_are_one(): void
    {
        $art = $this->face();
        $base = ['artwork' => $art, 'width' => 60];
        // a filament of the visitor's own choice for a colour and for the plate
        $own = $this->meta($this->preview('charm', $base + ['part_colors' => ['color_2' => 'blue', 'body' => 'yellow']])->assertOk())['notes'];
        $this->assertSame('blue', $own['colors'][1]['code']);
        $this->assertSame('yellow', $own['body_color']['code']);
        $this->assertCount(3, $own['color_changes']);
        // the eyes and the nose joined into one colour
        $merged = $this->meta($this->preview('charm', $base + ['merge' => [[3, 2]]])->assertOk())['notes'];
        $this->assertSame(['color_1', 'color_2'], array_column($merged['colors'], 'part'));
        // the nose under the eyes: the order of the layers follows
        $turned = $this->meta($this->preview('charm', $base + ['order' => [1, 3, 2]])->assertOk())['notes'];
        $this->assertSame(['color_1', 'color_3', 'color_2'], array_column($turned['colors'], 'part'));
        // colours inlaid level with the top, or a rim beside them, share a layer: only a multi-material printer prints that
        $flush = $this->meta($this->preview('charm', $base + ['flush' => true, 'thickness' => 3])->assertOk());
        $this->assertTrue($flush['notes']['multi_material']);
        $this->assertSame([], $flush['notes']['color_changes']);
        $this->assertEqualsWithDelta(3, $flush['bbox']['z'], 0.01);
        $rim = $this->meta($this->preview('charm', $base + ['rim' => true])->assertOk())['notes'];
        $this->assertContains('rim', $rim['parts']);
        $this->assertTrue($rim['multi_material']);
        $this->preview('charm', $base + ['part_colors' => ['color_1' => 'no-such-spool']])->assertStatus(422);
        $this->preview('charm', $base + ['merge' => [[1, 99]]])->assertStatus(422);
    }

    public function test_every_product_adds_its_own_thing(): void
    {
        $ghost = 'lib:colour/happy-ghost';
        // the eyelet travels round the outline: on top, then a quarter of the way round (the right side)
        $top = $this->meta($this->preview('charm', ['artwork' => $ghost, 'width' => 50])->assertOk());
        $side = $this->meta($this->preview('charm', ['artwork' => $ghost, 'width' => 50, 'eye_pos' => 25])->assertOk());
        $this->assertGreaterThan($top['notes']['eyelet']['x'] + 10, $side['notes']['eyelet']['x']);
        $this->assertLessThan($top['notes']['eyelet']['y'] - 10, $side['notes']['eyelet']['y']);
        $none = $this->meta($this->preview('charm', ['artwork' => $ghost, 'width' => 50, 'eyelet' => false])->assertOk());
        $this->assertArrayNotHasKey('eyelet', $none['notes']);
        $this->assertLessThan($top['bbox']['y'], $none['bbox']['y']);

        // earrings: two of them on the bed, each as wide as asked
        $pair = $this->meta($this->preview('earrings', ['artwork' => 'lib:colour/red-heart', 'width' => 30, 'mirror' => true], 'all', true)->assertOk());
        $this->assertSame(2, $pair['notes']['copies']);
        $this->assertEqualsWithDelta(30, $pair['notes']['each'][0], 0.4);
        $this->assertEqualsWithDelta(2 * $pair['notes']['each'][0] + 6, $pair['bbox']['x'], 0.1);
        $one = $this->meta($this->preview('charm', ['artwork' => 'lib:colour/red-heart', 'width' => 30, 'thickness' => 2.4, 'frame' => 0.8, 'relief' => 0.4, 'eye_hole' => 2, 'eye_wall' => 1.6])->assertOk());
        $this->assertEqualsWithDelta(2 * $one['volume_mm3'], $pair['volume_mm3'], 2 * $one['volume_mm3'] * 0.01);

        // ornament: in a circle or a star as wide as asked
        foreach (['circle', 'star'] as $body) {
            $o = $this->meta($this->preview('ornament', ['artwork' => 'lib:colour/gingerbread-man', 'width' => 90, 'body' => $body])->assertOk());
            $this->assertEqualsWithDelta(90, $o['bbox']['x'], 0.5, $body);
            // the plate stands out from the motif: never the motif's own first colour
            $this->assertNotSame($o['notes']['colors'][0]['code'], $o['notes']['body_color']['code'], $body);
        }

        // magnet: a pocket in the back, exactly the magnet's size plus the clearance; the plate thickens for a tall magnet
        $flat = $this->meta($this->preview('magnet', ['artwork' => 'lib:colour/paw-badge', 'width' => 60, 'mount' => 'none'])->assertOk());
        $glue = $this->meta($this->preview('magnet', ['artwork' => 'lib:colour/paw-badge', 'width' => 60, 'mount' => 'glue', 'disc' => 'd10x2', 'mag_gap' => 0.2])->assertOk());
        $this->assertEqualsWithDelta(M_PI * 5.2 * 5.2 * 2.2, $flat['volume_mm3'] - $glue['volume_mm3'], 2.0);
        $this->assertSame(['d' => 10, 'h' => 2], array_intersect_key($glue['notes']['magnet'], ['d' => 0, 'h' => 0]));
        $tall = $this->meta($this->preview('magnet', ['artwork' => 'lib:colour/paw-badge', 'width' => 60, 'mount' => 'press', 'disc' => 'custom', 'mag_d' => 12, 'mag_h' => 5, 'thickness' => 3])->assertOk());
        $this->assertEqualsWithDelta(5.8, $tall['notes']['thickened'], 0.001);
        $small = $this->meta($this->preview('magnet', ['artwork' => 'lib:colour/happy-ghost', 'width' => 40, 'mount' => 'glue', 'disc' => 'custom', 'mag_d' => 30, 'mag_h' => 2])->assertOk());
        $this->assertContains('magnet_no_room', $small['notes']['warnings']);

        // coaster: round, square or six-sided, as wide as asked; grooves underneath take material away
        $round = $this->meta($this->preview('coaster', ['artwork' => 'lib:colour/snowman', 'width' => 100, 'body' => 'circle'])->assertOk());
        $this->assertEqualsWithDelta(100, $round['bbox']['x'], 0.2);
        $this->assertEqualsWithDelta(100, $round['bbox']['y'], 0.2);
        $grooved = $this->meta($this->preview('coaster', ['artwork' => 'lib:colour/snowman', 'width' => 100, 'body' => 'circle', 'grooves' => true])->assertOk());
        $this->assertLessThan($round['volume_mm3'] - 100, $grooved['volume_mm3']);
        $hex = $this->meta($this->preview('coaster', ['artwork' => 'lib:colour/snowman', 'width' => 100, 'body' => 'hex'])->assertOk());
        $this->assertEqualsWithDelta(100 * cos(M_PI / 6), min($hex['bbox']['x'], $hex['bbox']['y']), 0.2);
        // a photo with its background kept fills the coaster up to the frame
        $photo = $this->meta($this->preview('coaster', ['artwork' => $this->face(), 'width' => 100, 'body' => 'square', 'remove_bg' => false, 'frame' => 2])->assertOk());
        $this->assertSame('kept', $photo['notes']['background']);
        $this->assertSame($photo['notes']['colors'][0]['code'], $photo['notes']['body_color']['code']);
        $this->preview('coaster', ['artwork' => 'lib:colour/snowman', 'body' => 'star'])->assertStatus(422);
    }

    public function test_a_name_instead_of_a_picture_and_honest_errors(): void
    {
        $name = $this->meta($this->preview('charm', ['line1' => 'Ela', 'typeface' => 'script', 'width' => 60])->assertOk());
        $this->assertSame(['body', 'color_1'], $name['notes']['parts']);
        $this->assertSame(2, $name['notes']['filaments']);
        $this->assertEqualsWithDelta(3, $name['notes']['color_change_mm'], 0.001);       // letters on a plate: the two colours the farm prints
        $this->assertEqualsWithDelta(60, $name['notes']['each'][0], 3.5);                 // the eyelet may reach over the side
        $this->assertNotSame($name['notes']['colors'][0]['code'], $name['notes']['body_color']['code']);

        $nothing = $this->preview('charm', ['line1' => ''])->assertStatus(422);
        $this->assertSame(__('param.error.no_text'), $nothing->json('errors.params.0'));
        $blank = (string) $this->post('/api/tools/artwork', ['file' => UploadedFile::fake()->image('white.png', 200, 200)], ['Accept' => 'application/json'])->json('artwork');
        $this->assertNotSame(__('param.error.failed'), $this->preview('charm', ['artwork' => $blank, 'remove_bg' => true])->assertStatus(422)->json('errors.params.0'));
        $this->preview('earrings', ['artwork' => 'lib:colour/red-heart', 'width' => 200])->assertStatus(422);
        foreach (['cs', 'en', 'es'] as $lang) {
            app()->setLocale($lang);
            $this->assertNotSame('param.error.shape_too_small', __('param.error.shape_too_small'));
            foreach (['pieces_tied', 'magnet_no_room', 'magnet_shows'] as $w) {
                $this->assertNotSame('param.shape.warn.'.$w, __('param.shape.warn.'.$w), "{$w} ({$lang})");
            }
        }
    }

    public function test_a_created_design_keeps_its_filaments_and_says_where_the_print_changes_them(): void
    {
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);
        $r = $this->postJson('/api/tools/param', ['kind' => 'ornament', 'params' => ['artwork' => $this->face(), 'width' => 80, 'part_colors' => ['color_3' => 'orange']]])->assertCreated();
        $r->assertJsonPath('file.kind', 'ornament')->assertJsonPath('file.tool.kind', 'ornament');
        $this->assertSame(['body', 'color_1', 'color_2', 'color_3'], $r->json('file.parts'));
        $this->assertStringContainsString('/tools/ornament?from=', $r->json('file.tool.url'));
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $p = $file->tool_params;
        // every part with the filament it was shown in, the chosen one and the matched ones alike
        $this->assertSame(['color_3' => 'orange', 'body' => 'white', 'color_1' => 'white', 'color_2' => 'black'], array_map(fn ($c) => $c['code'], $p['part_colors']));
        $this->assertFalse($p['multi_material']);
        $this->assertSame(['black', 'orange'], array_column($p['color_changes'], 'code'));
        $changes = $file->colorChanges();
        $this->assertSame([3.6, 4.2], array_column($changes, 'z'));
        $this->assertSame(strtolower(ParametricGenerator::COLOR_HEX['orange']), strtolower($changes[1]['hex']));
        $this->assertSame([7.2, 8.4], array_column($file->colorChanges(2.0), 'z'));
        $this->assertNull($file->colorChangeMm());                                   // two changes: not the one-change kind
        $this->assertSame(['supports' => false], $file->printHints());

        // opened again it is the same thing (the page sends the codes of the stored filaments), and every colour can be had as a file of its own
        $p = ['part_colors' => array_map(fn ($c) => $c['code'], $p['part_colors'])] + $p;
        $again = $this->meta($this->preview('ornament', $p)->assertOk());
        $this->assertEqualsWithDelta($file->bbox['x'], $again['bbox']['x'], 0.05);
        $this->assertSame(['white', 'black', 'orange'], array_column($again['notes']['colors'], 'code'));
        $this->get('/api/tools/param/'.$file->uuid.'/color_3.stl')->assertOk();
        $zip = $this->postJson('/api/tools/param/zip', ['kind' => 'ornament', 'params' => $p])->assertOk();
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($zip->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $names[] = $archive->getNameIndex($i);
        }
        $archive->close();
        $this->assertEqualsCanonicalizing(['ornament.stl', 'ornament-body.stl', 'ornament-color_1.stl', 'ornament-color_2.stl', 'ornament-color_3.stl'], $names);
    }
}
