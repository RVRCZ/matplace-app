<?php

namespace App\Domain\Farm;

use App\Models\FarmColor;
use Illuminate\Support\Facades\Schema;

/**
 * The filament colours every tool offers: one source of truth, the catalogue of the farm (farm_colors).
 *
 * A colour is named by its catalogue `code`. The nine built-in names (white, black…) are what designs stored
 * before the catalogue carry and what a site without a farm (development, tests) offers; they stay valid values
 * and are mapped to the nearest spool in stock when an old design is opened again.
 */
final class Palette
{
    /** The built-in colours as their swatches show them (the same values as FILAMENT in resources/js/calc/viewer.ts). */
    public const BUILT_IN = ['white' => '#EDE6D6', 'black' => '#17171A', 'grey' => '#8C9199', 'brown' => '#C2996B', 'red' => '#B8211F', 'blue' => '#213D78', 'green' => '#297345', 'yellow' => '#EBBD29', 'orange' => '#D1521F'];

    /** Groups of the colour window, in the order they are shown. */
    public const HUES = ['white_grey', 'black', 'red', 'orange_yellow', 'green', 'blue_violet', 'brown_beige', 'special'];

    /** Finishes that have no single colour a hue could be read from. */
    private const SPECIAL_FINISHES = ['special', 'luminous', 'glitter'];

    private const SPECIAL_WORDS = ['duh', 'rainbow', 'wood', 'dřev', 'drev', 'svít', 'svit', 'glow', 'marble', 'mramor', 'multi', 'dual', 'tri-', 'tricolor', 'gradient', 'přechod'];

    /** @var list<array<string, mixed>>|null */
    private ?array $rows = null;

    /** True when the colours come from the farm's catalogue, false when the nine built-in ones stand in. */
    public function fromFarm(): bool
    {
        $this->all();

        return $this->farm;
    }

    private bool $farm = false;

