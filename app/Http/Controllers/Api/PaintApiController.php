<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\ModelPainter;
use App\Domain\Tools\ToolVisibility;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Colouring a model in the browser (App\Domain\Tools\ModelPainter, docs/AS.md). */
class PaintApiController extends Controller
{
    /**
     * POST /api/files/{uuid}/paint (multipart: record = JSON {v, triangles, palette, heights}, states = a byte per triangle
     * of the model's STL, added + added_states = the triangles the brush refined) → a new model file, a painted 3MF made
     * in the queue (ask /api/files/{uuid} for its state). The model itself is not changed.
     */
    public function store(Request $request, ModelFile $modelFile, ModelPainter $painter): JsonResponse
    {
        abort_unless(ToolVisibility::canOpen($request->user(), ModelPainter::KIND), 404);
        abort_unless($modelFile->isReady() && ModelPainter::urlFor($modelFile, $request->user()) !== null, 404);
        $limits = ModelPainter::limits();
        $request->validate([
            'record' => ['required', 'string', 'max:20000'],
            'states' => ['required', 'file', 'max:'.(int) ceil($limits['max_triangles'] / 1024 + 1)],
            'added' => ['nullable', 'file', 'max:'.(int) ceil(($limits['max_added'] * 2 + 1000) * 36 / 1024 + 1)],
            'added_states' => ['nullable', 'file', 'required_with:added'],
        ]);
        if (! $painter->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        $record = json_decode((string) $request->input('record'), true);
        try {
            if (! is_array($record)) {
                throw new EngineException('bad_record');
            }
            $new = $painter->make($modelFile, $record, $request->file('states'), $request->file('added'), $request->file('added_states'), $request->attributes->get('anon_session'), $request->user());
        } catch (EngineException $e) {
            return response()->json(['error' => 'paint_failed', 'reason' => $e->getMessage()], 422);
        }

        return response()->json(['file' => UploadController::describe($new)], 201);
    }

    /** GET /api/tools/paint/{uuid}/colors.3mf → the painted 3MF itself, for the visitor's own slicer (Bambu Studio, OrcaSlicer, Anycubic Slicer Next) */
    public function download(ModelFile $modelFile): BinaryFileResponse
    {
        abort_unless($modelFile->isReady() && $modelFile->origin === ModelPainter::KIND && is_file($modelFile->absolutePath()), 404);

        return response()->download($modelFile->absolutePath(), $modelFile->original_name, ['Content-Type' => 'model/3mf']);
    }
}
