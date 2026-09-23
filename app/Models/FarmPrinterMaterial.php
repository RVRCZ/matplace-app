<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How a kind of filament (or one spool of it) prints on one machine of the farm. The overrides have the same shape
 * as FarmColor::print_overrides: temperatures go into the finished G-code, `process` / `filament` change the slice.
 * A row is created for every enabled printer × kind with the best known starting values (ProfileLibrary) and then
 * tuned with test prints; every change keeps the previous version in `history`.
 */
class FarmPrinterMaterial extends Model
{
    public const STATUS_UNTESTED = 'untested';

    public const STATUS_TESTING = 'testing';

    public const STATUS_TUNED = 'tuned';

    public const SOURCES = ['generic', 'library', 'inherited', 'test', 'manual'];

    protected $attributes = ['status' => self::STATUS_UNTESTED, 'source' => 'generic', 'version' => 1];

    protected $fillable = ['farm_printer_id', 'farm_material_id', 'farm_color_id', 'overrides', 'status', 'source', 'version', 'score', 'tested_at', 'notes', 'history'];

    protected $casts = ['overrides' => 'array', 'history' => 'array', 'version' => 'int', 'score' => 'int', 'tested_at' => 'datetime'];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FarmMaterial::class, 'farm_material_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(FarmColor::class, 'farm_color_id');
    }

    public function testOrders(): HasMany
    {
        return $this->hasMany(FarmOrder::class)->where('kind', FarmOrder::KIND_TEST)->orderByDesc('id');
    }

    /** "PLA+ matt" or "PLA+ matt · bílá" for a spool row. */
    public function label(): string
    {
        $kind = $this->material?->label() ?? '?';

        return $this->farm_color_id ? $kind.' · '.($this->color?->name ?? '?') : $kind;
    }

    /** Temperatures this row sets (only the keys it really has). */
    public function temps(): array
    {
        $o = (array) $this->overrides;

        return array_filter(['nozzle' => (int) ($o['nozzle_temp'] ?? 0), 'nozzle_first' => (int) ($o['nozzle_temp_first'] ?? 0), 'bed' => (int) ($o['bed_temp'] ?? 0)]);
    }

    /** @return array{process: array, filament: array} slicer settings of this row */
    public function sliceOverrides(): array
    {
        $o = (array) $this->overrides;

        return ['process' => (array) ($o['process'] ?? []), 'filament' => (array) ($o['filament'] ?? [])];
    }

    public function hasSliceOverrides(): bool
    {
        $s = $this->sliceOverrides();

        return $s['process'] !== [] || $s['filament'] !== [];
    }

    /** New values: the current ones go to the history, the version counts up. Same values = nothing happens. */
    public function revise(?array $overrides, string $source, ?string $note = null, ?string $status = null): void
    {
        $overrides = self::clean($overrides);
        if ($overrides == (array) $this->overrides && $status === null) {
            return;
        }
        if ($overrides != (array) $this->overrides) {
            $history = (array) $this->history;
            $history[] = ['version' => $this->version, 'overrides' => $this->overrides, 'source' => $this->source, 'note' => $note, 'at' => now()->toIso8601String()];
            $this->history = array_slice($history, -30);
            $this->version = $this->version + 1;
            $this->overrides = $overrides ?: null;
            $this->source = $source;
        }
        if ($status !== null) {
            $this->status = $status;
        }
        $this->save();
    }

    /** Only the keys a row may carry, numbers as ints, empty groups dropped. */
    public static function clean(?array $o): array
    {
        $out = [];
        foreach (['nozzle_temp', 'nozzle_temp_first', 'bed_temp'] as $k) {
            if (isset($o[$k]) && (int) $o[$k] > 0) {
                $out[$k] = (int) $o[$k];
            }
        }
        foreach (['process', 'filament'] as $k) {
            if (! empty($o[$k]) && is_array($o[$k])) {
                $out[$k] = array_map(fn ($v) => is_array($v) ? $v : (string) $v, $o[$k]);
            }
        }

        return $out;
    }
}
