<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
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

    public function test_the_page_opens_with_our_logo_as_roman_set_it_up(): void
    {
        $preset = ParametricGenerator::presets('charm')['matplace'];
        $this->get('/tools/charm')->assertOk()->assertSee('preset: "matplace"', false);
        // his screenshot: 61.9 x 34.4 x 5.4 mm, three colours of the picture on a grey plate, the eyelet right of the e
        $m = $this->meta($preset);
        $this->assertEqualsWithDelta(61.9, $m['bbox']['x'], 0.1);
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
                // the eyelet sits on the outline at every place: half the wall into the plate, the hole outside it
                foreach ([0, 35, 70] as $pos) {
                    $m = $this->meta($params + ['eyelet' => true, 'eye_pos' => $pos, 'eye_hole' => 3, 'eye_wall' => 2]);
                    $eye = $m['notes']['eyelet'];
                    $nearest = min(array_map(fn ($p) => hypot($p[0] - $eye['x'], $p[1] - $eye['y']), $m['notes']['outline']));
                    $this->assertEqualsWithDelta(2.5, $nearest, 0.8, "{$name} {$pos} %");
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
