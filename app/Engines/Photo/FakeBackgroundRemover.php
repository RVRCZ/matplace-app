<?php

namespace App\Engines\Photo;

use App\Engines\Exceptions\EngineException;

/**
 * Tests and local work without rembg: the middle of the photo (an ellipse) stays, the rest becomes transparent.
 * Small (at most 400 px) so the pixel loop is quick. Set $fail to make the next cut fail.
 */
final class FakeBackgroundRemover implements BackgroundRemover
{
    public static ?string $fail = null;

    /** @var list<string> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$fail = null;
        self::$calls = [];
    }

    public function available(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    public function cut(string $source, string $target): array
    {
        self::$calls[] = $source;
        if (self::$fail !== null) {
            throw new EngineException(self::$fail);
        }
        $img = @imagecreatefromstring((string) @file_get_contents($source));
        if (! $img) {
            throw new EngineException('not a picture');
        }
        $k = min(1, 400 / max(imagesx($img), imagesy($img)));
        $w = max(8, (int) round(imagesx($img) * $k));
        $h = max(8, (int) round(imagesy($img) * $k));
        $small = imagescale($img, $w, $h);
        imagedestroy($img);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        $kept = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dx = ($x - $w / 2) / ($w * 0.42);
                $dy = ($y - $h / 2) / ($h * 0.46);
                if ($dx * $dx + $dy * $dy <= 1) {
                    imagesetpixel($out, $x, $y, imagecolorat($small, $x, $y) & 0x00FFFFFF);
                    $kept++;
                }
            }
        }
        imagedestroy($small);
        imagepng($out, $target);
        imagedestroy($out);

        return ['width' => $w, 'height' => $h, 'coverage' => round($kept / ($w * $h), 3)];
    }
}
