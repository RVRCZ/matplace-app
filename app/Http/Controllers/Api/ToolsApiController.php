<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ReliefGenerator;
use App\Domain\Tools\SignGenerator;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\ModelFile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ToolsApiController extends Controller
{
    /** POST /api/tools/sign → a model file that opens in the calculator */
    public function sign(Request $request, SignGenerator $signs): JsonResponse
    {
        $data = $request->validate([
            'line1' => ['required', 'string', 'max:40'],
            'line2' => ['nullable', 'string', 'max:40'],
            'font' => ['nullable', 'in:sans,serif,mono'],
            'text_height' => ['nullable', 'numeric', 'min:4', 'max:80'],
            'shape' => ['nullable', 'in:rounded,rect,oval'],
            'thickness' => ['nullable', 'numeric', 'min:1.2', 'max:10'],
            'relief' => ['nullable', 'numeric', 'min:0.4', 'max:5'],
            'style' => ['nullable', 'in:emboss,engrave'],
            'hole' => ['nullable', 'boolean'],
            'border' => ['nullable', 'boolean'],
        ]);
        if (! $signs->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        try {
            $file = $signs->generate($data, $request->attributes->get('anon_session'), $request->user());
        } catch (EngineException $e) {
            return response()->json(['error' => 'generation_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['file' => UploadController::describe($file)], 201);
    }

    private function paramInput(Request $request): array
    {
        $kind = (string) $request->input('kind');
        $request->validate(['kind' => ['required', Rule::in(array_keys(ParametricGenerator::FIELDS))]]);
        $data = $request->validate(ParametricGenerator::rules($kind) + [
            'params' => ['nullable', 'array'],
            'part' => ['nullable', 'in:all,body,lid'],
            'view' => ['nullable', 'in:print,use'],
        ]);

        return [$kind, (array) ($data['params'] ?? []), $data['part'] ?? 'all', $data['view'] ?? 'print'];
    }

    /** POST /api/tools/param/preview {kind, params, part?, view?, download?} → STL + X-Model-Meta (size, volume, notes) */
    public function paramPreview(Request $request, ParametricGenerator $tools): BinaryFileResponse|JsonResponse
    {
        if (! $tools->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$kind, $params, $part, $view] = $this->paramInput($request);
        $built = $tools->build($kind, $params, $part, $view);
        $name = str_replace('_', '-', $kind).($part !== 'all' ? '-'.$part : '').'.stl';

        return response()->file($built['path'], [
            'Content-Type' => 'model/stl',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'X-Model-Meta' => json_encode($built['meta']),
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend(true);
    }

    /** POST /api/tools/param {kind, params} → a model file that opens in the calculator (price, inquiry, download) */
    public function paramCreate(Request $request, ParametricGenerator $tools): JsonResponse
    {
        if (! $tools->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$kind, $params] = $this->paramInput($request);
        $file = $tools->create($kind, $params, $request->attributes->get('anon_session'), $request->user());

        return response()->json(['file' => UploadController::describe($file)], 201);
    }

    /** GET /api/tools/param/{uuid}/{part}.stl → the box or its lid alone, rebuilt from the stored parameters */
    public function paramPart(ModelFile $modelFile, string $part, ParametricGenerator $tools): BinaryFileResponse
    {
        abort_unless($modelFile->origin === 'tool' && isset(ParametricGenerator::FIELDS[$modelFile->origin_ref]) && is_array($modelFile->tool_params), 404);
        abort_unless(in_array($part, ['body', 'lid'], true), 404);
        $built = $tools->build($modelFile->origin_ref, $modelFile->tool_params, $part);

        return response()->download($built['path'], pathinfo($modelFile->original_name, PATHINFO_FILENAME).'-'.$part.'.stl', ['Content-Type' => 'model/stl'])->deleteFileAfterSend(true);
    }

    /** POST /api/tools/relief (multipart: photo + options) → lithophane or relief plaque; the photo is not stored */
    public function relief(Request $request, ReliefGenerator $reliefs): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'mode' => ['nullable', 'in:lithophane,relief'],
            'width' => ['nullable', 'numeric', 'min:40', 'max:200'],
            'max_thickness' => ['nullable', 'numeric', 'min:1.6', 'max:10'],
            'frame' => ['nullable', 'boolean'],
            'invert' => ['nullable', 'boolean'],
        ]);
        if (! $reliefs->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        try {
            $file = $reliefs->generate($request->file('photo'), $data, $request->attributes->get('anon_session'), $request->user());
        } catch (EngineException $e) {
            return response()->json(['error' => 'generation_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['file' => UploadController::describe($file)], 201);
    }
}
