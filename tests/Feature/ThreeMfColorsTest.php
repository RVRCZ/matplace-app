<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmPricingProfile;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\GcodeSlot;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\PriceCalculator;
use App\Domain\Files\PaintedColors;
use App\Engines\Contracts\Slicer;
use App\Engines\Mesh\StlFile;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A 3MF painted in a slicer (TODO #50, docs/AL.md §14): its colours read when it is uploaded, the STL ordered by them
 * with the preview's ranges, the small ones joined to their body, two prices in the calculator, an order by colour
 * with a spool per colour that prints on one plate. The fixture (tests/fixtures/painted-two-bodies.3mf, made by
 * painted-two-bodies.py beside it): a green cube with a yellow sticker that touches itself at one corner (the turtle's
 * bug of 28 Oct 2026, the yellow lost) and a white dot of 0.1 %, and a red cube.
 */
final class ThreeMfColorsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create();
    }

    private function upload(string $fixture = 'painted-two-bodies.3mf', string $name = 'painted.3mf'): array
    {
        $path = sys_get_temp_dir().'/mp_painted_'.uniqid().'.3mf';
        copy(base_path('tests/fixtures/'.$fixture), $path);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, $name, null, null, true)])->assertCreated()->json('file');
    }

    public function test_an_upload_reads_its_painted_colours_orders_the_stl_by_them_and_keeps_the_yellow(): void
    {
        $f = $this->upload();
        $this->assertSame('ready', $f['status'], (string) $f['error']);
        $file = ModelFile::where('uuid', $f['uuid'])->firstOrFail();
        $api = $this->getJson('/api/files/'.$f['uuid'])->assertOk()->json('file') ?? $this->getJson('/api/files/'.$f['uuid'])->json();
        $api = isset($api['colors']) ? $api : $f;

        // three printed colours by area, the white dot (0.1 %) merged into the green cube it sits on
        $this->assertSame(['#80FF80', '#E53935', '#FFFF00', '#FFFFFF'], array_column($api['colors'], 'hex'));
        $this->assertSame([false, false, false, true], array_column($api['colors'], 'merged'));
        $this->assertSame('#80FF80', $api['colors'][3]['into']);
        $this->assertEqualsWithDelta(0.615, $api['colors'][0]['share'], 0.005);
        $this->assertEqualsWithDelta(0.023, $api['colors'][2]['share'], 0.002, 'the yellow sticker is there');
        $this->assertSame(['count' => 3, 'merged' => 1, 'too_many' => false, 'status' => 'done'], $api['colors_info']);

        // the preview's ranges: contiguous from 0 to the STL's triangles, one per printed colour, on the STL as it is served
        $groups = $api['groups'];
        $this->assertSame(['#80FF80', '#E53935', '#FFFF00'], array_column($groups, 'hex'));
        $this->assertSame(0, $groups[0]['from']);
        $this->assertSame($groups[0]['to'], $groups[1]['from']);
        $this->assertSame($groups[1]['to'], $groups[2]['from']);
        $this->assertSame(2400, $groups[2]['to']);
        $this->assertSame(2400, $file->triangles);
        $this->assertSame(2400, StlFile::binaryCount($file->absoluteStlPath()));
        $this->assertSame(44, $groups[2]['to'] - $groups[2]['from'], 'the sticker: 22 cells of two triangles');
        // the red cube's range lies where the red cube is (x 30..45), the geometry unchanged
        [$lo] = StlFile::bounds($file->absoluteStlPath());
        $this->assertEqualsWithDelta(0, $lo[0], 0.01);
        $this->assertEqualsWithDelta(45, $file->bbox['x'], 0.01);
        $this->assertArrayHasKey('colors_s', (array) $file->timings);

        // the parts the farm prints: a part per printed colour, named as on /tools/colors
        $this->assertSame(['color_1', 'color_2', 'color_3'], OrderService::designParts($file));
        $this->assertSame('#FFFF00', PaintedColors::partHex($file, 'color_3'));
        $this->assertSame(__('edit.colors.part.color', ['n' => 3]), OrderService::partLabel($file, 'color_3'));
    }

    public function test_files_without_colours_stay_as_they_were(): void
    {
        // a 3MF in one colour (the red cube alone), an STL, a 3MF whose paint cannot be read: no colours, no failure
        $zip = new \ZipArchive;
        $one = sys_get_temp_dir().'/mp_one_'.uniqid().'.3mf';
        copy(base_path('tests/fixtures/painted-two-bodies.3mf'), $one);
        $zip->open($one);
        $model = (string) $zip->getFromName('3D/3dmodel.model');
        $zip->addFromString('3D/3dmodel.model', preg_replace('/ paint_color="[^"]*"/', '', str_replace('<item objectid="1"/>', '', $model)));
        $zip->close();
        $f = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($one, 'one.3mf', null, null, true)])->assertCreated()->json('file');
        $this->assertSame('ready', $f['status']);
        $this->assertNull($f['colors']);
        $this->assertNull($f['groups']);
        $this->assertSame([], OrderService::designParts(ModelFile::where('uuid', $f['uuid'])->firstOrFail()));

        $broken = sys_get_temp_dir().'/mp_broken_'.uniqid().'.3mf';
        copy(base_path('tests/fixtures/painted-two-bodies.3mf'), $broken);
        $zip->open($broken);
        $zip->addFromString('3D/3dmodel.model', str_replace('paint_color="8"', 'paint_color="ZZ!"', (string) $zip->getFromName('3D/3dmodel.model')));
        $zip->close();
        $f = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($broken, 'broken.3mf', null, null, true)])->assertCreated()->json('file');
        $this->assertSame('ready', $f['status']);
        $this->assertSame(2, $f['colors_info']['count'], 'unreadable paint is the body colour: the two cubes remain');

        $stl = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($stl, 10);
        $f = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($stl, 'cube.stl', null, null, true)])->assertCreated()->json('file');
        $this->assertNull($f['colors']);
        $this->assertNull(ModelFile::where('uuid', $f['uuid'])->value('colors'));
    }

    public function test_the_calculator_gets_a_price_in_one_colour_and_in_the_painted_colours(): void
    {
        $f = $this->upload();
        $c = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $f['uuid'], 'material' => 'PLA', 'quality' => 'standard'])->json('calculation')
            ?? $this->fail('no calculation');
        $calc = $this->getJson('/api/calculations/'.$c['token'])->assertOk()->json('calculation');
        $this->assertNotNull($calc['colors'], 'status '.$calc['status']);
        $this->assertSame(3, $calc['colors']['count']);
        $this->assertGreaterThan(0, $calc['colors']['changes']);
        $this->assertGreaterThan($calc['colors']['one'], $calc['colors']['multi'], 'the tower and the changes cost');
        $first = $calc['prices'][0]['total'] ?? $calc['rough']['prices'][0]['total'];
        $this->assertEquals($first, $calc['colors']['one']);
        $this->assertSame($f['groups'], $calc['file']['groups']);

        // a file without colours: no second price
        $stl = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($stl, 10);
        $plain = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($stl, 'cube.stl', null, null, true)])->json('file.uuid');
        $c2 = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $plain, 'material' => 'PLA', 'quality' => 'standard'])->json('calculation');
        $this->assertNull($this->getJson('/api/calculations/'.$c2['token'])->json('calculation.colors'));
    }

    public function test_the_farm_minimum_in_the_calculator_is_the_one_the_order_charges(): void
    {
        // a minimum of 82 net is 99.22 with VAT: the order rounds it up to 100, the calculator showed 99 (28 Oct 2026)
        $settings = app(FarmSettings::class);
        $settings->set('min_price', 82.0);
        $settings->set('rounding', 10.0);
        $profile = app(FarmPricingProfile::class)->profile('PLA');
        $order = app(PriceCalculator::class)->price(10, 2.0, ['hourly_rate' => 35.0, 'price_per_gram' => 0.5, 'fixed_fee' => 0.0, 'min_price' => 82.0,
            'vat_percent' => (float) $settings->get('vat_percent'), 'rounding' => 10.0]);
        $this->assertTrue($order['min_price_applied']);
        $this->assertEquals($order['total'], $profile->minPrice);
        $this->assertEquals(100.0, $profile->minPrice);
    }

    /** @return array{0: FarmColor, 1: FarmColor, 2: FarmColor} green, red and yellow spools of the S1 run by an agent (it changes spools) */
    private function spools(bool $agent = true): array
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        if ($agent) {
            [$runner] = FarmAgent::issue('test agent');
            $s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $runner->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE]);
        } else {
            $s1->update(['mode' => FarmPrinter::MODE_MANUAL, 'farm_agent_id' => null]);
            // the other machines of the seed must not offer themselves either: only the manual S1 holds the colours
        }
        $loaded = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail();
        $pla = FarmColor::findOrFail($loaded->farm_color_id)->farm_material_id;
        $free = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->get();
        $spool = function (FarmPrinterSlot $slot, string $hex, string $name) use ($pla) {
            $color = FarmColor::create(['farm_material_id' => $pla, 'name' => $name, 'hex' => $hex, 'enabled' => true]);
            $slot->update(['farm_color_id' => $color->id, 'remaining_g' => 800, 'enabled' => true]);

            return $color;
        };
        $green = $spool($free[0], '#3CB043', 'Zelená');
        $red = $spool($free[1], '#C01818', 'Červená');
        $yellow = $spool($free[2], '#F5D000', 'Žlutá');

        return [$green, $red, $yellow];
    }

    public function test_an_order_of_a_painted_3mf_prints_a_part_per_colour_from_the_nearest_spools_on_one_plate(): void
    {
        [$green, $red, $yellow] = $this->spools();
        $f = $this->upload();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid']])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertTrue($order->isByParts(), 'painted: by colour from the start');
        $this->assertSame(['color_1', 'color_2', 'color_3'], array_column($order->partPlates(), 'part'));
        // every part from the spool nearest to its colour, all on the machine of the first one
        $this->assertSame([$green->id, $red->id, $yellow->id], array_map('intval', array_column($order->partPlates(), 'color_id')));
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertTrue($order->onePlate(), 'three spools of one machine with a changer: one plate');
        $this->assertCount(3, (array) ($order->slice_params['filaments'] ?? []));
        // the parts are our cut of the file: no "N separate bodies", no "we repaired the surface" on the order page
        $this->assertSame([], array_values(array_intersect(['multiple_shells', 'repaired'], array_column((array) ($order->check['warnings'] ?? []), 'code'))));
        $this->assertGreaterThan(1, (int) ($order->check['mesh']['shells'] ?? 0), 'the plate does hold several bodies');
        // the parts were made once, in place: the red cube still 30 mm to the right of the green one on the plate
        $dir = Storage::disk(ModelFile::DISK)->path('files/'.$f['uuid'].'/parts');
        foreach (['color_1', 'color_2', 'color_3'] as $part) {
            $this->assertFileExists($dir.'/'.$part.'.stl');
        }
        [$lo2] = StlFile::bounds($dir.'/color_2.stl');
        $this->assertEqualsWithDelta(30, $lo2[0], 0.01);
        $this->assertTrue(ModelFile::where('uuid', $f['uuid'])->firstOrFail()->colors['parts']['ok']);

        // the customer wants it in one colour: one piece as before
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid'], 'colors' => 'one'])->assertCreated();
        $one = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertFalse($one->isByParts());
    }

    public function test_copies_of_a_painted_3mf_print_on_one_plate_as_the_painted_project_placed_again(): void
    {
        [$green, $red, $yellow] = $this->spools();
        $f = $this->upload();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid'], 'copies' => 4])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame('painted', $order->timings['one_plate_project'] ?? null);
        $this->assertSame([1, 4, null], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertSame([4], $order->plateLayout());
        $this->assertSame([2, 2], $order->slice_params['copies_grid']['grid']);
        // the project holds the painted objects (the fixture's two cubes) four times each, every copy where the grid has it
        $model = $this->zipEntry(Storage::disk('farm')->path($order->dir().'/plate.3mf'), '3D/3dmodel.model');
        $this->assertSame(2 * 4, preg_match_all('/<item\b/', $model));
        preg_match_all('/<item transform="([^"]+)"/', $model, $m);
        $xs = array_map(fn ($t) => round((float) explode(' ', $t)[9], 1), $m[1]);
        $this->assertEqualsWithDelta($xs[0] + 45 + 5, $xs[1], 0.6, 'the second copy one piece and a gap to the right: '.json_encode($xs));
        // the preview's parts hold the four copies, and the order's spools are the parts' as for one piece
        $this->assertSame([$green->id, $red->id, $yellow->id], array_map('intval', array_column($order->partPlates(), 'color_id')));
        $this->assertSame([4, 4, 4], array_column($order->partPlates(), 'copies'));
        $this->assertGreaterThanOrEqual(4, $order->check['max_copies']);
    }

    public function test_one_painted_piece_tells_how_many_fit_a_plate_and_offers_the_spools_of_changers_alone(): void
    {
        [$green] = $this->spools();
        // a manual machine holds the green too (K14, U22 on production): its spool is no use to a painted order
        $other = FarmPrinter::where('key', '!=', 'kobra-s1-01')->where('enabled', true)->orderBy('id')->firstOrFail();
        $other->update(['mode' => FarmPrinter::MODE_MANUAL, 'farm_agent_id' => null]);
        $other->slots()->orderBy('slot')->firstOrFail()->update(['farm_color_id' => $green->id, 'remaining_g' => 800, 'enabled' => true]);
        $f = $this->upload();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid']])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $state = $this->actingAs($this->user)->getJson(route('farm.orders.status', $order))->assertOk()->json();
        // the page says the copies share the plate (not "one piece at a time", which is the tool's designs)
        $this->assertTrue($state['one_plate_required']);
        $this->assertTrue($state['painted_colors']);
        $this->assertGreaterThan(1, (int) $state['max_copies'], 'one piece ordered: how many would fit, not 1');
        // only the machines that change spools are on offer, the manual ones of the seed hold colours too
        $manual = FarmPrinter::where('mode', FarmPrinter::MODE_MANUAL)->whereHas('slots', fn ($q) => $q->whereNotNull('farm_color_id'))->get()->map->shortCode()->all();
        $this->assertContains($other->shortCode(), $manual);
        $codes = collect($state['colors'])->flatMap(fn ($c) => array_merge((array) $c['codes'], array_column((array) $c['second'], 'code'), array_column((array) $c['machines'], 'code')))->filter()->unique()->values()->all();
        $this->assertNotEmpty($codes);
        foreach ($codes as $code) {
            $this->assertNotContains(substr($code, 0, -1), $manual, 'a spool of a manual machine on offer: '.$code);
        }
    }

    public function test_print_again_of_a_painted_3mf_keeps_its_colours_on_one_plate(): void
    {
        [$green, $red, $yellow] = $this->spools();
        $f = $this->upload();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid'], 'copies' => 2])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $order->forceFill(['status' => FarmOrder::STATUS_DONE, 'paid_at' => now()])->save();
        $r = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/repeat")->assertCreated();
        $copy = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $copy->status, (string) $copy->error.' '.json_encode($copy->timings));
        $this->assertTrue($copy->isByParts());
        $this->assertTrue($copy->onePlate(), json_encode($copy->timings));
        $this->assertSame(2, $copy->copies);
        $this->assertSame([$green->id, $red->id, $yellow->id], array_map('intval', array_column($copy->partPlates(), 'color_id')));
    }

    public function test_more_copies_than_a_plate_holds_take_more_plates_with_the_rest_on_its_own(): void
    {
        $this->spools();
        // a small plate: two by three pieces leave the tower its band (PlateLayout::towerGrid)
        FarmPrinter::where('key', 'kobra-s1-01')->update(['bed_x' => 120, 'bed_y' => 120]);
        $f = $this->upload();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid'], 'copies' => 7])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertTrue($order->onePlate());
        $this->assertSame([2, 6, 1], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertSame([6, 1], $order->plateLayout());
        $this->assertNotNull($order->rest_gcode_path);
        $this->assertFileExists(Storage::disk('farm')->path($order->rest_gcode_path));
        $this->assertSame(2, preg_match_all('/<item\b/', $this->zipEntry(Storage::disk('farm')->path($order->dir().'/plate-rest.3mf'), '3D/3dmodel.model')));
        // the second plate prints its own G-code from the first part's spool, as the first one does
        $this->assertSame($order->absoluteGcodePath(2), Storage::disk('farm')->path($order->rest_gcode_path));
        $this->assertSame($order->plateSpool(1)?->id, $order->plateSpool(2)?->id);
        $this->assertGreaterThan((float) $order->slice_result['rest']['grams'], (float) $order->est_grams);
    }

    public function test_a_painted_3mf_that_cannot_print_in_colour_says_why_and_offers_one_colour(): void
    {
        // the colours sit in a machine without a changer: by parts the inlays would be refused, so the order stops
        $this->spools(agent: false);
        FarmPrinter::where('key', '!=', 'kobra-s1-01')->update(['enabled' => false]);
        $f = $this->upload();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid'], 'copies' => 3])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertTrue($order->isByParts());
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $this->assertSame('painted_one_plate', $order->error);
        $this->assertSame('manual', $order->error_detail);
        $state = $this->actingAs($this->user)->getJson(route('farm.orders.status', $order))->assertOk()->json();
        $this->assertTrue($state['offer_one_color']);
        $this->assertStringContainsString(__('farm.painted_reason.manual'), (string) $state['error_text']);

        // "Print in one colour": the same order, by parts off, recalculated – one piece of one colour as before
        $this->actingAs($this->user)->postJson(route('farm.orders.reslice', $order), ['quality' => $order->quality, 'strength' => $order->strength, 'by_parts' => false])->assertOk();
        $order->refresh();
        $this->assertFalse($order->isByParts());
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertFalse($this->actingAs($this->user)->getJson(route('farm.orders.status', $order))->json('offer_one_color'));
    }

    public function test_the_calculator_counts_the_colour_changes_once_per_plate(): void
    {
        $f = $this->upload();
        $price = function (int $quantity) use ($f): array {
            $c = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $f['uuid'], 'material' => 'PLA', 'quality' => 'standard', 'quantity' => $quantity])->json('calculation');

            return $this->getJson('/api/calculations/'.$c['token'])->assertOk()->json('calculation.colors');
        };
        $one = $price(1);
        $many = $price(40);
        $this->assertSame(1, $one['plates']);
        $this->assertSame(1, $many['plates'], '40 pieces of 45 × 20 mm fit one plate with the tower');
        $this->assertSame($one['changes'], $many['changes'], 'the changes go by layers, not by pieces');
        $this->assertSame(2, $price(60)['plates']);
        $this->assertSame(2 * $one['changes'], $price(60)['changes']);
    }

    private function zipEntry(string $zip, string $name): string
    {
        $z = new \ZipArchive;
        $this->assertTrue($z->open($zip) === true, $zip);
        $data = (string) $z->getFromName($name);
        $z->close();

        return $data;
    }

    /**
     * The acceptance of docs/prompts/import-3mf-barvy.md on this machine, with the real OrcaSlicer 2.4 (ORCA_LOCAL = its
     * orca-slicer.exe, TURTLE_3MF = Roman's turtle): the order of the turtle prints its green and yellow on one plate from
     * the painted file itself, two filaments, the header for the agent. Skipped elsewhere; FARM_VIDEO_SAMPLES gets the numbers.
     */
    public function test_the_turtle_slices_on_one_plate_with_the_real_orca(): void
    {
        $exe = (string) env('ORCA_LOCAL', '');
        $turtle = (string) env('TURTLE_3MF', '');
        if ($exe === '' || ! is_file($exe) || $turtle === '' || ! is_file($turtle)) {
            $this->markTestSkipped('ORCA_LOCAL and TURTLE_3MF not set: the real slice runs on Roman\'s PC only');
        }
        config(['engines.slicer' => 'orca', 'engines.orca.bin' => $exe, 'engines.orca.xvfb' => false, 'engines.orca.cache' => false,
            'engines.orca.work_dir' => sys_get_temp_dir().'/mp_orca_'.uniqid(), 'engines.orca.timeout' => 600]);
        $this->app->forgetInstance(Slicer::class);
        [$green, $red, $yellow] = $this->spools();
        $path = sys_get_temp_dir().'/mp_turtle_'.uniqid().'.3mf';
        copy($turtle, $path);
        $t = microtime(true);
        $f = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'turtlescutieone2.3mf', null, null, true)])->assertCreated()->json('file');
        $upload = round(microtime(true) - $t, 1);
        $this->assertSame(2, $f['colors_info']['count']);
        $t = microtime(true);
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid']])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $prepare = round(microtime(true) - $t, 1);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings).' '.json_encode(collect((array) $order->check)->except(['mesh'])->all()).' '.json_encode($order->check['mesh'] ?? null));
        $this->assertSame([$green->id, $yellow->id], array_map('intval', array_column($order->partPlates(), 'color_id')));
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame('painted', $order->timings['one_plate_project'] ?? null);
        $this->assertCount(2, (array) $order->slice_result['filament_grams']);
        $gcode = Storage::disk('farm')->path($order->gcode_path);
        $copy = GcodeSlot::fileForMany($gcode, $order->filamentSlots(), []);
        $head = implode("\n", array_slice(file($copy), 0, 3));
        $this->assertMatchesRegularExpression('/; matplace filaments T0=\d+ T1=\d+/', $head);
        // the calculator's two prices for the same file, against what the slice of the order says
        $c = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $f['uuid'], 'material' => 'PLA', 'quality' => 'standard'])->json('calculation');
        $calc = $this->getJson('/api/calculations/'.$c['token'])->json('calculation');
        $dir = (string) env('FARM_VIDEO_SAMPLES', '');
        if ($dir !== '' && is_dir($dir)) {
            file_put_contents($dir.'/turtle-one-plate.json', json_encode(['calc_status' => $calc['status'], 'calc_colors' => $calc['colors'], 'calc_slicer' => collect((array) $calc['slicer'])->only(['grams', 'minutes'])->all(), 'upload_s' => $upload, 'order_s' => $prepare, 'grams' => $order->slice_result['filament_grams'],
                'changes' => $order->slice_result['changes'] ?? null, 'minutes' => $order->est_minutes, 'grams_total' => $order->est_grams, 'price' => $order->price_total,
                'head' => $head, 'timings' => $order->timings], JSON_PRETTY_PRINT));
            copy(Storage::disk('farm')->path($order->dir().'/plate.3mf'), $dir.'/turtle-plate.3mf');
        }
    }

    /**
     * The acceptance of the copies (docs/AL.md §14.8) with the real OrcaSlicer 2.4: 12 turtles on one plate of the S1
     * (a 4 × 3 grid above the tower), two filaments, the changes of one plate; 13 turtles on two plates. Skipped
     * without ORCA_LOCAL and TURTLE_3MF; FARM_VIDEO_SAMPLES gets the numbers.
     */
    public function test_twelve_turtles_slice_on_one_plate_and_thirteen_on_two_with_the_real_orca(): void
    {
        $exe = (string) env('ORCA_LOCAL', '');
        $turtle = (string) env('TURTLE_3MF', '');
        if ($exe === '' || ! is_file($exe) || $turtle === '' || ! is_file($turtle)) {
            $this->markTestSkipped('ORCA_LOCAL and TURTLE_3MF not set: the real slice runs on Roman\'s PC only');
        }
        config(['engines.slicer' => 'orca', 'engines.orca.bin' => $exe, 'engines.orca.xvfb' => false, 'engines.orca.cache' => false,
            'engines.orca.work_dir' => sys_get_temp_dir().'/mp_orca_'.uniqid(), 'engines.orca.timeout' => 900]);
        $this->app->forgetInstance(Slicer::class);
        [$green, $red, $yellow] = $this->spools();
        $path = sys_get_temp_dir().'/mp_turtle_'.uniqid().'.3mf';
        copy($turtle, $path);
        $f = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'turtlescutieone2.3mf', null, null, true)])->assertCreated()->json('file');
        $numbers = [];
        foreach ([12, 13] as $copies) {
            $t = microtime(true);
            $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $f['uuid'], 'copies' => $copies])->assertCreated();
            $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
            $prepare = round(microtime(true) - $t, 1);
            $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.$order->error_detail.' '.json_encode($order->timings));
            $this->assertTrue($order->onePlate(), json_encode($order->timings));
            $this->assertSame('painted', $order->timings['one_plate_project'] ?? null);
            $this->assertCount(2, (array) $order->slice_result['filament_grams']);
            $this->assertSame($copies === 12 ? [12] : [12, 1], $order->plateLayout());
            $this->assertSame([4, 3], $order->slice_params['copies_grid']['grid']);
            $c = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $f['uuid'], 'material' => 'PLA', 'quality' => 'standard', 'quantity' => $copies])->json('calculation');
            $calc = $this->getJson('/api/calculations/'.$c['token'])->json('calculation');
            $numbers[$copies] = ['order_s' => $prepare, 'plates' => $order->plates, 'layout' => $order->plateLayout(), 'grid' => $order->slice_params['copies_grid'],
                'changes' => $order->slice_result['changes'] ?? null, 'rest_changes' => $order->slice_result['rest']['changes'] ?? null,
                'plate_minutes' => $order->slice_result['minutes'] ?? null, 'rest_minutes' => $order->slice_result['rest']['minutes'] ?? null,
                'minutes' => $order->est_minutes, 'grams' => $order->est_grams, 'filament_grams' => $order->slice_result['filament_grams'], 'price' => $order->price_total,
                'calc_colors' => $calc['colors'], 'calc_status' => $calc['status'], 'timings' => $order->timings];
        }
        $this->assertSame(2, $numbers[13]['plates']);
        $dir = (string) env('FARM_VIDEO_SAMPLES', '');
        if ($dir !== '' && is_dir($dir)) {
            file_put_contents($dir.'/turtle-copies.json', json_encode($numbers, JSON_PRETTY_PRINT));
        }
    }
}
