<?php

namespace App\Http\Controllers\Api;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\Footprint;
use App\Domain\Farm\PlateLayout;
use App\Engines\DTO\Dimensions;
use App\Engines\Repair\PythonTool;
use App\Http\Controllers\Controller;
use App\Models\DesignerModel;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * "N pieces fit on one plate" for the calculator, by the same PlateLayout the farm lays the plate with: the model's
 * shadow (convex hull seen from above, as the file lies) instead of its bounding rectangle. The page shows its own
 * rectangle estimate until this answers; the estimate is never more than this.
 */
class PlateController extends Controller
{
    /** Without Python the shadow is read in PHP, only from files up to this size (300k triangles, a few seconds). */
    private const PHP_BYTES = 15_000_000;

    /** POST /api/calc/plate — {file: uuid, scale?, quantity?, bed?} or {dims: {x,y,z}, footprint: [[x,y],…], quantity?, bed?} */
    public function show(Request $request, FarmSettings $settings, PythonTool $python): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required_without:dims', 'nullable', 'uuid'],
            'scale' => ['nullable', 'numeric', 'min:0.1', 'max:'.config('pricing.max_scale', 4)],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'dims' => ['required_without:file', 'array'],
            'dims.x' => ['required_with:dims', 'numeric', 'min:0.1', 'max:2000'],
            'dims.y' => ['required_with:dims', 'numeric', 'min:0.1', 'max:2000'],
            'dims.z' => ['required_with:dims', 'numeric', 'min:0.1', 'max:2000'],
            'footprint' => ['nullable', 'array', 'max:'.Footprint::MAX_POINTS],
            'footprint.*' => ['array', 'size:2'],
            'footprint.*.*' => ['numeric', 'between:-2000,2000'],
            'bed' => ['nullable', 'array'],
            'bed.x' => ['required_with:bed', 'numeric', 'min:20', 'max:1000'],
            'bed.y' => ['required_with:bed', 'numeric', 'min:20', 'max:1000'],
            'bed.z' => ['required_with:bed', 'numeric', 'min:20', 'max:1000'],
        ]);
        $bed = Dimensions::fromArray($data['bed'] ?? (array) config('pricing.bed_mm'));
        // the farm keeps this much clear at every edge (the same as /api/config bed_margin_mm)
        $margin = config('farm.enabled') ? 2 * (float) $settings->get('bed_margin_mm') : 0.0;
        $usable = new Dimensions($bed->x - $margin, $bed->y - $margin, $bed->z);
        $quantity = (int) ($data['quantity'] ?? 1);

        if (! empty($data['file'])) {
            $file = ModelFile::where('uuid', $data['file'])->first();
            abort_unless($file && $file->isReady() && $file->bbox, 404);
            abort_unless(self::mayRead($request, $file), 403);
            $scale = (float) ($data['scale'] ?? 1);
            $dims = Dimensions::fromArray((array) $file->bbox)->scaled($scale);
            // the shadow of the stored model once, every scale from it
            $shadowKey = 'plate:shadow:'.$file->uuid.':'.$file->sha256;
            $points = Cache::get($shadowKey);
            if (! is_array($points)) {
                $points = self::shadowOf($file, $python);
                if ($points !== []) {
                    Cache::put($shadowKey, $points, now()->addDays(7));
                }
            }
            $shadow = $points ? Footprint::fromArray($points)?->scaled($scale) : null;
            $key = $file->uuid.':'.$file->sha256.':'.round($scale, 4);
        } else {
            $dims = Dimensions::fromArray($data['dims']);
            $shadow = Footprint::fromArray($data['footprint'] ?? null);
            $key = md5(json_encode([$data['dims'], $shadow?->toArray()]));
        }

        $answer = Cache::remember('plate:layout:'.$key.':'.implode('x', [$usable->x, $usable->y, $usable->z]).':'.$quantity, now()->addDay(), function () use ($shadow, $dims, $usable, $quantity) {
            $max = PlateLayout::plan($shadow, $dims, $usable)['max'];
            $perPlate = $max > 0 ? min($quantity, $max) : 0;
            $layout = $perPlate > 0 ? PlateLayout::plan($shadow, $dims, $usable, $perPlate) : ['method' => 'grid'];

            return [
                'max' => $max,
                'perPlate' => $perPlate,
                'plates' => $max > 0 ? (int) ceil($quantity / $max) : 0,
                'layout' => array_intersect_key($layout, array_flip(['method', 'cols', 'rows', 'rotated', 'placements', 'size', 'turn', 'alternate', 'odd_rows', 'per_row'])),
            ];
        });

        return response()->json($answer);
    }

    /**
     * The shadow of the stored model as it lies: by Python when it is there (engines/python/plate_shadow.py, a 50 MB
     * file in a second), else in PHP for a file of up to PHP_BYTES; [] when it cannot be had or the file is broken,
     * and the calculator keeps the count by the rectangle.
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function shadowOf(ModelFile $file, PythonTool $python): array
    {
        $path = (string) $file->absoluteStlPath();
        try {
            if ($python->available()) {
                return Footprint::fromArray($python->runScript('plate_shadow.py', [$path], 60)['footprint'] ?? null)?->toArray() ?? [];
            }
            if (is_file($path) && filesize($path) <= self::PHP_BYTES) {
                return Footprint::fromStl($path)?->toArray() ?? [];
            }
        } catch (\Throwable) {
            // a broken file or a tool that failed: the rectangle counts
        }

        return [];
    }

    /** A designer's file that is not offered for download is not measured for strangers either (as GuardModelFile). */
    private static function mayRead(Request $request, ModelFile $file): bool
    {
        if ($file->origin !== 'portfolio') {
            return true;
        }
        $user = $request->user();
        if ($user && ($user->id === $file->owner_user_id || $user->isAdmin())) {
            return true;
        }
        $card = DesignerModel::where('model_file_id', $file->id)->first();

        return (bool) ($card && $card->download_allowed && $card->getAttribute('visible'));
    }
}
