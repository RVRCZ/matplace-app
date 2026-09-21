<?php

namespace App\Jobs;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\ModelValidator;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Engines\Contracts\PrintPreparer;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Farm order: uploaded → sliced. Check and repair the mesh, turn it for the plate, slice it with the printer's and the
 * material's profile, store the G-code with everything needed to produce it again, price it.
 * Waits (by re-queueing) until the model file itself is processed, like SliceCalculation.
 */
class PrepareFarmOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 40;

    public int $backoff = 3;

    public int $timeout = 900;

    public function __construct(public readonly int $orderId) {}

    public function handle(PrintPreparer $preparer, Slicer $slicer, FarmSettings $settings, OrderService $orders, OrderFlow $flow): void
    {
        $order = FarmOrder::with(['modelFile', 'printer', 'material'])->find($this->orderId);
        if (! $order || $order->status !== FarmOrder::STATUS_UPLOADED) {
            return;
        }
        $file = $order->modelFile;
        if (! $file || $file->status === ModelFile::STATUS_FAILED) {
            $flow->fail($order, 'file_unreadable', 'system', detail: $file?->error);

            return;
        }
        if (! $file->isReady()) {
            $this->release($this->backoff);

            return;
        }
        $printer = $order->printer;
        if (! $printer) {
            $flow->fail($order, 'no_printer', 'system');

            return;
        }

        $disk = Storage::disk(config('farm.disk'));
        $bed = new Dimensions($printer->bed_x, $printer->bed_y, $printer->bed_z);

        try {
            // ── 1. check, repair, orient ───────────────────────────────────────
            $order->update(['stage' => 'checking']);
            $stlRel = $order->dir().'/print.stl';
            File::ensureDirectoryExists(dirname($disk->path($stlRel)));
            $mesh = $preparer->prepare($file->absoluteStlPath(), $disk->path($stlRel), $order->unit_scale, $bed);
            $verdict = ModelValidator::judge($mesh, $bed, [
                'min_model_mm' => (float) $settings->get('min_model_mm'),
                'bed_margin_mm' => (float) $settings->get('bed_margin_mm'),
            ]);
            $raw = Dimensions::fromArray((array) $file->bbox);
            $order->check = $verdict + ['mesh' => $mesh->toArray(), 'unit_guess' => ModelValidator::guessUnit($raw, $bed), 'raw_dims' => $raw->toArray()];
            $order->orientation = $mesh->orientation;
            $order->print_stl_path = $stlRel;
            $order->save();
            if (! $verdict['ok']) {
                $flow->fail($order, $verdict['errors'][0]['code'], 'system');

                return;
            }

            // ── 2. slice ───────────────────────────────────────────────────────
            $order->update(['stage' => 'slicing']);
            $quality = $order->quality;
            $layer = $settings->layerFor($quality);
            $infill = $settings->infillFor($order->strength);
            $profiles = [
                'machine' => $printer->machine_profile,
                'process' => $printer->process_profiles[$quality] ?? null,
                'filament' => $order->material->filament_profile,
            ];
            $overrides = [
                'machine' => (array) $printer->machine_overrides,
                'process' => ['layer_height' => (string) $layer] + (array) $printer->process_overrides,
                'filament' => (array) $order->material->filament_overrides,
            ];
            $params = (new SliceParams(materialCode: $order->material->code, quality: $quality, infillPercent: $infill, supports: null, treeSupports: true))
                ->withFarmProfile($profiles, $overrides);
            $result = $slicer->slice($disk->path($stlRel), $params);
            if (! $result->gcodePath || ! is_file($result->gcodePath)) {
                throw new \RuntimeException('Slicer returned no G-code.');
            }
            $gcodeRel = $order->dir().'/print.gcode';
            File::move($result->gcodePath, $disk->path($gcodeRel));

            $order->fill([
                'gcode_path' => $gcodeRel,
                'gcode_sha256' => hash_file('sha256', $disk->path($gcodeRel)),
                'slice_params' => [
                    'engine' => $slicer->name(), 'printer' => ['id' => $printer->id, 'key' => $printer->key, 'model' => $printer->model],
                    'material' => $order->material->code, 'quality' => $quality, 'layer_mm' => $layer, 'strength' => $order->strength,
                    'infill_percent' => $infill, 'unit_scale' => $order->unit_scale, 'profiles' => $profiles, 'overrides' => $overrides,
                    'profile_hashes' => $this->profileHashes($profiles), 'preparer' => $preparer->name(), 'sliced_at' => now()->toIso8601String(),
                ],
                'slice_result' => $result->toArray(),
                'est_minutes' => $result->minutes,
                'est_grams' => $result->grams,
                'est_meters' => $result->meters,
                'supports_used' => $result->supportsUsed,
            ])->save();

            // ── 3. price ───────────────────────────────────────────────────────
            $price = $orders->priceFor($order, $printer);
            $order->fill(['price' => $price, 'price_total' => $price['total'], 'currency' => $price['currency'], 'stage' => null])->save();
            $flow->move($order, FarmOrder::STATUS_SLICED, 'system');
        } catch (\Throwable $e) {
            Log::warning('PrepareFarmOrder failed', ['order' => $order->id, 'error' => $e->getMessage()]);
            $flow->fail($order, 'slicing_failed', 'system', detail: mb_substr($e->getMessage(), 0, 1000));
        }
    }

    /** Fingerprints of the profile files: the same names with different content would not be the same print. */
    private function profileHashes(array $profiles): array
    {
        $out = [];
        foreach ($profiles as $kind => $name) {
            foreach ([config('farm.profiles_dir'), config('engines.orca.profiles')] as $dir) {
                if ($name && $dir && is_file($dir.'/'.basename($name))) {
                    $out[$kind] = sha1_file($dir.'/'.basename($name));
                    break;
                }
            }
        }

        return $out;
    }
}
