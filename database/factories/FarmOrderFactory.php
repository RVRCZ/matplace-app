<?php

namespace Database\Factories;

use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A farm order as a row alone: no slice, no files. The states say where in its life it stands; paid() gives it a
 * number and a payment time, done() and handedOver() the moments the statistics read.
 *
 * @extends Factory<FarmOrder>
 */
class FarmOrderFactory extends Factory
{
    protected $model = FarmOrder::class;

    public function definition(): array
    {
        return [
            'token' => Str::random(32),
            'user_id' => User::factory(),
            'model_file_id' => ModelFile::factory(),
            'status' => FarmOrder::STATUS_SLICED,
            'kind' => FarmOrder::KIND_PRINT,
            'currency' => 'CZK',
            // any enabled kind (the seeder's PLA when it ran, else a plain one)
            'farm_material_id' => fn () => FarmMaterial::query()->value('id') ?? FarmMaterial::create([
                'code' => 'PLA', 'finish' => 'solid', 'name' => 'PLA', 'filament_profile' => 'filament_pla.json', 'density' => 1.24,
                'nozzle_temp' => 215, 'nozzle_temp_first' => 220, 'bed_temp' => 60, 'price_per_gram' => 1.0, 'enabled' => true, 'sort' => 1,
            ])->id,
            'est_minutes' => 60,
            'est_grams' => 20,
            'price_total' => 150,
        ];
    }

    /** Paid at the given moment (now by default): a number, the payment time, and the money as held. */
    public function paid(?\DateTimeInterface $at = null): static
    {
        return $this->state(function (array $attributes) use ($at) {
            $at ??= now();

            return ['paid_at' => $at, 'number' => 'F26-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT), 'status' => FarmOrder::STATUS_QUEUED];
        });
    }

    /** Printed and waiting for the handover (finished at the given moment, now by default). */
    public function done(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => ['status' => FarmOrder::STATUS_DONE, 'finished_at' => $at ?? now()]);
    }

    /** Handed over or shipped; done just before unless said otherwise. */
    public function handedOver(?\DateTimeInterface $at = null, ?\DateTimeInterface $finishedAt = null): static
    {
        return $this->state(fn () => ['status' => FarmOrder::STATUS_HANDED_OVER, 'finished_at' => $finishedAt ?? $at ?? now(), 'handed_at' => $at ?? now()]);
    }
}
