<?php

namespace App\Models;

use App\Domain\Farm\FarmSettings;
use App\Engines\DTO\Dimensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One machine of the farm. The slicer profile, the calibration factors and the live state all live here. */
class FarmPrinter extends Model
{
    public const MODE_MANUAL = 'manual';   // operator downloads the G-code and changes the states by hand

    public const MODE_AGENT = 'agent';     // farm-agent uploads, starts and reports

    public const STATE_IDLE = 'idle';

    public const STATE_PRINTING = 'printing';

    public const STATE_PAUSED = 'paused';

    public const STATE_ERROR = 'error';

    public const STATE_UNKNOWN = 'unknown';

    /** Build plates as OrcaSlicer names them (process setting curr_bed_type) → what the operator calls them. */
    public const BED_TYPES = [
        'Textured PEI Plate' => 'texturovaná PEI',
        'High Temp Plate' => 'hladká PEI (High Temp)',
        'Cool Plate' => 'Cool Plate (PLA)',
        'Textured Cool Plate' => 'texturovaná Cool Plate',
        'Engineering Plate' => 'Engineering Plate',
        'Supertack Plate' => 'Supertack Plate',
    ];

    protected $fillable = [
        'name', 'model', 'key', 'farm_agent_id', 'mode', 'enabled', 'bed_x', 'bed_y', 'bed_z', 'nozzle_mm',
        'machine_profile', 'process_profiles', 'machine_overrides', 'process_overrides', 'time_factor', 'weight_factor',
        'hourly_rate', 'bed_clear', 'bed_cleared_at', 'state', 'telemetry', 'last_seen_at', 'snapshot_path', 'snapshot_at',
        'offline_notified_at',
    ];

    protected $casts = [
        'enabled' => 'bool', 'bed_clear' => 'bool', 'bed_x' => 'float', 'bed_y' => 'float', 'bed_z' => 'float',
        'nozzle_mm' => 'float', 'time_factor' => 'float', 'weight_factor' => 'float', 'hourly_rate' => 'float',
        'process_profiles' => 'array', 'machine_overrides' => 'array', 'process_overrides' => 'array', 'telemetry' => 'array',
        'bed_cleared_at' => 'datetime', 'last_seen_at' => 'datetime', 'snapshot_at' => 'datetime', 'offline_notified_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(FarmAgent::class, 'farm_agent_id');
    }

    /** The plate this machine prints on right now (slicer name), null when the profile does not say. */
    public function bedType(): ?string
    {
        $t = $this->process_overrides['curr_bed_type'] ?? null;

        return is_string($t) && $t !== '' ? $t : null;
    }

    public function bedTypeLabel(): string
    {
        $t = $this->bedType();

        return $t ? (self::BED_TYPES[$t] ?? $t) : '—';
    }

    public function slots(): HasMany
    {
        return $this->hasMany(FarmPrinterSlot::class)->orderBy('slot');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FarmOrder::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(FarmPrintJob::class);
    }

    public function isAgentDriven(): bool
    {
        return $this->mode === self::MODE_AGENT;
    }

    /** A manual printer is "online" whenever it is enabled: a person stands behind it. */
    public function isOnline(): bool
    {
        if (! $this->enabled) {
            return false;
        }
        if (! $this->isAgentDriven()) {
            return true;
        }
        $limit = (int) app(FarmSettings::class)->get('offline_after_seconds');

        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds($limit));
    }

    /** What people see: the reported state, or offline when the agent went silent. */
    public function displayState(): string
    {
        if (! $this->enabled) {
            return 'disabled';
        }

        return $this->isOnline() ? ($this->isAgentDriven() ? $this->state : 'manual') : 'offline';
    }

    /** Would a model of this size go on the plate (turned any way round), keeping the bed margin? */
    public function fits(Dimensions $d): bool
    {
        $margin = 2 * (float) app(FarmSettings::class)->get('bed_margin_mm');

        return $d->fits($this->bed_x - $margin, $this->bed_y - $margin, $this->bed_z);
    }

    /** Plate volume: the bigger machine wins a model the small one cannot take. */
    public function bedVolume(): float
    {
        return (float) $this->bed_x * (float) $this->bed_y * (float) $this->bed_z;
    }

    /** May a queued job start right now without a person touching anything? */
    public function readyForAutoStart(): bool
    {
        return $this->isAgentDriven() && $this->isOnline() && $this->bed_clear && $this->state === self::STATE_IDLE
            && ! $this->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->exists();
    }

    public function activeJob(): ?FarmPrintJob
    {
        return $this->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->latest('id')->first();
    }
}
