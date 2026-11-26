<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Mail\MailboxWatch;
use App\Domain\Mail\MailText;
use App\Domain\Mail\Outbox;
use App\Domain\Mail\ReplyContext;
use App\Engines\Exceptions\EngineException;
use App\Engines\Mail\Mailbox;
use App\Engines\Mail\MailboxFailed;
use App\Http\Controllers\Controller;
use App\Models\OutgoingEmail;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /** The shared mailbox: what came in, unread first. */
    public function inbox(Request $request, Mailbox $mailbox): View
    {
        $all = $request->query('all') === '1';
        $auto = $request->query('auto') === '1';
        $messages = [];
        $error = null;
        if ($mailbox->available()) {
            try {
                $messages = $mailbox->recent(40, ! $all);
            } catch (MailboxFailed $e) {
                $error = $e->getMessage();
            }
        }
        // robots, reports and our own notifications are folded away unless asked for (the watch, docs/AU.md)
        $own = app(MailboxWatch::class)->ownAddresses();
        $human = array_values(array_filter($messages, fn (array $m) => MailboxWatch::kind($m['from'], $m['subject'], $own) === MailboxWatch::HUMAN));
        $robots = count($messages) - count($human);
        if (! $auto) {
            $messages = $human;
        }
        // what already has a draft or a reply: the admin sees it next to the message
        $answered = OutgoingEmail::whereIn('inbox_message_id', array_column($messages, 'id'))->get()->groupBy('inbox_message_id');

        return view('admin.emails.inbox', ['messages' => $messages, 'all' => $all, 'auto' => $auto, 'robots' => $robots, 'error' => $error, 'available' => $mailbox->available(), 'address' => $mailbox->address(), 'answered' => $answered]);
    }

    /** One message of the mailbox with its text, and the drafts written for it. */
    public function inboxShow(string $id, Mailbox $mailbox): View|RedirectResponse
    {
        try {
            $message = $mailbox->message($id);
        } catch (MailboxFailed $e) {
            return redirect()->route('admin.emails.inbox')->with('error', $e->getMessage());
        }
        abort_unless($message, 404);

        return view('admin.emails.inbox_show', ['message' => $message, 'drafts' => OutgoingEmail::where('inbox_message_id', $id)->latest('id')->get(),
            'orders' => ReplyContext::orders($message['from'], $message['subject'], $message['text']), 'locale' => MailText::locale((string) (($message['fresh'] ?? '') ?: $message['text']))]);
    }

    /** One attached file of a message: pictures and PDFs open in the browser, anything else downloads. */
    public function attachment(string $id, string $file, Mailbox $mailbox): Response
    {
        try {
            $message = $mailbox->message($id);
        } catch (MailboxFailed) {
            abort(502);
        }
        $meta = collect($message['attachments'] ?? [])->firstWhere('id', $file);
        abort_unless($message && $meta, 404);
        $bytes = $mailbox->attachment($id, $file);
        abort_if($bytes === null, 404);
        $inline = in_array($meta['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], true);
        $name = str_replace(['"', "\r", "\n"], '', (string) $meta['filename']);

        return response($bytes, 200, [
            'Content-Type' => $inline ? $meta['mime'] : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** "Suggest a reply": the assistant drafts it, the admin reads, changes and approves (then it goes in the thread). */
    public function reply(Request $request, string $id, Mailbox $mailbox, Outbox $outbox): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(Locales::SUPPORTED)], 'instruction' => ['nullable', 'string', 'max:2000']]);
        try {
            $message = $mailbox->message($id);
            abort_unless($message, 404);
            $draft = $outbox->reply($message, $data['locale'], $data['instruction'] ?? null);
        } catch (EngineException $e) {
            return back()->withInput()->with('error', 'Návrh se nepodařilo napsat: '.$e->getMessage());
        }

        return redirect()->route('admin.emails.show', $draft->id)->with('status', 'Návrh odpovědi je připravený. Přečtěte ho, upravte a schvalte; odejde jako odpověď ve vlákně.');
    }

    public function markRead(string $id, Mailbox $mailbox): RedirectResponse
    {
        try {
            $mailbox->markRead($id);
        } catch (MailboxFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.emails.inbox')->with('status', 'Označeno jako přečtené.');
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

    public function show(int $email, Mailbox $mailbox): View
    {
        $email = OutgoingEmail::with('approver')->findOrFail($email);
        $original = null;
        if ($email->isReply()) {
            try {
                $original = $mailbox->message((string) $email->inbox_message_id);
            } catch (MailboxFailed) {
                $original = null;   // the reply can still be read and sent; only the original is not shown
            }
        }

        return view('admin.emails.show', ['email' => $email, 'original' => $original]);
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
