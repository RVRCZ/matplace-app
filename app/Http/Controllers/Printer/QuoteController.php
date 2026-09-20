<?php

namespace App\Http\Controllers\Printer;

use App\Domain\Quote\CostSheet;
use App\Domain\Quote\QuoteBuilder;
use App\Http\Controllers\Controller;
use App\Mail\QuoteAccepted;
use App\Mail\QuoteChangeRequested;
use App\Mail\QuoteSent;
use App\Models\Calculation;
use App\Models\PrinterProfile;
use App\Models\Quote;
use App\Models\QuoteVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quotes: calculator → internal cost sheet (only the printer sees it) → one price for the customer → private link.
 * The customer accepts or asks for a change; both are tied to the exact version they were looking at.
 */
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

    /** POST /printer/quotes — from a calculation token (calculator "Create quote") or empty. */
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
        $quote = $this->own($request, $quote)->load(['modelFile', 'calculation', 'versions']);
        // quotes made before the cost sheet existed (and inquiry offers) start from their total
        $sheet = $quote->cost ?: CostSheet::compute(['quantity' => $quote->params['quantity'] ?? 1, 'final_price' => Quote::sumLines(array_filter((array) $quote->lines, fn ($l) => in_array($l['key'] ?? '', Quote::INTERNAL_KEYS, true)))]);

        return view('printer.quote_edit', [
            'quote' => $quote, 'profile' => $quote->printerProfile, 'sheet' => $sheet,
            'extras' => array_values(array_filter((array) $quote->lines, fn ($l) => ! in_array($l['key'] ?? 'custom', Quote::INTERNAL_KEYS, true))),
            'roundTo' => (int) config('pricing.round_to', 1),
        ]);
    }

    public function update(Request $request, Quote $quote, QuoteBuilder $builder): RedirectResponse
    {
        $quote = $this->own($request, $quote);
        abort_if($quote->status === Quote::STATUS_ACCEPTED, 409, __('quote.locked_accepted'));
        $data = $request->validate([
            'client_name' => ['nullable', 'string', 'max:160'],
            'client_email' => ['nullable', 'email'],
            'title' => ['nullable', 'string', 'max:200'],
            'material' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
            'scope' => ['nullable', 'in:prints,parts,assembled'],
            'valid_until' => ['nullable', 'date'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:2000'],
            'shipping_label' => ['nullable', 'string', 'max:120'],
            'shipping_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cost' => ['required', 'array'],
            'cost.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'cost.material_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'cost.machine_hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cost.machine_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cost.setup_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'cost.labour_minutes' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cost.labour_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cost.failure_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cost.mode' => ['required', 'in:markup,margin'],
            'cost.pct' => ['nullable', 'numeric', 'min:0', $request->input('cost.mode') === 'margin' ? 'max:95' : 'max:1000'],
            'cost.min_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'cost.final_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'lines' => ['nullable', 'array', 'max:20'],
            'lines.*.label' => ['required', 'string', 'max:120'],
            'lines.*.qty' => ['required', 'numeric', 'min:0', 'max:100000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:-1000000', 'max:1000000'],
        ]);
        $quote->fill([
            'client_name' => $data['client_name'] ?? null,
            'client_email' => $data['client_email'] ?? null,
            'title' => $data['title'] ?? $quote->title,
            'color' => $data['color'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'lead_time_days' => $data['lead_time_days'] ?? $quote->lead_time_days,
            'note' => $data['note'] ?? null,
            'shipping_label' => $data['shipping_label'] ?? null,
            'shipping_price' => $data['shipping_price'] ?? 0,
            'params' => ['material' => $data['material'] ?? ($quote->params['material'] ?? null), 'scope' => $data['scope'] ?? ($quote->params['scope'] ?? 'prints')] + (array) $quote->params,
        ]);
        $builder->apply($quote, $data['cost'], $builder->normaliseLines($data['lines'] ?? []));
        $quote->pdf_path = null;
        $quote->save();

        return redirect()->route('printer.quotes.edit', $quote)->with('status', __('quote.saved'));
    }

    /**
     * Freezes what the customer will see as a version, renders the PDF, e-mails the link (if an e-mail is given).
     * Sending again after a change makes a new version; anything the customer did belongs to the version they saw.
     */
    public function send(Request $request, Quote $quote, QuoteBuilder $builder): RedirectResponse
    {
        $quote = $this->own($request, $quote)->load(['printerProfile', 'modelFile']);
        abort_if($quote->status === Quote::STATUS_ACCEPTED, 409, __('quote.locked_accepted'));
        abort_if($quote->total <= 0, 422, __('quote.no_price'));

        $snapshot = $quote->customerSnapshot();
        $last = $quote->versions()->orderByDesc('version')->first();
        if ($last && $last->snapshot != $snapshot) {
            $quote->version = $last->version + 1;
        } elseif ($last) {
            $quote->version = $last->version;
        }
        QuoteVersion::updateOrCreate(['quote_id' => $quote->id, 'version' => $quote->version], ['snapshot' => $snapshot, 'sent_at' => now()]);

        $quote->forceFill(['status' => Quote::STATUS_SENT, 'sent_at' => now(), 'change_request' => null, 'change_requested_at' => null, 'revoked_at' => null])->save();
        $builder->renderPdf($quote);

        if ($quote->client_email) {
            Mail::to($quote->client_email)->send(new QuoteSent($quote));
            $msg = __('quote.sent_mail', ['email' => $quote->client_email]);
        } else {
            $msg = __('quote.sent_link');
        }

        return redirect()->route('printer.quotes.edit', $quote)->with('status', $msg);
    }

    /** The link stops working at once. Sending again (or "new link") brings the quote back. */
    public function revoke(Request $request, Quote $quote): RedirectResponse
    {
        $quote = $this->own($request, $quote);
        $quote->forceFill(['revoked_at' => now()])->save();

        return redirect()->route('printer.quotes.edit', $quote)->with('status', __('quote.revoked'));
    }

    /** A fresh address: the old one is dead for good, the quote itself stays. */
    public function relink(Request $request, Quote $quote): RedirectResponse
    {
        $quote = $this->own($request, $quote);
        $quote->forceFill(['token' => Quote::newToken(), 'revoked_at' => null, 'pdf_path' => null])->save();

        return redirect()->route('printer.quotes.edit', $quote)->with('status', __('quote.relinked'));
    }

    /** "Repeat order": new draft with the same sums and client. */
    public function duplicate(Request $request, Quote $quote): RedirectResponse
    {
        $quote = $this->own($request, $quote);
        $copy = $quote->replicate(['token', 'number', 'status', 'pdf_path', 'sent_at', 'viewed_at', 'accepted_at', 'declined_at', 'revoked_at', 'version', 'accepted_version', 'change_request', 'change_requested_at', 'inquiry_id']);
        $copy->token = Quote::newToken();
        $copy->number = Quote::nextNumber($quote->printer_profile_id);
        $copy->status = Quote::STATUS_DRAFT;
        $copy->version = 1;
        $copy->valid_until = now()->addDays(14);
        $copy->save();

        return redirect()->route('printer.quotes.edit', $copy)->with('status', __('quote.duplicated'));
    }

    public function pdf(Request $request, Quote $quote, QuoteBuilder $builder): Response
    {
        $quote = $this->own($request, $quote)->load(['printerProfile', 'modelFile']);

        return $builder->pdfResponse($quote);
    }

    // ── Public (client side): the token in the address is the only key ────────

    private function visible(Quote $quote): void
    {
        abort_if($quote->status === Quote::STATUS_DRAFT, 404);
        abort_if($quote->isRevoked(), 410, __('quote.public.revoked'));
    }

    public function publicShow(Quote $quote): View
    {
        $this->visible($quote);
        $quote->load(['printerProfile', 'modelFile']);
        if ($quote->status === Quote::STATUS_SENT) {
            $quote->forceFill(['status' => Quote::STATUS_VIEWED, 'viewed_at' => now()])->save();
        }

        return view('quote.public', ['quote' => $quote, 'profile' => $quote->printerProfile, 'logo' => PrinterController::logoUrl($quote->printerProfile), 'lines' => $quote->customerLines()]);
    }

    public function publicPdf(Quote $quote, QuoteBuilder $builder): Response
    {
        $this->visible($quote);

        return $builder->pdfResponse($quote->load(['printerProfile', 'modelFile']));
    }

    /** The customer accepts exactly the version on their screen; if the printer changed it meanwhile, they are shown the new one first. */
    public function accept(Request $request, Quote $quote): RedirectResponse
    {
        $this->visible($quote);
        abort_unless($quote->isOpen(), 404);
        $data = $request->validate(['version' => ['required', 'integer']]);
        if ((int) $data['version'] !== $quote->version) {
            return redirect()->route('quote.public', $quote)->withErrors(['version' => __('quote.public.changed')]);
        }
        $quote->forceFill(['status' => Quote::STATUS_ACCEPTED, 'accepted_at' => now(), 'accepted_version' => $quote->version])->save();
        $quote->versions()->where('version', $quote->version)->update(['accepted_at' => now(), 'accepted_ip' => $request->ip()]);
        // The printer learns about it by e-mail; the platform steps back from here.
        if ($quote->printerProfile->contact_email) {
            Mail::to($quote->printerProfile->contact_email)->send(new QuoteAccepted($quote));
        }

        return redirect()->route('quote.public', $quote)->with('status', __('quote.accepted_thanks'));
    }

    public function decline(Quote $quote): RedirectResponse
    {
        $this->visible($quote);
        abort_unless($quote->isOpen(), 404);
        $quote->forceFill(['status' => Quote::STATUS_DECLINED, 'declined_at' => now()])->save();

        return redirect()->route('quote.public', $quote)->with('status', __('quote.declined_thanks'));
    }

    /** "Could it be in black and two days sooner?" → the printer edits and sends a new version. */
    public function requestChange(Request $request, Quote $quote): RedirectResponse
    {
        $this->visible($quote);
        abort_unless($quote->isOpen(), 404);
        $data = $request->validate(['version' => ['required', 'integer'], 'message' => ['required', 'string', 'min:3', 'max:1000'], 'website' => ['prohibited']]);
        if ((int) $data['version'] !== $quote->version) {
            return redirect()->route('quote.public', $quote)->withErrors(['version' => __('quote.public.changed')]);
        }
        $quote->forceFill(['status' => Quote::STATUS_CHANGE_REQUESTED, 'change_request' => $data['message'], 'change_requested_at' => now()])->save();
        $quote->versions()->where('version', $quote->version)->update(['change_request' => $data['message']]);
        if ($quote->printerProfile->contact_email) {
            Mail::to($quote->printerProfile->contact_email)->send(new QuoteChangeRequested($quote));
        }

        return redirect()->route('quote.public', $quote)->with('status', __('quote.change_thanks'));
    }
}
