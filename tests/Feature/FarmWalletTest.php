<?php

namespace Tests\Feature;

use App\Domain\Farm\InsufficientCredit;
use App\Domain\Farm\Wallet;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmWalletTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user, float $price): FarmOrder
    {
        $material = FarmMaterial::firstOrCreate(['code' => 'PLA'], ['name' => 'PLA', 'filament_profile' => 'filament_pla.json', 'price_per_gram' => 1.2]);
        $file = ModelFile::create(['uuid' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'original_name' => 'a.stl', 'ext' => 'stl', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64), 'storage_path' => 'x', 'status' => ModelFile::STATUS_READY]);

        return FarmOrder::create(['token' => Str::random(32), 'user_id' => $user->id, 'model_file_id' => $file->id, 'status' => FarmOrder::STATUS_SLICED, 'farm_material_id' => $material->id, 'price_total' => $price]);
    }

    private function paid(User $user, float $amount, string $ref = 'cs_1'): Payment
    {
        return Payment::create(['user_id' => $user->id, 'gateway' => 'fake', 'gateway_ref' => $ref, 'amount' => $amount, 'status' => Payment::STATUS_PAID]);
    }

    public function test_a_top_up_delivered_twice_is_credited_once(): void
    {
        $user = User::factory()->create();
        $payment = $this->paid($user, 500);
        $wallet = new Wallet;

        $this->assertTrue($wallet->topUp($payment));
        $this->assertFalse($wallet->topUp($payment));
        $this->assertSame(500.0, $wallet->balance($user));
    }

    public function test_hold_needs_enough_credit_and_says_how_much_is_missing(): void
    {
        $user = User::factory()->create();
        $wallet = new Wallet;
        $wallet->topUp($this->paid($user, 100));

        try {
            $wallet->hold($this->order($user, 149));
            $this->fail('expected InsufficientCredit');
        } catch (InsufficientCredit $e) {
            $this->assertSame(49.0, $e->missing());
        }
        $this->assertSame(100.0, $wallet->balance($user));
    }

    public function test_hold_then_cancel_returns_everything(): void
    {
        $user = User::factory()->create();
        $wallet = new Wallet;
        $wallet->topUp($this->paid($user, 500));
        $order = $this->order($user, 149);

        $wallet->hold($order);
        $this->assertSame(351.0, $wallet->balance($user));

        $this->assertSame(149.0, $wallet->giveBack($order));
        $this->assertSame(500.0, $wallet->balance($user));
        $this->assertSame(0.0, $wallet->giveBack($order), 'a second return must give nothing');
        $this->assertSame(500.0, $wallet->balance($user));
    }

    public function test_captured_credit_stays_spent_and_can_still_be_refunded_by_an_admin(): void
    {
        $user = User::factory()->create();
        $wallet = new Wallet;
        $wallet->topUp($this->paid($user, 500));
        $order = $this->order($user, 149);

        $wallet->hold($order);
        $wallet->capture($order);
        $this->assertSame(351.0, $wallet->balance($user));

        $wallet->giveBack($order, note: 'warped print');
        $this->assertSame(500.0, $wallet->balance($user));
        $this->assertDatabaseHas('credit_transactions', ['farm_order_id' => $order->id, 'type' => 'refund', 'amount' => 149]);
    }

    public function test_one_order_cannot_hold_twice(): void
    {
        $user = User::factory()->create();
        $wallet = new Wallet;
        $wallet->topUp($this->paid($user, 500));
        $order = $this->order($user, 149);
        $wallet->hold($order);

        $this->expectException(\LogicException::class);
        $wallet->hold($order);
    }
}
