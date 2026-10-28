<?php

namespace App\Domain\Calculation;

use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Jobs\SliceCalculation;
use App\Models\AnonymousSession;
use App\Models\Calculation;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Str;

/** Creates calculations, fills the rough estimate immediately and queues the precise slice. */
final class CalculationService
{
    public function __construct(
        private readonly RoughEstimator $rough,
        private readonly PriceEngine $prices,
        private readonly MaterialCatalog $materials,
        private readonly PricingSource $pricingSource,
        private readonly ShapeRange $range,
    ) {}

    /**
     * @param  bool  $sliceNow  false: the estimate alone, nothing is queued (a big model on the first page, TODO #41: the order page
     *                          slices once the customer has chosen); a finished slice of the same settings is still reused
     */
    public function create(ModelFile $file, array $params, ?AnonymousSession $session, ?User $user, ?array $clientGeometry = null, bool $sliceNow = true): Calculation
    {
        $sliceParams = SliceParams::fromArray($params);
        $quantity = max(1, min(1000, (int) ($params['quantity'] ?? 1)));
        if (! $this->materials->has($sliceParams->materialCode)) {
            $sliceParams = SliceParams::fromArray(['material' => $this->materials->defaultCode()] + $params);
        }
        // what the design asks of the slicing process (a medal irons its top): sliced and priced with it, as the farm does
        $sliceParams = $sliceParams->withProcess($file->processHints());

        // Geometry: server statistics when the file is processed, otherwise what the browser measured.
        $volume = $file->volume_mm3 ?? ($clientGeometry['volume_mm3'] ?? null);
        $area = $file->area_mm2 ?? ($clientGeometry['area_mm2'] ?? null);

        $lat = isset($params['lat']) ? (float) $params['lat'] : $user?->lat;
        $lng = isset($params['lng']) ? (float) $params['lng'] : $user?->lng;
        $source = $this->pricingSource->resolve($sliceParams->materialCode, $user, $lat, $lng);

        $calc = new Calculation;
        $calc->token = Str::lower(Str::random(12));
        $calc->model_file_id = $file->id;
        $calc->owner_user_id = $user?->id;
        $calc->anonymous_session_id = $session?->id;
        $calc->params = $sliceParams->toArray() + ['quantity' => $quantity];
        $calc->params_hash = sha1(json_encode($calc->params));
        $calc->pricing_context = $source['context'];
        $calc->status = Calculation::STATUS_ROUGH;

        if ($volume !== null) {
            // the shape the range follows: the file's box once it is measured (else the browser's), the browser's overhang measure
            $shape = ['bbox' => $file->bbox ?: ($clientGeometry['bbox'] ?? null), 'overhang_share' => $clientGeometry['overhang_share'] ?? null, 'triangles' => BigModel::triangles($file) ?: ($clientGeometry['triangles'] ?? null)];
            $calc->rough = $this->roughFor((float) $volume, $area !== null ? (float) $area : null, $sliceParams, $quantity, $source['profiles'], $shape);
        }

        // Reuse a finished slice for the same file + parameters instead of slicing again (prices are recomputed:
        // the viewer or the printers in range may differ). The quantity is not a parameter of the slice: one piece
        // is sliced and the price counts the pieces, so 1 → 5 pieces needs no slicer.
        $slice = fn (array $p) => json_encode(array_diff_key($p, ['quantity' => 1]));
        $cached = Calculation::query()
            ->where('model_file_id', $file->id)
            ->where('status', Calculation::STATUS_DONE)
            ->whereNotNull('slicer')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'params', 'slicer', 'slicer_engine'])
            ->first(fn (Calculation $c) => $slice((array) $c->params) === $slice($calc->params));

        if ($cached) {
            $calc->slicer = $cached->slicer;
            $calc->slicer_engine = $cached->slicer_engine;
            $calc->prices = $this->pricesFor((float) $cached->slicer['grams'], (int) $cached->slicer['minutes'], $quantity, $source['profiles']);
            $calc->status = Calculation::STATUS_DONE;
            $calc->timings = ['cache' => 'calculation'];
            $calc->save();

            return $calc;
        }

        $calc->save();

        $sliceable = in_array($sliceParams->materialCode, $this->materials->sliceable(), true);
        if ($sliceable && self::printedByParts($file, $sliceParams->scale)) {
            // a design of several parts larger than the plate (a filament picture of five plates and a frame, docs/AN.md §17.2):
            // the slicer would refuse it as one piece; the farm prints it by parts on the order page, so here the estimate
            // is the answer and the page says why
            $calc->rough = ((array) $calc->rough) + ['by_parts' => true];
            $calc->save();

            return $calc;
        }
        if ($sliceable && ! $sliceNow) {
            return $calc;   // stays rough: the estimate with its range is the answer until the order page slices
        }
        if ($sliceable) {
            $calc->status = Calculation::STATUS_QUEUED;
            $calc->save();
            SliceCalculation::dispatch($calc->id);
            $calc->refresh(); // sync queue (tests, dev) may have finished already
        } else {
            // Non-sliceable materials (resin, PA): rough estimate is the final answer.
            $calc->status = Calculation::STATUS_DONE;
            $calc->save();
        }

        return $calc;
    }

    /**
     * A design of several parts whose whole does not fit the plate (config pricing.bed_mm, turned any way): sliced as one
     * piece it fails, printed by parts it is what the order page does.
     */
    public static function printedByParts(ModelFile $file, float $scale = 1.0): bool
    {
        if (count($file->parts()) < 2 || ! is_array($file->bbox)) {
            return false;
        }
        $bed = (array) config('pricing.bed_mm');
        $dims = new Dimensions((float) ($file->bbox['x'] ?? 0) * $scale, (float) ($file->bbox['y'] ?? 0) * $scale, (float) ($file->bbox['z'] ?? 0) * $scale);

        return ! $dims->fits((float) ($bed['x'] ?? 0), (float) ($bed['y'] ?? 0), (float) ($bed['z'] ?? 0));
    }

    /**
     * @param  PricingProfile[]|null  $profiles
     * @param  ?array{bbox?: ?array, overhang_share?: ?float, triangles?: ?int}  $shape  what is known of the model's shape; the range follows it (ShapeRange), nothing = the widest
     */
    public function roughFor(float $volumeMm3, ?float $areaMm2, SliceParams $p, int $quantity, ?array $profiles = null, ?array $shape = null): array
    {
        $est = $this->rough->estimate(
            volumeMm3: $volumeMm3,
            areaMm2: $areaMm2,
            materialCode: $p->materialCode,
            quality: $p->quality,
            infillPercent: $p->infillPercent,
            supports: (bool) $p->supports,
            scale: $p->scale,
            vaseMode: $p->vaseMode,
        );
        $profiles ??= $this->prices->orientationProfiles();
        $breakdowns = $this->prices->priceAll($est['grams'], $est['minutes'], $quantity, $profiles);
        $bbox = is_array($shape['bbox'] ?? null) ? $shape['bbox'] : null;
        $height = $bbox ? (float) ($bbox['z'] ?? 0) * $p->scale : null;
        $fill = $bbox ? ShapeRange::fill((float) $est['grams'], $bbox, $p->scale) : null;
        $overhang = isset($shape['overhang_share']) ? max(0.0, min(1.0, (float) $shape['overhang_share'])) : null;
        $triangles = isset($shape['triangles']) && (int) $shape['triangles'] > 0 ? (int) $shape['triangles'] : null;
        $factors = $this->range->factors($height, $fill, $overhang, $p->supports, (int) $est['minutes'], $triangles);
        [$min, $max] = $this->prices->range($breakdowns, $factors['price']);

        return $est + [
            'price_min' => $min,
            'price_max' => $max,
            // how far the slice may land, by the shape (docs/M.md §6): the page shows the time as this range, the order page recounts from the factors
            'minutes_min' => max(1, (int) floor($est['minutes'] * $factors['time'][0])),
            'minutes_max' => max(1, (int) ceil($est['minutes'] * $factors['time'][1])),
            'range' => $factors,
            'shape' => ['height_mm' => $height !== null ? round($height, 1) : null, 'fill_g_cm3' => $fill !== null ? round($fill, 4) : null, 'overhang_share' => $overhang, 'triangles' => $triangles],
            'prices' => array_map(fn ($b) => $b->toArray(), $breakdowns),
        ];
    }

    /** Called by the slicing job when the precise result is available. */
    public function applySlice(Calculation $calc, SliceResult $result, string $engine): void
    {
        $quantity = (int) ($calc->params['quantity'] ?? 1);
        $profiles = $this->pricingSource->fromContext($calc->pricing_context, (string) ($calc->params['material'] ?? 'PLA'));
        $bed = config('pricing.bed_mm');
        $warnings = $result->warnings;
        if (! $result->dims->fits((float) $bed['x'], (float) $bed['y'], (float) $bed['z'])) {
            $warnings[] = 'exceeds_typical_bed';
        }
        $calc->slicer = ['warnings' => array_values(array_unique($warnings))] + $result->toArray();
        $calc->slicer_engine = $engine;
        $calc->prices = $this->pricesFor($result->grams, $result->minutes, $quantity, $profiles);
        $calc->status = Calculation::STATUS_DONE;
        $calc->error = null;
        $calc->save();
    }

    /** @param  PricingProfile[]  $profiles */
    public function pricesFor(float $grams, int $minutes, int $quantity, array $profiles): array
    {
        return array_map(fn ($b) => $b->toArray(), $this->prices->priceAll($grams, $minutes, $quantity, $profiles));
    }
}
