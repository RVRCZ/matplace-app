<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use App\Support\ToolSeo;
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

    private const KINDS = ['charm', 'keychain', 'earrings', 'ornament', 'magnet', 'coaster', 'gingerbread', 'name_letter', 'cookie', 'topper', 'tray', 'badge', 'medallion', 'photo_organizer', 'bag_charm'];

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
                $page = $this->get($this->localized('/tools/'.(['topper' => 'cake-topper', 'tray' => 'shape-tray', 'badge' => 'badge-reel'][$kind] ?? str_replace('_', '-', $kind)), $lang))->assertOk();
                $page->assertSee(__('tools.'.$kind.'.title'))->assertSee(__('param.'.$kind.'.lead'));
                // a gingerbread and a big letter have a shape of ours: there is no picture to bring
                in_array($kind, ['gingerbread', 'name_letter', 'topper'], true) ? $page->assertDontSee(__('param.shape.picture.hint')) : $page->assertSee(__('param.shape.picture'));
                // no key is shown instead of a text
                $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $page->getContent(), "{$kind} ({$lang})");
            }
            app()->setLocale('cs');
            $this->assertSame(route('tools.'.$kind), route(config('tools')[$kind]['route']));
            // a tool opens with something to look at: a picture of the library, or a text of its own (the keychain's name)
            $sample = ParametricGenerator::SAMPLE[$kind] ?? null;
            $this->assertTrue($sample ? ParametricGenerator::artworkPath($sample) !== null : ParametricGenerator::TEXTS[$kind]['line1'][2] !== '', "{$kind} opens empty");
        }
    }

    public function test_the_gifts_page_leads_to_every_one_of_them(): void
    {
        foreach (['cs' => '/gifts', 'en' => '/en/gifts', 'es' => '/es/gifts'] as $lang => $path) {
            app()->setLocale($lang);
            $page = $this->get($path)->assertOk()->assertSee(__('tools.gifts.more'));
            foreach (self::KINDS as $kind) {
                $page->assertSee(ToolSeo::url($kind, $lang), false)->assertSee(__('tools.'.$kind.'.title'))->assertSee('img/tools/'.$kind.'-800', false);
            }
        }
        app()->setLocale('cs');
        // a tool taken out of the catalogue is not offered as a gift either
        config(['tools.cookie.available' => false]);
        $this->get('/gifts')->assertOk()->assertDontSee(ToolSeo::url('cookie', 'cs'), false);
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

        // the keychain as it opens: the name on a rounded plate, the eyelet on the left side, one change of filament
        $key = $this->meta($this->preview('keychain', [])->assertOk());
        $this->assertSame(['body', 'color_1'], $key['notes']['parts']);
        $this->assertEqualsWithDelta(3, $key['notes']['color_change_mm'], 0.001);
        $this->assertEqualsWithDelta(4.5, $key['notes']['eyelet']['x'], 0.1);           // a 5 mm hole in a 2 mm ring: the ring's own radius from the left edge
        $this->assertEqualsWithDelta($key['bbox']['y'] / 2, $key['notes']['eyelet']['y'], 1.5);
        $this->assertGreaterThan(55, $key['bbox']['x']);
        $this->assertLessThan(55 + 12, $key['bbox']['x']);

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

    public function test_gingerbread_is_dough_and_icing_with_the_name_across_it(): void
    {
        foreach (['man', 'heart', 'star', 'tree'] as $cookie) {
            $g = $this->meta($this->preview('gingerbread', ['line1' => 'Ela', 'cookie' => $cookie, 'width' => 90, 'thickness' => 3, 'relief' => 0.6], 'all', true)->assertOk());
            $this->assertEqualsWithDelta(90, $g['notes']['each'][0], 0.3, $cookie);
            $this->assertSame(['body', 'color_1'], $g['notes']['parts'], $cookie);
            // brown under white: two filaments, one change at the top of the dough
            $this->assertSame(['brown', 'white'], [$g['notes']['body_color']['code'], $g['notes']['colors'][0]['code']], $cookie);
            $this->assertEqualsWithDelta(3, $g['notes']['color_change_mm'], 0.001, $cookie);
            $this->assertFalse($g['notes']['multi_material']);
            $this->assertEqualsWithDelta(3.6, $g['bbox']['z'], 0.01);
            $this->assertLessThan(20000, $g['triangles'], $cookie);
            $this->assertSame([], $g['notes']['warnings'], $cookie);
        }
        // the heart hangs by the dip between its lobes, in the middle
        $heart = $this->meta($this->preview('gingerbread', ['line1' => 'Ela', 'cookie' => 'heart', 'width' => 90])->assertOk());
        $this->assertEqualsWithDelta(45, $heart['notes']['eyelet']['x'], 1.0);
        // icing takes material: with it there is more of the second colour than without
        $plain = $this->meta($this->preview('gingerbread', ['line1' => 'Ela', 'cookie' => 'heart', 'icing' => 'none'], 'color_1')->assertOk());
        $wavy = $this->meta($this->preview('gingerbread', ['line1' => 'Ela', 'cookie' => 'heart', 'icing' => 'wavy'], 'color_1')->assertOk());
        $this->assertGreaterThan($plain['volume_mm3'] * 2, $wavy['volume_mm3']);
        // a name too long for the biscuit is said, not printed as a smear; no name and no icing is nothing to print
        $long = $this->meta($this->preview('gingerbread', ['line1' => 'Maxmiliánkovi ♥♥', 'cookie' => 'star', 'width' => 50])->assertOk());
        $this->assertContains('name_small', $long['notes']['warnings']);
        $this->preview('gingerbread', ['line1' => '', 'cookie' => 'heart', 'icing' => 'none'])->assertStatus(422);
        $this->assertGreaterThan(0, $this->meta($this->preview('gingerbread', ['line1' => '', 'cookie' => 'man', 'icing' => 'none'])->assertOk())['volume_mm3']);      // the man keeps his face
        $this->preview('gingerbread', ['cookie' => 'bell'])->assertStatus(422);
        // the visitor's own filaments: a red gingerbread with yellow icing
        $own = $this->meta($this->preview('gingerbread', ['line1' => 'Ela', 'part_colors' => ['body' => 'red', 'color_1' => 'yellow']])->assertOk())['notes'];
        $this->assertSame(['red', 'yellow'], [$own['body_color']['code'], $own['colors'][0]['code']]);
    }

    public function test_a_big_letter_carries_the_whole_name_where_it_has_room(): void
    {
        $e = $this->meta($this->preview('name_letter', ['line1' => 'Ela', 'height' => 120, 'thickness' => 8, 'relief' => 1], 'all', true)->assertOk());
        $this->assertEqualsWithDelta(120, $e['bbox']['y'], 0.5);
        $this->assertEqualsWithDelta(9, $e['bbox']['z'], 0.01);
        $this->assertSame(['body', 'color_1'], $e['notes']['parts']);
        $this->assertEqualsWithDelta(8, $e['notes']['color_change_mm'], 0.001);
        $this->assertSame(['black', 'white'], [$e['notes']['body_color']['code'], $e['notes']['colors'][0]['code']]);
        $this->assertSame([], $e['notes']['warnings']);
        // the name runs up the stem of the E: taller than wide, and well inside the letter
        $name = $this->meta($this->preview('name_letter', ['line1' => 'Ela', 'height' => 120], 'color_1')->assertOk());
        $this->assertGreaterThan($name['bbox']['x'] * 1.3, $name['bbox']['y']);
        $this->assertGreaterThan(15, $name['bbox']['x']);
        // another letter than the first one of the name, another typeface for it
        $n = $this->meta($this->preview('name_letter', ['line1' => 'Ela', 'initial' => 'n', 'height' => 120])->assertOk());
        $this->assertNotEqualsWithDelta($e['bbox']['x'], $n['bbox']['x'], 3);
        $serif = $this->meta($this->preview('name_letter', ['line1' => 'Ela', 'height' => 120, 'letter_face' => 'serif'])->assertOk());
        $this->assertNotEqualsWithDelta($e['volume_mm3'], $serif['volume_mm3'], 100);
        // a long name in a round letter comes out small, and the tool says so
        $o = $this->meta($this->preview('name_letter', ['line1' => 'Oldřiška Nováková ml', 'typeface' => 'mono', 'height' => 60])->assertOk());
        $this->assertNotEmpty(array_intersect(['name_small', 'name_no_room'], $o['notes']['warnings']));
        $this->assertSame(__('param.text_required'), $this->preview('name_letter', ['line1' => ''])->assertStatus(422)->json('errors')['params.line1'][0]);
        $this->preview('name_letter', ['line1' => 'Ela', 'height' => 400])->assertStatus(422);
        // hung on a door: an eyelet on top of the letter, only when asked for
        $hung = $this->meta($this->preview('name_letter', ['line1' => 'Ela', 'height' => 120, 'hang' => true, 'eye_hole' => 5])->assertOk());
        $this->assertArrayNotHasKey('eyelet', $e['notes']);
        $this->assertSame(5, (int) $hung['notes']['eyelet']['hole']);
        $this->assertGreaterThan(120 + 4, $hung['bbox']['y']);
        $this->assertCount(120, $hung['notes']['outline']);
    }

    public function test_a_biscuit_takes_icing_drawn_by_hand(): void
    {
        $star = ['artwork' => 'lib:hearts-stars/star', 'width' => 80, 'thickness' => 6, 'frame' => 2, 'relief' => 0.6];
        $zigzag = ['c' => 'white', 'w' => 2.5, 't' => 'round', 'p' => [[0.12, 0.55], [0.3, 0.6], [0.5, 0.54], [0.7, 0.6], [0.88, 0.55]]];
        $dot = fn (float $x, float $y, string $c = 'red') => ['c' => $c, 'w' => 4, 't' => 'round', 'p' => [[$x, $y]]];

        // a silhouette is only the shape: dough with a rounded top edge, nothing on it
        $plain = $this->meta($this->preview('cookie', $star)->assertOk());
        $this->assertSame(['body'], $plain['notes']['parts']);
        $this->assertSame('brown', $plain['notes']['body_color']['code']);
        $this->assertEqualsWithDelta(6, $plain['bbox']['z'], 0.01);
        $slab = $this->meta($this->preview('charm', ['artwork' => 'lib:hearts-stars/star', 'width' => 80, 'thickness' => 6, 'frame' => 2, 'colors_n' => 1, 'eyelet' => false], 'body')->assertOk());
        $this->assertLessThan($slab['volume_mm3'] - 80, $plain['volume_mm3']);            // the rounded edge takes a little off
        $this->assertGreaterThan($slab['volume_mm3'] * 0.9, $plain['volume_mm3']);

        // strokes become icing: a part for every filament drawn with, in the order the filaments first appear, each a step higher
        $iced = $this->meta($this->preview('cookie', $star + ['strokes' => [$zigzag, $dot(0.5, 0.38), $dot(0.38, 0.22), $dot(0.6, 0.4, 'white')]], 'all', true)->assertOk());
        $n = $iced['notes'];
        $this->assertSame(['body', 'icing_1', 'icing_2'], $n['parts']);
        $this->assertSame(['white', 'red'], array_column($n['colors'], 'code'));
        $this->assertSame([6.0, 6.6], array_map('floatval', array_column($n['color_changes'], 'z')));
        $this->assertSame(['icing_1', 'icing_2'], array_column($n['color_changes'], 'part'));
        $this->assertFalse($n['multi_material']);
        $this->assertEqualsWithDelta(7.2, $iced['bbox']['z'], 0.01);
        $this->assertSame(3, $n['filaments']);
        $this->assertEqualsWithDelta(76, $n['frame'][2], 0.6);                      // the width of the picture itself: what a stroke is measured in
        // the zigzag lies where it was drawn, and stays there on a bigger biscuit
        $line = $this->meta($this->preview('cookie', $star + ['strokes' => [$zigzag]], 'icing_1')->assertOk());
        $this->assertEqualsWithDelta(0.76 * 76 + 2.5, $line['bbox']['x'], 1.5);
        $big = $this->meta($this->preview('cookie', ['width' => 120] + $star + ['strokes' => [$zigzag]], 'icing_1')->assertOk());
        $this->assertEqualsWithDelta(0.76 * 116 + 2.5, $big['bbox']['x'], 1.5);
        // a stroke over the edge is cut off before the rounded rim; one wholly outside the biscuit leaves nothing
        $over = $this->meta($this->preview('cookie', $star + ['strokes' => [['c' => 'white', 'w' => 3, 't' => 'flat', 'p' => [[-0.5, 0.57], [1.5, 0.57]]]]], 'icing_1')->assertOk());
        $this->assertLessThan(76, $over['bbox']['x']);
        $this->assertSame(['body'], $this->meta($this->preview('cookie', $star + ['strokes' => [$dot(3.5, 3.5)]])->assertOk())['notes']['parts']);
        // a row of dots is more pieces of icing than one line
        $dots = $this->meta($this->preview('cookie', $star + ['strokes' => [['t' => 'dots'] + $zigzag]], 'icing_1', true)->assertOk());
        $this->assertGreaterThan(5, count($dots['parts']));

        // a picture in colours decorates itself: what is dough anyway is left out, the rest is icing
        $man = $this->meta($this->preview('cookie', ['artwork' => 'lib:colour/gingerbread-man', 'width' => 80])->assertOk())['notes'];
        $this->assertSame('brown', $man['body_color']['code']);
        $this->assertSame(['white', 'red'], array_column($man['colors'], 'code'));
        // as an ornament it gets an eyelet
        $hung = $this->meta($this->preview('cookie', $star + ['hang' => true])->assertOk());
        $this->assertArrayHasKey('eyelet', $hung['notes']);
        $this->assertArrayNotHasKey('eyelet', $plain['notes']);

        // a long drawing goes to the tool in a file, not on the command line
        $many = [];
        for ($i = 0; $i < 40; $i++) {
            $many[] = ['c' => $i % 2 ? 'white' : 'red', 'w' => 2, 't' => 'round', 'p' => array_map(fn ($k) => [round(0.3 + 0.4 * $k / 47, 4), round(0.3 + 0.3 * $i / 39 + 0.01 * sin($k), 4)], range(0, 47))];
        }
        $this->assertGreaterThan(12000, strlen((string) json_encode($many)));
        $this->assertSame(['body', 'icing_1', 'icing_2'], $this->meta($this->preview('cookie', $star + ['strokes' => $many])->assertOk())['notes']['parts']);

        // what the form may not send
        $this->preview('cookie', $star + ['strokes' => [['c' => 'no-such-spool'] + $zigzag]])->assertStatus(422);
        $this->preview('cookie', $star + ['strokes' => [['w' => 9] + $zigzag]])->assertStatus(422);
        $this->preview('cookie', $star + ['strokes' => array_fill(0, 61, $dot(0.5, 0.4))])->assertStatus(422);
        $this->preview('cookie', $star + ['strokes' => [['p' => array_fill(0, 49, [0.5, 0.5])] + $zigzag]])->assertStatus(422);

        // stored with the design, every colour of icing a file of its own
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);
        $r = $this->postJson('/api/tools/param', ['kind' => 'cookie', 'params' => $star + ['strokes' => [$zigzag, $dot(0.5, 0.38)]]])->assertCreated();
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame(['white', 'red'], array_column($file->tool_params['strokes'], 'c'));
        $this->assertSame(['body', 'icing_1', 'icing_2'], $r->json('file.parts'));
        $this->assertSame(['icing_1', 'icing_2'], array_column($file->tool_params['color_changes'], 'part'));
        $this->assertCount(2, $file->colorChanges());
        $this->get('/api/tools/param/'.$file->uuid.'/icing_2.stl')->assertOk();
    }

    public function test_a_cake_topper_is_one_piece_with_a_name_across_it_and_sticks_under_it(): void
    {
        $t = $this->meta($this->preview('topper', ['template' => 'number', 'number' => '2', 'line1' => 'Olivia', 'width' => 110, 'text_size' => 105, 'spike' => 60, 'spikes' => 2, 'thickness' => 3, 'relief' => 0.8], 'all', true)->assertOk());
        $n = $t['notes'];
        $this->assertSame(['body', 'color_1'], $n['parts']);
        $this->assertSame(2, $n['filaments']);
        $this->assertEqualsWithDelta(3, $n['color_change_mm'], 0.001);
        $this->assertEqualsWithDelta(3.8, $t['bbox']['z'], 0.01);
        $this->assertGreaterThan(110 * 1.05, $t['bbox']['x']);                 // the name is wider than the number and has its own backing
        $this->assertLessThan(110 * 1.05 + 8, $t['bbox']['x']);
        // shape, name and sticks are one body: nothing falls apart when it is lifted off the bed
        $body = $this->meta($this->preview('topper', ['number' => '2', 'line1' => 'Olivia'], 'body', true)->assertOk());
        $this->assertCount(1, $body['parts']);
        // longer sticks make it taller by exactly that much; one stick is less plastic than two
        $long = $this->meta($this->preview('topper', ['number' => '2', 'line1' => 'Olivia', 'width' => 110, 'spike' => 100])->assertOk());
        $this->assertEqualsWithDelta(40, $long['bbox']['y'] - $t['bbox']['y'], 0.2);
        $one = $this->meta($this->preview('topper', ['number' => '2', 'line1' => 'Olivia', 'width' => 110, 'spikes' => 1])->assertOk());
        $this->assertLessThan($t['volume_mm3'] - 400, $one['volume_mm3']);
        // the name alone: one colour, still one piece with its sticks, letters tied together
        $text = $this->meta($this->preview('topper', ['template' => 'none', 'line1' => 'Ela a Tom', 'typeface' => 'sans', 'width' => 150], 'all', true)->assertOk());
        $this->assertSame(['body'], $text['notes']['parts']);
        $this->assertSame(1, $text['notes']['filaments']);
        $this->assertCount(1, $text['parts']);
        $this->assertContains('pieces_tied', $text['notes']['warnings']);
        $this->assertEqualsWithDelta(150, $text['bbox']['x'], 4);
        // a heart with a name lower on it; a number that was not typed leaves the name alone
        $heart = $this->meta($this->preview('topper', ['template' => 'heart', 'line1' => 'Ela', 'width' => 100, 'text_size' => 60, 'text_y' => -20])->assertOk());
        $this->assertEqualsWithDelta(100, $heart['bbox']['x'], 0.5);
        $this->assertSame(['body'], $this->meta($this->preview('topper', ['template' => 'number', 'number' => '', 'line1' => 'Ela'])->assertOk())['notes']['parts']);
        $this->preview('topper', ['template' => 'none', 'line1' => '', 'number' => ''])->assertStatus(422);
        $this->preview('topper', ['template' => 'cube', 'line1' => 'Ela'])->assertStatus(422);
    }

    public function test_a_dish_follows_the_outline_and_carries_the_picture_in_its_floor(): void
    {
        $paw = ['artwork' => 'lib:colour/paw-badge', 'width' => 100, 'height' => 15, 'thickness' => 2, 'wall' => 1.6, 'frame' => 3];
        // the picture cut into the floor: one part, one filament, a dish as wide and as tall as asked
        $d = $this->meta($this->preview('tray', $paw, 'all', true)->assertOk());
        $this->assertSame(['body'], $d['notes']['parts']);
        $this->assertSame(1, $d['notes']['filaments']);
        $this->assertFalse($d['notes']['multi_material']);
        $this->assertSame([], $d['notes']['color_changes']);
        $this->assertEqualsWithDelta(100, $d['bbox']['x'], 0.5);
        $this->assertEqualsWithDelta(15, $d['bbox']['z'], 0.01);
        // a floor and a wall, not a block; the carving takes a little more away than a plain floor
        $this->assertLessThan(0.3 * M_PI * 50 * 50 * 15, $d['volume_mm3']);
        $plain = $this->meta($this->preview('tray', ['floor' => 'plain'] + $paw)->assertOk());
        $this->assertGreaterThan($d['volume_mm3'] + 100, $plain['volume_mm3']);
        $this->assertLessThan($d['volume_mm3'] + 0.6 * M_PI * 44 * 44, $plain['volume_mm3']);
        // taller walls and a thicker floor are more plastic
        $tall = $this->meta($this->preview('tray', ['height' => 30, 'thickness' => 4] + $paw)->assertOk());
        $this->assertEqualsWithDelta(30, $tall['bbox']['z'], 0.01);
        $this->assertGreaterThan($d['volume_mm3'] * 1.5, $tall['volume_mm3']);
        // in colours the picture is inlaid in the floor: parts for the colours, and the print needs a multi-material printer
        $col = $this->meta($this->preview('tray', ['floor' => 'colors'] + $paw)->assertOk());
        $this->assertContains('color_2', $col['notes']['parts']);
        $this->assertTrue($col['notes']['multi_material']);
        $this->assertEqualsWithDelta(15, $col['bbox']['z'], 0.01);
        // a silhouette is only the outline: a plain dish in a light filament, whatever the floor was asked to be
        $heart = $this->meta($this->preview('tray', ['artwork' => 'lib:hearts-stars/heart', 'width' => 110, 'height' => 20])->assertOk());
        $this->assertSame(['body'], $heart['notes']['parts']);
        $this->assertSame('white', $heart['notes']['body_color']['code']);
        // a name instead of a picture is cut into the floor of a dish shaped like the name
        $name = $this->meta($this->preview('tray', ['line1' => 'Ela', 'typeface' => 'script', 'width' => 120])->assertOk());
        $this->assertSame(['body'], $name['notes']['parts']);
        $this->assertEqualsWithDelta(120, $name['bbox']['x'], 1.0);
        // the lowest dish with the thickest floor the form allows is still a dish
        $this->assertEqualsWithDelta(8, $this->meta($this->preview('tray', ['height' => 8, 'thickness' => 4] + $paw)->assertOk())['bbox']['z'], 0.01);
        // the edge of dough round a biscuit's picture may be as wide as the form allows (it was refused above 4 mm)
        $this->preview('cookie', ['artwork' => 'lib:colour/gingerbread-man', 'width' => 80, 'frame' => 6])->assertOk();
    }

    public function test_a_badge_carries_a_name_in_a_colour_of_the_picture_and_a_pocket_in_its_back(): void
    {
        $star = ['artwork' => 'lib:colour/smiling-star', 'width' => 40];
        $plain = $this->meta($this->preview('badge', $star, 'all', true)->assertOk());
        $named = $this->meta($this->preview('badge', $star + ['line1' => 'Jana'], 'all', true)->assertOk());
        // the name hangs under the picture: the badge is taller, no wider, and holds the same parts and filaments
        $this->assertGreaterThan($plain['bbox']['y'] + 5, $named['bbox']['y']);
        $this->assertEqualsWithDelta($plain['bbox']['x'], $named['bbox']['x'], 0.5);
        $this->assertSame($plain['notes']['parts'], $named['notes']['parts']);
        $this->assertSame($plain['notes']['filaments'], $named['notes']['filaments']);
        $this->assertSame(count($plain['notes']['color_changes']), count($named['notes']['color_changes']));
        $this->assertFalse($named['notes']['multi_material']);
        // it is written in the colour that reads best on the yellow star (black), in capitals of 3 to 7 mm
        $caption = $named['notes']['caption'];
        $ink = collect($named['notes']['colors'])->firstWhere('index', $caption['index']);
        $this->assertSame('black', $ink['code']);
        $this->assertGreaterThanOrEqual(3, $caption['height']);
        $this->assertLessThanOrEqual(7, $caption['height']);
        $this->assertArrayNotHasKey('caption', $plain['notes']);
        // the pocket for the reel's sticky dot is cut out of the back; a flat back holds more plastic
        $this->assertSame(['d' => 19, 'h' => 0.8], array_intersect_key($named['notes']['magnet'], ['d' => 1, 'h' => 1]));
        $flat = $this->meta($this->preview('badge', $star + ['line1' => 'Jana', 'mount' => 'none'])->assertOk());
        $this->assertArrayNotHasKey('magnet', $flat['notes']);
        $this->assertEqualsWithDelta(M_PI * 9.7 * 9.7 * 0.8, $flat['volume_mm3'] - $named['volume_mm3'], 40);
        $this->assertSame($flat['bbox'], $named['bbox']);
        // in a circle the picture and the name share the room: the badge stays as wide as asked
        $round = $this->meta($this->preview('badge', ['artwork' => 'lib:colour/red-heart', 'line1' => 'Eva', 'width' => 38, 'body' => 'circle'])->assertOk());
        $this->assertEqualsWithDelta(38, $round['bbox']['x'], 0.1);
        $this->assertEqualsWithDelta(38, $round['bbox']['y'], 0.1);
        $this->assertArrayHasKey('caption', $round['notes']);
        // a name too long for a small badge is said, not hidden
        $long = $this->meta($this->preview('badge', ['artwork' => 'lib:colour/smiling-star', 'width' => 25, 'line1' => 'Bohumila Novotná'])->assertOk());
        $this->assertContains('name_small', $long['notes']['warnings']);
        // a name alone is a badge too, but the pocket does not fit into it and the tool says so
        $only = $this->meta($this->preview('badge', ['line1' => 'Jana', 'width' => 40])->assertOk());
        $this->assertContains('magnet_no_room', $only['notes']['warnings']);
        // the page says it in the badge's own words (a pocket for a sticky dot, not a magnet)
        $this->get('/tools/badge-reel')->assertOk()->assertSee(str_replace('\\', '\\\\', substr((string) json_encode(__('param.shape.warn.magnet_no_room.badge')), 1, -1)), false)->assertSee(__('param.o.badge.glue'));
        // the other tools of the family still put a typed name instead of the picture, never under it
        $this->assertArrayNotHasKey('caption', $this->meta($this->preview('charm', ['artwork' => 'lib:colour/smiling-star', 'line1' => 'Jana', 'width' => 40])->assertOk())['notes']);
    }

    public function test_a_medal_brings_the_links_of_its_chain_in_its_own_filament(): void
    {
        $star = ['artwork' => 'lib:colour/smiling-star', 'width' => 80, 'thickness' => 4];
        $bare = $this->meta($this->preview('medallion', $star + ['links' => 0], 'all', true)->assertOk());
        $chained = $this->meta($this->preview('medallion', $star + ['links' => 20], 'all', true)->assertOk());
        // a round plate as wide as asked with an eyelet on top; without links it is the whole plate
        $this->assertEqualsWithDelta(80, $bare['bbox']['x'], 0.1);
        $this->assertGreaterThan(85, $bare['bbox']['y']);
        $this->assertArrayNotHasKey('chain', $bare['notes']);
        $this->assertSame($bare['notes']['outer'], $bare['notes']['each']);
        // twenty links: each an open oval of 30 x 18 with a bar of 4, as tall as the plate; 22 mm of chain apiece
        $link = (M_PI * 9 * 9 + 12 * 18 - (M_PI * 5 * 5 + 12 * 10) - 3.6 * 4) * 4;
        $this->assertEqualsWithDelta(20 * $link, $chained['volume_mm3'] - $bare['volume_mm3'], 0.02 * 20 * $link);
        $this->assertSame(['links' => 20, 'length' => 440], $chained['notes']['chain']);
        // they belong to the plate: the same parts, filaments and changes, and nothing taller than the medal
        $this->assertSame($bare['notes']['parts'], $chained['notes']['parts']);
        $this->assertSame($bare['notes']['filaments'], $chained['notes']['filaments']);
        $this->assertSame($bare['notes']['color_changes'], $chained['notes']['color_changes']);
        $this->assertSame($bare['bbox']['z'], $chained['bbox']['z']);
        $this->assertSame($bare['notes']['each'], $chained['notes']['each']);
        // the medal keeps its corner of the bed (the eyelet is dragged where it was), the links lie beside and above it
        $this->assertSame($bare['notes']['eyelet'], $chained['notes']['eyelet']);
        $this->assertGreaterThan($bare['bbox']['x'] + 30, $chained['bbox']['x']);
        // the biggest medal with the longest chain is still one plate of a 250 mm bed
        $most = $this->meta($this->preview('medallion', ['artwork' => 'lib:colour/smiling-star', 'width' => 120, 'links' => 40])->assertOk());
        $this->assertLessThanOrEqual(240, max($most['bbox']['x'], $most['bbox']['y']));
        $this->assertSame(880, $most['notes']['chain']['length']);
        // a number in a star: letters dark on a light plate, one change, links in the plate's filament
        $first = $this->meta($this->preview('medallion', ['line1' => '1', 'body' => 'star', 'width' => 70, 'links' => 4])->assertOk());
        $this->assertSame(2, $first['notes']['filaments']);
        $this->assertCount(1, $first['notes']['color_changes']);
        $this->preview('medallion', $star + ['links' => 41])->assertStatus(422);
        $this->get('/tools/medallion')->assertOk()->assertSee('data-param="links"', false);
    }

    public function test_an_organizer_is_a_tall_dish_with_compartments_or_round_holes(): void
    {
        $cloud = ['artwork' => 'lib:nature/cloud', 'width' => 120, 'height' => 80, 'thickness' => 2, 'wall' => 1.6];
        $open = $this->meta($this->preview('photo_organizer', $cloud + ['inside' => 'open'], 'all', true)->assertOk());
        $grid = $this->meta($this->preview('photo_organizer', $cloud + ['inside' => 'grid', 'cell' => 40])->assertOk());
        $fine = $this->meta($this->preview('photo_organizer', $cloud + ['inside' => 'grid', 'cell' => 20])->assertOk());
        $holes = $this->meta($this->preview('photo_organizer', $cloud + ['inside' => 'holes', 'hole_d' => 20])->assertOk());
        // as wide and as tall as asked, one part in one filament, nothing of the picture's colours
        $this->assertEqualsWithDelta(120, $open['bbox']['x'], 0.5);
        $this->assertEqualsWithDelta(80, $open['bbox']['z'], 0.01);
        $this->assertSame(['body'], $open['notes']['parts']);
        $this->assertSame(1, $open['notes']['filaments']);
        $this->assertSame([], $open['notes']['color_changes']);
        $this->assertSame(['kind' => 'open', 'count' => 1, 'depth' => 78], $open['notes']['pockets']);
        // dividers add plastic and compartments, a finer grid more of both; the outside stays what it was
        $this->assertSame($open['bbox'], $grid['bbox']);
        $this->assertGreaterThan($open['volume_mm3'] * 1.1, $grid['volume_mm3']);
        $this->assertGreaterThan($grid['volume_mm3'], $fine['volume_mm3']);
        $this->assertGreaterThanOrEqual(4, $grid['notes']['pockets']['count']);
        $this->assertGreaterThan($grid['notes']['pockets']['count'], $fine['notes']['pockets']['count']);
        // round holes are drilled into a solid block: every hole takes its cylinder away from it
        $n = $holes['notes']['pockets']['count'];
        $this->assertGreaterThanOrEqual(5, $n);
        $block = $this->meta($this->preview('photo_organizer', $cloud + ['inside' => 'holes', 'hole_d' => 8])->assertOk());
        $m = $block['notes']['pockets']['count'];
        $this->assertGreaterThan($n, $m);
        $this->assertEqualsWithDelta(($n * 100 - $m * 16) * M_PI * 78, $block['volume_mm3'] - $holes['volume_mm3'], 0.02 * $block['volume_mm3']);
        // in a round body the picture is not needed for anything but the request; a hole too big for the shape is said
        $round = $this->meta($this->preview('photo_organizer', ['artwork' => 'lib:nature/cloud', 'body' => 'circle', 'width' => 90, 'height' => 90, 'inside' => 'holes', 'hole_d' => 20])->assertOk());
        $this->assertSame(7, $round['notes']['pockets']['count']);
        $this->assertEqualsWithDelta(90, $round['bbox']['y'], 0.1);
        $this->assertSame(__('param.error.shape_too_small'), $this->preview('photo_organizer', ['artwork' => 'lib:nature/cloud', 'width' => 60, 'height' => 60, 'inside' => 'holes', 'hole_d' => 40])->assertStatus(422)->json('errors.params.0'));
        // the page has no name to fall back on, so the picture is required
        $this->preview('photo_organizer', ['width' => 120])->assertStatus(422);
        $this->get('/tools/photo-organizer')->assertOk()->assertSee('data-choice="inside"', false)->assertSee('data-when="inside=grid"', false)->assertDontSee('data-text=', false);
    }

    public function test_a_bag_charm_comes_with_the_pin_that_holds_it(): void
    {
        $heart = ['artwork' => 'lib:colour/red-heart', 'width' => 45, 'thickness' => 5, 'bag_hole' => 12, 'bag_wall' => 4];
        $m = $this->meta($this->preview('bag_charm', $heart, 'all', true)->assertOk());
        // the pin: 0.6 mm thinner than the hole, a head 8 mm wider, long enough for the head, the wall of the bag and the pocket
        $this->assertSame(['d' => 11.4, 'head' => 19.4, 'height' => 8.7], $m['notes']['pin']);
        $this->assertSame(['d' => 11.4, 'h' => 3], array_intersect_key($m['notes']['magnet'], ['d' => 1, 'h' => 1]));
        // it lies next to the charm and belongs to its part; the plate is as tall as the pin, the charm itself lower
        $this->assertEqualsWithDelta(45 + 4 + 19.4, $m['bbox']['x'], 1.5);
        $this->assertEqualsWithDelta(8.7, $m['bbox']['z'], 0.01);
        $this->assertSame(8.7, $m['notes']['outer'][2]);
        $this->assertLessThan(8, $m['notes']['each'][2]);
        $this->assertNotContains('pin', $m['notes']['parts']);
        $this->assertFalse($m['notes']['multi_material']);
        // a thicker bag wall makes the pin longer by just that; a smaller hole makes pin and pocket thinner
        $this->assertSame(11.7, $this->meta($this->preview('bag_charm', ['bag_wall' => 7] + $heart)->assertOk())['notes']['pin']['height']);
        $small = $this->meta($this->preview('bag_charm', ['bag_hole' => 8] + $heart)->assertOk());
        $this->assertSame(7.4, $small['notes']['pin']['d']);
        $this->assertLessThan($m['volume_mm3'], $small['volume_mm3'] + 1);
        // the thinnest charm the form allows still keeps four layers over the pocket: nothing has to be thickened
        $thin = $this->meta($this->preview('bag_charm', ['thickness' => 4, 'frame' => 0] + $heart)->assertOk());
        $this->assertArrayNotHasKey('thickened', $thin['notes']);
        $this->get('/tools/bag-charm')->assertOk()->assertSee('data-param="bag_hole"', false)->assertSee(str_replace('\\', '\\\\', substr((string) json_encode(__('param.shape.magnet.fact.bag_charm')), 1, -1)), false);
    }

    public function test_a_big_letter_can_stand_in_a_base_printed_next_to_it(): void
    {
        $ela = ['line1' => 'Ela', 'height' => 120, 'thickness' => 5, 'relief' => 1];
        $flat = $this->meta($this->preview('name_letter', $ela, 'all', true)->assertOk());
        $stood = $this->meta($this->preview('name_letter', $ela + ['stand' => true], 'all', true)->assertOk());
        // thick enough for a slot of 6 mm with a floor under it; a foot under the letter; the base beside it on the bed
        $this->assertEqualsWithDelta(8 + 1, $stood['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta($flat['bbox']['y'] + 7.5, $stood['bbox']['y'], 0.1);
        [$w, $d, $h] = $stood['notes']['stand'];
        $this->assertSame(8.0, (float) $h);
        $this->assertGreaterThanOrEqual(40, $w);
        $this->assertEqualsWithDelta(8 + 26, $d, 0.01);
        $this->assertEqualsWithDelta($flat['bbox']['x'] + 6 + $w, $stood['bbox']['x'], 0.5);
        // the base is as high as the letter is thick: still two filaments one on another and one change, at the letter's top
        $this->assertSame($flat['notes']['parts'], $stood['notes']['parts']);
        $this->assertSame(2, $stood['notes']['filaments']);
        $this->assertFalse($stood['notes']['multi_material']);
        $this->assertSame([8.0], array_map('floatval', array_column($stood['notes']['color_changes'], 'z')));
        $this->assertArrayNotHasKey('stand', $flat['notes']);
        $this->get('/tools/name-letter')->assertOk()->assertSee('data-flag="stand"', false);
    }

    public function test_a_biscuit_takes_sweets_and_sprinkles_and_a_tray_to_lie_in(): void
    {
        $star = ['artwork' => 'lib:cookies/cookie-star', 'width' => 80, 'thickness' => 6];
        $tap = fn (string $tip) => ['c' => 'red', 'w' => 3, 't' => $tip, 'p' => [[0.5, 0.5]]];
        $line = fn (string $tip) => ['c' => 'red', 'w' => 3, 't' => $tip, 'p' => [[0.35, 0.45], [0.5, 0.45], [0.65, 0.45]]];
        $icing = fn (array $stroke) => $this->meta($this->preview('cookie', $star + ['strokes' => [$stroke]], 'icing_1', true)->assertOk());
        // a tap with the sweets nib is one round sweet, nearly three times as wide as a tap of the plain nib
        $dot = $icing($tap('round'));
        $sweet = $icing($tap('candy'));
        $this->assertEqualsWithDelta(3, $dot['bbox']['x'], 0.2);
        $this->assertEqualsWithDelta(2.8 * 3, $sweet['bbox']['x'], 0.3);
        $this->assertEqualsWithDelta(7.84 * $dot['volume_mm3'], $sweet['volume_mm3'], 0.08 * 7.84 * $dot['volume_mm3']);
        // a tap of sprinkles is a handful of little rods; along a stroke they lie every which way, the same on every preview
        $pinch = $icing($tap('sprinkles'));
        $this->assertGreaterThan($dot['volume_mm3'], $pinch['volume_mm3']);
        $scatter = $icing($line('sprinkles'));
        $this->assertGreaterThan($pinch['volume_mm3'], $scatter['volume_mm3']);
        $this->assertSame($scatter['volume_mm3'], $icing($line('sprinkles'))['volume_mm3']);
        $this->assertGreaterThan(3 * 1.2, $scatter['bbox']['y']);
        // sweets along a stroke are sweets, not a line
        $row = $icing($line('candy'));
        $this->assertGreaterThan(2 * $sweet['volume_mm3'] * 0.9, $row['volume_mm3']);
        $this->preview('cookie', $star + ['strokes' => [$tap('glitter')]])->assertStatus(422);

        // the tray: beside the biscuit, in its part and its filament, lower than the biscuit; the drawing stays where it was
        $bare = $this->meta($this->preview('cookie', $star + ['strokes' => [$tap('candy')]], 'all', true)->assertOk());
        $shown = $this->meta($this->preview('cookie', $star + ['tray' => true, 'strokes' => [$tap('candy')]], 'all', true)->assertOk());
        [$w, $d] = $shown['notes']['tray'];
        $this->assertEqualsWithDelta($bare['bbox']['x'] + 6, $w, 0.1);
        $this->assertEqualsWithDelta($bare['bbox']['x'] + 6 + $w, $shown['bbox']['x'], 0.1);
        $this->assertEqualsWithDelta($d, $shown['bbox']['y'], 0.1);
        $this->assertSame($bare['bbox']['z'], $shown['bbox']['z']);
        $this->assertSame($bare['notes']['parts'], $shown['notes']['parts']);
        $this->assertSame($bare['notes']['color_changes'], $shown['notes']['color_changes']);
        $this->assertSame($bare['notes']['frame'], $shown['notes']['frame']);
        $this->assertGreaterThan($bare['volume_mm3'] + 1000, $shown['volume_mm3']);
        // and a place for the list of strokes, where any of them is moved or taken away
        $this->get('/tools/cookie')->assertOk()->assertSee('value="sprinkles"', false)->assertSee('data-flag="tray"', false)->assertSee('id="cookie-strokes"', false);
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
