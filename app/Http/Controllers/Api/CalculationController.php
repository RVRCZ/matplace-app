<?php

namespace App\Http\Controllers\Api;

use App\Domain\Calculation\CalculationService;
use App\Domain\Calculation\PricingSource;
use App\Domain\Files\PaintedColors;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\ModelFile;
use App\Support\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalculationController extends Controller
{
    /** POST /api/calculations — {file: uuid, material, quality, infill, supports, scale, quantity, geometry?, rough_only?} */
    public function store(Request $request, CalculationService $service): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'uuid'],
            'material' => ['nullable', 'string', 'max:10'],
            'quality' => ['nullable', 'in:draft,standard,fine'],
            'infill' => ['nullable', 'integer', 'min:0', 'max:100'],
            'supports' => ['nullable'],
            'scale' => ['nullable', 'numeric', 'min:0.1', 'max:'.config('pricing.max_scale', 4)],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'vase' => ['nullable', 'boolean'],
            'geometry' => ['nullable', 'array'],
            'geometry.volume_mm3' => ['nullable', 'numeric', 'min:0'],
            'geometry.area_mm2' => ['nullable', 'numeric', 'min:0'],
            'geometry.overhang_share' => ['nullable', 'numeric', 'min:0', 'max:1'],   // the range by shape (ShapeRange) follows the browser's measure of the overhangs and the box
            'geometry.bbox' => ['nullable', 'array'],
            'geometry.bbox.x' => ['nullable', 'numeric', 'min:0'],
            'geometry.bbox.y' => ['nullable', 'numeric', 'min:0'],
            'geometry.bbox.z' => ['nullable', 'numeric', 'min:0'],
            'geometry.triangles' => ['nullable', 'integer', 'min:0'],
            'rough_only' => ['nullable', 'boolean'],   // a big model on the first page: the estimate and its range, no slice (TODO #41)
        ]);

        $file = ModelFile::where('uuid', $data['file'])->firstOrFail();
        $calc = $service->create(
            $file,
            $data,
            $request->attributes->get('anon_session'),
            $request->user(),
            $data['geometry'] ?? null,
            ! $request->boolean('rough_only'),
        );
        Track::event('calculation', $file, array_filter(['tool' => $file->origin === 'tool' ? $file->kind() : null]));

        return response()->json(['calculation' => self::measured($calc->load('modelFile'))], 201);
    }

    /** GET /api/calculations/{token} */
    public function show(Calculation $calculation): JsonResponse
    {
        return response()->json(['calculation' => self::measured($calculation->load('modelFile'))]);
    }

    /**
     * The answer the browser gets. When it carries the result, its owner has seen the exact price (from the slicer) or
     * the failure: measured once (docs/AJ.md). A rough estimate shows no price while the marketplace is off: no event.
     */
    private static function measured(Calculation $calculation): array
    {
        $described = self::describe($calculation);
        $tool = $calculation->modelFile?->origin === 'tool' ? $calculation->modelFile->kind() : null;
        if ($calculation->status === Calculation::STATUS_DONE && ! empty($described['prices'])) {
            Track::outcome('price_shown', $calculation, array_filter(['kind' => 'exact', 'tool' => $tool]));
        } elseif ($calculation->status === Calculation::STATUS_FAILED) {
            Track::outcome('failed', $calculation, array_filter(['step' => 'calculation', 'tool' => $tool]));
        }

        return $described;
    }

    /**
     * Stored prices, except when the marketplace is off: then a calculation shared from before shows the farm's
     * current list, not the printers' lists it was computed with.
     */
    private static function currentPrices(Calculation $c): ?array
    {
        if (config('features.marketplace') || ! $c->prices || ! $c->slicer || ! empty($c->pricing_context['farm'])) {
            return $c->prices;
        }
        $service = app(CalculationService::class);
        $profiles = app(PricingSource::class)->fromContext(['farm' => true], (string) ($c->params['material'] ?? 'PLA'));

        return $service->pricesFor((float) ($c->slicer['grams'] ?? 0), (int) ($c->slicer['minutes'] ?? 0), (int) ($c->params['quantity'] ?? 1), $profiles);
    }

    /**
     * The first price list's total in one colour (the price as shown) and printed in the file's painted colours on one
     * plate (PaintedColors::extra: the tower and the changes on top of the same grams and minutes); null for a file
     * without colours or a calculation without a price yet.
     *
     * @return array{one: float, multi: float, count: int, changes: int}|null
     */
    private static function colorPrices(Calculation $c, ?array $prices): ?array
    {
        $file = $c->modelFile;
        if (! $file || PaintedColors::printed($file) === []) {
            return null;
        }
        $material = (string) ($c->params['material'] ?? 'PLA');
        $extra = PaintedColors::extra($file, (string) ($c->params['quality'] ?? 'standard'), (float) ($c->params['scale'] ?? 1), $material);
        $done = $c->status === Calculation::STATUS_DONE && $prices && $c->slicer;
        $rough = is_array($c->rough) ? $c->rough : null;
        $list = $done ? $prices : ($rough['prices'] ?? null);
        if (! $extra || ! $list || ! isset($list[0]['total'])) {
            return null;
        }
        $grams = (float) ($done ? ($c->slicer['grams'] ?? 0) : ($rough['grams'] ?? 0));
        $minutes = (int) ($done ? ($c->slicer['minutes'] ?? 0) : ($rough['minutes'] ?? 0));
        $quantity = max(1, (int) ($c->params['quantity'] ?? 1));
        // the copies share the plate and its changes: per piece, the tower and the changes divided among them
        $profiles = app(PricingSource::class)->fromContext($c->pricing_context ?? ($done ? null : ['farm' => true]), $material);
        $multi = app(CalculationService::class)->pricesFor($grams + $extra['grams'] / $quantity, $minutes + (int) ceil($extra['minutes'] / $quantity), $quantity, $profiles);

        return ['one' => (float) $list[0]['total'], 'multi' => (float) ($multi[0]['total'] ?? $list[0]['total']), 'count' => $extra['count'], 'changes' => $extra['changes']];
    }

    public static function describe(Calculation $c): array
    {
        return [
            'token' => $c->token,
            'url' => route('calc.share', $c->token),
            'status' => $c->status,
            'error' => $c->error,
            'params' => $c->params,
            'rough' => config('features.marketplace') || ! is_array($c->rough) ? $c->rough : collect($c->rough)->except(['prices'])->all(),   // printers' rough lists never leak past the switch
            'slicer' => $c->slicer ? collect($c->slicer)->except(['gcode_path', 'raw'])->all() : null,
            'prices' => $prices = self::currentPrices($c),
            // a painted 3MF (docs/AL.md §14): the price in one colour and in its colours, with the colour changes of one plate
            'colors' => self::colorPrices($c, $prices),
            'file' => $c->modelFile ? UploadController::describe($c->modelFile) : null,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
