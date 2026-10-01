<?php

namespace App\Domain\Farm;

use App\Engines\DTO\Dimensions;
use App\Jobs\PrepareFarmOrder;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
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
     * @throws FarmRefusal with a code the UI translates: not_ready, too_big, daily_limit, no_printer
     */
    public function create(User $user, ModelFile $file, string $quality = 'standard', string $strength = 'standard', ?string $unit = null, int $copies = 1, float $scale = 1.0, ?int $colorId = null, string $supports = 'auto', ?int $secondColorId = null, ?DesignerModel $card = null, ?CatalogModel $inspiration = null): FarmOrder
    {
        $scale = max(0.25, min((float) config('pricing.max_scale', 4), $scale));
        if (! config('farm.open', true)) {
            throw new FarmRefusal('closed');
        }
        // whatever came in (STL, 3MF, STEP, OBJ…), the upload pipeline normalised it to an STL; only a file that failed
        // that or is still being processed cannot be printed
        if (! $file->isReady()) {
            throw new FarmRefusal('not_ready');
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
        $dims = $raw?->scaled((ModelValidator::UNITS[$unit] ?? 1.0) * $scale);
        $quality = $this->knownQuality($quality);
        // the model may fit no plate at all: start on the biggest machine that takes this quality and let the check say so
        $fallback = $this->printers()->filter(fn (FarmPrinter $p) => $p->takesQuality($quality))->sortByDesc(fn (FarmPrinter $p) => $p->bedVolume())->first();
        [$printer, $material] = $this->printerAndMaterialFor($dims, $quality) ?? [$fallback, $fallback ? $this->loadedMaterials($fallback)->first() : null];
        // the customer already chose a colour: the order goes to the machine that holds that spool and takes the model
        $chosen = $colorId ? $this->offeredColors($quality)->first(fn ($r) => $r['color']->id === $colorId && ($dims === null || $r['printer']->fits($dims))) : null;
        if ($chosen) {
            [$printer, $material] = [$chosen['printer'], $chosen['color']->material];
        }
        if (! $printer || ! $material) {
            throw new FarmRefusal('no_printer');
        }
        // the second colour of a plate with a code or a text, chosen on the start page: a spool of the same machine
        $second = $chosen && $secondColorId && $file->colorChangeMm() ? $this->secondSpools($chosen['slot'])->first(fn (FarmPrinterSlot $s) => $s->color->id === $secondColorId) : null;

        $order = FarmOrder::create([
            'token' => Str::random(32),
            'user_id' => $user->id,
            'model_file_id' => $file->id,
            'status' => FarmOrder::STATUS_UPLOADED,
            'stage' => 'checking',
            'quality' => $this->knownQuality($quality),
            'strength' => $this->knownStrength($strength),
            'supports' => $supports === 'off' ? 'off' : 'auto',
            'copies' => max(1, min(PlateLayout::MAX_COPIES, $copies)),
            'unit_scale' => ModelValidator::UNITS[$unit] ?? 1.0,
            'scale' => round($scale, 3),
            'farm_material_id' => $material->id,
            'farm_printer_id' => $printer->id,
            'farm_color_id' => $chosen['color']->id ?? null,
            'farm_printer_slot_id' => $chosen['slot']->id ?? null,
            'second_slot_id' => $second?->id,
            'second_color_id' => $second?->color->id,
            'currency' => $this->settings->get('currency'),
            // a designer's card (the reward is added to the price) or the inspiration page the customer came from
            'designer_model_id' => $card?->id,
            'catalog_model_id' => $inspiration?->id,
            'note' => $inspiration?->attribution(),
        ]);
        $order->events()->create(['to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'user', 'actor_id' => $user->id]);
        PrepareFarmOrder::dispatch($order->id);

        return $order;
    }

    /** Another quality, strength or unit: slice again (counts towards the daily limit, like a new order). */
    public function reslice(FarmOrder $order, string $quality, string $strength, ?string $unit, ?int $copies = null, ?float $scale = null, ?string $supports = null, ?int $slotId = null): FarmOrder
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
            'supports' => $supports === null ? $order->supports : ($supports === 'off' ? 'off' : 'auto'),
            'copies' => $copies === null ? $order->copies : max(1, min(PlateLayout::MAX_COPIES, $copies)),
            'scale' => $scale === null ? $order->scale : round(max(0.25, min((float) config('pricing.max_scale', 4), $scale)), 3),
            'unit_scale' => ModelValidator::UNITS[$unit] ?? $order->unit_scale,
            'price' => null, 'price_total' => null,
        ]);
        // another colour: the order moves to the machine that holds that spool and is sliced and priced for it
        $chosen = $slotId ? $this->availableColors($order)->first(fn ($r) => $r['slot']->id === $slotId) : null;
        if ($chosen) {
            $order->fill([
                'farm_printer_id' => $chosen['printer']->id, 'farm_material_id' => $chosen['color']->material->id,
                'farm_color_id' => $chosen['color']->id, 'farm_printer_slot_id' => $chosen['slot']->id,
            ]);
            $order->unsetRelation('printer')->unsetRelation('material')->unsetRelation('color');
        }
        // a machine kept for fine work hands the order back when the customer asks for a coarser quality
        if ($order->printer && ! $order->printer->takesQuality($quality)) {
            $dims = is_array($order->check['piece_dims'] ?? $order->check['dims'] ?? null) ? Dimensions::fromArray($order->check['piece_dims'] ?? $order->check['dims']) : null;
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

    /** PLA, PLA+ and silk PLA hold together in one print; PLA and PETG do not. */
    public static function family(?FarmMaterial $m): string
    {
        return preg_match('/^[A-Za-z]+/', (string) $m?->code, $x) ? strtoupper($x[0]) : '';
    }

    /**
     * The colours a raised text can be printed in next to the colour of the plate: other spools of the same machine
     * and of the same family of plastic, with something left on them.
     *
     * @return Collection<int, FarmPrinterSlot>
     */
    public function secondColors(FarmOrder $order, FarmPrinterSlot $main): Collection
    {
        if ($order->colorChangeMm() === null) {
            return collect();
        }

        return $this->secondSpools($main);
    }

    /** The other spools of the same machine a second colour can come from: same plastic family, enough left. */
    public function secondSpools(FarmPrinterSlot $main): Collection
    {
        $main->loadMissing('color.material');

        return FarmPrinterSlot::with('color.material')
            ->where('farm_printer_id', $main->farm_printer_id)->where('id', '!=', $main->id)
            ->where('enabled', true)->whereNotNull('farm_color_id')
            ->whereHas('color', fn ($q) => $q->where('enabled', true))
            ->get()
            ->filter(fn (FarmPrinterSlot $s) => $s->color->id !== $main->color?->id && self::family($s->color->material) === self::family($main->color?->material) && $s->availableGrams() > 30)
            ->values();
    }

    /**
     * Colours on offer before an order exists: every enabled colour loaded in an enabled slot of an enabled, online
     * printer that takes this quality; one entry per colour (a machine ready to start first, then the smaller plate).
     *
     * @return Collection<int, array{slot: FarmPrinterSlot, color: FarmColor, printer: FarmPrinter}>
     */
    public function offeredColors(string $quality = 'standard'): Collection
    {
        $quality = $this->knownQuality($quality);

        return FarmPrinterSlot::with(['color.material', 'printer'])
            ->where('enabled', true)->whereNotNull('farm_color_id')
            ->whereHas('printer', fn ($q) => $q->where('enabled', true))
            ->whereHas('color', fn ($q) => $q->where('enabled', true)->whereHas('material', fn ($m) => $m->where('enabled', true)))
            ->get()
            ->filter(fn (FarmPrinterSlot $s) => $s->printer->isOnline() && $s->printer->takesQuality($quality))
            ->sortBy(fn (FarmPrinterSlot $s) => [$s->printer->readyForAutoStart() ? 0 : 1, $s->printer->bedVolume(), $s->id])
            ->unique('farm_color_id')
            ->map(fn (FarmPrinterSlot $s) => ['slot' => $s, 'color' => $s->color, 'printer' => $s->printer])
            ->values();
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

        return $this->withRoyalty($order, $this->prices->price((int) $order->est_minutes, (float) $order->est_grams, [
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
        ]));
    }

    /**
     * A designer's model costs the print plus the designer's reward: per piece what the card asks, at most 30 % of
     * the print price of one piece (without delivery), in whole crowns. The whole reward is the designer's.
     * Nothing is added for the customer's own file, nor for a designer printing their own model.
     *
     * @param  array<string,mixed>  $price
     * @return array<string,mixed>
     */
    private function withRoyalty(FarmOrder $order, array $price): array
    {
        $unit = self::royaltyPerPiece($order->designer_model_id ? $order->designerModel : null, $order->user_id, (float) $price['print_total'], (int) $order->copies);
        $royalty = round($unit * max(1, (int) $order->copies), 2);

        return ['royalty_unit' => $unit, 'royalty' => $royalty, 'total' => round((float) $price['total'] + $royalty, 2)] + $price;
    }

    public static function royaltyPerPiece(?DesignerModel $card, ?int $customerId, float $printTotal, int $copies): float
    {
        if (! $card || $card->profile?->user_id === $customerId) {
            return 0.0;
        }
        $cap = (float) config('catalog.royalty_cap', 0.30) * $printTotal / max(1, $copies);

        return (float) floor(min((float) $card->royalty_czk, $cap));
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
