<?php

namespace App\Domain\Farm;

use App\Models\FarmPrinter;

/**
 * What the operator calls a spool position (TODO #66, docs/AL.md §19). The Kobra X feeds from its own rack and from the
 * ACE box behind it: with the changer the rack keeps three places and the ACE four ("Stojan 1 až 3, ACE 1 až 4",
 * docs/AM.md §8), alone the rack has four. A Kobra S1 or 3 Max feeds from the ACE ("ACE 1 až 4", a second box
 * "ACE2 1 až 4"). Any other machine: "Pozice n". The order is matplace's slot order (T0 = the first position), the
 * same the agent's driver numbers (anycubic_lan.Position). A name the admin typed in (FarmPrinterSlot::label) wins.
 */
final class SpoolPositions
{
    /** Positions of one box: an ACE unit holds four, so does the rack of the Kobra X without a changer. */
    public const BOX = 4;

    /**
     * The names of every position of a machine with `$count` of them, the first position first.
     *
     * @return list<string>
     */
    public static function names(FarmPrinter $printer, ?int $count = null): array
    {
        $count ??= $printer->relationLoaded('slots') ? $printer->slots->count() : $printer->slots()->count();
        $count = max(0, (int) $count);
        $model = $printer->machineName();
        $out = [];
        if (preg_match('/kobra\s*x\b/i', $model)) {
            // the rack first, then the ACE units: a changer behind the machine takes the rack's fourth place for its feed
            $units = $count > self::BOX ? (int) ceil(($count - self::BOX) / self::BOX) : 0;
            $rack = $count - $units * self::BOX;
            for ($i = 1; $i <= $rack; $i++) {
                $out[] = __('farm.position.rack', ['n' => $i]);
            }
            for ($u = 1; $u <= $units; $u++) {
                for ($i = 1; $i <= self::BOX && count($out) < $count; $i++) {
                    $out[] = __($u === 1 ? 'farm.position.ace' : 'farm.position.ace_more', ['u' => $u, 'n' => $i]);
                }
            }

            return $out;
        }
        if (preg_match('/kobra/i', $model)) {
            // a Kobra with an ACE (S1 Combo, 3 Max Combo): ACE 1 to 4, a second box ACE2 1 to 4
            for ($k = 0; $k < $count; $k++) {
                $u = intdiv($k, self::BOX) + 1;
                $out[] = __($u === 1 ? 'farm.position.ace' : 'farm.position.ace_more', ['u' => $u, 'n' => $k % self::BOX + 1]);
            }

            return $out;
        }
        for ($i = 1; $i <= $count; $i++) {
            $out[] = __('farm.position.generic', ['n' => $i]);
        }

        return $out;
    }

    /** The name of the position at `$index` (0-based, in slot order) of a machine with `$count` positions. */
    public static function name(FarmPrinter $printer, int $index, ?int $count = null): string
    {
        return self::names($printer, $count)[$index] ?? __('farm.position.generic', ['n' => $index + 1]);
    }
}