    /**
     * @return list<array{code: string, name: string, hex: string, material: string, finish: string, photo: ?string, in_stock: bool, hue: string, light: float}>
     */
    public function all(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $rows = [];
        if (config('farm.enabled') && Schema::hasTable('farm_colors')) {
            $colors = FarmColor::with('material')->where('enabled', true)->whereNotNull('code')->whereNotNull('hex')
                ->whereHas('material', fn ($m) => $m->where('enabled', true))->orderBy('sort')->orderBy('id')->get();
            foreach ($colors as $c) {
                if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c->hex)) {
                    continue;
                }
                $finish = (string) ($c->material->finish ?? 'solid');
                $rows[] = [
                    'code' => (string) $c->code, 'name' => $c->displayName(), 'hex' => strtolower($c->hex),
                    'material' => (string) $c->material->code, 'finish' => $finish, 'photo' => $c->photoUrl(), 'in_stock' => (bool) $c->in_stock,
                    'hue' => self::hue($c->hex, $finish, $c->name.' '.$c->name_en), 'light' => round(self::lab($c->hex)[0], 1),
                    // what the search of the colour window looks through: both names and the code, without accents
                    'search' => self::plain($c->name.' '.$c->name_en.' '.$c->code),
                ];
            }
        }
        $this->farm = $rows !== [];
        if (! $rows) {
            foreach (self::BUILT_IN as $name => $hex) {
                $rows[] = ['code' => $name, 'name' => __('color.'.$name), 'hex' => strtolower($hex), 'material' => 'PLA', 'finish' => 'solid', 'photo' => null, 'in_stock' => true,
                    'hue' => self::hue($hex, 'solid', ''), 'light' => round(self::lab($hex)[0], 1), 'search' => self::plain(__('color.'.$name).' '.$name)];
            }
        }

        return $this->rows = $rows;
    }

    /** What the browser gets: the colours, the built-in names as catalogue codes, and whether this is the real catalogue. */
    public function payload(): array
    {
        return ['items' => $this->all(), 'legacy' => $this->legacy(), 'farm' => $this->fromFarm(), 'hues' => self::HUES];
    }

    /** @return list<string> every value a colour field may carry: catalogue codes and the built-in names */
    public function codes(): array
    {
        return array_values(array_unique(array_merge(array_column($this->all(), 'code'), array_keys(self::BUILT_IN))));
    }

    public function has(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    /** The swatch of a colour; a built-in name answers too, an unknown code gets null. */
    public function hex(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        // a built-in name keeps the value designs were stored and exported with
        if (isset(self::BUILT_IN[$code])) {
            return self::BUILT_IN[$code];
        }
        foreach ($this->all() as $row) {
            if ($row['code'] === $code) {
                return $row['hex'];
            }
        }
        // a spool that left the catalogue: a design stored with it still knows its colour
        $gone = config('farm.enabled') && Schema::hasTable('farm_colors') ? FarmColor::where('code', $code)->value('hex') : null;

        return is_string($gone) && preg_match('/^#[0-9a-fA-F]{6}$/', $gone) ? strtolower($gone) : null;
    }

    /** False for a spool that is switched off or out of stock (the design keeps its colour, the page says so). */
    public function inStock(string $code): bool
    {
        foreach ($this->all() as $row) {
            if ($row['code'] === $code) {
                return $row['in_stock'];
            }
        }

        return ! $this->fromFarm() && isset(self::BUILT_IN[$code]);
    }

    /**
     * The built-in names as codes of the catalogue: the nearest spool in stock, plain PLA kinds before the special ones.
     *
     * @return array<string, string>
     */
    public function legacy(): array
    {
        $map = [];
        foreach (self::BUILT_IN as $name => $hex) {
            $map[$name] = $this->nearest($hex) ?? $name;
        }

        return $map;
    }

    /** The code of the colour nearest to a hex (CIE76 in Lab); spools in stock and of a plain finish come first. */
    public function nearest(string $hex): ?string
    {
        $want = self::lab($hex);
        $best = null;
        $bestD = INF;
        foreach ($this->all() as $row) {
            [$l, $a, $b] = self::lab($row['hex']);
            $d = sqrt(($l - $want[0]) ** 2 + ($a - $want[1]) ** 2 + ($b - $want[2]) ** 2);
            $d += ($row['in_stock'] ? 0 : 40) + ($row['hue'] === 'special' ? 25 : 0) + (in_array($row['finish'], ['solid', 'matte'], true) ? 0 : 6);
            if ($d < $bestD) {
                [$best, $bestD] = [$row['code'], $d];
            }
        }

        return $best;
    }

    /** Which group of the colour window a colour belongs to. */
    public static function hue(string $hex, string $finish = 'solid', string $name = ''): string
    {
        $plain = self::plain($name);
        if (in_array($finish, self::SPECIAL_FINISHES, true)) {
            return 'special';
        }
        foreach (self::SPECIAL_WORDS as $word) {
            if ($plain !== '' && str_contains($plain, self::plain($word))) {
                return 'special';
            }
        }
        [$r, $g, $b] = array_map(fn ($h) => hexdec($h) / 255, str_split(ltrim($hex, '#'), 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $light = ($max + $min) / 2;
        $delta = $max - $min;
        $sat = $delta < 1e-6 ? 0.0 : $delta / (1 - abs(2 * $light - 1) + 1e-9);
        if ($light < 0.13 || ($light < 0.2 && $sat < 0.25)) {
            return 'black';
        }
        if ($sat < 0.12 || $delta < 0.06) {
            return 'white_grey';
        }
        $h = match (true) {
            $max === $r => fmod(($g - $b) / $delta, 6) * 60,
            $max === $g => (($b - $r) / $delta + 2) * 60,
            default => (($r - $g) / $delta + 4) * 60,
        };
        $h = $h < 0 ? $h + 360 : $h;
        // browns and beiges are dull or dark oranges
        if ($h >= 12 && $h < 50 && ($sat < 0.55 || $light < 0.4)) {
            return 'brown_beige';
        }

        return match (true) {
            $h < 12 || $h >= 320 => 'red',
            $h < 70 => 'orange_yellow',
            $h < 170 => 'green',
            default => 'blue_violet',
        };
    }

    /** @return array{0: float, 1: float, 2: float} CIE L*a*b* (D65) of an sRGB hex */
    public static function lab(string $hex): array
    {
        [$r, $g, $b] = array_map(function ($h) {
            $v = hexdec($h) / 255;

            return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));
        $x = (0.4124 * $r + 0.3576 * $g + 0.1805 * $b) / 0.95047;
        $y = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        $z = (0.0193 * $r + 0.1192 * $g + 0.9505 * $b) / 1.08883;
        $f = fn (float $t) => $t > 0.008856 ? $t ** (1 / 3) : 7.787 * $t + 16 / 116;

        return [116 * $f($y) - 16, 500 * ($f($x) - $f($y)), 200 * ($f($y) - $f($z))];
    }

    /** Lower case without accents: what a search compares. */
    public static function plain(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/[^a-z0-9+ _-]+/', '', $ascii !== false ? $ascii : $text) ?? '';
    }
}
