<?php

namespace App\Http\Controllers\Api;

use App\Domain\Calculation\MaterialCatalog;
use App\Engines\Converter\ConverterChain;
use App\Engines\DTO\SliceParams;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Constants the browser needs for the instant estimate and the form (also inlined into the page). */
class ConfigController extends Controller
{
    public static function payload(MaterialCatalog $materials, ConverterChain $converters): array
    {
        $items = [];
        foreach ($materials->all() as $m) {
            $items[] = $m + [
                'label' => __('materials.'.$m['code'].'.label'),
                'hint' => __('materials.'.$m['code'].'.hint'),
            ];
        }

        return [
            'rough' => config('pricing.rough'),
            'orientation_profiles' => config('pricing.orientation_profiles'),
            'round_to' => config('pricing.round_to'),
            'currency' => config('pricing.currency'),
            'max_scale' => config('pricing.max_scale'),
            'bed_mm' => config('pricing.bed_mm'),
            'materials' => $items,
            'default_material' => $materials->defaultCode(),
            'qualities' => SliceParams::QUALITIES,
            'formats' => $converters->inputFormats(),
            'max_upload_mb' => (int) config('uploads.max_mb', 100),
            'lay' => collect(['home', 'decor', 'hand', 'strong', 'outdoor', 'outdoor_light', 'flexible', 'technical', 'detail'])
                ->mapWithKeys(fn ($k) => [$k => __('lay.'.$k)])->all(),
        ];
    }

    public function show(MaterialCatalog $materials, ConverterChain $converters): JsonResponse
    {
        return response()->json(self::payload($materials, $converters));
    }
}
