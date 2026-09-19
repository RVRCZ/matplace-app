<?php

namespace App\Domain\Calculation;

use App\Models\PrinterProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which price lists apply to a calculation.
 * Real printers that offer the material (nearest first when the customer's position is known),
 * the viewer's own price list first when they are a printer, and the platform orientation
 * profiles only when no real printer qualifies.
 */
final class PricingSource
{
    public const MAX_PRINTERS = 8;

    public function __construct(private readonly PriceEngine $engine) {}

    /** @return array{profiles: PricingProfile[], context: array} */
    public function resolve(string $materialCode, ?User $viewer = null, ?float $lat = null, ?float $lng = null): array
    {
        $materialCode = strtoupper($materialCode);
        $own = $viewer?->isPrinter() ? $viewer->printerProfile : null;

        $candidates = $this->candidates($materialCode, $lat, $lng)
            ->reject(fn (PrinterProfile $p) => $own && $p->id === $own->id)
            ->take(self::MAX_PRINTERS);

        $profiles = [];
        if ($own) {
            $dto = $own->load(['materials', 'pricingProfiles'])->pricingDto($materialCode);
            if ($dto) {
                $profiles[] = $dto;
            }
        }
        foreach ($candidates as $p) {
            $dto = $p->pricingDto($materialCode);
            if ($dto) {
                $profiles[] = $dto;
            }
        }

        $context = [
            'printer_profile_ids' => $candidates->pluck('id')->values()->all(),
            'own_printer_profile_id' => $own?->id,
            'lat' => $lat,
            'lng' => $lng,
        ];
        if (! $profiles) {
            $profiles = $this->engine->orientationProfiles();
            $context['orientation'] = true;
        }

        return ['profiles' => $profiles, 'context' => $context];
    }

    /** Rebuild the same price lists later (worker) from a stored context. */
    public function fromContext(?array $context, string $materialCode): array
    {
        if (! $context || ! empty($context['orientation'])) {
            return $this->engine->orientationProfiles();
        }
        $ids = array_values(array_filter(array_merge(
            [$context['own_printer_profile_id'] ?? null],
            $context['printer_profile_ids'] ?? []
        )));
        $byId = PrinterProfile::with(['materials', 'pricingProfiles'])->whereIn('id', $ids)->get()->keyBy('id');
        $profiles = [];
        foreach ($ids as $id) {
            $dto = $byId->get($id)?->pricingDto(strtoupper($materialCode));
            if ($dto) {
                $profiles[] = $dto;
            }
        }

        return $profiles ?: $this->engine->orientationProfiles();
    }

    /** @return Collection<int, PrinterProfile> */
    public function candidates(string $materialCode, ?float $lat, ?float $lng): Collection
    {
        $q = PrinterProfile::query()
            ->with(['materials', 'pricingProfiles', 'user'])
            ->where('visible', true)
            ->where('capacity', '!=', 'paused')
            ->whereHas('materials', fn ($m) => $m->where('material_code', $materialCode)->where('in_stock', true))
            ->whereHas('pricingProfiles', fn ($q) => $q->where(fn ($w) => $w->where('hourly_rate', '>', 0)->orWhere('price_per_gram', '>', 0)))
            ->whereHas('user', fn ($u) => $u->whereNull('blocked_at'));

        if ($lat !== null && $lng !== null) {
            $q->join('users as u', 'u.id', '=', 'printer_profiles.user_id')
                ->select('printer_profiles.*')
                ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(u.lat)) * cos(radians(u.lng) - radians(?)) + sin(radians(?)) * sin(radians(u.lat)))) AS distance_km', [$lat, $lng, $lat])
                ->orderByRaw('distance_km IS NULL, distance_km ASC');
        } else {
            $q->join('users as u', 'u.id', '=', 'printer_profiles.user_id')
                ->select('printer_profiles.*')
                ->orderByDesc('u.rating_avg')->orderBy('printer_profiles.id');
        }

        return $q->limit(self::MAX_PRINTERS + 1)->get();
    }
}
