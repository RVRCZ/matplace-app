<?php

namespace App\Jobs;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\ModelValidator;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\PlateLayout;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\PrintSettings;
use App\Domain\Farm\TestPrintService;
use App\Domain\Farm\TimelapseGcode;
use App\Domain\Farm\TowerGcode;
use App\Engines\Contracts\PrintPreparer;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\Farm\PhpPrintPreparer;
use App\Engines\Gcode\SupportLines;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Support\Stopwatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Farm order: uploaded → sliced. Check and repair the mesh, turn it for the plate, slice it with the printer's and the
 * material's profile, store the G-code with everything needed to produce it again, price it.
 * Started when the order is made and again by ProcessModelFile when its file is ready, like SliceCalculation; two
 * copies never run at once (WithoutOverlapping), and the later one finds the order no longer "uploaded".
 */
class PrepareFarmOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 40;

    public int $backoff = 3;

    public int $timeout = 900;

    /** When it was put in the queue (epoch): the wait in the queue is part of the customer's wait. */
    public ?float $queuedAt = null;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue(config('queue.interactive'));
        $this->queuedAt = microtime(true);
    }

    /** Several workers: two recalculations of one order never write its files at the same time. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('farm-order-'.$this->orderId))->releaseAfter(5)->expireAfter($this->timeout + 60)];
    }

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
            return;   // ProcessModelFile starts it again when the file is ready (or fails it when the file failed)
        }
        $printer = $order->printer;
        if (! $printer) {
            $flow->fail($order, 'no_printer', 'system');

            return;
        }

        $disk = Storage::disk(config('farm.disk'));
        $bed = new Dimensions($printer->bed_x, $printer->bed_y, $printer->bed_z);
        // a first slice is waited for from the upload, a re-slice (other quality, colour, printer) from the click
        $reslice = $order->slice_params !== null;
        $clock = (new Stopwatch)->note('started', round(microtime(true), 1))->note('reslice', $reslice)->since('queue', $this->queuedAt)
            ->note('triangles', $file->triangles);
        if (! $reslice) {
            $clock->since('wait', $order->created_at?->getTimestamp());
        }

        try {
            if ($order->isByParts()) {
                // every part its own plate from its own spool: checked, laid out, sliced and priced one after another
                $this->prepareParts($order, $preparer, $slicer, $settings, $orders, $flow, $clock, $disk, $bed);

                return;
            }
            // ── 1. check, repair, orient ───────────────────────────────────────
            $order->update(['stage' => 'checking']);
            $stlRel = $order->dir().'/print.stl';
            File::ensureDirectoryExists(dirname($disk->path($stlRel)));
            @unlink($disk->path($stlRel).'.stage');
            // a test object is built closed, on Z = 0, the way it must be printed: nothing to repair or turn
            $mesh = $clock->measure('prepare', fn () => $order->isTest() ? (new PhpPrintPreparer)->prepare($file->absoluteStlPath(), $disk->path($stlRel), 1.0, $bed)
                : $preparer->prepare($file->absoluteStlPath(), $disk->path($stlRel), $order->unit_scale * (float) ($order->scale ?: 1), $bed, $file->builtForPrinting()));
            $clock->note('prepare_cached', (bool) ($mesh->orientation['reused'] ?? false));
            @unlink($disk->path($order->dir().'/print.stl.stage'));   // the tool's running commentary ends with it
            $piece = $mesh;
            $margin = 2 * (float) $settings->get('bed_margin_mm');
            $usable = new Dimensions($bed->x - $margin, $bed->y - $margin, $bed->z);
            $capacity = PlateLayout::capacity($piece->bbox, $usable);
            $plates = 1;
            $perPlate = $order->copies;
            $rest = null;
            if ($order->copies > 1 && ! $order->isTest() && $capacity['max'] >= 1) {
                // several copies on one plate: the oriented piece repeated in a grid, sliced and priced as one print;
                // more than a plate takes → full plates one after another, the last one holding what is left
                if ($order->copies > $capacity['max']) {
                    $plates = (int) ceil($order->copies / $capacity['max']);
                    $perPlate = $capacity['max'];
                    $rest = $order->copies - $perPlate * ($plates - 1);
                    if ($rest === $perPlate) {
                        $rest = null;
                    }
                }
                $plateRel = $order->dir().'/plate.stl';
                $mesh = $clock->measure('layout', fn () => PlateLayout::replicate($piece, $disk->path($plateRel), $perPlate, $usable)) ?? throw new \RuntimeException('Plate layout failed although the capacity allowed it.');
                $stlRel = $plateRel;
            }
            $order->fill(['plates' => $plates, 'plates_done' => 0, 'plate_copies' => $perPlate, 'rest_copies' => $rest, 'rest_gcode_path' => null]);
            $verdict = ModelValidator::judge($mesh, $bed, [
                'min_model_mm' => (float) $settings->get('min_model_mm'),
                'bed_margin_mm' => (float) $settings->get('bed_margin_mm'),
            ]);
            $raw = Dimensions::fromArray((array) $file->bbox);
            $order->check = $verdict + ['mesh' => $mesh->toArray(), 'unit_guess' => ModelValidator::guessUnit($raw, $bed), 'raw_dims' => $raw->toArray(),
                'piece_dims' => $piece->bbox->toArray(), 'max_copies' => $capacity['max']];
            $order->orientation = $mesh->orientation;
            $order->print_stl_path = $stlRel;
            $order->save();
            if (! $verdict['ok']) {
                $flow->fail($order, $verdict['errors'][0]['code'], 'system');

                return;
            }

            // ── 2. slice ───────────────────────────────────────────────────────
            $order->update(['stage' => 'slicing']);
            ['params' => $params, 'profiles' => $profiles, 'overrides' => $overrides, 'profile' => $profile, 'layer' => $layer, 'infill' => $infill, 'quality' => $quality] = $this->sliceSetup($order, $settings);
            $result = $clock->measure('slice', fn () => $slicer->slice($disk->path($stlRel), $params));
            $clock->merge($result->timings);
            $postStart = hrtime(true);
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
            // the last plate of a multi-plate order holds fewer pieces: its own layout and G-code
            $restRel = null;
            $restResult = null;
            if ($rest !== null) {
                $restStl = $disk->path($order->dir().'/rest.stl');
                PlateLayout::replicate($piece, $restStl, $rest, $usable) ?? throw new \RuntimeException('Rest plate layout failed.');
                $clock->add('post', (hrtime(true) - $postStart) / 1e9);
                $restResult = $clock->measure('rest_slice', fn () => $slicer->slice($restStl, $params));
                $clock->merge($restResult->timings, 'rest_');
                $postStart = hrtime(true);
                if (! $restResult->gcodePath || ! is_file($restResult->gcodePath)) {
                    throw new \RuntimeException('Slicer returned no G-code for the last plate.');
                }
                $restRel = $order->dir().'/rest.gcode';
                File::move($restResult->gcodePath, $disk->path($restRel));
            }
            $fullPlates = $rest === null ? $plates : $plates - 1;
            $sum = fn (float $full, ?float $last) => $full * $fullPlates + ($last ?? 0.0);

            // the head parks for the time-lapse after every layer on this machine: that time is printing time too
            $timelapse = $printer->timelapseFor($order);
            $parkingOf = fn (?string $rel) => $timelapse && $rel ? TimelapseGcode::extraMinutesForFile($disk->path($rel), $timelapse, (float) $printer->bed_x, (float) $printer->bed_y) : 0;
            $parking = (int) round($sum($parkingOf($gcodeRel), $restRel ? $parkingOf($restRel) : null));

            $order->fill([
                'gcode_path' => $gcodeRel,
                'gcode_sha256' => hash_file('sha256', $disk->path($gcodeRel)),
                'rest_gcode_path' => $restRel,
                'slice_params' => [
                    'engine' => $slicer->name(), 'printer' => ['id' => $printer->id, 'key' => $printer->key, 'model' => $printer->model],
                    'material' => $order->material->code, 'quality' => $quality, 'layer_mm' => $layer, 'strength' => $order->strength, 'copies' => $order->copies,
                    'infill_percent' => $infill, 'unit_scale' => $order->unit_scale, 'scale' => (float) $order->scale, 'profiles' => $profiles, 'overrides' => $overrides,
                    'profile_layers' => $profile->layers, 'profile_fingerprint' => $profile->sliceFingerprint(),
                    'profile_hashes' => $this->profileHashes($profiles), 'preparer' => $order->isTest() ? 'php-stl' : $preparer->name(), 'sliced_at' => now()->toIso8601String(),
                    'timelapse_minutes' => $parking,
                ],
                'slice_result' => $result->toArray() + ['plates' => $plates, 'plate_copies' => $perPlate, 'rest_copies' => $rest, 'rest' => $restResult?->toArray()],
                // the whole order: every full plate, plus the last one when it differs
                'est_minutes' => (int) round($sum($result->minutes, $restResult?->minutes)) + $parking,
                'est_grams' => round($sum($result->grams, $restResult?->grams), 1),
                'est_meters' => round($sum((float) ($result->meters ?? 0), $restResult?->meters), 2),
                'supports_used' => $result->supportsUsed,
            ])->save();

            $clock->add('post', (hrtime(true) - $postStart) / 1e9)->note('print_minutes', $order->est_minutes);

            // ── 3. price ───────────────────────────────────────────────────────
            $this->price($order, $printer, $settings, $orders, $flow, $clock);
        } catch (\Throwable $e) {
            Log::warning('PrepareFarmOrder failed', ['order' => $order->id, 'error' => $e->getMessage()]);
            $clock->note('failed', true);
            $flow->fail($order, 'slicing_failed', 'system', detail: mb_substr($e->getMessage(), 0, 1000));
        } finally {
            // only the column: whatever the steps above saved stays as it is
            $clock->note('finished', round(microtime(true), 1));
            $clock->add('total', (float) $clock->toArray()['finished'] - (float) $clock->toArray()['started']);
            FarmOrder::whereKey($order->id)->update(['timings' => json_encode($clock->toArray())]);
        }
    }

    /**
     * What the slicer is given: the printer's profiles, the quality's layer, the strength's infill, the customer's and
     * the admin's overrides, supports as the model wants them.
     *
     * @return array{params: SliceParams, profiles: array, overrides: array, profile: PrintProfile, layer: float, infill: int, quality: string}
     */
    private function sliceSetup(FarmOrder $order, FarmSettings $settings): array
    {
        $printer = $order->printer;
        $quality = $order->quality;
        // the quality settings are written for a 0.4 nozzle; a finer nozzle prints the whole ladder finer
        $layer = $printer->layerFor($settings->layerFor($quality));
        // the customer's own numbers (the "advanced" settings) win over the presets; the admin's overrides over everything
        $custom = PrintSettings::of($order);
        $infill = $custom['infill'] ?? $settings->infillFor($order->strength);
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
        if ($order->isTest()) {
            // the bridges and overhangs of a test object are the test: never prop them up
            $overrides['process']['enable_support'] = '0';
            // a test object stands on its own base plate: a brim only glues it harder to the build plate, and a
            // fine-nozzle test bent while it was prised off (Kobra S1 #2, 26 Sep 2026)
            $overrides['process']['brim_type'] = 'no_brim';
        }
        // the customer knows the model (made to print in place, supports would only weld its joints together), or
        // the model comes from one of our tools and was built to print without them (a cap, a holder, a box)
        $builtWithout = ($order->modelFile?->printHints()['supports'] ?? null) === false;
        $noSupports = $order->supports === 'off' || ($order->supports !== 'on' && $builtWithout);
        if ($noSupports) {
            $overrides['process']['enable_support'] = '0';
        }
        $overrides['process'] = PrintSettings::process($custom) + $overrides['process'];
        $overrides['process'] = PrintSettings::adminOverrides($order) + $overrides['process'];
        $params = (new SliceParams(materialCode: $order->material->code, quality: $quality, infillPercent: $infill, supports: $order->isTest() || $noSupports ? false : null, treeSupports: true))
            ->withFarmProfile($profiles, $overrides);

        return compact('params', 'profiles', 'overrides', 'profile', 'layer', 'infill', 'quality');
    }

    /** The sliced order gets its price and moves on: to the customer (sliced), or straight on when nobody pays or has paid already. */
    private function price(FarmOrder $order, $printer, FarmSettings $settings, OrderService $orders, OrderFlow $flow, Stopwatch $clock): void
    {
        if ($order->isFree()) {
            // the farm's own print (test or YouTube showcase): nobody pays, it goes straight to the queue
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
        $price = $clock->measure('price', fn () => $orders->priceFor($order, $printer));
        $order->fill(['price' => $price, 'price_total' => $price['total'], 'royalty_czk' => $price['royalty_unit'] ?? null, 'currency' => $price['currency'], 'stage' => null])->save();
        $flow->move($order, FarmOrder::STATUS_SLICED, 'system');
    }

    /**
     * An order printed by parts: every separately printed part of the design becomes a plate of its own (the copies of
     * that part laid out on it), sliced on its own, printed from the spool chosen for it. The order's figures are the
     * sums; its check, orientation and preview are those of the first plate.
     */
    private function prepareParts(FarmOrder $order, PrintPreparer $preparer, Slicer $slicer, FarmSettings $settings, OrderService $orders, OrderFlow $flow, Stopwatch $clock, $disk, Dimensions $bed): void
    {
        $file = $order->modelFile;
        $printer = $order->printer;
        $skeleton = $order->partPlates();
        if (count($skeleton) < 2) {
            throw new \RuntimeException('An order by parts needs at least two parts.');
        }
        $margin = 2 * (float) $settings->get('bed_margin_mm');
        $usable = new Dimensions($bed->x - $margin, $bed->y - $margin, $bed->z);
        $setup = $this->sliceSetup($order, $settings);
        $timelapse = $printer->timelapseFor($order);
        $scale = $order->unit_scale * (float) ($order->scale ?: 1);
        File::ensureDirectoryExists($disk->path($order->dir()));
        $plates = [];
        $first = null;
        $minutes = $grams = $meters = $parking = 0.0;
        $supports = false;
        $maxCopies = null;
        foreach ($skeleton as $i => $plate) {
            $n = $i + 1;
            $order->update(['stage' => 'checking']);
            $src = $orders->partStl($file, (string) $plate['part']) ?? throw new \RuntimeException('The part '.$plate['part'].' of the design cannot be built.');
            $stlRel = $order->dir().'/part-'.$n.'.stl';
            @unlink($disk->path($stlRel).'.stage');
            try {
                $mesh = $clock->measure('prepare', fn () => $preparer->prepare($src['path'], $disk->path($stlRel), $scale, $bed, $file->builtForPrinting()));
            } finally {
                if ($src['temp']) {
                    @unlink($src['path']);
                }
            }
            @unlink($disk->path($stlRel).'.stage');
            $piece = $mesh;
            $capacity = PlateLayout::capacity($piece->bbox, $usable);
            $maxCopies = $maxCopies === null ? $capacity['max'] : min($maxCopies, $capacity['max']);
            if ($order->copies > 1 && $capacity['max'] >= $order->copies) {
                $plateRel = $order->dir().'/part-'.$n.'-plate.stl';
                $mesh = $clock->measure('layout', fn () => PlateLayout::replicate($piece, $disk->path($plateRel), $order->copies, $usable)) ?? throw new \RuntimeException('Plate layout failed although the capacity allowed it.');
                $stlRel = $plateRel;
            }
            $verdict = ModelValidator::judge($mesh, $bed, ['min_model_mm' => (float) $settings->get('min_model_mm'), 'bed_margin_mm' => (float) $settings->get('bed_margin_mm')]);
            if ($verdict['ok'] && $order->copies > 1 && $capacity['max'] < $order->copies) {
                // the copies of this part do not fit one plate: an order by parts prints each part on one plate
                $verdict = ['ok' => false, 'errors' => [['code' => 'copies_fit', 'data' => ['part' => $plate['part'], 'max' => $capacity['max']]]], 'warnings' => $verdict['warnings'] ?? []] + $verdict;
            }
            if ($first === null) {
                $raw = Dimensions::fromArray((array) $file->bbox);
                $order->check = $verdict + ['mesh' => $mesh->toArray(), 'unit_guess' => ModelValidator::guessUnit($raw, $bed), 'raw_dims' => $raw->toArray(), 'piece_dims' => $piece->bbox->toArray(), 'max_copies' => $capacity['max']];
                $order->orientation = $mesh->orientation;
                $order->print_stl_path = $stlRel;
                $order->save();
            }
            if (! $verdict['ok']) {
                $order->check = $verdict + ['part' => $plate['part']] + (array) $order->check;
                $order->save();
                $flow->fail($order, $verdict['errors'][0]['code'], 'system', detail: (string) $plate['part']);

                return;
            }
            $order->update(['stage' => 'slicing']);
            $result = $clock->measure('slice', fn () => $slicer->slice($disk->path($stlRel), $setup['params']));
            $clock->merge($result->timings, 'p'.$n.'_');
            if (! $result->gcodePath || ! is_file($result->gcodePath)) {
                throw new \RuntimeException('Slicer returned no G-code for the part '.$plate['part'].'.');
            }
            $gcodeRel = $order->dir().'/part-'.$n.'.gcode';
            File::move($result->gcodePath, $disk->path($gcodeRel));
            $park = $timelapse ? TimelapseGcode::extraMinutesForFile($disk->path($gcodeRel), $timelapse, (float) $printer->bed_x, (float) $printer->bed_y) : 0;
            $parking += $park;
            $minutes += $result->minutes;
            $grams += $result->grams;
            $meters += (float) ($result->meters ?? 0);
            $supports = $supports || $result->supportsUsed;
            $plates[] = ['part' => (string) $plate['part'], 'slot_id' => (int) ($plate['slot_id'] ?? 0), 'color_id' => (int) ($plate['color_id'] ?? 0), 'copies' => (int) $order->copies,
                'stl_path' => $stlRel, 'gcode_path' => $gcodeRel, 'sha256' => hash_file('sha256', $disk->path($gcodeRel)),
                'minutes' => (int) round($result->minutes), 'grams' => round($result->grams, 1), 'meters' => round((float) ($result->meters ?? 0), 2),
                'dims' => $mesh->bbox->toArray(), 'piece_dims' => $piece->bbox->toArray(), 'supports' => (bool) $result->supportsUsed];
            $first ??= $result;
        }
        $order->check = ['max_copies' => $maxCopies] + (array) $order->check;
        $order->fill([
            'plates' => count($plates), 'plates_done' => 0, 'plate_copies' => (int) $order->copies, 'rest_copies' => null, 'rest_gcode_path' => null,
            'part_plates' => $plates,
            'gcode_path' => $plates[0]['gcode_path'], 'gcode_sha256' => $plates[0]['sha256'],
            'slice_params' => [
                'engine' => $slicer->name(), 'printer' => ['id' => $printer->id, 'key' => $printer->key, 'model' => $printer->model],
                'material' => $order->material->code, 'quality' => $setup['quality'], 'layer_mm' => $setup['layer'], 'strength' => $order->strength, 'copies' => $order->copies,
                'infill_percent' => $setup['infill'], 'unit_scale' => $order->unit_scale, 'scale' => (float) $order->scale, 'profiles' => $setup['profiles'], 'overrides' => $setup['overrides'],
                'profile_layers' => $setup['profile']->layers, 'profile_fingerprint' => $setup['profile']->sliceFingerprint(),
                'profile_hashes' => $this->profileHashes($setup['profiles']), 'preparer' => $preparer->name(), 'sliced_at' => now()->toIso8601String(),
                'timelapse_minutes' => (int) round($parking), 'by_parts' => true,
            ],
            'slice_result' => $first->toArray() + ['plates' => count($plates), 'parts' => array_map(fn ($p) => ['part' => $p['part'], 'minutes' => $p['minutes'], 'grams' => $p['grams']], $plates)],
            'est_minutes' => (int) round($minutes) + (int) round($parking),
            'est_grams' => round($grams, 1),
            'est_meters' => round($meters, 2),
            'supports_used' => $supports,
        ])->save();
        $clock->note('print_minutes', $order->est_minutes)->note('parts', count($plates));
        $this->price($order, $printer, $settings, $orders, $flow, $clock);
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
