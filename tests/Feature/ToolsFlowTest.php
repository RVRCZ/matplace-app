<?php

namespace Tests\Feature;

use App\Domain\Tools\ModelCheck;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Mesh\StlTopology;
use App\Models\ModelFile;
use App\Support\NextStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The flow of a tool: rate limits that do not trip each other, errors in the visitor's language, the next step named truthfully. */
class ToolsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_inline_rate_limit_has_its_own_counter(): void
    {
        $prefixes = [];
        foreach (Route::getRoutes() as $route) {
            // the farm agent's group shares one budget on purpose (one agent per address polls several endpoints)
            if (str_starts_with($route->uri(), 'api/agent/') || (! str_starts_with($route->uri(), 'api/') && ! str_starts_with($route->uri(), 'farm/') && ! str_starts_with($route->uri(), 'account/'))) {
                continue;
            }
            foreach ($route->middleware() as $m) {
                if (preg_match('/^throttle:(\d+),(\d+)(?:,(\w+))?$/', $m, $hit)) {
                    $this->assertArrayHasKey(3, $hit, "{$route->uri()} shares its rate limit with every other inline throttle ({$m})");
                    $prefixes[$hit[3]][] = $route->uri();
                }
            }
        }
        $this->assertNotEmpty($prefixes);
        foreach ($prefixes as $prefix => $uris) {
            $this->assertCount(1, $uris, "prefix {$prefix} is used by several routes: ".implode(', ', $uris));
        }
        $this->assertContains('api/tools/param/preview', $prefixes['preview']);
        $this->assertContains('api/tools/param', $prefixes['create']);
    }

    public function test_missing_text_is_explained_in_the_visitors_language(): void
    {
        // the visitor's language comes from the request (?lang, cookie, Accept-Language), so the expectation names it too
        $first = fn (array $body, string $field, string $lang = 'cs') => $this->postJson('/api/tools/param/preview?lang='.$lang, $body)->assertStatus(422)->json('errors')[$field][0] ?? null;
        $this->assertSame(__('param.text_required', [], 'cs'), $first(['kind' => 'sign', 'params' => ['line1' => '']], 'params.line1'));
        $this->assertSame(__('param.error.qr_bad_text', [], 'es'), $first(['kind' => 'qr', 'params' => []], 'params.url', 'es'));
        $this->assertSame(__('param.error.text_too_long', ['n' => 40], 'en'), $first(['kind' => 'sign', 'params' => ['line1' => str_repeat('x', 41)]], 'params.line1', 'en'));
        $this->assertSame(__('param.error.out_of_range', ['n' => __('param.f.width', [], 'cs')], 'cs'), $first(['kind' => 'organizer', 'params' => ['width' => 5000]], 'params.width'));
        $this->assertStringNotContainsString('field', (string) $first(['kind' => 'sign', 'params' => ['line1' => '']], 'params.line1', 'en'));
    }

    public function test_tool_pages_promise_the_step_that_really_follows(): void
    {
        // marketplace: an inquiry to printers
        config(['features.marketplace' => true, 'farm.enabled' => true, 'farm.public' => false]);
        $this->assertSame(NextStep::INQUIRY, NextStep::mode());
        $this->get('/tools')->assertOk()->assertSee(__('tools.create.lead'))->assertSee('#spare', false);
        $this->get('/tools/organizer')->assertOk()->assertSee(__('param.step.inquiry'))->assertSee(__('param.go'));

        // farm only, not yet public: a visitor downloads, the admin also prints on the farm
        config(['features.marketplace' => false, 'farm.enabled' => true, 'farm.open' => true, 'farm.public' => false]);
        $this->assertSame(NextStep::DOWNLOAD, NextStep::mode());
        $page = $this->get('/tools')->assertOk();
        $page->assertSee(__('tools.create.lead.download'))->assertDontSee('#spare', false)->assertDontSee(__('tools.intent.spare'));
        $page->assertSee(__('footer.promise.download'))->assertDontSee(__('footer.promise'));
        $this->get('/tools/organizer')->assertOk()->assertSee(__('param.step.inquiry.download'))->assertSee(__('param.go.download'))->assertDontSee(__('param.go.hint'));
        $this->get('/tools/check')->assertOk()->assertSee(__('check.page.go.download'));
        $this->assertSame(__('check.disclaimer.download'), NextStep::text('check.disclaimer'));      // goes to the browser through the JSON dictionary
        $this->get('/tools/mold')->assertOk()->assertSee(__('mold.page.go.download'));
        $this->get('/tools/vase')->assertOk()->assertSee(__('param.vase.tip.download'))->assertDontSee(__('param.vase.tip'));
        $this->get('/tools/relief')->assertOk()->assertSee(__('param.step.inquiry.download'));
        $this->get('/tools/figure')->assertOk()->assertSee(__('param.step.inquiry.download'));

        config(['farm.public' => true]);
        $this->assertSame(NextStep::FARM, NextStep::mode());
        $this->get('/tools/organizer')->assertOk()->assertSee(__('param.go.farm'))->assertSee(__('param.step.inquiry.farm'));
        $this->get('/tools/check')->assertOk()->assertSee(__('check.page.go.farm'));
        $this->assertSame(__('calc.tip.modular.farm'), NextStep::text('calc.tip.modular'));
        $this->assertSame(__('calc.tip.mold'), NextStep::text('calc.tip.mold'));                     // no variant: the marketplace wording is fine for everyone

        foreach (['farm', 'download'] as $mode) {
            foreach (['param.step.inquiry', 'param.go', 'param.go.hint', 'tools.create.lead', 'check.page.go', 'footer.promise', 'calc.tip.lightbox', 'calc.tip.modular', 'calc.tip.vase', 'check.disclaimer', 'calc.warn.not_watertight'] as $key) {
                foreach (['cs', 'en', 'es'] as $lang) {
                    app()->setLocale($lang);
                    $this->assertNotSame("{$key}.{$mode}", __("{$key}.{$mode}"), "{$key}.{$mode} missing in {$lang}");
                    $this->assertStringNotContainsString('tiskař', __("{$key}.{$mode}"), "{$key}.{$mode} still talks about printers");
                }
            }
        }
    }

    public function test_multi_part_products_are_judged_by_their_biggest_part(): void
    {
        // an illuminated sign 180 mm wide lays its four parts side by side on a 363 mm plate; each part fits a 250 mm printer
        $set = new ModelFile([
            'status' => ModelFile::STATUS_READY, 'stl_path' => 'x.stl', 'bbox' => ['x' => 363.5, 'y' => 135.7, 'z' => 35], 'mesh_report' => ['watertight' => true, 'shells' => 4],
            'origin' => 'tool', 'origin_ref' => 'lightbox', 'tool_params' => ['width' => 180, 'parts_bbox' => ['body' => [184, 62.2, 35], 'face' => [184, 62.2, 1.2], 'diffuser' => [180, 58, 1], 'back' => [180, 58, 2]]],
        ]);
        $report = ModelCheck::report($set);
        $this->assertSame('ok', $report['status']);
        $this->assertSame('parts_fit', $report['items'][0]['code']);
        $this->assertStringStartsWith('184 × 62.2 × 35', $report['items'][0]['params']['size']);

        $set->tool_params = ['width' => 300, 'parts_bbox' => ['body' => [304, 62.2, 35], 'face' => [304, 62.2, 1.2]]];
        $this->assertSame('part_exceeds_bed', ModelCheck::report($set)['items'][0]['code']);

        // the same layout without stored part sizes (older designs) is still judged as one plate
        $set->tool_params = ['width' => 180];
        $this->assertSame('exceeds_bed', ModelCheck::report($set)['items'][0]['code']);
        $this->assertSame(['body', 'face', 'diffuser', 'back'], ParametricGenerator::partsOf('lightbox', []));
        $this->assertSame([], ParametricGenerator::partsOf('box', ['lid' => false]));
        $this->assertSame(['body', 'lid'], ParametricGenerator::partsOf('box', ['lid' => true]));
    }

    public function test_calculator_shows_the_print_size_in_millimetres_before_anything_else(): void
    {
        $page = $this->get('/?lang=cs')->assertOk();
        $page->assertSee(__('calc.size.title', [], 'cs'))->assertSee('id="size-x"', false)->assertSee('id="size-z"', false)->assertSee('id="scale"', false);
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'id="materials"'), strpos($html, 'id="size-x"'), 'the size block comes before the material');
        $this->assertLessThan(strpos($html, 'id="stat-grams"'), strpos($html, 'id="quantity"'), 'size and quantity sit in the price card, under the price');
        $page->assertSee('id="bed-fit"', false)->assertSee('"calc.fit.bed"', false)->assertSee('"calc.fit.none"', false);   // the wording reaches the browser's dictionary
        $page->assertSee('"bed_margin_mm"', false);
        $page->assertSee('id="cta-recalc"', false)->assertSee('id="price-orient"', false);                        // settings wait for "Recalculate"; the farm's price is a line to orient by                                                          // the farm's clear edge: the calculator counts pieces the way the farm does
        $page->assertSee('Kč');                                                                   // the farm's list prices the model: the number is money, not minutes
        foreach (['cs', 'en', 'es'] as $lang) {
            $this->assertNotSame('calc.size.generated', __('calc.size.generated', [], $lang));
        }
    }

    public function test_cookie_cutter_is_a_thin_closed_wall_with_a_flange_and_bridges_for_holes(): void
    {
        $this->get('/tools/cookie-cutter?lang=cs')->assertOk()->assertSee('Vykrajovátko')->assertSee('data-choice="edge"', false)->assertSee('data-flag="stamp"', false);
        $this->get('/tools?lang=cs')->assertOk()->assertSee(__('tools.cutter.title', [], 'cs'));
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        Storage::fake('models');
        $meta = fn ($r) => json_decode((string) $r->headers->get('X-Model-Meta'), true);
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'cutter', 'params' => ['line1' => 'O8', 'width' => 70, 'height' => 18, 'wall' => 1.0, 'flange' => 5, 'flange_t' => 1.6]])->assertOk();
        $m = $meta($r);
        $this->assertEqualsWithDelta(18, $m['bbox']['z'], 0.05);                         // the wall height, exactly
        $this->assertEqualsWithDelta(70 + 2 * (1.0 + 5), $m['bbox']['x'], 0.1);           // the text width plus wall and flange on both sides
        $this->assertSame(3, $m['notes']['bridges']);                                     // O, 8 top, 8 bottom
        $this->assertSame([], $m['notes']['parts']);                                      // text has no inner drawing: no stamp
        $this->assertLessThan(70 * 49 * 18 * 0.15, $m['volume_mm3']);                      // a thin wall, not a block
        $stl = tempnam(sys_get_temp_dir(), 'cut').'.stl';
        file_put_contents($stl, $r->streamedContent());
        $topo = StlTopology::check($stl);
        @unlink($stl);
        $this->assertTrue($topo['watertight'], 'open '.$topo['open_edges'].' non-manifold '.$topo['non_manifold_edges']);
        $this->postJson('/api/tools/param/preview', ['kind' => 'cutter', 'params' => ['line1' => 'O8'], 'part' => 'stamp'])->assertOk()->assertHeader('X-Model-Meta');   // no stamp: falls back to the whole design

        $created = $this->postJson('/api/tools/param', ['kind' => 'cutter', 'params' => ['line1' => 'Ela']])->assertCreated();
        $this->assertSame([], $created->json('file.parts'));
        $this->assertSame('cutter', $created->json('file.kind'));
        $this->assertFalse($created->json('file.hints.supports'));
    }

    public function test_tool_pages_address_the_visitor_formally(): void
    {
        $this->get('/tools/figure?lang=cs')->assertOk()->assertSee('Vyberte nebo vyfoťte fotku')->assertDontSee('Zkus ');
        $this->get('/tools/mold?lang=cs')->assertOk()->assertSee('Přetáhněte sem model')->assertDontSee('Přetáhni');
        $this->get('/tools?lang=cs')->assertOk()->assertSee('Nahrajte fotku, za minutu máte 3D model');
        $this->get('/tools/figure?lang=es')->assertOk()->assertSee('Elija o haga una foto');
    }
}
