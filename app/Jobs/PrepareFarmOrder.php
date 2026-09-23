<?php

namespace App\Jobs;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\ModelValidator;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\TestPrintService;
use App\Domain\Farm\TowerGcode;
use App\Engines\Contracts\PrintPreparer;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\Farm\PhpPrintPreparer;
use App\Engines\Gcode\SupportLines;
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
            // a test object is built closed, on Z = 0, the way it must be printed: nothing to repair or turn
            $mesh = $order->isTest() ? (new PhpPrintPreparer)->prepare($file->absoluteStlPath(), $disk->path($stlRel), 1.0, $bed)
                : $preparer->prepare($file->absoluteStlPath(), $disk->path($stlRel), $order->unit_scale, $bed);
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
            // the kind, then the kind on this machine, then the spool: the most specific layer wins (PrintProfile);
            // temperatures are written into the G-code copy for the chosen spool later
            $profile = PrintProfile::forOrder($order);
            $profiles = [
                'machine' => $printer->machine_profile,
                'process' => $printer->process_profiles[$quality] ?? null,
                'filament' => $profile->filamentProfile,
            ];
            $overrides = [
                'machine' => (array) $printer->machine_overrides,
                'process' => ['layer_height' => (string) $layer] + $profile->process + (array) $printer->process_overrides,
                'filament' => $profile->filament,
            ];
            $params = (new SliceParams(materialCode: $order->material->code, quality: $quality, infillPercent: $infill, supports: null, treeSupports: true))
                ->withFarmProfile($profiles, $overrides);
            $result = $slicer->slice($disk->path($stlRel), $params);
            if (! $result->gcodePath || ! is_file($result->gcodePath)) {
                throw new \RuntimeException('Slicer returned no G-code.');
            }
            $gcodeRel = $order->dir().'/print.gcode';
            File::move($result->gcodePath, $disk->path($gcodeRel));
            if ($order->isTest() && is_array($order->test_params['temps'] ?? null)) {
                // temperature tower: one temperature per floor, written after the slice
                $tower = TowerGcode::apply((string) File::get($disk->path($gcodeRel)), (float) ($order->test_params['floor_mm'] ?? TestPrintService::FLOOR_MM), $order->test_params['temps']);
                File::put($disk->path($gcodeRel), $tower['gcode']);
                $order->test_params = ['floors_set' => $tower['floors_set']] + $order->test_params;
            }
            // the supports the slicer built, for the customer's preview (a re-slice without them drops the old file)
            $supportsBin = $disk->path($order->dir().'/supports.bin');
            if (! $result->supportsUsed || ! SupportLines::extract($disk->path($gcodeRel), $supportsBin)) {
                @unlink($supportsBin);
            }

            $order->fill([
                'gcode_path' => $gcodeRel,
                'gcode_sha256' => hash_file('sha256', $disk->path($gcodeRel)),
                'slice_params' => [
                    'engine' => $slicer->name(), 'printer' => ['id' => $printer->id, 'key' => $printer->key, 'model' => $printer->model],
                    'material' => $order->material->code, 'quality' => $quality, 'layer_mm' => $layer, 'strength' => $order->strength,
                    'infill_percent' => $infill, 'unit_scale' => $order->unit_scale, 'profiles' => $profiles, 'overrides' => $overrides,
                    'profile_layers' => $profile->layers, 'profile_fingerprint' => $profile->sliceFingerprint(),
                    'profile_hashes' => $this->profileHashes($profiles), 'preparer' => $order->isTest() ? 'php-stl' : $preparer->name(), 'sliced_at' => now()->toIso8601String(),
                ],
                'slice_result' => $result->toArray(),
                'est_minutes' => $result->minutes,
                'est_grams' => $result->grams,
                'est_meters' => $result->meters,
                'supports_used' => $result->supportsUsed,
            ])->save();

            // ── 3. price ───────────────────────────────────────────────────────
            if ($order->isTest()) {
                // the farm's own test print: nobody pays, it goes straight to the queue
                $order->fill(['stage' => null])->save();
                $flow->move($order, FarmOrder::STATUS_SLICED, 'system');
                $flow->move($order, FarmOrder::STATUS_QUEUED, 'system');

                return;
            }
            if ($order->paid_at !== null) {
                // re-sliced for the chosen spool after payment: the price stays what the customer paid
                $order->fill(['stage' => null])->save();
                $flow->move($order, FarmOrder::STATUS_SLICED, 'system');
                $flow->move($order, FarmOrder::STATUS_PAID, 'system');
                if (! $settings->get('require_approval')) {
                    $flow->move($order, FarmOrder::STATUS_QUEUED, 'system');
                }

                return;
            }
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
