<?php

namespace App\Domain\Farm;

use App\Models\FarmColor;
use App\Models\FarmPrinterSlot;
use Illuminate\Support\Facades\Schema;

/**
 * What a colour of a design is, and which spool of the farm (farm_colors) is nearest to it.
 *
 * A design is drawn in any colour: its value is the colour itself, a hex like "#2a7fd5" (the tool pages offer sixteen
 * basic ones and a free choice). The farm's spools are shown later, on the calculation and on the farm's pages, where
 * `nearest()` finds the spool for each colour of the design. Two older kinds of value stay valid, because stored
 * designs carry them: the `code` of a spool of the catalogue, and the nine built-in names (white, black…).
 */
final class Palette
{
    /** The built-in colours as their swatches show them (the same values as FILAMENT in resources/js/calc/viewer.ts). */
    public const BUILT_IN = ['white' => '#EDE6D6', 'black' => '#17171A', 'grey' => '#8C9199', 'brown' => '#C2996B', 'red' => '#B8211F', 'blue' => '#213D78', 'green' => '#297345', 'yellow' => '#EBBD29', 'orange' => '#D1521F'];

    /**
     * The basic colours of the colour window, in the order they are shown. The one place that says what they look like:
     * the browser gets them with their names from `payload()` (`colors.basic`), the tools take them as the colours a
     * part gets by itself (the lightest for a plate, the darkest for letters…).
     */
    public const BASIC = [
        'white' => '#ffffff', 'black' => '#1a1a1a', 'grey' => '#8c9199', 'red' => '#d62828', 'orange' => '#f77f00', 'yellow' => '#f6c915',
        'green' => '#2e9e4f', 'turquoise' => '#1fb5a8', 'blue' => '#1f6fd6', 'violet' => '#7a3fb0', 'pink' => '#ef6aa7', 'brown' => '#7a4a2a',
        'beige' => '#d9c3a0', 'gold' => '#c9a227', 'silver' => '#c0c4c8', 'copper' => '#b5683a',
    ];

