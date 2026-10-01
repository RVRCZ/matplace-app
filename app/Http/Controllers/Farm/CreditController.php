<?php

namespace App\Http\Controllers\Farm;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\Wallet;
use App\Engines\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Support\Currency;
use App\Support\CurrencyMismatch;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Prepaid credit: balance, history, top-up through the payment gateway. Credit is added by the webhook only. */
class CreditController extends Controller
{
    public function __construct(private readonly Wallet $wallet, private readonly FarmSettings $settings) {}

    /**
     * What an account may top up by, in its currency: crowns from the admin's settings, euros from config
     * (`farm.topup_eur`; the smallest top-up is 5 €).
     *
     * @return array{amounts: list<int>, min: int, max: int}
     */
    private function limits(string $currency): array
    {
        if ($currency === Money::EUR) {
            return ['amounts' => array_map('intval', (array) config('farm.topup_eur.amounts', [10, 20, 50])), 'min' => (int) config('farm.topup_eur.min', 5), 'max' => (int) config('farm.topup_eur.max', 800)];
        }

        return ['amounts' => array_map('intval', (array) $this->settings->get('topup_amounts')), 'min' => (int) $this->settings->get('topup_min'), 'max' => (int) $this->settings->get('topup_max')];
    }

    public function index(Request $request): View
    {
        $balance = $this->wallet->balance($request->user());
        $limits = $this->limits($balance->currency);

        return view('farm.credit', [
            'balance' => $balance,
            'transactions' => CreditTransaction::with('order')->where('user_id', $request->user()->id)->where('type', '!=', CreditTransaction::TYPE_CAPTURE)->latest('id')->paginate(30),
            'amounts' => $limits['amounts'],
            'min' => $limits['min'],
            'max' => $limits['max'],
            'currency' => $balance->currency,
            // the first payment fixes the currency of the account: say so while it can still be chosen
            'locked' => Currency::locked($request->user()),
            // what an order is short of, rounded up to something that can be typed (whole crowns, whole euros)
            'need' => (int) ceil(max(0, (float) $request->query('need', 0))),
            'back' => FarmOrder::where('token', (string) $request->query('back'))->where('user_id', $request->user()->id)->first(),
            'pending' => $request->query('paid') ? Payment::where('user_id', $request->user()->id)->latest('id')->first() : null,
        ]);
    }

    public function topUp(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        // the account's currency; before the first payment the one this customer sees prices in (this payment fixes it)
        $currency = $request->user()->currency ?: Currency::current($request->user());
        ['min' => $min, 'max' => $max] = $this->limits($currency);
        // the quick-amount buttons and the free field live in one form; a pressed button wins
        $request->merge(['amount' => $request->input('preset', $request->input('amount'))]);
        $data = $request->validate(['amount' => ['required', 'integer', 'min:'.$min, 'max:'.$max], 'back' => ['nullable', 'string', 'size:32']]);

        $payment = Payment::create([
            'user_id' => $request->user()->id, 'gateway' => $gateway->name(), 'amount' => $data['amount'],
            'currency' => $currency, 'status' => Payment::STATUS_PENDING,
        ]);
        $back = array_filter(['back' => $data['back'] ?? null]);
        try {
            $url = $gateway->checkoutUrl($payment->load('user'), route('account.credit', ['paid' => 1] + $back), route('account.credit', $back));
        } catch (\Throwable $e) {
            Log::error('Top-up checkout failed', ['payment' => $payment->id, 'error' => $e->getMessage()]);
            $payment->update(['status' => Payment::STATUS_FAILED]);

            return back()->with('error', __('farm.credit.gateway_down'));
        }

        return redirect()->away($url);
    }

    /** POST /webhooks/payments/{gateway} — called by the gateway, no session, verified by signature. */
    public function webhook(Request $request, PaymentGateway $gateway, string $name): Response
    {
        abort_unless($name === $gateway->name(), 404);
        try {
            $event = $gateway->parseWebhook($request);
        } catch (\Throwable $e) {
            Log::warning('Payment webhook rejected', ['error' => $e->getMessage()]);

            return response('invalid signature', 400);
        }
        if ($event === null) {
            return response('ignored', 200);
        }
        $payment = Payment::where('gateway', $gateway->name())->where('gateway_ref', $event['ref'])->first();
        if (! $payment) {
            return response('unknown payment', 200);   // another product on the same Stripe account: not ours, not an error
        }
        if ($event['paid']) {
            if ($payment->status !== Payment::STATUS_PAID) {
                $payment->update(['status' => Payment::STATUS_PAID, 'paid_at' => now(), 'raw' => $event['raw']]);
            }
            try {
                $this->wallet->topUp($payment);   // idempotent; the first one fixes the currency of the account
            } catch (CurrencyMismatch $e) {
                // two checkouts in two currencies were open and both got paid: the money is at the gateway, the ledger
                // stays clean, a person decides (refund at the gateway, or a correction by hand)
                Log::error('Top-up in another currency than the account', ['payment' => $payment->id, 'error' => $e->getMessage()]);
                app(OrderFlow::class)->alertAdmin(__('farm.admin.mail.currency', ['payment' => $payment->id]), null, [$payment->user?->email.': '.Money::of($payment->amount, $payment->currency)->format(), $e->getMessage()]);
            }
        } elseif ($event['expired'] && $payment->status === Payment::STATUS_PENDING) {
            $payment->update(['status' => Payment::STATUS_EXPIRED, 'raw' => $event['raw']]);
        }

        return response('ok', 200);
    }
}
