<?php

namespace Database\Factories;

use App\Models\FarmOrder;
use App\Models\FarmOrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One line of an order's history. `created_at` is the moment it happened: pass it for the statistics that read the
 * time between the payment and the handover.
 *
 * @extends Factory<FarmOrderEvent>
 */
class FarmOrderEventFactory extends Factory
{
    protected $model = FarmOrderEvent::class;

    public function definition(): array
    {
        return [
            'farm_order_id' => FarmOrder::factory(),
            'from' => FarmOrder::STATUS_DONE,
            'to' => FarmOrder::STATUS_HANDED_OVER,
            'actor' => 'admin',
            'created_at' => now(),
        ];
    }
}
