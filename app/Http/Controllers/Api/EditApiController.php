<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ModelEditor;
use App\Domain\Tools\ToolVisibility;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use App\Models\ModelFile;
use App\Support\PreviewMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    /**
     * POST /api/tools/art/poster {params, propose?} → PNG of the picture in its colours (no model is built) + X-Art-Poster:
     * {colors, regions, look, suggest, found, wanted, size, picture, proposed}. With `propose` (a picture just chosen)
     * the tool decides how the picture is read and says so in `proposed`: {look, remove_bg, shape}.
     */
    public function artPoster(Request $request, ArtGenerator $art): BinaryFileResponse|JsonResponse
    {
        abort_unless(ToolVisibility::canOpen($request->user(), ArtGenerator::KIND), 404);
        if (! $art->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$params] = $this->artInput($request);
        $poster = $art->poster($params, $request->boolean('propose'));

        return response()->file($poster['path'], [
            'Content-Type' => 'image/png',
            'X-Art-Poster' => (string) json_encode($poster['meta'], JSON_UNESCAPED_SLASHES),
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

    /**
     * POST /api/tools/gcode (multipart: file) → a model file made out of the G-code in the queue (ask /api/files/{uuid}
     * for its state): the model, its parts by colour and what the header said (docs/AL.md)
     */
    public function gcode(Request $request, ModelEditor $editor): JsonResponse
    {
        if (! $editor->available()) {
            return response()->json(['error' => 'edit_unavailable'], 503);
        }
        $request->validate(['file' => ['required', 'file', 'max:'.(ModelEditor::GCODE_MAX_MB * 1024)]]);
        $upload = $request->file('file');
        if (strtolower($upload->getClientOriginalExtension()) !== 'gcode') {
            throw ValidationException::withMessages(['file' => __('edit.gcode.bad_format')]);
        }
        $file = $editor->fromGcode($upload, $request->attributes->get('anon_session'), $request->user());

        return response()->json(['file' => UploadController::describe($file)], 201);
    }

    /** GET /api/tools/edit/{uuid}/colors.3mf → a model out of G-code with an object per colour, for the visitor's own slicer */
    public function colors3mf(ModelFile $modelFile): BinaryFileResponse
    {
        $path = ModelEditor::colors3mf($modelFile);
        abort_unless($path !== null, 404);

        return response()->download($path, pathinfo($modelFile->original_name, PATHINFO_FILENAME).'-colors.3mf', ['Content-Type' => 'model/3mf']);
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
        $data = $request->validate(['op' => ['required', 'in:'.implode(',', ModelEditor::KINDS)]] + ModelEditor::rules((string) $request->input('op')) + self::unwrapped($request));
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
        $data = $request->validate(['op' => ['required', 'in:'.implode(',', ModelEditor::KINDS)]] + ModelEditor::rules((string) $request->input('op')) + self::unwrapped($request));
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

    /**
     * The tool's settings belong at the top level of the body ({op, rows, frame…}), as the page sends them; a caller who wraps
     * them in `params` (the way the generators' endpoints take theirs) is read the same way – the keys are lifted before the
     * validation, the top level wins where both say something (docs/AN.md §17).
     *
     * @return array<string, mixed> nothing: the lifting changes the request, the rules stay
     */
    private static function unwrapped(Request $request): array
    {
        $wrapped = $request->input('params');
        if (is_array($wrapped) && $wrapped !== []) {
            $request->merge(array_diff_key($wrapped, $request->except('params')));
        }

        return [];
    }
}
