<?php

namespace App\Http\Controllers\Api;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Farm\ColorLimit;
use App\Domain\Farm\FarmPricingProfile;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\FarmState;
use App\Domain\Farm\Palette;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\Converter\ConverterChain;
use App\Engines\DTO\SliceParams;
use App\Http\Controllers\Controller;
use App\Models\FarmOrder;
use App\Support\CalculatorTexts;
use App\Support\Locales;
use App\Support\NextStep;
use App\Support\ShippingHint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Constants the browser needs for the instant estimate and the form (the calculator's pages fetch them, with their texts, as one cached JSON: calc()). */
class ConfigController extends Controller
{
    /**
     * The farm's price list for every FDM material of the calculator, each marked with its material: the kind of that
     * material when one is loaded, else the cheapest loaded kind weighed as that material (FarmPricingProfile). Empty
     * when the marketplace is on, the farm is off or nothing is loaded.
     *
     * @return list<array<string, mixed>>
     */
    public static function farmProfiles(MaterialCatalog $materials): array
    {
        if (config('features.marketplace') || ! config('farm.enabled')) {
            return [];
        }
        // a closed farm (FarmState) names no price of a print to its visitors; the admin, who orders anyway, keeps it
        // (NextStep::mode() is "farm" only for somebody the farm is offered to, and it is part of the cached JSON's address)
        if (FarmState::isClosed() && NextStep::mode() !== NextStep::FARM) {
            return [];
        }
        $farm = app(FarmPricingProfile::class);
        $out = [];
        foreach ($materials->all() as $m) {
            if (($m['technology'] ?? 'fdm') === 'fdm' && ($profile = $farm->toArray((string) $m['code']))) {
                $out[] = $profile + ['material' => (string) $m['code']];
            }
        }

        return $out;
    }

    /** @param  bool  $colors  the filament palette too (tool pages and the API; the calculator does not need its 250 rows) */
    public static function payload(MaterialCatalog $materials, ConverterChain $converters, bool $colors = false): array
    {
        $items = [];
        foreach ($materials->all() as $m) {
            $items[] = $m + [
                'label' => __('materials.'.$m['code'].'.label'),
                'hint' => __('materials.'.$m['code'].'.hint'),
            ];
        }

        return ($colors ? ['colors' => app(Palette::class)->payload()] : []) + [
            'marketplace' => (bool) config('features.marketplace'),   // false: no prices in the calculator, only the slicer's facts
            'farm' => (bool) config('farm.enabled'),
            'rough' => config('pricing.rough'),
            // the farm's list per material, so the instant estimate counts with the kind the exact price will (FarmPricingProfile);
            // the marketplace's orientation lists when there is no farm price at all
            'orientation_profiles' => self::farmProfiles($materials) ?: config('pricing.orientation_profiles'),
            'round_to' => config('pricing.round_to'),
            'currency' => config('pricing.currency'),
            // what delivery adds, in the visitor's currency and for the country they most likely order to (an estimate; docs/AN.md §3)
            'shipping_hint' => ShippingHint::text(),
            'max_scale' => config('pricing.max_scale'),
            'bed_mm' => config('pricing.bed_mm'),
            // the farm keeps this much clear at every edge of the plate: "N pieces fit" must count with it, or the order page says fewer
            'bed_margin_mm' => config('farm.enabled') ? (float) app(FarmSettings::class)->get('bed_margin_mm') : 0.0,
            'materials' => $items,
            'default_material' => $materials->defaultCode(),
            'qualities' => SliceParams::QUALITIES,
            'formats' => $converters->inputFormats(),
            'max_upload_mb' => (int) config('uploads.max_mb', 120),
            // how many colours one print on the farm takes at most (the machine with the most spool positions, ColorLimit; docs/AL.md §19):
            // a tool page says when a design of flush colours asks for more
            'max_colors' => config('farm.enabled') ? ColorLimit::farm() : FarmOrder::MAX_COLORS,
            'vision' => (string) config('ai.anthropic.api_key') !== '',
            'generator' => app(ModelGenerator::class)->name() !== 'null',
            'lay' => collect(['home', 'decor', 'hand', 'strong', 'outdoor', 'outdoor_light', 'flexible', 'technical', 'detail'])
                ->mapWithKeys(fn ($k) => [$k => __('lay.'.$k)])->all(),
        ];
    }

    public function show(MaterialCatalog $materials, ConverterChain $converters): JsonResponse
    {
        return response()->json(self::payload($materials, $converters, true));
    }

    /**
     * GET /api/calc-config?l=&m=&v=: the calculator's constants and the texts its scripts say, in one language (l) and in
     * the wording of one next step (m), as one JSON the browser keeps for a day; the version in the address (v, a hash of
     * the content, CalculatorTexts::url) changes whenever the content does, so a stale copy is never read (docs/AN.md §12.4).
     */
    public function calc(Request $request, MaterialCatalog $materials, ConverterChain $converters): JsonResponse
    {
        $locale = (string) $request->query('l', app()->getLocale());
        $mode = (string) $request->query('m', NextStep::mode());
        abort_unless(Locales::supported($locale) && in_array($mode, NextStep::MODES, true), 404);
        app()->setLocale($locale);
        NextStep::force($mode);
        try {
            $payload = CalculatorTexts::payload(self::payload($materials, $converters));
        } finally {
            NextStep::force(null);
        }

        return response()->json($payload)->setPublic()->setMaxAge(CalculatorTexts::MAX_AGE);
    }
}
