<?php

namespace App\Http\Controllers;

use App\Domain\YouTube\FarmVideos;
use App\Models\FarmVideo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The file of a print video at a signed address that lives two days: Instagram downloads a Reel from it (VideoSharer). */
class SocialVideoController extends Controller
{
    public function show(FarmVideo $video, FarmVideos $videos): BinaryFileResponse
    {
        abort_unless($videos->consented($video), 404);
        $path = $videos->pathOf($video);
        $disk = Storage::disk(config('farm.disk'));
        abort_unless($path !== '' && $disk->exists($path), 404);

        return response()->file($disk->path($path), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store']);
    }
}
