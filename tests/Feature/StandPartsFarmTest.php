<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Domain\Tools\ParametricGenerator;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\StartsOrders;
use Tests\TestCase;

/**
 * A stand with a silhouette is printed by parts on the farm, each part from its own spool: the hair-tie holder as
 * three plates (the silhouette, the base, the post), the sticky-notes stand and the candle stand as two.
 */
class StandPartsFarmTest extends TestCase
{
    use RefreshDatabase;
    use StartsOrders;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
    }

    public function test_the_hair_tie_holder_is_three_plates_from_three_spools(): void
    {
        // the S1 holds one spool; two more of the same family join it
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $mainSlot = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail();
        $first = $mainSlot->color;
        $more = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'like', 'PLA%'))->where('id', '!=', $first->id)->take(2)->get();
        $this->assertCount(2, $more);
        $slots = [];
        foreach ($more as $i => $color) {
            $color->update(['hex' => ['#C01818', '#F2C200'][$i], 'enabled' => true]);
            $slot = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->firstOrFail();
            $slot->update(['farm_color_id' => $color->id, 'remaining_g' => 800, 'enabled' => true]);
            $slots[] = $slot;
        }
        [$red, $yellow] = [$more[0], $more[1]];

        $uuid = $this->actingAs($this->user)->postJson('/api/tools/param', ['kind' => 'hair_tie', 'params' => [
            'artwork' => 'lib:hearts-stars/crown', 'width' => 60, 'thickness' => 3, 'post_d' => 14, 'post_h' => 60,
            'part_colors' => ['body' => '#c01818', 'stand' => '#222222', 'post' => '#f2c200'],
        ]])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(['body', 'stand', 'post'], OrderService::designParts($file));

        // made from the start page, the order is printed by parts: the design names a colour for each; the order page
        // names the three parts as the tool does
        $started = $this->startOrder($this->user, 'file='.$uuid);
        $this->assertTrue($started->isByParts());
        $labels = array_column($this->actingAs($this->user)->getJson("/farm/orders/{$started->token}/status")->json('parts'), 'label');
        $this->assertSame(array_map(fn ($part) => __('param.part.'.$part.'.hair_tie'), ['body', 'stand', 'post']), $labels);

        // one colour: one plate, the three parts next to each other as the tool lays them out
        $plain = FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid, 'color' => $first->id, 'by_parts' => 0])->assertCreated()->json('url')))->firstOrFail();
        $this->assertFalse($plain->isByParts());
        $this->assertSame(1, $plain->plates);

        // by parts: a plate per part, each from the spool named for it
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid, 'color' => $first->id, 'by_parts' => 1, 'part_color' => ['body' => $red->id, 'post' => $yellow->id]])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertTrue($order->isByParts());
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertSame(3, $order->plates);
        $this->assertSame(['body', 'stand', 'post'], array_column($order->partPlates(), 'part'));
        $this->assertSame([$red->id, $first->id, $yellow->id], array_column($order->partPlates(), 'color_id'));
        $this->assertSame([$slots[0]->id, $mainSlot->id, $slots[1]->id], array_column($order->partPlates(), 'slot_id'));
        foreach ([1, 2, 3] as $plate) {
            $this->assertFileExists($order->absoluteGcodePath($plate));
        }
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame(['body', 'stand', 'post'], array_column($state['parts'], 'part'));
        $this->assertSame(__('param.part.post.hair_tie'), $state['parts'][2]['label']);
    }

    public function test_the_other_two_stands_are_two_plates(): void
    {
        foreach (['notes' => ['artwork' => 'lib:animals/cat', 'width' => 60, 'pad' => 76], 'candle_stand' => ['artwork' => 'lib:holidays/christmas-tree', 'width' => 60, 'jar_d' => 60]] as $kind => $params) {
            $uuid = $this->actingAs($this->user)->postJson('/api/tools/param', ['kind' => $kind, 'params' => $params])->assertCreated()->json('file.uuid');
            $file = ModelFile::where('uuid', $uuid)->firstOrFail();
            $this->assertSame(['body', 'stand'], OrderService::designParts($file), $kind);
            $order = FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid, 'by_parts' => 1, 'part_color' => []])->assertCreated()->json('url')))->firstOrFail();
            $this->assertSame(['body', 'stand'], array_column($order->partPlates(), 'part'), $kind);
        }
    }
}
