<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use App\Models\User;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A flat plate with its colours flush prints face down like the medal: the colours in the first layers beside the plate, the
 * face smooth from the bed, what belongs to the back on top, the preview turned over (docs/P.md §11).
 */
class FlushDownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    /** A picture that is not the same both ways: a big red block on the left, a small black one on the right, on blue. */
    private function picture(): string
    {
        $img = imagecreatetruecolor(400, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 170, 230));
        imagefilledrectangle($img, 40, 40, 220, 260, imagecolorallocate($img, 200, 30, 30));
        imagefilledrectangle($img, 220, 120, 360, 180, imagecolorallocate($img, 25, 25, 28));
        $tmp = tempnam(sys_get_temp_dir(), 'mpdown').'.png';
        imagepng($img, $tmp);
        imagedestroy($img);

        return (string) $this->post('/api/tools/artwork', ['file' => new UploadedFile($tmp, 'blocks.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
    }

    /** @return array{meta: array<string, mixed>, triangles: list<array>, pieces: array<string, list<array>>} */
    private function built(string $kind, array $params, string $view = 'print'): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'view' => $view, 'pieces' => true])->assertOk();
        $meta = PreviewMeta::whole($r->headers->get('X-Model-Meta'));
        $triangles = iterator_to_array(StlFile::triangles($r->baseResponse->getFile()->getPathname()), false);
        $pieces = [];
        foreach ($meta['parts'] ?? [] as $piece) {
            $pieces[$piece['name']] = array_slice($triangles, $piece['tris'][0], $piece['tris'][1] - $piece['tris'][0]);
        }

        return ['meta' => $meta, 'triangles' => $triangles, 'pieces' => $pieces];
    }

    /** @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}> */
    private function boxes(array $meta): array
    {
        return array_column($meta['parts'], 'bbox', 'name');
    }

    private function volume(string $kind, array $params, string $part = 'all'): float
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'part' => $part])->assertOk();

        return (float) PreviewMeta::whole($r->headers->get('X-Model-Meta'))['volume_mm3'];
    }

    /** Where the surface of triangles lies from left to right, weighted by its size. */
    private function middleX(array $triangles): float
    {
        [$sum, $weight] = [0.0, 0.0];
        foreach ($triangles as [$a, $b, $c]) {
            $u = [$b[0] - $a[0], $b[1] - $a[1], $b[2] - $a[2]];
            $v = [$c[0] - $a[0], $c[1] - $a[1], $c[2] - $a[2]];
            $area = sqrt(($u[1] * $v[2] - $u[2] * $v[1]) ** 2 + ($u[2] * $v[0] - $u[0] * $v[2]) ** 2 + ($u[0] * $v[1] - $u[1] * $v[0]) ** 2) / 2;
            $sum += $area * ($a[0] + $b[0] + $c[0]) / 3;
            $weight += $area;
        }

        return $sum / max($weight, 1e-9);
    }

    /** The part of the red block and of the black one. */
    private function redAndBlack(array $meta): array
    {
        $colors = collect($meta['notes']['colors'])->sortByDesc(fn ($c) => hexdec(substr($c['hex'], 1, 2)))->values();

        return [$colors[0]['part'], $colors[count($colors) - 1]['part']];
    }

    /** @return list<float> the heights where the model has corners */
    private function heights(array $triangles): array
    {
        $z = [];
        foreach ($triangles as $t) {
            foreach ($t as $p) {
                $z[(string) round($p[2], 2)] = (float) round($p[2], 2);
            }
        }

        return array_values($z);
    }

    /** What the farm makes of one part: the check every part of a plate has to pass. */
    private function farm(string $kind, array $params, string $part): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'part' => $part])->assertOk();
        $out = tempnam(sys_get_temp_dir(), 'down');
        $farm = app(PythonTool::class)->runScript('farm_tool.py', ['prepare', $r->baseResponse->getFile()->getPathname(), $out, '1', '250', '250', '250'], 120);
        @unlink($out);

        return $farm;
    }

    public function test_a_flush_charm_lies_on_its_face_and_the_preview_shows_it_turned_over(): void
    {
        $art = $this->picture();
        $params = ['artwork' => $art, 'body' => 'image', 'width' => 50, 'thickness' => 3, 'frame' => 1.5, 'eyelet' => true, 'eye_pos' => 30, 'eye_hole' => 3, 'flush' => true];
        $print = $this->built('charm', $params);
        $notes = $print['meta']['notes'];
        $this->assertTrue($notes['one_plate_required']);
        $this->assertTrue($notes['multi_material']);
        $this->assertSame([], $notes['color_changes']);
        $this->assertEqualsWithDelta(3, $print['meta']['bbox']['z'], 0.01);

        // the colours are the first three layers, the plate goes on above them
        $boxes = $this->boxes($print['meta']);
        foreach ($notes['colors'] as $color) {
            $this->assertEqualsWithDelta(0, $boxes[$color['part']][2], 0.001, $color['part']);
            $this->assertEqualsWithDelta(0.6, $boxes[$color['part']][5], 0.001, $color['part']);
        }
        $this->assertEqualsWithDelta(3, $boxes['body'][5], 0.001);

        // the plate has its pocket: plate and colours together are the whole model, nothing lies twice
        $sum = array_sum(array_map(fn ($part) => $this->volume('charm', $params, $part), $notes['parts']));
        $this->assertEqualsWithDelta($print['meta']['volume_mm3'], $sum, $sum * 0.005);

        // mirrored for the bed, the right way round in the hand: the red block is on the left as in the picture
        [$red, $black] = $this->redAndBlack($print['meta']);
        $this->assertGreaterThan($this->middleX($print['pieces'][$black]), $this->middleX($print['pieces'][$red]), 'printed: mirrored');
        $use = $this->built('charm', $params, 'use');
        $this->assertLessThan($this->middleX($use['pieces'][$black]), $this->middleX($use['pieces'][$red]), 'in the preview: as drawn');
        $this->assertEqualsWithDelta($print['meta']['volume_mm3'], $use['meta']['volume_mm3'], 0.5);
        foreach (['x', 'y', 'z'] as $axis) {
            $this->assertEqualsWithDelta($print['meta']['bbox'][$axis], $use['meta']['bbox'][$axis], 0.01, $axis);
        }

        // the grip of the eyelet sits where the turned piece has it: the same place as on the piece that prints face up
        $up = $this->built('charm', ['flush' => false] + $params, 'use')['meta']['notes'];
        $this->assertEqualsWithDelta($up['eyelet']['x'], $notes['eyelet']['x'], 0.1);
        $this->assertEqualsWithDelta($up['eyelet']['y'], $notes['eyelet']['y'], 0.1);
        $this->assertEqualsWithDelta(3, $notes['eyelet']['z'], 0.01);
        $this->assertSame(array_map(fn ($p) => array_map(fn ($c) => round($c), $p), $up['outline']), array_map(fn ($p) => array_map(fn ($c) => round($c), $p), $notes['outline']));
        // … and the hole is there in the preview, on the other side in the print
        $width = $print['meta']['bbox']['x'];
        $this->assertGreaterThan(3, abs($notes['eyelet']['x'] - $width / 2), 'the eyelet is off the middle, or this test proves nothing');
        $this->assertGreaterThan(20, $this->onCircle($use['triangles'], $notes['eyelet']['x'], $notes['eyelet']['y'], 1.5));
        $this->assertGreaterThan(20, $this->onCircle($print['triangles'], $width - $notes['eyelet']['x'], $notes['eyelet']['y'], 1.5));
        $this->assertLessThan(5, $this->onCircle($print['triangles'], $notes['eyelet']['x'], $notes['eyelet']['y'], 1.5), 'the print is mirrored');
    }

    public function test_the_parts_to_download_lie_as_they_print(): void
    {
        $params = ['artwork' => 'lib:colour/matplace', 'body' => 'image', 'width' => 55, 'thickness' => 3, 'frame' => 1.7, 'flush' => true, 'rim' => true];
        $r = $this->post('/api/tools/param/zip', ['kind' => 'charm', 'params' => $params])->assertOk();
        $dir = sys_get_temp_dir().'/mp_down_'.uniqid();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($r->baseResponse->getFile()->getPathname()) === true);
        $zip->extractTo($dir);
        $zip->close();
        // the rim, the colours and the plate in one frame, on the bed: put together in a slicer they are the charm
        [$bodyLo, $bodyHi] = StlFile::bounds($dir.'/charm-body.stl');
        [$rimLo, $rimHi] = StlFile::bounds($dir.'/charm-rim.stl');
        [$colorLo, $colorHi] = StlFile::bounds($dir.'/charm-color_1.stl');
        [$allLo, $allHi] = StlFile::bounds($dir.'/charm.stl');
        $this->assertEqualsWithDelta(3, $bodyHi[2], 0.001);
        $this->assertEqualsWithDelta(0.6, $rimHi[2], 0.001);
        $this->assertEqualsWithDelta(0.6, $colorHi[2], 0.001);
        $this->assertEqualsWithDelta(0, $colorLo[2], 0.001);
        $this->assertEqualsWithDelta($allHi[0] - $allLo[0], $rimHi[0] - $rimLo[0], 1.0, 'the rim goes round the whole charm');
        $this->assertGreaterThan($bodyLo[0] + 1, $colorLo[0], 'the colour inside the rim, not in its own corner');
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }

    /** How many corners of the model lie on a circle: the wall of a hole. */
    private function onCircle(array $triangles, float $x, float $y, float $r): int
    {
        $seen = [];
        foreach ($triangles as $t) {
            foreach ($t as $p) {
                if (abs(hypot($p[0] - $x, $p[1] - $y) - $r) < 0.03) {
                    $seen[round($p[0], 2).' '.round($p[1], 2)] = true;
                }
            }
        }

        return count($seen);
    }

    /** How deep the model reaches into a hole: 0 when it is free. */
    private function intoHole(array $triangles, float $x, float $y, float $r): float
    {
        $deepest = 0.0;
        foreach ($triangles as $t) {
            $mid = [($t[0][0] + $t[1][0] + $t[2][0]) / 3, ($t[0][1] + $t[1][1] + $t[2][1]) / 3];
            foreach ([$t[0], $t[1], $t[2], $mid] as $p) {
                $deepest = max($deepest, $r - hypot($p[0] - $x, $p[1] - $y));
            }
        }

        return $deepest;
    }

    public function test_the_rim_is_a_band_level_with_the_face_and_a_bevel_adds_nothing(): void
    {
        $params = ['artwork' => 'lib:colour/matplace', 'body' => 'image', 'width' => 55, 'thickness' => 3, 'frame' => 1.7, 'eyelet' => true, 'eye_pos' => 42, 'eye_hole' => 4.5, 'flush' => true, 'rim' => true];
        $print = $this->built('charm', $params);
        $boxes = $this->boxes($print['meta']);
        $this->assertEqualsWithDelta(0, $boxes['rim'][2], 0.001);
        $this->assertEqualsWithDelta(0.6, $boxes['rim'][5], 0.001);
        $this->assertEqualsWithDelta(3, $print['meta']['bbox']['z'], 0.01, 'nothing stands over the back');
        $this->assertEqualsWithDelta(3, $print['meta']['notes']['outer'][2], 0.01);
        $this->assertTrue($print['meta']['notes']['one_plate_required']);

        $sum = array_sum(array_map(fn ($part) => $this->volume('charm', $params, $part), $print['meta']['notes']['parts']));
        $this->assertEqualsWithDelta($print['meta']['volume_mm3'], $sum, $sum * 0.005);

        // a saved design may have both: the bevel changes nothing, on the bed there is no edge to chamfer
        $this->assertEqualsWithDelta($print['meta']['volume_mm3'], $this->volume('charm', ['bevel' => true] + $params), 0.01);
    }

    /**
     * Cut along the wall of the plate, a rim left a sliver on every facet of the outline, and hairlines between the colours
     * and the band left walls of no thickness: "open edges, 91 bodies", no print (the logo as a charm, 1 Nov 2026). The farm
     * meets each part alone, and the body has to be closed before anyone repairs it.
     */
    public function test_the_plate_under_flush_colours_is_one_closed_body_a_printer_takes(): void
    {
        $logo = ['artwork' => 'lib:colour/matplace', 'body' => 'image', 'thickness' => 3];
        foreach ([
            ['charm', $logo + ['width' => 55, 'frame' => 1.7, 'eyelet' => true, 'eye_pos' => 42, 'eye_hole' => 4.5, 'rim' => true]],
            ['badge', $logo + ['width' => 50, 'rim' => true]],
            ['magnet', ['artwork' => 'lib:sport/golfer', 'body' => 'image', 'thickness' => 3, 'width' => 60]],
        ] as [$kind, $params]) {
            $params['flush'] = true;
            $body = $this->farm($kind, $params, 'body');
            $this->assertTrue($body['was_watertight'], $kind.' '.json_encode($body));
            $this->assertSame(1, $body['shells'], $kind);
            foreach ($this->built($kind, $params)['meta']['notes']['parts'] as $part) {
                $this->assertTrue($this->farm($kind, $params, $part)['watertight'], "{$kind} {$part}");
            }
        }
    }

    public function test_what_belongs_to_the_back_moves_on_top(): void
    {
        $art = $this->picture();
        // the grooves of a coaster: 0.4 mm into the top of the print, the rim down in the face
        $golf = $this->built('coaster', ['artwork' => 'lib:sport/golfer', 'body' => 'circle', 'width' => 100, 'thickness' => 4, 'frame' => 2, 'colors_n' => 4, 'remove_bg' => true, 'grooves' => true, 'flush' => true, 'rim' => true]);
        $z = $this->heights($golf['triangles']);
        $this->assertContains(3.6, $z);
        $this->assertNotContains(0.4, $z);
        $this->assertEqualsWithDelta(0.6, $this->boxes($golf['meta'])['rim'][5], 0.001);
        $this->assertSame([], $golf['meta']['notes']['warnings']);
        $this->assertContains(0.4, $this->heights($this->built('coaster', ['artwork' => 'lib:sport/golfer', 'body' => 'circle', 'width' => 100, 'thickness' => 4, 'frame' => 2, 'grooves' => true])['triangles']), 'face up they stay below');

        // the pocket of a magnet opens upwards: 2.2 mm deep in a plate of 3 mm
        $magnet = ['artwork' => $art, 'width' => 60, 'thickness' => 3, 'mount' => 'glue', 'mag_d' => 10, 'mag_h' => 2, 'flush' => true];
        $z = $this->heights($this->built('magnet', $magnet)['triangles']);
        $this->assertContains(0.8, $z);
        $this->assertNotContains(2.2, $z);
        $this->assertContains(2.2, $this->heights($this->built('magnet', ['flush' => false] + $magnet)['triangles']), 'face up it opens downwards');
        // the thinnest plate a magnet allows: the pocket reaches the colours, and the plate is still one closed body
        $thin = ['mag_h' => 2.2] + $magnet;
        $this->assertContains(0.6, $this->heights($this->built('magnet', $thin)['triangles']));
        $this->assertTrue($this->farm('magnet', $thin, 'body')['watertight']);
        // through stays through, and still shows on the face
        $through = $this->built('magnet', ['mount' => 'through'] + $magnet);
        $this->assertContains('magnet_shows', $through['meta']['notes']['warnings']);
        $hole = $through['meta']['notes']['magnet'];
        $this->assertLessThan(0.1, $this->intoHole($through['triangles'], $hole['x'], $hole['y'], 5.0));

        // a badge: the sticky dot goes on the back
        $badge = $this->built('badge', ['artwork' => $art, 'width' => 50, 'thickness' => 3, 'mount' => 'glue', 'mag_d' => 20, 'mag_h' => 1, 'flush' => true]);
        $this->assertContains(2.0, $this->heights($badge['triangles']));
        $this->assertNotContains(1.0, $this->heights($badge['triangles']));

        // a bag charm: the pocket on top, 3 mm deep; turned over in the preview the pin stays as it lies, right of the charm
        $bag = ['artwork' => $art, 'width' => 50, 'thickness' => 4, 'bag_hole' => 12, 'bag_wall' => 4, 'flush' => true];
        $print = $this->built('bag_charm', $bag);
        $this->assertContains(1.0, $this->heights($print['triangles']));
        $this->assertNotContains(3.0, $this->heights(array_filter($print['pieces']['body'], fn ($t) => $t[0][0] < 40 && $t[1][0] < 40 && $t[2][0] < 40)));
        $use = $this->built('bag_charm', $bag, 'use');
        $pin = $print['meta']['notes']['pin'];
        $this->assertEqualsWithDelta($pin['height'], $use['meta']['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta($print['meta']['volume_mm3'], $use['meta']['volume_mm3'], 0.5);
        $body = $this->boxes($use['meta'])['body'];
        $this->assertEqualsWithDelta(0, $body[2], 0.001);
        // the head of the pin is at the far right, on the same floor as the charm
        $farRight = array_filter($use['triangles'], fn ($t) => min($t[0][0], $t[1][0], $t[2][0]) > $use['meta']['bbox']['x'] - 5);
        $this->assertEqualsWithDelta(0, min(array_map(fn ($t) => min($t[0][2], $t[1][2], $t[2][2]), $farRight)), 0.001);
        $this->assertEqualsWithDelta($pin['height'], max(array_map(fn ($t) => max($t[0][2], $t[1][2], $t[2][2]), $use['triangles'])), 0.01);
    }

    public function test_a_pair_of_earrings_reads_right_on_both_after_it_is_turned_over(): void
    {
        $art = $this->picture();
        $params = ['artwork' => $art, 'body' => 'image', 'width' => 30, 'thickness' => 3, 'frame' => 1, 'eyelet' => true, 'eye_pos' => 30, 'flush' => true];
        $sides = function (array $built, string $red, string $black): array {
            $span = (float) $built['meta']['notes']['each'][0];
            $half = fn (array $tris, bool $left) => array_filter($tris, fn ($t) => (($t[0][0] + $t[1][0] + $t[2][0]) / 3 < $span + 0.5) === $left);

            return [$this->middleX($half($built['pieces'][$red], true)) < $this->middleX($half($built['pieces'][$black], true)),
                $this->middleX($half($built['pieces'][$red], false)) < $this->middleX($half($built['pieces'][$black], false))];
        };

        // two alike: the red block on the left on both, as drawn
        $alike = $this->built('earrings', $params, 'use');
        [$red, $black] = $this->redAndBlack($alike['meta']);
        $this->assertSame([true, true], $sides($alike, $red, $black));
        $this->assertTrue($alike['meta']['notes']['one_plate_required']);

        // a mirrored pair: as it is face up, the left one as drawn and the right one its mirror image
        $pair = ['mirror' => true] + $params;
        $this->assertSame([true, false], $sides($this->built('earrings', $pair, 'use'), $red, $black));
        $this->assertSame([false, false], $sides($this->built('earrings', $params), $red, $black), 'two alike lie mirrored on the bed');

        // the grip of the eyelet is on the left one, the same one as face up
        $flush = $this->built('earrings', $pair, 'use')['meta']['notes'];
        $up = $this->built('earrings', ['flush' => false] + $pair)['meta']['notes'];
        $this->assertEqualsWithDelta($up['eyelet']['x'], $flush['eyelet']['x'], 0.1);
        $this->assertEqualsWithDelta($up['eyelet']['y'], $flush['eyelet']['y'], 0.1);
    }

    public function test_only_the_flat_plates_turn_over_and_a_design_without_flush_stays_as_it_was(): void
    {
        // the two lists are one rule, in two languages
        preg_match('/^FLUSH_DOWN = \(([^)]*)\)/m', (string) file_get_contents(base_path('engines/python/shape_kinds.py')), $m);
        $this->assertSame(ParametricGenerator::FLUSH_DOWN, array_map(fn ($s) => trim($s, ' "'), explode(',', $m[1])));

        // what stands up or has a round top stays face up, colours in the top of the plate
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin);
        foreach ([['straw', ['line1' => 'Ela', 'width' => 40, 'thickness' => 4, 'straw_d' => 8]], ['opener', ['line1' => 'Ela', 'width' => 50, 'thickness' => 4]], ['cookie', ['line1' => 'Ela', 'width' => 60, 'thickness' => 6]]] as [$kind, $params]) {
            $built = $this->built($kind, $params + ['flush' => true]);
            $this->assertFalse($built['meta']['notes']['one_plate_required'], $kind);
            $color = $this->boxes($built['meta'])[$built['meta']['notes']['colors'][0]['part']];
            $this->assertEqualsWithDelta($params['thickness'], $color[5], 0.01, $kind);
            $this->assertEqualsWithDelta($params['thickness'] - 0.6, $color[2], 0.01, $kind);
        }

        // no flush: as before, the same volume to the tenth (measured on main e0be23c4, 1 Nov 2026)
        foreach ([
            ['charm', ['artwork' => 'lib:colour/matplace', 'body' => 'image', 'width' => 55, 'thickness' => 3, 'frame' => 1.7, 'eyelet' => true, 'eye_pos' => 42, 'eye_hole' => 4.5], 3350.2],
            ['magnet', ['artwork' => 'lib:colour/matplace', 'body' => 'image', 'width' => 60, 'thickness' => 3, 'frame' => 1.2, 'mount' => 'glue', 'mag_d' => 10, 'mag_h' => 2, 'mag_gap' => 0.2], 3474.1],
            ['coaster', ['artwork' => 'lib:colour/matplace', 'body' => 'circle', 'width' => 100, 'thickness' => 4, 'frame' => 2, 'relief' => 0.6, 'colors_n' => 4, 'bg_strength' => 30, 'smooth' => 0.3, 'grooves' => true], 32307.6],
        ] as [$kind, $params, $volume]) {
            $built = $this->built($kind, $params);
            $this->assertEqualsWithDelta($volume, $built['meta']['volume_mm3'], 0.2, $kind);
            $this->assertFalse($built['meta']['notes']['one_plate_required'], $kind);
        }
    }

    public function test_the_page_hides_the_bevel_with_flush_and_says_how_it_prints(): void
    {
        foreach (ParametricGenerator::FLUSH_DOWN as $kind) {
            $this->assertSame(['flush', ['off']], ParametricGenerator::WHEN[$kind]['bevel'], $kind);
            $this->assertStringContainsString('lícem dolů', __('param.flag.'.$kind.'.flush.hint'), $kind);
        }
        $this->assertArrayNotHasKey('bevel', ParametricGenerator::WHEN['gingerbread'], 'a gingerbread stays face up with its bevel');
        $this->assertStringNotContainsString('lícem dolů', __('param.flag.flush.hint'));
        $this->assertStringContainsString('jedné podložce', __('param.shape.print.plate.farm'));
        $this->assertStringContainsString('jedné podložce', __('param.shape.print.plate.medallion.farm'));
        // the limit is the farm's (max_colors of the page's config, ColorLimit::farm: 8 with the Core One), no longer "four"
        $this->assertStringContainsString('nejvýš 8 v jednom tisku', __('param.shape.print.plate.many.farm', ['c' => 9, 'max' => 8]));
        $this->assertStringNotContainsString('jednom kuse', __('param.shape.print.plate.farm'), 'several pieces share the plate (docs/AL.md §10.13)');
        // what prints as one piece in one colour still cannot be ordered in colours (a rim over raised colours, a straw)
        $this->assertStringContainsString('nejde', __('param.shape.print.multi.farm'));
        $this->get('/tools/charm')->assertOk()->assertSee('lícem dolů')->assertSee('shape.print.plate', false);
    }
}
