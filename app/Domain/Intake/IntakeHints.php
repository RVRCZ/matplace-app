<?php

namespace App\Domain\Intake;

use App\Domain\Farm\FarmSettings;
use App\Models\FarmSetting;
use App\Support\Locales;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The examples under the first screen's field (docs/AN.md §23.3): a short label and a sample wish that the click
 * writes into the field. Which ones are shown follows the calendar of config/home.php `intake.hints`: a few from the
 * events whose window is open today in the page's language (St Nicholas, Christmas…), the rest from those that last
 * all year (a bust from a photo, a spare part, a birthday, a wedding). Without an open event only the steady ones.
 *
 * The admin switches an event off and changes its dates and its texts at /admin/intake/hints: what differs from the
 * config is kept in the farm's settings table under the key `home.hints` (no deploy, no migration).
 */
final class IntakeHints
{
    /** The key of the admin's changes in farm_settings. */
    public const SETTING = 'home.hints';

    public const MAX_EXAMPLES = 4;

    public const LABEL_MAX = 40;

    public const FILL_MAX = 300;

    /** @return array<string, array<string, mixed>> */
    public static function events(): array
    {
        return (array) config('home.intake.hints.events');
    }

    /**
     * What the admin changed: per event `off`, `from` / `to` (MM-DD) and `texts` per language (pairs label, wish).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function overrides(): array
    {
        try {
            $raw = app(FarmSettings::class)->get(self::SETTING);
        } catch (\Throwable) {
            $raw = null;   // no table yet (a fresh install): the config alone
        }

        return is_array($raw) ? $raw : [];
    }

    /** @param  array<string, array<string, mixed>>  $overrides */
    public static function save(array $overrides): void
    {
        FarmSetting::updateOrCreate(['key' => self::SETTING], ['value' => $overrides]);
        app()->forgetInstance(FarmSettings::class);
    }

    /**
     * The chips of the page: `seasonal` at most from the open events (the one that ends soonest first, one example of
     * each before the second of any), then the steady ones up to `total`.
     *
     * @return list<array{code: string, event: string, label: string, fill: string, seasonal: bool}>
     */
    public static function chips(?string $locale = null, ?CarbonInterface $today = null): array
    {
        $locale = Locales::supported((string) $locale) ? (string) $locale : app()->getLocale();
        $today = ($today ? Carbon::instance($today) : now())->startOfDay();
        $total = max(1, (int) config('home.intake.hints.total', 6));
        $seasonal = max(0, (int) config('home.intake.hints.seasonal', 3));

        $open = [];
        $steady = [];
        foreach (self::events() as $key => $event) {
            if (self::off($key)) {
                continue;
            }
            if (! empty($event['always'])) {
                $steady[$key] = self::examples($key, $locale, (int) $today->year);
            } elseif ($window = self::window($key, $locale, $today)) {
                $open[$key] = ['to' => $window['to'], 'examples' => self::examples($key, $locale, $window['year'])];
            }
        }
        uasort($open, fn (array $a, array $b) => $a['to'] <=> $b['to']);

        $out = self::deal(array_map(fn (array $o) => $o['examples'], $open), $seasonal, true);
        // the steady ones: the first event's own examples first (a bust, a pet, a part…), then one of each of the others in turn
        $first = array_key_first($steady);
        $rest = $first === null ? [] : array_merge(self::deal([$first => $steady[$first]], PHP_INT_MAX, false), self::deal(array_slice($steady, 1, null, true), PHP_INT_MAX, false));

        return array_slice(array_merge($out, $rest), 0, $total);
    }

    /**
     * One example of each event in turn, until `limit`.
     *
     * @param  array<string, list<array{label: string, fill: string}>>  $groups
     * @return list<array{code: string, event: string, label: string, fill: string, seasonal: bool}>
     */
    private static function deal(array $groups, int $limit, bool $seasonal): array
    {
        $out = [];
        for ($i = 0; $i < self::MAX_EXAMPLES && count($out) < $limit; $i++) {
            foreach ($groups as $key => $examples) {
                if (isset($examples[$i]) && count($out) < $limit) {
                    $out[] = ['code' => $key.'.'.($i + 1), 'event' => (string) $key, 'seasonal' => $seasonal] + $examples[$i];
                }
            }
        }

        return $out;
    }

    public static function off(string $key): bool
    {
        return ! empty(self::overrides()[$key]['off']);
    }

    /** The languages an event is offered in. */
    public static function locales(string $key): array
    {
        $date = self::events()[$key]['date'] ?? null;

        return is_array($date) ? array_keys($date) : Locales::SUPPORTED;
    }

