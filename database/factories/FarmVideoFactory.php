<?php

namespace Database\Factories;

use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A time-lapse of an order on its way to YouTube; published() is the one the public counts see.
 *
 * @extends Factory<FarmVideo>
 */
class FarmVideoFactory extends Factory
{
    protected $model = FarmVideo::class;

    public function definition(): array
    {
        return [
            'farm_order_id' => FarmOrder::factory(),
            'kind' => FarmVideo::KIND_SHORT,
            'status' => FarmVideo::STATUS_PENDING,
            'title' => 'Time-lapse',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => FarmVideo::STATUS_PUBLISHED, 'youtube_id' => 'yt'.random_int(100000, 999999), 'published_at' => now()]);
    }
}
