<?php

namespace App\Domain\YouTube;

use App\Engines\Repair\ScriptRunner;
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
        return $path !== null && str_starts_with(basename($path), 'short_model_');
    }

    /** Absolute path of the picture (made now when missing), null when there is no model or it could not be drawn. */
    public function get(FarmOrder $order): ?string
    {
        $file = $order->modelFile;
        $source = $order->absolutePrintStlPath() ?? $file?->absoluteStlPath();
        if (! $file || ! $source || ! is_file($source)) {
            return null;
        }
        $disk = Storage::disk(ModelFile::DISK);
        $colour = $this->colour($order);
        $key = substr(md5($source.'|'.filemtime($source).'|'.filesize($source).'|'.$colour), 0, 10);
        $relative = $file->dir().'/short_model_'.$key.'.png';
        if ($disk->exists($relative)) {
            return $disk->path($relative);
        }
        $failed = $file->dir().'/short_model.failed';
        if ($disk->exists($failed) && $disk->lastModified($failed) > now()->subHours(self::RETRY_AFTER_HOURS)->getTimestamp() && trim((string) $disk->get($failed)) === $key) {
            return null;
        }
        try {
            $disk->makeDirectory($file->dir());
            $target = $disk->path($relative);
            $tmp = $disk->path($file->dir().'/drawing_short_model.png');
            $r = $this->python->runScript('advise_tool.py', ['preview', $source, $tmp, $colour, '1080', '1920'], self::SECONDS);
            if (! ($r['ok'] ?? false) || ! is_file($tmp) || filesize($tmp) < 2000) {
                throw new \RuntimeException((string) ($r['error'] ?? 'no picture'));
            }
            foreach ($disk->files($file->dir()) as $old) {
                if (str_starts_with(basename($old), 'short_model_')) {
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

    /** The order's first colour when it is at hand and not so pale it vanishes on the light ground, else a neutral grey. */
    public function colour(FarmOrder $order): string
    {
        $hex = ltrim((string) $order->color?->hex, '#');
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return self::NEUTRAL;
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        $light = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        if ($light < 60) {
            return self::DARK;   // black and near-black filaments: a dark grey keeps the shading, a true black is a flat silhouette
        }

        return $light > 215 ? self::NEUTRAL : strtolower($hex);
    }
}
