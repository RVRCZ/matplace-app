<?php

namespace App\Support;

/** Countries an address can be in: the EU, the United Kingdom and the USA. Names live in lang/<locale>/countries.php. */
final class Countries
{
    public const EU = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

    public const CODES = [...self::EU, 'GB', 'US'];

    /**
     * Code → name in the language of the page, sorted by name; the home market first.
     *
     * @param  list<string>|null  $codes  a subset (delivery offers only where we ship)
     * @return array<string, string>
     */
    public static function names(?array $codes = null, ?string $locale = null): array
    {
        $names = array_intersect_key((array) __('countries', [], $locale), array_flip($codes ?? self::CODES));
        $collator = class_exists(\Collator::class) ? new \Collator($locale ?? app()->getLocale()) : null;
        uasort($names, fn (string $a, string $b) => $collator ? $collator->compare($a, $b) : strcmp($a, $b));

        return isset($names['CZ']) ? ['CZ' => $names['CZ']] + $names : $names;
    }

    public static function name(?string $code, ?string $locale = null): string
    {
        $code = strtoupper((string) $code);

        return (string) (((array) __('countries', [], $locale))[$code] ?? $code);
    }

    public static function inEu(?string $code): bool
    {
        return in_array(strtoupper((string) $code), self::EU, true);
    }
}
