<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\ReliefGenerator;
use App\Domain\Tools\SignGenerator;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
