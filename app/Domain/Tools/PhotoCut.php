<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Photo\BackgroundRemover;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * /tools/photo: a product photo with its background taken off. The cut-out (an RGBA PNG) lies in storage/app/tmp/photo
 * for a day (php artisan matplace:prune) under an unguessable id; the page puts it on a backdrop in the browser and
 * saves the result there, so nothing composed is kept here.
 */
final class PhotoCut
{
    public const KEEP_HOURS = 24;

    public const MAX_PHOTOS = 6;

    /**
     * @return array{id: string, width: int, height: int, coverage: float}
     *
     * @throws EngineException
     */
    public static function make(UploadedFile $photo, BackgroundRemover $engine): array
    {
        File::ensureDirectoryExists(self::dir());
        $id = (string) Str::uuid();
        $src = self::dir().'/'.$id.'.src';
        File::copy($photo->getRealPath(), $src);
        try {
            $facts = $engine->cut($src, self::dir().'/'.$id.'.png');
        } finally {
            @unlink($src);
        }

        return ['id' => $id] + $facts;
    }

    public static function path(string $id): ?string
    {
        if (! preg_match('/^[0-9a-f-]{36}$/', $id)) {
            return null;
        }
        $path = self::dir().'/'.$id.'.png';

        return is_file($path) ? $path : null;
    }

    /** Cut-outs older than a day leave the disk. Returns how many files went. */
    public static function prune(bool $dry = false): int
    {
        $gone = 0;
        foreach (File::glob(self::dir().'/*') as $path) {
            if (is_file($path) && filemtime($path) < time() - self::KEEP_HOURS * 3600) {
                $gone++;
                if (! $dry) {
                    @unlink($path);
                }
            }
        }

        return $gone;
    }

    private static function dir(): string
    {
        return storage_path('app/tmp/photo');
    }
}
