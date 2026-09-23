<?php

namespace App\Domain\Farm;

use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;

/**
 * Gives every enabled printer × kind a row with the best starting values we know, in this order:
 *
 *   1. a tuned row of the same kind on another machine of the same model (inherited)
 *   2. database/data/farm_profile_library.json for the printer model and kind (library)
 *   3. nothing but the kind's own profile (generic)
 *
 * Temperatures the kind already states are not repeated in the row: the kind keeps saying what it needs, the row
 * says only what this machine adds. Safe to run any time; existing rows are never touched.
 */
final class ProfileLibrary
{
    private ?array $library = null;

    /** @return int rows created */
    public function sync(): int
    {
        $created = 0;
        $printers = FarmPrinter::where('enabled', true)->get();
        $materials = FarmMaterial::where('enabled', true)->get();
        $existing = FarmPrinterMaterial::whereNull('farm_color_id')->get()->keyBy(fn ($r) => $r->farm_printer_id.':'.$r->farm_material_id);
        foreach ($printers as $printer) {
            foreach ($materials as $material) {
                if ($existing->has($printer->id.':'.$material->id)) {
                    continue;
                }
                [$overrides, $source, $note] = $this->startingValues($printer, $material);
                FarmPrinterMaterial::create([
                    'farm_printer_id' => $printer->id, 'farm_material_id' => $material->id, 'overrides' => $overrides ?: null,
                    'source' => $source, 'status' => FarmPrinterMaterial::STATUS_UNTESTED, 'notes' => $note,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** @return array{0: array, 1: string, 2: string|null} */
    public function startingValues(FarmPrinter $printer, FarmMaterial $material): array
    {
        $tuned = FarmPrinterMaterial::whereNull('farm_color_id')->where('farm_material_id', $material->id)
            ->where('status', FarmPrinterMaterial::STATUS_TUNED)->where('farm_printer_id', '!=', $printer->id)
            ->whereHas('printer', fn ($q) => $q->where('model', $printer->model))->latest('tested_at')->first();
        if ($tuned) {
            return [(array) $tuned->overrides, 'inherited', 'Převzato z '.$tuned->printer->name.' (verze '.$tuned->version.').'];
        }

        $lib = $this->lookup($printer->model, $material->code, $material->finish);
        if ($lib !== null) {
            $lib = FarmPrinterMaterial::clean($lib);
            // the kind's own temperatures stay the kind's business
            foreach (['nozzle_temp', 'nozzle_temp_first', 'bed_temp'] as $k) {
                if ($material->{$k}) {
                    unset($lib[$k]);
                }
            }

            return [$lib, 'library', 'Výchozí hodnoty z knihovny pro '.$printer->model.'.'];
        }

        return [[], 'generic', null];
    }

    /** Library entry for a printer model and kind: the model's block (with what it inherits), the kind, the finish over "*". */
    public function lookup(string $printerModel, string $code, string $finish): ?array
    {
        $lib = $this->library();
        $model = strtolower($printerModel);
        $block = null;
        foreach ($lib as $key => $entry) {
            if (! str_starts_with($key, '_') && str_contains($model, (string) $key)) {
                $block = $this->resolved($lib, (string) $key);
                break;
            }
        }
        if ($block === null) {
            return null;
        }
        $kind = $block[strtoupper($code)] ?? null;
        if (! is_array($kind)) {
            return null;
        }
        $base = (array) ($kind['*'] ?? []);
        $own = $finish !== 'solid' && isset($kind[$finish]) ? (array) $kind[$finish] : [];
        $merged = $own ? self::merge($base, $own) : $base;

        return $merged ?: null;
    }

    /** A model block with `_inherits` merged in, kind by kind and finish by finish. */
    private function resolved(array $lib, string $key, int $depth = 0): array
    {
        $block = (array) ($lib[$key] ?? []);
        $parent = $block['_inherits'] ?? null;
        unset($block['_inherits']);
        if (is_string($parent) && isset($lib[$parent]) && $depth < 5) {
            $base = $this->resolved($lib, $parent, $depth + 1);
            foreach ($block as $code => $finishes) {
                if (str_starts_with((string) $code, '_') || ! is_array($finishes)) {
                    continue;
                }
                foreach ($finishes as $finish => $values) {
                    $base[$code][$finish] = self::merge((array) ($base[$code][$finish] ?? $base[$code]['*'] ?? []), (array) $values);
                }
            }

            return $base;
        }

        return $block;
    }

    /** Override groups (process, filament) merge key by key; everything else is replaced. */
    public static function merge(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            $base[$k] = in_array($k, ['process', 'filament'], true) && is_array($v) ? $v + (array) ($base[$k] ?? []) : $v;
        }

        return $base;
    }

    private function library(): array
    {
        if ($this->library === null) {
            $path = database_path('data/farm_profile_library.json');
            $this->library = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        }

        return $this->library;
    }
}
