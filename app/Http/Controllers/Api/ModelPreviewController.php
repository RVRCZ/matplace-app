<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Small pictures of models for lists ("My models", orders, portfolio cards). The owner's browser draws the model
 * once with the 3D viewer and sends the picture here; from then on every list shows it as a plain image.
 */
class ModelPreviewController extends Controller
{
    private const MAX_KB = 400;

    public function show(ModelFile $modelFile): BinaryFileResponse
    {
        $disk = Storage::disk(ModelFile::DISK);
        abort_unless($modelFile->preview_path && $disk->exists($modelFile->preview_path), 404);

        return response()->file($disk->path($modelFile->preview_path), ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=604800']);
    }

    public function store(Request $request, ModelFile $modelFile): JsonResponse
    {
        abort_unless($this->owns($request, $modelFile) && $modelFile->isReady(), 403);
        $request->validate(['image' => ['required', 'file', 'mimetypes:image/webp,image/png,image/jpeg', 'max:'.self::MAX_KB]]);
        $image = @imagecreatefromstring((string) file_get_contents($request->file('image')->getRealPath()));
        abort_unless($image !== false && imagesx($image) <= 1200 && imagesy($image) <= 1200, 422);

        // always re-encoded here: what is stored is a picture we made, whatever the browser sent
        $path = $modelFile->dir().'/preview.webp';
        $target = Storage::disk(ModelFile::DISK)->path($path);
        @mkdir(dirname($target), 0775, true);
        imagepalettetotruecolor($image);
        imagewebp($image, $target, 82);
        imagedestroy($image);
        $modelFile->forceFill(['preview_path' => $path])->save();

        return response()->json(['url' => route('api.files.preview', $modelFile).'?v='.$modelFile->updated_at->timestamp], 201);
    }

    private function owns(Request $request, ModelFile $file): bool
    {
        $session = $request->attributes->get('anon_session');

        return ($request->user() && $file->owner_user_id === $request->user()->id)
            || ($file->owner_user_id === null && $session && $file->anonymous_session_id === $session->id);
    }
}
