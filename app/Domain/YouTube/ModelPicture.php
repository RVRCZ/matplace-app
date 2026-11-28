<?php

namespace App\Domain\YouTube;

use App\Engines\Repair\ScriptRunner;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The picture of the model the Short opens with when the browser stored no preview with the model file (most files:
 * 93 of 703 on production, 18 Nov 2026). Made on the server by engines/python/advise_tool.py `preview`: the model as it
 * went to print (the order's prepared STL, else the file's own), three-quarter view from the front, one colour on a
 * plain light ground, 1080x1920. The light copy of the mesh keeps a million-triangle scan as fast as a small part.
 *
 * Kept beside the browser's preview in the model's folder (`short_model_<key>.png`; the browser's preview_path is never
 * touched), so a rebuild finds it. A failure or a timeout leaves a `short_model.failed` note for a day and returns null:
 * the Short then opens as before, it never fails because of this picture.
 */
class ModelPicture
{
    public const SECONDS = 90;          // the script's time limit; a scan of millions of triangles is loaded and thinned within it or skipped

    public const RETRY_AFTER_HOURS = 24;

    public const NEUTRAL = 'b9bec6';

    private const DARK = '4a4d52';

    public function __construct(private readonly ScriptRunner $python) {}

    /** Is this still one of ours (light ground, so the Short pads it with the same light colour, not black)? */
    public static function isOurs(?string $path): bool
    {
        return $path !== null && (str_starts_with(basename($path), 'short_model_') || str_starts_with(basename($path), 'studio_sliced_'));
    }

