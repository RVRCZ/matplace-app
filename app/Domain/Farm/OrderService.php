<?php

namespace App\Domain\Farm;

use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ModelEditor;
use App\Domain\Tools\ParametricGenerator;
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
use App\Support\Currency;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Everything the customer can do with a farm order before it is paid: create, change presets, see colours and price. */
final class OrderService
{
    public function __construct(private readonly FarmSettings $settings, private readonly PriceCalculator $prices, private readonly Shipping $shipping) {}

    /**
     * @throws FarmRefusal with a code the UI translates: not_ready, too_big, daily_limit, no_printer
     */
    public function create(User $user, ModelFile $file, string $quality = 'standard', string $strength = 'standard', ?string $unit = null, int $copies = 1, float $scale = 1.0, ?int $colorId = null, string $supports = 'auto', ?int $secondColorId = null, ?DesignerModel $card = null, ?CatalogModel $inspiration = null, ?array $changeColorIds = null, ?array $partColorIds = null, ?string $materialCode = null): FarmOrder
    {
        $scale = max(0.25, min((float) config('pricing.max_scale', 4), $scale));
        if (! FarmGate::open($user)) {
            throw new FarmRefusal('closed');   // an admin orders on a closed farm; a customer waits
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
        [$printer, $material] = $this->printerAndMaterialFor($dims, $quality, $materialCode) ?? [$fallback, $fallback ? $this->loadedMaterials($fallback)->first() : null];
        // the customer already chose a colour: the order goes to the machine that holds that spool and takes the model
        $chosen = $colorId ? $this->offeredColors($quality)->first(fn ($r) => $r['color']->id === $colorId && ($dims === null || $r['printer']->fits($dims))) : null;
        if ($chosen) {
            [$printer, $material] = [$chosen['printer'], $chosen['color']->material];
        }
        if (! $printer || ! $material) {
            throw new FarmRefusal('no_printer');
        }
        // the parts in colours of their own: the machine that holds the first colour and every one of them (the customer
        // picks colours, the farm the machine); when none holds them all, the first colour's machine prints the parts it
        // cannot colour in the first colour (spoolsForParts)
        if ($chosen && $partColorIds && ($first = $this->machineForColors($chosen['slot'], array_values($partColorIds), null, $quality))
            && $first->id !== $chosen['slot']->id && ($dims === null || $first->printer->fits($dims))) {
            $chosen = ['slot' => $first, 'color' => $first->color, 'printer' => $first->printer];
            [$printer, $material] = [$chosen['printer'], $chosen['color']->material];
        }
        // the colours of a plate with a text or of a picture in filament colours, chosen on the start page: spools of the
        // same machine, one per change of the design (an older caller names just the one second colour)
        $picked = $chosen ? $this->spoolsForChanges($chosen['slot'], $file->colorChanges((ModelValidator::UNITS[$unit] ?? 1.0) * $scale), $changeColorIds ?? ($secondColorId ? [$secondColorId] : [])) : [];
        $second = $picked && $picked[0]['slot_id'] !== $chosen['slot']->id ? FarmPrinterSlot::with('color')->find($picked[0]['slot_id']) : null;
        // the parts of the design printed separately, each from its own spool: one plate per part, laid out when it is prepared
        $partPlates = $partColorIds !== null ? $this->spoolsForParts($chosen['slot'] ?? null, self::designParts($file), $partColorIds) : [];

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
            'color_changes' => $partPlates ? null : ($picked ?: null),
            'by_parts' => $partPlates !== [],
            'part_plates' => $partPlates ?: null,
            // the account's currency, or the one this customer sees prices in until the first payment fixes it
            'currency' => $user->currency ?: Currency::current($user),
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
    /**
     * @param  array<string, mixed>|null  $settings  the "advanced" numbers (PrintSettings::FIELDS); null = leave as they are, [] = back to the presets
     * @param  bool|null  $byParts  print the parts of the design separately (each from its own spool) or together; null = leave as it is
     */
    public function reslice(FarmOrder $order, string $quality, string $strength, ?string $unit, ?int $copies = null, ?float $scale = null, ?string $supports = null, ?int $slotId = null, ?array $settings = null, ?bool $byParts = null, ?array $partSlotIds = null): FarmOrder
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
            'print_settings' => $settings === null ? $order->print_settings : PrintSettings::clean($settings),
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
        // the parts of the design printed separately or together (chosen on the order page since the start page has no
        // colours): a plate per part, every part on the first colour until the customer picks its spool
        $parts = $byParts !== null && $byParts !== $order->isByParts() ? self::designParts($order->modelFile) : [];
        if (count($parts) >= 2) {
            $order->fill(['by_parts' => $byParts, 'part_plates' => $byParts ? $this->spoolsForParts($order->slot()->first(), $parts, []) : null, 'color_changes' => $byParts ? null : $order->color_changes]);
        }
        // the parts' spools follow the first colour to its machine (the same colour there, else the first colour), and
        // the spools the page names come along – one machine's only (order 198, 20 Oct 2026: the first colour moved to
        // another machine, the parts kept the old machine's spools, and no machine could print that)
        if ($order->isByParts() && ($chosen || $partSlotIds !== null) && ($main = $order->slot()->first())) {
            $order->part_plates = $this->partPlatesOn($main, $order->partPlates(), $partSlotIds ?? []);
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

    /**
     * The admin's process overrides for one order (a drawing: "3 perimeters, gyroid"), then the order is sliced
     * again. Only while nothing was printed yet: an unpaid order, or a paid one still waiting for the admin's
     * approval. Once it stands in the queue, the printer may already be fetching its G-code.
     *
     * @param  array<string, mixed>|null  $overrides
     *
     * @throws FarmRefusal locked
     */
    public function overrideByAdmin(FarmOrder $order, ?array $overrides): FarmOrder
    {
        if (! in_array($order->status, [FarmOrder::STATUS_UPLOADED, FarmOrder::STATUS_SLICED, FarmOrder::STATUS_FAILED, FarmOrder::STATUS_PAID], true)) {
            throw new FarmRefusal('locked');
        }
        $clean = PrintSettings::cleanOverrides($overrides);
        $order->fill(['admin_overrides' => $clean ?: null]);
        if ($order->status !== FarmOrder::STATUS_UPLOADED) {
            $order->fill(['status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'checking', 'error' => null, 'error_detail' => null]);
            if ($order->paid_at === null) {
                $order->fill(['price' => null, 'price_total' => null]);
            }
            $order->save();
            PrepareFarmOrder::dispatch($order->id);
        } else {
            $order->save();
        }

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
        // a design that changes colour at least once (a plate with a text, a picture in colours one on another), or one
        // printed by parts (every part may take another spool of the machine)
        if (! $order->wantedChanges() && ! $order->isByParts()) {
            return collect();
        }

        return $this->secondSpools($main);
    }

    /**
     * The spools a second colour may come from when the first colour sits in several machines: the other spools of
     * every machine that holds the first colour and could print the order (online, the plate fits, takes the quality),
     * each colour once, the main's own machine first. The machine is settled when the second spool is chosen
     * (machineFor): the order goes where both colours are. Every spool knows the first colour's slot on its machine
     * (`main_slot_id`).
     *
     * @return Collection<int, FarmPrinterSlot>
     */
    public function secondSpoolsAcross(FarmPrinterSlot $main, ?FarmOrder $order = null, string $quality = 'standard'): Collection
    {
        $out = collect();
        foreach ($this->firstsAcross($main, $order, $quality) as $first) {
            foreach ($this->secondSpools($first) as $spool) {
                if ($out->contains(fn (FarmPrinterSlot $s) => $s->farm_color_id === $spool->farm_color_id)) {
                    continue;
                }
                $spool->main_slot_id = $first->id;
                $out->push($spool);
            }
        }

        return $out->sortBy(fn (FarmPrinterSlot $s) => self::colorOrder($s->color))->values();
    }

    /**
     * The first colour's slot on every machine that holds it and could print the order (or any order of this quality,
     * before one exists): the main's own machine first, then the ones ready to start.
     *
     * @return Collection<int, FarmPrinterSlot>
     */
    public function firstsAcross(FarmPrinterSlot $main, ?FarmOrder $order = null, string $quality = 'standard'): Collection
    {
        $main->loadMissing(['color.material', 'printer']);
        $dims = $order && is_array($order->check['dims'] ?? null) ? Dimensions::fromArray($order->check['dims']) : null;
        $quality = $this->knownQuality($order ? (string) $order->quality : $quality);

        return FarmPrinterSlot::with(['color.material', 'printer'])
            ->where('farm_color_id', $main->farm_color_id)->where('enabled', true)
            ->whereHas('printer', fn ($q) => $q->where('enabled', true))
            ->get()
            ->filter(fn (FarmPrinterSlot $s) => $s->kindFitsPrinter() && ($s->id === $main->id || ($s->printer->isOnline() && $s->printer->takesQuality($quality) && (! $dims || $s->printer->fits($dims)))))
            ->sortBy(fn (FarmPrinterSlot $s) => [$s->id === $main->id ? 0 : 1, $s->printer->readyForAutoStart() ? 0 : 1, $s->id])
            ->values();
    }

    /**
     * The first colour's slot on the machine that holds every colour named with it (the parts of a design, each in a
     * colour of its own): the customer picks colours, the farm the machine – the main's own when it has them all, else
     * the first other one that has (firstsAcross); null when no single machine holds the set.
     *
     * @param  list<int|null>  $colorIds
     */
    public function machineForColors(FarmPrinterSlot $main, array $colorIds, ?FarmOrder $order = null, string $quality = 'standard'): ?FarmPrinterSlot
    {
        $wanted = array_values(array_unique(array_filter(array_map('intval', $colorIds), fn ($id) => $id > 0 && $id !== (int) $main->farm_color_id)));
        foreach ($this->firstsAcross($main, $order, $quality) as $first) {
            $held = $this->secondSpools($first)->map(fn (FarmPrinterSlot $s) => (int) $s->farm_color_id)->all();
            if (! array_diff($wanted, $held)) {
                return $first;
            }
        }

        return null;
    }

    /**
     * Which machine an order goes to once its spools are chosen: the first colour's slot as chosen, unless a second or
     * part spool sits in another machine that holds the first colour too – then that machine's slot of the first
     * colour (same colour, same kind; sliced again at payment when it differs from the machine the price was made on).
     *
     * @param  list<int>  $otherSlotIds  the chosen second, change and part spools
     * @return array{slot: FarmPrinterSlot, color: FarmColor, printer: FarmPrinter, enough: bool}|null null = the first colour is gone
     *
     * @throws FarmRefusal when the other spools sit in two different machines
     */
    public function machineFor(FarmOrder $order, FarmPrinterSlot $slot, array $otherSlotIds = []): ?array
    {
        $offers = $this->availableSlots($order);
        $ids = array_values(array_unique(array_filter(array_map('intval', $otherSlotIds), fn ($id) => $id > 0 && $id !== (int) $slot->id)));
        $printers = $ids ? FarmPrinterSlot::whereIn('id', $ids)->pluck('farm_printer_id')->map(fn ($id) => (int) $id)->unique() : collect();
        if ($printers->count() > 1) {
            throw new FarmRefusal('one_machine');   // spools of two machines: no single machine prints that
        }
        $printerId = (int) ($printers->first() ?? $slot->farm_printer_id);
        $offer = $offers->first(fn ($r) => $r['color']->id === (int) $slot->farm_color_id && $r['printer']->id === $printerId)
            ?? $offers->first(fn ($r) => $r['slot']->id === $slot->id);
        if ($offer && $printers->isNotEmpty() && (int) $offer['printer']->id !== $printerId) {
            throw new FarmRefusal('one_machine');   // the other spools' machine does not hold the first colour: no machine prints that
        }

        return $offer;
    }

    /**
     * The spool for every change of a design, from the colours the customer named (by index, bottom to top): the main
     * spool itself (the print goes back to it) or another spool of its machine. A change nobody chose stays in the
     * colour printing at that moment. Spools of another machine are ignored: the order goes where the first colour is.
     *
     * @param  list<array{z: float, hex: string, code: ?string}>  $wanted  the design's changes
     * @param  list<int|null>  $colorIds  a colour per change
     * @return list<array{z: float, slot_id: int, color_id: int}>
     *
     * @throws FarmRefusal when the print would need more spools than one machine holds at once
     */
    public function spoolsForChanges(FarmPrinterSlot $main, array $wanted, array $colorIds): array
    {
        if (! $wanted || ! $colorIds) {
            return [];
        }
        $others = $this->secondSpools($main);
        $out = [];
        $used = [(int) $main->farm_color_id];
        foreach (array_values($wanted) as $i => $change) {
            $id = (int) ($colorIds[$i] ?? 0);
            if (! $id) {
                continue;
            }
            $slot = $id === (int) $main->farm_color_id ? $main : $others->first(fn (FarmPrinterSlot $s) => (int) $s->farm_color_id === $id);
            if (! $slot) {
                continue;
            }
            $used[] = $id;
            $out[] = ['z' => round((float) $change['z'], 3), 'slot_id' => (int) $slot->id, 'color_id' => $id];
        }
        if (count(array_unique($used)) > FarmOrder::MAX_COLORS) {
            throw new FarmRefusal('too_many_colors', ['n' => FarmOrder::MAX_COLORS]);
        }

        return $out;
    }

    /**
     * The separately printed parts of a design (a box and its lid, the plates of a layered picture, the parts of a
     * painted 3MF); [] for a plain upload or a one-piece design.
     *
     * @return list<string>
     */
    public static function designParts(ModelFile $file): array
    {
        if ($file->origin !== 'tool') {
            return [];
        }
        $kind = (string) $file->origin_ref;
        if (isset(ParametricGenerator::FIELDS[$kind])) {
            // only parts that are objects of their own; the colour layers of a picture or a sign print as one piece – unless
            // the design says its colours must print together on one plate (a medal with its motif flush: body + colours)
            $parts = in_array($kind, self::ASSEMBLED, true) || $file->onePlateRequired() ? ParametricGenerator::partsOf($kind, (array) $file->tool_params) : [];
        } else {
            $parts = $kind === 'colors' ? [] : ModelEditor::partsOf($file);
        }

        return array_values(array_diff($parts, ['all']));
    }

    /** Our generators whose parts are separate objects (a box and its lid…), not the colour layers of one piece. */
    public const ASSEMBLED = ['box', 'vase', 'stamp', 'logo', 'qr', 'lightbox', 'cutter', 'modular',
        // a silhouette pushed into its base (and the post of the hair-tie holder pushed through it): each its own colour
        'notes', 'hair_tie', 'candle_stand'];

    /** The name of a part as the tool's page shows it ("Víko", "Deska 2", "Díl 3"); the raw name when none is known. */
    public static function partLabel(?ModelFile $file, string $part): string
    {
        $kind = (string) $file?->origin_ref;
        $n = preg_match('/_([0-9]{1,2})$/', $part, $m) ? $m[1] : null;
        $stem = $n !== null ? substr($part, 0, -strlen($n) - 1) : $part;
        if (str_starts_with($part, 'bin_')) {
            return __('param.part.bin', ['s' => str_replace('x', ' × ', substr($part, 4))]);
        }
        // the tools of a picture in colours name their parts on their own page (plate, colour n, rim): the same words here,
        // so no part reads "color_1", nor "only the box" (the generic word for a body) outside the box
        $shape = (ParametricGenerator::FAMILY[$kind] ?? null) === 'shape' ? ["param.shape.part.$part.$kind", "param.shape.part.$stem.$kind", "param.shape.part.$stem"] : [];
        foreach ($kind === ArtGenerator::KIND ? ["edit.art.part.$stem"] : (isset(ParametricGenerator::FIELDS[$kind]) ? ["param.part.$part.$kind", ...$shape, "param.part.$part", "param.part.$stem"] : ["edit.$kind.part.$stem", "edit.part.$stem"]) as $key) {
            if (Lang::has($key)) {
                return __($key, ['n' => $n]);
            }
        }

        return $part;
    }

    /**
     * The STL of one part of a design: built again from the stored parameters (our generators) or the part file an
     * editing tool kept. Returns the path and whether the caller has to delete it; null when the part does not exist.
     *
     * @return array{path: string, temp: bool}|null
     */
    public function partStl(ModelFile $file, string $part): ?array
    {
        if (! in_array($part, self::designParts($file), true)) {
            return null;
        }
        if (isset(ParametricGenerator::FIELDS[(string) $file->origin_ref])) {
            $built = app(ParametricGenerator::class)->build((string) $file->origin_ref, (array) $file->tool_params, $part);
            if (($built['meta']['part'] ?? null) !== $part) {
                @unlink($built['path']);

                return null;
            }

            return ['path' => $built['path'], 'temp' => true];
        }
        $path = dirname(Storage::disk(ModelFile::DISK)->path($file->storage_path)).'/parts/'.$part.'.stl';

        return is_file($path) ? ['path' => $path, 'temp' => false] : null;
    }

    /**
     * One plate per part, each with the spool the customer named for it (the main spool itself, or another spool of
     * its machine); a part nobody named prints from the main spool. [] when the design has fewer than two parts.
     *
     * @param  list<string>  $parts
     * @param  array<string, int|null>  $colorIds  part → colour
     * @return list<array{part: string, slot_id: int, color_id: int}>
     */
    public function spoolsForParts(?FarmPrinterSlot $main, array $parts, array $colorIds): array
    {
        if (count($parts) < 2) {
            return [];
        }
        if (! $main) {
            // no colour chosen yet: the spools are picked on the order page
            return array_map(fn ($part) => ['part' => $part, 'slot_id' => null, 'color_id' => null], $parts);
        }
        $main->loadMissing('color');
        $others = $this->secondSpools($main);
        $out = [];
        foreach ($parts as $part) {
            $id = (int) ($colorIds[$part] ?? 0);
            $slot = $id && $id !== (int) $main->farm_color_id ? $others->first(fn (FarmPrinterSlot $s) => (int) $s->farm_color_id === $id) : null;
            $slot ??= $main;
            $out[] = ['part' => $part, 'slot_id' => (int) $slot->id, 'color_id' => (int) $slot->farm_color_id];
        }
        if (count(array_unique(array_merge([(int) $main->farm_color_id], array_column($out, 'color_id')))) > FarmOrder::MAX_COLORS) {
            throw new FarmRefusal('too_many_colors', ['n' => FarmOrder::MAX_COLORS]);
        }

        return $out;
    }

    /**
     * The spools of the parts on the machine of `$main` (the first colour as chosen). A part the page names a spool for
     * (`$wantedSlotIds`, part → slot) gets it when this machine holds it; another machine's spool is refused
     * (`one_machine`: the page offers one machine's spools, and no machine prints a mix). A part nobody named keeps the
     * spool it had when this machine holds it, else takes this machine's spool of the same colour, else the first colour
     * – or, with `$carry` false (payment: the page names every part), the first colour straight away.
     *
     * @param  list<array<string, mixed>>  $plates  the order's part plates (FarmOrder::partPlates)
     * @param  array<string, int|null>  $wantedSlotIds
     * @return list<array<string, mixed>> the plates with the `slot_id` and `color_id` of this machine
     *
     * @throws FarmRefusal one_machine | color_gone | too_many_colors
     */
    public function partPlatesOn(FarmPrinterSlot $main, array $plates, array $wantedSlotIds = [], bool $carry = true): array
    {
        $main->loadMissing('color');
        $others = $this->secondSpools($main);
        $out = [];
        foreach ($plates as $plate) {
            $wanted = (int) ($wantedSlotIds[(string) $plate['part']] ?? 0);
            $had = (int) ($plate['slot_id'] ?? 0);
            if ($wanted && $wanted !== (int) $main->id) {
                $spool = $others->firstWhere('id', $wanted);
                if (! $spool) {
                    $foreign = FarmPrinterSlot::find($wanted);
                    throw new FarmRefusal($foreign && (int) $foreign->farm_printer_id !== (int) $main->farm_printer_id ? 'one_machine' : 'color_gone');
                }
            } elseif ($wanted || ! $carry || ! $had || $had === (int) $main->id) {
                $spool = $main;
            } else {
                $spool = $others->firstWhere('id', $had) ?? $others->first(fn (FarmPrinterSlot $s) => (int) $s->farm_color_id === (int) ($plate['color_id'] ?? 0)) ?? $main;
            }
            $out[] = ['slot_id' => (int) $spool->id, 'color_id' => (int) $spool->farm_color_id] + $plate;
        }
        if (count(array_unique(array_merge([(int) $main->farm_color_id], array_column($out, 'color_id')))) > FarmOrder::MAX_COLORS) {
            throw new FarmRefusal('too_many_colors', ['n' => FarmOrder::MAX_COLORS]);
        }

        return $out;
    }

    /**
     * The other spools of the same machine a second colour can come from: same plastic family, enough left, and a kind
     * the machine may feed (a kind that breaks in the changer only from a manual printer, FarmPrinterSlot::kindFitsPrinter).
     */
    public function secondSpools(FarmPrinterSlot $main): Collection
    {
        $main->loadMissing('color.material');

        return FarmPrinterSlot::with(['color.material', 'printer'])
            ->where('farm_printer_id', $main->farm_printer_id)->where('id', '!=', $main->id)
            ->where('enabled', true)->whereNotNull('farm_color_id')
            ->whereHas('color', fn ($q) => $q->where('enabled', true))
            ->get()
            ->filter(fn (FarmPrinterSlot $s) => $s->color->id !== $main->color?->id && self::family($s->color->material) === self::family($main->color?->material) && $s->availableGrams() > 30 && $s->kindFitsPrinter())
            ->values();
    }

    /** A spool with less than this left is not offered at all (a print that needs more sees its colour greyed). */
    public const MIN_SPOOL_G = 50;

    /** The groups of the colour window in the order a list of colours shows them: colours, then white, grey, black. */
    private const HUE_ORDER = ['red', 'orange_yellow', 'brown_beige', 'green', 'blue_violet', 'special', 'white_grey', 'black'];

    /**
     * Where a colour stands in a list: by hue (Palette::hue), within a hue from light to dark, white, grey and black
     * at the end; the name decides between equals.
     *
     * @return array{0: int, 1: float, 2: string}
     */
    public static function colorOrder(FarmColor $color): array
    {
        $hex = preg_match('/^#?[0-9a-f]{6}$/i', (string) $color->hex) ? '#'.ltrim((string) $color->hex, '#') : '#808080';
        $hue = Palette::hue($hex, (string) $color->material?->finish, (string) $color->name);

        return [(int) array_search($hue, self::HUE_ORDER, true), -round(Palette::lab($hex)[0], 3), Str::lower((string) $color->displayName())];
    }

    /**
     * Colours on offer before an order exists: every enabled colour loaded in an enabled slot of an enabled, online
     * printer that takes this quality, with at least MIN_SPOOL_G left; one entry per colour (a machine ready to start
     * first, then the smaller plate), in the order of the colour window (colorOrder). A colour of a kind that breaks
     * in the changer counts only from a printer in manual mode (FarmPrinterSlot::kindFitsPrinter) – the same rule the
     * admin's slot forms keep, kept here too for slots loaded before the kind got its flag.
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
            ->filter(fn (FarmPrinterSlot $s) => $s->printer->isOnline() && $s->printer->takesQuality($quality) && $s->availableGrams() >= self::MIN_SPOOL_G && $s->kindFitsPrinter())
            ->sortBy(fn (FarmPrinterSlot $s) => [$s->printer->readyForAutoStart() ? 0 : 1, $s->printer->bedVolume(), $s->id])
            ->unique('farm_color_id')
            ->sortBy(fn (FarmPrinterSlot $s) => self::colorOrder($s->color))
            ->map(fn (FarmPrinterSlot $s) => ['slot' => $s, 'color' => $s->color, 'printer' => $s->printer])
            ->values();
    }

    /**
     * Colours the customer can have right now: every enabled kind loaded in an enabled slot of an online printer that
     * prints this order's G-code (same machine profile), with enough filament left after what is already promised.
     * The kind's temperatures go into the G-code when the print is sent (GcodeSlot), so PLA, PLA+ and PETG mix freely.
     * One entry per colour – the customer picks a colour, the farm the machine (machineFor) – with the codes of every
     * spool of that colour that could print it ("U33", the entry's own first), in the order of the colour window.
     *
     * @return Collection<int, array{slot: FarmPrinterSlot, color: FarmColor, printer: FarmPrinter, enough: bool, codes: list<string>}>
     */
    public function availableColors(FarmOrder $order, bool $changerOnly = false): Collection
    {
        // one entry per colour: prefer a slot with enough filament, the machine the order is sliced for, then the one
        // that can start soonest; a design that must print on one plate sees the machines that change spools alone
        $slots = $this->availableSlots($order)
            ->filter(fn ($r) => ! $changerOnly || $r['printer']->isAgentDriven())
            ->sortBy(fn ($r) => [$r['enough'] ? 0 : 1, $r['printer']->id === $order->farm_printer_id ? 0 : 1, $r['printer']->readyForAutoStart() ? 0 : 1, $r['slot']->id])
            ->values();
        $codes = $slots->groupBy(fn ($r) => $r['color']->id)->map(fn ($rows) => $rows->map(fn ($r) => $r['slot']->code())->unique()->values()->all());

        return $slots->unique(fn ($r) => $r['color']->id)
            ->sortBy(fn ($r) => self::colorOrder($r['color']))
            ->map(fn ($r) => $r + ['codes' => $codes[$r['color']->id]])
            ->values();
    }

    /**
     * Every slot that could print this order, machine by machine (a colour loaded in two machines is here twice); a
     * spool with less than MIN_SPOOL_G left is not among them.
     *
     * @return Collection<int, array{slot: FarmPrinterSlot, color: FarmColor, printer: FarmPrinter, enough: bool}>
     */
    public function availableSlots(FarmOrder $order): Collection
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
            ->filter(fn (FarmPrinterSlot $s) => $s->printer->isOnline() && $s->printer->fits($dims) && $s->printer->takesQuality((string) $order->quality) && $s->availableGrams() >= self::MIN_SPOOL_G && $s->kindFitsPrinter())
            ->map(fn (FarmPrinterSlot $s) => ['slot' => $s, 'color' => $s->color, 'printer' => $s->printer, 'enough' => $s->availableGrams() >= $need])
            ->values();
    }

    /**
     * What this order costs on a given printer: the print (computed in crowns, where the rates are defined), the
     * designer's reward, the parcel; everything the customer pays in the currency asked for.
     *
     * @param  FarmMaterial|null  $material  the kind of the chosen colour; before a colour is chosen, the order's kind
     * @param  string|null  $country  where it is delivered (sets the parcel price and, once config says so, the VAT)
     * @param  string|null  $currency  CZK | EUR; the order's own when not given
     * @return array<string,mixed> price breakdown
     */
    public function priceFor(FarmOrder $order, FarmPrinter $printer, ?string $delivery = null, ?FarmMaterial $material = null, ?string $country = null, ?string $currency = null): array
    {
        $delivery ??= $order->delivery;
        $material ??= $order->material;
        $currency = strtoupper((string) ($currency ?: $order->currency ?: Money::CZK));
        $country = $delivery === Shipping::PICKUP ? 'CZ' : strtoupper((string) ($country ?: ($order->shipping_address['country'] ?? 'CZ')));

        $price = $this->withRoyalty($order, $this->prices->price((int) $order->est_minutes, (float) $order->est_grams, [
            'hourly_rate' => $printer->hourly_rate ?? (float) $this->settings->get('hourly_rate'),
            'price_per_gram' => (float) $material->price_per_gram,
            'fixed_fee' => (float) $this->settings->get('fixed_fee'),
            'min_price' => (float) $this->settings->get('min_price'),
            'vat_percent' => $this->shipping->vatPercent($country),
            'rounding' => (float) $this->settings->get('rounding'),
            'time_factor' => $printer->time_factor,
            'weight_factor' => $printer->weight_factor,
            'currency' => Money::CZK,
        ]));
        $parcel = $this->shipping->price($delivery, $country, $this->shipping->parcelGrams($order), $currency);

        return self::inCurrency($price, $currency, (float) $parcel?->amount) + ['delivery' => $delivery, 'country' => $delivery === Shipping::PICKUP ? null : $country,
            // a plate of several filaments: how often the machine changes the spool (the purge is in the grams already; the time in the minutes)
            'changes' => (int) ($order->slice_result['changes'] ?? 0)];
    }

    /**
     * The breakdown in the currency the customer pays in. What is charged (print, reward, total) is rounded the way
     * prices are (euros up to 0.10); the lines that only explain the print (time, material, fee, net) are plain
     * conversions, and the VAT line is what is left, so the lines always add up to the print price.
     * `royalty_unit` stays in crowns: it is the frozen per-piece reward the designer's account is credited from.
     *
     * @param  array<string,mixed>  $price  computed in crowns
     * @return array<string,mixed>
     */
    private static function inCurrency(array $price, string $currency, float $shipping): array
    {
        $charged = fn (float $czk) => Money::czk($czk)->to($currency)->amount;
        $plain = fn (float $czk) => Money::czk($czk)->exactly($currency)->amount;
        $print = $charged((float) $price['print_total']);
        $royalty = $charged((float) $price['royalty']);
        $net = $plain((float) $price['net']);

        return [
            'currency' => $currency,
            'time' => $plain((float) $price['time']), 'material' => $plain((float) $price['material']), 'fixed' => $plain((float) $price['fixed']),
            'net' => $net, 'vat' => round($print - $net, 2), 'print_total' => $print, 'rounding_added' => $plain((float) $price['rounding_added']),
            'royalty' => $royalty, 'shipping' => round($shipping, 2), 'total' => round($print + $royalty + $shipping, 2),
        ] + ($currency === Money::CZK ? [] : ['czk' => ['print_total' => $price['print_total'], 'royalty' => $price['royalty'], 'rate' => Money::rate()]]) + $price;
    }

    /**
     * An order that is not paid yet follows the currency its customer sees prices in (they may switch Kč / € or
     * top up in the other one meanwhile); a paid order keeps the currency it was paid in for good.
     */
    public function alignCurrency(FarmOrder $order, string $currency): FarmOrder
    {
        if ($order->paid_at !== null || $order->status !== FarmOrder::STATUS_SLICED || $order->currency === $currency || ! $order->printer || ! $order->material) {
            return $order;
        }
        $price = $this->priceFor($order, $order->printer, Shipping::PICKUP, null, null, $currency);
        $order->forceFill(['currency' => $currency, 'price' => $price, 'price_total' => $price['total']])->save();

        return $order;
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
     * size, before orientation, so a loose fit) and this quality, online ones first; the kind the calculation was
     * made for (PLA, PETG…) before the others when it is loaded; null when the model fits no machine at all.
     *
     * @return array{0: FarmPrinter, 1: FarmMaterial}|null
     */
    private function printerAndMaterialFor(?Dimensions $dims, string $quality, ?string $materialCode = null): ?array
    {
        $printers = $this->printers()
            ->filter(fn (FarmPrinter $p) => $dims === null || $p->fits($dims))
            ->filter(fn (FarmPrinter $p) => $p->takesQuality($quality));
        foreach ($this->loadedMaterials()->sortBy(fn (FarmMaterial $m) => $materialCode !== null && $m->code === $materialCode ? 0 : 1) as $material) {
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
