<?php

namespace App\Domain\YouTube;

use App\Domain\Farm\FarmRefusal;
use App\Jobs\PrepareFarmOrder;
use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * A print for the YouTube channel on a free machine: an impressive model the admin picked, nobody pays, it goes to
 * the queue once sliced (like a test print, OrderFlow), the head parks for the layer time-lapse and the video is
 * uploaded like a customer's. Numbered U26-000001 (ukázka).
 */
class ShowcasePrints
{
    /**
     * @param  string  $model  a calculator link (…/c/{token}) or a model file uuid
     *
     * @throws FarmRefusal model (not found / not ready), slot (empty or switched off)
     */
    public function create(string $model, FarmPrinterSlot $slot, string $quality, User $admin): FarmOrder
    {
        $file = $this->file($model);
        if (! $file || ! $file->isReady()) {
            throw new FarmRefusal('model');
        }
        $color = $slot->color;
        $printer = $slot->printer;
        if (! $color || ! $printer || ! $printer->enabled || $printer->mode !== FarmPrinter::MODE_AGENT) {
            throw new FarmRefusal('slot');
        }

        $order = FarmOrder::create([
            'token' => Str::random(32), 'number' => $this->nextNumber(), 'kind' => FarmOrder::KIND_SHOWCASE,
            'user_id' => $admin->id, 'model_file_id' => $file->id, 'status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'checking',
            'quality' => $quality, 'strength' => 'standard', 'unit_scale' => 1,
            'farm_material_id' => $color->farm_material_id, 'farm_color_id' => $color->id, 'farm_printer_id' => $printer->id, 'farm_printer_slot_id' => $slot->id,
            'video_consent' => true, 'video_consent_at' => now(), 'note' => 'Ukázka pro YouTube',
        ]);
        $order->events()->create(['to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'admin', 'actor_id' => $admin->id, 'note' => 'showcase']);
        PrepareFarmOrder::dispatch($order->id);

        return $order;
    }

    private function file(string $model): ?ModelFile
    {
        $model = trim($model);
        if (preg_match('~/c/([A-Za-z0-9_-]+)~', $model, $m)) {
            return Calculation::with('modelFile')->where('token', $m[1])->first()?->modelFile;
        }
        if (preg_match('~[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}~i', $model, $m)) {
            return ModelFile::where('uuid', strtolower($m[0]))->first();
        }

        return null;
    }

    private function nextNumber(): string
    {
        $prefix = 'U'.now()->format('y').'-';
        $last = FarmOrder::where('number', 'like', $prefix.'%')->max('number');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 6, '0', STR_PAD_LEFT);
    }
}
