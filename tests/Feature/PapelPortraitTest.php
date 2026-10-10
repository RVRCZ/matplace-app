<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Domain\Farm\Palette;
use App\Domain\Tools\ParametricGenerator;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\PreviewMeta;
use App\Support\ToolSeo;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Papel picado as a portrait (/tools/papel-picado, treatment "portrait"): a photo of a face in two filaments, the
 * whole panel a backing plate and on it the frame and what of the face is dark. The cut-out mode is PapelPicadoTest.
 */
class PapelPortraitTest extends TestCase
{
    use RefreshDatabase;

    /** a square panel with scallops all round: exactly as big as asked */
    private const PANEL = ['treatment' => 'portrait', 'width' => 150, 'height' => 150, 'border' => 'folk', 'border_mm' => 12, 'scallop_edge' => 'all'];

    /** @var list<string> pictures this test uploaded: uploads lie on the real disk and "my pictures" of another test would list them */
    private array $uploaded = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
        Storage::fake('models');
        // a server without rembg, whatever this machine has installed: the person is cut out only of a picture that brings its own transparency
        putenv('PAPEL_REMBG=off');
    }

    protected function tearDown(): void
    {
        foreach ($this->uploaded as $path) {
            @unlink($path);
            @unlink(preg_replace('/\.[a-z]+$/', '.json', $path));
        }
        putenv('PAPEL_REMBG');
        parent::tearDown();
    }

    private function preview(array $params, string $part = 'all')
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => 'papel', 'params' => $params + self::PANEL, 'part' => $part, 'pieces' => $part === 'all']);
    }

    /** @return array<string, mixed> */
    private function meta(array $params, string $part = 'all'): array
    {
        return (array) PreviewMeta::whole($this->preview($params, $part)->assertOk()->headers->get('X-Model-Meta'));      // the small picture of the result may wait beside the header
    }

    /**
     * A drawn "photo" of a person: a light face under dark hair, two eyes, a mouth, a dark coat. As a JPG it stands
     * before a grey wall; as a cut-out it is a PNG with nothing round the person (what rembg would hand back).
     */
    private function face(bool $cutOut = false): string
    {
        $img = imagecreatetruecolor(360, 480);
        if ($cutOut) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
            imagealphablending($img, true);
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, 150, 160, 172));
        }
        $skin = imagecolorallocate($img, 232, 205, 186);
        $dark = imagecolorallocate($img, 34, 30, 34);
        imagefilledellipse($img, 180, 470, 330, 230, imagecolorallocate($img, 44, 48, 74));       // the coat
        imagefilledrectangle($img, 150, 300, 210, 372, $skin);                                     // the neck
        imagefilledellipse($img, 180, 205, 190, 250, $skin);                                       // the face
        imagefilledarc($img, 180, 180, 204, 236, 180, 360, imagecolorallocate($img, 52, 38, 30), IMG_ARC_PIE);      // the hair
        imagefilledellipse($img, 140, 215, 28, 18, $dark);
        imagefilledellipse($img, 220, 215, 28, 18, $dark);
        imagefilledellipse($img, 180, 288, 62, 14, imagecolorallocate($img, 150, 58, 60));         // the mouth
        $tmp = tempnam(sys_get_temp_dir(), 'papelface').($cutOut ? '.png' : '.jpg');
        if ($cutOut) {
            imagepng($img, $tmp);
        } else {
            imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
            imagejpeg($img, $tmp, 90);
        }
        imagedestroy($img);

        return $this->upload($tmp, $cutOut ? 'person.png' : 'person.jpg', $cutOut ? 'image/png' : 'image/jpeg');
    }

    /** A picture file → the reference of its upload; the upload is taken off the disk again when the test ends. */
    private function upload(string $file, string $name, string $mime): string
    {
        $ref = (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($file, $name, $mime, null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
        $this->uploaded[] = (string) ParametricGenerator::artworkPath($ref);

        return $ref;
    }

    /** The small picture of the result the page shows: [width, height, share of dark, middle of the dark by x (0–1), by y (0–1)]. */
    private function seen(array $meta): array
    {
        $img = imagecreatefromstring((string) base64_decode((string) $meta['notes']['preview'], true));
        $this->assertNotFalse($img);
        [$w, $h] = [imagesx($img), imagesy($img)];
        [$n, $sx, $sy] = [0, 0, 0];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (imagecolorsforindex($img, imagecolorat($img, $x, $y))['red'] < 128) {
                    $n++;
                    $sx += $x;
                    $sy += $y;
                }
            }
        }

        return [$w, $h, $n / ($w * $h), $n ? $sx / $n / $w : 0.5, $n ? $sy / $n / $h : 0.5];
    }

    public function test_a_portrait_is_a_backing_with_the_frame_and_the_face_on_it(): void
    {
        $face = ['artwork' => $this->face()];
        $m = $this->meta($face);
        // as big as asked, scallops included, and as high as the backing and the layer on it together
        $this->assertEqualsWithDelta(150, $m['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(150, $m['bbox']['y'], 0.05);
        $this->assertEqualsWithDelta(2.6, $m['bbox']['z'], 0.01);
        $this->assertSame([150.0, 150.0, 2.6], array_map('floatval', $m['notes']['outer']));
        // two parts, in the order they are printed; the preview brings each as a piece of its own
        $this->assertSame(['body', 'details'], $m['notes']['parts']);
        $this->assertSame(['body', 'details'], array_column($m['parts'], 'name'));
        $body = $this->meta($face, 'body');
        $details = $this->meta($face, 'details');
        $this->assertEqualsWithDelta(2.0, $body['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta(0.6, $details['bbox']['z'], 0.01);
        $this->assertGreaterThan(0, $details['volume_mm3']);
        $this->assertLessThan($body['volume_mm3'], $details['volume_mm3']);
        // the two together are the one solid of a one-colour print: nothing is counted twice, nothing is missing
        $this->assertEqualsWithDelta($body['volume_mm3'] + $details['volume_mm3'], $m['volume_mm3'], 1.0);
        // the print changes filament once, where the backing ends; the backing is the light one of the two
        $this->assertCount(1, $m['notes']['color_changes']);
        $change = $m['notes']['color_changes'][0];
        $this->assertEqualsWithDelta(2.0, $change['z'], 0.001);
        $this->assertSame('details', $change['part']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $change['hex']);
        $this->assertSame($change['hex'], $m['notes']['paint']['details']);
        $this->assertSame(['body', 'details'], array_keys($m['notes']['part_colors']));
        $light = fn (string $hex) => array_sum(sscanf($hex, '#%2x%2x%2x'));
        $this->assertGreaterThan($light($m['notes']['paint']['details']), $light($m['notes']['paint']['body']));
        // nobody chose colours: cream paper under dark blue, the look the tool is shown in
        $this->assertSame(['#ede6d6', '#213d78'], [strtolower($m['notes']['paint']['body']), strtolower($m['notes']['paint']['details'])]);
        $this->assertFalse($m['notes']['multi_material']);
        // other sizes of the two layers: the height follows, the change moves with the backing
        $thick = $this->meta($face + ['base' => 3, 'relief' => 1.2]);
        $this->assertEqualsWithDelta(4.2, $thick['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta(3.0, $thick['notes']['color_changes'][0]['z'], 0.001);
        // the small picture of the result is a PNG light enough for a response header
        [$w, $h, $share] = $this->seen($m);
        $this->assertLessThanOrEqual(300, max($w, $h));
        $this->assertLessThan(4000, strlen($m['notes']['preview']));
        $this->assertGreaterThan(0.05, $share);
        $this->assertLessThan(0.9, $share);
        // a face needs no ties: nothing of it hangs in the air
        $this->assertArrayNotHasKey('ties', $m['notes']);
        $this->assertSame([], array_values(array_diff($m['notes']['warnings'], ['portrait_whole'])));
    }

    public function test_the_design_keeps_its_two_filaments_and_the_height_they_change_at(): void
    {
        $r = $this->postJson('/api/tools/param', ['kind' => 'papel', 'params' => ['artwork' => $this->face(), 'portrait_x' => 6, 'portrait_scale' => 0.9] + self::PANEL])->assertCreated();
        $this->assertSame(['body', 'details'], $r->json('file.parts'));
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $p = $file->tool_params;
        $this->assertSame('portrait', $p['treatment']);
        $this->assertSame(['body', 'details'], $p['parts']);
        $this->assertFalse($p['multi_material']);
        $this->assertCount(1, $p['color_changes']);
        $this->assertEqualsWithDelta(2.0, $p['color_changes'][0]['z'], 0.001);
        $this->assertSame('details', $p['color_changes'][0]['part']);
        // what the farm and the slicer project read: one change at 2 mm, in the dark filament
        $changes = $file->colorChanges();
        $this->assertCount(1, $changes);
        $this->assertEqualsWithDelta(2.0, $changes[0]['z'], 0.001);
        $this->assertSame($p['color_changes'][0]['hex'], $changes[0]['hex']);
        // the slicer project of the download stops the printer for the second filament in the first layer above the backing
        $project = $this->get('/api/files/'.$file->uuid.'/project.3mf?printer=prusa-mk4s&quality=standard')->assertOk();
        $zip = new \ZipArchive;
        $zip->open($project->baseResponse->getFile()->getPathname());
        $stops = (string) $zip->getFromName('Metadata/custom_gcode_per_layer.xml');
        $zip->close();
        $this->assertStringContainsString('top_z="2.20"', $stops);
        $this->assertStringContainsString('gcode="M600"', $stops);
        $this->assertStringContainsStringIgnoringCase('color="'.$p['color_changes'][0]['hex'].'"', $stops);
        $this->assertSame(1, substr_count($stops, 'gcode="M600"'), 'one change, no more');
        // where the visitor put the portrait goes with the design, and the photo is kept with it
        $this->assertEqualsWithDelta(6.0, $p['portrait_x'], 0.001);
        $this->assertEqualsWithDelta(0.9, $p['portrait_scale'], 0.001);
        $this->assertSame('file:'.$file->uuid, $p['artwork']);
        // the two colours lie on one plate: they are not parts the farm would print one by one
        $this->assertArrayNotHasKey('parts_bbox', $p);
        $this->assertSame([], OrderService::designParts($file));
        // a colour chosen by the visitor wins over the tool's own
        if ($codes = app(Palette::class)->codes()) {
            $own = $this->meta(['artwork' => $this->face(), 'part_colors' => ['details' => $codes[0], 'body' => $codes[0]]]);
            $this->assertSame($own['notes']['paint']['body'], $own['notes']['paint']['details']);
            $this->assertSame([], $own['notes']['color_changes'], 'one filament for both: nothing to change');
            // a backing in the very filament the frame would take by itself: the frame takes the other end, the picture stays readable
            $dark = $this->meta(['artwork' => $this->face()])['notes']['part_colors']['details']['code'];
            if ($dark !== '') {
                $turned = $this->meta(['artwork' => $this->face(), 'part_colors' => ['body' => $dark]]);
                $this->assertNotSame($turned['notes']['paint']['body'], $turned['notes']['paint']['details']);
                $this->assertCount(1, $turned['notes']['color_changes']);
            }
        }
    }

    public function test_a_photo_is_trimmed_reversed_and_read_finer_or_coarser(): void
    {
        $face = ['artwork' => $this->face()];
        $m = $this->meta($face);
        // rembg is not here: the photo is used whole and the page is told so (it greys the tick)
        $this->assertFalse($m['notes']['rembg']);
        $this->assertFalse($m['notes']['isolated']);
        $this->assertContains('portrait_whole', $m['notes']['warnings']);
        $this->assertNotSame('', (string) ($m['notes']['rembg_error'] ?? ''), 'why it could not is told to whoever reads the answer');
        $asked = $this->meta($face + ['isolate' => false]);
        $this->assertNull($asked['notes']['rembg']);
        $this->assertNotContains('portrait_whole', $asked['notes']['warnings']);
        // trimming below the shoulders cuts the photo from below: less of its height is used, the face is bigger
        $whole = $this->meta($face + ['trim' => 0]);
        $half = $this->meta($face + ['trim' => 50]);
        $height = fn (array $x) => $x['notes']['portrait']['crop'][3] - $x['notes']['portrait']['crop'][1];
        $this->assertSame(480, $height($whole));
        $this->assertSame(240, $height($half));
        $this->assertSame(0, $half['notes']['portrait']['crop'][1], 'the top of the photo stays');
        // reversed: what was dark is light
        $reversed = $this->meta($face + ['invert' => true]);
        $this->assertEqualsWithDelta(100, $m['notes']['portrait']['dark_pct'] + $reversed['notes']['portrait']['dark_pct'], 1);
        // more "dark": more of the face is printed in the second filament
        $this->assertGreaterThan($m['notes']['portrait']['dark_pct'], $this->meta($face + ['darkness' => 80])['notes']['portrait']['dark_pct']);
        $this->assertLessThan($m['notes']['portrait']['dark_pct'], $this->meta($face + ['darkness' => 20])['notes']['portrait']['dark_pct']);
        // the detail is how fine the face is read: the eyes and the mouth are there at any setting, the model differs
        $coarse = $this->meta($face + ['detail' => 0]);
        $fine = $this->meta($face + ['detail' => 100]);
        $this->assertNotEquals(round($coarse['volume_mm3']), round($fine['volume_mm3']));
        // a picture with nothing in it is no portrait: the visitor is told what to try
        $blank = imagecreatetruecolor(200, 200);
        imagefill($blank, 0, 0, imagecolorallocate($blank, 250, 250, 250));
        $tmp = tempnam(sys_get_temp_dir(), 'papelblank').'.png';
        imagepng($blank, $tmp);
        $ref = $this->upload($tmp, 'blank.png', 'image/png');
        $this->preview(['artwork' => $ref])->assertStatus(422)->assertJsonPath('errors.params.0', __('param.error.portrait_blank', ['n' => 0]));
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertNotSame('param.error.portrait_blank', __('param.error.portrait_blank', [], $locale));
        }
    }

    public function test_a_person_cut_out_of_the_background_stands_on_a_light_or_a_dark_ground(): void
    {
        // a picture with its own transparency is its own cut-out: no rembg is asked
        $person = ['artwork' => $this->face(true)];
        $light = $this->meta($person);
        $this->assertTrue($light['notes']['isolated']);
        $this->assertNull($light['notes']['rembg']);
        $this->assertSame([], $light['notes']['warnings']);
        // trimming goes from the person's own lower edge, and the picture is cut to the person
        $crop = $light['notes']['portrait']['crop'];
        $this->assertLessThan(480, $crop[3]);
        $this->assertGreaterThan(20, $crop[1], 'the empty top of the picture is cut away, but for a little air above the head');
        // a dark ground round the person, opened by the border's flowers: more of the second filament, the same backing
        $dark = $this->meta($person + ['backdrop' => 'pattern']);
        $this->assertGreaterThan(1.3 * $this->meta($person, 'details')['volume_mm3'], $this->meta($person + ['backdrop' => 'pattern'], 'details')['volume_mm3']);
        $this->assertEqualsWithDelta($this->meta($person, 'body')['volume_mm3'], $this->meta($person + ['backdrop' => 'pattern'], 'body')['volume_mm3'], 0.5);
        $this->assertGreaterThan($this->seen($light)[2] + 0.15, $this->seen($dark)[2]);
        // a denser pattern cuts more flowers into the ground
        $this->assertGreaterThan($this->meta($person + ['backdrop' => 'pattern', 'density' => 1], 'details')['volume_mm3'], $this->meta($person + ['backdrop' => 'pattern', 'density' => 0.2], 'details')['volume_mm3']);
        // a drawing in colours (the face of the library) is read like a photo that brings its own cut-out; none of it is trimmed
        $drawn = $this->meta(['artwork' => 'lib:colour/portrait-woman', 'backdrop' => 'pattern']);
        $this->assertTrue($drawn['notes']['isolated']);
        $this->assertArrayHasKey('preview', $drawn['notes']);
        $this->assertSame([], $drawn['notes']['warnings']);
        $this->assertEqualsWithDelta($this->meta(['artwork' => 'lib:colour/portrait-woman'], 'details')['volume_mm3'], $this->meta(['artwork' => 'lib:colour/portrait-woman', 'trim' => 50], 'details')['volume_mm3'], 0.5);
        // a silhouette of one colour stays the outline it is: dark on the backing, nothing to cut out of anything
        $skull = $this->meta(['artwork' => 'lib:holidays/sugar-skull', 'backdrop' => 'pattern']);
        $this->assertFalse($skull['notes']['isolated']);
        $this->assertArrayNotHasKey('preview', $skull['notes']);
        // a photo nobody was cut out of has no ground to darken: the choice changes nothing
        $photo = ['artwork' => $this->face(), 'isolate' => false];
        $this->assertEqualsWithDelta($this->meta($photo, 'details')['volume_mm3'], $this->meta($photo + ['backdrop' => 'pattern'], 'details')['volume_mm3'], 0.5);
    }

    public function test_the_portrait_is_moved_sized_and_turned_inside_its_window(): void
    {
        // a person at 60 % of the window leaves room to move in
        $person = ['artwork' => $this->face(true), 'portrait_scale' => 0.6];
        $mid = $this->meta($person);
        [$w, $h] = $mid['notes']['portrait']['window'];
        $this->assertEqualsWithDelta(150 - 2 * 12 - 2 * 3.75, $w, 0.2, 'the window is the panel less its scallops and its border');
        $box = $mid['notes']['portrait']['box'];
        $this->assertEqualsWithDelta(0.6 * $w, $box[2] - $box[0], 0.05);
        $this->assertEqualsWithDelta(75, ($box[0] + $box[2]) / 2, 0.05);
        // to the right and up: the frame the visitor drags follows, and so does what is printed
        $moved = $this->meta($person + ['portrait_x' => 20, 'portrait_y' => 10]);
        $to = $moved['notes']['portrait']['box'];
        $this->assertEqualsWithDelta($box[0] + 20, $to[0], 0.05);
        $this->assertEqualsWithDelta($box[1] + 10, $to[1], 0.05);
        [, , , $x0, $y0] = $this->seen($mid);
        [, , , $x1, $y1] = $this->seen($moved);
        $this->assertEqualsWithDelta(20 / $w, $x1 - $x0, 0.03);
        $this->assertEqualsWithDelta(-10 / $h, $y1 - $y0, 0.03, 'up on the panel is up in the picture');
        // bigger: more of the window is the person
        $this->assertGreaterThan($this->seen($mid)[2], $this->seen($this->meta(['portrait_scale' => 1.2] + $person))[2]);
        // turned: the frame grows to the box of the turned picture
        $turned = $this->meta($person + ['portrait_turn' => 30])['notes']['portrait']['box'];
        $this->assertEqualsWithDelta(0.6 * $w * (cos(deg2rad(30)) + sin(deg2rad(30))), $turned[2] - $turned[0], 0.1);
        // however it is sized, turned and pushed, the portrait stays in its window: the panel keeps its size and
        // the layer on the backing never holds more than the backing does
        $far = ['portrait_scale' => 1.5, 'portrait_turn' => 45, 'portrait_x' => 125, 'portrait_y' => -125] + $person;
        $out = $this->meta($far);
        $this->assertEqualsWithDelta(150, $out['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(150, $out['bbox']['y'], 0.05);
        $this->assertEqualsWithDelta($w / 2, ($out['notes']['portrait']['box'][0] + $out['notes']['portrait']['box'][2]) / 2 - 75, 0.05, 'the middle of the picture stops at the edge of the window');
        $this->assertLessThanOrEqual($this->meta($far, 'body')['volume_mm3'] * 0.6 / 2.0 + 1, $this->meta($far, 'details')['volume_mm3']);
        // a drawing is placed the same way, and is never trimmed
        $skull = ['artwork' => 'lib:holidays/sugar-skull', 'portrait_scale' => 0.5];
        $this->assertNotEquals(round($this->meta($skull, 'details')['volume_mm3']), round($this->meta(['portrait_scale' => 1] + $skull, 'details')['volume_mm3']));
        $this->assertEqualsWithDelta($this->meta($skull, 'details')['volume_mm3'], $this->meta($skull + ['trim' => 50, 'portrait_x' => 10], 'details')['volume_mm3'], 0.5);
    }

    public function test_frames_of_every_kind_are_sound_solids_from_the_smallest_panel_to_the_biggest(): void
    {
        $person = $this->face(true);
        foreach ([80, 250] as $size) {
            $seen = [];
            foreach ([['border' => 'folk'], ['border' => 'folk', 'density' => 0.2], ['border' => 'folk', 'density' => 1], ['border' => 'stars', 'scallop_edge' => 'bottom'], ['border' => 'none'], ['border' => 'hearts', 'scallop' => false, 'string_holes' => false]] as $i => $frame) {
                // (the tool answers only with a closed solid of some volume: anything else is a 422)
                $m = $this->meta($frame + ['artwork' => $person, 'width' => $size, 'height' => $size, 'border_mm' => $size > 100 ? 20 : 8, 'backdrop' => 'pattern']);
                $this->assertGreaterThan(0, $m['volume_mm3'], "$size #$i");
                $this->assertEqualsWithDelta(2.6, $m['bbox']['z'], 0.01);
                $this->assertEqualsWithDelta($size, $m['bbox']['x'], 0.05, 'scallops never widen the panel');
                $seen[] = round($m['volume_mm3']);
            }
            $this->assertCount(count($seen), array_unique($seen), "every frame of $size mm is another solid");
            // scallops all round are part of the size; on the lower edge alone they hang below it, as they always did
            $this->assertEqualsWithDelta($size, $this->meta(['artwork' => $person, 'width' => $size, 'height' => $size, 'border_mm' => 8])['bbox']['y'], 0.05);
            $this->assertGreaterThan($size + 2, $this->meta(['artwork' => $person, 'width' => $size, 'height' => $size, 'border_mm' => 8, 'scallop_edge' => 'bottom'])['bbox']['y']);
        }
        // a wider border leaves a smaller window
        $narrow = $this->meta(['artwork' => $person, 'border_mm' => 8])['notes']['portrait']['window'][0];
        $this->assertEqualsWithDelta($narrow - 2 * 10, $this->meta(['artwork' => $person, 'border_mm' => 18])['notes']['portrait']['window'][0], 0.2);
        // a frame alone, nothing in its window, is a plate to write on
        $empty = $this->meta([]);
        $this->assertSame(['body', 'details'], $empty['notes']['parts']);
        $this->assertArrayNotHasKey('preview', $empty['notes']);
        // the cut-out learnt the new frames too, and keeps the border it always had when none is named
        $skull = ['kind' => 'papel', 'params' => ['artwork' => 'lib:holidays/sugar-skull', 'width' => 150, 'height' => 200]];
        $volume = fn (array $more) => (array) PreviewMeta::whole($this->postJson('/api/tools/param/preview', ['kind' => 'papel', 'params' => $skull['params'] + $more])->assertOk()->headers->get('X-Model-Meta'));
        $old = $volume([]);
        $this->assertEqualsWithDelta($old['volume_mm3'], $volume(['border_mm' => 19.5, 'density' => 0.5, 'scallop_edge' => 'bottom'])['volume_mm3'], 0.5, '13 % of the shorter side');
        $this->assertSame(1, $old['notes']['ties']);
        // a narrow border holds less paper than the skull in it: it is still the frame, the skull is tied to it
        $thin = $volume(['border_mm' => 8]);
        $this->assertSame(1, $thin['notes']['ties']);
        $this->assertEqualsWithDelta(150, $thin['bbox']['x'], 0.1);
        $all = $volume(['border' => 'folk', 'scallop_edge' => 'all', 'border_mm' => 20]);
        $this->assertEqualsWithDelta(200, $all['bbox']['y'], 0.1);
        $this->assertEqualsWithDelta(1.2, $all['bbox']['z'], 0.01);
        foreach (['cs', 'en', 'es'] as $locale) {
            foreach (['o.papel.folk', 'o.papel.all', 'o.papel.bottom', 'o.papel.plain', 'o.papel.pattern', 'o.papel.cutout', 'o.papel.portrait', 'c.papel.treatment', 'c.papel.scallop_edge', 'c.papel.backdrop', 'flag.isolate', 'part.body.papel', 'part.details.papel', 'warn.portrait_fine', 'warn.portrait_whole'] as $key) {
                $this->assertNotSame('param.'.$key, __('param.'.$key, [], $locale), "$locale $key");
            }
            foreach (array_keys(ParametricGenerator::FIELDS['papel']) as $field) {
                $this->assertTrue(Lang::has('param.f.papel.'.$field, $locale) || Lang::has('param.f.'.$field, $locale), "$locale $field");
            }
        }
    }

    public function test_the_page_has_the_six_steps_of_a_portrait_in_three_languages(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/papel-picado')->assertOk()->getContent();
            $this->assertStringContainsString(e(ToolSeo::texts('papel', $locale)['h1']), $html);
            // photo · frame · size · placement · colours · print, in this order
            $at = array_map(fn (string $id) => strpos($html, 'data-section="'.$id.'"'), ['input', 'frame', 'size', 'placement', 'colors', 'print']);
            $this->assertNotContains(false, $at, $locale);
            $sorted = $at;
            sort($sorted);
            $this->assertSame($sorted, $at, $locale);
            foreach (['input', 'frame', 'size', 'placement'] as $step) {
                $this->assertStringContainsString(e(__('param.papel.step.'.$step, [], $locale)).'</a>', $html);
            }
            // cut-out or portrait comes first; then the picture and how it is read
            $this->assertLessThan(strpos($html, 'id="param-artwork-open"'), strpos($html, 'data-choice="treatment"'));
            foreach (['darkness', 'detail', 'trim', 'soften', 'density', 'border_mm', 'width', 'height', 'base', 'relief', 'thickness', 'portrait_scale', 'portrait_x', 'portrait_y', 'portrait_turn'] as $field) {
                $this->assertStringContainsString('data-param="'.$field.'"', $html, "$locale $field");
            }
            foreach (['isolate', 'invert', 'scallop', 'string_holes'] as $flag) {
                $this->assertStringContainsString('data-flag="'.$flag.'"', $html);
            }
            foreach (['border', 'scallop_edge', 'backdrop'] as $choice) {
                $this->assertStringContainsString('data-choice="'.$choice.'"', $html);
            }
            // the photo goes in right in the first step: a field to click or drop on, before the button of the library
            $this->assertLessThan(strpos($html, 'id="param-artwork-open"'), strpos($html, 'id="param-artwork-drop"'));
            $this->assertStringContainsString('id="param-artwork-file" type="file"', $html);
            $this->assertStringContainsString(e(__('param.papel.upload', [], $locale)), $html);
            $this->assertStringContainsString(e(__('param.papel.library', [], $locale)), $html);
            foreach (['id="papel-thumbs"', 'id="papel-portrait"', 'id="papel-isolate-off"', 'id="papel-place-reset"'] as $hook) {
                $this->assertStringContainsString($hook, $html);
            }
            // what belongs to one of the two only is marked so: the script folds it away for the other
            $this->assertMatchesRegularExpression('/data-when="treatment=portrait"[^>]*data-field="detail"/', $html);
            $this->assertMatchesRegularExpression('/data-when="treatment=cutout"[^>]*data-field="soften"/', $html);
            $this->assertStringContainsString(e(__('param.papel.place.reset', [], $locale)), $html);
            $this->assertStringContainsString(e(__('param.papel.isolate.off', [], $locale)), $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
    }

    /** The farm's start page ticks two spools of one machine for a portrait: the light one first, the dark one for the change. */
    public function test_printing_with_us_starts_with_the_light_and_the_dark_spool(): void
    {
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create(['email' => 'portrait@example.com']);
        // the S1 holds a white spool; a black and a red one of the same family join it
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $white = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail()->color;
        $white->update(['hex' => '#F4F4F0']);
        [$black, $red] = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'like', 'PLA%'))->where('id', '!=', $white->id)->take(2)->get()->all();
        $black->update(['hex' => '#101012', 'enabled' => true]);
        $red->update(['hex' => '#C01818', 'enabled' => true]);
        $free = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->get();
        $free[0]->update(['farm_color_id' => $red->id, 'remaining_g' => 800, 'enabled' => true]);
        $free[1]->update(['farm_color_id' => $black->id, 'remaining_g' => 800, 'enabled' => true]);

        $uuid = $this->actingAs($user)->postJson('/api/tools/param', ['kind' => 'papel', 'params' => ['artwork' => $this->face(), 'part_colors' => ['body' => $white->code, 'details' => $black->code]] + self::PANEL])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame($white->code, $file->tool_params['part_colors']['body']['code']);
        $this->assertSame($black->code, $file->tool_params['color_changes'][0]['code']);
        $html = $this->actingAs($user)->get('/farm?file='.$uuid)->assertOk()->getContent();
        $ticked = fn (string $name) => preg_match('/name="'.preg_quote($name, '/').'" value="(\d*)"[^>]*\schecked/', $html, $found) ? (int) $found[1] : null;
        $this->assertSame($white->id, $ticked('color'), 'the backing in the light spool');
        $this->assertSame($black->id, $ticked('change_color[0]'), 'the frame and the face in the dark spool of that machine');
        $this->assertStringContainsString('data-want="'.strtolower($file->tool_params['color_changes'][0]['hex']).'"', strtolower($html));
        // the order carries the change to the printer: the slot of the black spool, at the top of the backing
        $order = FarmOrder::where('token', basename($this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid, 'color' => $white->id, 'change_color' => [$black->id]])->assertCreated()->json('url')))->firstOrFail();
        $this->assertSame([['slot' => (int) $free[1]->slot, 'z' => 2.0]], $order->colorChanges());
        $this->assertFalse($order->isByParts());
    }
}
