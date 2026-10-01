<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Pictures for link previews (Open Graph), 1200 × 630, drawn with GD: no browser, no external service.
 * A template takes plain values (texts, paths of pictures on this machine) and the result is kept in
 * storage/app/og under the hash of what went in, so the same card is drawn once.
 */
final class OgImage
{
    public const W = 1200;

    public const H = 630;

    private const INK = [23, 43, 77];

    private const MUTED = [82, 97, 118];

    private const ACTION = [201, 71, 20];

    private const PAGE = [251, 247, 241];

    private const LINE = [221, 226, 234];

    private \GdImage $im;

    private function __construct()
    {
        $this->im = imagecreatetruecolor(self::W, self::H);
        imageantialias($this->im, true);
        imagefilledrectangle($this->im, 0, 0, self::W, self::H, $this->color(self::PAGE));
    }

    /**
     * A designer's portfolio: avatar, name, a line under it, and up to three covers on the right.
     *
     * @param  list<string>  $covers  absolute paths of pictures
     */
    public static function portfolio(string $name, string $line, ?string $avatar, array $covers): string
    {
        return self::cached('portfolio', [$name, $line, self::stamp($avatar), array_map([self::class, 'stamp'], $covers)], function (self $c) use ($name, $line, $avatar, $covers) {
            $covers = array_values(array_filter($covers, 'is_file'));
            $left = $covers ? 560 : self::W - 160;
            $y = 150;
            if ($avatar && is_file($avatar)) {
                $c->picture($avatar, 80, 110, 150, 150, 75);
                $y = 320;
            }
            $y = $c->text($name, 80, $y, $left, 62, self::INK, 2);
            $c->text($line, 80, $y + 24, $left, 30, self::MUTED, 2);
            // covers: one large tile and two small ones, or fewer when there are fewer
            $x = 680;
            if (count($covers) === 1) {
                $c->picture($covers[0], $x, 95, 440, 440, 28);
            } elseif (count($covers) === 2) {
                $c->picture($covers[0], $x, 95, 440, 212, 28);
                $c->picture($covers[1], $x, 323, 440, 212, 28);
            } elseif ($covers) {
                $c->picture($covers[0], $x, 95, 440, 270, 28);
                $c->picture($covers[1], $x, 381, 212, 154, 24);
                $c->picture($covers[2], $x + 228, 381, 212, 154, 24);
            }
            $c->brand();
        });
    }

    /**
     * A model the farm prints: its picture, its name, and what it costs to have it printed.
     */
    public static function model(string $title, string $line, ?string $cover, ?string $author = null): string
    {
        return self::card('model', $title, $line, $cover, $author);
    }

    /** A tool: its product picture and what it does. */
    public static function tool(string $title, string $line, ?string $picture): string
    {
        return self::card('tool', $title, $line, $picture);
    }

    /** An article: its cover and its headline. */
    public static function article(string $title, string $line, ?string $cover): string
    {
        return self::card('article', $title, $line, $cover);
    }

    /** Any other page: the name of the site and one line about it. */
    public static function site(string $title, string $line): string
    {
        return self::card('site', $title, $line, null);
    }

    /**
     * The common layout: words on the left, one picture on the right (when there is one, else the words get the
     * whole width), a small line above the brand for the author.
     */
    private static function card(string $kind, string $title, string $line, ?string $picture, ?string $small = null): string
    {
        return self::cached($kind, [$title, $line, self::stamp($picture), $small], function (self $c) use ($title, $line, $picture, $small) {
            $has = $picture && is_file($picture);
            $width = $has ? 540 : self::W - 160;
            // an accent bar the colour of the buttons, so the card is recognisable among others
            $c->rectangle(80, 96, 64, 8, self::ACTION);
            // a long headline gets smaller letters rather than an ellipsis
            $size = $has ? (mb_strlen($title) > 34 ? 42 : 54) : (mb_strlen($title) > 48 ? 54 : 66);
            $y = $c->text($title, 80, 130, $width, $size, self::INK, 3);
            $y = $c->text($line, 80, $y + 22, $width, 28, self::MUTED, $small ? 2 : 4);
            if ($small) {
                $c->text($small, 80, min($y + 18, self::H - 150), $width, 24, self::MUTED, 1);
            }
            if ($has) {
                $c->picture($picture, 680, 85, 440, 440, 28);
            }
            $c->brand();
        });
    }

    /** Path of a stored picture; drawn by $draw when it is not there yet. */
    public static function cached(string $kind, array $inputs, \Closure $draw): string
    {
        $path = storage_path('app/og/'.$kind.'-'.sha1(json_encode([$kind, $inputs, 5])).'.png');
        if (! is_file($path)) {
            $canvas = new self;
            $draw($canvas);
            File::ensureDirectoryExists(dirname($path));
            imagepng($canvas->im, $path, 6);
            imagedestroy($canvas->im);
        }

        return $path;
    }

