<?php

namespace App\Http\Controllers\Api;

use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessModelFile;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    /** POST /api/uploads — multipart "file" (+ optional browser geometry). Returns the model file descriptor. */
    public function store(Request $request, ConverterChain $converters): JsonResponse
    {
        $maxKb = (int) config('uploads.max_mb', 100) * 1024;
        $formats = $converters->inputFormats();

        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'volume_mm3' => ['nullable', 'numeric', 'min:0'],
            'area_mm2' => ['nullable', 'numeric', 'min:0'],
        ]);

        $upload = $request->file('file');
        $ext = strtolower($upload->getClientOriginalExtension());
        if (! in_array($ext, $formats, true)) {
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

        return response()->json(['file' => self::describe($file)], 201);
    }

    public function show(ModelFile $modelFile): JsonResponse
    {
        return response()->json(['file' => self::describe($modelFile)]);
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
            'triangles' => $f->triangles,
            'issues' => $f->mesh_report['issues'] ?? [],
            'stl_url' => $f->stl_path ? route('api.files.stl', $f->uuid) : null,
            'kind' => $f->kind(),
            'hints' => $f->printHints(),
            'generation' => $f->generationInfo(),
        ];
    }
}
