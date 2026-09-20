<?php

namespace App\Http\Controllers\Printer;

use App\Domain\Inquiry\InquiryService;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\InquiryDispatch;
use App\Models\PrinterProfile;
use App\Models\Thread;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Printer side: inquiries sent to me, offer / decline, chat. */
class InquiryController extends Controller
{
    private function profile(Request $request): PrinterProfile
    {
        return $request->user()->printerProfile()->with(['materials', 'pricingProfiles'])->firstOrFail();
    }

    private function dispatchFor(PrinterProfile $profile, Inquiry $inquiry): InquiryDispatch
    {
        return InquiryDispatch::where('inquiry_id', $inquiry->id)->where('printer_profile_id', $profile->id)->firstOrFail();
    }

    public function index(Request $request): View
    {
        $profile = $this->profile($request);
        $dispatches = InquiryDispatch::with(['inquiry.modelFile', 'inquiry.offers' => fn ($q) => $q->where('printer_profile_id', $profile->id)])
            ->where('printer_profile_id', $profile->id)->latest('id')->paginate(30);
        $threads = Thread::where('printer_profile_id', $profile->id)->get()->keyBy('inquiry_id');

        return view('printer.inquiries', ['profile' => $profile, 'dispatches' => $dispatches, 'threads' => $threads]);
    }

    public function show(Request $request, Inquiry $inquiry): View
    {
        $profile = $this->profile($request);
        $dispatch = $this->dispatchFor($profile, $inquiry);
        if (! $dispatch->seen_at) {
            $dispatch->forceFill(['seen_at' => now()])->save();
        }
        $inquiry->load(['modelFile', 'calculation']);
        $offer = $inquiry->offers()->where('printer_profile_id', $profile->id)->first();
        $thread = Thread::where('inquiry_id', $inquiry->id)->where('printer_profile_id', $profile->id)->first();
        if ($thread) {
            $thread->forceFill(['printer_read_at' => now()])->save();
        }

        return view('printer.inquiry_show', [
            'profile' => $profile, 'inquiry' => $inquiry, 'dispatch' => $dispatch, 'offer' => $offer, 'thread' => $thread,
            'accepted' => $inquiry->accepted_quote_id && $offer && $inquiry->accepted_quote_id === $offer->id,
        ]);
    }

    public function offer(Request $request, Inquiry $inquiry, InquiryService $service): RedirectResponse
    {
        $profile = $this->profile($request);
        $dispatch = $this->dispatchFor($profile, $inquiry);
        abort_unless($inquiry->isOpenForOffers(), 422);
        $data = $request->validate([
            'total' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->offer($inquiry, $profile, (float) $data['total'], $data['lead_time_days'] ?? null, $data['note'] ?? null, $dispatch->auto_breakdown);

        return redirect()->route('printer.inquiries.show', $inquiry)->with('status', __('inquiry.offer_sent'));
    }

    public function decline(Request $request, Inquiry $inquiry, InquiryService $service): RedirectResponse
    {
        $profile = $this->profile($request);
        $this->dispatchFor($profile, $inquiry);
        $service->decline($inquiry, $profile, $request->input('reason'));

        return redirect()->route('printer.inquiries')->with('status', __('inquiry.declined_status'));
    }
}
