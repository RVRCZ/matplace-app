<?php

namespace App\Domain\Inquiry;

use App\Domain\Geo\Geocoder;
use App\Mail\CustomerInquiryVerify;
use App\Mail\CustomerNewOffer;
use App\Mail\CustomerOfferDeclined;
use App\Mail\PrinterOfferAccepted;
use App\Mail\PrinterOfferLost;
use App\Models\AnonymousSession;
use App\Models\Calculation;
use App\Models\Inquiry;
use App\Models\InquiryDispatch;
use App\Models\PrinterProfile;
use App\Models\Quote;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Inquiry lifecycle: create (+ e-mail verification for guests) → dispatch → offers → accept → done. */
final class InquiryService
{
    public function __construct(private readonly Geocoder $geocoder, private readonly InquiryDispatcher $dispatcher) {}

    public function create(Calculation $calc, array $data, ?User $user, ?AnonymousSession $session): Inquiry
    {
        $geo = $this->geocoder->resolve($data['zip'] ?? null, $data['city'] ?? null, $data['country'] ?? 'CZ');
        $slicer = $calc->slicer ?? [];
        $rough = $calc->rough ?? [];

        $inquiry = Inquiry::create([
            'token' => Inquiry::newToken(),
            'calculation_id' => $calc->id,
            'model_file_id' => $calc->model_file_id,
            'customer_user_id' => $user?->id,
            'contact_name' => $data['name'] ?? $user?->name,
            'contact_email' => strtolower(trim($data['email'] ?? $user?->email ?? '')),
            'contact_phone' => $data['phone'] ?? $user?->phone,
            'country' => strtoupper($data['country'] ?? 'CZ'),
            'zip' => $data['zip'] ?? null,
            'city' => $data['city'] ?? null,
            'lat' => $geo['lat'] ?? null,
            'lng' => $geo['lng'] ?? null,
            'material_code' => strtoupper((string) ($calc->params['material'] ?? 'PLA')),
            'quantity' => max(1, (int) ($data['quantity'] ?? $calc->params['quantity'] ?? 1)),
            'params' => $calc->params,
            'summary' => [
                'grams' => $slicer['grams'] ?? $rough['grams'] ?? null,
                'minutes' => $slicer['minutes'] ?? $rough['minutes'] ?? null,
                'dims' => $slicer['dims'] ?? $calc->modelFile?->bbox,
                'warnings' => $slicer['warnings'] ?? [],
                'precise' => isset($slicer['grams']),
            ],
            'note' => $data['note'] ?? null,
            'color' => $data['color'] ?? null,
            'wanted_by' => $data['wanted_by'] ?? null,
            'delivery_pref' => in_array($data['delivery_pref'] ?? 'any', ['any', 'pickup', 'shipping'], true) ? ($data['delivery_pref'] ?? 'any') : 'any',
            'status' => Inquiry::STATUS_PENDING,
            'verification_code' => $user ? null : Str::random(32),
            'verified_at' => $user ? now() : null,
            'expires_at' => now()->addDays((int) config('inquiries.expire_days', 14)),
            'locale' => app()->getLocale(),
        ]);

        // remember the customer's location on the account for next time
        if ($user && $geo && ($user->lat === null || ! empty($data['zip']))) {
            $user->forceFill(['zip' => $data['zip'] ?? $user->zip, 'city' => $data['city'] ?? $user->city, 'lat' => $geo['lat'], 'lng' => $geo['lng']])->save();
        }
        if ($session && $calc->anonymous_session_id === $session->id && $user) {
            $user->claimSession($session);
        }

        if ($user) {
            $this->dispatcher->dispatch($inquiry);
        } else {
            Mail::to($inquiry->contact_email)->locale($inquiry->locale)->send(new CustomerInquiryVerify($inquiry));
        }

        return $inquiry;
    }

    /** Guest e-mail verification link → dispatch. Returns false when the code is wrong. */
    public function verify(Inquiry $inquiry, string $code): bool
    {
        if ($inquiry->verified_at) {
            return true;
        }
        if (! $inquiry->verification_code || ! hash_equals($inquiry->verification_code, $code)) {
            return false;
        }
        $inquiry->forceFill(['verified_at' => now()])->save();
        $this->dispatcher->dispatch($inquiry);

        return true;
    }