    /**
     * The window an event is offered in around `today`, in a language: from, to, and the year of the event's day.
     * Null when the language does not have the event or today is outside. The admin's `from` / `to` (MM-DD) replace
     * the computed ends; a window may run over the New Year.
     *
     * @return array{from: Carbon, to: Carbon, year: int}|null
     */
    public static function window(string $key, string $locale, CarbonInterface $today): ?array
    {
        $today = Carbon::instance($today)->startOfDay();
        foreach ([$today->year - 1, $today->year, $today->year + 1] as $year) {
            $w = self::windowOf($key, $locale, $year);
            if ($w && $today->betweenIncluded($w['from'], $w['to'])) {
                return $w;
            }
        }

        return null;
    }

    /**
     * The window of the event's day in one year (the day itself decides the year: Christmas 2026 is offered from November 2026).
     *
     * @return array{from: Carbon, to: Carbon, year: int}|null
     */
    public static function windowOf(string $key, string $locale, int $year): ?array
    {
        $event = self::events()[$key] ?? null;
        if (! $event || ! empty($event['always'])) {
            return null;
        }
        $rule = is_array($event['date'] ?? null) ? ($event['date'][$locale] ?? null) : ($event['date'] ?? null);
        $day = $rule ? self::day((string) $rule, $year) : null;
        if (! $day) {
            return null;
        }
        $from = $day->copy()->subDays((int) ($event['lead'] ?? 30));
        $to = $day->copy()->subDays((int) ($event['stop'] ?? 4));
        $over = self::overrides()[$key] ?? [];
        $adminTo = self::monthDay($over['to'] ?? null);
        if ($adminTo) {
            $to = self::before($adminTo, $day);
        } elseif (! empty($event['until_setting'])) {
            // the farm's own last day (the Christmas deadline of /admin/farm/settings) ends the offer when it is set for this year's day
            $raw = (string) app(FarmSettings::class)->get($event['until_setting']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $set = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
                if ($set->lte($day) && $set->diffInDays($day) < 60) {
                    $to = $set;
                }
            }
        }
        if ($adminFrom = self::monthDay($over['from'] ?? null)) {
            $from = self::before($adminFrom, $to);
        }

        return $from->lte($to) ? ['from' => $from, 'to' => $to, 'year' => (int) $day->year + (int) ($event['year_offset'] ?? 0)] : null;
    }

    /** The last MM-DD on or before a day (a window that starts in December and ends in January keeps its order). */
    private static function before(array $md, Carbon $day): Carbon
    {
        $d = Carbon::create($day->year, $md[0], $md[1])->startOfDay();

        return $d->gt($day) ? $d->subYear() : $d;
    }

