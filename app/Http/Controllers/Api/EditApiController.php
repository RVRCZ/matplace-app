<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ModelEditor;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use App\Models\ModelFile;
use App\Support\PreviewMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The tools of session 3: filament art (a picture → plates), and the editing of a model file (split, hollow, scale…). */
class EditApiController extends Controller
{
    private function artInput(Request $request): array
    {
        $messages = [];
        foreach (array_keys(ArtGenerator::FIELDS) as $field) {
            foreach (['min', 'max', 'numeric', 'integer'] as $rule) {
                $messages['params.'.$field.'.'.$rule] = __('param.error.out_of_range', ['n' => __('edit.f.'.$field)]);
            }
        }
        $messages['params.artwork.required'] = __('param.error.no_text');
        $messages['params.artwork.regex'] = __('param.error.artwork_gone');
        $data = $request->validate(ArtGenerator::rules() + ['params' => ['required', 'array'], 'view' => ['nullable', 'in:print,use']], $messages);

        return [(array) $data['params'], $data['view'] ?? 'use'];
    }

    /** POST /api/tools/art/preview {params, view?} → STL + X-Model-Meta (size, volume, notes, parts: which triangles are which piece) */
    public function artPreview(Request $request, ArtGenerator $art): BinaryFileResponse|JsonResponse
    {
        if (! $art->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$params, $view] = $this->artInput($request);
        $built = $art->build($params, $view, true);

        return response()->file($built['path'], [
            'Content-Type' => 'model/stl',
            'Content-Disposition' => 'inline; filename="filament-art.stl"',
            'X-Model-Meta' => PreviewMeta::header($built['meta']),      // the guide of a layered picture does not fit a header: see PreviewMeta
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend(true);
    }

    /** POST /api/tools/art {params} → a model file (the pieces laid out for printing) that opens in the calculator */
    public function artCreate(Request $request, ArtGenerator $art): JsonResponse
    {
        if (! $art->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$params] = $this->artInput($request);
        $file = $art->create($params, $request->attributes->get('anon_session'), $request->user());

        return response()->json(['file' => UploadController::describe($file)], 201);
    }

    /** POST /api/tools/art/zip {params} → every plate as its own STL, in one archive */
    public function artZip(Request $request, ArtGenerator $art): BinaryFileResponse|JsonResponse
    {
        if (! $art->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$params] = $this->artInput($request);

        return response()->download($art->zip($params), 'filament-art.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /** GET /api/tools/edit/{uuid}/{part}.stl → one piece of a design made here (a plate, the frame, a piece of a split model) */
    public function part(ModelFile $modelFile, string $part): BinaryFileResponse
    {
        abort_unless($modelFile->isReady() && $modelFile->origin === 'tool' && in_array($modelFile->kind(), [ArtGenerator::KIND, ...ModelEditor::KINDS], true), 404);
        abort_unless(preg_match('/^[a-z]+(_[0-9]{1,2})?$/', $part) && in_array($part, (array) ($modelFile->tool_params['parts'] ?? []), true), 404);
        $path = dirname(Storage::disk(ModelFile::DISK)->path($modelFile->storage_path)).'/parts/'.$part.'.stl';
        abort_unless(is_file($path), 404);

        return response()->download($path, pathinfo($modelFile->original_name, PATHINFO_FILENAME).'-'.$part.'.stl', ['Content-Type' => 'model/stl']);
    }

    /** GET /api/tools/edit/{uuid}/guide → the plates of a layered picture, back to front, with their drawings */
    public function guide(ModelFile $modelFile): JsonResponse
    {
        $guide = ArtGenerator::guide($modelFile);
        abort_unless($guide !== null, 404);

        return response()->json($guide);
    }

    /** POST /api/files/{uuid}/edit/analysis {op, …} → what the tool would do with this model before it does it (planes of a split…) */
    public function analysis(Request $request, ModelFile $modelFile, ModelEditor $editor): JsonResponse
    {
        abort_unless($modelFile->isReady(), 404);
        $data = $request->validate(['op' => ['required', 'in:'.implode(',', ModelEditor::KINDS)]] + ModelEditor::rules((string) $request->input('op')));
        if (! $editor->available()) {
            return response()->json(['error' => 'edit_unavailable'], 503);
        }
        try {
            return response()->json(['analysis' => $editor->analyse($modelFile, $data['op'], $data)]);
        } catch (EngineException $e) {
            return response()->json(['error' => 'edit_failed', 'reason' => $e->getMessage()], 422);
        }
    }

    /** POST /api/files/{uuid}/edit {op, …} → a new model file (processed in the queue; ask /api/files/{uuid} for its state) */
    public function edit(Request $request, ModelFile $modelFile, ModelEditor $editor): JsonResponse
    {
        abort_unless($modelFile->isReady(), 404);
        $data = $request->validate(['op' => ['required', 'in:'.implode(',', ModelEditor::KINDS)]] + ModelEditor::rules((string) $request->input('op')));
        if (! $editor->available()) {
            return response()->json(['error' => 'edit_unavailable'], 503);
        }
        try {
            $new = $editor->make($modelFile, $data['op'], $data, $request->attributes->get('anon_session'), $request->user());
        } catch (EngineException $e) {
            return response()->json(['error' => 'edit_failed', 'reason' => $e->getMessage()], 422);
        }

        return response()->json(['file' => UploadController::describe($new)], 201);
    }
}
