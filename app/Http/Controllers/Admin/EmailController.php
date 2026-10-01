<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Mail\Outbox;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use App\Models\OutgoingEmail;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * /admin/emails: e-mails the AI wrote wait here as drafts until an admin approves them (then they are sent) or
 * rejects them. System notifications are listed as sent, for the overview.
 */
class EmailController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['draft', 'approved', 'sent', 'rejected'], true) ? (string) $request->query('status') : 'draft';

        return view('admin.emails.index', [
            'emails' => OutgoingEmail::where('status', $status)->latest('id')->paginate(40)->withQueryString(), 'status' => $status,
            'counts' => OutgoingEmail::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    /** "Write an e-mail with AI": the result is a draft to read, never a sent mail. */
    public function write(Request $request, Outbox $outbox): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email', 'max:190'], 'locale' => ['required', Rule::in(Locales::SUPPORTED)],
            'instruction' => ['required', 'string', 'min:10', 'max:2000'], 'context' => ['nullable', 'string', 'max:8000'],
        ]);
        try {
            $email = $outbox->write($data['to'], $data['instruction'], $data['locale'], $data['context'] ?? null);
        } catch (EngineException $e) {
            return back()->withInput()->with('error', 'Návrh se nepodařilo napsat: '.$e->getMessage());
        }

        return redirect()->route('admin.emails.show', $email->id)->with('status', 'Návrh je připravený. Přečtěte ho, upravte a schvalte, nebo zamítněte.');
    }

    public function show(int $email): View
    {
        return view('admin.emails.show', ['email' => OutgoingEmail::with('approver')->findOrFail($email)]);
    }

    /** Save the admin's changes; with "approve" the saved text is sent. */
    public function update(Request $request, int $email, Outbox $outbox): RedirectResponse
    {
        $email = OutgoingEmail::findOrFail($email);
        if (! in_array($email->status, [OutgoingEmail::STATUS_DRAFT, OutgoingEmail::STATUS_APPROVED], true)) {
            return back()->with('error', 'Tenhle e-mail už nejde měnit.');
        }
        $data = $request->validate(['to' => ['required', 'email', 'max:190'], 'subject' => ['required', 'string', 'max:250'], 'body' => ['required', 'string', 'max:20000']]);
        $email->fill($data)->save();
        if ($request->input('action') === 'reject') {
            $outbox->reject($email, $request->user()->id);

            return redirect()->route('admin.emails.index')->with('status', 'Zamítnuto. E-mail neodešel.');
        }
        if ($request->input('action') === 'approve') {
            $outbox->approve($email, $request->user()->id);

            return $email->status === OutgoingEmail::STATUS_SENT
                ? redirect()->route('admin.emails.index')->with('status', 'Schváleno a odesláno na '.$email->to.'.')
                : back()->with('error', 'Odeslání se nepodařilo: '.$email->error.' E-mail zůstal schválený, zkuste to znovu.');
        }

        return back()->with('status', 'Uloženo.');
    }
}
