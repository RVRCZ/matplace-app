<?php

namespace App\Domain\Farm;

use App\Models\FarmOrder;

/**
 * Print settings beyond the three presets. A customer may write the numbers a drawing asks for (infill percent,
 * walls, top and bottom layers); the admin may write any process setting of the slicer onto one order. Both end
 * up as overrides of the process profile in PrepareFarmOrder, the admin's last.
 */
final class PrintSettings
{
    /** field → [min, max]; null leaves the preset / profile value */
    public const FIELDS = ['infill' => [5, 100], 'walls' => [1, 6], 'top' => [0, 10], 'bottom' => [0, 10]];

    /** field → allowed words; absent = the preset / profile value. Ironing: the topmost surface only, or every top surface. */
    public const CHOICES = ['ironing' => ['topmost', 'top']];

    /** Orca's names for the customer's fields (infill goes through SliceParams::infillPercent, not here) */
    private const PROCESS = ['walls' => 'wall_loops', 'top' => 'top_shell_layers', 'bottom' => 'bottom_shell_layers'];

    /** Orca's names for the customer's choices; the words are Orca's own values (ironing_type: topmost | top) */
    private const PROCESS_CHOICES = ['ironing' => 'ironing_type'];

    /** Only settings of the slicing process, written as text; a key that is not a plain word is dropped. */
    private const KEY = '/^[a-z][a-z0-9_]{1,60}$/';

    /**
     * The customer's numbers and choices, cleaned: outside the range they are pulled in, a word that is not on the
     * list and unknown fields are dropped, nothing → null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, int|string>|null
     */
    public static function clean(array $input): ?array
    {
        $out = [];
        foreach (self::FIELDS as $field => [$min, $max]) {
            $v = $input[$field] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $out[$field] = max($min, min($max, (int) $v));
        }
        foreach (self::CHOICES as $field => $words) {
            $v = $input[$field] ?? null;
            if (is_string($v) && in_array($v, $words, true)) {
                $out[$field] = $v;
            }
        }

        return $out ?: null;
    }

    /** @return array<string, int|string> */
    public static function of(FarmOrder $order): array
    {
        return self::clean((array) $order->print_settings) ?? [];
    }

    /**
     * @param  array<string, int|string>  $settings
     * @return array<string, string> process settings
     */
    public static function process(array $settings): array
    {
        $out = [];
        foreach (self::PROCESS as $field => $key) {
            if (isset($settings[$field])) {
                $out[$key] = (string) $settings[$field];
            }
        }
        foreach (self::PROCESS_CHOICES as $field => $key) {
            if (isset($settings[$field]) && in_array($settings[$field], self::CHOICES[$field], true)) {
                $out[$key] = (string) $settings[$field];
            }
        }

        return $out;
    }

    /**
     * The admin's overrides of one order, as the slicer takes them: plain keys, scalar values written as text.
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<string, string>
     */
    public static function cleanOverrides(?array $raw): array
    {
        $out = [];
        foreach ((array) $raw as $key => $value) {
            if (is_string($key) && preg_match(self::KEY, $key) && is_scalar($value)) {
                $out[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function adminOverrides(FarmOrder $order): array
    {
        return self::cleanOverrides($order->admin_overrides);
    }
}
