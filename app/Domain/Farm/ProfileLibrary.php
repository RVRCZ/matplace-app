<?php

namespace App\Domain\Farm;

use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use Illuminate\Support\Collection;

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
        // the same machine = the same slicer machine profile and nozzle; the free text in `model` is not trusted
        // (17 Oct 2026: eleven machines said "Printer Cr", an S1 and the Max alike) and a different nozzle is a
        // different machine as far as tuning goes
        $tuned = FarmPrinterMaterial::whereNull('farm_color_id')->where('farm_material_id', $material->id)
            ->where('status', FarmPrinterMaterial::STATUS_TUNED)->where('farm_printer_id', '!=', $printer->id)
            ->whereHas('printer', fn ($q) => $q->where('machine_profile', $printer->machine_profile)->where('nozzle_mm', $printer->nozzle_mm))
            ->latest('tested_at')->first();
        if ($tuned) {
            return [(array) $tuned->overrides, 'inherited', 'Převzato z '.$tuned->printer->name.' (verze '.$tuned->version.').'];
        }

        $lib = $this->lookup($printer->machineName(), $material->code, $material->finish, (float) $printer->nozzle_mm);
        if ($lib !== null) {
            // the library's temperatures stay: the same kind prints with a hotter bed on the Max than on the S1, so the
            // machine's row carries them and wins over the kind's own (Roman, 17 Oct 2026)
            $lib = FarmPrinterMaterial::clean($lib);

            return [$lib, 'library', 'Výchozí hodnoty z knihovny pro '.$printer->machineName().'.'];
        }

        return [[], 'generic', null];
    }

    /**
     * Library entry for a printer model and kind: the model's block (with what it inherits), the kind, the finish
     * over "*". Every value in the file is written for a 0.4 mm nozzle — flow rates and wall speeds above all — so
     * a machine with another nozzle gets nothing from here: its own slicer profiles carry what the nozzle needs,
     * and the rest is for a test print to find.
     */
    public function lookup(string $printerModel, string $code, string $finish, float $nozzle = FarmPrinter::REFERENCE_NOZZLE_MM): ?array
    {
        if (abs($nozzle - FarmPrinter::REFERENCE_NOZZLE_MM) > 0.001) {
            return null;
        }
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

    /**
     * Rows nobody has touched (status untested, source generic or library) brought up to what the library and the
     * tuned rows say today. sync() only creates rows that are missing, so a row made while its machine's model said
     * "Printer Cr" stayed empty for good (B #6 and B #7 on 17 Oct 2026). A row that was tested, tuned or edited by
     * hand is never touched: the spread button on the tuning page is for those. Dry by default.
     *
     * @return array{changed: list<string>, same: int, skipped: int}
     */
    public function refresh(bool $write = false): array
    {
        $changed = [];
        $same = $skipped = 0;
        $rows = FarmPrinterMaterial::with(['printer', 'material'])->whereNull('farm_color_id')->orderBy('farm_printer_id')->orderBy('farm_material_id')->get();
        foreach ($rows as $row) {
            if (! $row->printer || ! $row->material || ! $row->printer->enabled || ! $row->material->enabled) {
                continue;
            }
            if ($row->status !== FarmPrinterMaterial::STATUS_UNTESTED || ! in_array($row->source, ['generic', 'library'], true)) {
                $skipped++;

                continue;
            }
            [$overrides, $source, $note] = $this->startingValues($row->printer, $row->material);
            $overrides = FarmPrinterMaterial::clean($overrides);
            if ($overrides == FarmPrinterMaterial::clean((array) $row->overrides)) {
                $same++;

                continue;
            }
            $changed[] = $row->printer->name.' '.$row->label().' ('.$row->source.' v'.$row->version.' → '.$source.')';
            if ($write) {
                $row->revise($overrides, $source, 'farm:library-refresh: '.($note ?? 'stroj bez bloku v knihovně'), FarmPrinterMaterial::STATUS_UNTESTED);
            }
        }

        return compact('changed', 'same', 'skipped');
    }

    /**
     * Printers whose free-text model does not say what their slicer profile says get the profile's name
     * ("Printer Cr" → "Anycubic Kobra S1"). The text is only what people read; nothing else goes by it any more.
     *
     * @return list<string>
     */
    public function fixModels(bool $write = false): array
    {
        $fixed = [];
        foreach (FarmPrinter::orderBy('id')->get() as $printer) {
            $name = $printer->machineName();
            if ($name === '' || str_contains(strtolower((string) $printer->model), strtolower($name))) {
                continue;
            }
            $fixed[] = $printer->name.': "'.$printer->model.'" → "'.$name.'"';
            if ($write) {
                $printer->update(['model' => $name]);
            }
        }

        return $fixed;
    }

    /**
     * The kind-level temperatures of `_materials` (code/finish) onto the kinds themselves: what a machine without a
     * block of its own (the Kobra X) prints with, and what the admin sees next to the kind.
     *
     * @return list<string>
     */
    public function fixKinds(bool $write = false): array
    {
        $fixed = [];
        foreach ((array) ($this->library()['_materials'] ?? []) as $key => $temps) {
            [$code, $finish] = array_pad(explode('/', (string) $key, 2), 2, 'solid');
            $kind = FarmMaterial::where('code', $code)->where('finish', $finish)->first();
            if (! $kind) {
                continue;
            }
            $new = array_map('intval', array_intersect_key((array) $temps, array_flip(['nozzle_temp', 'nozzle_temp_first', 'bed_temp'])));
            $old = ['nozzle_temp' => (int) $kind->nozzle_temp, 'nozzle_temp_first' => (int) $kind->nozzle_temp_first, 'bed_temp' => (int) $kind->bed_temp];
            if (array_intersect_key($old, $new) == $new) {
                continue;
            }
            $fixed[] = $kind->label().': '.implode('/', $old).' → '.implode('/', $new);
            if ($write) {
                $kind->forceFill($new)->save();
            }
        }

        return $fixed;
    }

    /**
     * The same kind on the other machines of this row's type: the same slicer machine profile and the same nozzle
     * (an S1 and the Max are never siblings, whatever their model text says; the key is not trusted either, the B
     * farm's keys are kobra-s1-d01… and "the key without its number" never reached them: 17 Oct 2026).
     *
     * @return Collection<int, FarmPrinterMaterial>
     */
    public function siblings(FarmPrinterMaterial $row): Collection
    {
        $own = $row->printer;

        return FarmPrinterMaterial::with(['printer', 'material'])->whereNull('farm_color_id')
            ->where('farm_material_id', $row->farm_material_id)->where('id', '!=', $row->id)->get()
            ->filter(fn (FarmPrinterMaterial $r) => $own && $r->printer && $r->printer->sameMachineAs($own))
            ->sortBy(fn (FarmPrinterMaterial $r) => $r->printer->key)->values();
    }

    /**
     * One row's settings onto its siblings: the chosen groups (process, filament, temps) are added to what each of
     * those rows holds (the same keys are replaced, the rest stays; the temperatures go as one set, so a source
     * that names only the nozzle leaves no stale first layer behind), every changed row gets a new version and goes
     * back to "testing". A tuned row is left alone - nothing overwrites it without a test print. Without $write it
     * only says what would happen.
     *
     * @param  list<string>  $what  process | filament | temps
     * @return array{take: array, done: list<array{row: FarmPrinterMaterial, from: int, to: int}>, same: list<FarmPrinterMaterial>, skipped: list<FarmPrinterMaterial>}
     */
    public function spread(FarmPrinterMaterial $row, array $what, string $why, bool $write = true): array
    {
        $source = (array) $row->overrides;
        $take = [];
        foreach (['process', 'filament'] as $group) {
            if (in_array($group, $what, true) && ! empty($source[$group])) {
                $take[$group] = (array) $source[$group];
            }
        }
        $temps = ['nozzle_temp', 'nozzle_temp_first', 'bed_temp'];
        $withTemps = in_array('temps', $what, true) && array_intersect_key($source, array_flip($temps));
        if ($withTemps) {
            $take += array_intersect_key($source, array_flip($temps));
        }
        $done = $same = $skipped = [];
        foreach ($take ? $this->siblings($row) : [] as $target) {
            if ($target->status === FarmPrinterMaterial::STATUS_TUNED) {
                $skipped[] = $target;

                continue;
            }
            $overrides = (array) $target->overrides;
            if ($withTemps) {
                $overrides = array_diff_key($overrides, array_flip($temps));
            }
            foreach ($take as $key => $value) {
                $overrides[$key] = is_array($value) ? $value + (array) ($overrides[$key] ?? []) : $value;
            }
            $before = $target->version;
            if ($write) {
                $target->revise($overrides, 'manual', $why, FarmPrinterMaterial::STATUS_TESTING);
                $changed = $target->version !== $before;
            } else {
                $changed = FarmPrinterMaterial::clean($overrides) != FarmPrinterMaterial::clean((array) $target->overrides);
            }
            if ($changed) {
                $done[] = ['row' => $target, 'from' => $before, 'to' => $write ? $target->version : $before + 1];
            } else {
                $same[] = $target;
            }
        }

        return compact('take', 'done', 'same', 'skipped');
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
