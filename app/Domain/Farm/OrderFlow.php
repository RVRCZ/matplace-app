<?php

namespace App\Domain\Farm;

use App\Domain\YouTube\FarmVideos;
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
use App\Models\Payment;
use App\Support\Money;
use App\Support\Track;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * The only place an order changes its status. Every move checks the allowed transitions, writes the history,
 * moves the credit (hold → capture / give back), tells the customer and – for a payment, the first plate starting
 * on an agent's machine, the last plate done, a failure or the customer's cancellation – the operator (alertAdmin).
 */
final class OrderFlow
{
    /** The customer hears about these; the rest is internal. */
    private const NOTIFY = [
        FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING, FarmOrder::STATUS_DONE,
        FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_FAILED, FarmOrder::STATUS_CANCELLED,
    ];

    /** The kinds of mail the operator can switch off one by one in the farm's settings (`notify_<kind>`). */
    public const ADMIN_KINDS = ['paid', 'printing', 'done', 'problem'];

    /** A camera picture bigger than this is shrunk before it rides along with the "done" mail, or left out. */
    public const SNAPSHOT_MAX_BYTES = 300 * 1024;

    /** The order being paid right now: what its queueing would say goes into the one "paid" mail, not into a second one. */
    private ?int $paying = null;

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
    public function pay(FarmOrder $order, FarmPrinterSlot $slot, string $delivery, ?array $address, bool $termsAccepted, ?string $ip, ?float $expectedTotal = null, ?string $note = null, ?int $secondSlotId = null, ?string $currency = null, ?array $changeSlotIds = null, ?array $partSlotIds = null): FarmOrder
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
        // the first colour as chosen – on the machine of the second or part spools when they sit elsewhere (the first
        // colour is in several machines; the page offered the second colours of all of them)
        $offer = $this->orders->machineFor($order, $slot, array_merge((array) $changeSlotIds, [$secondSlotId], array_values((array) $partSlotIds)));
        if (! $offer) {
            throw new FarmRefusal('color_gone');
        }
        if (! $offer['enough']) {
            throw new FarmRefusal('filament_low');
        }
        // a design whose colours share a layer prints on one plate of a machine with a changer, one piece at a time, or not at all
        if ($order->modelFile?->onePlateRequired() && (! $offer['printer']->isAgentDriven() || (int) $order->copies !== 1)) {
            throw new FarmRefusal('one_plate_needed');
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

        // an order printed by parts: every part takes the chosen spool itself or another spool of its machine
        $partPlates = null;
        if ($order->isByParts()) {
            $spools = $this->orders->secondSpools($offer['slot']);
            $partPlates = [];
            foreach ($order->partPlates() as $plate) {
                $id = (int) ($partSlotIds[$plate['part']] ?? 0);
                $spool = ! $id || $id === (int) $offer['slot']->id ? $offer['slot'] : ($spools->firstWhere('id', $id) ?? throw new FarmRefusal('color_gone'));
                $partPlates[] = ['slot_id' => (int) $spool->id, 'color_id' => (int) $spool->farm_color_id] + $plate;
            }
            if (count(array_unique(array_merge([(int) $offer['slot']->farm_color_id], array_column($partPlates, 'color_id')))) > FarmOrder::MAX_COLORS) {
                throw new FarmRefusal('too_many_colors', ['n' => FarmOrder::MAX_COLORS]);
            }
        }

        $price = $this->orders->priceFor($order, $offer['printer'], $delivery, $offer['color']->material, $destination['country'] ?? null, $currency);
        if ($expectedTotal !== null && abs($expectedTotal - $price['total']) > 0.009) {
            throw new FarmRefusal('price_changed', ['total' => Money::of($price['total'], $currency)->format()]);
        }

        $slicedFor = $order->farm_printer_id;
        $partSpoolsBefore = array_map('intval', array_column($order->partPlates(), 'slot_id'));
        DB::transaction(function () use ($order, $offer, $delivery, $destination, $ip, $price, $note, $second, $picked, $partPlates, $currency) {
            $order->fill([
                'farm_printer_id' => $offer['printer']->id, 'farm_printer_slot_id' => $offer['slot']->id, 'farm_color_id' => $offer['color']->id,
                'farm_material_id' => $offer['color']->farm_material_id,
                'second_slot_id' => $second?->id, 'second_color_id' => $second?->farm_color_id, 'color_changes' => $picked ?: null,
                'part_plates' => $partPlates ?? $order->part_plates,
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
        // an order by parts was sliced for the spools it had: other spools for the parts mean other kinds, another
        // grouping of the parts, or a plate of several filaments where there were separate plates – sliced again
        $partsChanged = $order->isByParts() && array_map('intval', array_column($order->partPlates(), 'slot_id')) !== $partSpoolsBefore;
        $this->paying = $order->id;
        try {
            if ($wanted !== ($order->slice_params['profile_fingerprint'] ?? null) || $offer['printer']->id !== $slicedFor || $partsChanged) {
                // this spool (or this kind on this machine) prints with other slicer settings than the order was sliced with,
                // or sits in another machine: slice again, the paid order waits meanwhile (the price stays as quoted)
                $order->forceFill(['status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'slicing'])->save();
                $order->events()->create(['from' => FarmOrder::STATUS_PAID, 'to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'system', 'note' => $offer['printer']->id !== $slicedFor ? 'reslice for '.$offer['printer']->key : ($partsChanged ? 'reslice for the spools of the parts' : 'reslice for the tuned profile')]);
                PrepareFarmOrder::dispatch($order->id);
                $situation = 'reslice';
            } elseif ($this->settings->get('require_approval')) {
                $situation = 'approval';   // the "paid" mail says so; no second mail about the approval
            } else {
                $this->move($order, FarmOrder::STATUS_QUEUED, 'system');
                $situation = 'queued';
            }
        } finally {
            $this->paying = null;
        }
        // one mail to the operator per payment: who, what, which spool, where it prints and when (or what it waits for)
        $this->adminPaid($order->refresh(), $situation);

        return $order->refresh();
    }

    public function approve(FarmOrder $order, int $adminId): void
    {
        $order->forceFill(['approved_at' => now(), 'approved_by' => $adminId])->save();
        if ($order->status === FarmOrder::STATUS_PAID) {
            $this->move($order, FarmOrder::STATUS_QUEUED, 'admin', $adminId);
        }
    }

    /**
     * A card payment made for an order from its page has arrived (the webhook has just topped the credit up): the order
     * is paid with what the customer chose then. Whatever stops it (the price moved, the spool is gone, the order was
     * paid from credit meanwhile) leaves the money as credit and is written into the payment, so the page can say so.
     *
     * @return string "paid", or the reason it did not happen
     */
    public function payFromCard(Payment $payment): string
    {
        $context = (array) $payment->context;
        $order = $payment->order;
        if ($payment->purpose !== Payment::PURPOSE_ORDER || ! $order) {
            return 'no_order';
        }
        if (! empty($context['result'])) {
            return (string) $context['result'];   // the gateway said it twice
        }
        try {
            if ($order->status !== FarmOrder::STATUS_SLICED) {
                throw new FarmRefusal('not_ready');
            }
            $slot = FarmPrinterSlot::find((int) ($context['slot'] ?? 0)) ?? throw new FarmRefusal('color_gone');
            $this->pay($order, $slot, (string) ($context['delivery'] ?? ''), isset($context['address']) ? (array) $context['address'] : null, true, $context['ip'] ?? null,
                isset($context['expected_total']) ? (float) $context['expected_total'] : null, $context['note'] ?? null, isset($context['second_slot']) ? (int) $context['second_slot'] : null,
                $context['currency'] ?? null, isset($context['change_slots']) ? array_map('intval', array_values((array) $context['change_slots'])) : null,
                isset($context['part_slots']) ? array_map('intval', (array) $context['part_slots']) : null);
            if (! empty($context['video_consent'])) {
                app(FarmVideos::class)->setConsent($order->refresh(), true);
            }
            $result = 'paid';
            // the bank confirmed the money and the order is paid: the conversion of our statistics (docs/AJ.md)
            Track::paidByCard($order->refresh());
        } catch (FarmRefusal $e) {
            $result = $e->reason;
            Log::warning('Card payment arrived but the order could not be paid', ['payment' => $payment->id, 'order' => $order->id, 'reason' => $result]);
        } catch (InsufficientCredit $e) {
            $result = 'credit';   // less arrived than the order costs now (another currency, a bigger price): the credit waits
            Log::warning('Card payment arrived but does not cover the order', ['payment' => $payment->id, 'order' => $order->id, 'missing' => $e->missing()]);
        }
        $payment->update(['context' => array_merge($context, ['result' => $result])]);

        return $result;
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
        $this->adminEvent($order, $to, $actor);

        return $order;
    }

    /** Failure with a reason code the customer can read (`farm.error.<code>`). Works from any status that allows it. */
    public function fail(FarmOrder $order, string $code, string $actor, ?int $actorId = null, ?string $detail = null): void
    {
        $order->forceFill(['error' => $code, 'error_detail' => $detail, 'stage' => null]);
        if ($order->canMoveTo(FarmOrder::STATUS_FAILED)) {
            $this->move($order, FarmOrder::STATUS_FAILED, $actor, $actorId, $code);   // keeps the error: only other targets clear it; the operator's mail goes from move()
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
            // a manual machine: a person must start the print. Said in the "paid" mail when the order is being paid right
            // now (and that mail goes out); on its own after an approval, a new slice or a return to the queue
            if (! $order->printer->isAgentDriven() && ($this->paying !== $order->id || ! $this->settings->get('notify_paid'))) {
                $this->alertAdmin($this->t('manual', ['number' => $order->number, 'printer' => $order->printer->name]), $order, [...$this->orderLines($order), $this->t('line_manual_start')], 'printing');
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

    /**
     * A mail to the operator (`admin_email`), queued like the customer's; nothing goes out without an address or when
     * the admin switched that kind off (`notify_paid` … `notify_problem` in the farm's settings). Only the mail's
     * failure is logged; the farm never stops for it.
     *
     * @param  string[]  $lines
     * @param  string  $kind  paid | printing | done | problem (ADMIN_KINDS)
     * @param  string|null  $attachment  a JPEG to ride along (the camera's last picture)
     */
    public function alertAdmin(string $subject, ?FarmOrder $order = null, array $lines = [], string $kind = 'problem', ?string $attachment = null): void
    {
        $to = (string) $this->settings->get('admin_email');
        if ($to === '' || ! $this->settings->get('notify_'.$kind)) {
            return;
        }
        try {
            Mail::to($to)->queue(new FarmAdminAlert($subject, $lines, $order ? route('admin.farm.orders.show', $order) : route('admin.farm.printers'), $kind, $attachment));
        } catch (\Throwable $e) {
            Log::warning('Farm admin mail failed', ['error' => $e->getMessage()]);
        }
    }

    // ── the operator's mails about one order ─────────────────────────────────────────────────────────────────────

    /**
     * What a status change means for the operator. The status machine fires once per transition, so one event is one
     * mail: an agent reporting "printing" every few seconds moves the order only the first time (AgentService), and a
     * print of several plates says "printing" at its first plate and "done" after its last (plateFinished).
     */
    private function adminEvent(FarmOrder $order, string $to, string $actor): void
    {
        if ($to === FarmOrder::STATUS_PRINTING && (int) $order->plates_done === 0 && $order->printer?->isAgentDriven()) {
            $this->adminPrinting($order);      // a manual machine was started by the operator: nothing to tell
        } elseif ($to === FarmOrder::STATUS_DONE) {
            $this->adminDone($order);
        } elseif ($to === FarmOrder::STATUS_FAILED && $order->paid_at !== null) {
            $this->adminFailed($order);
        } elseif ($to === FarmOrder::STATUS_CANCELLED && $actor === 'user' && $order->paid_at !== null) {
            $this->adminCancelled($order);
        }
    }

    /** @param string $situation queued | approval | reslice – what happened to the order right after the payment */
    private function adminPaid(FarmOrder $order, string $situation): void
    {
        $lines = $this->orderLines($order);
        $lines[] = $this->t('line_copies', ['n' => $order->copies]).($order->plates > 1 ? ', '.$this->t('line_plates', ['n' => $order->plates]) : '');
        if ($total = $order->total()) {
            $lines[] = $this->t('line_price', ['total' => $total->format()]);
        }
        $lines[] = $this->t('line_delivery', ['how' => $this->deliveryText($order)]);
        if ($order->note) {
            $lines[] = $this->t('line_note', ['note' => mb_substr($order->note, 0, 300)]);
        }
        $lines[] = $this->queueLine($order, $situation);
        if ($situation === 'queued' && $order->printer && ! $order->printer->isAgentDriven()) {
            $lines[] = $this->t('line_manual_start');
        }
        $subject = match ($situation) {
            'approval' => $this->t('paid_approval', ['number' => $order->number]),
            'reslice' => $this->t('paid_reslice', ['number' => $order->number, 'printer' => (string) $order->printer?->name]),
            default => $this->t('paid', ['number' => $order->number]),
        };
        $this->alertAdmin($subject, $order, $lines, 'paid');
    }

    private function adminPrinting(FarmOrder $order): void
    {
        $lines = $this->orderLines($order);
        if ($order->plates > 1) {
            $lines[] = ucfirst($this->t('line_plate', ['n' => $order->nextPlate(), 'of' => $order->plates]));
        }
        if ($e = app(Dispatcher::class)->estimate($order, $this->settings)) {
            $lines[] = $this->t('line_finish', ['in' => self::duration($e['finish_in']), 'at' => $this->clock(now()->addMinutes($e['finish_in']))]);
        }
        $this->alertAdmin($this->t('printing', ['number' => $order->number, 'printer' => (string) $order->printer?->name]), $order, $lines, 'printing');
    }

    private function adminDone(FarmOrder $order): void
    {
        $minutes = (int) ($order->actual_minutes ?? $order->est_minutes ?? 0);
        $lines = $this->orderLines($order);
        $lines[] = $this->t('line_duration', ['duration' => self::duration($minutes)]).($order->actual_minutes === null ? ' '.$this->t('estimated') : '');
        if ($grams = $order->actual_grams ?? $order->est_grams) {
            $lines[] = $this->t('line_filament', ['grams' => round((float) $grams)]).($order->actual_grams === null ? ' '.$this->t('estimated') : '');
        }
        $lines[] = match (true) {
            $order->isTest() => $this->t('line_next_test'),
            $order->isParcel() => $this->t('line_next_parcel', ['where' => $this->deliveryText($order)]),
            default => $this->t('line_next_pickup'),
        };
        $snapshot = $this->snapshotFor($order);
        if ($snapshot) {
            $lines[] = $this->t('line_snapshot');
        }
        $this->alertAdmin($this->t('done', ['number' => $order->number, 'printer' => (string) $order->printer?->name, 'duration' => self::duration($minutes)]), $order, $lines, 'done', $snapshot);
    }

    private function adminFailed(FarmOrder $order): void
    {
        $code = (string) ($order->error ?: 'failed');
        $what = Lang::has('farm.admin.mail.reason.'.$code, 'cs') ? $this->t('reason.'.$code) : (Lang::has('farm.error.'.$code, 'cs') ? Lang::get('farm.error.'.$code, [], 'cs') : $code);
        $job = $order->latestJob();
        $lines = $this->orderLines($order);
        $lines[] = $this->t('line_what', ['what' => trim($what.' '.($order->error_detail ? '('.$order->error_detail.')' : ''))]);
        if ($job) {
            $where = ($order->printer?->name ?? '—').($order->plates > 1 ? ', '.$this->t('line_plate', ['n' => $job->plate, 'of' => $order->plates]) : '').', '.$this->t('line_progress', ['percent' => round((float) $job->progress)]);
            $lines[] = $this->t('line_where', ['where' => $where]);
        }
        $lines[] = $this->t('line_next_failed');
        $this->alertAdmin($this->t('failed', ['number' => $order->number, 'reason' => $code]), $order, $lines, 'problem');
    }

    private function adminCancelled(FarmOrder $order): void
    {
        $price = (array) $order->price;
        $kept = (float) ($price['charged_on_cancel'] ?? 0);
        $percent = (float) ($price['printed_percent'] ?? 0);
        $currency = (string) ($order->currency ?: Money::CZK);
        $lines = $this->orderLines($order);
        $lines[] = $this->t('line_when', ['time' => $this->clock(now(), 'j. n. Y H:i')]);
        $lines[] = $this->t('line_refund', ['amount' => Money::of(max(0.0, (float) $order->price_total - $kept), $currency)->format()]);
        if ($kept > 0) {
            $lines[] = $this->t('line_kept', ['amount' => Money::of($kept, $currency)->format(), 'percent' => round($percent)]);
        }
        $lines[] = $percent > 0 || $order->started_at ? $this->t('line_printed_yes', ['percent' => round($percent)]) : $this->t('line_printed_no');
        $this->alertAdmin($this->t('cancelled', ['number' => $order->number]), $order, $lines, 'problem');
    }

    /**
     * Who, what, which spools, which machine: the lines every mail about an order starts with.
     *
     * @return list<string>
     */
    public function orderLines(FarmOrder $order): array
    {
        $user = $order->user;
        $who = trim(($user?->name ? $user->name.' ' : '').($user?->email ? '('.$user->email.')' : '')) ?: $this->t('guest');
        $file = $order->modelFile;
        $name = (string) ($file?->original_name ?: '—');
        $lines = [
            $this->t('line_customer', ['who' => $who]),
            $file && $file->origin === 'tool' ? $this->t('line_model_tool', ['name' => $name, 'tool' => $file->kind()]) : $this->t('line_model', ['name' => $name]),
        ];
        if ($spools = $order->spoolText() ?: (string) $order->color?->name) {
            $lines[] = $this->t('line_spools', ['spools' => $spools]);
        }
        if ($printer = $order->printer) {
            $lines[] = $this->t('line_printer', ['printer' => $printer->name.' ('.$printer->shortCode().')', 'mode' => $this->t($printer->isAgentDriven() ? 'line_mode_agent' : 'line_mode_manual')]);
        }

        return $lines;
    }

    /** When the order starts and ends, as the queue sees it right after the payment. */
    private function queueLine(FarmOrder $order, string $situation): string
    {
        if ($situation === 'reslice') {
            return $this->t('line_queue_reslice', ['printer' => (string) $order->printer?->name]);
        }
        if ($situation === 'approval') {
            return $this->t('line_queue_approval');
        }
        if ($order->printer && ! $order->printer->isAgentDriven()) {
            return $this->t('line_queue_manual');
        }
        $e = app(Dispatcher::class)->estimate($order, $this->settings);
        if (! $e) {
            return $this->t('line_queue_unknown');
        }
        $finish = self::duration($e['finish_in']);

        return match ($e['blocked']) {
            'offline' => $this->t('line_queue_offline', ['finish' => $finish]),
            'plate' => $this->t('line_queue_plate', ['finish' => $finish]),
            default => $e['start_in'] <= 0 ? $this->t('line_queue_now', ['finish' => $finish]) : $this->t('line_queue_wait', ['start' => self::duration($e['start_in']), 'n' => $e['ahead'], 'finish' => $finish]),
        };
    }

    /** "Výdejní místo: Z-BOX Praha 4 (CZ)", "Doručení domů: Dlouhá 1, Praha, 110 00 (CZ)", "Osobní odběr". */
    private function deliveryText(FarmOrder $order): string
    {
        $a = (array) $order->shipping_address;
        $label = Lang::get('farm.delivery.'.$order->delivery, [], 'cs');
        $place = match ($order->delivery) {
            Shipping::POINT => (string) ($a['pickup_point_name'] ?? ''),
            Shipping::HOME => implode(', ', array_filter([$a['street'] ?? null, $a['city'] ?? null, $a['zip'] ?? null])),
            default => '',
        };
        $place = trim($place.(! empty($a['country']) ? ' ('.$a['country'].')' : ''));

        return $place !== '' ? $label.': '.$place : $label;
    }

    /**
     * The camera's last picture of this print as a file to attach: as it is up to SNAPSHOT_MAX_BYTES, else shrunk to
     * 1280 px beside it, else nothing (the mail says so by leaving the line out).
     */
    private function snapshotFor(FarmOrder $order): ?string
    {
        $job = $order->printJobs()->reorder()->whereNotNull('snapshot_path')->latest('id')->first();
        $disk = Storage::disk(config('farm.disk'));
        if (! $job || ! $disk->exists($job->snapshot_path)) {
            return null;
        }
        $path = $disk->path($job->snapshot_path);
        if (filesize($path) <= self::SNAPSHOT_MAX_BYTES) {
            return $path;
        }
        if (! function_exists('imagecreatefromjpeg') || ! ($img = @imagecreatefromjpeg($path))) {
            return null;
        }
        $width = imagesx($img);
        if ($width > 1280) {
            $img = imagescale($img, 1280) ?: $img;
        }
        $small = $disk->path($order->dir().'/snapshot_mail.jpg');
        imagejpeg($img, $small, 70);
        imagedestroy($img);

        return is_file($small) && filesize($small) <= self::SNAPSHOT_MAX_BYTES ? $small : null;
    }

    /** The operator reads Czech whatever the customer's language was when the event happened. */
    private function t(string $key, array $replace = []): string
    {
        return Lang::get('farm.admin.mail.'.$key, $replace, 'cs');
    }

    /** A time as the operator's clock shows it (the app runs in UTC). */
    private function clock(\DateTimeInterface $at, string $format = 'H:i'): string
    {
        return Carbon::instance($at)->setTimezone((string) config('farm.operator_timezone', 'Europe/Prague'))->format($format);
    }

    /** "1 h 47 min", "12 min". */
    public static function duration(int $minutes): string
    {
        $minutes = max(0, $minutes);

        return $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    }

    /** F26-000123: year + running number. */
    private function nextNumber(): string
    {
        $prefix = 'F'.now()->format('y').'-';
        $last = FarmOrder::where('number', 'like', $prefix.'%')->lockForUpdate()->max('number');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 6, '0', STR_PAD_LEFT);
    }
}
