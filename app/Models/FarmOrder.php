<?php

namespace App\Models;

use App\Domain\Farm\OrderService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * One print ordered from the farm. The status only moves along TRANSITIONS and only through
 * App\Domain\Farm\OrderFlow, which also writes the event, moves the credit and notifies the customer.
 */
class FarmOrder extends Model
{
    use HasFactory;

    public const STATUS_AWAITING_CHOICES = 'awaiting_choices';   // a big model: waits for the customer's choices and colour before it is sliced at all (docs/AL.md §11)

    public const STATUS_UPLOADED = 'uploaded';        // model taken over, being checked / oriented / sliced

    public const STATUS_SLICED = 'sliced';            // price known, waiting for the customer

    public const STATUS_PAID = 'paid';                // credit held; waits for approval when the farm requires it

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PRINTING = 'printing';

    public const STATUS_DONE = 'done';                // printed, waiting to be taken off the plate / handed over

    public const STATUS_HANDED_OVER = 'handed_over';  // picked up or shipped

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_PRINT = 'print';            // a customer's order

    public const KIND_TEST = 'test';              // a tuning test print of the farm itself: no price, sliced → queued

    public const KIND_SHOWCASE = 'showcase';      // a print for the YouTube channel (App\Domain\YouTube\ShowcasePrints): no price, sliced → queued

