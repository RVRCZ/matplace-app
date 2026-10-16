<?php

namespace Tests\Feature;

use App\Mail\FarmOrderStatus;
use App\Models\FarmColor;
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
 * The colours of an order are chosen once, on the order page: one list of what is loaded and printable right now, in
 * the order of the colour window, every colour with the codes of its spools (U33 = farm U, printer 3, slot 3); the
 * same codes in the summary, the mails and on the farm's dashboard.
 */
class FarmOrderColorsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FarmPrinter $s1;

    /** @var array<string, FarmPrinterSlot> */
    private array $spools = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        // one machine with four spools of one kind; the Max is switched off so the list is the S1's alone
        FarmPrinter::where('key', 'kobra-3-max-01')->update(['enabled' => false]);
        $this->s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $white = $this->s1->slots()->where('slot', 2)->firstOrFail();
        $kind = $white->color->farm_material_id;
        $white->color->update(['hex' => '#F4F4F0']);
        $others = FarmColor::where('farm_material_id', $kind)->where('id', '!=', $white->farm_color_id)->take(3)->get();
        foreach ([0 => ['black', '#101012'], 1 => ['red', '#C01818'], 3 => ['blue', '#1E4FA0']] as $slot => [$name, $hex]) {
            $color = $others->shift();
            $color->update(['hex' => $hex, 'enabled' => true]);
            $this->spools[$name] = tap($this->s1->slots()->where('slot', $slot)->firstOrFail())->update(['farm_color_id' => $color->id, 'remaining_g' => 800, 'enabled' => true]);
        }
        $this->spools['white'] = $white;
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_colors_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
    }

    private function colors(FarmOrder $order): array
    {
        return $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('colors');
    }

    public function test_the_colours_run_by_hue_with_white_and_black_last_and_every_one_names_its_spool(): void
    {
        $order = $this->order();
        $colors = $this->colors($order);
        $this->assertSame([$this->spools['red']->id, $this->spools['blue']->id, $this->spools['white']->id, $this->spools['black']->id], array_column($colors, 'slot'));
        // the seeded S1 took the code K1 (its name, Kobra S1 #1): slot 2 counted from 1 is K12
        $this->assertSame(['K12', 'K14', 'K13', 'K11'], array_column($colors, 'code'));
        $this->assertSame([['K12'], ['K14'], ['K13'], ['K11']], array_column($colors, 'codes'));

        // the operator renames the machine U3: the spools follow
        $this->s1->update(['code' => 'U3']);
        $this->assertSame(['U32', 'U34', 'U33', 'U31'], array_column($this->colors($order), 'code'));
    }

    public function test_only_spools_that_can_print_now_are_offered(): void
    {
        $order = $this->order();
        $this->assertCount(4, $this->colors($order));

        // a spool almost empty is not offered at all (one with enough for another print but not this one is greyed)
        $this->spools['blue']->update(['remaining_g' => 40]);
        $this->assertNotContains($this->spools['blue']->id, array_column($this->colors($order), 'slot'));
        $this->spools['blue']->update(['remaining_g' => 800]);
        // a colour switched off, a slot switched off
        $this->spools['red']->color->update(['enabled' => false]);
        $this->spools['black']->update(['enabled' => false]);
        $this->assertSame([$this->spools['blue']->id, $this->spools['white']->id], array_column($this->colors($order), 'slot'));
        $this->spools['red']->color->update(['enabled' => true]);
        $this->spools['black']->update(['enabled' => true]);

        // a machine of the agent that went silent offers nothing; seen a moment ago, it offers again
        $this->s1->update(['mode' => FarmPrinter::MODE_AGENT, 'last_seen_at' => now()->subMinutes(10)]);
        $this->assertSame([], $this->colors($order));
        $this->s1->update(['last_seen_at' => now()->subSeconds(30)]);
        $this->assertCount(4, $this->colors($order));
        // a manual machine is online while it is switched on
        $this->s1->update(['mode' => FarmPrinter::MODE_MANUAL, 'last_seen_at' => null]);
        $this->assertCount(4, $this->colors($order));
    }

    public function test_the_order_page_chooses_colours_once_and_the_codes_reach_the_mail_and_the_dashboard(): void
    {
        $order = $this->order();
        // the page: material and colours up in step 2, the order in step 3 only shows them
        $html = (string) $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->getContent();
        $this->assertStringContainsString('id="farm-material"', $html);
        $this->assertStringContainsString('id="farm-kinds"', $html);
        $this->assertStringContainsString('id="farm-summary"', $html);
        $this->assertStringContainsString(__('farm.order.step_material'), $html);
        $pay = substr($html, strpos($html, 'id="farm-pay"'));
        $pay = substr($pay, 0, strpos($pay, '</form>'));
        $this->assertStringNotContainsString('id="farm-colors"', $pay, 'no second choice of colours in the payment');
        $this->assertStringNotContainsString('id="farm-second-colors"', $pay);
        $this->assertLessThan(strpos($html, 'id="farm-pay"'), strpos($html, 'id="farm-colors"'));

        // paid in blue: the line under the number, the mail and the dashboard name the spool
        $this->actingAs($this->user)->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
        $blue = collect($this->colors($order))->firstWhere('slot', $this->spools['blue']->id);
        $state = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $blue['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $blue['total']])->assertOk()->json();
        $this->assertSame('K14', $state['color']['code']);
        $this->assertSame([['role' => 'main', 'n' => null, 'part' => null, 'name' => $blue['name'], 'kind' => $blue['kind'], 'hex' => $this->spools['blue']->color->hex, 'code' => 'K14']], $state['spools']);
        $order->refresh();
        $this->assertStringContainsString('(K14)', $order->spoolText());
        Mail::assertQueued(FarmOrderStatus::class, fn (FarmOrderStatus $mail) => str_contains($mail->render(), '(K14)'));

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get('/admin/farm')->assertOk()->assertSee('<span class="font-mono">K14</span>', false)->assertSee($order->spoolText());
        $this->actingAs($admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee('K14');
    }
}
