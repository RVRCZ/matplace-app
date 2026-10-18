<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Glow-in-the-dark filament snaps in the filament changer (F26-000065 cancelled; Roman, 14 Oct 2026): a kind marked
 * "only from a directly fed spool" goes only into the slots of a printer in manual mode, where a person feeds the
 * spool, is offered only from there, and the operator sees a badge on the order and the dashboard saying why the
 * print goes by hand. The customer sees nothing new.
 */
class FarmManualOnlyKindsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private FarmMaterial $glow;

    private FarmColor $glowColor;

    /** the seeded machines: two in manual mode, one run by the agent */
    private FarmPrinter $manual;

    private FarmPrinter $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        $this->glow = FarmMaterial::where('finish', 'luminous')->firstOrFail();
        $this->glowColor = $this->glow->colors()->firstOrFail();
        $this->glowColor->update(['enabled' => true, 'hex' => '#B5F57A']);
        $this->manual = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $this->agent = FarmPrinter::where('key', 'kobra-s1-02')->firstOrFail();
        $this->agent->update(['enabled' => true, 'last_seen_at' => now(), 'farm_agent_id' => FarmAgent::issue('farm')[0]->id]);
        FarmPrinter::where('key', 'kobra-3-max-01')->update(['enabled' => false]);
    }

    private function printerForm(FarmPrinter $printer, array $slots, array $over = []): array
    {
        return $over + $printer->only(['name', 'model', 'key', 'mode', 'farm_agent_id', 'bed_x', 'bed_y', 'bed_z', 'nozzle_mm', 'machine_profile', 'time_factor', 'weight_factor'])
            + ['enabled' => 1, 'process_profiles' => json_encode($printer->process_profiles), 'slots' => $slots];
    }

    private function slotRows(FarmPrinter $printer): array
    {
        return $printer->slots()->get()->keyBy('slot')->map(fn (FarmPrinterSlot $s) => ['color' => $s->farm_color_id, 'remaining_g' => $s->remaining_g, 'enabled' => $s->enabled ? 1 : 0])->all();
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_glow_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
    }

    /** the colour ids on offer for an order, as the order page reads them (one entry per colour, named by its spool) */
    private function offered(FarmOrder $order): array
    {
        $slots = array_column($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('colors'), 'slot');

        return FarmPrinterSlot::whereIn('id', $slots)->pluck('farm_color_id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_the_kind_carries_the_flag_from_the_admin_form(): void
    {
        $this->assertFalse($this->glow->isManualOnly(), 'a kind starts without the flag');
        $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk()->assertSee('name="manual_only"', false)->assertSee(__('farm.admin.manual_only_hint'));

        $form = $this->glow->only(['code', 'finish', 'name', 'manufacturer', 'filament_profile', 'density', 'price_per_gram']) + ['enabled' => 1];
        $this->actingAs($this->admin)->post("/admin/farm/materials/{$this->glow->id}", $form + ['manual_only' => 1])->assertRedirectContains('/admin/farm/materials');
        $this->assertTrue($this->glow->fresh()->isManualOnly());
        $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk()->assertSee($this->glow->label().' · '.$this->glow->colors()->count().' barev · '.__('farm.admin.manual_only_short'));
        // a copy of the kind keeps the flag; the box left empty takes it away
        $this->assertTrue($this->glow->fresh()->copy()->isManualOnly());
        $this->actingAs($this->admin)->post("/admin/farm/materials/{$this->glow->id}", $form)->assertRedirectContains('/admin/farm/materials');
        $this->assertFalse($this->glow->fresh()->isManualOnly());
    }

    public function test_a_colour_of_such_a_kind_goes_only_into_a_printer_in_manual_mode(): void
    {
        $this->glow->update(['manual_only' => true]);
        $refused = __('farm.admin.manual_only_refused', ['color' => $this->glowColor->fullLabel(), 'printer' => $this->agent->name]);

        // the printer's own page: refused on the agent's machine, the slot stays as it was
        $rows = $this->slotRows($this->agent);
        $rows[0] = ['color' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => 1];
        $this->actingAs($this->admin)->from("/admin/farm/printers/{$this->agent->id}")->post("/admin/farm/printers/{$this->agent->id}", $this->printerForm($this->agent, $rows))
            ->assertRedirect("/admin/farm/printers/{$this->agent->id}")->assertSessionHasErrors(['slots.0.color' => $refused]);
        $this->assertNull($this->agent->slots()->where('slot', 0)->firstOrFail()->farm_color_id);
        $this->actingAs($this->admin)->get("/admin/farm/printers/{$this->agent->id}")->assertOk()->assertSee(__('farm.admin.manual_only_short'));
        // a spool typed in at the slot as a new colour of that kind is not even created
        $rows[0] = ['color' => 'new', 'remaining_g' => 800, 'enabled' => 1, 'new' => ['name' => 'svítivá modrá', 'material' => $this->glow->id]];
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->agent->id}", $this->printerForm($this->agent, $rows))->assertSessionHasErrors('slots.0.color');
        $this->assertDatabaseMissing('farm_colors', ['name' => 'svítivá modrá']);

        // accepted on a manual machine, offered at once
        $rows = $this->slotRows($this->manual);
        $rows[0] = ['color' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => 1];
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->manual->id}", $this->printerForm($this->manual, $rows))->assertRedirect('/admin/farm/printers')->assertSessionHasNoErrors()->assertSessionMissing('error');
        $slot = $this->manual->slots()->where('slot', 0)->firstOrFail();
        $this->assertSame([$this->glowColor->id, true], [$slot->farm_color_id, $slot->enabled]);
        $this->assertTrue($slot->kindFitsPrinter());

        // the page of all machines: the same rule per slot, nothing of the page saved when one slot is refused
        $this->actingAs($this->admin)->get('/admin/farm/printers/load')->assertOk()->assertSee(__('farm.admin.manual_only_short'))->assertSee(__('farm.admin.mode.agent'));
        $this->actingAs($this->admin)->from('/admin/farm/printers/load')->post('/admin/farm/printers/load', ['slots' => [
            $this->manual->id => [0 => ['color' => $this->glowColor->id, 'remaining_g' => 700, 'enabled' => 1]],
            $this->agent->id => [1 => ['color' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => 1]],
        ]])->assertRedirect('/admin/farm/printers/load')->assertSessionHasErrors(["slots.{$this->agent->id}.1.color" => $refused]);
        $this->assertSame(800.0, $this->manual->slots()->where('slot', 0)->firstOrFail()->remaining_g, 'the manual machine was not saved either');
        $this->assertNull($this->agent->slots()->where('slot', 1)->firstOrFail()->farm_color_id);
        $this->actingAs($this->admin)->post('/admin/farm/printers/load', ['slots' => [$this->manual->id => [0 => ['color' => $this->glowColor->id, 'remaining_g' => 700, 'enabled' => 1]]]])->assertRedirect('/admin/farm/printers')->assertSessionHasNoErrors();
        $this->assertSame(700.0, $this->manual->slots()->where('slot', 0)->firstOrFail()->remaining_g);
    }

    public function test_switching_a_printer_to_the_agent_switches_such_a_slot_off_and_says_so(): void
    {
        $this->glow->update(['manual_only' => true]);
        $this->manual->slots()->where('slot', 0)->update(['farm_color_id' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => true]);
        $agentId = $this->agent->farm_agent_id;
        $rows = $this->slotRows($this->manual);
        $this->assertSame(1, $rows[0]['enabled']);

        // manual → agent with the glow spool still ticked: saved, the slot switched off and kept, the notice names it
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->manual->id}", $this->printerForm($this->manual, $rows, ['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agentId]))
            ->assertRedirect('/admin/farm/printers')->assertSessionHasNoErrors()
            ->assertSessionHas('error', __('farm.admin.manual_only_disabled', ['n' => 1, 'color' => $this->glowColor->fullLabel(), 'printer' => $this->manual->name]));
        $this->manual->refresh();
        $slot = $this->manual->slots()->where('slot', 0)->firstOrFail();
        $this->assertSame([FarmPrinter::MODE_AGENT, $this->glowColor->id, false, 800.0], [$this->manual->mode, $slot->farm_color_id, $slot->enabled, $slot->remaining_g]);
        $this->assertFalse($slot->kindFitsPrinter());
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee('line-through');
        // the white spool of the same machine stays offered
        $this->assertTrue($this->manual->slots()->where('slot', 2)->firstOrFail()->enabled);

        // saved again with the slot left unticked: nothing to say
        $rows = $this->slotRows($this->manual);
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->manual->id}", $this->printerForm($this->manual, $rows))->assertRedirect('/admin/farm/printers')->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertFalse($this->manual->slots()->where('slot', 0)->firstOrFail()->enabled);
        // back to manual with the tick: offered again, no notice
        $rows[0]['enabled'] = 1;
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$this->manual->id}", $this->printerForm($this->manual, $rows, ['mode' => FarmPrinter::MODE_MANUAL]))->assertRedirect('/admin/farm/printers')->assertSessionMissing('error');
        $this->assertTrue($this->manual->slots()->where('slot', 0)->firstOrFail()->enabled);
    }

    public function test_the_colour_is_offered_only_while_it_sits_in_a_manual_printer(): void
    {
        $this->glow->update(['manual_only' => true]);
        $white = $this->manual->slots()->where('slot', 2)->firstOrFail()->color;
        $order = $this->order();
        $this->assertSame([$white->id], $this->offered($order));

        // loaded straight into the agent's machine (data from before the flag): not offered, though the machine is
        $black = FarmColor::where('farm_material_id', $white->farm_material_id)->where('id', '!=', $white->id)->firstOrFail();
        $black->update(['enabled' => true, 'hex' => '#101012']);
        $this->agent->slots()->where('slot', 0)->update(['farm_color_id' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => true]);
        $this->agent->slots()->where('slot', 1)->update(['farm_color_id' => $black->id, 'remaining_g' => 800, 'enabled' => true]);
        $this->assertEqualsCanonicalizing([$white->id, $black->id], $this->offered($order));
        $this->assertEqualsCanonicalizing([$white->id, $black->id], app(OrderService::class)->offeredColors()->pluck('color.id')->all(), 'the start page says the same');
        // as a second colour next to the black of that machine: left out too
        $blackSlot = $this->agent->slots()->where('slot', 1)->firstOrFail();
        $this->assertSame([], app(OrderService::class)->secondSpools($blackSlot)->pluck('farm_color_id')->all());
        $this->assertSame([], app(OrderService::class)->secondSpoolsAcross($blackSlot)->pluck('farm_color_id')->all());

        // in a manual machine: offered, with that machine's code; as a second colour next to its white as well
        $this->manual->slots()->where('slot', 0)->update(['farm_color_id' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => true]);
        $this->assertEqualsCanonicalizing([$white->id, $black->id, $this->glowColor->id], $this->offered($order));
        $colors = collect($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json('colors'));
        $this->assertSame([$this->manual->shortCode().'1'], $colors->firstWhere('slot', $this->manual->slots()->where('slot', 0)->value('id'))['codes']);
        $whiteSlot = $this->manual->slots()->where('slot', 2)->firstOrFail();
        $this->assertSame([$this->glowColor->id], app(OrderService::class)->secondSpools($whiteSlot)->pluck('farm_color_id')->all());
        // without the flag the agent's machine offers it like any other spool
        $this->glow->update(['manual_only' => false]);
        $this->manual->slots()->where('slot', 0)->update(['farm_color_id' => null, 'enabled' => false]);
        $this->assertEqualsCanonicalizing([$white->id, $black->id, $this->glowColor->id], $this->offered($order));
    }

    public function test_the_order_and_the_dashboard_wear_the_badge_and_the_customer_sees_nothing_new(): void
    {
        $this->glow->update(['manual_only' => true]);
        $this->manual->slots()->where('slot', 0)->update(['farm_color_id' => $this->glowColor->id, 'remaining_g' => 800, 'enabled' => true]);
        $order = $this->order();
        $this->assertFalse($order->manualOnly(), 'no colour chosen yet');

        $this->actingAs($this->user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
        $glow = collect($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json('colors'))->firstWhere('slot', $this->manual->slots()->where('slot', 0)->value('id'));
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $glow['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $glow['total']])->assertOk();
        $order->refresh();
        $this->assertSame($this->glowColor->id, $order->farm_color_id);
        $this->assertTrue($order->manualOnly());

        $badge = __('farm.admin.manual_only_badge');
        $this->actingAs($this->admin)->get("/admin/farm/orders/{$order->token}")->assertOk()->assertSee('data-manual-only', false)->assertSee($badge);
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee($order->number)->assertSee('data-manual-only', false)->assertSee($badge);
        // the customer's page and state carry no such word
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertDontSee($badge)->assertDontSee('data-manual-only', false);
        $this->assertStringNotContainsString('manual', json_encode($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json('spools')));

        // an order in another colour wears nothing
        $white = $this->manual->slots()->where('slot', 2)->firstOrFail();
        $other = $this->order();
        $other->update(['farm_color_id' => $white->farm_color_id, 'farm_printer_slot_id' => $white->id]);
        $this->assertFalse($other->fresh()->manualOnly());
        $this->actingAs($this->admin)->get("/admin/farm/orders/{$other->token}")->assertOk()->assertDontSee('data-manual-only', false);
    }

    public function test_every_text_is_said_in_the_three_languages(): void
    {
        foreach (['cs', 'en', 'es'] as $locale) {
            app()->setLocale($locale);
            foreach (['manual_only', 'manual_only_hint', 'manual_only_short', 'manual_only_slots_hint', 'manual_only_refused', 'manual_only_disabled', 'manual_only_badge'] as $key) {
                $this->assertNotSame('farm.admin.'.$key, __('farm.admin.'.$key), "$locale $key");
            }
            $this->assertStringContainsString('X1', __('farm.admin.manual_only_refused', ['color' => 'c', 'printer' => 'X1']));
            $this->assertStringContainsString('3', __('farm.admin.manual_only_disabled', ['n' => 3, 'color' => 'c', 'printer' => 'X1']));
        }
    }
}
