<?php

namespace App\Domain\Farm;

use App\Models\FarmColor;
use App\Models\FarmPrinter;
use Illuminate\Validation\ValidationException;

/**
 * What may go into a spool position, checked the same way wherever a slot is written: the loading page
 * (FarmCatalogController::saveSlots) and the card of a spool read from an e-shop (FilamentFromShop::save, TODO #71).
 */
final class SlotRules
{
    /**
     * The rule of kinds that break in the filament changer (FarmMaterial::manual_only), applied to the slot rows about
     * to be saved on a machine that runs (or is about to run) through the agent: a colour of such a kind newly put into
     * a slot is refused at that slot ("put it into a printer in manual mode"); one that already sat there – the machine
     * was switched from manual to the agent, or the kind got its flag after the spool was loaded – stays in the slot
     * but switched off, and the notice names it, so the operator moves the spool. A manual machine takes anything, and
     * so does the directly fed spool (position 1) of a machine whose changer is off (`$direct`, FarmPrinter::withoutChanger,
     * docs/AL.md §24); its other positions are positions of the changer and keep the rule.
     *
     * @param  array<int|string, array<string, mixed>>  $rows  slot index → color, remaining_g, enabled
     * @param  string  $field  the request field of a slot's colour, with %d for the slot index (the validation error)
     * @param  bool  $direct  the machine has no changer on it right now
     * @return array{0: array<int|string, array<string, mixed>>, 1: list<string>} the rows (enabled cleared where needed) and the notices
     *
     * @throws ValidationException
     */
    public static function manualOnly(FarmPrinter $printer, string $mode, array $rows, string $field, bool $direct = false): array
    {
        if ($mode !== FarmPrinter::MODE_AGENT) {
            return [$rows, []];
        }
        $ids = array_filter(array_map(fn ($r) => is_numeric($r['color'] ?? null) ? (int) $r['color'] : 0, $rows));
        $colors = $ids ? FarmColor::with('material')->whereIn('id', $ids)->get()->keyBy('id') : collect();
        $stored = $printer->exists ? $printer->slots()->pluck('farm_color_id', 'slot') : collect();
        $notices = [];
        foreach ($rows as $i => $r) {
            $color = $colors->get((int) ($r['color'] ?? 0));
            if (! $color || ! $color->material?->isManualOnly() || ($direct && (int) $i === FarmPrinter::DIRECT_SLOT)) {
                continue;
            }
            if ((int) ($stored[(int) $i] ?? 0) !== (int) $color->id) {
                throw ValidationException::withMessages([sprintf($field, $i) => __('farm.admin.manual_only_refused', ['color' => $color->fullLabel(), 'printer' => $printer->name])]);
            }
            if (! empty($r['enabled'])) {
                $notices[] = __('farm.admin.manual_only_disabled', ['n' => (int) $i + 1, 'color' => $color->fullLabel(), 'printer' => $printer->name]);
            }
            $rows[$i]['enabled'] = 0;
        }

        return [$rows, $notices];
    }
}
