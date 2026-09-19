<?php

namespace App\Http\Controllers\Api;

use App\Domain\Calculation\CalculationService;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalculationController extends Controller
{
    /** POST /api/calculations — {file: uuid, material, quality, infill, supports, scale, quantity, geometry?} */
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
        ]);

        $file = ModelFile::where('uuid', $data['file'])->firstOrFail();
        $calc = $service->create(
            $file,
            $data,
            $request->attributes->get('anon_session'),
            $request->user(),
            $data['geometry'] ?? null,
        );

        return response()->json(['calculation' => self::describe($calc->load('modelFile'))], 201);
    }

    /** GET /api/calculations/{token} */
    public function show(Calculation $calculation): JsonResponse
    {
        return response()->json(['calculation' => self::describe($calculation->load('modelFile'))]);
    }

    public static function describe(Calculation $c): array
    {
        return [
            'token' => $c->token,
            'url' => route('calc.share', $c->token),
            'status' => $c->status,
            'error' => $c->error,
            'params' => $c->params,
            'rough' => $c->rough,
            'slicer' => $c->slicer ? collect($c->slicer)->except(['gcode_path', 'raw'])->all() : null,
            'prices' => $c->prices,
            'file' => $c->modelFile ? UploadController::describe($c->modelFile) : null,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
