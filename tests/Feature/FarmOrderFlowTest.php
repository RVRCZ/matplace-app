<?php

namespace Tests\Feature;

use App\Domain\Farm\Dispatcher;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\Wallet;
use App\Engines\Mesh\StlFile;
use App\Mail\FarmAdminAlert;
use App\Mail\FarmOrderStatus;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Rent a printer, end to end: upload → slice → credit → pay → queue → agent prints → done. Fake slicer, fake gateway, sync queue. */
class FarmOrderFlowTest extends TestCase
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
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
    }

    private function upload(float $size = 20.0, ?callable $writer = null): string
    {
        $path = sys_get_temp_dir().'/mp_farm_'.uniqid().'.stl';
        $writer ? $writer($path) : MeshFixtures::cubeStl($path, $size);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function order(float $size = 20.0, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $this->upload($size)] + $extra)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
    }

    private function pay(FarmOrder $order, array $over = [])
    {
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();

        return $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", $over + [
            'slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total'],
        ]);
    }

    private function agentPrinter(): array
    {
        [$agent, $token] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id]);

        return [$printer, ['Authorization' => 'Bearer '.$token]];
    }

    private function sync(array $headers, string $state = 'idle', ?array $job = null)
    {
        return $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => 'kobra-s1-01', 'state' => $state, 'telemetry' => ['extruder' => 215.2, 'bed' => 55], 'job' => $job]]], $headers);
    }

    public function test_every_loaded_colour_of_every_kind_is_offered_and_the_chosen_kind_sets_the_price(): void
    {
        // the seeded printer: white PLA+ in slot 3; load a PETG colour into slot 1 too
        $printer = FarmPrinter::firstOrFail();
        $petg = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'PETG'))->firstOrFail();
        $printer->slots()->where('slot', 0)->update(['farm_color_id' => $petg->id, 'remaining_g' => 700, 'enabled' => true]);

        $order = $this->order();
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $kinds = array_column($state['colors'], 'kind');
        $this->assertCount(2, $state['colors']);
        $this->assertContains('PLA+', $kinds);
        $this->assertContains('PETG', $kinds);
        $petgOffer = collect($state['colors'])->firstWhere('kind', 'PETG');
        $plaOffer = collect($state['colors'])->firstWhere('kind', 'PLA+');
        $this->assertGreaterThanOrEqual($plaOffer['total'], $petgOffer['total'], 'PETG costs more per gram');

        $this->credit(1000);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $petgOffer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $petgOffer['total']])->assertOk();
        $order->refresh();
        $this->assertSame('PETG', $order->material->code, 'the order now belongs to the chosen kind');
        $this->assertEqualsWithDelta($petgOffer['total'], $order->price_total, 0.001);
    }

    public function test_a_spool_with_its_own_slicer_settings_is_resliced_after_payment_at_the_paid_price(): void
    {
        $white = FarmPrinter::firstOrFail()->slots()->where('slot', 2)->firstOrFail()->color;
        $white->update(['print_overrides' => ['nozzle_temp' => 222, 'process' => ['sparse_infill_density' => '25%']]]);

        $order = $this->order();
        $this->credit(1000);
        $paid = $this->pay($order)->assertOk()->json();
        $order->refresh();
        // sync queue: the re-slice already ran; the order went uploaded → sliced → paid → queued again
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertSame('25%', $order->slice_params['overrides']['process']['sparse_infill_density']);
        $this->assertEqualsWithDelta($paid['total'], $order->price_total, 0.001, 'the customer pays what was shown');
        $this->assertSame(['nozzle' => 222, 'nozzle_first' => 225, 'bed' => 60], $white->fresh()->temps(), 'own nozzle, first layer and bed inherited from PLA+');
        $this->assertEqualsWithDelta(1000 - $paid['total'], app(Wallet::class)->balance($this->user), 0.001);
        $this->assertStringContainsString('reslice', $order->events->pluck('note')->implode(' '));
    }

    public function test_a_bigger_machine_takes_what_the_small_one_cannot_and_a_colour_there_means_slicing_again(): void
    {
        // the seeder's Kobra 3 Max (420 x 420 x 500) with light blue PLA+ in slot 1; both machines manual = online
        $max = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $blue = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'PLA+'))->where('name', 'světle modrá')->firstOrFail();
        $max->slots()->where('slot', 0)->update(['farm_color_id' => $blue->id, 'remaining_g' => 1000, 'enabled' => true]);
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();

        // a 300 mm cube fits only the Max: sliced for it from the start, the S1's white is not offered
        $big = $this->order(300.0);
        $this->assertSame($max->id, $big->farm_printer_id);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$big->token}/status")->json();
        $this->assertSame(['light blue'], array_column($state['colors'], 'name'));   // test locale is en

        // a small part goes to the cheapest kind's machine (the S1) but both machines' colours are on offer
        $small = $this->order();
        $this->assertSame($s1->id, $small->farm_printer_id);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$small->token}/status")->json();
        $this->assertEqualsCanonicalizing(['white', 'light blue'], array_column($state['colors'], 'name'));
        $offer = collect($state['colors'])->firstWhere('name', 'light blue');

        // choosing the Max's blue: paid at the shown price, sliced again for the Max, queued there
        $this->credit(1000);
        $this->actingAs($this->user)->postJson("/farm/orders/{$small->token}/pay", ['slot' => $offer['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $offer['total']])->assertOk();
        $small->refresh();
        $this->assertSame($max->id, $small->farm_printer_id);
        $this->assertSame(FarmOrder::STATUS_QUEUED, $small->status);
        $this->assertSame('kobra-3-max-01', $small->slice_params['printer']['key']);
        $this->assertEqualsWithDelta($offer['total'], $small->price_total, 0.001);
        $this->assertStringContainsString('reslice for kobra-3-max-01', $small->events->pluck('note')->implode(' '));
    }

    public function test_guests_cannot_rent_a_printer(): void
    {
        $this->get('/farm')->assertRedirect('/login');
        $this->postJson('/farm/orders', ['file' => '00000000-0000-0000-0000-000000000000'])->assertUnauthorized();
    }

    public function test_order_is_sliced_priced_and_reproducible(): void
    {
        $order = $this->order();

        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertGreaterThan(0, $order->est_minutes);
        $this->assertGreaterThan(0, $order->est_grams);
        $this->assertGreaterThan(0, $order->est_meters);
        $this->assertGreaterThanOrEqual(99 * 1.21, $order->price_total, 'minimum price with VAT');
        Storage::disk('farm')->assertExists($order->gcode_path);
        Storage::disk('farm')->assertExists($order->print_stl_path);
        $this->assertSame(hash_file('sha256', $order->absoluteGcodePath()), $order->gcode_sha256);
        // everything needed to slice the same thing again
        $this->assertSame(0.2, $order->slice_params['layer_mm']);
        $this->assertSame(15, $order->slice_params['infill_percent']);
        $this->assertSame(['0x0', '250x0', '250x250', '0x250'], $order->slice_params['overrides']['machine']['printable_area']);
        $this->assertSame('0', $order->slice_params['overrides']['process']['enable_prime_tower']);
        $this->assertArrayHasKey('machine', $order->slice_params['profile_hashes']);
    }

    public function test_presets_map_to_layer_and_infill_and_a_reslice_changes_the_price_inputs(): void
    {
        $order = $this->order(20, ['quality' => 'fine', 'strength' => 'high']);
        $this->assertSame(0.12, $order->slice_params['layer_mm']);
        $this->assertSame(30, $order->slice_params['infill_percent']);

        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'draft', 'strength' => 'low'])->assertOk();
        $order->refresh();
        $this->assertSame(0.28, $order->slice_params['layer_mm']);
        $this->assertSame(10, $order->slice_params['infill_percent']);
    }

    /** Louskacek, 28 Sep 2026: the job sent to the machine was counted as somebody else's print ahead of its own order. */
    public function test_an_order_whose_job_is_on_the_printer_has_nobody_ahead(): void
    {
        $order = $this->order(20);
        $order->forceFill(['status' => FarmOrder::STATUS_QUEUED, 'est_minutes' => 370])->save();
        FarmPrintJob::create(['farm_order_id' => $order->id, 'farm_printer_id' => $order->farm_printer_id, 'status' => 'sent', 'plate' => 1, 'progress' => 0]);
        $order->printer->forceFill(['last_seen_at' => now()])->save();

        $q = app(Dispatcher::class)->estimate($order->refresh(), app(FarmSettings::class));
        $this->assertSame(0, $q['ahead']);
        $this->assertSame(0, $q['start_in']);
        $this->assertGreaterThanOrEqual(370, $q['finish_in']);
    }

    /** top-single.stl, 28 Sep 2026: a colour changed on the order page was paid for without computing the order again. */
    public function test_another_colour_on_the_order_page_asks_for_a_recalculation(): void
    {
        // the seeder's Kobra 3 Max gets light blue PLA+; the order starts in white on the S1
        $max = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $blue = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'PLA+'))->where('name', 'světle modrá')->firstOrFail();
        $max->slots()->where('slot', 0)->update(['farm_color_id' => $blue->id, 'remaining_g' => 1000, 'enabled' => true]);
        $order = $this->order(20);
        $this->assertNotSame($max->id, $order->farm_printer_id);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $other = collect($state['colors'])->first(fn ($c) => ! $c['sliced'] && $c['enough']);
        $this->assertNotNull($other, 'the farm of the test offers more than one colour');

        $after = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'slot' => $other['slot']])->assertOk()->json();
        $this->assertSame($other['slot'], $after['slot']);
        $order->refresh();
        $this->assertSame($other['slot'], $order->farm_printer_slot_id);
        $this->assertSame($max->id, $order->farm_printer_id, 'the order moved to the machine that holds the colour');
        $this->assertSame('PLA+', $order->material->code);
        $this->assertSame('kobra-3-max-01', $order->slice_params['printer']['key']);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->assertSame([$other['slot']], collect($state['colors'])->where('sliced', true)->pluck('slot')->all());
    }

    /** A turtle with joints prints in place: the customer switches the supports off and the slicer is told so. */
    public function test_the_customer_can_switch_supports_off_and_back(): void
    {
        $order = $this->order(20, ['supports' => 'off']);
        $this->assertSame('off', $order->supports);
        $this->assertSame('0', $order->slice_params['overrides']['process']['enable_support']);

        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard'])->assertOk()->assertJsonPath('supports_mode', 'off');
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'supports' => 'auto'])->assertOk()->assertJsonPath('supports_mode', 'auto');
        $this->assertNotSame('0', $order->refresh()->slice_params['overrides']['process']['enable_support'] ?? null);
        $this->actingAs($this->user)->get('/farm/orders/'.$order->token.'/repeat')->assertRedirect();
        $this->actingAs($this->user)->get('/farm?supports=off&lang=cs')->assertOk()->assertSee('Bez podpěr');
    }

    public function test_several_copies_print_on_one_plate_and_more_than_a_plate_takes_prints_plate_after_plate(): void
    {
        // 4 cubes of 20 mm: a 2 × 2 grid 5 mm apart, one print, one price for all four
        $order = $this->order(20.0, ['copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertSame(4, $order->copies);
        $this->assertSame(48, iterator_count(StlFile::triangles($order->absolutePrintStlPath())));
        $this->assertEqualsWithDelta(45.0, $order->check['dims']['x'], 0.01);
        $this->assertEqualsWithDelta(45.0, $order->check['dims']['y'], 0.01);
        $this->assertEqualsWithDelta(20.0, $order->check['piece_dims']['x'], 0.01);
        $this->assertSame(4, $order->slice_params['copies']);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame(4, $state['copies']);
        $this->assertGreaterThanOrEqual(4, $state['max_copies']);
        $this->assertNotEmpty($state['colors']);

        // the customer changes the number of pieces like any other preset: sliced again
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'copies' => 2])->assertOk()->assertJsonPath('copies', 2);
        $this->assertSame(24, iterator_count(StlFile::triangles($order->refresh()->absolutePrintStlPath())));

        // more than the plate takes: full plates one after another, the last one with what is left (4 + 4 + 1)
        $one = $this->order(100.0);
        $many = $this->order(100.0, ['copies' => 9]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $many->status);
        $this->assertSame([3, 4, 1], [$many->plates, $many->plate_copies, $many->rest_copies]);
        $this->assertNotNull($many->rest_gcode_path);
        $this->assertFileExists($many->absoluteGcodePath(3));
        $this->assertNotSame($many->absoluteGcodePath(1), $many->absoluteGcodePath(3));
        $this->assertSame(48, iterator_count(StlFile::triangles($many->absolutePrintStlPath())));                 // the full plate: 4 cubes
        $this->assertGreaterThan($one->est_minutes * 8, $many->est_minutes, 'two full plates and a single cube');
        $this->assertLessThan($one->est_minutes * 9.5, $many->est_minutes);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$many->token}/status")->assertOk()->json();
        $this->assertSame(4, $state['max_copies']);                                     // 250 mm plate minus margins: 2 × 2 cubes of 100 mm
        $this->assertSame([4, 4, 1], $state['plate_layout']);

        // the operator finishes plate after plate on the manual printer: the order returns to the queue twice, then it is done
        FarmPrinter::firstOrFail()->slots()->where('slot', 2)->update(['remaining_g' => 50000]);   // nine big cubes need more than one spool
        foreach ([20000, 20000, 20000] as $amount) {
            $this->credit($amount);
        }
        $this->pay($many)->assertOk();
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $many->refresh();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $many->status);
        foreach ([1, 2, 3] as $plate) {
            $this->actingAs($admin)->post("/admin/farm/orders/{$many->token}/status", ['to' => FarmOrder::STATUS_PRINTING])->assertRedirect();
            $this->assertSame($plate, $many->refresh()->latestJob()->plate);                 // the job of this plate, not the first one
            $this->actingAs($admin)->post("/admin/farm/orders/{$many->token}/status", ['to' => FarmOrder::STATUS_DONE])->assertRedirect();
            $many->refresh();
            $this->assertSame($plate, $many->plates_done);
            $this->assertSame($plate < 3 ? FarmOrder::STATUS_QUEUED : FarmOrder::STATUS_DONE, $many->status, "after plate {$plate}");
        }
        $this->assertStringContainsString('plate 2/3', $many->events->pluck('note')->implode(' '));

        // the calculator hands its quantity over to the start page
        $file = $this->upload(20.0);
        $calc = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $file, 'material' => 'PLA', 'quantity' => 6])->assertCreated()->json('calculation.token');
        $this->actingAs($this->user)->get('/farm?calc='.$calc)->assertOk()->assertSee('name="copies"', false)->assertSee('value="6"', false);
    }

    public function test_the_size_and_the_colour_chosen_before_the_order_decide_the_piece_and_the_machine(): void
    {
        // the seeder's Kobra 3 Max with light blue PLA+ in slot 1; the S1 holds white
        $max = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $blue = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'PLA+'))->where('name', 'světle modrá')->firstOrFail();
        $max->slots()->where('slot', 0)->update(['farm_color_id' => $blue->id, 'remaining_g' => 1000, 'enabled' => true]);

        // half size: a 20 mm cube prints as 10 mm, and the factor travels with the order
        $half = $this->order(20.0, ['scale' => 0.5]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $half->status);
        $this->assertEqualsWithDelta(0.5, $half->scale, 0.001);
        $this->assertEqualsWithDelta(10.0, $half->check['dims']['x'], 0.01);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$half->token}/status")->assertOk()->json();
        $this->assertEqualsWithDelta(0.5, $state['scale'], 0.001);
        $this->assertEqualsWithDelta(20.0, $state['raw_bbox']['x'], 0.01);
        $this->assertSame('Kobra S1 #1', $state['printer']['name']);

        // a colour chosen up front sends the order to the machine that holds it: blue lives only in the Max
        $chosen = $this->order(20.0, ['color' => $blue->id]);
        $this->assertSame($max->id, $chosen->farm_printer_id);
        $this->assertSame($blue->id, $chosen->farm_color_id);
        $this->assertNotNull($chosen->farm_printer_slot_id);
        $this->assertSame('PLA+', $chosen->material->code);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$chosen->token}/status")->assertOk()->json();
        $this->assertSame($chosen->farm_printer_slot_id, $state['slot']);

        // the start page offers the loaded colours and carries size and copies over from the calculation
        $file = $this->upload(20.0);
        $calc = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $file, 'material' => 'PLA', 'quantity' => 3, 'scale' => 1.5])->assertCreated()->json('calculation.token');
        $page = $this->actingAs($this->user)->get('/farm?calc='.$calc)->assertOk();
        $page->assertSee('name="color"', false)->assertSee('name="scale"', false)->assertSee('value="1.5"', false)->assertSee('value="3"', false)->assertSee('light blue');   // test locale is en

        // "print again": the start page with the order's settings; the colour is preselected when it is still loaded
        $again = $this->actingAs($this->user)->get("/farm/orders/{$chosen->token}/repeat")->assertRedirect();
        $target = $again->headers->get('Location');
        $this->assertStringContainsString('file='.$chosen->modelFile->uuid, $target);
        $this->assertStringContainsString('color='.$blue->id, $target);
        $this->actingAs($this->user)->get($target)->assertOk()->assertSee('value="'.$blue->id.'" class="sr-only" checked', false);
        $max->slots()->where('slot', 0)->update(['enabled' => false]);                     // the spool is gone: another colour is offered first, nothing breaks
        $this->actingAs($this->user)->get($target)->assertOk()->assertDontSee('value="'.$blue->id.'"', false);

        // resizing on the order page is a preset like the others: sliced again at the new size
        $this->actingAs($this->user)->postJson("/farm/orders/{$half->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'scale' => 2])->assertOk()->assertJsonPath('scale', 2);
        $this->assertEqualsWithDelta(40.0, $half->refresh()->check['dims']['x'], 0.01);
    }

    public function test_model_bigger_than_the_plate_is_refused_with_a_reason(): void
    {
        $order = $this->order(300);
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $this->assertSame('exceeds_bed', $order->error);
        $this->assertNull($order->gcode_path);
    }

    public function test_model_with_a_hole_is_refused_when_it_cannot_be_repaired(): void
    {
        $uuid = $this->upload(writer: function (string $path) {
            MeshFixtures::cubeStl($path.'.full', 20);
            $fh = StlFile::beginBinary($path);
            $n = 0;
            foreach (StlFile::triangles($path.'.full') as $i => [$a, $b, $c]) {
                if ($i >= 2) {   // drop the two bottom triangles
                    StlFile::writeTriangle($fh, $a, $b, $c);
                    $n++;
                }
            }
            StlFile::endBinary($fh, $n);
        });
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();

        $this->assertSame('not_watertight', $order->error);   // the PHP fallback checks but cannot repair
        $this->assertSame(4, $order->check['errors'][0]['data']['open_edges']);
    }

    public function test_paying_needs_credit_and_the_webhook_is_the_only_way_to_get_it(): void
    {
        $order = $this->order();

        $this->pay($order)->assertStatus(402)->assertJsonPath('error', 'credit');
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->refresh()->status);

        // a forged webhook credits nothing
        $this->actingAs($this->user)->post('/account/credit', ['amount' => 500]);
        $ref = Payment::latest('id')->value('gateway_ref');
        $this->postJson('/webhooks/payments/fake', ['ref' => $ref, 'paid' => true], ['X-Fake-Signature' => 'nope'])->assertStatus(400);
        $this->assertSame(0.0, app(Wallet::class)->balance($this->user));

        $this->postJson('/webhooks/payments/fake', ['ref' => $ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->postJson('/webhooks/payments/fake', ['ref' => $ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->assertSame(500.0, app(Wallet::class)->balance($this->user));
    }

    public function test_terms_must_be_accepted_and_a_changed_price_is_never_charged_silently(): void
    {
        $order = $this->order();
        $this->credit(1000);

        $this->pay($order, ['terms' => false])->assertStatus(422);
        $this->pay($order, ['expected_total' => 1])->assertStatus(422)->assertJsonPath('error', 'price_changed');
        $this->assertSame(1000.0, app(Wallet::class)->balance($this->user));
    }

    public function test_manual_printer_order_is_paid_queued_and_the_operator_is_told(): void
    {
        $order = $this->order();
        $this->credit(1000);

        $this->pay($order)->assertOk()->assertJsonPath('status', 'queued');
        $order->refresh();
        $this->assertMatchesRegularExpression('/^F\d{2}-000001$/', $order->number);
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertSame(round(1000 - $order->price_total, 2), app(Wallet::class)->balance($this->user));
        Mail::assertQueued(FarmAdminAlert::class);
        Mail::assertQueued(FarmOrderStatus::class, fn ($m) => $m->status === 'queued');

        // the customer changes their mind before the print: everything comes back
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(1000.0, app(Wallet::class)->balance($this->user));
    }

    public function test_approval_mode_holds_the_order_until_an_admin_lets_it_through(): void
    {
        app(FarmSettings::class)->set('require_approval', true);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order)->assertOk()->assertJsonPath('status', 'paid');

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/approve")->assertRedirect();
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->refresh()->status);
    }

    public function test_agent_prints_only_after_the_plate_was_declared_clear_and_reports_back(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth)->assertOk();                         // printer online before the customer comes
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order)->assertOk()->assertJsonPath('status', 'queued');

        // plate not confirmed: nothing starts, however idle the printer is
        $this->sync($auth)->assertOk()->assertJsonCount(0, 'commands');
        $this->assertSame(0, FarmPrintJob::count());

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/printers/{$printer->id}/bed", ['clear' => 1])->assertRedirect();
        $this->assertFalse($printer->refresh()->bed_clear, 'starting consumes the confirmation');

        $cmd = $this->sync($auth)->assertOk()->assertJsonCount(1, 'commands')->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $job = FarmPrintJob::findOrFail($cmd['job_id']);
        $this->assertSame('sent', $job->status);

        $gcode = $this->get($cmd['payload']['gcode_url'], $auth)->assertOk();
        // the customer's colour sits in slot 3: the agent gets the copy that selects it, with its own checksum
        $this->assertSame(2, $cmd['payload']['slot']);
        $this->assertSame(hash('sha256', $gcode->streamedContent()), $gcode->headers->get('X-Content-Sha256'));
        $this->assertStringContainsString('
T2 ; slot chosen by matplace farm
', $gcode->streamedContent());
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();

        $this->sync($auth, 'printing', ['id' => $job->id, 'status' => 'printing', 'progress' => 42.5, 'print_duration' => 600, 'filament_used' => 1200])->assertOk();
        $this->assertSame(FarmOrder::STATUS_PRINTING, $order->refresh()->status);
        $this->assertSame(42.5, $job->refresh()->progress);

        $this->sync($auth, 'idle', ['id' => $job->id, 'status' => 'done', 'progress' => 100, 'print_duration' => 2400, 'filament_used' => 3600])->assertOk();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_DONE, $order->status);
        $this->assertSame(40, $order->actual_minutes);
        $this->assertEqualsWithDelta(10.7, $order->actual_grams, 0.1);   // 3.6 m of 1.75 mm PLA
        $this->assertSame('agent', $order->actual_source);
        $this->assertDatabaseHas('credit_transactions', ['farm_order_id' => $order->id, 'type' => 'capture']);
        $this->assertEqualsWithDelta(900 - $order->actual_grams, $order->slot->refresh()->remaining_g, 0.01);   // the seeded spool holds 900 g

        // the plate is full now: the next order waits for a person
        $this->sync($auth)->assertJsonCount(0, 'commands');
    }

    public function test_a_print_cancelled_part_way_is_paid_for_as_far_as_it_got(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth)->assertOk();
        $order = $this->order();
        $this->credit(1000);
        $paid = $this->pay($order)->assertOk()->json();
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/printers/{$printer->id}/bed", ['clear' => 1])->assertRedirect();
        $cmd = $this->sync($auth)->json('commands.0');
        $this->postJson("/api/agent/commands/{$cmd['id']}/result", ['ok' => true], $auth)->assertOk();
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 40, 'print_duration' => 600, 'filament_used' => 1200])->assertOk();

        // the customer stops it at 40 %: the page told them the price of the printed part first
        $p = $order->refresh()->price;
        $expected = min($p['print_total'], ceil(($p['fixed'] + 0.4 * ($p['time'] + $p['material'])) * (1 + $p['inputs']['vat_percent'] / 100)));
        $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertJsonPath('cancel_keep', (int) $expected);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $order->refresh();
        $p = $order->price;
        $this->assertGreaterThan(0, $expected);
        $this->assertLessThan($paid['total'], $expected);
        $this->assertEqualsWithDelta($expected, $p['charged_on_cancel'], 0.001);
        $this->assertEqualsWithDelta(1000 - $expected, app(Wallet::class)->balance($this->user), 0.001);
        $this->assertDatabaseHas('credit_transactions', ['farm_order_id' => $order->id, 'type' => 'capture', 'note' => (string) $expected]);
        $this->assertSame('cancel', FarmCommand::latest('id')->first()->type, 'the printer is told to stop');
        // the admin's refund button can still hand the kept part back, on purpose
        $this->assertEqualsWithDelta($expected, app(Wallet::class)->giveBack($order), 0.001);
        $this->assertEqualsWithDelta(1000, app(Wallet::class)->balance($this->user), 0.001);
        $this->assertSame(0.0, app(Wallet::class)->giveBack($order), 'and only once');

        // an order cancelled before anything printed costs nothing
        $other = $this->order();
        $this->pay($other)->assertOk();
        $this->actingAs($this->user)->postJson("/farm/orders/{$other->token}/cancel")->assertOk();
        $this->assertEqualsWithDelta(1000, app(Wallet::class)->balance($this->user), 0.001);
    }

    public function test_failed_print_returns_the_credit_and_alerts_the_admin(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order);
        $printer->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');

        $this->sync($auth, 'error', ['id' => $cmd['job_id'], 'status' => 'failed', 'message' => 'Heater extruder not heating']);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_FAILED, $order->status);
        $this->assertSame('print_failed', $order->error);
        $this->assertSame(1000.0, app(Wallet::class)->balance($this->user));
        Mail::assertQueued(FarmAdminAlert::class);
    }

    public function test_agent_api_needs_a_valid_token_and_sees_only_its_own_printers(): void
    {
        $this->postJson('/api/agent/sync', ['printers' => []])->assertUnauthorized();
        $this->postJson('/api/agent/sync', ['printers' => []], ['Authorization' => 'Bearer mpa_wrong'])->assertUnauthorized();

        [, $token] = FarmAgent::issue('stranger');   // a valid agent that owns no printer
        $this->sync(['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonCount(0, 'printers');
        $this->assertNull(FarmPrinter::firstOrFail()->last_seen_at);
    }

    public function test_silent_agent_turns_the_printer_offline_and_the_job_unknown(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth);
        $order = $this->order();
        $this->credit(1000);
        $this->pay($order);
        $printer->update(['bed_clear' => true]);
        $cmd = $this->sync($auth)->json('commands.0');
        $this->sync($auth, 'printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 10]);

        $this->travel(5)->minutes();
        $this->artisan('farm:watch')->assertSuccessful();

        $this->assertFalse($printer->refresh()->isOnline());
        $this->assertSame('unknown', FarmPrintJob::findOrFail($cmd['job_id'])->status);
        $this->assertSame(FarmOrder::STATUS_PRINTING, $order->refresh()->status, 'nobody knows yet: the order is not failed by a lost connection alone');
        Mail::assertQueued(FarmAdminAlert::class, fn ($m) => str_contains($m->subjectLine, 'farm.admin.mail.offline') || str_contains($m->subjectLine, $printer->name));
    }

    public function test_daily_limit_stops_further_slicing(): void
    {
        app(FarmSettings::class)->set('daily_slices_per_user', 1);
        $this->order();
        $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $this->upload()])->assertStatus(422)->assertJsonPath('error', 'daily_limit');
    }

    public function test_somebody_elses_order_does_not_exist_for_me(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create())->getJson("/farm/orders/{$order->token}/status")->assertNotFound();
    }
}
