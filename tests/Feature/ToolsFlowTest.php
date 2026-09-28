<?php

namespace Tests\Feature;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Tools\ModelCheck;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\DTO\Dimensions;
use App\Engines\Farm\PythonPrintPreparer;
use App\Engines\Mesh\StlTopology;
use App\Models\ModelFile;
use App\Support\NextStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_gifts_page_leads_into_the_sign_tool_with_a_preset_and_a_sample_text(): void
    {
        $this->get('/gifts?lang=cs')->assertOk()->assertSee('Dárek se jménem')->assertSee('Vánoce')->assertSee('preset=keyring', false)->assertSee('preset=ornament', false);
        $this->get('/gifts?lang=en')->assertOk()->assertSee('A gift with a name');
        $this->get('/gifts?lang=es')->assertOk()->assertSee('Un regalo con nombre');
        $this->get('/tools?lang=cs')->assertOk()->assertSee(route('tools.gifts'), false);
        $this->get('/tools/sign?preset=keyring&line1=Jana')->assertOk()->assertSee('data-preset="keyring"', false)->assertSee('data-preset="door"', false);

        // every preset is within the tool's own limits (the server would refuse it otherwise)
        foreach (ParametricGenerator::PRESETS['sign'] as $name => $values) {
            $rules = ParametricGenerator::rules('sign');
            $v = validator(['params' => $values + ['line1' => 'Jana']], $rules);
            $this->assertTrue($v->passes(), $name.': '.implode(' ', $v->errors()->all()));
            foreach (['cs', 'en', 'es'] as $lang) {
                $this->assertNotSame('param.preset.'.$name, __('param.preset.'.$name, [], $lang));
                $this->assertNotSame('gifts.product.'.$name, __('gifts.product.'.$name, [], $lang));
            }
        }
        if (app(ParametricGenerator::class)->available()) {
            foreach (ParametricGenerator::PRESETS['sign'] as $name => $values) {
                $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => $values + ['line1' => 'Anička 2026']])->assertOk();
            }
        }
    }

    public function test_holder_fits_the_measured_thing_in_four_ways(): void
    {
        $this->get('/tools/holder?lang=cs')->assertOk()->assertSee('Držák na cokoliv')->assertSee('data-choice="style"', false)->assertSee('data-preset="remote"', false);
        $this->get('/tools/holder?lang=en')->assertOk()->assertSee('A holder for anything');
        $this->get('/tools/holder?lang=es')->assertOk();
        foreach (ParametricGenerator::PRESETS['holder'] as $name => $values) {
            $v = validator(['params' => $values], ParametricGenerator::rules('holder'));
            $this->assertTrue($v->passes(), $name.': '.implode(' ', $v->errors()->all()));
        }
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        Storage::fake('models');
        $meta = fn ($r) => json_decode((string) $r->headers->get('X-Model-Meta'), true);
        foreach (['cradle', 'pocket', 'hook'] as $style) {
            $r = $this->postJson('/api/tools/param/preview', ['kind' => 'holder', 'params' => ['style' => $style, 'obj_w' => 50, 'obj_d' => 25, 'height' => 60, 'wall' => 3, 'clearance' => 0.8, 'mount' => true]])->assertOk();
            $m = $meta($r);
            $this->assertEqualsWithDelta(25.8, $m['notes']['inner'][1], 0.01, $style.': the thing plus the clearance');
            $this->assertEqualsWithDelta(25.8 + 2 * 3, $m['bbox']['x'], 0.01, $style);
            $this->assertSame(2, $m['notes']['screws']);
            $stl = tempnam(sys_get_temp_dir(), 'hold').'.stl';
            file_put_contents($stl, $r->streamedContent());
            $this->assertTrue(StlTopology::check($stl)['watertight'], $style);
            if ($style !== 'hook') {
                // cradle and pocket stand the way they hang on the wall: walls straight up from the bed, no supports
                $this->assertEqualsWithDelta(60 + 22, $m['bbox']['z'], 0.01, $style.' stands');
                $bed = new Dimensions(250, 250, 250);
                $out = $stl.'.out.stl';
                foreach ([true, false] as $keep) {
                    $mesh = app(PythonPrintPreparer::class)->prepare($stl, $out, 1.0, $bed, $keep);
                    $this->assertFalse($mesh->orientation['changed'], $style.($keep ? ' kept' : ' not laid on its back plate'));
                    $this->assertEqualsWithDelta(0.0, $mesh->orientation['overhang_mm2'], 1.0, $style);
                }
                @unlink($out);
            }
            @unlink($stl);
        }
        $clip = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'holder', 'params' => ['style' => 'clip', 'obj_w' => 24, 'height' => 25]])->assertOk());
        $this->assertSame([24], array_map('intval', $clip['notes']['inner']));
        $wide = $this->postJson('/api/tools/param/preview?lang=cs', ['kind' => 'holder', 'params' => ['style' => 'clip', 'obj_w' => 80]])->assertStatus(422);
        $this->assertStringContainsString('60', $wide->json('errors.params.0'));

        $created = $this->postJson('/api/tools/param', ['kind' => 'holder', 'params' => ['style' => 'pocket', 'obj_w' => 75, 'obj_d' => 75, 'height' => 90]])->assertCreated();
        $this->assertSame('holder', $created->json('file.kind'));
        $this->assertFalse($created->json('file.hints.supports'));
        $this->assertTrue(ModelFile::where('uuid', $created->json('file.uuid'))->firstOrFail()->builtForPrinting());
    }

    /** The pictures on the tool cards promise these: a handwritten name with a heart, a round light box, a hexagonal and a domed cap, a box with a cable slot. */
    public function test_the_shapes_shown_on_the_tool_cards_can_be_made(): void
    {
        $this->get('/tools/sign?lang=cs')->assertOk()->assertSee('Psací')->assertSee('Jen jméno, bez destičky')->assertSee('data-symbol="♥"', false)->assertSee('data-preset="name"', false);
        $this->get('/tools/qr?lang=cs')->assertOk()->assertDontSee('data-symbol', false);
        $this->get('/tools/illuminated-sign?lang=cs')->assertOk()->assertSee('Kulatý s rovnou patou');
        $this->get('/tools/cap?lang=cs')->assertOk()->assertSee('Šestihranný')->assertSee('Kulový')->assertSee('data-when="style=push"', false);
        $this->get('/tools/box?lang=cs')->assertOk()->assertSee('S výřezem na kabel');
        $this->get('/gifts?lang=cs')->assertOk()->assertSee('Jméno psacím písmem')->assertSee('preset=name', false);
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $meta = fn ($r) => json_decode((string) $r->headers->get('X-Model-Meta'), true);
        $closed = function ($r, string $what): void {
            $stl = tempnam(sys_get_temp_dir(), 'shape').'.stl';
            file_put_contents($stl, $r->streamedContent());
            $t = StlTopology::check($stl);
            @unlink($stl);
            $this->assertTrue($t['watertight'], $what);
        };

        // the name is the pendant: one piece although the heart stands apart, no character missing
        $name = ['style' => 'name', 'typeface' => 'script', 'text_height' => 14, 'thickness' => 3, 'relief' => 1, 'keyring' => true, 'line1' => 'Emma ♥'];
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => $name])->assertOk();
        $m = $meta($r);
        $this->assertSame([], $m['notes']['missing_chars']);
        $this->assertGreaterThanOrEqual(1, $m['notes']['links']);
        $this->assertLessThan(30, $m['bbox']['y'], 'no plate round the name');
        $closed($r, 'name');
        // an emoji from the spare font on an ordinary plate
        $m = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'sign', 'params' => ['style' => 'emboss', 'typeface' => 'sans', 'line1' => 'Rex 🐾']])->assertOk());
        $this->assertSame([], $m['notes']['missing_chars']);

        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'lightbox', 'params' => ['shape' => 'round', 'width' => 160, 'depth' => 35, 'line1' => 'OPEN'], 'part' => 'body'])->assertOk();
        $m = $meta($r);
        $this->assertEqualsWithDelta(160.0, $m['bbox']['x'], 0.1);
        $this->assertEqualsWithDelta(144.0, $m['bbox']['y'], 0.1, 'a circle with a fifth of its radius cut off for the foot');
        $this->assertEqualsWithDelta(96.0, $m['notes']['foot_mm'], 0.1);
        $closed($r, 'round light box');

        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'cap', 'params' => ['style' => 'plug', 'shape' => 'hex', 'size_a' => 30, 'height' => 12]])->assertOk();
        $this->assertSame([30], array_map('intval', $meta($r)['notes']['fits']));
        $closed($r, 'hexagonal plug');
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'cap', 'params' => ['style' => 'push', 'shape' => 'round', 'head' => 'dome', 'size_a' => 30, 'height' => 12, 'wall' => 2, 'clearance' => 0.3]])->assertOk();
        $this->assertEqualsWithDelta(12 + 30 / 2 + 0.3 + 2, $meta($r)['bbox']['z'], 0.05, 'the skirt and a half ball');
        $closed($r, 'domed cap');
        $bad = $this->postJson('/api/tools/param/preview?lang=cs', ['kind' => 'cap', 'params' => ['style' => 'push', 'shape' => 'hex', 'head' => 'dome', 'size_a' => 30]])->assertStatus(422);
        $this->assertStringContainsString('Kulový vršek', $bad->json('errors.params.0'));

        $plain = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['inner_w' => 120, 'inner_d' => 70, 'inner_h' => 50, 'lid' => true], 'part' => 'body'])->assertOk());
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['inner_w' => 120, 'inner_d' => 70, 'inner_h' => 50, 'lid' => true, 'cable_slot' => true, 'cable_d' => 10], 'part' => 'body'])->assertOk();
        $this->assertLessThan($plain['volume_mm3'] - 200, $meta($r)['volume_mm3'], 'the slot took plastic out of the wall');
        $closed($r, 'box with a cable slot');
        $small = $this->postJson('/api/tools/param/preview?lang=cs', ['kind' => 'box', 'params' => ['inner_w' => 30, 'inner_d' => 20, 'inner_h' => 10, 'lid' => true, 'cable_slot' => true, 'cable_d' => 20]])->assertStatus(422);
        $this->assertStringContainsString('Výřez na kabel', $small->json('errors.params.0'));
    }

    /** A bear drawn in black with white eyes or as a line drawing: the cutter cuts the head, the stamp marks the face. */
    public function test_a_bear_gets_its_outline_cut_and_its_face_stamped(): void
    {
        if (! app(ParametricGenerator::class)->available() || ! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Python with manifold3d or GD is not installed.');
        }
        Storage::fake('local');
        $meta = fn ($r) => json_decode((string) $r->headers->get('X-Model-Meta'), true);
        foreach (['filled', 'lines'] as $how) {
            $im = imagecreatetruecolor(400, 400);
            $white = imagecolorallocate($im, 255, 255, 255);
            $black = imagecolorallocate($im, 0, 0, 0);
            imagefill($im, 0, 0, $white);
            imagesetthickness($im, 7);
            foreach ([[100, 85, 110], [300, 85, 110], [200, 215, 280]] as [$x, $y, $d]) {
                if ($how === 'filled') {
                    imagefilledellipse($im, $x, $y, $d, $d, $black);
                } else {
                    imagefilledellipse($im, $x, $y, $d, $d, $black);
                    imagefilledellipse($im, $x, $y, $d - 14, $d - 14, $white);
                }
            }
            $ink = $how === 'filled' ? $white : $black;
            imagefilledellipse($im, 152, 178, 26, 26, $ink);
            imagefilledellipse($im, 248, 178, 26, 26, $ink);
            imagefilledellipse($im, 200, 236, 32, 24, $ink);
            $png = sys_get_temp_dir().'/mp_bear_'.$how.'_'.uniqid().'.png';
            imagepng($im, $png);
            $ref = $this->post('/api/tools/artwork', ['file' => new UploadedFile($png, 'bear.png', 'image/png', null, true)], ['Accept' => 'application/json'])->assertSuccessful()->json('artwork');
            $m = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'cutter', 'params' => ['width' => 80, 'stamp' => true, 'artwork' => $ref]])->assertOk());
            $this->assertSame(['body', 'stamp'], $m['notes']['parts'], $how);
            $stamp = $meta($this->postJson('/api/tools/param/preview', ['kind' => 'cutter', 'params' => ['width' => 80, 'stamp' => true, 'artwork' => $ref], 'part' => 'stamp'])->assertOk());
            // the marks are the eyes and the nose, not the whole head: the raised part is a small share of the plate
            $plate = $stamp['bbox']['x'] * $stamp['bbox']['y'] * 1.6;
            $this->assertLessThan($plate * 0.9, $stamp['volume_mm3'], $how);
            @unlink($png);
        }
    }

    public function test_lid_plug_and_threaded_cap_fit_what_was_measured(): void
    {
        $this->get('/tools/cap?lang=cs')->assertOk()->assertSee('Víčko, zátka, krytka')->assertSee('data-preset="pet"', false)->assertSee('data-param="pitch"', false);
        $this->get('/tools/cap?lang=en')->assertOk()->assertSee('Lid, plug, cover');
        $this->get('/tools/cap?lang=es')->assertOk();
        foreach (ParametricGenerator::PRESETS['cap'] as $name => $values) {
            $v = validator(['params' => $values], ParametricGenerator::rules('cap'));
            $this->assertTrue($v->passes(), $name.': '.implode(' ', $v->errors()->all()));
        }
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        Storage::fake('models');
        $meta = fn ($r) => json_decode((string) $r->headers->get('X-Model-Meta'), true);
        $closed = function ($r, string $what) {
            $stl = tempnam(sys_get_temp_dir(), 'cap').'.stl';
            file_put_contents($stl, $r->streamedContent());
            $this->assertTrue(StlTopology::check($stl)['watertight'], $what);
            @unlink($stl);
        };
        $base = ['size_a' => 40, 'size_b' => 30, 'height' => 12, 'wall' => 2, 'top' => 2, 'clearance' => 0.3, 'grip' => false];

        // push-on: the cavity is the neck plus clearance on both sides, the wall comes on top of it
        $push = $this->postJson('/api/tools/param/preview', ['kind' => 'cap', 'params' => $base + ['style' => 'push', 'shape' => 'round']])->assertOk();
        $this->assertEqualsWithDelta(40 + 2 * 0.3 + 2 * 2, $meta($push)['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(14.0, $meta($push)['bbox']['z'], 0.01);
        $closed($push, 'push');

        // plug: narrower than the opening by the clearance, stopped by a flange wider than the opening
        $plug = $this->postJson('/api/tools/param/preview', ['kind' => 'cap', 'params' => $base + ['style' => 'plug', 'shape' => 'rect']])->assertOk();
        $this->assertGreaterThan(40, $meta($plug)['bbox']['x']);
        $this->assertSame([40, 30], array_map('intval', $meta($plug)['notes']['fits']));
        $closed($plug, 'plug');

        // threaded: whole turns of one helix, and an honest note to try it first
        $thread = $this->postJson('/api/tools/param/preview', ['kind' => 'cap', 'params' => ['style' => 'thread', 'shape' => 'round', 'size_a' => 27.4, 'height' => 12, 'pitch' => 2.7]])->assertOk();
        $this->assertEqualsWithDelta(4.4, $meta($thread)['notes']['thread']['turns'], 0.05);
        $this->assertContains('thread_try', $meta($thread)['notes']['warnings']);
        $closed($thread, 'thread');

        $this->postJson('/api/tools/param/preview?lang=cs', ['kind' => 'cap', 'params' => ['style' => 'thread', 'shape' => 'rect']])->assertStatus(422)->assertJsonFragment([__('param.error.cap_thread_round', [], 'cs')]);
        $this->assertSame('cap', $this->postJson('/api/tools/param', ['kind' => 'cap', 'params' => $base + ['style' => 'plug']])->assertCreated()->json('file.kind'));
    }

    public function test_calculator_compares_materials_for_the_model(): void
    {
        $page = $this->get('/?lang=cs')->assertOk();
        $page->assertSee('id="mat-compare"', false)->assertSee('Porovnat materiály')->assertSee('"compare.col.heat"', false);
        $materials = collect(app(MaterialCatalog::class)->all())->keyBy('code');
        foreach (['PLA', 'PETG', 'ASA', 'TPU', 'PA'] as $code) {
            $p = $materials[$code]['props'];
            $this->assertIsArray($p, $code);
            $this->assertGreaterThan(40, $p['heat']);
            $this->assertContains($p['outdoor'], ['yes', 'limited', 'no']);
            $this->assertContains($p['food'], ['liner', 'no']);
            $this->assertGreaterThanOrEqual(1.0, $p['time']);
        }
        $this->assertNull($materials['RESIN']['props']);                                  // another technology: not in the table
        $this->assertGreaterThan($materials['PLA']['props']['heat'], $materials['ASA']['props']['heat']);
        foreach (['cs', 'en', 'es'] as $lang) {
            foreach (['compare.title', 'compare.note', 'compare.outdoor.limited', 'compare.food.liner'] as $key) {
                $this->assertNotSame($key, __($key, [], $lang));
            }
        }
    }

    public function test_tool_pages_address_the_visitor_formally(): void
    {
        $this->get('/tools/figure?lang=cs')->assertOk()->assertSee('Vyberte nebo vyfoťte fotku')->assertDontSee('Zkus ');
        $this->get('/tools/mold?lang=cs')->assertOk()->assertSee('Přetáhněte sem model')->assertDontSee('Přetáhni');
        $this->get('/tools?lang=cs')->assertOk()->assertSee('Nahrajte fotku, za minutu máte 3D model');
        $this->get('/tools/figure?lang=es')->assertOk()->assertSee('Elija o haga una foto');
    }
}
