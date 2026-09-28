<?php

namespace App\Domain\Farm;

/**
 * Drops the layer pictures in which the head is not parked (the agent picked a moment of printing).
 *
 * Every picture is compared with the per-pixel median of its neighbours (±WINDOW): the head is parked on most of
 * them, so the median shows the parked scene and one more layer changes it only a little. A picture with the head
 * over the object differs far more. Measured on F26-000018 (373 layer pictures, Kobra S1): stray pictures deviate
 * 20–35 grey levels, good ones below 5, the median deviation is about 0.7 — the cut at 8× the median separates them.
 */
final class TimelapseFrames
{
    private const W = 64;

    private const H = 36;

    private const WINDOW = 7;

    private const FACTOR = 8.0;

    private const FLOOR = 4.0;       // grey levels: a print where nothing strays has a tiny median

    /**
     * @param  list<string>  $paths  JPEG files in time order
     * @return list<int> indexes of the stray pictures
     */
    public static function strays(array $paths): array
    {
        $n = count($paths);
        if ($n < 2 * self::WINDOW) {
            return [];
        }
        $pixels = array_map(self::grey(...), $paths);
        $dev = [];
        for ($i = 0; $i < $n; $i++) {
            if ($pixels[$i] === null) {
                $dev[$i] = INF;

                continue;
            }
            $lo = max(0, $i - self::WINDOW);
            $hi = min($n - 1, $i + self::WINDOW);
            $sum = 0.0;
            foreach ($pixels[$i] as $p => $v) {
                $column = [];
                for ($j = $lo; $j <= $hi; $j++) {
                    if ($pixels[$j] !== null) {
                        $column[] = $pixels[$j][$p];
                    }
                }
                sort($column);
                $sum += abs($v - $column[intdiv(count($column), 2)]);
            }
            $dev[$i] = $sum / count($pixels[$i]);
        }
        $finite = array_filter($dev, 'is_finite');
        sort($finite);
        $median = $finite ? $finite[intdiv(count($finite), 2)] : 0.0;
        $cut = max(self::FACTOR * $median, self::FLOOR);

        return array_keys(array_filter($dev, fn ($d) => $d > $cut));
    }

    /** @return list<int>|null grey values of a small copy, null for an unreadable file */
    private static function grey(string $path): ?array
    {
        $src = @imagecreatefromjpeg($path);
        if (! $src) {
            return null;
        }
        $small = imagecreatetruecolor(self::W, self::H);
        imagecopyresampled($small, $src, 0, 0, 0, 0, self::W, self::H, imagesx($src), imagesy($src));
        imagedestroy($src);
        $out = [];
        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                $c = imagecolorat($small, $x, $y);
                $out[] = (int) ((($c >> 16) & 255) * 0.299 + (($c >> 8) & 255) * 0.587 + ($c & 255) * 0.114);
            }
        }
        imagedestroy($small);

        return $out;
    }
}
