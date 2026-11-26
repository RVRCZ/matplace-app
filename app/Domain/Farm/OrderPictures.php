<?php

namespace App\Domain\Farm;

use App\Domain\Generation\SourcePhotos;
use App\Domain\YouTube\ModelPicture;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Small pictures of an order that outlive its print files (docs/AU.md §8): the model as we printed it, the photo the
 * customer made it from, the printer camera's last picture and the photo-box photo. Each is a JPEG of at most 900 px
 * in the order's folder under `keep/` (tens of kB): OrderFiles::prune removes print files by name and leaves `keep/`.
 * A reply to a complaint weeks later can still show what we printed, and the assistant can compare it with the
 * customer's photos.
 */
final class OrderPictures
{
    public const KINDS = [
        'model' => 'Náhled modelu, podle kterého jsme tiskli',
        'source' => 'Fotka, ze které model vznikl',
        'snapshot' => 'Snímek z kamery tiskárny',
        'photo' => 'Fotka výtisku (foto-box)',
    ];

    public const MAX_PX = 900;

    public function __construct(private readonly ModelPicture $models) {}

    /**
     * The order's pictures, kept copies made or refreshed now. `$render` = draw the model when it has no picture yet
     * (up to ModelPicture::SECONDS); without it only what exists is kept.
     *
     * @return array<string, string> kind → absolute path of the small JPEG
     */
    public function all(FarmOrder $order, bool $render = true): array
    {
        $disk = Storage::disk(config('farm.disk'));
        $keep = $order->dir().'/keep';
        $out = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $target = $disk->path($keep.'/'.$kind.'.jpg');
            $source = $this->source($order, $kind, $render && ! is_file($target));
            if ($source !== null && (! is_file($target) || filemtime($source) > filemtime($target))) {
                $disk->makeDirectory($keep);
                if (! self::small($source, $target)) {
                    continue;
                }
            }
            if (is_file($target)) {
                $out[$kind] = $target;
            }
        }

        return $out;
    }

    /** Where a kind's full picture is now, null when there is none. */
    private function source(FarmOrder $order, string $kind, bool $render): ?string
    {
        $farm = Storage::disk(config('farm.disk'));
        try {
            return match ($kind) {
                'model' => $render ? $this->models->get($order) : $this->existingModelPicture($order),
                'source' => ($f = SourcePhotos::copies($order)[0] ?? null) ? $farm->path($f) : null,
                'snapshot' => (($job = $order->latestJob()) && $job->snapshot_path && $farm->exists($job->snapshot_path)) ? $farm->path($job->snapshot_path)
                    : ($farm->exists($order->dir().'/snapshot.jpg') ? $farm->path($order->dir().'/snapshot.jpg') : null),
                'photo' => $order->photo_path && Storage::disk('public')->exists($order->photo_path) ? Storage::disk('public')->path($order->photo_path) : null,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /** The model picture ModelPicture drew before, without drawing a new one. */
    private function existingModelPicture(FarmOrder $order): ?string
    {
        $file = $order->modelFile;
        if (! $file) {
            return null;
        }
        $disk = Storage::disk(ModelFile::DISK);
        foreach ($disk->files($file->dir()) as $f) {
            if (ModelPicture::isOurs($f)) {
                return $disk->path($f);
            }
        }

        return null;
    }

    /** A JPEG of at most MAX_PX on the longer side. False when the source is not a picture GD can read. */
    public static function small(string $source, string $target): bool
    {
        $image = @imagecreatefromstring((string) @file_get_contents($source));
        if ($image === false) {
            return false;
        }
        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_PX / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // a transparent PNG gets a white ground, not black
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        $tmp = $target.'.tmp';
        $ok = imagejpeg($out, $tmp, 82);
        imagedestroy($image);
        imagedestroy($out);

        return $ok && rename($tmp, $target);
    }
}
