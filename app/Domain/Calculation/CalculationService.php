<?php

namespace App\Domain\Calculation;

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
    ) {}

    public function create(ModelFile $file, array $params, ?AnonymousSession $session, ?User $user, ?array $clientGeometry = null): Calculation
    {
        $sliceParams = SliceParams::fromArray($params);
        $quantity = max(1, min(1000, (int) ($params['quantity'] ?? 1)));
        if (! $this->materials->has($sliceParams->materialCode)) {
            $sliceParams = SliceParams::fromArray(['material' => $this->materials->defaultCode()] + $params);
        }

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
            $calc->rough = $this->roughFor((float) $volume, $area !== null ? (float) $area : null, $sliceParams, $quantity, $source['profiles']);
        }

        // Reuse a finished slice for the same file + parameters instead of slicing again (prices are recomputed:
        // the viewer or the printers in range may differ).
        $cached = Calculation::query()
            ->where('model_file_id', $file->id)
            ->where('params_hash', $calc->params_hash)
            ->where('status', Calculation::STATUS_DONE)
            ->whereNotNull('slicer')
            ->latest('id')
            ->first();

        if ($cached) {
            $calc->slicer = $cached->slicer;
            $calc->slicer_engine = $cached->slicer_engine;
            $calc->prices = $this->pricesFor((float) $cached->slicer['grams'], (int) $cached->slicer['minutes'], $quantity, $source['profiles']);
            $calc->status = Calculation::STATUS_DONE;
            $calc->save();

            return $calc;
        }

        $calc->save();

        $sliceable = in_array($sliceParams->materialCode, $this->materials->sliceable(), true);
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

    /** @param  PricingProfile[]|null  $profiles */
    public function roughFor(float $volumeMm3, ?float $areaMm2, SliceParams $p, int $quantity, ?array $profiles = null): array
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
        [$min, $max] = $this->prices->range($breakdowns, rough: true);

        return $est + [
            'price_min' => $min,
            'price_max' => $max,
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
