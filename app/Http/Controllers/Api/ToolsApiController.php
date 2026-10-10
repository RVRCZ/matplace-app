<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\Artwork;
use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ReliefGenerator;
use App\Domain\Tools\SignGenerator;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use App\Models\ModelFile;
use App\Support\PreviewMeta;
use Illuminate\Http\JsonResponse;
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
            'radius' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'bevel' => ['nullable', 'boolean'],
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
        // the form shows these messages as they are: in the visitor's language, never "The params.line1 field is required."
        $messages = [];
        $required = match (true) {
            $kind === 'qr' => __('param.error.qr_bad_text'),
            in_array($kind, ParametricGenerator::ARTWORK, true) && ! in_array($kind, ParametricGenerator::BESIDE, true) => __('param.error.no_text'),
            default => __('param.text_required'),
        };
        foreach (array_keys(ParametricGenerator::TEXTS[$kind] ?? []) as $text) {
            $messages['params.'.$text.'.required'] = $required;
            $messages['params.'.$text.'.max'] = __('param.error.text_too_long', ['n' => ParametricGenerator::TEXTS[$kind][$text][0]]);
        }
        foreach (array_keys(ParametricGenerator::FIELDS[$kind]) as $field) {
            foreach (['min', 'max', 'numeric', 'integer'] as $rule) {
                $messages['params.'.$field.'.'.$rule] = __('param.error.out_of_range', ['n' => __('param.f.'.$field)]);
            }
        }
        $data = $request->validate(ParametricGenerator::rules($kind) + [
            'params' => ['nullable', 'array'],
            'part' => ['nullable', 'string', 'regex:/^('.implode('|', ParametricGenerator::PARTS).'|tray|bin_\d{1,2}x\d{1,2})$/'],
            'view' => ['nullable', 'in:print,use'],
        ], $messages);

        return [$kind, (array) ($data['params'] ?? []), $data['part'] ?? 'all', $data['view'] ?? 'print'];
    }

    /** POST /api/tools/artwork (multipart: file) → {artwork: id} used as params.artwork by logo, stamp… */
    public function artwork(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:svg,png,jpg,jpeg,webp']]);

        // kept for the visitor (account or this browser) among "my pictures" for 30 days
        $owner = Artwork::owner($request->user(), $request->attributes->get('anon_session'));

        return response()->json(['artwork' => ParametricGenerator::storeArtwork($request->file('file'), $owner), 'name' => $request->file('file')->getClientOriginalName()], 201);
    }

    /** POST /api/tools/param/preview {kind, params, part?, view?, download?, pieces?} → STL + X-Model-Meta (size, volume, notes; with pieces also parts: which triangles are which piece) */
    public function paramPreview(Request $request, ParametricGenerator $tools): BinaryFileResponse|JsonResponse
    {
        if (! $tools->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$kind, $params, $part, $view] = $this->paramInput($request);
        $built = $tools->build($kind, $params, $part, $view, $request->boolean('pieces') && ! $request->boolean('download'));
        $name = str_replace('_', '-', $kind).($part !== 'all' ? '-'.$part : '').'.stl';

        return response()->file($built['path'], [
            'Content-Type' => 'model/stl',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'X-Model-Meta' => PreviewMeta::header($built['meta']),
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend(true);
    }

    /** GET /api/tools/preview/{key}/meta → the notes of a preview that did not fit its header (see App\Support\PreviewMeta) */
    public function previewMeta(string $key): JsonResponse
    {
        $rest = PreviewMeta::rest($key);
        abort_if($rest === null, 404);

        return response()->json($rest, 200, ['Cache-Control' => 'no-store']);
    }

    /** POST /api/tools/param/zip {kind, params} → every part of the design as its own STL, in one archive */
    public function paramZip(Request $request, ParametricGenerator $tools): BinaryFileResponse|JsonResponse
    {
        if (! $tools->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        [$kind, $params] = $this->paramInput($request);

        return response()->download($tools->zip($kind, $params), str_replace('_', '-', $kind).'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
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
        abort_unless((in_array($part, ParametricGenerator::PARTS, true) && $part !== 'all') || preg_match('/^(tray|bin_\d{1,2}x\d{1,2})$/', $part), 404);
        $built = $tools->build($modelFile->origin_ref, $modelFile->tool_params, $part);
        if ($built['meta']['part'] !== $part) {
            @unlink($built['path']);                     // this design has no such part: never hand out the whole set under its name
            abort(404);
        }

        return response()->download($built['path'], pathinfo($modelFile->original_name, PATHINFO_FILENAME).'-'.$part.'.stl', ['Content-Type' => 'model/stl'])->deleteFileAfterSend(true);
    }

    /** POST /api/tools/relief (multipart: photo + options) → lithophane, relief plaque or lamp; the photo is not stored */
    public function relief(Request $request, ReliefGenerator $reliefs): JsonResponse
    {
        $data = $request->validate(ReliefGenerator::rules());
        if (! $reliefs->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        try {
            $file = $reliefs->generate($request->file('photo'), $data, $request->attributes->get('anon_session'), $request->user());
        } catch (EngineException $e) {
            // a reason the page has a text for (the frame too wide, the socket too big, no silhouette), else the engine's words
            $reason = in_array($e->getMessage(), ReliefGenerator::REASONS, true) ? $e->getMessage() : null;

            return response()->json(['error' => 'generation_failed', 'reason' => $reason, 'message' => $reason ? __('relief.warn.'.$reason) : $e->getMessage()], 422);
        }

        return response()->json(['file' => UploadController::describe($file)], 201);
    }
}
