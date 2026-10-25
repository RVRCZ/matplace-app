<?php

namespace App\Http\Controllers\Api;

use App\Domain\Calculation\BigModel;
use App\Domain\Farm\Palette;
use App\Domain\Tools\MapBuilder;
use App\Domain\Tools\ModelCheck;
use App\Domain\Tools\ModelEditor;
use App\Domain\Tools\MoldGenerator;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessModelFile;
use App\Models\ModelFile;
use App\Support\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    /** POST /api/uploads — multipart "file" (+ optional browser geometry). Returns the model file descriptor. */
    public function store(Request $request, ConverterChain $converters): JsonResponse
    {
        $maxKb = (int) config('uploads.max_mb', 120) * 1024;
        $formats = $converters->inputFormats();

        try {
            $request->validate([
                'file' => ['required', 'file', 'max:'.$maxKb],
                'volume_mm3' => ['nullable', 'numeric', 'min:0'],
                'area_mm2' => ['nullable', 'numeric', 'min:0'],
            ]);
        } catch (ValidationException $e) {
            // an upload that began and was refused is a step of the funnel (docs/AN.md §9): the size over the limit, or no file at all
            Track::event('failed', null, ['step' => 'upload', 'kind' => $request->hasFile('file') ? 'too_big' : 'missing']);
            throw $e;
        }

        $upload = $request->file('file');
        $ext = strtolower($upload->getClientOriginalExtension());
        if (! in_array($ext, $formats, true)) {
            Track::event('failed', null, ['step' => 'upload', 'kind' => 'format']);
            throw ValidationException::withMessages(['file' => __('upload.unsupported', ['formats' => strtoupper(implode(', ', $formats))])]);
        }

        $uuid = (string) Str::uuid();
        $dir = 'files/'.$uuid;
        $storedName = 'original.'.$ext;
        Storage::disk(ModelFile::DISK)->putFileAs($dir, $upload, $storedName);
        $abs = Storage::disk(ModelFile::DISK)->path($dir.'/'.$storedName);

        $file = ModelFile::create([
            'uuid' => $uuid,
            'owner_user_id' => $request->user()?->id,
            'anonymous_session_id' => $request->attributes->get('anon_session')?->id,
            'original_name' => mb_substr($upload->getClientOriginalName(), 0, 255),
            'ext' => $ext,
            'mime' => $upload->getClientMimeType(),
            'size_bytes' => $upload->getSize(),
            'sha256' => hash_file('sha256', $abs),
            'storage_path' => $dir.'/'.$storedName,
            'status' => ModelFile::STATUS_UPLOADED,
        ]);

        ProcessModelFile::dispatch($file->id);
        Track::event('upload', $file, ['kind' => $ext]);
        self::measure($file->refresh());

        return response()->json(['file' => self::describe($file)], 201);
    }

    public function show(ModelFile $modelFile): JsonResponse
    {
        self::measure($modelFile);

        return response()->json(['file' => self::describe($modelFile)]);
    }

    /** Its owner sees the model ready (an upload, a tool's output, a generated model) or failed: measured once (docs/AJ.md). */
    public static function measure(ModelFile $file): void
    {
        $kind = $file->origin === 'tool' ? 'tool' : $file->kind();
        if ($file->status === ModelFile::STATUS_READY) {
            Track::outcome('model_ready', $file, array_filter(['kind' => $kind, 'tool' => $file->origin === 'tool' ? $file->kind() : null]));
        } elseif ($file->status === ModelFile::STATUS_FAILED) {
            Track::outcome('failed', $file, ['step' => 'model', 'kind' => $kind]);
        }
    }

    /** Products that consist of several printed parts offer each one as its own download. */
    private static function partsOf(ModelFile $f): array
    {
        return $f->origin === 'tool' ? (ParametricGenerator::partsOf((string) $f->origin_ref, (array) $f->tool_params) ?: ModelEditor::partsOf($f)) : [];
    }

    /**
     * For each colour of a design the spool of our farm nearest to it. Nothing is decided by it: the farm's own page
     * ticks the spools and lets the customer change them. Empty on a site without the farm's catalogue.
     *
     * @return list<array{hex: string, spool: array<string, mixed>}>
     */
    private static function nearestOf(ModelFile $f): array
    {
        $out = [];
        foreach ($f->designColors() as $hex) {
            if ($spool = app(Palette::class)->nearestSpool($hex)) {
                $out[] = ['hex' => $hex, 'spool' => $spool];
            }
        }

        return $out;
    }

    /** The tool page this design came from; the page reopens with the same settings. Our own geometry, so changing it costs nothing. */
    private static function toolOf(ModelFile $f): ?array
    {
        $route = 'tools.'.(in_array($f->origin_ref, ['lithophane', 'relief'], true) ? 'relief' : $f->origin_ref);
        if ($f->origin !== 'tool' || ! is_array($f->tool_params) || ! Route::has($route)) {
            return null;
        }
        // older reliefs stored only the stand flag: nothing to reopen
        if ($route === 'tools.relief' && ! isset($f->tool_params['mode'])) {
            return null;
        }

        return ['kind' => $f->origin_ref, 'params' => $f->tool_params, 'url' => route($route, ['from' => $f->uuid])];
    }

    public static function describe(ModelFile $f): array
    {
        return [
            'uuid' => $f->uuid,
            'name' => $f->original_name,
            'ext' => $f->ext,
            'size' => $f->size_bytes,
            'status' => $f->status,
            'error' => $f->error,
            'bbox' => $f->bbox,
            'volume_mm3' => $f->volume_mm3,
            'area_mm2' => $f->area_mm2,
            'triangles' => $f->triangles ?? (BigModel::triangles($f) ?: null),   // right after the upload: the binary STL header
            // millions of triangles (thinned before the slice, docs/AL.md §10.11) or a slice that took over a minute last time: the
            // calculator shows the estimate with a range and no slice, the order page slices after the choices (TODO #41)
            'big_model' => BigModel::is($f),
            // a box with its lid is two bodies on one plate by design, not a defect
            // a box with its lid, or the two halves of a mold, are two bodies on one plate by design, not a defect
            'issues' => array_values(array_diff($f->mesh_report['issues'] ?? [], self::partsOf($f) || in_array($f->kind(), ['logo', 'qr', 'stamp', 'mold'], true) || ! empty($f->tool_params['stand']) ? ['multiple_shells'] : [])),
            // casting mold: what the tool measured (box size, resin needed, undercuts)
            'mold' => $f->kind() === 'mold' && isset($f->tool_params['report']) ? $f->tool_params['report'] + (MoldGenerator::castPath($f, 'cast.stl') ? [
                // undercuts were filled: the shape that will really be cast, to look at
                'cast_url' => route('api.files.mold.cast', [$f->uuid, 'cast.stl']), 'cast_flags_url' => route('api.files.mold.cast', [$f->uuid, 'cast.bin']),
            ] : []) : null,
            // repaired model: what was wrong, what was done, what is left
            'repair' => $f->kind() === 'repaired' ? ($f->tool_params['report'] ?? null) : null,
            // a model edited here (split, scaled…): what the tool did, and while it works, the phase it is in
            'edit' => ModelEditor::report($f),
            // a map made here: its notes (the scale, the data's date) and, while it is built, the phase it is in
            'map' => MapBuilder::report($f),
            'parts' => self::partsOf($f),
            // lets the tool page reopen this design ("edit" from the calculator)
            'tool' => self::toolOf($f),
            // the design is drawn in free colours: which of our spools each one would be printed from (shown beside "print it with us")
            'nearest' => self::nearestOf($f),
            'stl_url' => $f->stl_path ? route('api.files.stl', $f->uuid) : null,
            'kind' => $f->kind(),
            'check' => ModelCheck::report($f),
            'hints' => $f->printHints(),
            'generation' => $f->generationInfo(),
        ];
    }
}
