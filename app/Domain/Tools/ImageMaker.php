<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Image\ImageGenerator;
use App\Engines\Image\ImageResult;
use App\Models\AnonymousSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * /tools/image: a picture from a description, made by the image generator, turned into what the tools take (a
 * silhouette or a line drawing: pure black on white; colour: as it came) and kept among the visitor's own pictures
 * (App\Domain\Tools\Artwork), so every tool with a picture finds it under "my pictures". Daily counts per visitor
 * and for the whole site (config/ai.php `daily_limits.image_*`).
 */
final class ImageMaker
{
    public function __construct(private readonly ImageGenerator $generator) {}

    public function available(): bool
    {
        return $this->generator->available();
    }

    /** How many pictures this visitor may still make today; null when the site's cap is reached. */
    public static function left(?User $user, string $ip): ?int
    {
        if ($user?->isAdmin()) {
            return 99;
        }
        if ((int) Cache::get(self::key('all'), 0) >= (int) config('ai.daily_limits.image_global', 150)) {
            return null;
        }
        $limit = (int) config($user ? 'ai.daily_limits.image_user' : 'ai.daily_limits.image_guest', $user ? 10 : 2);

        return max(0, $limit - (int) Cache::get(self::key($user ? 'u'.$user->id : 'ip'.$ip), 0));
    }

    /**
     * @return array{ref: string, name: string, model: string, left: int|null}
     *
     * @throws EngineException
     */
    public function make(string $prompt, string $style, string $size, ?User $user, ?AnonymousSession $session, string $ip): array
    {
        $style = in_array($style, ImageGenerator::STYLES, true) ? $style : 'silhouette';
        $size = in_array($size, ImageGenerator::SIZES, true) ? $size : 'square';
        $result = $this->generator->fromText($prompt, $style, $size, ['user_id' => $user?->id, 'session_id' => $session?->id]);
        self::count($user, $ip);
        $tmp = tempnam(sys_get_temp_dir(), 'mp_image_');
        try {
            $ext = self::prepare($result, $style, $tmp);
            $name = Str::limit(trim($prompt), 60, '').'.'.$ext;
            $ref = Artwork::store(new UploadedFile($tmp, $name, $ext === 'png' ? 'image/png' : 'image/jpeg', null, true), Artwork::owner($user, $session));
        } finally {
            @unlink($tmp);
        }

        return ['ref' => $ref, 'name' => $name, 'model' => $result->model, 'left' => self::left($user, $ip)];
    }

    /**
     * The picture as a tool wants it: a silhouette or a line drawing becomes pure black on pure white (the models
     * leave grey edges and faint backgrounds), no bigger than 1600 px; colour stays as it came. Returns the extension.
     */
    public static function prepare(ImageResult $result, string $style, string $to): string
    {
        $img = @imagecreatefromstring($result->bytes);
        if (! $img) {
            file_put_contents($to, $result->bytes);

            return $result->extension();
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 1600;
        if (max($w, $h) > $max) {
            $k = $max / max($w, $h);
            $small = imagescale($img, (int) round($w * $k), (int) round($h * $k), IMG_BICUBIC);
            if ($small) {
                imagedestroy($img);
                $img = $small;
                $w = imagesx($img);
                $h = imagesy($img);
            }
        }
        if ($style === 'colour') {
            imagejpeg($img, $to, 90);
            imagedestroy($img);

            return 'jpg';
        }
        // the threshold: black where the picture is darker than the middle of the range between its darkest and lightest
        $out = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($out, 255, 255, 255);
        $black = imagecolorallocate($out, 0, 0, 0);
        imagefill($out, 0, 0, $white);
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ((imagecolorat($img, $x, $y) & 0xFF) < 128) {
                    imagesetpixel($out, $x, $y, $black);
                }
            }
        }
        imagedestroy($img);
        imagepng($out, $to, 6);
        imagedestroy($out);

        return 'png';
    }

    private static function count(?User $user, string $ip): void
    {
        foreach (['all', $user ? 'u'.$user->id : 'ip'.$ip] as $who) {
            Cache::put(self::key($who), (int) Cache::get(self::key($who), 0) + 1, now()->endOfDay());
        }
    }

    private static function key(string $who): string
    {
        return 'image_make:'.now()->toDateString().':'.$who;
    }
}