    /** What a spool's code may look like when a stored design brings it back with its colour. */
    private const CODE = '/^[\p{L}\p{N} +_.,()\/-]{1,40}$/u';

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
            // the spools sitting in a slot of a machine of the farm right now: what the farm's page can offer
            $loaded = FarmPrinterSlot::query()->where('enabled', true)->whereNotNull('farm_color_id')
                ->whereHas('printer', fn ($q) => $q->where('enabled', true))->pluck('farm_color_id')->map(fn ($id) => (int) $id)->flip();
            foreach ($colors as $c) {
                if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c->hex)) {
                    continue;
                }
                $finish = (string) ($c->material->finish ?? 'solid');
                $rows[] = [
                    'code' => (string) $c->code, 'name' => $c->displayName(), 'hex' => strtolower($c->hex),
                    'material' => (string) $c->material->code, 'finish' => $finish, 'photo' => $c->photoUrl(), 'in_stock' => (bool) $c->in_stock,
                    'loaded' => isset($loaded[$c->id]),
                    'hue' => self::hue($c->hex, $finish, $c->name.' '.$c->name_en), 'light' => round(self::lab($c->hex)[0], 1),
                    // what the search of the colour window looks through: both names and the code, without accents
                    'search' => self::plain($c->name.' '.$c->name_en.' '.$c->code),
                ];
            }
        }
        $this->farm = $rows !== [];
        if (! $rows) {
            foreach (self::BUILT_IN as $name => $hex) {
                $rows[] = ['code' => $name, 'name' => __('color.'.$name), 'hex' => strtolower($hex), 'material' => 'PLA', 'finish' => 'solid', 'photo' => null, 'in_stock' => true, 'loaded' => false,
                    'hue' => self::hue($hex, 'solid', ''), 'light' => round(self::lab($hex)[0], 1), 'search' => self::plain(__('color.'.$name).' '.$name)];
            }
        }

        return $this->rows = $rows;
    }

    /**
     * What the browser gets: the basic colours of the colour window with their names, what the built-in names look like,
     * and the catalogue (the farm's pages offer it; a tool page only reads it, to show a design stored with a spool).
     */
    public function payload(): array
    {
        return ['items' => $this->all(), 'legacy' => $this->legacy(), 'farm' => $this->fromFarm(), 'hues' => self::HUES,
            'basic' => self::basic(), 'named' => array_map('strtolower', self::BUILT_IN)];
    }

    /** @return list<array{key: string, hex: string, name: string}> the basic colours, named in the visitor's language */
    public static function basic(): array
    {
        return array_map(fn (string $key, string $hex) => ['key' => $key, 'hex' => $hex, 'name' => __('toolpage.color.basic.'.$key)], array_keys(self::BASIC), array_values(self::BASIC));
    }

    /** A colour chosen freely: the value is the colour itself. */
    public static function isCustom(mixed $code): bool
    {
        return is_string($code) && preg_match('/^#[0-9a-f]{6}$/i', $code) === 1;
    }

    /** The one way a value is written: a free colour in lower case, a spool's code and a built-in name as they are. */
    public static function canonical(string $code): string
    {
        return self::isCustom($code) ? strtolower($code) : $code;
    }

    /**
     * The one rule of a colour field: a free colour ("#2a7fd5"), the code of a spool of the catalogue or a built-in name.
     * A stored design sends its colours back as {code, hex}: that form passes too, and with a valid hex even when its
     * spool has left the catalogue since (the design keeps the colour it was made in).
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $code = is_array($value) ? ($value['code'] ?? null) : $value;
            $kept = is_array($value) && self::isCustom($value['hex'] ?? null) && is_string($code) && preg_match(self::CODE, $code) === 1;
            if (! is_string($code) || ! ($kept || app(self::class)->has($code))) {
                $fail(__('validation.in', ['attribute' => $attribute]));
            }
        };
    }

    /**
     * The colours a tool chooses from by itself and knows the look of: the basic ones and what the visitor picked,
     * each as [value, hex]. No spool is among them: a design is drawn in colours, the farm matches its spools later.
     *
     * @param  iterable<mixed>  $picked  values of the design (free colours, spool codes, built-in names; anything else is skipped)
     * @return list<array{0: string, 1: string}>
     */
    public function free(iterable $picked = []): array
    {
        $out = [];
        foreach (self::BASIC as $hex) {
            $out[$hex] = [$hex, $hex];
        }
        foreach ($picked as $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }
            $code = self::canonical($code);
            $hex = $this->hex($code);
            if (! isset($out[$code]) && $hex !== null) {
                $out[$code] = [$code, strtolower($hex)];
            }
        }

        return array_values($out);
    }

    /**
     * The spool of the catalogue nearest to a colour, as a page shows it; null on a site without a catalogue.
     *
     * @return array{code: string, name: string, hex: string, material: string, finish: string, photo: ?string, in_stock: bool}|null
     */
    public function nearestSpool(?string $hex): ?array
    {
        if (! self::isCustom($hex) || ! $this->fromFarm() || ($code = $this->nearest($hex)) === null) {
            return null;
        }
        foreach ($this->all() as $row) {
            if ($row['code'] === $code) {
                return array_intersect_key($row, array_flip(['code', 'name', 'hex', 'material', 'finish', 'photo', 'in_stock', 'loaded']));
            }
        }

        return null;
    }

    /** @return list<string> every value a colour field may carry: catalogue codes and the built-in names */
    public function codes(): array
    {
        return array_values(array_unique(array_merge(array_column($this->all(), 'code'), array_keys(self::BUILT_IN))));
    }

    /** True for every value a colour field may carry: a free colour, a spool of the catalogue, a built-in name. */
    public function has(string $code): bool
    {
        return self::isCustom($code) || in_array($code, $this->codes(), true);
    }

    /** The swatch of a colour; a free colour is its own, a built-in name answers too, an unknown code gets null. */
    public function hex(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        if (self::isCustom($code)) {
            return strtolower($code);
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
        if (self::isCustom($code)) {
            return true;                     // a free colour is no spool: nothing of it can be out of stock
        }
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
            $map[$name] = $this->nearest($hex, false) ?? $name;
        }

        return $map;
    }

    /**
     * The code of the colour nearest to a hex (CIE76 in Lab). Among the spools loaded in the machines when any is,
     * so the calculation names the very spool the farm's page then ticks (both measure the same way: distance()); the
     * whole catalogue only when nothing is loaded or when asked ($loadedFirst = false: a name for a stored design).
     * Spools in stock and of a plain finish come first.
     */
    public function nearest(string $hex, bool $loadedFirst = true): ?string
    {
        $rows = $this->all();
        if ($loadedFirst && ($loaded = array_filter($rows, fn ($r) => ! empty($r['loaded'])))) {
            $rows = $loaded;
        }
        $best = null;
        $bestD = INF;
        foreach ($rows as $row) {
            $d = self::distance($hex, $row['hex']);
            $d += ($row['in_stock'] ? 0 : 40) + ($row['hue'] === 'special' ? 25 : 0) + (in_array($row['finish'], ['solid', 'matte'], true) ? 0 : 6);
            if ($d < $bestD) {
                [$best, $bestD] = [$row['code'], $d];
            }
        }

        return $best;
    }

    /** How far two colours are apart as the eye sees it (CIE76 in Lab); a value that is not a hex counts as grey. */
    public static function distance(?string $a, ?string $b): float
    {
        $hex = fn (?string $h) => preg_match('/^#?[0-9a-f]{6}$/i', (string) $h) ? '#'.ltrim((string) $h, '#') : '#808080';
        [$l1, $a1, $b1] = self::lab($hex($a));
        [$l2, $a2, $b2] = self::lab($hex($b));

        return sqrt(($l1 - $l2) ** 2 + ($a1 - $a2) ** 2 + ($b1 - $b2) ** 2);
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
