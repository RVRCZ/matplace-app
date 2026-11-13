<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\PreviewMeta;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Decorations, round 2 (docs/AR.md §12): the background of a photo goes, the preview says where every decoration lies
 * (for the frame that drags it on the front or the back), the texts are listed in the first step, a relief may have a
 * filament of its own, and a design whose coloured decoration stands on its plate is printed on one plate by the farm.
 */
class DecorRound2Test extends TestCase
{
    use RefreshDatabase;

    private const PLAIN = [
        'medallion' => ['line1' => '', 'motif' => 'raised', 'body' => 'circle', 'width' => 60, 'links' => 0],
        'keychain' => ['line1' => '', 'body' => 'rect', 'width' => 60],
        'ornament' => ['line1' => '', 'body' => 'circle', 'width' => 80],
        'coaster' => ['line1' => '', 'body' => 'circle', 'width' => 100],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // a server without the network that cuts a subject out, whatever this machine has installed: only a picture that
        // brings its own transparency loses its background here
        putenv('PAPEL_REMBG=off');
    }

    protected function tearDown(): void
    {
        putenv('PAPEL_REMBG');
        parent::tearDown();
    }

    private function engine(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    /** @return array<string, mixed> the whole meta of a preview, its notes under `_notes` */
    private function notes(string $kind, array $params, string $view = 'use'): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'part' => 'all', 'view' => $view, 'pieces' => true])->assertOk();
        $meta = PreviewMeta::whole($r->headers->get('X-Model-Meta'));

        return $meta + ['_notes' => $meta['notes']];
    }

    /**
     * A drawn head, shaded, on a busy ground; with `$cut` the ground is transparent (a picture that is its own mask).
     * No photograph, no real person.
     */
    private function photo(bool $cut): string
    {
        $img = imagecreatetruecolor(300, 300);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, $cut ? imagecolorallocatealpha($img, 0, 0, 0, 127) : imagecolorallocate($img, 200, 200, 190));
        if (! $cut) {
            for ($i = 0; $i < 300; $i += 20) {
                imagefilledrectangle($img, $i, 0, $i + 9, 299, imagecolorallocate($img, 60, 90, 60));
            }
        }
        for ($r = 90; $r > 0; $r -= 4) {
            $v = (int) (70 + (90 - $r) * 1.9);
            imagefilledellipse($img, 150, 160, 2 * $r, (int) (2.3 * $r), imagecolorallocate($img, $v, $v, $v));
        }
        imagefilledellipse($img, 122, 140, 22, 14, imagecolorallocate($img, 25, 25, 28));
        imagefilledellipse($img, 178, 140, 22, 14, imagecolorallocate($img, 25, 25, 28));
        $tmp = tempnam(sys_get_temp_dir(), 'mpdecor2').'.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($tmp, 'head.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    private function relief(string $ref, array $over = []): array
    {
        return $over + ['kind' => 'photo', 'face' => 'front', 'mode' => 'raised', 'image' => $ref, 'size' => 60, 'depth' => 1.2, 'frame' => 'circle'];
    }

    private function text(array $over = []): array
    {
        return $over + ['kind' => 'text', 'face' => 'front', 'mode' => 'raised', 'lines' => ['Jana'], 'typeface' => 'archivo', 'size' => 40, 'depth' => 0.6];
    }

    public function test_the_record_keeps_the_background_of_a_photo_and_the_colour_of_a_relief(): void
    {
        $clean = ParametricGenerator::cleanDecor([
            ['kind' => 'photo', 'image' => 'lib:animals/cat'],                                  // stored before round 2: no word about the background
            ['kind' => 'photo', 'image' => 'lib:animals/cat', 'background' => 'remove', 'color' => 'red'],
            ['kind' => 'photo', 'image' => 'lib:animals/cat', 'background' => 'blurred'],
            ['kind' => 'text', 'lines' => ['a'], 'background' => 'remove'],
        ]);
        $this->assertSame(['keep', 'remove', 'keep'], array_column(array_slice($clean, 0, 3), 'background'), 'a record without the key is built as it always was');
        $this->assertNull($clean[0]['color']);
        $this->assertSame('red', $clean[1]['color']['code'], 'a relief may have a filament of its own');
        $this->assertArrayNotHasKey('background', $clean[3]);
        $this->assertSame($clean, ParametricGenerator::cleanDecor($clean));
        // a word the record does not know is refused by the API, in the visitor's language
        $this->postJson('/api/tools/param/preview', ['kind' => 'ornament', 'params' => self::PLAIN['ornament'] + ['decor' => [['kind' => 'photo', 'image' => 'lib:animals/cat', 'background' => 'blurred']]]])
            ->assertStatus(422)->assertJsonFragment([__('param.error.decor_bad')]);
    }

    public function test_the_background_of_a_photo_goes_and_the_subject_alone_stands_on_the_product(): void
    {
        $this->engine();
        $cut = $this->photo(true);
        $kept = $this->notes('ornament', self::PLAIN['ornament'] + ['decor' => [$this->relief($cut, ['background' => 'keep'])]]);
        $old = $this->notes('ornament', self::PLAIN['ornament'] + ['decor' => [$this->relief($cut)]]);
        $gone = $this->notes('ornament', self::PLAIN['ornament'] + ['decor' => [$this->relief($cut, ['background' => 'remove'])]]);
        // a design stored before the key existed is the design it was: the whole photo in its round frame
        $this->assertSame($kept['volume_mm3'], $old['volume_mm3']);
        $this->assertArrayNotHasKey('cutout', $old['_notes']['decor'][0]);
        // without the background only the head stands: less plastic than the disc of the whole photo, and the outline the
        // page keeps on the face is the head's, narrower than the frame the photo is sized by
        $placed = $gone['_notes']['decor'][0];
        $this->assertTrue($placed['cutout']);
        $this->assertLessThan($kept['volume_mm3'] - 200, $gone['volume_mm3']);
        $this->assertNotContains('decor_no_cutout', $gone['_notes']['warnings'] ?? []);
        $frame = $placed['view']['box'];
        $this->assertEqualsWithDelta(0.6 * 80, $frame[2] - $frame[0], 0.3, 'the frame is the size the visitor set: 60 % of the face');
        $xs = array_column($placed['view']['hull'], 0);
        $this->assertLessThan(0.75 * ($frame[2] - $frame[0]), max($xs) - min($xs), 'the head is narrower than its frame');
        $this->assertGreaterThan(0.3 * ($frame[2] - $frame[0]), max($xs) - min($xs));
        // dark parts of the subject still stand out of the plate: the relief starts a step above it
        $this->assertGreaterThanOrEqual(3.0 - 0.01, $placed['z'][0]);
        $this->assertEqualsWithDelta(3.0 + 1.2, $placed['z'][1], 0.02);

        // a photo the server cannot cut out (no network here): built of the whole photo, and said so
        $whole = $this->photo(false);
        $failed = $this->notes('ornament', self::PLAIN['ornament'] + ['decor' => [$this->relief($whole, ['background' => 'remove'])]]);
        $this->assertFalse($failed['_notes']['decor'][0]['cutout']);
        $this->assertContains('decor_no_cutout', $failed['_notes']['warnings']);
        $same = $this->notes('ornament', self::PLAIN['ornament'] + ['decor' => [$this->relief($whole, ['background' => 'keep'])]]);
        $this->assertSame($same['volume_mm3'], $failed['volume_mm3']);
        $this->assertNotSame('param.warn.decor_no_cutout', __('param.warn.decor_no_cutout'));
    }

    public function test_the_preview_says_where_a_decoration_lies_on_the_side_it_is_looked_at(): void
    {
        $this->engine();
        $decor = [$this->text(['x' => 20, 'y' => 10]), $this->text(['face' => 'back', 'mode' => 'engraved', 'x' => 20, 'y' => 10, 'lines' => ['2026']])];
        // a key ring printed face up, a medal with its motif flush printed face down and shown turned over: the same
        // record lies at the same place of the face in both previews
        foreach (['keychain' => self::PLAIN['keychain'], 'medallion' => ['line1' => '1', 'motif' => 'flush', 'width' => 60, 'links' => 6]] as $kind => $params) {
            $n = $this->notes($kind, $params + ['decor' => $decor])['_notes'];
            $face = $n['decor_face'];
            $this->assertEqualsWithDelta(60.0, $face['width'], 0.01, $kind);
            [$front, $back] = $n['decor'];
            $mid = fn (array $d) => [($d['view']['box'][0] + $d['view']['box'][2]) / 2, ($d['view']['box'][1] + $d['view']['box'][3]) / 2];
            // the front: right of the middle by 20 % of the face, above it by 10 %
            $this->assertEqualsWithDelta([$face['centre'][0] + 12.0, $face['centre'][1] + 6.0], $mid($front), 0.25, $kind.' front');
            // the back is looked at from behind: what is on its right lies left of the middle in the preview's own millimetres
            $this->assertEqualsWithDelta([$face['centre'][0] - 12.0, $face['centre'][1] + 6.0], $mid($back), 0.25, $kind.' back');
            $this->assertGreaterThan($face['back_z'], $face['front_z'], $kind.': the preview shows the front up');
            $this->assertEqualsWithDelta(0.0, $face['back_z'], 0.01);
            // where decorations may lie: the plate less its margin, at least its outer ring; the corners of each inside it
            $this->assertNotEmpty($face['region']);
            $this->assertGreaterThanOrEqual(12, count($face['region'][0]));
            $this->assertLessThanOrEqual(16, count($front['view']['hull']));
            $this->assertGreaterThanOrEqual(3, count($front['view']['hull']));
            // the floor of the engraving on the back, a little above the back as the preview shows it (the page draws it darker);
            // what stands out has no floor (the medal's front lies on the bed: the text is sunk two layers into it, and in
            // the preview, turned over, its floor lies that far under the front)
            $kind === 'keychain' ? $this->assertArrayNotHasKey('floor', $front['view']) : $this->assertEqualsWithDelta($face['front_z'] - 0.4, $front['view']['floor'], 0.01);
            $this->assertEqualsWithDelta($kind === 'keychain' ? 0.4 : 0.6, $back['view']['floor'], 0.01, $kind.': two layers into the face on the bed, three into the one that is up');
        }
        // the key ring's eyelet is a hole in that region: a second ring
        $ring = $this->notes('keychain', self::PLAIN['keychain'] + ['eyelet' => true, 'decor' => $decor])['_notes']['decor_face']['region'];
        $this->assertCount(2, $ring);
    }

    public function test_a_relief_in_a_filament_of_its_own_changes_colour_by_height_and_a_sunk_one_needs_a_changer(): void
    {
        $this->engine();
        $cut = $this->photo(true);
        $raised = $this->notes('medallion', self::PLAIN['medallion'] + ['part_colors' => ['body' => 'yellow'], 'decor' => [$this->relief($cut, ['color' => 'white', 'background' => 'remove'])]])['_notes'];
        $this->assertSame(['body', 'decor_1'], $raised['parts']);
        $this->assertFalse($raised['multi_material']);
        $this->assertFalse($raised['one_plate_required']);
        $this->assertSame(2, $raised['filaments']);
        $this->assertCount(1, $raised['color_changes']);
        $this->assertEquals([4.4, 'decor_1', 'white'], [$raised['color_changes'][0]['z'], $raised['color_changes'][0]['part'], $raised['color_changes'][0]['code']]);
        $this->assertSame('decor_1', $raised['decor'][0]['part']);
        // sunk into the plate it shares the plate's layers: one plate on a printer that changes spools
        $sunk = $this->notes('medallion', self::PLAIN['medallion'] + ['part_colors' => ['body' => 'yellow'], 'decor' => [$this->relief($cut, ['color' => 'white', 'mode' => 'engraved'])]])['_notes'];
        $this->assertTrue($sunk['multi_material']);
        $this->assertTrue($sunk['one_plate_required']);
        // the plate's own filament is no colour of its own
        $plain = $this->notes('medallion', self::PLAIN['medallion'] + ['part_colors' => ['body' => 'yellow'], 'decor' => [$this->relief($cut, ['color' => 'yellow'])]])['_notes'];
        $this->assertSame(['body'], $plain['parts']);
        // the design is stored with the part and its colour, and the part comes out alone for the farm
        $uuid = $this->postJson('/api/tools/param', ['kind' => 'medallion', 'params' => self::PLAIN['medallion'] + ['part_colors' => ['body' => 'yellow'], 'decor' => [$this->relief($cut, ['color' => 'white', 'background' => 'remove'])]]])->assertCreated()->json('file.uuid');
        $kept = ModelFile::where('uuid', $uuid)->firstOrFail()->tool_params;
        $this->assertSame('remove', $kept['decor'][0]['background']);
        $this->assertSame('white', $kept['part_colors']['decor_1']['code']);
        $this->assertSame(4.4, (float) $kept['color_change_mm']);
        $this->get('/api/tools/param/'.$uuid.'/decor_1.stl')->assertOk();
    }

    public function test_every_text_is_written_in_the_first_step_and_the_panel_keeps_the_pictures(): void
    {
        foreach (['', '/en', '/es'] as $prefix) {
            foreach (['medallion', 'keychain', 'keychain?form=1', 'ornament', 'coaster'] as $tool) {
                $html = $this->get($prefix.'/tools/'.$tool)->assertOk()->getContent();
                // the place for the texts lies in the step "Input", before the step "Decoration"
                $input = strpos($html, 'id="sec-input"');
                $texts = strpos($html, 'id="decor-texts"');
                $panel = strpos($html, 'id="sec-decor"');
                $this->assertTrue($input !== false && $texts !== false && $panel !== false, $tool);
                $this->assertTrue($input < $texts && $texts < $panel, $prefix.' '.$tool.': the texts are listed in the first step');
                $this->assertStringNotContainsString('param.decor.', $html, $prefix.' '.$tool.': every text of the panel has its words');
                foreach (['decor.text.more', 'decor.background.keep', 'decor.background.working', 'decor.side.front', 'decor.side.back', 'decor.drag.hint', 'decor.print.plate', 'decor.print.swap', 'decor.print.many'] as $key) {
                    $this->assertStringContainsString($key.'\u0022:', $html, $tool.' '.$key);
                }
            }
        }
        $this->get('/tools/charm')->assertOk()->assertDontSee('id="decor-texts"', false);
        // the script: one list, two places; a new photo loses its background, a stored record without the word keeps it
        $script = (string) file_get_contents(resource_path('js/calc/decor.ts'));
        $this->assertStringContainsString("if (opts.texts) { place(opts.texts, ['text']); place(host, ['photo', 'logo']); } else place(host, ['text', 'photo', 'logo']);", $script);
        $this->assertStringContainsString("background: raw.background === 'remove' || raw.background === 'keep' ? raw.background : stored ? 'keep' : 'remove'", $script);
        $page = (string) file_get_contents(resource_path('js/calc/param.ts'));
        $this->assertStringContainsString("{ texts: document.getElementById('decor-texts'), onOpen: () => decorOpened() }", $page);
        $this->assertStringContainsString('(c, phase) => decor.drag(c, phase, face), (c) => decor.hold(c, face)', $page);
    }

    public function test_the_farm_prints_a_design_whose_coloured_decoration_stands_on_its_plate_on_one_plate(): void
    {
        $this->engine();
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        [$agent] = FarmAgent::issue('test agent');
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE, 'bed_clear' => true]);
        $white = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail();
        $redColor = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'like', 'PLA%'))->where('id', '!=', $white->farm_color_id)->firstOrFail();
        $redColor->update(['hex' => '#C01818', 'enabled' => true]);
        $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->firstOrFail()->update(['farm_color_id' => $redColor->id, 'remaining_g' => 800, 'enabled' => true]);

        // a key ring with a red text raised beside a text in the plate's own filament: two filaments in the layers above
        // the plate, and the red text is a part that starts 3 mm above the bed
        $params = self::PLAIN['keychain'] + ['part_colors' => ['body' => 'white'], 'decor' => [$this->text(['lines' => ['Roman'], 'x' => -18, 'color' => 'red']), $this->text(['lines' => ['2026'], 'x' => 24, 'size' => 25])]];
        $uuid = $this->actingAs($user)->postJson('/api/tools/param', ['kind' => 'keychain', 'params' => $params])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(['body', 'decor_1'], $file->tool_params['parts']);
        $this->assertTrue($file->onePlateRequired());

        $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid, 'color' => $white->farm_color_id, 'by_parts' => 1, 'part_color' => ['decor_1' => $redColor->id]])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.(string) $order->error_detail.' '.json_encode($order->timings));
        $this->assertTrue($order->onePlate());
        $this->assertSame(1, (int) $order->plates);
        $this->assertCount(2, $order->slice_result['filament_grams']);
        $this->assertGreaterThan(0, $order->price['changes']);
        $this->assertGreaterThan(0, (float) $order->price['total']);
        // the project: one object, the plate and the text its two parts, each with its filament (as objects of their own
        // the slicer would lay the text on the bed, into the plate)
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('farm')->path($order->dir().'/plate.3mf')) === true);
        $model = (string) $zip->getFromName('3D/3dmodel.model');
        $config = (string) $zip->getFromName('Metadata/model_settings.config');
        $zip->close();
        $this->assertSame(1, substr_count($model, '<item objectid="3"'));
        $this->assertSame(1, substr_count($model, '<item '));
        $this->assertStringContainsString('<components><component objectid="1"/><component objectid="2"/></components>', $model);
        $this->assertMatchesRegularExpression('/<part id="1" subtype="normal_part"><metadata key="name" value="body"\/><metadata key="extruder" value="1"\/>/', $config);
        $this->assertMatchesRegularExpression('/<part id="2" subtype="normal_part"><metadata key="name" value="decor_1"\/><metadata key="extruder" value="2"\/>/', $config);
    }
}
