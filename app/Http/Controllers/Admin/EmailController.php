<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\OrderPictures;
use App\Domain\Mail\MailboxWatch;
use App\Domain\Mail\MailText;
use App\Domain\Mail\Outbox;
use App\Domain\Mail\ReplyContext;
use App\Engines\Exceptions\EngineException;
use App\Engines\Mail\Mailbox;
use App\Engines\Mail\MailboxFailed;
use App\Http\Controllers\Controller;
use App\Models\FarmOrder;
use App\Models\OutgoingEmail;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        // the orders this e-mail is about: named in the message it answers, else the recipient's own (docs/AU.md §8)
        $orders = ReplyContext::orders($email->to, (string) ($original['subject'] ?? $email->subject), (string) ($original['text'] ?? $email->body));
        $pictures = $email->status === OutgoingEmail::STATUS_DRAFT || $email->status === OutgoingEmail::STATUS_APPROVED
            ? $orders->mapWithKeys(fn ($o) => [$o->id => app(OrderPictures::class)->all($o, render: false)])->all() : [];

        return view('admin.emails.show', ['email' => $email, 'original' => $original, 'orders' => $orders, 'pictures' => $pictures, 'kinds' => OrderPictures::KINDS]);
    }

    /** A file attached to a draft or a sent e-mail, for the admin's eyes. */
    public function file(int $email, string $name): BinaryFileResponse
    {
        $email = OutgoingEmail::findOrFail($email);
        $file = collect($email->files())->firstWhere('name', $name);
        abort_unless($file, 404);

        return response()->file($file['path'], ['Content-Type' => $file['mime'], 'Content-Disposition' => 'inline', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    /** A kept picture of an order (OrderPictures), to look at before attaching it. */
    public function orderPicture(FarmOrder $order, string $kind): BinaryFileResponse
    {
        abort_unless(isset(OrderPictures::KINDS[$kind]), 404);
        $path = app(OrderPictures::class)->all($order, render: false)[$kind] ?? null;
        abort_unless($path, 404);

        return response()->file($path, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=600']);
    }

    /**
     * What the admin attached, took off or asked for: order pictures (`pictures[]` = "order:kind"), files from the
     * computer (`files[]`), names to remove (`remove[]`). Kept in the draft's own folder; the e-mail lists them.
     */
    private function saveFiles(Request $request, OutgoingEmail $email): ?string
    {
        $request->validate(['files' => ['nullable', 'array', 'max:'.OutgoingEmail::MAX_FILES], 'files.*' => ['file', 'max:'.OutgoingEmail::MAX_FILE_KB, 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf'],
            'pictures' => ['nullable', 'array'], 'pictures.*' => ['string', 'regex:/^\d+:[a-z]+$/'], 'remove' => ['nullable', 'array'], 'remove.*' => ['string', 'max:200']]);
        $disk = Storage::disk(OutgoingEmail::FILES_DISK);
        $list = collect((array) $email->attachments)->keyBy('name');
        foreach ((array) $request->input('remove', []) as $name) {
            $disk->delete($email->filesDir().'/'.basename((string) $name));
            $list->forget(basename((string) $name));
        }
        $add = function (string $name, string $mime, string $from) use ($disk, $email, $list): void {
            $name = Str::limit(trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($name)), '-') ?: 'priloha', 120, '');
            $disk->makeDirectory($email->filesDir());
            copy($from, $disk->path($email->filesDir().'/'.$name));
            $list->put($name, ['name' => $name, 'mime' => $mime]);
        };
        foreach ((array) $request->input('pictures', []) as $pick) {
            [$orderId, $kind] = explode(':', (string) $pick);
            $order = FarmOrder::find((int) $orderId);
            $path = $order && isset(OrderPictures::KINDS[$kind]) ? (app(OrderPictures::class)->all($order, render: false)[$kind] ?? null) : null;
            if ($path) {
                $add(($order->number ?: 'zakazka').'-'.['model' => 'model', 'source' => 'predloha', 'snapshot' => 'tiskarna', 'photo' => 'vytisk'][$kind].'.jpg', 'image/jpeg', $path);
            }
        }
        foreach ((array) $request->file('files', []) as $upload) {
            $add((string) $upload->getClientOriginalName(), (string) $upload->getMimeType(), $upload->getRealPath());
        }
        if ($list->count() > OutgoingEmail::MAX_FILES) {
            return 'Nejvýš '.OutgoingEmail::MAX_FILES.' příloh.';
        }
        $email->forceFill(['attachments' => $list->values()->all() ?: null])->save();

        return null;
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
        if ($problem = $this->saveFiles($request, $email)) {
            return back()->with('error', $problem);
        }
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
