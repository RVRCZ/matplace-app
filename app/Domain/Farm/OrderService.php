<?php

namespace App\Domain\Farm;

use App\Engines\DTO\Dimensions;
use App\Jobs\PrepareFarmOrder;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Everything the customer can do with a farm order before it is paid: create, change presets, see colours and price. */
final class OrderService
{
    public function __construct(private readonly FarmSettings $settings, private readonly PriceCalculator $prices) {}

    /**
     * @throws FarmRefusal with a code the UI translates: not_stl, too_big, daily_limit, no_printer
     */
    public function create(User $user, ModelFile $file, string $quality = 'standard', string $strength = 'standard', ?string $unit = null): FarmOrder
    {
        if (! config('farm.open', true)) {
            throw new FarmRefusal('closed');
        }
        // our own generators always produce printable STL; a customer's upload must be .stl in this phase
        if ($file->origin === null || $file->origin === 'upload') {
            if ($file->ext !== 'stl') {
                throw new FarmRefusal('not_stl');
            }
        }
        $maxMb = (int) $this->settings->get('max_upload_mb');
        if ($file->size_bytes > $maxMb * 1024 * 1024) {
            throw new FarmRefusal('too_big', ['max' => $maxMb]);
        }
        $this->assertDailyLimit($user);

        // the kind the order is sliced and first priced for: the cheapest one really loaded in a printer whose plate
        // takes the model; the price is recomputed for the kind and machine of the colour the customer picks
        $largest = $this->printers()->sortByDesc(fn (FarmPrinter $p) => $p->bedVolume())->first();
        if (! $largest) {
            throw new FarmRefusal('no_printer');
        }
        // numbers that cannot be millimetres (0.12 = metres, 1.5 = inches): start from the likely unit, the customer can change it
        $raw = is_array($file->bbox) ? Dimensions::fromArray($file->bbox) : null;
        if ($unit === null && $raw) {
            $guess = ModelValidator::guessUnit($raw, new Dimensions($largest->bed_x, $largest->bed_y, $largest->bed_z));
            $unit = $guess['confident'] ? $guess['unit'] : null;
        }
        $dims = $raw?->scaled(ModelValidator::UNITS[$unit] ?? 1.0);
        $quality = $this->knownQuality($quality);
        // the model may fit no plate at all: start on the biggest machine that takes this quality and let the check say so
        $fallback = $this->printers()->filter(fn (FarmPrinter $p) => $p->takesQuality($quality))->sortByDesc(fn (FarmPrinter $p) => $p->bedVolume())->first();
        [$printer, $material] = $this->printerAndMaterialFor($dims, $quality) ?? [$fallback, $fallback ? $this->loadedMaterials($fallback)->first() : null];
        if (! $printer || ! $material) {
            throw new FarmRefusal('no_printer');
        }

        $order = FarmOrder::create([
            'token' => Str::random(32),
            'user_id' => $user->id,
            'model_file_id' => $file->id,
            'status' => FarmOrder::STATUS_UPLOADED,
            'stage' => 'checking',
            'quality' => $this->knownQuality($quality),
            'strength' => $this->knownStrength($strength),
            'unit_scale' => ModelValidator::UNITS[$unit] ?? 1.0,
            'farm_material_id' => $material->id,
            'farm_printer_id' => $printer->id,
            'currency' => $this->settings->get('currency'),
        ]);
        $order->events()->create(['to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'user', 'actor_id' => $user->id]);
        PrepareFarmOrder::dispatch($order->id);

        return $order;
    }

    /** Another quality, strength or unit: slice again (counts towards the daily limit, like a new order). */
    public function reslice(FarmOrder $order, string $quality, string $strength, ?string $unit): FarmOrder
    {
        if (! in_array($order->status, [FarmOrder::STATUS_SLICED, FarmOrder::STATUS_FAILED], true) || $order->paid_at !== null) {
            throw new FarmRefusal('locked');
        }
        $this->assertDailyLimit($order->user);
        $from = $order->status;
        $quality = $this->knownQuality($quality);
        $order->fill([
            'status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'checking', 'error' => null, 'error_detail' => null,
            'quality' => $quality, 'strength' => $this->knownStrength($strength),
            'unit_scale' => ModelValidator::UNITS[$unit] ?? $order->unit_scale,
            'price' => null, 'price_total' => null,
        ]);
        // a machine kept for fine work hands the order back when the customer asks for a coarser quality
        if ($order->printer && ! $order->printer->takesQuality($quality)) {
            $dims = is_array($order->check['dims'] ?? null) ? Dimensions::fromArray($order->check['dims']) : null;
            [$printer, $material] = $this->printerAndMaterialFor($dims, $quality) ?? throw new FarmRefusal('no_printer');
            $order->farm_printer_id = $printer->id;
            $order->farm_material_id = $material->id;
        }
        $order->save();
        $order->events()->create(['from' => $from, 'to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'user', 'actor_id' => $order->user_id, 'note' => 'reslice']);
        Cache::add($this->sliceCounterKey($order->user), 0, now()->endOfDay());
        Cache::increment($this->sliceCounterKey($order->user));
        PrepareFarmOrder::dispatch($order->id);

        return $order;
    }

    /**
     * Colours the customer can have right now: every enabled kind loaded in an enabled slot of an online printer that
     * prints this order's G-code (same machine profile), with enough filament left after what is already promised.
     * The kind's temperatures go into the G-code when the print is sent (GcodeSlot), so PLA, PLA+ and PETG mix freely.
     *
     * @return Collection<int, array{slot: FarmPrinterSlot, color: FarmColor, printer: FarmPrinter, enough: bool}>
     */
    public function availableColors(FarmOrder $order): Collection
    {
        $dims = is_array($order->check['dims'] ?? null) ? Dimensions::fromArray($order->check['dims']) : null;
        if (! $dims) {
            return collect();
        }
        $need = (float) ($order->est_grams ?? 0) * 1.05 + 5;   // purge line and a little reserve

        return FarmPrinterSlot::with(['color.material', 'printer'])
            ->where('enabled', true)->whereNotNull('farm_color_id')
            ->whereHas('printer', fn ($q) => $q->where('enabled', true))
            ->whereHas('color', fn ($q) => $q->where('enabled', true)->whereHas('material', fn ($m) => $m->where('enabled', true)))
            ->get()
            // any machine of the farm the model goes on and that takes this quality: a colour on another machine means
            // slicing again for it at payment
            ->filter(fn (FarmPrinterSlot $s) => $s->printer->isOnline() && $s->printer->fits($dims) && $s->printer->takesQuality((string) $order->quality))
            ->map(fn (FarmPrinterSlot $s) => ['slot' => $s, 'color' => $s->color, 'printer' => $s->printer, 'enough' => $s->availableGrams() >= $need])
            // one entry per colour: prefer a slot with enough filament, the machine the order is sliced for, then the one
            // that can start soonest
            ->sortBy(fn ($r) => [$r['enough'] ? 0 : 1, $r['printer']->id === $order->farm_printer_id ? 0 : 1, $r['printer']->readyForAutoStart() ? 0 : 1, $r['slot']->id])
            ->unique(fn ($r) => $r['color']->id)
            ->values();
    }

    /**
     * @param  FarmMaterial|null  $material  the kind of the chosen colour; before a colour is chosen, the order's kind
     * @return array<string,mixed> price breakdown for this order on a given printer
     */
    public function priceFor(FarmOrder $order, FarmPrinter $printer, ?string $delivery = null, ?FarmMaterial $material = null): array
    {
        $delivery ??= $order->delivery;
        $material ??= $order->material;

        return $this->prices->price((int) $order->est_minutes, (float) $order->est_grams, [
            'hourly_rate' => $printer->hourly_rate ?? (float) $this->settings->get('hourly_rate'),
            'price_per_gram' => (float) $material->price_per_gram,
            'fixed_fee' => (float) $this->settings->get('fixed_fee'),
            'min_price' => (float) $this->settings->get('min_price'),
            'vat_percent' => (float) $this->settings->get('vat_percent'),
            'rounding' => (float) $this->settings->get('rounding'),
            'time_factor' => $printer->time_factor,
            'weight_factor' => $printer->weight_factor,
            'shipping' => $delivery === 'shipping' ? (float) $this->settings->get('shipping_price') : 0.0,
            'currency' => (string) $this->settings->get('currency'),
        ]);
    }

    /**
     * Material kinds loaded in an enabled slot of an enabled printer (or of one printer) right now, the cheapest per gram first.
     *
     * @return Collection<int, FarmMaterial>
     */
    public function loadedMaterials(?FarmPrinter $printer = null): Collection
    {
        $counts = FarmPrinterSlot::query()->where('farm_printer_slots.enabled', true)
            ->join('farm_colors', 'farm_colors.id', '=', 'farm_printer_slots.farm_color_id')
            ->join('farm_printers', 'farm_printers.id', '=', 'farm_printer_slots.farm_printer_id')
            ->where('farm_colors.enabled', true)->where('farm_printers.enabled', true)
            ->when($printer, fn ($q) => $q->where('farm_printers.id', $printer->id))
            ->groupBy('farm_colors.farm_material_id')
            ->selectRaw('farm_colors.farm_material_id as id, count(*) as n')->pluck('n', 'id');

        return FarmMaterial::where('enabled', true)->whereIn('id', $counts->keys())->get()
            ->sortBy(fn (FarmMaterial $m) => [$m->price_per_gram, -$counts[$m->id], $m->sort])->values();
    }

    /** The printer an order is sliced for: one that has this material loaded, online ones first. */
    public function printerFor(FarmMaterial $material): ?FarmPrinter
    {
        return FarmPrinter::where('enabled', true)
            ->whereHas('slots', fn ($q) => $q->where('enabled', true)->whereHas('color', fn ($c) => $c->where('farm_material_id', $material->id)))
            ->get()
            ->sortBy(fn (FarmPrinter $p) => [$p->isOnline() ? 0 : 1, $p->id])
            ->first();
    }

    /** Enabled printers with at least one spool loaded. */
    public function printers(): Collection
    {
        return FarmPrinter::where('enabled', true)
            ->whereHas('slots', fn ($q) => $q->where('enabled', true)->whereHas('color', fn ($c) => $c->where('enabled', true)))
            ->get();
    }

    /** The biggest plate of the farm: what the start page promises. */
    public function largestBed(): ?Dimensions
    {
        $p = FarmPrinter::where('enabled', true)->get()->sortByDesc(fn (FarmPrinter $p) => $p->bedVolume())->first();

        return $p ? new Dimensions($p->bed_x, $p->bed_y, $p->bed_z) : null;
    }

    /**
     * The machine and kind an order starts on: the cheapest loaded kind on a machine that takes the model (the raw
     * size, before orientation, so a loose fit) and this quality, online ones first; null when the model fits no
     * machine at all.
     *
     * @return array{0: FarmPrinter, 1: FarmMaterial}|null
     */
    private function printerAndMaterialFor(?Dimensions $dims, string $quality): ?array
    {
        $printers = $this->printers()
            ->filter(fn (FarmPrinter $p) => $dims === null || $p->fits($dims))
            ->filter(fn (FarmPrinter $p) => $p->takesQuality($quality));
        foreach ($this->loadedMaterials() as $material) {
            $printer = $printers
                ->filter(fn (FarmPrinter $p) => $p->slots()->where('enabled', true)->whereHas('color', fn ($c) => $c->where('farm_material_id', $material->id))->exists())
                ->sortBy(fn (FarmPrinter $p) => [$p->isOnline() ? 0 : 1, $p->bedVolume(), $p->id])
                ->first();
            if ($printer) {
                return [$printer, $material];
            }
        }

        return null;
    }

    public function slicesToday(User $user): int
    {
        return (int) Cache::get($this->sliceCounterKey($user), 0)
            + FarmOrder::where('user_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count();
    }

    private function assertDailyLimit(User $user): void
    {
        $limit = (int) $this->settings->get('daily_slices_per_user');
        if ($limit > 0 && ! $user->isAdmin() && $this->slicesToday($user) >= $limit) {
            throw new FarmRefusal('daily_limit', ['limit' => $limit]);
        }
    }

    private function sliceCounterKey(User $user): string
    {
        return 'farm:reslices:'.$user->id.':'.now()->toDateString();
    }

    private function knownQuality(string $q): string
    {
        return array_key_exists($q, (array) $this->settings->get('qualities')) ? $q : 'standard';
    }

    private function knownStrength(string $s): string
    {
        return array_key_exists($s, (array) $this->settings->get('strengths')) ? $s : 'standard';
    }
}
