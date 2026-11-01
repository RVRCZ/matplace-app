<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The charm opens with our own logo as Roman set it up, and its plate comes in more shapes, shown as icons (docs/P.md §10).
 */
class CharmShapesTest extends TestCase
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
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'charm', 'params' => $params, 'pieces' => true])->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
    }

    /** What the farm makes of the model: the check that stops a print. */
    private function farm(array $params): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'charm', 'params' => $params])->assertOk();
        $out = tempnam(sys_get_temp_dir(), 'charm');
        $farm = app(PythonTool::class)->runScript('farm_tool.py', ['prepare', $r->baseResponse->getFile()->getPathname(), $out, '1', '250', '250', '250'], 120);
        @unlink($out);

        return $farm;
    }

    /** @return list<array{0: array{float, float, float}, 1: array{float, float, float}, 2: array{float, float, float}}> */
    private function triangles(array $params): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'charm', 'params' => $params])->assertOk();

        return iterator_to_array(StlFile::triangles($r->baseResponse->getFile()->getPathname()), false);
    }

    /** How many bodies of more than a square millimetre of surface: joined by their corners, whatever flaps lie about. */
    private function solidBodies(array $triangles): int
    {
        $of = [];
        $find = function (string $k) use (&$of, &$find): string {
            return ($of[$k] ??= $k) === $k ? $k : ($of[$k] = $find($of[$k]));
        };
        $areas = [];
        foreach ($triangles as $t) {
            $keys = array_map(fn ($p) => round($p[0], 3).' '.round($p[1], 3).' '.round($p[2], 3), $t);
            $of[$find($keys[0])] = $find($keys[1]);
            $of[$find($keys[1])] = $find($keys[2]);
        }
        foreach ($triangles as [$a, $b, $c]) {
            $u = [$b[0] - $a[0], $b[1] - $a[1], $b[2] - $a[2]];
            $v = [$c[0] - $a[0], $c[1] - $a[1], $c[2] - $a[2]];
            $root = $find(round($a[0], 3).' '.round($a[1], 3).' '.round($a[2], 3));
            $areas[$root] = ($areas[$root] ?? 0) + sqrt(($u[1] * $v[2] - $u[2] * $v[1]) ** 2 + ($u[2] * $v[0] - $u[0] * $v[2]) ** 2 + ($u[0] * $v[1] - $u[1] * $v[0]) ** 2) / 2;
        }

        return count(array_filter($areas, fn ($area) => $area > 1));
    }

    public function test_the_eyelet_is_sunk_into_the_plate_and_its_hole_goes_right_through(): void
    {
        // on the right side of a straight plate; a 4.5 mm hole in a 2 mm wall, as the page opens with
        $plate = ['line1' => 'Jana', 'body' => 'rect', 'width' => 55, 'thickness' => 3, 'frame' => 1.5, 'eyelet' => true, 'eye_pos' => 25, 'eye_hole' => 4.5, 'eye_wall' => 2];
        $m = $this->meta($plate);
        $eye = $m['notes']['eyelet'];
        $edge = $eye['x'] - 1.25;                                // the middle of the ring stands 1.25 mm off the plate: a millimetre of the hole lies in it
        $this->assertEqualsWithDelta($m['bbox']['x'], $eye['x'] + 4.25, 0.05);
        // a line along the edge of the plate, just outside it, at half its thickness: what the ring holds on to
        $hits = [];
        foreach ($this->triangles($plate) as [$a, $b, $c]) {
            $x = $edge + 0.05;
            $z = 1.37;
            $d = ($b[0] - $a[0]) * ($c[2] - $a[2]) - ($c[0] - $a[0]) * ($b[2] - $a[2]);
            if (abs($d) < 1e-9) {
                continue;
            }
            $u = (($x - $a[0]) * ($c[2] - $a[2]) - ($c[0] - $a[0]) * ($z - $a[2])) / $d;
            $v = (($b[0] - $a[0]) * ($z - $a[2]) - ($x - $a[0]) * ($b[2] - $a[2])) / $d;
            if ($u >= 0 && $v >= 0 && $u + $v <= 1) {
                $hits[] = $a[1] + $u * ($b[1] - $a[1]) + $v * ($c[1] - $a[1]);
            }
        }
        sort($hits);
        // the ring, then the hole, then the ring again: 8 of its 8.5 mm wide, where it used to stand on 5.5 mm
        $this->assertCount(4, $hits);
        $this->assertGreaterThan(7.8, $hits[3] - $hits[0]);
        $this->assertEqualsWithDelta($eye['y'], ($hits[0] + $hits[3]) / 2, 0.1);
        $this->assertGreaterThan(3.4, $hits[2] - $hits[1], 'the hole between them');

        // the hole goes right through whatever lies at the edge: the plate, the raised colours, the rim, the inlaid colours, the bevel
        foreach ([[], ['rim' => true], ['flush' => true], ['bevel' => true], ['frame' => 0], ['thickness' => 6]] as $more) {
            $params = $more + $plate;
            $eye = $this->meta($params)['notes']['eyelet'];
            $deepest = 0.0;
            foreach ($this->triangles($params) as $t) {
                $mid = [($t[0][0] + $t[1][0] + $t[2][0]) / 3, ($t[0][1] + $t[1][1] + $t[2][1]) / 3];
                foreach ([$t[0], $t[1], $t[2], $mid] as $p) {
                    $deepest = max($deepest, 2.25 - hypot($p[0] - $eye['x'], $p[1] - $eye['y']));
                }
            }
            $this->assertLessThan(0.05, $deepest, json_encode($more));
            $this->assertSame(1, $this->farm($params)['shells'], json_encode($more));
        }

        // a ring on the end of a letter's stroke holds too: nothing is cut off, wherever it is put on a name that follows its
        // outline. Counted here, not by the farm: seams of the colours leave flaps only its pymeshfix mends (docs/P.md §10)
        foreach ([5, 20, 45, 70, 95] as $pos) {
            $name = ['line1' => 'Jana', 'body' => 'image', 'width' => 55, 'thickness' => 3, 'frame' => 1.5, 'eyelet' => true, 'eye_pos' => $pos, 'eye_hole' => 4.5];
            $this->assertSame(1, $this->solidBodies($this->triangles($name)), "{$pos} %");
        }
    }

    public function test_the_page_opens_with_our_logo_as_roman_set_it_up(): void
    {
        $preset = ParametricGenerator::presets('charm')['matplace'];
        $this->get('/tools/charm')->assertOk()->assertSee('preset: "matplace"', false);
        // his screenshot: 61.9 x 34.4 x 5.4 mm less the 2 mm the eyelet was sunk by (1 Nov 2026), three colours of the picture on
        // a grey plate, the eyelet right of the e
        $m = $this->meta($preset);
        $this->assertEqualsWithDelta(59.9, $m['bbox']['x'], 0.1);
        $this->assertEqualsWithDelta(34.4, $m['bbox']['y'], 0.1);
        $this->assertEqualsWithDelta(5.4, $m['bbox']['z'], 0.01);
        $this->assertSame([], array_values((array) ($m['notes']['warnings'] ?? [])));
        $this->assertCount(3, $m['notes']['colors']);
        $this->assertEqualsWithDelta(0.511, $m['notes']['colors'][0]['share'], 0.01);
        $this->assertEqualsWithDelta(0.464, $m['notes']['colors'][1]['share'], 0.01);
        $this->assertEqualsWithDelta(0.025, $m['notes']['colors'][2]['share'], 0.01);
        $this->assertSame('grey', $m['notes']['body_color']['code']);
        $this->assertGreaterThan($m['bbox']['x'] * 0.9, $m['notes']['eyelet']['x']);
        $this->assertLessThan($m['bbox']['y'] * 0.6, $m['notes']['eyelet']['y']);
        // and it goes to print: whatever the seams of its colours leave, the farm ends up with one closed body
        $farm = $this->farm($preset);
        $this->assertTrue($farm['watertight']);
        $this->assertSame(1, $farm['shells']);
    }

    public function test_the_plate_comes_in_more_shapes_each_with_a_picture_or_a_name(): void
    {
        $sizes = ['oval' => [55, 38.5], 'heart' => [55, null], 'hex' => [null, 55], 'star' => [55, null], 'circle' => [55, 55]];
        foreach ([['artwork' => 'lib:colour/red-heart'], ['line1' => 'Jana']] as $design) {
            foreach ($sizes as $body => [$x, $y]) {
                $name = $body.' '.json_encode($design);
                $params = $design + ['body' => $body, 'width' => 55, 'thickness' => 3, 'frame' => 1.5];
                $bare = $this->meta($params + ['eyelet' => false]);
                if ($x !== null) {
                    $this->assertEqualsWithDelta($x, $bare['bbox']['x'], 0.3, $name);
                }
                if ($y !== null) {
                    $this->assertEqualsWithDelta($y, $bare['bbox']['y'], 0.3, $name);
                }
                // the eyelet sits on the outline at every place: its middle half a millimetre off it (a 3 mm hole, sunk by 1 mm)
                foreach ([0, 35, 70] as $pos) {
                    $m = $this->meta($params + ['eyelet' => true, 'eye_pos' => $pos, 'eye_hole' => 3, 'eye_wall' => 2]);
                    $eye = $m['notes']['eyelet'];
                    $nearest = min(array_map(fn ($p) => hypot($p[0] - $eye['x'], $p[1] - $eye['y']), $m['notes']['outline']));
                    $this->assertLessThan(1.5, $nearest, "{$name} {$pos} %");
                    $this->assertSame(1, $this->farm($params + ['eyelet' => true, 'eye_pos' => $pos])['shells'], "{$name} {$pos} %: one body");
                }
            }
        }
        // an oval takes a wide name as wide as it goes, not only as far as the circle inside it
        $oval = $this->meta(['line1' => 'Kristýna', 'body' => 'oval', 'width' => 55, 'frame' => 1.5, 'eyelet' => false]);
        $circle = $this->meta(['line1' => 'Kristýna', 'body' => 'circle', 'width' => 39, 'frame' => 1.5, 'eyelet' => false]);
        $this->assertGreaterThan($circle['volume_mm3'], $oval['volume_mm3']);
    }

    public function test_the_shapes_are_chips_with_icons_and_the_siblings_get_them_for_nothing(): void
    {
        // new shapes for the charm alone
        $this->assertSame(['image', 'circle', 'oval', 'rect', 'heart', 'hex', 'star'], ParametricGenerator::choicesOf('charm')['body']);
        $this->assertSame(['rect', 'image', 'circle'], ParametricGenerator::choicesOf('keychain')['body']);
        $this->assertSame(['image', 'circle', 'rect'], ParametricGenerator::choicesOf('bag_charm')['body']);
        $html = $this->get('/tools/charm')->assertOk()->getContent();
        foreach (ParametricGenerator::SHAPE_ICONS as $value) {
            $this->assertStringContainsString('id="i-shape-'.$value.'"', $html, "{$value} has no icon in the sprite");
        }
        foreach (ParametricGenerator::choicesOf('charm')['body'] as $value) {
            $this->assertMatchesRegularExpression('/data-choice="body" value="'.$value.'"[^>]*aria-label="[^"]+"/', $html, $value);
            $this->assertStringContainsString('href="#i-shape-'.$value.'"', $html);
        }
        // the name stays: in the title, the aria-label and under the icon, in every language
        foreach (['cs' => ['', 'Ovál'], 'en' => ['/en', 'Oval'], 'es' => ['/es', 'Óvalo']] as [$prefix, $oval]) {
            $this->get($prefix.'/tools/charm')->assertOk()->assertSee('title="'.$oval.'"', false)->assertSee('aria-label="'.$oval.'"', false);
        }
        // a sibling draws the same chips with its own shapes; other choices stay plain words
        $sibling = $this->get('/tools/bag-charm')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-choice="body" value="circle"[^>]*aria-label="[^"]+"/', $sibling);
        $this->assertDoesNotMatchRegularExpression('/data-choice="body" value="oval"/', $sibling);
        $this->assertDoesNotMatchRegularExpression('/data-choice="typeface"[^>]*aria-label/', $sibling);
        $this->assertStringNotContainsString('href="#i-shape-sans"', $sibling);
    }
}