    public const TRANSITIONS = [
        self::STATUS_AWAITING_CHOICES => [self::STATUS_UPLOADED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_UPLOADED => [self::STATUS_SLICED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_SLICED => [self::STATUS_UPLOADED, self::STATUS_PAID, self::STATUS_CANCELLED, self::STATUS_QUEUED],   // → queued: tests only (OrderFlow)
        self::STATUS_PAID => [self::STATUS_QUEUED, self::STATUS_CANCELLED],
        self::STATUS_QUEUED => [self::STATUS_PRINTING, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_PRINTING => [self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_QUEUED],
        self::STATUS_DONE => [self::STATUS_HANDED_OVER, self::STATUS_FAILED],
        self::STATUS_FAILED => [self::STATUS_QUEUED, self::STATUS_UPLOADED, self::STATUS_CANCELLED],
        self::STATUS_HANDED_OVER => [],
        self::STATUS_CANCELLED => [],
    ];

    /** The customer's money is held or spent in these states. */
    public const STATUSES_COMMITTED = [self::STATUS_PAID, self::STATUS_QUEUED, self::STATUS_PRINTING, self::STATUS_DONE, self::STATUS_HANDED_OVER];

    protected $attributes = ['currency' => 'CZK', 'quality' => 'standard', 'strength' => 'standard', 'supports' => 'auto', 'copies' => 1, 'plates' => 1, 'plates_done' => 0, 'plate_copies' => 1, 'unit_scale' => 1, 'scale' => 1, 'delivery' => 'pickup', 'kind' => 'print'];

    protected $fillable = [
        'quality_rating', 'quality_note', 'timelapse_path', 'kind', 'farm_printer_material_id', 'test_params',
        'token', 'number', 'user_id', 'model_file_id', 'status', 'stage', 'big_model', 'slice_run', 'slice_estimate_s', 'error', 'error_detail', 'quality', 'strength', 'supports', 'second_slot_id', 'second_color_id', 'color_changes', 'by_parts', 'part_plates', 'copies', 'plates', 'plates_done', 'plate_copies', 'rest_copies', 'unit_scale', 'scale',
        'farm_material_id', 'farm_color_id', 'farm_printer_id', 'farm_printer_slot_id', 'delivery', 'shipping_address', 'note',
        'print_settings', 'admin_overrides', 'check', 'orientation', 'print_stl_path', 'gcode_path', 'gcode_sha256', 'rest_gcode_path', 'slice_params', 'slice_result', 'est_minutes',
        'est_grams', 'est_meters', 'supports_used', 'price', 'price_total', 'currency', 'terms_version', 'terms_accepted_at',
        'terms_ip', 'paid_at', 'approved_at', 'approved_by', 'queued_at', 'started_at', 'finished_at', 'handed_at', 'tracking',
        'actual_minutes', 'actual_grams', 'actual_source', 'video_consent', 'video_consent_at', 'timelapse_short_path', 'timelapse_wide_path',
        'designer_model_id', 'royalty_czk', 'catalog_model_id',
        'shipping_price', 'packeta_packet_id', 'packeta_barcode', 'tracking_url', 'shipped_at', 'timings',
    ];

    protected $casts = [
        'unit_scale' => 'float', 'scale' => 'float', 'copies' => 'int', 'plates' => 'int', 'plates_done' => 'int', 'plate_copies' => 'int', 'rest_copies' => 'int', 'shipping_address' => 'array', 'check' => 'array', 'orientation' => 'array', 'test_params' => 'array',
        'print_settings' => 'array', 'admin_overrides' => 'array', 'slice_params' => 'array', 'slice_result' => 'array', 'price' => 'array', 'est_minutes' => 'int', 'est_grams' => 'float',
        'est_meters' => 'float', 'supports_used' => 'bool', 'price_total' => 'float', 'actual_minutes' => 'int', 'actual_grams' => 'float', 'stats_estimated' => 'bool',
        'terms_accepted_at' => 'datetime', 'paid_at' => 'datetime', 'approved_at' => 'datetime', 'queued_at' => 'datetime',
        'started_at' => 'datetime', 'finished_at' => 'datetime', 'handed_at' => 'datetime', 'attention_cleared_at' => 'datetime',
        'video_consent' => 'bool', 'video_consent_at' => 'datetime', 'royalty_czk' => 'float',
        'shipping_price' => 'float', 'shipped_at' => 'datetime', 'timings' => 'array', 'color_changes' => 'array', 'by_parts' => 'bool', 'part_plates' => 'array',
        'big_model' => 'bool', 'slice_run' => 'int', 'slice_estimate_s' => 'int',
    ];

    /** What the customer pays (or paid), in the order's own currency. */
    public function total(): ?Money
    {
        return $this->price_total === null ? null : new Money((float) $this->price_total, (string) ($this->currency ?: Money::CZK));
    }

    /** The status in words, as the customer reads it: a finished print that leaves as a parcel is not "waiting for handover". */
    public function statusText(): string
    {
        return __('farm.status.'.($this->status === self::STATUS_DONE && $this->isParcel() ? 'done_parcel' : $this->status));
    }

    /** The order leaves as a parcel (not picked up in person). */
    public function isParcel(): bool
    {
        return in_array($this->delivery, ['packeta_point', 'packeta_home'], true);
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function modelFile(): BelongsTo
    {
        return $this->belongsTo(ModelFile::class);
    }

    /** The designer's card this order prints (null for the customer's own file). */
    public function designerModel(): BelongsTo
    {
        return $this->belongsTo(DesignerModel::class);
    }

    /** The inspiration page the customer came from with their own file. */
    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class);
    }

    /**
     * The customer must not get the file of a designer's model that is not offered for download: the order page then
     * shows the card's picture instead of the 3D preview. The designer and the farm's operators see it as usual.
     */
    public function hidesModelFrom(?User $viewer): bool
    {
        $card = $this->designer_model_id ? $this->designerModel : null;
        if (! $card || $card->download_allowed) {
            return false;
        }

        return ! $viewer || (! $viewer->isAdmin() && $viewer->id !== $card->profile?->user_id);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FarmMaterial::class, 'farm_material_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(FarmColor::class, 'farm_color_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(FarmPrinterSlot::class, 'farm_printer_slot_id');
    }

    public function secondSlot(): BelongsTo
    {
        return $this->belongsTo(FarmPrinterSlot::class, 'second_slot_id');
    }

    public function secondColor(): BelongsTo
    {
        return $this->belongsTo(FarmColor::class, 'second_color_id');
    }

    /**
     * The height (mm, as printed) where a raised text starts, for models of our tools that are a plate with a raised
     * motif; null for everything else. Tool models are never turned by the farm, so the height only follows the size.
     */
    public function colorChangeMm(): ?float
    {
        $file = $this->modelFile;
        $z = $file && $file->builtForPrinting() ? ($file->tool_params['color_change_mm'] ?? null) : null;

        return $z ? round((float) $z * (float) ($this->unit_scale ?: 1) * (float) ($this->scale ?: 1), 3) : null;
    }

    /**
     * The spools of the order as people read them: the first colour, then the spool of every change of the design or
     * of every part printed separately, each with its code ("U33" = farm U, printer 3, slot 3) so the operator finds
     * machine and spool and the customer sees what was chosen. A change or part on the first colour is left out.
     *
     * @return list<array{role: string, n: ?int, part: ?string, name: string, kind: string, hex: ?string, code: ?string}>
     */
    public function spoolLines(): array
    {
        $ids = array_filter(array_merge([(int) $this->farm_printer_slot_id], array_column($this->colorSlots(), 'slot_id'), array_map('intval', array_column($this->partPlates(), 'slot_id'))));
        $slots = $ids ? FarmPrinterSlot::with(['color.material', 'printer'])->whereIn('id', $ids)->get()->keyBy('id') : collect();
        $line = function (?FarmPrinterSlot $slot, ?FarmColor $color, string $role, ?int $n = null, ?string $part = null): array {
            $color ??= $slot?->color;

            return ['role' => $role, 'n' => $n, 'part' => $part, 'name' => (string) $color?->displayName(), 'kind' => (string) $color?->material?->label(), 'hex' => $color?->hex, 'code' => $slot?->code()];
        };
        if (! $this->color && ! $slots->get((int) $this->farm_printer_slot_id)) {
            return [];
        }
        $out = [$line($slots->get((int) $this->farm_printer_slot_id), $this->color, 'main')];
        // one change (a plate with a text) is "the second colour", several are numbered from the bottom
        $one = count($this->colorSlots()) === 1;
        foreach ($this->colorSlots() as $i => $c) {
            if ((int) $c['slot_id'] !== (int) $this->farm_printer_slot_id && ($slot = $slots->get($c['slot_id']))) {
                $out[] = $line($slot, null, $one ? 'second' : 'change', $i + 2);
            }
        }
        foreach ($this->partPlates() as $p) {
            $slot = $slots->get((int) ($p['slot_id'] ?? 0));
            if ($slot && (int) $slot->id !== (int) $this->farm_printer_slot_id) {
                $out[] = $line($slot, null, 'part', null, OrderService::partLabel($this->modelFile, (string) $p['part']));
            }
        }

        return $out;
    }

    /** The spools in one line, for a mail or the operator: "PLA+ modrá (U33), barva 2: bílá (U31)". */
    public function spoolText(): string
    {
        return implode(', ', array_map(fn (array $l) => match ($l['role']) {
            'second' => __('farm.order.second_line', ['name' => $l['name']]),
            'change' => __('farm.order.change_line', ['n' => $l['n'], 'name' => $l['name']]),
            'part' => __('farm.order.part_line', ['part' => $l['part'], 'name' => $l['name']]),
            default => trim($l['kind'].' '.$l['name']),
        }.($l['code'] ? ' ('.$l['code'].')' : ''), $this->spoolLines()));
    }

    /**
     * Does the print take a spool of a kind that breaks in the filament changer (FarmMaterial::manual_only)? Such a
     * spool is fed straight from the holder by a person: the operator's badge on the order and the dashboard says why
     * the print goes by hand. Any of the order's colours counts: the first, a second, a change, a part.
     */
    public function manualOnly(): bool
    {
        $ids = array_filter(array_map('intval', array_merge([$this->farm_color_id, $this->second_color_id], array_column($this->colorSlots(), 'color_id'), array_column($this->partPlates(), 'color_id'))));

        return $ids && FarmColor::whereIn('id', $ids)->whereHas('material', fn ($q) => $q->where('manual_only', true))->exists();
    }

    /** The most spools one print may use, the first one counted: one ACE holds four. */
    public const MAX_COLORS = 4;

    /**
     * What the design asks for, as printed: every height at which the colour changes, bottom to top, with the hex and
     * the spool code it was designed in. A plate with a text has one, a picture in filament colours one per colour.
     *
     * @return list<array{z: float, hex: string, code: ?string}>
     */
    public function wantedChanges(): array
    {
        $file = $this->modelFile;

        return $file ? $file->colorChanges((float) ($this->unit_scale ?: 1) * (float) ($this->scale ?: 1)) : [];
    }

    /**
     * The spools the customer chose for the changes, bottom to top, as stored with the order. An older order carries
     * its one second colour in second_slot_id.
     *
     * @return list<array{z: float, slot_id: int, color_id: int}>
     */
    public function colorSlots(): array
    {
        $out = [];
        foreach ((array) $this->color_changes as $c) {
            if (is_array($c) && isset($c['z'], $c['slot_id'], $c['color_id'])) {
                $out[] = ['z' => round((float) $c['z'], 3), 'slot_id' => (int) $c['slot_id'], 'color_id' => (int) $c['color_id']];
            }
        }
        if (! $out && ($z = $this->colorChangeMm()) && $this->second_slot_id) {
            $out[] = ['z' => $z, 'slot_id' => (int) $this->second_slot_id, 'color_id' => (int) $this->second_color_id];
        }

        return $out;
    }

    /**
     * What the G-code copy for the printer switches to and where (tool numbers of this machine), bottom to top. A spool
     * of another machine is skipped (the order moved since), a change to the spool already printing is no change.
     *
     * @return list<array{slot:int, z:float}>
     */
    public function colorChanges(): array
    {
        $rows = $this->colorSlots();
        if (! $rows) {
            return [];
        }
        $slots = FarmPrinterSlot::whereIn('id', array_column($rows, 'slot_id'))->get()->keyBy('id');
        $current = (int) ($this->slot?->slot ?? 0);
        $out = [];
        foreach ($rows as $r) {
            $s = $slots->get($r['slot_id']);
            if (! $s || (int) $s->farm_printer_id !== (int) $this->farm_printer_id) {
                continue;
            }
            if ((int) $s->slot !== $current) {
                $out[] = ['slot' => (int) $s->slot, 'z' => $r['z']];
                $current = (int) $s->slot;
            }
        }

        return $out;
    }

    /** @return array{slot:int, z:float}|null the first change, for code that knew only one; see colorChanges() */
    public function colorChange(): ?array
    {
        return $this->colorChanges()[0] ?? null;
    }

    /** The tuning row (printer × kind, or printer × spool) a test print was made for. */
    public function printerMaterial(): BelongsTo
    {
        return $this->belongsTo(FarmPrinterMaterial::class, 'farm_printer_material_id');
    }

    public function isTest(): bool
    {
        return $this->kind === self::KIND_TEST;
    }

    /** The farm's own print (a tuning test or a showcase for YouTube): nobody pays, it is queued once sliced. */
    public function isFree(): bool
    {
        return in_array($this->kind, [self::KIND_TEST, self::KIND_SHOWCASE], true);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FarmOrderEvent::class)->orderBy('id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(FarmPrintJob::class)->orderBy('id');
    }

    /** The square Short on YouTube, when the customer agreed to share it. */
    public function video(): HasOne
    {
        return $this->hasOne(FarmVideo::class)->where('kind', FarmVideo::KIND_SHORT);
    }

    /** Every video of the print on YouTube: the Short and the 16:9. */
    public function videos(): HasMany
    {
        return $this->hasMany(FarmVideo::class);
    }

    public function latestJob(): ?FarmPrintJob
    {
        // the relation orders by id ascending; without reorder() that first ORDER BY would win and the oldest job came back
        return $this->printJobs()->reorder()->latest('id')->first();
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isCommitted(): bool
    {
        return in_array($this->status, self::STATUSES_COMMITTED, true);
    }

    /** The customer may still walk away and get the held credit back. */
    /** Before it starts for free; a running print too, paid for as far as it got (OrderFlow::onStopped). */
    public function cancellableByCustomer(): bool
    {
        return in_array($this->status, [self::STATUS_AWAITING_CHOICES, self::STATUS_UPLOADED, self::STATUS_SLICED, self::STATUS_PAID, self::STATUS_QUEUED, self::STATUS_PRINTING], true);
    }

    /** The order has not been sliced yet and is not being sliced: a big model waiting for the customer's choices (docs/AL.md §11). */
    public function awaitsChoices(): bool
    {
        return $this->status === self::STATUS_AWAITING_CHOICES;
    }

    /** A slice is running right now for an unpaid big model: a change of the choices starts it over (OrderService::reslice). */
    public function sliceRestartable(): bool
    {
        return $this->status === self::STATUS_UPLOADED && $this->big_model && $this->paid_at === null && $this->kind === self::KIND_PRINT;
    }

    /** Directory on the farm disk holding this order's print STL, G-code and snapshots. */
    public function dir(): string
    {
        return 'orders/'.$this->token;
    }

    /** Separately printed parts of the design, every part its own print from its own spool (one plate per part). */
    public function isByParts(): bool
    {
        return (bool) $this->by_parts;
    }

    /**
     * The plates of an order printed by parts, in the order they print: the part, the spool chosen for it and, once
     * sliced, its G-code and figures. Before slicing only part, slot_id and color_id are there.
     *
     * @return list<array<string, mixed>>
     */
    public function partPlates(): array
    {
        if (! $this->isByParts()) {
            return [];
        }

        return array_values(array_filter((array) $this->part_plates, fn ($p) => is_array($p) && ! empty($p['part'])));
    }

    /** The spool a plate prints from: its own for an order by parts, else the order's. */
    public function plateSpool(?int $plate = null): ?FarmPrinterSlot
    {
        if ($this->isByParts() && $plate !== null) {
            $id = (int) ($this->partPlates()[$plate - 1]['slot_id'] ?? 0);
            $slot = $id ? FarmPrinterSlot::with('color')->find($id) : null;
            if ($slot && (int) $slot->farm_printer_id === (int) $this->farm_printer_id) {
                return $slot;
            }
        }

        return $this->slot;
    }

    /**
     * An order by parts whose parts print together on one plate, a filament per spool, the machine changing spools as
     * it goes (PrepareFarmOrder::prepareOnePlate, docs/AL.md §10): one G-code with `T0`…`T<n-1>`.
     */
    public function onePlate(): bool
    {
        return $this->isByParts() && ! empty($this->slice_params['one_plate']);
    }

    /**
     * Filament index (T0 = 0) → the slot it comes from, for a plate of several filaments; [] otherwise. Parts that share
     * a spool share the filament; a part without a spool yet falls back to the order's main slot.
     *
     * @return array<int, int>
     */
    public function filamentSlots(): array
    {
        if (! $this->onePlate()) {
            return [];
        }
        $ids = array_filter(array_map('intval', array_column($this->partPlates(), 'slot_id')));
        $numbers = $ids ? FarmPrinterSlot::whereIn('id', $ids)->pluck('slot', 'id') : collect();
        $out = [];
        foreach ($this->partPlates() as $p) {
            $n = max(0, (int) ($p['extruder'] ?? 1) - 1);
            $out[$n] ??= (int) ($numbers[(int) ($p['slot_id'] ?? 0)] ?? ($this->slot?->slot ?? 0));
        }
        ksort($out);

        return $out;
    }

    /** The G-code of one plate: every full plate shares the main file, a partly filled last plate has its own; by parts, every plate its own. */
    public function absoluteGcodePath(?int $plate = null): ?string
    {
        if ($this->isByParts() && $plate !== null && ($own = $this->partPlates()[$plate - 1]['gcode_path'] ?? null)) {
            return Storage::disk(config('farm.disk'))->path((string) $own);
        }
        $rel = $plate !== null && $plate >= $this->plates && $this->rest_gcode_path ? $this->rest_gcode_path : $this->gcode_path;

        return $rel ? Storage::disk(config('farm.disk'))->path($rel) : null;
    }

    /** The plate a job started now would print (1-based). */
    public function nextPlate(): int
    {
        return min($this->plates, $this->plates_done + 1);
    }

    /** Pieces on each plate, first to last: [4, 4, 1] for 9 pieces when four fit. */
    public function plateLayout(): array
    {
        if ($this->isByParts() && $this->partPlates()) {
            return array_map(fn ($p) => (int) ($p['copies'] ?? $this->copies), $this->partPlates());
        }
        if ($this->plates <= 1) {
            return [$this->copies];
        }
        $full = array_fill(0, $this->plates - 1, $this->plate_copies);
        $full[] = $this->rest_copies ?: $this->plate_copies;

        return $full;
    }

    public function absolutePrintStlPath(): ?string
    {
        return $this->print_stl_path ? Storage::disk(config('farm.disk'))->path($this->print_stl_path) : null;
    }

    /** Support structures for the 3D preview (App\Engines\Gcode\SupportLines), when the slice built any. */
    public function absoluteSupportsPath(): ?string
    {
        $path = Storage::disk(config('farm.disk'))->path($this->dir().'/supports.bin');

        return is_file($path) ? $path : null;
    }
}