    /** @return array{0: int, 1: int}|null month, day */
    public static function monthDay(mixed $raw): ?array
    {
        if (! is_string($raw) || ! preg_match('/^(\d{2})-(\d{2})$/', $raw, $m) || ! checkdate((int) $m[1], (int) $m[2], 2024)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /** The day of an event in a year: `MM-DD`, `easter` (Sunday), or `sunday:N:M` = the N-th Sunday of month M. */
    public static function day(string $rule, int $year): ?Carbon
    {
        if ($rule === 'easter') {
            // the Gregorian computus (Meeus/Jones/Butcher): no calendar extension is needed
            $a = $year % 19;
            $b = intdiv($year, 100);
            $c = $year % 100;
            $d = intdiv($b, 4);
            $e = $b % 4;
            $f = intdiv($b + 8, 25);
            $g = intdiv($b - $f + 1, 3);
            $h = (19 * $a + $b - $d - $g + 15) % 30;
            $i = intdiv($c, 4);
            $k = $c % 4;
            $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
            $m = intdiv($a + 11 * $h + 22 * $l, 451);
            $month = intdiv($h + $l - 7 * $m + 114, 31);
            $day = (($h + $l - 7 * $m + 114) % 31) + 1;

            return Carbon::create($year, $month, $day)->startOfDay();
        }
        if (preg_match('/^sunday:([1-5]):(\d{1,2})$/', $rule, $m)) {
            $first = Carbon::create($year, (int) $m[2], 1)->startOfDay();
            $first->addDays((7 - $first->dayOfWeek) % 7);

            return $first->addWeeks((int) $m[1] - 1);
        }
        $md = self::monthDay($rule);

        return $md ? Carbon::create($year, $md[0], $md[1])->startOfDay() : null;
    }

    /**
     * The examples of an event in a language, `:year` filled in.
     *
     * @return list<array{label: string, fill: string}>
     */
    public static function examples(string $key, string $locale, ?int $year = null): array
    {
        $year = (string) ($year ?? now()->year);

        return array_map(fn (array $e) => ['label' => str_replace(':year', $year, $e['label']), 'fill' => str_replace(':year', $year, $e['fill'])], self::raw($key, $locale));
    }

    /**
     * The examples as they are written (`:year` left in): the admin's pairs when there are any, else the texts of
     * the dictionary (`router.hint.<event>.<n>` and `.fill`).
     *
     * @return list<array{label: string, fill: string}>
     */
    public static function raw(string $key, string $locale): array
    {
        $own = self::overrides()[$key]['texts'][$locale] ?? null;
        $out = [];
        foreach (is_array($own) ? $own : [] as $pair) {
            if (is_array($pair) && trim((string) ($pair[0] ?? '')) !== '' && trim((string) ($pair[1] ?? '')) !== '') {
                $out[] = ['label' => (string) $pair[0], 'fill' => (string) $pair[1]];
            }
        }

        return array_slice($out ?: self::defaults($key, $locale), 0, self::MAX_EXAMPLES);
    }

    /**
     * The table of /admin/intake/hints: every event with its state, its window around today per language and its texts.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function table(?CarbonInterface $today = null): array
    {
        $today = ($today ? Carbon::instance($today) : now())->startOfDay();
        $over = self::overrides();
        $out = [];
        foreach (self::events() as $key => $event) {
            $windows = [];
            foreach (self::locales($key) as $locale) {
                // the window that is open, else the next one to come
                $w = self::window($key, $locale, $today);
                foreach ([$today->year, $today->year + 1] as $year) {
                    $next = $w ? null : self::windowOf($key, $locale, $year);
                    if ($next && $next['to']->gte($today)) {
                        $w = $next;
                    }
                    if ($w) {
                        break;
                    }
                }
                $windows[$locale] = $w ? ['from' => $w['from'], 'to' => $w['to'], 'open' => $today->betweenIncluded($w['from'], $w['to'])] : null;
            }
            $texts = [];
            foreach (Locales::SUPPORTED as $locale) {
                $texts[$locale] = ['own' => ! empty($over[$key]['texts'][$locale]), 'lines' => implode("\n", array_map(fn (array $e) => $e['label'].' | '.$e['fill'], self::raw($key, $locale)))];
            }
            $out[$key] = [
                'name' => (string) ($event['name'] ?? $key), 'always' => ! empty($event['always']), 'off' => ! empty($over[$key]['off']),
                'from' => (string) ($over[$key]['from'] ?? ''), 'to' => (string) ($over[$key]['to'] ?? ''), 'locales' => self::locales($key),
                'windows' => $windows, 'texts' => $texts, 'expect' => (array) ($event['examples'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * The admin's form for one event → what is kept. Lines are `label | wish`; an empty box means the dictionary's
     * texts. Returns the error of the first line that does not fit, or null.
     *
     * @param  array<string, mixed>  $input  off, from, to (D.M. or MM-DD or empty), texts[locale] (lines)
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    public static function parse(string $key, array $input): array
    {
        $keep = [];
        if (! empty($input['off'])) {
            $keep['off'] = true;
        }
        foreach (['from', 'to'] as $end) {
            $raw = trim((string) ($input[$end] ?? ''));
            if ($raw === '') {
                continue;
            }
            $md = preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.?$/', $raw, $m) ? sprintf('%02d-%02d', $m[2], $m[1]) : $raw;
            if (! self::monthDay($md)) {
                return [[], "Datum „{$raw}“ nečtu: napište den a měsíc, třeba 5.11."];
            }
            $keep[$end] = $md;
        }
        foreach (Locales::SUPPORTED as $locale) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($input['texts'][$locale] ?? '')) ?: []), fn (string $l) => $l !== ''));
            if (! $lines) {
                continue;
            }
            if (count($lines) > self::MAX_EXAMPLES) {
                return [[], "{$locale}: nejvýš ".self::MAX_EXAMPLES.' příklady.'];
            }
            $pairs = [];
            foreach ($lines as $line) {
                $parts = array_map('trim', explode('|', $line, 2));
                if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                    return [[], "{$locale}: řádek „".mb_substr($line, 0, 40).'“ nemá tvar Štítek | Vzorové přání.'];
                }
                if (mb_strlen($parts[0]) > self::LABEL_MAX || mb_strlen($parts[1]) > self::FILL_MAX) {
                    return [[], "{$locale}: štítek nejvýš ".self::LABEL_MAX.' znaků, přání nejvýš '.self::FILL_MAX.'.'];
                }
                $pairs[] = [$parts[0], $parts[1]];
            }
            // the same as the dictionary says: nothing to keep, a later change of the dictionary still shows
            $default = array_map(fn (array $e) => [$e['label'], $e['fill']], self::defaults($key, $locale));
            if ($pairs !== $default) {
                $keep['texts'][$locale] = $pairs;
            }
        }

        return [$keep, null];
    }

    /**
     * The dictionary's examples of an event, `:year` left in.
     *
     * @return list<array{label: string, fill: string}>
     */
    public static function defaults(string $key, string $locale): array
    {
        $out = [];
        foreach (array_keys((array) (self::events()[$key]['examples'] ?? [])) as $i) {
            $id = 'router.hint.'.$key.'.'.($i + 1);
            $out[] = ['label' => (string) __($id, [], $locale), 'fill' => (string) __($id.'.fill', [], $locale)];
        }

        return $out;
    }
}
