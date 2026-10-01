<?php

namespace App\Domain\Designer;

use App\Models\DesignerModel;
use App\Models\DesignerModelImage;
use App\Models\DesignerProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures of a portfolio on the public disk, always re-encoded as WebP: a large one (longer side up to 1600 px)
 * and a small one for grids (480 px wide). Whatever comes in (an upload, a picture fetched from Printables),
 * what is stored is a picture we made.
 */
final class DesignerImages
{
    private const LARGE = 1600;

    private const SMALL = 480;

    private const MAX_BYTES = 12 * 1024 * 1024;

    public function avatar(DesignerProfile $profile, string $bytes): ?string
    {
        return $this->replace($profile, 'avatar_path', $bytes, 'avatar', 512, true);
    }

    public function cover(DesignerProfile $profile, string $bytes): ?string
    {
        return $this->replace($profile, 'cover_path', $bytes, 'cover', self::LARGE, false);
    }

    /** Add a picture to a card (the first one becomes the cover). Null when the bytes are not a picture or the card is full. */
    public function add(DesignerModel $model, string $bytes): ?DesignerModelImage
    {
        $count = $model->images()->count();
        if ($count >= DesignerModel::MAX_IMAGES) {
            return null;
        }
        $image = $this->decode($bytes);
        if (! $image) {
            return null;
        }
        $base = 'designers/'.$model->designer_profile_id.'/models/'.$model->id.'/'.Str::lower(Str::random(10));
        $this->write($image, $base.'.webp', self::LARGE);
        $this->write($image, $base.'_s.webp', self::SMALL, true);
        imagedestroy($image);

        return $model->images()->create(['path' => $base.'.webp', 'position' => (int) $model->images()->max('position') + 1, 'is_cover' => $count === 0]);
    }

    /** Fetch a picture from the source of an import. Pictures only, a size limit, never anything else. */
    public function addFromUrl(DesignerModel $model, string $url): ?DesignerModelImage
    {
        // only the picture servers of the sources we import from (config engines.import.image_hosts)
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $known = collect((array) config('engines.import.image_hosts'))->contains(fn (string $h) => $host === $h || str_ends_with($host, '.'.$h));
        if (! preg_match('~^https://~i', $url) || ! $known) {
            return null;
        }
        try {
            $res = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; matplace-import/1.0)'])->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $res->ok() || strlen($res->body()) > self::MAX_BYTES) {
            return null;
        }

        return $this->add($model, $res->body());
    }

    public function remove(DesignerModelImage $image): void
    {
        $model = $image->model;
        Storage::disk('public')->delete([$image->path, $image->smallPath()]);
        $wasCover = $image->is_cover;
        $image->delete();
        if ($wasCover && ($first = $model?->images()->first())) {
            $first->update(['is_cover' => true]);
        }
    }

    public function makeCover(DesignerModelImage $image): void
    {
        DesignerModelImage::where('designer_model_id', $image->designer_model_id)->update(['is_cover' => false]);
        $image->update(['is_cover' => true]);
    }

    /** Everything a card keeps on the disk. */
    public function removeAll(DesignerModel $model): void
    {
        Storage::disk('public')->deleteDirectory('designers/'.$model->designer_profile_id.'/models/'.$model->id);
        $model->images()->delete();
    }

    private function replace(DesignerProfile $profile, string $column, string $bytes, string $name, int $size, bool $square): ?string
    {
        $image = $this->decode($bytes);
        if (! $image) {
            return null;
        }
        $path = 'designers/'.$profile->id.'/'.$name.'-'.Str::lower(Str::random(8)).'.webp';
        $this->write($square ? $this->square($image) : $image, $path, $size);
        imagedestroy($image);
        if ($profile->{$column}) {
            Storage::disk('public')->delete($profile->{$column});
        }
        $profile->forceFill([$column => $path])->save();

        return $path;
    }

    private function decode(string $bytes): \GdImage|false
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return false;
        }
        $info = @getimagesizefromstring($bytes);
        // refuse absurd dimensions before GD allocates the bitmap
        if (! $info || $info[0] < 16 || $info[1] < 16 || $info[0] * $info[1] > 60_000_000) {
            return false;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    private function square(\GdImage $image): \GdImage
    {
        $side = min(imagesx($image), imagesy($image));

        return imagecrop($image, ['x' => intdiv(imagesx($image) - $side, 2), 'y' => intdiv(imagesy($image) - $side, 2), 'width' => $side, 'height' => $side]) ?: $image;
    }

    /** $byWidth: scale so the width fits (grid pictures); otherwise so the longer side fits. Never enlarges. */
    private function write(\GdImage $image, string $path, int $limit, bool $byWidth = false): void
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1.0, $limit / ($byWidth ? $w : max($w, $h)));
        $out = $scale < 1.0 ? imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC) : $image;
        $target = Storage::disk('public')->path($path);
        @mkdir(dirname($target), 0775, true);
        imagewebp($out, $target, 84);
        if ($out !== $image) {
            imagedestroy($out);
        }
    }
}
