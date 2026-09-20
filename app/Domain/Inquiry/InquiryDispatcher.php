<?php

namespace App\Domain\Inquiry;

use App\Domain\Calculation\PriceEngine;
use App\Domain\Geo\Geocoder;
use App\Engines\DTO\Dimensions;
use App\Mail\PrinterNewInquiry;
use App\Models\Inquiry;
use App\Models\InquiryDispatch;
use App\Models\PrinterProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Chooses which printers receive an inquiry. Criteria, not an auction:
 * material offered, technology/bed fits, capacity open, not blocked, same country; nearest first when the
 * customer gave a postcode, best rated otherwise. Each printer gets an automatic price from their own price list.
 */
final class InquiryDispatcher
{
    public function __construct(private readonly PriceEngine $prices) {}

    public function dispatch(Inquiry $inquiry): Collection
    {
        $max = (int) config('inquiries.max_printers', 6);
        $material = strtoupper($inquiry->material_code);
        $dims = isset($inquiry->summary['dims']) ? Dimensions::fromArray($inquiry->summary['dims']) : null;

        $candidates = PrinterProfile::query()
            ->with(['materials', 'pricingProfiles', 'machines', 'user'])
            ->where('visible', true)
            ->where('capacity', '!=', 'paused')
            ->whereHas('materials', fn ($m) => $m->where('material_code', $material)->where('in_stock', true))
            ->whereHas('pricingProfiles', fn ($q) => $q->where(fn ($w) => $w->where('hourly_rate', '>', 0)->orWhere('price_per_gram', '>', 0)))
            ->whereHas('user', fn ($u) => $u->whereNull('blocked_at')->where('country', $inquiry->country))
            ->get()
            ->reject(fn (PrinterProfile $p) => $p->user_id === $inquiry->customer_user_id)
            ->filter(fn (PrinterProfile $p) => $this->fitsMachines($p, $dims));

        // a spare part has to be modelled first: printers who offer design come first, the rest only if there are none
        if ($inquiry->kind === 'spare_part') {
            $designers = $candidates->filter(fn (PrinterProfile $p) => in_array('design', (array) $p->services, true));
            $candidates = $designers->isNotEmpty() ? $designers : $candidates;
        }

        $scored = $candidates->map(function (PrinterProfile $p) use ($inquiry) {
            $dist = ($inquiry->lat !== null && $p->user->lat !== null)
                ? Geocoder::distanceKm($inquiry->lat, $inquiry->lng, $p->user->lat, $p->user->lng)
                : null;

            return ['profile' => $p, 'distance' => $dist, 'rating' => (float) $p->user->rating_avg];
        });

        // busy printers only when the customer is not in a hurry
        if ($inquiry->wanted_by && $inquiry->wanted_by->lt(now()->addDays(7))) {
            $scored = $scored->reject(fn ($c) => $c['profile']->capacity === 'busy');
        }

        $sorted = $inquiry->lat !== null
            ? $scored->sortBy([fn ($a, $b) => ($a['distance'] ?? 1e6) <=> ($b['distance'] ?? 1e6), fn ($a, $b) => $b['rating'] <=> $a['rating']])
            : $scored->sortBy([fn ($a, $b) => $b['rating'] <=> $a['rating'], fn ($a, $b) => $a['profile']->id <=> $b['profile']->id]);

        $grams = (float) ($inquiry->summary['grams'] ?? 0);
        $minutes = (int) ($inquiry->summary['minutes'] ?? 0);
        $created = collect();
        foreach ($sorted->take($max)->values() as $rank => $c) {
            /** @var PrinterProfile $p */
            $p = $c['profile'];
            $dto = $p->pricingDto($material);
            $breakdown = ($dto && ($grams > 0 || $minutes > 0)) ? $this->prices->price($grams, $minutes, $inquiry->quantity, $dto) : null;
            $d = InquiryDispatch::updateOrCreate(['inquiry_id' => $inquiry->id, 'printer_profile_id' => $p->id], [
                'rank' => $rank + 1,
                'distance_km' => $c['distance'],
                'auto_price' => $breakdown?->total,
                'auto_breakdown' => $breakdown?->toArray(),
            ]);
            if (! $d->notified_at) {
                $to = $p->contact_email ?: $p->user->email;
                if ($to && $p->user->notify_email) {
                    Mail::to($to)->locale($p->user->locale ?: 'cs')->queue(new PrinterNewInquiry($inquiry, null, $p, ['price' => $breakdown ? number_format($breakdown->total, 0, ',', ' ') : '', 'distance' => $c['distance'] !== null ? (string) round($c['distance']) : '']));
                }
                $d->forceFill(['notified_at' => now()])->save();
            }
            $created->push($d);
        }

        // verified → open even when nobody matched yet (printers can join later; the customer sees the state)
        if ($inquiry->status === Inquiry::STATUS_PENDING && $inquiry->verified_at) {
            $inquiry->update(['status' => Inquiry::STATUS_OPEN]);
        }

        return $created;
    }

    /** A printer with no machine data is not excluded; with machines, at least one bed must fit (any rotation). */
    private function fitsMachines(PrinterProfile $p, ?Dimensions $dims): bool
    {
        if (! $dims || $p->machines->isEmpty()) {
            return true;
        }
        foreach ($p->machines as $m) {
            if (! $m->bed_x || ! $m->bed_y || ! $m->bed_z) {
                return true;
            }
            if ($dims->fits((float) $m->bed_x, (float) $m->bed_y, (float) $m->bed_z)) {
                return true;
            }
        }

        return false;
    }
}
