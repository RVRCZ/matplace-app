<?php

namespace Tests\Feature;

use App\Domain\Tools\Artwork;
use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Decorations (docs/AR.md): a text, a relief from a photo or a logo on the front or the back of a medal, a key ring, an
 * ornament and a coaster. One list in the parameters, one panel on the page, one place in the engine (decor.py).
 */
class DecorTest extends TestCase
{
    use RefreshDatabase;

    /** product → what it is made from without any decoration: the plainest plate each tool has */
    private const PLAIN = [
        'medallion' => ['line1' => '', 'motif' => 'raised', 'body' => 'circle', 'width' => 60, 'links' => 6],
        'keychain' => ['line1' => '', 'body' => 'rect', 'width' => 60],
        'ornament' => ['line1' => '', 'body' => 'circle', 'width' => 80],
        'coaster' => ['line1' => '', 'body' => 'circle', 'width' => 100],
        'compose' => ['base' => 'eyelet', 'layers' => [['kind' => 'shape', 'shape' => 'rounded', 'x' => 0, 'y' => 0, 'w' => 70, 'code' => 'blue']]],
    ];

    private function engine(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    private function preview(string $kind, array $params, string $part = 'all')
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'part' => $part, 'pieces' => true]);
    }

    /** @return array<string, mixed> */
    private function notes(string $kind, array $params): array
    {
        $r = $this->preview($kind, $params)->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta')) + ['_notes' => PreviewMeta::whole($r->headers->get('X-Model-Meta'))['notes']];
    }

    /** A drawn head, light on dark, shaded to its edges: something a relief has heights to read from. No real person. */
    private function photo(): string
    {
        $img = imagecreatetruecolor(300, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 30, 34));
        for ($r = 120; $r > 0; $r -= 4) {
            $v = (int) (90 + (120 - $r) * 1.3);
            imagefilledellipse($img, 150, 150, 2 * $r, (int) (2.3 * $r), imagecolorallocate($img, $v, $v, $v));
        }
        $dark = imagecolorallocate($img, 25, 25, 28);
        imagefilledellipse($img, 115, 130, 26, 16, $dark);
        imagefilledellipse($img, 185, 130, 26, 16, $dark);
        imagefilledrectangle($img, 120, 200, 180, 208, $dark);
        $tmp = tempnam(sys_get_temp_dir(), 'mpdecor').'.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($tmp, 'head.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    private function text(array $over = []): array
    {
        return $over + ['kind' => 'text', 'face' => 'front', 'mode' => 'raised', 'lines' => ['Jana', '2026'], 'typeface' => 'archivo', 'size' => 50, 'depth' => 0.6];
    }

    private function logo(array $over = []): array
    {
        return $over + ['kind' => 'logo', 'face' => 'front', 'mode' => 'raised', 'image' => 'lib:colour/matplace', 'size' => 50, 'depth' => 0.6];
    }

    private function relief(string $ref, array $over = []): array
    {
        return $over + ['kind' => 'photo', 'face' => 'front', 'mode' => 'raised', 'image' => $ref, 'size' => 30, 'depth' => 1.2, 'frame' => 'circle'];
    }

    public function test_the_list_is_cleaned_into_its_limits(): void
    {
        $clean = ParametricGenerator::cleanDecor([
            ['kind' => 'text', 'lines' => ['  Jana  ', '', str_repeat('x', 80)], 'size' => 500, 'x' => -90, 'depth' => 9, 'mode' => 'sideways', 'face' => 'edge', 'color' => 'red', 'typeface' => 'no-such', 'rm' => '-rf'],
            ['kind' => 'text', 'lines' => ['', ' ']],                                   // nothing written yet: no decoration
            ['kind' => 'qr', 'lines' => ['x']],                                         // a kind of later
            ['kind' => 'photo', 'image' => 'lib:animals/cat', 'depth' => 0.1, 'color' => 'red', 'smooth' => 7],
            ['kind' => 'logo', 'image' => '../../etc/passwd'],                          // no reference of a picture
            ['kind' => 'logo', 'image' => 'lib:animals/cat', 'depth' => 0.5, 'color' => ['code' => 'blue', 'hex' => '#123456']],
            'nonsense',
        ]);
        $this->assertCount(3, $clean);
        [$text, $photo, $logo] = $clean;
        $this->assertSame(['Jana', str_repeat('x', ParametricGenerator::DECOR_LINE)], $text['lines']);
        $this->assertSame([100.0, -50.0, 1.2, 'raised', 'front', 'archivo'], [$text['size'], $text['x'], $text['depth'], $text['mode'], $text['face'], $text['typeface']]);
        $this->assertSame('red', $text['color']['code']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $text['color']['hex']);
        $this->assertArrayNotHasKey('rm', $text);
        // (round 2, docs/AR.md §12: a relief may have a filament of its own; a record without a word about the background keeps it)
        $this->assertSame([0.6, 'red', 1.0, 'circle', 'keep'], [$photo['depth'], $photo['color']['code'], $photo['smooth'], $photo['frame'], $photo['background']]);
        $this->assertSame([0.6, 'blue'], [$logo['depth'], $logo['color']['code']]);                                               // whole layers of 0.2
        // what is stored is cleaned the same again: a design opened from its stored parameters is the same design
        $this->assertSame($clean, ParametricGenerator::cleanDecor($clean));
        $this->assertCount(ParametricGenerator::MAX_DECOR, ParametricGenerator::cleanDecor(array_fill(0, 9, ['kind' => 'text', 'lines' => ['a']])));
        // kinds that take no decorations do not carry the list
        $this->assertArrayNotHasKey('decor', ParametricGenerator::clean('charm', ['decor' => [$this->text()]]));
        $this->assertArrayHasKey('decor', ParametricGenerator::clean('coaster', ['decor' => [$this->text()]]));
    }

    public function test_a_decoration_outside_its_limits_is_refused_in_the_visitors_language(): void
    {
        foreach ([['size' => 150], ['x' => 80], ['depth' => 5], ['kind' => 'hologram'], ['mode' => 'glued'], ['face' => 'edge'], ['lines' => [str_repeat('x', 31)]], ['typeface' => 'wingdings']] as $bad) {
            $this->preview('ornament', self::PLAIN['ornament'] + ['decor' => [$bad + $this->text()]])
                ->assertStatus(422)->assertJsonFragment([__('param.error.decor_bad')]);
        }
        $this->preview('ornament', self::PLAIN['ornament'] + ['decor' => array_fill(0, 7, $this->text())])->assertStatus(422);
    }

    public function test_each_product_takes_each_kind_of_decoration(): void
    {
        $this->engine();
        $photo = $this->photo();
        foreach (self::PLAIN as $kind => $plain) {
            $bare = $kind === 'compose' ? $this->notes($kind, $plain) : null;
            foreach (['text' => $this->text(), 'logo' => $this->logo(), 'photo' => $this->relief($photo)] as $what => $item) {
                $made = $this->notes($kind, $plain + ['decor' => [$item]]);
                $laid = $made['_notes']['decor'];
                $this->assertCount(1, $laid, "$kind $what");
                $this->assertSame($what, $laid[0]['kind'], "$kind $what");
                $this->assertFalse($laid[0]['on_bed'], "$kind $what");
                // it stands on the plate: as high as asked, inside the plate's outline
                $this->assertEqualsWithDelta($item['depth'], $laid[0]['z'][1] - $laid[0]['z'][0], 0.01, "$kind $what");
                $this->assertEqualsWithDelta($made['_notes']['outer'][2], $laid[0]['z'][1], 0.11, "$kind $what");
                $this->assertSame([], array_values(array_intersect($made['_notes']['warnings'], ['decor_clipped', 'decor_text_small'])), "$kind $what");
                if ($bare) {
                    $this->assertGreaterThan($bare['volume_mm3'], $made['volume_mm3']);
                }
            }
        }
    }

    public function test_a_medal_carries_a_relief_in_front_and_a_name_with_the_year_at_the_back(): void
    {
        $this->engine();
        $bare = ['line1' => '', 'body' => 'circle', 'width' => 60, 'thickness' => 3, 'links' => 6, 'hang' => 'chain'];
        $front = $this->notes('medallion', $bare + ['decor' => [$this->relief($this->photo(), ['size' => 70])]]);
        $both = $this->notes('medallion', $bare + ['decor' => [$this->relief($this->photo(), ['size' => 70]), $this->text(['face' => 'back', 'mode' => 'engraved', 'x' => 20])]]);
        $n = $both['_notes'];
        // a medal with decorations only and nothing said about its motif is face up by itself (docs/AR.md §9, question 1);
        // one whose motif is flush (the page's own choice, a stored design) prints face down: there the relief turns it face up
        $this->assertArrayNotHasKey('turned_up', $n);
        $this->assertTrue($this->notes('medallion', ['motif' => 'flush'] + $bare + ['decor' => [$this->relief($this->photo(), ['size' => 70])]])['_notes']['turned_up']);
        $this->assertSame(['body'], $n['parts']);
        $this->assertFalse($n['multi_material']);
        $this->assertSame([], $n['color_changes']);
        [$relief, $name] = $n['decor'];
        $this->assertEquals([3.0, 4.2], $relief['z']);
        $this->assertEqualsWithDelta(42.0, $relief['box'][2] - $relief['box'][0], 0.3);          // 70 % of 60 mm
        // the back lies on the bed: the name is sunk two layers into it, mirrored so it reads right when the medal is turned
        $this->assertTrue($name['on_bed']);
        $this->assertSame('engraved', $name['mode']);
        $this->assertEquals([0.0, 0.4], $name['z']);              // two layers: a third would call for supports
        $middle = ($relief['box'][0] + $relief['box'][2]) / 2;
        $this->assertLessThan($middle - 5, ($name['box'][0] + $name['box'][2]) / 2);              // 20 % to the right as it is read = to the left as it is printed
        $this->assertLessThan($front['volume_mm3'], $both['volume_mm3']);
        $this->assertGreaterThan(3.0, $name['cap']);
        // every size of a medal: the relief is made and stays one body with the medal
        foreach ([40, 120] as $width) {
            $made = $this->notes('medallion', ['width' => $width] + $bare + ['decor' => [$this->relief($this->photo(), ['size' => 80])]]);
            $this->assertSame(['body'], $made['_notes']['parts']);
            $this->assertEqualsWithDelta(0.8 * $width, $made['_notes']['decor'][0]['box'][2] - $made['_notes']['decor'][0]['box'][0], 0.5);
        }
    }

    public function test_the_face_on_the_bed_takes_nothing_raised(): void
    {
        $this->engine();
        // the medal as it opens: a flush "1", printed face down. A text asked to stand out of its front is sunk into it…
        $sunk = $this->notes('medallion', ['line1' => '1', 'links' => 6, 'decor' => [$this->text(['lines' => ['VITEZ'], 'y' => -32, 'size' => 40])]])['_notes'];
        $this->assertSame(['engraved', true], [$sunk['decor'][0]['mode'], $sunk['decor'][0]['on_bed']]);
        $this->assertContains('decor_bed_face', $sunk['warnings']);
        $this->assertSame(['body', 'color_1'], $sunk['parts']);
        // …or inlaid level with it when it has a filament of its own: a part beside the plate, one plate on the farm
        $inlaid = $this->notes('medallion', ['line1' => '1', 'links' => 6, 'decor' => [$this->text(['lines' => ['VITEZ'], 'y' => -32, 'size' => 40, 'color' => 'blue'])]])['_notes'];
        $this->assertSame(['flush', 'decor_1'], [$inlaid['decor'][0]['mode'], $inlaid['decor'][0]['part']]);
        $this->assertEquals([0.0, 0.6], $inlaid['decor'][0]['z']);
        $this->assertSame(['body', 'color_1', 'decor_1'], $inlaid['parts']);
        $this->assertTrue($inlaid['multi_material']);
        $this->assertTrue($inlaid['one_plate_required']);
        $this->assertSame(3, $inlaid['filaments']);
        // its back is up: a name stands out of it
        $back = $this->notes('medallion', ['line1' => '1', 'links' => 6, 'decor' => [$this->text(['face' => 'back'])]])['_notes'];
        $this->assertSame(['raised', false], [$back['decor'][0]['mode'], $back['decor'][0]['on_bed']]);
        // a relief cannot be made on the bed: the back of a key ring printed face up
        $this->preview('keychain', ['line1' => 'Jana', 'decor' => [$this->relief($this->photo(), ['face' => 'back'])]])
            ->assertStatus(422)->assertJsonFragment([__('param.error.decor_photo_bed')]);
        // too deep for the plate, off the plate: said, not built
        $this->preview('coaster', self::PLAIN['coaster'] + ['thickness' => 3, 'decor' => [$this->relief($this->photo(), ['mode' => 'engraved', 'depth' => 2.6])]])
            ->assertStatus(422)->assertJsonFragment([__('param.error.decor_too_deep', ['n' => 1])]);
    }

    /** docs/AR.md §9, question 1: a medal with decorations only prints face up unless somebody chose the motif's mode. */
    public function test_a_medal_with_decorations_only_prints_face_up_by_default_and_a_stored_one_keeps_its_mode(): void
    {
        $bare = ['line1' => '', 'line2' => '', 'body' => 'circle', 'width' => 60, 'links' => 6, 'decor' => [$this->text()]];

        // what is stored: nothing said about the mode → raised; a mode that was said (a stored design always carries it) stays
        $this->assertSame('raised', ParametricGenerator::clean('medallion', $bare)['motif']);
        $this->assertSame('flush', ParametricGenerator::clean('medallion', $bare + ['motif' => 'flush'])['motif'], 'a stored design keeps what it has');
        $this->assertSame('raised', ParametricGenerator::clean('medallion', $bare + ['motif' => 'raised'])['motif']);
        // a medal with a number or a motif of its own opens as always, with or without decorations; so does one without any decoration
        $this->assertSame('flush', ParametricGenerator::clean('medallion', ['line1' => '1'] + $bare)['motif']);
        $this->assertSame('flush', ParametricGenerator::clean('medallion', ['decor' => [$this->text()]])['motif'], 'the default first place');
        $this->assertSame('flush', ParametricGenerator::clean('medallion', ['artwork' => 'lib:colour/matplace'] + $bare)['motif']);
        $this->assertSame('flush', ParametricGenerator::clean('medallion', ['line1' => '', 'line2' => ''])['motif']);
        $this->assertSame('flush', ParametricGenerator::clean('medallion', ['line1' => '', 'line2' => '', 'decor' => [['kind' => 'text', 'lines' => ['']]]])['motif'], 'a decoration that shows nothing yet is none');

        // the page's script does the same before it asks for a preview (the form always sends a mode)
        $script = (string) file_get_contents(resource_path('js/calc/param.ts'));
        $this->assertStringContainsString("cfg.kind === 'medallion' && !motifChosen && p.motif === 'flush' && p.decor && !artwork", $script);
        $this->assertStringContainsString("if (cfg.kind === 'medallion' && el.dataset.choice === 'motif' && e.isTrusted) motifChosen = true;", $script);

        $this->engine();
        // built: the text on the front stands raised, the front is up, nothing is turned into an engraving
        $up = $this->notes('medallion', $bare)['_notes'];
        $this->assertSame(['raised', false], [$up['decor'][0]['mode'], $up['decor'][0]['on_bed']]);
        $this->assertNotContains('decor_bed_face', $up['warnings'] ?? []);
        // the same medal as a design stored face down: built as it was, the text sunk into the face on the bed
        $down = $this->notes('medallion', $bare + ['motif' => 'flush'])['_notes'];
        $this->assertSame(['engraved', true], [$down['decor'][0]['mode'], $down['decor'][0]['on_bed']]);
        $this->assertContains('decor_bed_face', $down['warnings']);
    }

    public function test_a_key_ring_carries_a_logo_and_a_text_in_a_filament_of_its_own(): void
    {
        $this->engine();
        $decor = [$this->logo(['size' => 35, 'x' => -24, 'color' => 'red']), $this->text(['lines' => ['Roman'], 'x' => 17, 'size' => 40, 'color' => 'red'])];
        $params = self::PLAIN['keychain'] + ['eyelet' => true, 'part_colors' => ['body' => 'white'], 'decor' => $decor];
        $n = $this->notes('keychain', $params)['_notes'];
        // both in one filament of their own: two parts on the plate, one change of filament at the plate's top
        $this->assertSame(['body', 'decor_1', 'decor_2'], $n['parts']);
        $this->assertFalse($n['multi_material']);
        $this->assertCount(1, $n['color_changes']);
        $this->assertEquals([3.0, 'red'], [$n['color_changes'][0]['z'], $n['color_changes'][0]['code']]);
        $this->assertSame(2, $n['filaments']);
        $this->assertLessThan($n['decor'][1]['box'][0], $n['decor'][0]['box'][2]);              // side by side, not over each other
        // the composer that /tools/keychain opens takes the same list
        $composed = $this->notes('compose', self::PLAIN['compose'] + ['decor' => $decor])['_notes'];
        $this->assertSame(['layer_1', 'decor_1', 'decor_2'], $composed['parts']);
        $this->assertCount(1, $composed['color_changes']);

        $file = ModelFile::where('uuid', $this->postJson('/api/tools/param', ['kind' => 'keychain', 'params' => $params])->assertCreated()->json('file.uuid'))->firstOrFail();
        $kept = $file->tool_params;
        $this->assertSame(['body', 'decor_1', 'decor_2'], $kept['parts']);
        $this->assertSame('red', $kept['part_colors']['decor_2']['code']);
        $this->assertSame(3.0, (float) $kept['color_change_mm']);
        $this->assertFalse($file->onePlateRequired());
        $this->assertSame(['body', 'decor_1', 'decor_2'], ParametricGenerator::partsOf('keychain', $kept));
        // the logo in the plate's filament beside the red name: two filaments in the same layers, so one plate on a printer
        // that changes spools by itself, never part by part
        $decor[0]['color'] = null;
        $mixed = ModelFile::where('uuid', $this->postJson('/api/tools/param', ['kind' => 'keychain', 'params' => ['decor' => $decor] + $params])->assertCreated()->json('file.uuid'))->firstOrFail();
        $this->assertSame(['body', 'decor_2'], $mixed->tool_params['parts']);
        $this->assertTrue($mixed->tool_params['multi_material']);
        $this->assertTrue($mixed->onePlateRequired());
        // the part alone, as the farm and the download ask for it
        $this->get('/api/tools/param/'.$file->uuid.'/decor_2.stl')->assertOk();
    }

    public function test_a_design_is_stored_and_opened_again_with_its_decorations(): void
    {
        $this->engine();
        $photo = $this->photo();
        $params = self::PLAIN['ornament'] + ['eyelet' => true, 'decor' => [$this->relief($photo, ['size' => 70, 'contrast' => 120]), $this->logo(['face' => 'back', 'mode' => 'engraved', 'size' => 40])]];
        $uuid = $this->postJson('/api/tools/param', ['kind' => 'ornament', 'params' => $params])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $kept = $file->tool_params['decor'];
        $this->assertCount(2, $kept);
        // the visitor's photo is kept with the design, a picture of the library stays a reference
        $this->assertSame('file:'.$uuid.':'.ParametricGenerator::DECOR_FILE, $kept[0]['image']);
        $this->assertFileExists((string) Artwork::path($kept[0]['image']));
        $this->assertSame('lib:colour/matplace', $kept[1]['image']);
        $this->assertEquals([120.0, 'engraved', 'back'], [$kept[0]['contrast'], $kept[1]['mode'], $kept[1]['face']]);
        // the page reads it back and the same design is built from it again
        $read = $this->getJson('/api/files/'.$uuid)->assertOk()->json('file.tool.params.decor');
        $this->assertEquals($kept, $read);
        $again = $this->notes('ornament', $file->tool_params)['_notes'];
        $this->assertSame(['photo', 'logo'], array_column($again['decor'], 'kind'));
        $this->assertEquals($kept, ParametricGenerator::clean('ornament', $file->tool_params)['decor']);
    }

    public function test_designs_from_before_decorations_are_built_as_they_were(): void
    {
        $this->engine();
        $old = [
            'medallion' => ['line1' => '1', 'typeface' => 'archivo', 'links' => 6],
            'keychain' => ['line1' => 'Jana'],
            'ornament' => ['artwork' => 'lib:colour/gingerbread-man'],
            'coaster' => ['artwork' => 'lib:sport/golfer', 'body' => 'circle', 'flush' => true, 'rim' => true, 'grooves' => true],
        ];
        foreach ($old as $kind => $params) {
            $before = $this->notes($kind, $params);
            $this->assertArrayNotHasKey('decor', $before['_notes'], $kind);
            $this->assertArrayNotHasKey('decor', ParametricGenerator::clean($kind, $params), $kind);
            // an emptied list is no list: the same solid to the last cubic millimetre, the same parts and the same way to print
            $empty = $this->notes($kind, $params + ['decor' => []]);
            $this->assertSame($before['volume_mm3'], $empty['volume_mm3'], $kind);
            $this->assertSame($before['_notes']['parts'], $empty['_notes']['parts'], $kind);
            foreach (['multi_material', 'one_plate_required', 'color_changes', 'filaments'] as $key) {
                $this->assertSame($before['_notes'][$key], $empty['_notes'][$key], "$kind $key");
            }
        }
        // a tool without a name and without a decoration still says what is missing
        $this->preview('keychain', ['line1' => ''])->assertStatus(422)->assertJsonFragment([__('param.error.no_text')]);
        // a decoration beside the tool's own motif keeps the motif: the coaster's golfer flush in its face, a name sunk into its back
        $golf = $this->notes('coaster', $old['coaster'] + ['decor' => [$this->text(['face' => 'back', 'lines' => ['Golf klub']])]])['_notes'];
        $this->assertContains('color_1', $golf['parts']);
        $this->assertFalse($golf['decor'][0]['on_bed']);                    // flush colours print face down: the back is up
    }

    public function test_the_four_tools_show_the_same_panel(): void
    {
        foreach (['cs' => ['', 'Zdobení'], 'en' => ['/en', 'Decoration'], 'es' => ['/es', 'Decoración']] as [$prefix, $title]) {
            foreach (['medallion', 'keychain', 'ornament', 'coaster'] as $tool) {
                $page = $this->get($prefix.'/tools/'.$tool)->assertOk();
                $page->assertSee('id="decor-panel"', false)->assertSee('id="sec-decor"', false)->assertSee($title);
                $this->assertStringNotContainsString('param.decor.', $page->getContent(), $tool);
            }
        }
        // the quick form of the key ring too; tools outside the four keep their pages
        $this->get('/tools/keychain?form=1')->assertOk()->assertSee('id="decor-panel"', false);
        foreach (['charm', 'nameplate', 'magnet', 'compose'] as $tool) {
            $this->get('/tools/'.$tool)->assertOk()->assertDontSee('id="decor-panel"', false);
        }
    }
}
