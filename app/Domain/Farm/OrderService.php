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

        $material = FarmMaterial::where('enabled', true)->orderBy('id')->first();
        $printer = $material ? $this->printerFor($material) : null;
        if (! $material || ! $printer) {
            throw new FarmRefusal('no_printer');
        }

        // numbers that cannot be millimetres (0.12 = metres, 1.5 = inches): start from the likely unit, the customer can change it
        if ($unit === null && is_array($file->bbox)) {
            $guess = ModelValidator::guessUnit(Dimensions::fromArray($file->bbox), new Dimensions($printer->bed_x, $printer->bed_y, $printer->bed_z));
            $unit = $guess['confident'] ? $guess['unit'] : null;
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
        $order->fill([
            'status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'checking', 'error' => null, 'error_detail' => null,
            'quality' => $this->knownQuality($quality), 'strength' => $this->knownStrength($strength),
            'unit_scale' => ModelValidator::UNITS[$unit] ?? $order->unit_scale,
            'price' => null, 'price_total' => null,
        ])->save();
        $order->events()->create(['from' => $from, 'to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'user', 'actor_id' => $order->user_id, 'note' => 'reslice']);
        Cache::add($this->sliceCounterKey($order->user), 0, now()->endOfDay());
        Cache::increment($this->sliceCounterKey($order->user));
        PrepareFarmOrder::dispatch($order->id);

        return $order;
    }

    /**
     * Colours the customer can have right now: loaded in an enabled slot of an online printer that prints this
     * order's G-code (same machine profile), with enough filament left after what is already promised.
     *
     * @return Collection<int, array{slot: FarmPrinterSlot, color: FarmColor, printer: FarmPrinter, enough: bool}>
     */
    public function availableColors(FarmOrder $order): Collection
    {
        $base = $order->printer;
        if (! $base) {
            return collect();
        }
        $need = (float) ($order->est_grams ?? 0) * 1.05 + 5;   // purge line and a little reserve

        return FarmPrinterSlot::with(['color.material', 'printer'])
            ->where('enabled', true)->whereNotNull('farm_color_id')
            ->whereHas('printer', fn ($q) => $q->where('enabled', true)->where('model', $base->model)->where('machine_profile', $base->machine_profile))
            ->whereHas('color', fn ($q) => $q->where('enabled', true)->where('farm_material_id', $order->farm_material_id))
            ->get()
            ->filter(fn (FarmPrinterSlot $s) => $s->printer->isOnline())
            ->map(fn (FarmPrinterSlot $s) => ['slot' => $s, 'color' => $s->color, 'printer' => $s->printer, 'enough' => $s->availableGrams() >= $need])
            // one entry per colour: prefer a slot with enough filament, then the printer that can start soonest
            ->sortBy(fn ($r) => [$r['enough'] ? 0 : 1, $r['printer']->readyForAutoStart() ? 0 : 1, $r['slot']->id])
            ->unique(fn ($r) => $r['color']->id)
            ->values();
    }

    /** @return array<string,mixed> price breakdown for this order on a given printer */
    public function priceFor(FarmOrder $order, FarmPrinter $printer, ?string $delivery = null): array
    {
        $delivery ??= $order->delivery;

        return $this->prices->price((int) $order->est_minutes, (float) $order->est_grams, [
            'hourly_rate' => $printer->hourly_rate ?? (float) $this->settings->get('hourly_rate'),
            'price_per_gram' => (float) $order->material->price_per_gram,
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

    /** The printer an order is sliced for: one that has this material loaded, online ones first. */
    public function printerFor(FarmMaterial $material): ?FarmPrinter
    {
        return FarmPrinter::where('enabled', true)
            ->whereHas('slots', fn ($q) => $q->where('enabled', true)->whereHas('color', fn ($c) => $c->where('farm_material_id', $material->id)))
            ->get()
            ->sortBy(fn (FarmPrinter $p) => [$p->isOnline() ? 0 : 1, $p->id])
            ->first();
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
