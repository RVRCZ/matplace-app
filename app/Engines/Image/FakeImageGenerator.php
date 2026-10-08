<?php

namespace App\Engines\Image;

use App\Engines\Exceptions\EngineException;
use App\Support\AiUsage;

/**
 * Tests and local work without a key: draws a picture with GD instead of asking anyone. A silhouette is a black
 * shape on white (a cat-like blob), a line drawing its outline, colour a few flat patches; the prompt is kept as
 * text in the picture's corner so a test can see what was asked. Set $fail to make the next call fail.
 */
final class FakeImageGenerator implements ImageGenerator
{
    public static ?string $fail = null;

    /** @var list<array{prompt: string, style: string, size: string}> */
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

    public function fromText(string $prompt, string $style, string $size, array $context = []): ImageResult
    {
        self::$calls[] = compact('prompt', 'style', 'size');
        if (self::$fail !== null) {
            throw new EngineException(self::$fail);
        }
        [$w, $h] = match ($size) {
            'wide' => [1024, 768], 'tall' => [768, 1024], default => [1024, 1024]
        };
        $img = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        $cx = (int) ($w / 2);
        $cy = (int) ($h / 2);
        $r = (int) (min($w, $h) * 0.28);
        if ($style === 'line') {
            imagesetthickness($img, 14);
            imageellipse($img, $cx, $cy + (int) ($r * 0.2), 2 * $r, 2 * $r, $black);
            imagepolygon($img, [$cx - $r, $cy - (int) ($r * 0.6), $cx - (int) ($r * 0.8), $cy - (int) ($r * 1.4), $cx - (int) ($r * 0.3), $cy - (int) ($r * 0.8)], $black);
            imagepolygon($img, [$cx + $r, $cy - (int) ($r * 0.6), $cx + (int) ($r * 0.8), $cy - (int) ($r * 1.4), $cx + (int) ($r * 0.3), $cy - (int) ($r * 0.8)], $black);
        } else {
            $fill = $style === 'colour' ? imagecolorallocate($img, 230, 110, 44) : $black;
            imagefilledellipse($img, $cx, $cy + (int) ($r * 0.2), 2 * $r, 2 * $r, $fill);
            imagefilledpolygon($img, [$cx - $r, $cy - (int) ($r * 0.6), $cx - (int) ($r * 0.8), $cy - (int) ($r * 1.4), $cx - (int) ($r * 0.3), $cy - (int) ($r * 0.8)], $fill);
            imagefilledpolygon($img, [$cx + $r, $cy - (int) ($r * 0.6), $cx + (int) ($r * 0.8), $cy - (int) ($r * 1.4), $cx + (int) ($r * 0.3), $cy - (int) ($r * 0.8)], $fill);
            if ($style === 'colour') {
                $eye = imagecolorallocate($img, 30, 60, 120);
                imagefilledellipse($img, $cx - (int) ($r * 0.35), $cy, (int) ($r * 0.25), (int) ($r * 0.25), $eye);
                imagefilledellipse($img, $cx + (int) ($r * 0.35), $cy, (int) ($r * 0.25), (int) ($r * 0.25), $eye);
            }
        }
        imagestring($img, 2, 6, $h - 16, mb_substr(preg_replace('/[^\x20-\x7E]/', '?', $prompt), 0, 60), $black);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        AiUsage::record('image', 'fake', ['input_tokens' => 10, 'output_tokens' => 0], 1, $context);

        return new ImageResult($bytes, 'image/png', 'fake', 10, 1);
    }
}
