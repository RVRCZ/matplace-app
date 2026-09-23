<?php

namespace App\Domain\Farm;

use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;

/**
 * What a print is sliced and heated with, put together from the layers that know something about it. The more
 * specific layer wins, key by key:
 *
 *   1. the kind (FarmMaterial: profile file, temperatures, filament_overrides)
 *   2. the kind on this printer (FarmPrinterMaterial without a colour)
 *   3. the spool anywhere (FarmColor::print_overrides)
 *   4. the spool on this printer (FarmPrinterMaterial with the colour)
 *
 * A test order carries its candidate values itself, so a test prints exactly what is being tried.
 */
final class PrintProfile
{
    /** @param  array<int, string>  $layers  names of the layers that contributed, for the slice_params record */
    private function __construct(
        public readonly string $filamentProfile,
        public readonly array $process,
        public readonly array $filament,
        public readonly array $temps,
        public readonly array $layers,
    ) {}

    public static function for(FarmPrinter $printer, FarmMaterial $material, ?FarmColor $color = null): self
    {
        $process = [];
        $filament = $material->sliceOverrides();
        $temps = GcodeSlot::tempsOf($material);
        $layers = ['kind'];

        $rows = FarmPrinterMaterial::where('farm_printer_id', $printer->id)->where('farm_material_id', $material->id)
            ->where(fn ($q) => $q->whereNull('farm_color_id')->when($color, fn ($q) => $q->orWhere('farm_color_id', $color->id)))
            ->get()->keyBy(fn ($r) => $r->farm_color_id ? 'spool' : 'kind');

        $apply = function (array $slice, array $t, string $name) use (&$process, &$filament, &$temps, &$layers) {
            if ($slice['process'] === [] && $slice['filament'] === [] && $t === []) {
                return;
            }
            $process = $slice['process'] + $process;
            $filament = $slice['filament'] + $filament;
            if (! empty($t['nozzle'])) {
                // a layer that names the printing temperature but not the first layer's keeps the known gap (+5 by default)
                $gap = ! empty($temps['nozzle']) && ! empty($temps['nozzle_first']) ? $temps['nozzle_first'] - $temps['nozzle'] : 5;
                $temps = ['nozzle' => $t['nozzle'], 'nozzle_first' => ! empty($t['nozzle_first']) ? $t['nozzle_first'] : $t['nozzle'] + $gap, 'bed' => ! empty($t['bed']) ? $t['bed'] : ($temps['bed'] ?? 0)];
            } elseif (! empty($t['bed']) && ! empty($temps['nozzle'])) {
                $temps['bed'] = $t['bed'];
            }
            $layers[] = $name;
        };

        if ($row = $rows->get('kind')) {
            $apply($row->sliceOverrides(), $row->temps(), 'printer_kind');
        }
        if ($color) {
            // only what the spool itself says (FarmColor::temps() would fall back to the kind and undo the machine's row)
            $s = $color->sliceOverrides();
            $po = (array) $color->print_overrides;
            $own = array_filter(['nozzle' => (int) ($po['nozzle_temp'] ?? 0), 'nozzle_first' => (int) ($po['nozzle_temp_first'] ?? 0), 'bed' => (int) ($po['bed_temp'] ?? 0)]);
            $apply(['process' => (array) ($s['process'] ?? []), 'filament' => (array) ($s['filament'] ?? [])], $own, 'spool');
        }
        if ($row = $rows->get('spool')) {
            $apply($row->sliceOverrides(), $row->temps(), 'printer_spool');
        }
        // a filament override that names a temperature would fight the G-code rewrite: the temperatures win
        foreach (['nozzle_temperature', 'nozzle_temperature_initial_layer'] as $k) {
            if (! empty($temps['nozzle'])) {
                $filament[$k] = [(string) ($k === 'nozzle_temperature' ? $temps['nozzle'] : ($temps['nozzle_first'] ?: $temps['nozzle'] + 5))];
            }
        }

        return new self((string) $material->filament_profile, $process, $filament, $temps, $layers);
    }

    /** The profile an order prints with: a test carries its own candidate, a customer's print is assembled. */
    public static function forOrder(FarmOrder $order): self
    {
        if ($order->isTest() && is_array($order->test_params['candidate'] ?? null)) {
            $c = $order->test_params['candidate'];
            $material = $order->material;
            $temps = array_filter(['nozzle' => (int) ($c['nozzle_temp'] ?? 0), 'nozzle_first' => (int) ($c['nozzle_temp_first'] ?? 0), 'bed' => (int) ($c['bed_temp'] ?? 0)]);
            $filament = (array) ($c['filament'] ?? []) + $material->sliceOverrides();
            if (! empty($temps['nozzle'])) {
                $filament['nozzle_temperature'] = [(string) $temps['nozzle']];
                $filament['nozzle_temperature_initial_layer'] = [(string) ($temps['nozzle_first'] ?: $temps['nozzle'] + 5)];
            }

            return new self((string) $material->filament_profile, (array) ($c['process'] ?? []), $filament, $temps ?: GcodeSlot::tempsOf($material), ['test_candidate']);
        }

        return self::for($order->printer, $order->material, $order->color);
    }

    /** Temperatures written into the G-code copy that goes to the printer. */
    public static function tempsFor(FarmOrder $order): array
    {
        if (! $order->printer || ! $order->material) {
            return GcodeSlot::tempsOf($order->color ?? $order->material);
        }

        return self::forOrder($order)->temps;
    }

    /** Fingerprint of everything that changes the slice (not the temperatures: those go into the G-code later). */
    public function sliceFingerprint(): string
    {
        $p = $this->process;
        $f = $this->filament;
        ksort($p);
        ksort($f);

        return sha1(json_encode([$this->filamentProfile, $p, $f]));
    }
}
