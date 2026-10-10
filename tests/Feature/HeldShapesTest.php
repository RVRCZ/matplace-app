<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Models\User;
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

        return json_decode((string) $r->headers->get('X-Model-Meta'), true);
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
