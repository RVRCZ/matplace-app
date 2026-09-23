<?php

namespace App\Domain\Farm;

use App\Mail\FarmAdminAlert;
use App\Mail\FarmOrderStatus;
use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinterSlot;
use App\Models\FarmPrintJob;
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

    public function __construct(private readonly Wallet $wallet, private readonly FarmSettings $settings, private readonly OrderService $orders) {}

    /**
     * Pay from credit and send to the queue.
     *
     * @param  float|null  $expectedTotal  what the customer saw; a different price (other printer, changed rates) is refused, never charged silently
     *
     * @throws FarmRefusal|InsufficientCredit
     */
    public function pay(FarmOrder $order, FarmPrinterSlot $slot, string $delivery, ?array $address, bool $termsAccepted, ?string $ip, ?float $expectedTotal = null, ?string $note = null): FarmOrder
    {
        if ($order->status !== FarmOrder::STATUS_SLICED) {
            throw new FarmRefusal('not_ready');
        }
        if (! $termsAccepted) {
            throw new FarmRefusal('terms');
        }
        if (! in_array($delivery, (array) $this->settings->get('delivery_modes'), true)) {
            throw new FarmRefusal('delivery');
        }
        $offer = $this->orders->availableColors($order)->first(fn ($r) => $r['slot']->id === $slot->id);
        if (! $offer) {
            throw new FarmRefusal('color_gone');
        }
        if (! $offer['enough']) {
            throw new FarmRefusal('filament_low');
        }

        $price = $this->orders->priceFor($order, $offer['printer'], $delivery, $offer['color']->material);
        if ($expectedTotal !== null && abs($expectedTotal - $price['total']) > 0.009) {
            throw new FarmRefusal('price_changed', ['total' => $price['total']]);
        }

        DB::transaction(function () use ($order, $offer, $delivery, $address, $ip, $price, $note) {
            $order->fill([
                'farm_printer_id' => $offer['printer']->id, 'farm_printer_slot_id' => $offer['slot']->id, 'farm_color_id' => $offer['color']->id,
                'farm_material_id' => $offer['color']->farm_material_id,
                'delivery' => $delivery, 'shipping_address' => $delivery === 'shipping' ? $address : null, 'note' => $note,
                'price' => $price, 'price_total' => $price['total'],
                'terms_version' => (string) $this->settings->get('terms_version'), 'terms_accepted_at' => now(), 'terms_ip' => $ip,
            ])->save();
            $this->wallet->hold($order);
            $order->forceFill(['paid_at' => now(), 'number' => $this->nextNumber()])->save();
            $this->move($order, FarmOrder::STATUS_PAID, 'user', $order->user_id);
        });

        if ($offer['color']->sliceOverrides()) {
            // this spool prints best with its own slicer settings: slice again for them, the paid order waits meanwhile
            $order->forceFill(['status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'slicing'])->save();
            $order->events()->create(['from' => FarmOrder::STATUS_PAID, 'to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'system', 'note' => 'reslice for the spool settings']);
            \App\Jobs\PrepareFarmOrder::dispatch($order->id);
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
        if (! $order->canMoveTo($to)) {
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
        if ($slot = $order->slot) {
            // what really left the spool when we know it, else the estimate
            $used = (float) ($order->actual_grams ?? $order->est_grams ?? 0);
            $slot->update(['remaining_g' => max(0, $slot->remaining_g - $used)]);
        }
    }

    private function onStopped(FarmOrder $order, bool $cancelPrint): void
    {
        $this->wallet->giveBack($order);
        foreach ($order->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->get() as $job) {
            if ($cancelPrint && in_array($job->status, [FarmPrintJob::STATUS_PRINTING, FarmPrintJob::STATUS_PAUSED, FarmPrintJob::STATUS_SENT, FarmPrintJob::STATUS_UNKNOWN], true)) {
                FarmCommand::create(['farm_printer_id' => $job->farm_printer_id, 'farm_print_job_id' => $job->id, 'type' => FarmCommand::TYPE_CANCEL]);
            }
            FarmCommand::where('farm_print_job_id', $job->id)->where('status', 'pending')->where('type', FarmCommand::TYPE_START)->update(['status' => 'failed', 'result' => 'order stopped']);
            $job->update(['status' => $cancelPrint ? FarmPrintJob::STATUS_CANCELLED : FarmPrintJob::STATUS_FAILED, 'finished_at' => now()]);
        }
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