    /**
     * Absolute path of the picture (made now when missing; with $make false only when it is made already), null when
     * there is no model or it could not be drawn. 1080x1920 for the Short, 1920x1080 for the 16:9 (the video studio).
     */
    public function get(FarmOrder $order, int $width = 1080, int $height = 1920, bool $make = true): ?string
    {
        $file = $order->modelFile;
        $source = $order->absolutePrintStlPath() ?? $file?->absoluteStlPath();
        if (! $file || ! $source || ! is_file($source)) {
            return null;
        }
        $disk = Storage::disk(ModelFile::DISK);
        $colour = $this->colour($order);
        $size = $width === 1080 && $height === 1920 ? '' : '|'.$width.'x'.$height;
        $key = substr(md5($source.'|'.filemtime($source).'|'.filesize($source).'|'.$colour.$size), 0, 10);
        $relative = $file->dir().'/short_model_'.$key.'.png';
        if ($disk->exists($relative)) {
            return $disk->path($relative);
        }
        if (! $make) {
            return null;
        }
        $failed = $file->dir().'/short_model.failed';
        if ($disk->exists($failed) && $disk->lastModified($failed) > now()->subHours(self::RETRY_AFTER_HOURS)->getTimestamp() && trim((string) $disk->get($failed)) === $key) {
            return null;
        }
        try {
            $disk->makeDirectory($file->dir());
            $target = $disk->path($relative);
            $tmp = $disk->path($file->dir().'/drawing_short_model.png');
            $r = $this->python->runScript('advise_tool.py', ['preview', $source, $tmp, $colour, (string) $width, (string) $height], self::SECONDS);
            if (! ($r['ok'] ?? false) || ! is_file($tmp) || filesize($tmp) < 2000) {
                throw new \RuntimeException((string) ($r['error'] ?? 'no picture'));
            }
            // the older pictures of this size go (a re-prepared model, another colour); the other size stays
            foreach ($disk->files($file->dir()) as $old) {
                if (str_starts_with(basename($old), 'short_model_') && $this->sizeOf($disk->path($old)) === [$width, $height]) {
                    $disk->delete($old);
                }
            }
            rename($tmp, $target);
            $disk->delete($failed);

            return $target;
        } catch (Throwable $e) {
            Log::warning('The model picture for the Short could not be made, the Short opens without it', ['order' => $order->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            @unlink($tmp ?? '');
            $disk->put($failed, $key);

            return null;
        }
    }

    /** [width, height] of a picture, null when it cannot be read. */
    private function sizeOf(string $path): ?array
    {
        $info = @getimagesize($path);

        return $info ? [(int) $info[0], (int) $info[1]] : null;
    }

    /**
     * The plate as it was sliced (the video studio's "po naslicování"): every part of an order by parts in its spool's
     * colour, else the prepared model in the order's colour, the slice's support lines in grey (supports.bin). Kept on
     * the farm disk next to the order (`studio_sliced_<key>.png`); null when there is nothing to draw or it failed (a
     * note keeps it from being tried again for a day, like the model picture).
     */
    public function plate(FarmOrder $order, int $width = 1080, int $height = 1920, bool $make = true): ?string
    {
        $parts = [];
        foreach ($order->partPlates() as $plate) {
            $stl = $order->absolutePartStlPath($plate);
            if ($stl) {
                $parts[] = [$stl, $this->hexOf(FarmColor::find((int) ($plate['color_id'] ?? 0))?->hex)];
            }
        }
        if (! $parts && ($stl = $order->absolutePrintStlPath())) {
            $parts[] = [$stl, $this->colour($order)];
        }
        if (! $parts) {
            return null;
        }
        $supports = $order->absoluteSupportsPath();
        $stamp = implode('|', array_map(fn ($p) => $p[0].'='.$p[1].'@'.filemtime($p[0]), $parts)).'|'.($supports ? filemtime($supports) : '-').'|'.$width.'x'.$height;
        $key = substr(md5($stamp), 0, 10);
        $disk = Storage::disk(config('farm.disk'));
        $relative = $order->dir().'/studio_sliced_'.$key.'.png';
        if ($disk->exists($relative)) {
            return $disk->path($relative);
        }
        $failed = $order->dir().'/studio_sliced.failed';
        if (! $make || ($disk->exists($failed) && $disk->lastModified($failed) > now()->subHours(self::RETRY_AFTER_HOURS)->getTimestamp() && trim((string) $disk->get($failed)) === $key)) {
            return null;
        }
        $tmp = $disk->path($order->dir().'/drawing_studio_sliced.png');
        try {
            $args = ['plate', $tmp, (string) $width, (string) $height, $supports ?? '-', ...array_map(fn ($p) => $p[0].'='.$p[1], $parts)];
            $r = $this->python->runScript('advise_tool.py', $args, self::SECONDS);
            if (! ($r['ok'] ?? false) || ! is_file($tmp) || filesize($tmp) < 2000) {
                throw new \RuntimeException((string) ($r['error'] ?? 'no picture'));
            }
            foreach ($disk->files($order->dir()) as $old) {
                if (str_starts_with(basename($old), 'studio_sliced_') && $this->sizeOf($disk->path($old)) === [$width, $height]) {
                    $disk->delete($old);
                }
            }
            rename($tmp, $disk->path($relative));
            $disk->delete($failed);

            return $disk->path($relative);
        } catch (Throwable $e) {
            Log::warning('The sliced plate picture could not be made', ['order' => $order->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            @unlink($tmp);
            $disk->put($failed, $key);

            return null;
        }
    }

    /**
     * A filament colour for a picture on the light ground: a pale one would vanish (neutral grey), black and near-black
     * ones are drawn dark grey (a true black is a flat silhouette, the shading is lost).
     */
    private function hexOf(?string $hex): string
    {
        $hex = ltrim((string) $hex, '#');
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return self::NEUTRAL;
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $light = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $light < 60 ? self::DARK : ($light > 215 ? self::NEUTRAL : strtolower($hex));
    }

    /** The order's first colour when it is at hand and not so pale it vanishes on the light ground, else a neutral grey. */
    public function colour(FarmOrder $order): string
    {
        return $this->hexOf($order->color?->hex);
    }
}
