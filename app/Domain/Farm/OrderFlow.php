<?php

namespace App\Domain\Farm;

use App\Engines\Shipping\ShippingCarrier;
use App\Engines\Shipping\ShippingFailed;
use App\Jobs\BuildFarmTimelapse;
use App\Jobs\PrepareFarmOrder;
use App\Mail\FarmAdminAlert;
use App\Mail\FarmOrderStatus;
use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinterSlot;
use App\Models\FarmPrintJob;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The only place an order changes its status. Every move checks the allowed transitions, writes the history,
 * moves the credit (hold → capture / give back) and tells the customer.
 */
final class OrderFlow
{
    /** The customer hears about these; the rest is internal. */
    private const NOTIFY = [
        FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING, FarmOrder::STATUS_DONE,
        FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_FAILED, FarmOrder::STATUS_CANCELLED,
    ];

    public function __construct(private readonly Wallet $wallet, private readonly FarmSettings $settings, private readonly OrderService $orders, private readonly Shipping $shipping, private readonly ShippingCarrier $carrier) {}

    /**
     * Pay from credit and send to the queue. Delivery is chosen here, before the payment, because it changes the price.
     *
     * @param  array<string,mixed>|null  $address  where it goes: name, phone, country and either street/city/zip or point{id, name, country, carrier_id}
     * @param  float|null  $expectedTotal  what the customer saw; a different price (other printer, changed rates) is refused, never charged silently
     * @param  string|null  $currency  the currency the customer saw the price in; the account's own always wins
     *
     * @throws FarmRefusal|InsufficientCredit
     */
    public function pay(FarmOrder $order, FarmPrinterSlot $slot, string $delivery, ?array $address, bool $termsAccepted, ?string $ip, ?float $expectedTotal = null, ?string $note = null, ?int $secondSlotId = null, ?string $currency = null, ?array $changeSlotIds = null): FarmOrder
    {
        if ($order->status !== FarmOrder::STATUS_SLICED) {
            throw new FarmRefusal('not_ready');
        }
        if (! $termsAccepted) {
            throw new FarmRefusal('terms');
        }
        if (! in_array($delivery, $this->shipping->modes(), true)) {
            throw new FarmRefusal('delivery');
        }
        $currency = (string) ($order->user?->currency ?: $currency ?: $order->currency);
        // a country we send to, a pickup point that lies in it, an address a courier can find
        $destination = $this->shipping->destination($delivery, (array) $address, $order, $currency);
        if ($delivery === Shipping::POINT && $this->carrier->refusePoint(['id' => $destination['pickup_point_id'], 'carrier_id' => $destination['carrier_id']], $destination['country'], $this->shipping->parcelGrams($order) / 1000) !== null) {
            throw new FarmRefusal('delivery_point');
        }
        $offer = $this->orders->availableColors($order)->first(fn ($r) => $r['slot']->id === $slot->id);
        if (! $offer) {
            throw new FarmRefusal('color_gone');
        }
        if (! $offer['enough']) {
            throw new FarmRefusal('filament_low');
        }

        // the spools of the changes, one per change of the design (bottom to top): the chosen spool itself or another one
        // of its machine; an older page names just the one second colour
        $ids = $changeSlotIds ?? ($secondSlotId ? [$secondSlotId] : []);
        $picked = [];
        $others = collect();
        if ($ids) {
            $wanted = array_values($order->wantedChanges());
            $others = $this->orders->secondColors($order, $offer['slot']);
            if (! $wanted) {
                throw new FarmRefusal('color_gone');
            }
            foreach ($wanted as $i => $change) {
                $id = (int) ($ids[$i] ?? 0);
                if (! $id) {
                    continue;
                }
                $slot = $id === (int) $offer['slot']->id ? $offer['slot'] : ($others->firstWhere('id', $id) ?? throw new FarmRefusal('color_gone'));
                $picked[] = ['z' => round((float) $change['z'], 3), 'slot_id' => (int) $slot->id, 'color_id' => (int) $slot->farm_color_id];
            }
            if (count(array_unique(array_merge([(int) $offer['slot']->farm_color_id], array_column($picked, 'color_id')))) > FarmOrder::MAX_COLORS) {
                throw new FarmRefusal('too_many_colors', ['n' => FarmOrder::MAX_COLORS]);
            }
        }
        $second = $picked && $picked[0]['slot_id'] !== $offer['slot']->id ? $others->firstWhere('id', $picked[0]['slot_id']) : null;

        $price = $this->orders->priceFor($order, $offer['printer'], $delivery, $offer['color']->material, $destination['country'] ?? null, $currency);
        if ($expectedTotal !== null && abs($expectedTotal - $price['total']) > 0.009) {
            throw new FarmRefusal('price_changed', ['total' => Money::of($price['total'], $currency)->format()]);
        }

        $slicedFor = $order->farm_printer_id;
        DB::transaction(function () use ($order, $offer, $delivery, $destination, $ip, $price, $note, $second, $picked, $currency) {
            $order->fill([
                'farm_printer_id' => $offer['printer']->id, 'farm_printer_slot_id' => $offer['slot']->id, 'farm_color_id' => $offer['color']->id,
                'farm_material_id' => $offer['color']->farm_material_id,
                'second_slot_id' => $second?->id, 'second_color_id' => $second?->farm_color_id, 'color_changes' => $picked ?: null,
                'delivery' => $delivery, 'shipping_address' => $destination, 'shipping_price' => $price['shipping'], 'currency' => $currency,
                // an order made from an inspiration page keeps the line that names the model and its author
                'note' => trim(implode("\n", array_filter([$order->catalog_model_id ? $order->catalogModel?->attribution() : null, $note]))) ?: null,
                'price' => $price, 'price_total' => $price['total'], 'royalty_czk' => $price['royalty_unit'] ?? null,
                'terms_version' => (string) $this->settings->get('terms_version'), 'terms_accepted_at' => now(), 'terms_ip' => $ip,
            ])->save();
            $this->wallet->hold($order);
            $order->forceFill(['paid_at' => now(), 'number' => $this->nextNumber()])->save();
            $this->move($order, FarmOrder::STATUS_PAID, 'user', $order->user_id);
        });

        $wanted = PrintProfile::for($offer['printer'], $offer['color']->material, $offer['color'])->sliceFingerprint();
        if ($wanted !== ($order->slice_params['profile_fingerprint'] ?? null) || $offer['printer']->id !== $slicedFor) {
            // this spool (or this kind on this machine) prints with other slicer settings than the order was sliced with,
            // or sits in another machine: slice again, the paid order waits meanwhile (the price stays as quoted)
            $order->forceFill(['status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'slicing'])->save();
            $order->events()->create(['from' => FarmOrder::STATUS_PAID, 'to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'system', 'note' => $offer['printer']->id !== $slicedFor ? 'reslice for '.$offer['printer']->key : 'reslice for the tuned profile']);
            PrepareFarmOrder::dispatch($order->id);
        } elseif ($this->settings->get('require_approval')) {
            $this->alertAdmin(__('farm.admin.mail.approve', ['number' => $order->number]), $order);
        } else {
            $this->move($order, FarmOrder::STATUS_QUEUED, 'system');
        }

        return $order->refresh();
    }

    public function approve(FarmOrder $order, int $adminId): void
    {
        $order->forceFill(['approved_at' => now(), 'approved_by' => $adminId])->save();
        if ($order->status === FarmOrder::STATUS_PAID) {
            $this->move($order, FarmOrder::STATUS_QUEUED, 'admin', $adminId);
        }
    }

    /** @throws \DomainException when the move is not allowed from the current status */
    public function move(FarmOrder $order, string $to, string $actor, ?int $actorId = null, ?string $note = null): FarmOrder
    {
        if (! $order->canMoveTo($to) || ($order->status === FarmOrder::STATUS_SLICED && $to === FarmOrder::STATUS_QUEUED && ! $order->isFree())) {
            throw new \DomainException("Farm order {$order->id}: {$order->status} → {$to} is not allowed.");
        }
        $from = $order->status;
        $order->status = $to;
        match ($to) {
            FarmOrder::STATUS_QUEUED => $order->queued_at = now(),
            FarmOrder::STATUS_PRINTING => $order->started_at = now(),
            FarmOrder::STATUS_DONE => $order->finished_at = now(),
            FarmOrder::STATUS_HANDED_OVER => $order->handed_at = now(),
            default => null,
        };
        if ($to !== FarmOrder::STATUS_FAILED) {
            $order->error = $order->error_detail = null;
        }
        $order->save();
        $order->events()->create(['from' => $from, 'to' => $to, 'actor' => $actor, 'actor_id' => $actorId, 'note' => $note ? mb_substr($note, 0, 500) : null]);

        match ($to) {
            FarmOrder::STATUS_QUEUED => $this->onQueued($order),
            FarmOrder::STATUS_DONE => $this->onDone($order),
            FarmOrder::STATUS_CANCELLED => $this->onStopped($order, cancelPrint: true),
            FarmOrder::STATUS_FAILED => $this->onStopped($order, cancelPrint: false),
            default => null,
        };
        if (in_array($to, self::NOTIFY, true) && $order->paid_at !== null) {
            $this->notifyCustomer($order);
        }

        return $order;
    }

    /** Failure with a reason code the customer can read (`farm.error.<code>`). Works from any status that allows it. */
    public function fail(FarmOrder $order, string $code, string $actor, ?int $actorId = null, ?string $detail = null): void
    {
        $order->forceFill(['error' => $code, 'error_detail' => $detail, 'stage' => null]);
        if ($order->canMoveTo(FarmOrder::STATUS_FAILED)) {
            $this->move($order, FarmOrder::STATUS_FAILED, $actor, $actorId, $code);   // keeps the error: only other targets clear it
            if ($order->paid_at !== null) {
                $this->alertAdmin(__('farm.admin.mail.failed', ['number' => $order->number, 'reason' => $code]), $order);
            }
        } else {
            $order->save();
        }
    }

    /**
     * A plate came off the printer finished. The last plate finishes the order; any other sends it back to the queue
     * for the next plate (the printer's plate is not clear until an operator says so, as after any print).
     */
    public function plateFinished(FarmOrder $order, string $actor, ?int $actorId = null): FarmOrder
    {
        $done = min((int) $order->plates, (int) $order->plates_done + 1);
        $order->forceFill(['plates_done' => $done])->save();
        if ($done < (int) $order->plates) {
            return $this->move($order, FarmOrder::STATUS_QUEUED, $actor, $actorId, "plate {$done}/{$order->plates} printed");
        }

        return $this->move($order, FarmOrder::STATUS_DONE, $actor, $actorId, $order->plates > 1 ? "plate {$done}/{$order->plates} printed" : null);
    }

    /** Measured values after a print (from the agent or typed in by the operator): the calibration data. */
    public function recordActuals(FarmOrder $order, ?int $minutes, ?float $grams, string $source): void
    {
        $order->forceFill(array_filter(['actual_minutes' => $minutes, 'actual_grams' => $grams], fn ($v) => $v !== null) + ['actual_source' => $source])->save();
    }

    private function onQueued(FarmOrder $order): void
    {
        if ($order->printer) {
            app(Dispatcher::class)->kick($order->printer);
            if (! $order->printer->isAgentDriven()) {
                $this->alertAdmin(__('farm.admin.mail.manual', ['number' => $order->number, 'printer' => $order->printer->name]), $order);
            }
        }
    }

    private function onDone(FarmOrder $order): void
    {
        $this->wallet->capture($order);
        // the same moment the customer's money becomes final, the designer of the printed model gets the reward
        $this->wallet->creditRoyalty($order);
        BuildFarmTimelapse::dispatch($order->id);
        if ($slot = $order->slot) {
            // what really left the spool when we know it, else the estimate
            $used = (float) ($order->actual_grams ?? $order->est_grams ?? 0);
            $slot->update(['remaining_g' => max(0, $slot->remaining_g - $used)]);
        }
    }

    private function onStopped(FarmOrder $order, bool $cancelPrint): void
    {
        $jobs = $order->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->get();
        // a cancelled print that already ran is paid for as far as it got: the handling fee in full, time and material
        // by the printed share (a failed print is the farm's problem and costs nothing)
        $progress = $cancelPrint ? (float) $jobs->whereIn('status', [FarmPrintJob::STATUS_PRINTING, FarmPrintJob::STATUS_PAUSED])->max('progress') : 0.0;
        $keep = $progress > 0 ? $this->shareOfPrice((array) $order->price, $progress / 100) : 0.0;
        $this->wallet->giveBack($order, note: $keep > 0 ? __('farm.credit.partial', ['percent' => round($progress)]) : null, keep: $keep);
        if ($keep > 0) {
            $order->forceFill(['price' => ['charged_on_cancel' => $keep, 'printed_percent' => round($progress, 1)] + (array) $order->price])->save();
            $order->events()->create(['from' => $order->status, 'to' => $order->status, 'actor' => 'system', 'note' => "kept {$keep} for {$progress} % printed"]);
        }
        foreach ($jobs as $job) {
            if ($cancelPrint && in_array($job->status, [FarmPrintJob::STATUS_PRINTING, FarmPrintJob::STATUS_PAUSED, FarmPrintJob::STATUS_SENT, FarmPrintJob::STATUS_UNKNOWN], true)) {
                FarmCommand::create(['farm_printer_id' => $job->farm_printer_id, 'farm_print_job_id' => $job->id, 'type' => FarmCommand::TYPE_CANCEL]);
            }
            FarmCommand::where('farm_print_job_id', $job->id)->where('status', 'pending')->where('type', FarmCommand::TYPE_START)->update(['status' => 'failed', 'result' => 'order stopped']);
            $job->update(['status' => $cancelPrint ? FarmPrintJob::STATUS_CANCELLED : FarmPrintJob::STATUS_FAILED, 'finished_at' => now()]);
        }
    }

    /** Fixed fee plus the printed share of time and material, with VAT, whole crowns (ten cents in euros); never above the print price. */
    public function shareOfPrice(array $price, float $share): float
    {
        if (! isset($price['fixed'], $price['time'], $price['material'], $price['print_total'])) {
            return 0.0;
        }
        $net = (float) $price['fixed'] + $share * ((float) $price['time'] + (float) $price['material']);
        $gross = $net * (1 + (float) ($price['inputs']['vat_percent'] ?? 0) / 100);
        $step = ($price['currency'] ?? Money::CZK) === Money::EUR ? 0.1 : 1.0;

        return min((float) $price['print_total'], PriceCalculator::roundUp($gross, $step));
    }

    /**
     * The finished print leaves as a parcel: announce it to the carrier, keep what it gave us, hand the order over
     * and tell the customer where to follow it. When the carrier refuses, nothing changes and the operator reads why.
     *
     * @throws ShippingFailed|\DomainException
     */
    public function ship(FarmOrder $order, int $adminId): FarmOrder
    {
        if ($order->status !== FarmOrder::STATUS_DONE || ! isset(Shipping::KINDS[$order->delivery])) {
            throw new \DomainException("Farm order {$order->id} is not a finished print waiting for a parcel.");
        }
        // asked twice (a double click, a retry after a timeout): the parcel that exists is the parcel
        if (! $order->packeta_packet_id) {
            $packet = $this->carrier->createPacket($order);
            $order->forceFill([
                'packeta_packet_id' => $packet->id, 'packeta_barcode' => $packet->barcode, 'tracking' => $packet->barcode,
                'tracking_url' => $this->carrier->trackingUrl($packet->barcode, $order->user?->locale), 'shipped_at' => now(),
            ])->save();
        }

        return $this->move($order, FarmOrder::STATUS_HANDED_OVER, 'admin', $adminId, 'parcel '.$order->packeta_barcode);
    }

    private function notifyCustomer(FarmOrder $order): void
    {
        $user = $order->user;
        if (! $user || ! $user->email || $user->notify_email === false) {
            return;
        }
        try {
            Mail::to($user->email)->locale($user->locale ?: config('app.locale'))->queue(new FarmOrderStatus($order, $order->status));
        } catch (\Throwable $e) {
            Log::warning('Farm status mail failed', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    public function alertAdmin(string $subject, ?FarmOrder $order = null, array $lines = []): void
    {
        $to = (string) $this->settings->get('admin_email');
        if ($to === '') {
            return;
        }
        try {
            Mail::to($to)->queue(new FarmAdminAlert($subject, $lines, $order ? route('admin.farm.orders.show', $order) : route('admin.farm.printers')));
        } catch (\Throwable $e) {
            Log::warning('Farm admin mail failed', ['error' => $e->getMessage()]);
        }
    }

    /** F26-000123: year + running number. */
    private function nextNumber(): string
    {
        $prefix = 'F'.now()->format('y').'-';
        $last = FarmOrder::where('number', 'like', $prefix.'%')->lockForUpdate()->max('number');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 6, '0', STR_PAD_LEFT);
    }
}
