<?php

namespace App\Http\Controllers;

use App\Domain\Inquiry\InquiryService;
use App\Models\Inquiry;
use App\Models\Quote;
use App\Models\Rating;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Customer side: /i/{token} — the link is the access (e-mailed to the customer), like a shared calculation. */
class InquiryController extends Controller
{
    public function show(Inquiry $inquiry): View
    {
        $inquiry->load(['modelFile', 'calculation', 'offers.printerProfile.user', 'threads.printerProfile', 'dispatches']);
        $offers = $inquiry->offers->sortBy('total')->values();

        return view('inquiry.show', [
            'inquiry' => $inquiry,
            'offers' => $offers,
            'threads' => $inquiry->threads->keyBy('printer_profile_id'),
            'rated' => Rating::where('inquiry_id', $inquiry->id)->exists(),
        ]);
    }

    public function verify(Inquiry $inquiry, string $code, InquiryService $service): RedirectResponse
    {
        $ok = $service->verify($inquiry, $code);

        return redirect()->route('inquiry.show', $inquiry)->with($ok ? 'status' : 'error', $ok ? __('inquiry.verified') : __('inquiry.verify_failed'));
    }

    public function accept(Inquiry $inquiry, Quote $quote, InquiryService $service): RedirectResponse
    {
        $service->accept($inquiry, $quote);

        return redirect()->route('inquiry.show', $inquiry)->with('status', __('inquiry.accepted_status'));
    }

    public function done(Inquiry $inquiry, InquiryService $service): RedirectResponse
    {
        $service->markDone($inquiry);

        return redirect()->route('inquiry.show', $inquiry)->with('status', __('inquiry.done_status'));
    }

    public function cancel(Inquiry $inquiry, InquiryService $service): RedirectResponse
    {
        $service->cancel($inquiry);

        return redirect()->route('inquiry.show', $inquiry)->with('status', __('inquiry.cancelled_status'));
    }

    public function rate(Request $request, Inquiry $inquiry): RedirectResponse
    {
        abort_unless($inquiry->status === Inquiry::STATUS_DONE && $inquiry->accepted_quote_id, 422);
        $data = $request->validate(['score' => ['required', 'integer', 'min:1', 'max:5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $printerUser = $inquiry->acceptedQuote->printerProfile->user;
        if (! Rating::where('inquiry_id', $inquiry->id)->exists()) {
            Rating::create([
                'to_user_id' => $printerUser->id, 'from_user_id' => $inquiry->customer_user_id, 'role_rated' => 'printer',
                'score' => $data['score'], 'comment' => $data['comment'] ?? null, 'status' => 'approved', 'inquiry_id' => $inquiry->id,
            ]);
            Rating::refreshUser($printerUser->id);
        }

        return redirect()->route('inquiry.show', $inquiry)->with('status', __('inquiry.rated'));
    }
}
