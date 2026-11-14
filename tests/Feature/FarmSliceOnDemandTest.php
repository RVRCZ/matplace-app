<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Domain\Stats\Funnel;
use App\Models\Event;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\NextStep;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * From a tool straight to the order, the slice only on the customer's word (docs/AN.md §26, Roman 14 Nov 2026).
 * With `farm.slice_on_demand` every new order waits for the choices and nothing is sliced before the button; a design
 * changed in its tool takes the waiting order's place; the tool's page goes straight to the order; a design built face
 * down is shown motif up. With the switch off (the suite's default, phpunit.xml) everything is as it was.
 * Fake slicer, sync queue.
 */
final class FarmSliceOnDemandTest extends TestCase
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
        config(['farm.slice_on_demand' => true]);
    }

    private function upload(float $size = 20.0): string
    {
        $path = sys_get_temp_dir().'/mp_ondemand_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $size);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    /** A file as a tool would leave it: the upload's mesh with the tool's name and parameters on it. */
    private function design(string $kind = 'coaster', array $params = ['flush' => true]): string
    {
        $uuid = $this->upload();
        ModelFile::where('uuid', $uuid)->firstOrFail()->forceFill(['origin' => 'tool', 'origin_ref' => $kind, 'tool_params' => $params])->save();

        return $uuid;
    }

    private function order(array $body): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', $body)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function state(FarmOrder $order): array
    {
        return $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    /** What a page hands its script (`window.MP_FARM = JSON.parse('…')`), read back as the script reads it. */
    private function handed(string $html, string $name): array
    {
        $this->assertSame(1, preg_match('/window\.'.$name.' = JSON\.parse\(\'(.*?)\'\);/s', $html, $m), $name.' is on the page');

        return json_decode((string) json_decode('"'.$m[1].'"'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** The choices as the page sends them: what the order has, with the colour the page ticks first. */
    private function choices(FarmOrder $order, array $over = []): array
    {
        $state = $this->state($order);
        $color = collect($state['colors'])->firstWhere('sliced', true) ?? $state['colors'][0] ?? null;

        return $over + ['quality' => $state['quality'], 'strength' => $state['strength'], 'unit' => $state['unit'], 'copies' => $state['copies'], 'supports' => $state['supports_mode'], 'slot' => $color['slot'] ?? null];
    }

    public function test_a_small_model_waits_for_the_choices_and_nothing_is_sliced_before_the_button(): void
    {
        $order = $this->order(['file' => $this->upload()]);

        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $order->status);
        $this->assertFalse((bool) $order->big_model, 'big_model keeps its meaning: a small model is not one');
        $this->assertNull($order->stage);
        $this->assertNull($order->est_minutes);
        $this->assertNull($order->gcode_path);
        $this->assertNull($order->slice_estimate_s);
        $this->assertSame(0, (int) $order->slice_run);
        $this->assertSame('the slice waits for the word of the customer', $order->events()->first()->note);
        Mail::assertNothingQueued();

        // the page: the colours to choose from, no price and no rough range (the price comes when the customer asks)
        $state = $this->state($order);
        $this->assertSame('awaiting_choices', $state['status']);
        $this->assertTrue($state['on_demand']);
        $this->assertFalse($state['big']);
        $this->assertTrue($state['choosing']);
        $this->assertNull($state['estimate'], 'no rough range for a small model');
        $this->assertNull($state['slice_minutes']);
        $this->assertNull($state['minutes']);
        $this->assertNull($state['total']);
        $this->assertNotEmpty($state['colors']);
        $this->assertNull($state['colors'][0]['price']);
        $this->assertTrue($state['can_cancel']);
        // looking at the page, however often, slices nothing
        $this->state($order);
        $this->assertSame(0, (int) $order->refresh()->slice_run);
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $order->status);

        foreach (['cs' => 'Spočítat cenu', 'en' => 'Calculate the price', 'es' => 'Calcular el precio'] as $lang => $button) {
            $page = (string) $this->actingAs($this->user)->get($this->localized("/farm/orders/{$order->token}", $lang))->assertOk()->getContent();
            $this->assertStringContainsString('id="farm-slice-start"', $page, $lang);
            $this->assertStringContainsString('id="farm-await-note"', $page, $lang);
            $this->assertStringContainsString('id="farm-price-bar"', $page, $lang);
            $texts = $this->handed($page, 'MP_FARM')['i18n'];
            $this->assertSame($button, $texts['farm.order.price_start'], $lang.': the button of a small waiting order');
            foreach (['farm.order.price_start_hint', 'farm.order.price_stale_pay', 'farm.order.recalculate_price'] as $key) {
                $this->assertNotSame($key, $texts[$key], $lang.': a text, not its key');
            }
            $this->assertStringNotContainsString('farm.order.await_note', $page, $lang);
            $this->assertStringNotContainsString('farm.order.price_stale<', $page, $lang);
            $this->assertStringNotContainsString('farm.order.price_bar_note', $page, $lang);
        }
        $this->assertStringContainsString('nic neobjednáváte ani neplatíte', __('farm.order.await_note', [], 'cs'));
        $this->assertStringContainsString('nic neobjednáváte ani neplatíte', __('farm.order.price_start_hint', [], 'cs'));

        // the button: one slice with the choices of the page; the order was counted when it was made, not again
        $before = app(OrderService::class)->slicesToday($this->user);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order, ['copies' => 2]))->assertOk()
            ->assertJsonPath('status', 'sliced')->assertJsonPath('copies', 2);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertSame(1, (int) $order->slice_run);
        $this->assertNotNull($order->est_minutes);
        $this->assertNotNull($order->price_total);
        $this->assertSame('slice started by the customer', $order->events()->where('to', FarmOrder::STATUS_UPLOADED)->get()->last()->note);
        $this->assertSame($before, app(OrderService::class)->slicesToday($this->user));
        $state = $this->state($order);
        $this->assertNotNull($state['total']);
        $this->assertNotNull($state['shipping']);

        // after the first slice nothing starts by itself either: the page asks, the order stays as it is
        $this->state($order);
        $this->assertSame(1, (int) $order->refresh()->slice_run);
        // a changed choice is sliced on the button (the same request), and counts as a slice of the day
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order, ['copies' => 3]))->assertOk()->assertJsonPath('copies', 3);
        $this->assertSame(2, (int) $order->refresh()->slice_run);
        $this->assertSame($before + 1, app(OrderService::class)->slicesToday($this->user));
    }

    public function test_the_first_price_on_the_order_page_is_recorded_once_as_price_shown(): void
    {
        $order = $this->order(['file' => $this->upload()]);
        $this->state($order);
        $this->assertSame(0, Event::where('type', 'price_shown')->count(), 'no price yet');
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order))->assertOk();
        $this->state($order);
        $this->state($order);
        $this->assertSame(1, Event::where('type', 'price_shown')->count());
        $this->assertSame('order', Event::where('type', 'price_shown')->first()->meta['kind']);
        // the funnel's price step is passed by the exact price or by an order made straight from a tool
        $this->assertSame(['price_shown', 'order_created'], Funnel::PATHS['order'][2]);
    }

    public function test_with_the_switch_off_a_small_model_is_sliced_at_once_as_before(): void
    {
        config(['farm.slice_on_demand' => false]);
        $order = $this->order(['file' => $this->upload()]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, 'sync queue: sliced by the time the answer comes');
        $this->assertNotNull($order->est_minutes);
        $this->assertNull($order->events()->first()->note);
        $state = $this->state($order);
        $this->assertFalse($state['on_demand']);
        $this->assertNotNull($state['total']);
        // and a design sent again makes another order, as it did
        $first = $this->order(['file' => $this->design()]);
        $second = $this->order(['file' => $this->design()]);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_a_print_made_again_is_sliced_at_once(): void
    {
        $order = $this->order(['file' => $this->upload()]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $this->choices($order))->assertOk();
        // the old order as paid and printed (its choices were made and its price seen once already)
        $order->refresh()->forceFill(['status' => FarmOrder::STATUS_DONE, 'paid_at' => now(), 'number' => 'F26-000999'])->save();

        $r = $this->actingAs($this->user)->postJson(route('farm.orders.again', $order))->assertCreated();
        $again = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertNotSame($order->id, $again->id);
        $this->assertSame(FarmOrder::STATUS_SLICED, $again->status);
        $this->assertNotNull($again->price_total);
    }

    public function test_a_design_changed_in_its_tool_takes_the_waiting_orders_place(): void
    {
        $a = $this->order(['file' => $this->design(), 'from' => 'tool', 'copies' => 2]);
        $this->assertSame(FarmOrder::STATUS_AWAITING_CHOICES, $a->status);
        $this->assertSame('tool', Event::where('type', 'order_created')->firstOrFail()->meta['kind']);
        $before = app(OrderService::class)->slicesToday($this->user);

        // back in the tool, the design changed, sent again: the same order (the same address) with the new file
        $second = $this->design();
        $b = $this->order(['file' => $second, 'from' => 'tool', 'copies' => 3]);
        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->token, $b->token);
        $this->assertSame(ModelFile::where('uuid', $second)->value('id'), $b->model_file_id);
        $this->assertSame(3, (int) $b->copies);
        $this->assertSame(1, FarmOrder::count());
        $this->assertSame(1, Event::where('type', 'order_created')->count(), 'one order, one event');
        $this->assertSame($before, app(OrderService::class)->slicesToday($this->user), 'not another order of the day');
        $this->assertStringContainsString('design changed in the tool', (string) $b->events()->get()->last()->note);

        // a design of another tool is another order
        $c = $this->order(['file' => $this->design('keychain', []), 'from' => 'tool']);
        $this->assertNotSame($a->id, $c->id);
        // an uploaded file never takes a design's place
        $d = $this->order(['file' => $this->upload()]);
        $this->assertNotContains($d->id, [$a->id, $c->id]);

        // once the price was asked for the order is a real one: the next design makes its own
        $this->actingAs($this->user)->postJson("/farm/orders/{$b->token}/reslice", $this->choices($b))->assertOk()->assertJsonPath('status', 'sliced');
        $e = $this->order(['file' => $this->design(), 'from' => 'tool']);
        $this->assertNotSame($b->id, $e->id);
        $this->assertSame(FarmOrder::STATUS_SLICED, $b->refresh()->status);

        // somebody else's waiting order is never taken
        $other = User::factory()->create();
        $uuid = $this->design();
        ModelFile::where('uuid', $uuid)->update(['owner_user_id' => $other->id]);
        $r = $this->actingAs($other)->postJson('/farm/orders', ['file' => $uuid, 'from' => 'tool'])->assertCreated();
        $this->assertNotSame($e->token, basename($r->json('url')));
    }

    public function test_the_kind_of_plastic_picked_on_the_tool_page_rides_to_the_order(): void
    {
        // the farm's own first choice is the cheapest kind loaded; the tool's page names another one that is loaded too
        $other = FarmMaterial::where('code', 'ASA')->firstOrFail();
        $other->update(['enabled' => true]);
        $black = FarmColor::create(['farm_material_id' => $other->id, 'code' => 'ASA_black', 'name' => 'černá', 'name_en' => 'black', 'hex' => '#111111', 'enabled' => true, 'in_stock' => true]);
        FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail()->slots()->where('slot', 0)->firstOrFail()->update(['farm_color_id' => $black->id, 'remaining_g' => 800, 'enabled' => true]);
        $plain = $this->order(['file' => $this->upload()]);
        $this->assertNotSame($other->id, $plain->farm_material_id, 'without a word the order starts on the cheapest kind');
        $order = $this->order(['file' => $this->design(), 'from' => 'tool', 'material' => 'ASA']);
        $this->assertSame($other->id, $order->farm_material_id);
    }

    public function test_the_tool_page_goes_straight_to_the_order_only_where_an_order_can_be_made(): void
    {
        config(['features.marketplace' => false, 'farm.enabled' => true, 'farm.open' => true, 'farm.public' => true]);
        $this->assertTrue(NextStep::direct('coaster'));
        foreach (['' => 'Přejít k tisku', '/en' => 'Continue to printing', '/es' => 'Continuar a la impresión'] as $prefix => $label) {
            $html = (string) $this->get($prefix.'/tools/coaster')->assertOk()->getContent();
            $this->assertStringContainsString('<span id="tool-go-label">'.$label.'</span>', $html, $prefix);
            $this->assertSame(route('farm.orders.store'), $this->handed($html, 'MP_TOOL')['order']['store'], $prefix.': the page knows where the order is made');
            $this->assertStringNotContainsString('param.go.hint.order', $html, $prefix);
        }
        preg_match('/<p class="w-full text-xs text-muted">(.*?)<\/p>/s', (string) $this->get('/tools/coaster')->getContent(), $m);
        $this->assertSame(__('param.go.hint.order'), html_entity_decode(trim($m[1] ?? '')));
        $this->assertStringNotContainsString('kalkulac', __('param.go.hint.order'), 'the calculation is left out of this path');
        // the map names its own next step: the same word
        $this->assertStringContainsString(trim(json_encode('Přejít k tisku'), '"'), stripslashes((string) $this->get('/tools/map')->assertOk()->getContent()));

        // the switch off, the farm closed or not public: the calculation as before
        foreach ([['farm.slice_on_demand' => false], ['farm.open' => false], ['farm.public' => false], ['features.marketplace' => true]] as $off) {
            config(['features.marketplace' => false, 'farm.open' => true, 'farm.public' => true, 'farm.slice_on_demand' => true]);
            config($off);
            $this->assertFalse(NextStep::direct('coaster'), json_encode($off));
            $html = (string) $this->get('/tools/coaster')->assertOk()->getContent();
            $this->assertStringNotContainsString('Přejít k tisku</span>', $html, json_encode($off));
            $this->assertNull($this->handed($html, 'MP_TOOL')['order'], json_encode($off));
        }
    }

    public function test_the_calculators_button_only_leads_to_the_order_and_says_so(): void
    {
        foreach (['cs' => 'Přejít k tisku', 'en' => 'Continue to printing', 'es' => 'Continuar a la impresión'] as $lang => $label) {
            $this->assertSame($label, __('farm.cta', [], $lang));
            $this->assertSame($label, __('models.order', [], $lang));
            $this->assertSame($label, __('user.models.order', [], $lang));
        }
    }

    public function test_a_design_built_face_down_is_shown_motif_up_and_the_order_leads_back_to_its_tool(): void
    {
        $file = fn (string $kind, array $params) => (new ModelFile)->forceFill(['origin' => 'tool', 'origin_ref' => $kind, 'tool_params' => $params]);
        $this->assertTrue($file('coaster', ['flush' => true])->printsFaceDown());
        $this->assertFalse($file('coaster', ['flush' => false])->printsFaceDown());
        $this->assertTrue($file('medallion', ['motif' => 'flush'])->printsFaceDown());
        $this->assertFalse($file('medallion', ['motif' => 'raised'])->printsFaceDown());
        $this->assertTrue($file('medallion', ['motif' => 'raised', 'inlay' => true])->printsFaceDown());
        $this->assertFalse($file('cookie', ['flush' => true])->printsFaceDown(), 'a cookie keeps its round top up');
        $this->assertFalse($file('coaster', ['flush' => true, 'decor' => [['kind' => 'photo', 'face' => 'front']]])->printsFaceDown(), 'a relief from a photo on the front turns the piece up');
        $this->assertFalse((new ModelFile)->forceFill(['origin' => 'upload'])->printsFaceDown());

        $uuid = $this->design('coaster', ['flush' => true]);
        $this->actingAs($this->user)->getJson("/api/files/{$uuid}")->assertOk()->assertJsonPath('file.face_down', true);
        $order = $this->order(['file' => $uuid, 'from' => 'tool']);
        $state = $this->state($order);
        $this->assertTrue($state['face_down']);
        $this->assertSame(route('tools.coaster', ['from' => $uuid]), $state['tool_url']);
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()
            ->assertSee('id="farm-face-down"', false)->assertSee(__('farm.face_down_note'))
            ->assertSee('id="farm-tool-link"', false)->assertSee(__('calc.edit_design'));

        // a plain upload: no line, no way back to a tool
        $plain = $this->order(['file' => $this->upload()]);
        $state = $this->state($plain);
        $this->assertFalse($state['face_down']);
        $this->assertNull($state['tool_url']);
        $this->actingAs($this->user)->get("/farm/orders/{$plain->token}")->assertOk()->assertDontSee('id="farm-face-down"', false)->assertDontSee('id="farm-tool-link"', false);
    }
}