    /** "matplace." in the corner, the way the header writes it. */
    public function brand(): void
    {
        $font = self::font();
        $box = imagettfbbox(34, 0, $font, 'matplace');
        imagettftext($this->im, 34, 0, 80, self::H - 62, $this->color(self::INK), $font, 'matplace');
        imagettftext($this->im, 34, 0, 80 + ($box[2] - $box[0]) + 2, self::H - 62, $this->color(self::ACTION), $font, '.');
    }

    /**
     * Text wrapped to a width, at most $maxLines lines (the last one ends with … when cut). Returns the y under it.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public function text(string $text, int $x, int $y, int $width, int $size, array $rgb, int $maxLines = 2): int
    {
        $font = self::font();
        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $try = $current === '' ? $word : $current.' '.$word;
            if ($current !== '' && $this->width($try, $size) > $width) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $try;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' .,;:').'…';
        }
        foreach ($lines as $i => $line) {
            while (mb_strlen($line) > 1 && $this->width($line, $size) > $width) {
                $line = rtrim(mb_substr($line, 0, -2)).'…';
            }
            imagettftext($this->im, $size, 0, $x, $y + (int) round($size * 1.05) + (int) round($i * $size * 1.4), $this->color($rgb), $font, $line);
        }

        return $y + (int) round(count($lines) * $size * 1.4);
    }

    /** A picture cropped to fill a box with rounded corners (radius = half the side makes a circle). */
    public function picture(string $path, int $x, int $y, int $w, int $h, int $radius = 0): void
    {
        $src = @imagecreatefromstring((string) @file_get_contents($path));
        if (! $src) {
            return;
        }
        imagepalettetotruecolor($src);
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) round($w / $scale);
        $ch = (int) round($h / $scale);
        // drawn at double size and scaled down: smooth edges for the rounded mask
        $tile = imagecreatetruecolor($w * 2, $h * 2);
        imagecopyresampled($tile, $src, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $w * 2, $h * 2, $cw, $ch);
        imagedestroy($src);
        if ($radius > 0) {
            $this->round($tile, $radius * 2);
        }
        imagecopyresampled($this->im, $tile, $x, $y, 0, 0, $w, $h, $w * 2, $h * 2);
        imagedestroy($tile);
    }

    public function rectangle(int $x, int $y, int $w, int $h, array $rgb): void
    {
        imagefilledrectangle($this->im, $x, $y, $x + $w, $y + $h, $this->color($rgb));
    }

    /** Paints the corners of a tile in the page colour (the tile is then copied onto the page). */
    private function round(\GdImage $tile, int $r): void
    {
        $w = imagesx($tile);
        $h = imagesy($tile);
        $r = min($r, intdiv(min($w, $h), 2));
        $page = imagecolorallocate($tile, ...self::PAGE);
        $mask = imagecreatetruecolor($w, $h);
        $keep = imagecolorallocate($mask, 255, 0, 255);
        $cut = imagecolorallocate($mask, 0, 0, 0);
        imagefilledrectangle($mask, 0, 0, $w, $h, $cut);
        imagefilledrectangle($mask, $r, 0, $w - $r - 1, $h - 1, $keep);
        imagefilledrectangle($mask, 0, $r, $w - 1, $h - $r - 1, $keep);
        foreach ([[$r, $r], [$w - $r - 1, $r], [$r, $h - $r - 1], [$w - $r - 1, $h - $r - 1]] as [$cx, $cy]) {
            imagefilledellipse($mask, $cx, $cy, $r * 2, $r * 2, $keep);
        }
        for ($yy = 0; $yy < $h; $yy++) {
            // only the corner bands can be outside the rounded shape
            if ($yy >= $r && $yy < $h - $r) {
                continue;
            }
            for ($xx = 0; $xx < $w; $xx++) {
                if (($xx < $r || $xx >= $w - $r) && imagecolorat($mask, $xx, $yy) === $cut) {
                    imagesetpixel($tile, $xx, $yy, $page);
                }
            }
        }
        imagedestroy($mask);
    }

    private function width(string $text, int $size): int
    {
        $box = imagettfbbox($size, 0, self::font(), $text);

        return $box[2] - $box[0];
    }

    private function color(array $rgb): int
    {
        return imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private static function font(): string
    {
        return (string) config('seo.og_font', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'));
    }

    /** A file's identity for the cache key: path and when it last changed. */
    private static function stamp(?string $path): ?string
    {
        return $path && is_file($path) ? $path.'@'.filemtime($path) : null;
    }
}
