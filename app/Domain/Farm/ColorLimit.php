<?php

namespace App\Domain\Farm;

use App\Models\FarmOrder;
use App\Models\FarmPrinter;

/**
 * How many colours one print may take (TODO #66, docs/AL.md §19): as many as the machine that prints it has spool
 * positions (the Kobra X 3 on its rack + 4 in the ACE = 7, a Kobra S1 4, a Core One with the INDX 8), never more than
 * the safety ceiling of config/farm.php (`max_colors_ceiling`). FarmOrder::MAX_COLORS is only the default for a machine
 * whose positions are not in the database yet.
 */
final class ColorLimit
{
    public static function ceiling(): int
    {
        return max(1, (int) config('farm.max_colors_ceiling', 16));
    }

    /** The limit of one machine: its positions (FarmPrinterSlot rows), the default when it has none, the ceiling at most. */
    public static function of(?FarmPrinter $printer): int
    {
        if (! $printer) {
            return min(self::ceiling(), FarmOrder::MAX_COLORS);
        }
        $positions = $printer->relationLoaded('slots') ? $printer->slots->count() : $printer->slots()->count();

        return max(1, min(self::ceiling(), $positions > 0 ? $positions : FarmOrder::MAX_COLORS));
    }

    /**
     * The limit before a machine is known (a 3MF just uploaded, the colours picked for the customer): the most positions
     * of any enabled machine run by an agent (a changer), else of any enabled machine, else the default. Being online
     * does not count: an upload analysed while a machine is off has to split the same way when it is on again.
     */
    public static function farm(): int
    {
        $printers = FarmPrinter::with('slots')->where('enabled', true)->get();
        $changers = $printers->filter(fn (FarmPrinter $p) => $p->isAgentDriven());
        $pool = $changers->isNotEmpty() ? $changers : $printers;

        return $pool->isNotEmpty() ? (int) $pool->max(fn (FarmPrinter $p) => self::of($p)) : min(self::ceiling(), FarmOrder::MAX_COLORS);
    }
}