    /** Printer answers with an offer: a quote linked to the inquiry, a chat thread, a mail to the customer. */
    public function offer(Inquiry $inquiry, PrinterProfile $printer, float $total, ?int $leadTimeDays, ?string $note, ?array $breakdown): Quote
    {
        $lines = [];
        if ($breakdown) {
            $q = $inquiry->quantity;
            $lines[] = ['key' => 'material', 'label' => __('quote.line.material', ['material' => $inquiry->material_code]), 'qty' => $q, 'unit_price' => round((float) $breakdown['unit']['material'], 2), 'total' => round($q * (float) $breakdown['unit']['material'], 2)];
            $lines[] = ['key' => 'time', 'label' => __('quote.line.time'), 'qty' => $q, 'unit_price' => round((float) $breakdown['unit']['time'], 2), 'total' => round($q * (float) $breakdown['unit']['time'], 2)];
            if ((float) $breakdown['setup'] > 0) {
                $lines[] = ['key' => 'setup', 'label' => __('quote.line.setup'), 'qty' => 1, 'unit_price' => (float) $breakdown['setup'], 'total' => (float) $breakdown['setup']];
            }
            $diff = round($total - Quote::sumLines($lines), 2);
            if (abs($diff) >= 0.5) {
                $lines[] = ['key' => 'adjust', 'label' => __('quote.line.adjust'), 'qty' => 1, 'unit_price' => $diff, 'total' => $diff];
            }
        } else {
            $lines[] = ['key' => 'print', 'label' => __('quote.line.print'), 'qty' => $inquiry->quantity, 'unit_price' => round($total / max(1, $inquiry->quantity), 2), 'total' => $total];
        }

        $quote = Quote::updateOrCreate(['inquiry_id' => $inquiry->id, 'printer_profile_id' => $printer->id], [
            'token' => Quote::newToken(),
            'number' => Quote::nextNumber($printer->id),
            'calculation_id' => $inquiry->calculation_id,
            'model_file_id' => $inquiry->model_file_id,
            'client_name' => $inquiry->contact_name,
            'client_email' => $inquiry->contact_email,
            'title' => $inquiry->modelFile?->original_name,
            'params' => $inquiry->params,
            'lines' => $lines,
            'total' => round($total, 0),
            'valid_until' => now()->addDays(14),
            'lead_time_days' => $leadTimeDays,
            'note' => $note,
            'status' => Quote::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $thread = Thread::open($inquiry, $printer, $quote);
        $thread->post('system', __('inquiry.sys.offer', ['price' => number_format($quote->total, 0, ',', ' ')]));
        if ($note) {
            $thread->post('printer', $note, $printer->user);
        }

        if ($inquiry->status === Inquiry::STATUS_OPEN) {
            $inquiry->update(['status' => Inquiry::STATUS_OFFERED]);
        }
        $customer = $inquiry->customer;
        if (! $customer || $customer->notify_email) {
            Mail::to($inquiry->contact_email)->locale($inquiry->locale)->queue(new CustomerNewOffer($inquiry, $quote, $printer));
        }

        return $quote;
    }

    public function decline(Inquiry $inquiry, PrinterProfile $printer, ?string $reason): void
    {
        InquiryDispatch::where('inquiry_id', $inquiry->id)->where('printer_profile_id', $printer->id)
            ->update(['declined_at' => now(), 'decline_reason' => $reason ? mb_substr($reason, 0, 200) : null]);
    }

    /** Customer picks one offer. Others are closed politely; the platform steps back. */
    public function accept(Inquiry $inquiry, Quote $quote): void
    {
        abort_unless($quote->inquiry_id === $inquiry->id && $inquiry->isOpenForOffers(), 422);
        $quote->forceFill(['status' => Quote::STATUS_ACCEPTED, 'accepted_at' => now()])->save();
        $inquiry->forceFill(['status' => Inquiry::STATUS_ACCEPTED, 'accepted_quote_id' => $quote->id, 'accepted_at' => now()])->save();

        $printer = $quote->printerProfile;
        Thread::open($inquiry, $printer, $quote)->post('system', __('inquiry.sys.accepted'));
        Mail::to($printer->contact_email ?: $printer->user->email)->locale($printer->user->locale ?: 'cs')->queue(new PrinterOfferAccepted($inquiry, $quote));

        foreach ($inquiry->offers()->where('id', '!=', $quote->id)->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])->get() as $other) {
            $other->forceFill(['status' => Quote::STATUS_DECLINED, 'declined_at' => now()])->save();
            $op = $other->printerProfile;
            Thread::open($inquiry, $op, $other)->post('system', __('inquiry.sys.lost'));
            Mail::to($op->contact_email ?: $op->user->email)->locale($op->user->locale ?: 'cs')->queue(new PrinterOfferLost($inquiry, $other));
        }
    }

    public function markDone(Inquiry $inquiry): void
    {
        abort_unless($inquiry->status === Inquiry::STATUS_ACCEPTED, 422);
        $inquiry->forceFill(['status' => Inquiry::STATUS_DONE, 'done_at' => now()])->save();
    }

    public function cancel(Inquiry $inquiry): void
    {
        abort_unless(in_array($inquiry->status, [Inquiry::STATUS_PENDING, Inquiry::STATUS_OPEN, Inquiry::STATUS_OFFERED], true), 422);
        $inquiry->forceFill(['status' => Inquiry::STATUS_CANCELLED])->save();
        foreach ($inquiry->offers()->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])->get() as $o) {
            $o->forceFill(['status' => Quote::STATUS_DECLINED, 'declined_at' => now()])->save();
            $op = $o->printerProfile;
            Mail::to($op->contact_email ?: $op->user->email)->locale($op->user->locale ?: 'cs')->queue(new CustomerOfferDeclined($inquiry, $o));
        }
    }
}
