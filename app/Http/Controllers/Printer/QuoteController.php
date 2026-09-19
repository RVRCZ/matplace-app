<?php

namespace App\Http\Controllers\Printer;

use App\Domain\Quote\QuoteBuilder;
use App\Http\Controllers\Controller;
use App\Mail\QuoteSent;
use App\Models\Calculation;
use App\Models\PrinterProfile;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Quotes: one button from the calculator → editable lines → PDF + online link → client accepts. */
class QuoteController extends Controller
{
    private function profile(Request $request): PrinterProfile
    {
        return $request->user()->printerProfile()->with(['pricingProfiles', 'materials'])->firstOrFail();
    }

    private function own(Request $request, Quote $quote): Quote
    {
        abort_unless($quote->printer_profile_id === $this->profile($request)->id, 403);

        return $quote;
    }

    public function index(Request $request): View
    {
        $profile = $this->profile($request);
        $quotes = Quote::where('printer_profile_id', $profile->id)->latest('id')->paginate(30);

        return view('printer.quotes', ['profile' => $profile, 'quotes' => $quotes]);
    }

    /** POST /tiskar/nabidky — from a calculation token (calculator "Create quote") or empty. */
    public function store(Request $request, QuoteBuilder $builder): RedirectResponse
    {
        $profile = $this->profile($request);
        $data = $request->validate(['calculation' => ['nullable', 'string', 'max:16']]);
        $calc = ! empty($data['calculation']) ? Calculation::where('token', $data['calculation'])->with('modelFile')->first() : null;
        if ($calc && $calc->owner_user_id !== null && $calc->owner_user_id !== $request->user()->id) {
            abort(403);
        }
        $quote = $builder->fromCalculation($profile, $calc);

        return redirect()->route('printer.quotes.edit', $quote)->with('status', __('quote.created'));
    }

    public function edit(Request $request, Quote $quote): View
    {
        $quote = $this->own($request, $quote)->load(['modelFile', 'calculation']);

        return view('printer.quote_edit', ['quote' => $quote, 'profile' => $quote->printerProfile]);
    }

    /** Lines are editable; the total is always the sum of the lines (no hidden formula on this page). */
    public function update(Request $request, Quote $quote, QuoteBuilder $builder): RedirectResponse
    {
        $quote = $this->own($request, $quote);
        $data = $request->validate([
            'client_name' => ['nullable', 'string', 'max:160'],
            'client_email' => ['nullable', 'email'],
            'title' => ['nullable', 'string', 'max:200'],
            'valid_until' => ['nullable', 'date'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.label' => ['required', 'string', 'max:120'],
            'lines.*.qty' => ['required', 'numeric', 'min:0', 'max:100000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:-1000000', 'max:1000000'],
        ]);
        $lines = $builder->normaliseLines($data['lines'], $quote->lines);
        $quote->fill([
            'client_name' => $data['client_name'] ?? null,
            'client_email' => $data['client_email'] ?? null,
            'title' => $data['title'] ?? $quote->title,
            'valid_until' => $data['valid_until'] ?? null,
            'lead_time_days' => $data['lead_time_days'] ?? $quote->lead_time_days,
            'note' => $data['note'] ?? null,
            'lines' => $lines,
            'total' => Quote::sumLines($lines),
        ]);
        if ($quote->status === Quote::STATUS_SENT || $quote->status === Quote::STATUS_VIEWED) {
            $quote->pdf_path = null; // regenerate on next send
        }
        $quote->save();

        return redirect()->route('printer.quotes.edit', $quote)->with('status', __('quote.saved'));
    }

    /** Generates the PDF, e-mails the client (if e-mail given) and marks the quote as sent. */
    public function send(Request $request, Quote $quote, QuoteBuilder $builder): RedirectResponse
    {
        $quote = $this->own($request, $quote)->load(['printerProfile', 'modelFile']);
        $builder->renderPdf($quote);
        $quote->status = Quote::STATUS_SENT;
        $quote->sent_at = now();
        $quote->save();

        if ($quote->client_email) {
            Mail::to($quote->client_email)->send(new QuoteSent($quote));
            $msg = __('quote.sent_mail', ['email' => $quote->client_email]);
        } else {
            $msg = __('quote.sent_link');
        }

        return redirect()->route('printer.quotes.edit', $quote)->with('status', $msg);
    }

    /** "Repeat order": new draft with the same lines and client. */
    public function duplicate(Request $request, Quote $quote): RedirectResponse
    {
        $quote = $this->own($request, $quote);
        $copy = $quote->replicate(['token', 'number', 'status', 'pdf_path', 'sent_at', 'viewed_at', 'accepted_at', 'declined_at']);
        $copy->token = Quote::newToken();
        $copy->number = Quote::nextNumber($quote->printer_profile_id);
        $copy->status = Quote::STATUS_DRAFT;
        $copy->valid_until = now()->addDays(14);
        $copy->save();

        return redirect()->route('printer.quotes.edit', $copy)->with('status', __('quote.duplicated'));
    }

    public function pdf(Request $request, Quote $quote, QuoteBuilder $builder): Response
    {
        $quote = $this->own($request, $quote)->load(['printerProfile', 'modelFile']);

        return $builder->pdfResponse($quote);
    }

    // ── Public (client side) ─────────────────────────────────────────────────

    public function publicShow(Quote $quote): View
    {
        abort_if($quote->status === Quote::STATUS_DRAFT, 404);
        $quote->load(['printerProfile', 'modelFile', 'calculation']);
        if ($quote->status === Quote::STATUS_SENT) {
            $quote->forceFill(['status' => Quote::STATUS_VIEWED, 'viewed_at' => now()])->save();
        }

        return view('quote.public', ['quote' => $quote, 'profile' => $quote->printerProfile, 'logo' => PrinterController::logoUrl($quote->printerProfile)]);
    }

    public function publicPdf(Quote $quote, QuoteBuilder $builder): Response
    {
        abort_if($quote->status === Quote::STATUS_DRAFT, 404);

        return $builder->pdfResponse($quote->load(['printerProfile', 'modelFile']));
    }

    public function accept(Quote $quote): RedirectResponse
    {
        abort_unless($quote->isOpen(), 404);
        $quote->forceFill(['status' => Quote::STATUS_ACCEPTED, 'accepted_at' => now()])->save();
        // The printer learns about it by e-mail; the platform steps back from here ("spojíme a ustoupíme").
        if ($quote->printerProfile->contact_email) {
            Mail::to($quote->printerProfile->contact_email)->send(new \App\Mail\QuoteAccepted($quote));
        }

        return redirect()->route('quote.public', $quote)->with('status', __('quote.accepted_thanks'));
    }

    public function decline(Quote $quote): RedirectResponse
    {
        abort_unless($quote->isOpen(), 404);
        $quote->forceFill(['status' => Quote::STATUS_DECLINED, 'declined_at' => now()])->save();

        return redirect()->route('quote.public', $quote)->with('status', __('quote.declined_thanks'));
    }
}
