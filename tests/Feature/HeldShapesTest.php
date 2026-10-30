<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\Mesh\StlFile;
use App\Models\User;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A straw topper with a clip and a can opener with a tongue: products of the shape family that are held, not hung.
 * Both work end to end but stay out of the catalogue until one of each was printed and tried.
 */
class HeldShapesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
        // held back in config/tools.php: only an admin gets to their pages and their generators (ToolVisibility)
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin);
    }

    /** @return array<string, mixed> */
    private function meta(string $kind, array $params): array
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'pieces' => true])->assertOk();

        return PreviewMeta::whole($r->headers->get('X-Model-Meta'));
    }

    /**
     * How far into the straw's way the model reaches: the deepest point (a corner or the middle of a triangle) inside the
     * circle the straw takes through a clip on the right, 0 when that way is empty. The clip is the model's right edge.
     */
    private function intoStraw(array $params, float $d): float
    {
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'straw', 'params' => $params])->assertOk();
        $triangles = iterator_to_array(StlFile::triangles($r->baseResponse->getFile()->getPathname()), false);
        $xmax = max(array_map(fn ($t) => max($t[0][0], $t[1][0], $t[2][0]), $triangles));
        $rIn = $d / 2 + 0.2;
        $rOut = $rIn + 1.6;
        $deepest = 0.0;
        foreach ($triangles as $t) {
            $mid = [($t[0][0] + $t[1][0] + $t[2][0]) / 3, 0, ($t[0][2] + $t[1][2] + $t[2][2]) / 3];
            foreach ([$t[0], $t[1], $t[2], $mid] as $v) {
                $deepest = max($deepest, $rIn - hypot($v[0] - ($xmax - $rOut), $v[2] - ($rOut - 0.5)));
            }
        }

        return $deepest;
    }

    public function test_a_straw_topper_carries_a_clip_as_wide_as_the_straw_asks(): void
    {
        $star = ['artwork' => 'lib:colour/smiling-star', 'width' => 35, 'thickness' => 3];
        $thin = $this->meta('straw', $star + ['straw_d' => 6]);
        $thick = $this->meta('straw', $star + ['straw_d' => 12]);
        // the clip is a tube round the straw (0.4 mm of play, walls of 1.6 mm, half a millimetre sunk into the bed), open on
        // top for six tenths of the straw's width: it ends where that opening begins
        $top = fn (float $d) => ($d / 2 + 1.8) - 0.5 + sqrt(($d / 2 + 1.8) ** 2 - (0.3 * $d) ** 2);
        $this->assertEqualsWithDelta($top(6), $thin['bbox']['z'], 0.05);
        $this->assertEqualsWithDelta($top(12), $thick['bbox']['z'], 0.05);
        // it stands upright beside the picture wherever it is put: the model grows sideways, not up or down
        $bare = $this->meta('charm', ['eyelet' => false] + $star);
        $this->assertGreaterThan($bare['bbox']['x'] + 5, $thin['bbox']['x']);
        $this->assertEqualsWithDelta($thin['bbox']['z'], $thin['notes']['outer'][2], 0.05);      // the page shows the height of the clip, not of the plate
        $this->assertGreaterThan($thin['volume_mm3'], $thick['volume_mm3']);
        // it belongs to the plate (no part of its own) and the picture's colours still change by height
        $this->assertNotContains('clip', $thin['notes']['parts']);
        $this->assertFalse($thin['notes']['multi_material']);
        // the visitor drags it along the outline: the grip and the path are told, and another place is another model
        $this->assertArrayHasKey('outline', $thin['notes']);
        $this->assertCount(120, $thin['notes']['outline']);
        $moved = $this->meta('straw', $star + ['straw_d' => 6, 'eye_pos' => 0]);
        $this->assertNotSame($thin['notes']['eyelet'], $moved['notes']['eyelet']);
        // the grip lies inside the plate the model is laid on: the clip never pushes the model off its corner
        foreach ([$thin, $moved] as $m) {
            $this->assertGreaterThanOrEqual(0, $m['notes']['eyelet']['x']);
            $this->assertGreaterThanOrEqual(0, $m['notes']['eyelet']['y']);
            $this->assertLessThanOrEqual($m['bbox']['x'], $m['notes']['eyelet']['x']);
            $this->assertLessThanOrEqual($m['bbox']['y'], $m['notes']['eyelet']['y']);
        }
    }

    public function test_a_name_along_the_straw_is_held_by_a_clip_along_it(): void
    {
        $name = ['line1' => 'Ela', 'width' => 40, 'thickness' => 3, 'straw_d' => 8];
        $across = $this->meta('straw', $name + ['text_dir' => 'across']);
        $along = $this->meta('straw', $name + ['text_dir' => 'along']);
        // across: the name runs sideways and the clip holds it at one point, 14 mm long, dragged along the outline
        $this->assertEquals(['length' => 14, 'along' => false], $across['notes']['clip']);
        $this->assertArrayHasKey('outline', $across['notes']);
        // along: the name is turned to run up the straw, and a clip of 15 mm holds it in the middle of its baseline,
        // however long the name is (Roman: the clip need not grow with the text)
        $this->assertGreaterThan($across['bbox']['y'] + 10, $along['bbox']['y']);
        $this->assertLessThan($across['bbox']['x'] - 10, $along['bbox']['x']);
        $this->assertEquals(['length' => 15, 'along' => true], $along['notes']['clip']);
        $this->assertGreaterThan(15 + 10, $along['bbox']['y']);
        $this->assertArrayNotHasKey('outline', $along['notes']);       // nothing to drag: the clip is where the straw is
        // the same tube round the straw: as high, 0.4 mm of play and walls of 1.6 mm
        $this->assertEqualsWithDelta($across['bbox']['z'], $along['bbox']['z'], 0.05);
        // a strip of the plate along the baseline ties the letters: the page has nothing to say about pieces joined by a bridge
        $this->assertNotContains('pieces_tied', (array) ($along['notes']['warnings'] ?? []));
        // a round plate gets the same 15 mm on its arc
        $round = $this->meta('straw', ['artwork' => 'lib:colour/smiling-star', 'body' => 'circle', 'width' => 35, 'straw_d' => 8, 'text_dir' => 'along']);
        $this->assertEquals(15, $round['notes']['clip']['length']);
    }

    public function test_nothing_reaches_into_the_straws_way_through_the_clip(): void
    {
        // the plate under the clip used to be as thick as the plate and rose 1.9 mm into the tube (Roman, 30 Oct 2026:
        // "vstupuje hrana do prostoru brčka"); the straw's way is now cut out of the plate and the letters
        foreach ([6, 8, 12] as $d) {
            $this->assertLessThan(0.05, $this->intoStraw(['line1' => 'Ela', 'width' => 40, 'thickness' => 3, 'straw_d' => $d, 'text_dir' => 'along'], $d), "Ela along, {$d} mm");
        }
        $this->assertLessThan(0.05, $this->intoStraw(['line1' => 'Ela', 'width' => 40, 'thickness' => 4, 'flush' => true, 'straw_d' => 8, 'text_dir' => 'along'], 8), 'thick plate');
    }

    public function test_the_page_opens_with_a_name_along_a_common_straw_and_asks_for_the_straw_first(): void
    {
        $preset = ParametricGenerator::PRESETS['straw']['ela'];
        $this->assertSame(['along', 's8', 8], [$preset['text_dir'], $preset['straw_size'], $preset['straw_d']]);
        $html = $this->get('/tools/straw-topper')->assertOk()->assertSee('preset: "ela"', false)->getContent();
        // the straw's kinds right under the name, its number under them, both before the sizes; the clip's place only across
        $name = strpos($html, 'data-text="line1"');
        $chips = strpos($html, 'data-choice="straw_size" value="s12"');
        $field = strpos($html, 'data-param="straw_d"');
        $this->assertTrue($name < $chips && $chips < $field && $field < strpos($html, 'data-param="width"'), 'name, straw kinds, diameter, sizes');
        $this->assertSame(1, substr_count($html, 'data-param="straw_d"'));
        $this->assertStringContainsString('data-when="text_dir=across" data-field="eye_pos"', $html);
        // a kind of straw writes its diameter, whatever the form sent; a number of its own keeps the kind "other"
        $this->assertEquals(12, ParametricGenerator::clean('straw', ['straw_size' => 's12', 'straw_d' => 8])['straw_d']);
        $this->assertSame(9.5, ParametricGenerator::clean('straw', ['straw_d' => 9.5])['straw_d']);
        $this->assertSame('across', ParametricGenerator::clean('straw', [])['text_dir']);      // a design from before the choice keeps its clip
    }

    public function test_a_can_opener_has_a_tongue_that_thins_to_its_tip(): void
    {
        $paw = ['artwork' => 'lib:colour/paw-badge', 'width' => 55, 'thickness' => 4, 'body' => 'circle'];
        $m = $this->meta('opener', $paw);
        $charm = $this->meta('charm', ['eyelet' => false] + $paw);
        // a wedge that reaches 14 mm out of the round plate at its top: as thick as the plate where it leaves it, 1.2 mm at the tip
        $this->assertEqualsWithDelta(55 + 14, $m['bbox']['y'], 1.2);
        $this->assertEqualsWithDelta($charm['bbox']['x'], $m['bbox']['x'], 0.1);
        $this->assertSame($charm['bbox']['z'], $m['bbox']['z']);
        $wedge = $m['volume_mm3'] - $charm['volume_mm3'];
        $this->assertGreaterThan(14 * 8 * 1.2, $wedge);
        $this->assertLessThan(14 * 13 * 4, $wedge);
        $this->assertArrayHasKey('outline', $m['notes']);
        // moved a quarter round, it sticks out to the side instead
        $side = $this->meta('opener', $paw + ['eye_pos' => 25]);
        $this->assertEqualsWithDelta(55 + 14, $side['bbox']['x'], 1.2);
        $this->assertEqualsWithDelta(55, $side['bbox']['y'], 0.6);
    }

    public function test_both_have_a_page_but_are_not_in_the_catalogue(): void
    {
        $catalogue = $this->get('/tools')->assertOk();
        foreach (['straw' => 'straw-topper', 'opener' => 'can-opener'] as $kind => $slug) {
            $this->assertFalse(config('tools.'.$kind.'.available'));
            $catalogue->assertDontSee('/tools/'.$slug, false);
            foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
                app()->setLocale($locale);
                $html = $this->get($prefix.'/tools/'.$slug)->assertOk()->assertSee(__('tools.'.$kind.'.title'))->assertSee(__('param.'.$kind.'.lead'))->getContent();
                $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, "{$kind} ({$locale})");
            }
            app()->setLocale('cs');
        }
        // and nobody but an admin opens them
        auth()->logout();
        $this->get('/tools/straw-topper')->assertNotFound();
        $this->get('/tools/can-opener')->assertNotFound();
    }
}
