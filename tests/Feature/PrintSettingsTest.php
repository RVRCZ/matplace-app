<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\PrintSettings;
use App\Domain\Farm\Wallet;
use App\Models\FarmOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Print settings beyond the presets: a customer writes the numbers of a drawing (infill, perimeters, layers), the
 * admin writes any slicer setting onto one order. Both reach the slicer as overrides of the process profile.
 */
class PrintSettingsTest extends TestCase
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

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_ps_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 60.0);   // big enough for the price to be above the minimum
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'block.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function state(FarmOrder $order): array
    {
        return $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    public function test_a_customer_writes_the_numbers_of_a_drawing_and_the_slicer_gets_them(): void
    {
        $order = $this->order();
        $before = $this->state($order);
        $this->assertSame('sliced', $before['status']);
        $this->assertNull($before['settings']);
        $this->assertSame(15, $order->fresh()->slice_params['infill_percent']);
        $this->assertArrayNotHasKey('wall_loops', $order->fresh()->slice_params['overrides']['process']);

        // 20 % infill, 3 perimeters, 5 top layers: as the drawing says, whatever the presets are
        $reslice = ['quality' => 'standard', 'strength' => 'standard', 'settings' => ['infill' => 20, 'walls' => 3, 'top' => 5, 'bottom' => '']];
        $after = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $reslice)->assertOk()->json();
        $this->assertSame(['infill' => 20, 'walls' => 3, 'top' => 5], $after['settings']);
        $order->refresh();
        $this->assertSame('sliced', $order->status);
        $this->assertSame(20, $order->slice_params['infill_percent']);
        $this->assertSame(['3', '5'], [$order->slice_params['overrides']['process']['wall_loops'], $order->slice_params['overrides']['process']['top_shell_layers']]);
        $this->assertArrayNotHasKey('bottom_shell_layers', $order->slice_params['overrides']['process']);
        // more plastic costs more: the estimate and the price follow the infill
        $dense = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'settings' => ['infill' => 80]])->assertOk()->json();
        $this->assertGreaterThan($before['grams'], $dense['grams']);
        $this->assertGreaterThan($before['total'], $dense['total']);
        $this->assertSame(['infill' => 80], $dense['settings']);
        $this->assertSame(['infill' => 80], $order->fresh()->print_settings);

        // outside the range the form refuses; an empty set of settings goes back to the presets
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'settings' => ['walls' => 9]])->assertStatus(422);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'settings' => ['infill' => 2]])->assertStatus(422);
        $back = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'settings' => []])->assertOk()->json();
        $this->assertNull($back['settings']);
        $this->assertSame(15, $order->fresh()->slice_params['infill_percent']);
        // the page has the fields
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee('data-setting="walls"', false)->assertSee(__('farm.advanced.label'));

        // the cleaning itself
        $this->assertSame(['infill' => 100, 'walls' => 1], PrintSettings::clean(['infill' => 250, 'walls' => -2, 'top' => '', 'nozzle' => 0.6]));
        $this->assertNull(PrintSettings::clean(['top' => null]));
    }

    public function test_the_admin_writes_slicer_settings_onto_one_order_and_they_win(): void
    {
        $admin = User::factory()->create();
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin']);
        $order = $this->order();
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'settings' => ['walls' => 3]])->assertOk();

        // not JSON, or keys that are not slicer settings: refused with a reason, nothing changes
        $this->actingAs($admin)->from('/admin/farm/orders/'.$order->token)->post("/admin/farm/orders/{$order->token}/overrides", ['overrides' => 'wall_loops=4'])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($admin)->from('/admin/farm/orders/'.$order->token)->post("/admin/farm/orders/{$order->token}/overrides", ['overrides' => '{"Wall Loops": "4", "ok": "1"}'])->assertSessionHas('error');
        $this->assertNull($order->fresh()->admin_overrides);

        // the admin's numbers go over the customer's: 4 walls and gyroid, sliced again at once
        $this->actingAs($admin)->from('/admin/farm/orders/'.$order->token)->post("/admin/farm/orders/{$order->token}/overrides", ['overrides' => '{"wall_loops": 4, "sparse_infill_pattern": "gyroid"}'])->assertSessionHas('status');
        $order->refresh();
        $this->assertSame(['wall_loops' => '4', 'sparse_infill_pattern' => 'gyroid'], $order->admin_overrides);
        $this->assertSame('sliced', $order->status);
        $this->assertSame(['4', 'gyroid'], [$order->slice_params['overrides']['process']['wall_loops'], $order->slice_params['overrides']['process']['sparse_infill_pattern']]);
        $this->assertSame(['wall_loops' => '4', 'sparse_infill_pattern' => 'gyroid'], $this->state($order)['admin_overrides']);
        $this->actingAs($admin)->get('/admin/farm/orders/'.$order->token)->assertOk()->assertSee('Přepisy nastavení sliceru')->assertSee('&quot;wall_loops&quot;:&quot;4&quot;', false)->assertSee('Zákazník zadal');

        // the customer's own change later keeps the admin's overrides on top
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'settings' => ['walls' => 2]])->assertOk();
        $this->assertSame('4', $order->fresh()->slice_params['overrides']['process']['wall_loops']);

        // cleared: the customer's 2 walls apply again
        $this->actingAs($admin)->from('/admin/farm/orders/'.$order->token)->post("/admin/farm/orders/{$order->token}/overrides", ['overrides' => ''])->assertSessionHas('status');
        $order->refresh();
        $this->assertNull($order->admin_overrides);
        $this->assertSame('2', $order->slice_params['overrides']['process']['wall_loops']);

        // once the order stands in the queue, the printer may be fetching its G-code: no more overrides
        app(Wallet::class)->adjust($this->user, 2000, 'test', $admin->id);
        $state = $this->state($order);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total']])->assertOk();
        $this->assertSame('queued', $order->fresh()->status);
        $this->actingAs($admin)->from('/admin/farm/orders/'.$order->token)->post("/admin/farm/orders/{$order->token}/overrides", ['overrides' => '{"wall_loops": "5"}'])->assertSessionHas('error');
        $this->assertNull($order->fresh()->admin_overrides);
        $this->actingAs($admin)->get('/admin/farm/orders/'.$order->token)->assertOk()->assertSee('jen před zařazením');

        // a paid order that waits for the admin's approval can still be changed (nothing printed yet)
        app(FarmSettings::class)->set('require_approval', true);
        $second = $this->order();
        $state = $this->state($second);
        $this->actingAs($this->user)->postJson("/farm/orders/{$second->token}/pay", ['slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total']])->assertOk();
        $this->assertSame('paid', $second->fresh()->status);
        $this->actingAs($admin)->from('/admin/farm/orders/'.$second->token)->post("/admin/farm/orders/{$second->token}/overrides", ['overrides' => '{"wall_loops": "3"}'])->assertSessionHas('status');
        $second->refresh();
        $this->assertSame(['3', (float) $state['colors'][0]['total']], [$second->slice_params['overrides']['process']['wall_loops'], (float) $second->price_total], 'sliced again, the paid price untouched');
    }
}
