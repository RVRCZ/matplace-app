<?php

namespace App\Domain\YouTube;

use App\Models\FarmVideo;
use App\Models\FarmVideoClip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class VideoClips
{
    public const LIMITS = ['removal' => 10, 'turn' => 10, 'detail' => 3, 'with_source' => 3, 'in_hand' => 3];

    public function store(FarmVideo $video, string $kind, UploadedFile $file): FarmVideoClip
    {
        abort_unless($video->kind === FarmVideo::KIND_SHORT && $video->farm_order_id && in_array($kind, FarmVideoClip::KINDS, true), 404);
        if (! in_array(strtolower((string) $file->getClientOriginalExtension()), ['mp4', 'webm', 'mov', 'm4v'], true)) {
            throw ValidationException::withMessages(['clip' => __('youtube.clips.invalid')]);
        }
        $old = $video->clips()->where('kind', $kind)->first();
        $extension = strtolower((string) $file->getClientOriginalExtension()) ?: 'mp4';
        $path = $video->order->dir().'/clips/'.$video->id.'/'.$kind.'-'.now()->format('YmdHisv').'.'.$extension;
        Storage::disk(config('farm.disk'))->putFileAs(dirname($path), $file, basename($path));
        $clip = $video->clips()->updateOrCreate(['kind' => $kind], [
            'farm_order_id' => $video->farm_order_id,
            'path' => $path,
            'mime' => (string) ($file->getMimeType() ?: 'video/'.$extension),
            'size' => (int) $file->getSize(),
        ]);
        if ($old && $old->path !== $path) {
            Storage::disk(config('farm.disk'))->delete($old->path);
        }

        return $clip;
    }

    /** @return array<string, FarmVideoClip> */
    public function for(FarmVideo $video): array
    {
        return $video->clips->keyBy('kind')->all();
    }

    /** @return list<string> */
    public function missing(FarmVideo $video): array
    {
        return array_values(array_diff(FarmVideoClip::KINDS, array_keys($this->for($video))));
    }

    public function path(FarmVideo $video, string $kind): ?string
    {
        $clip = $video->clips->firstWhere('kind', $kind);
        if (! $clip || ! Storage::disk(config('farm.disk'))->exists($clip->path)) {
            return null;
        }

        return Storage::disk(config('farm.disk'))->path($clip->path);
    }
}
