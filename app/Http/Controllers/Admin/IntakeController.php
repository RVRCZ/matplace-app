<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Intake\IntakeHints;
use App\Domain\Intake\IntakeService;
use App\Http\Controllers\Controller;
use App\Mail\PlainMessage;
use App\Models\Intake;
use App\Models\IntakeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * /admin/intake (TODO #78, docs/AN.md §22 E): what the home page's router took in, newest first; the requests a person
 * has to answer on top. The detail shows the photo, the words, the assistant's decision and its cost, and takes the
 * reply: one mail to the customer, written here.
 */
class IntakeController extends Controller
{
    public function index(Request $request): View
    {
        $filter = in_array($request->query('show'), ['requests', 'all'], true) ? $request->query('show') : 'requests';
        // what the rules against advertising held stays out of both lists until "show spam too" is on (docs/AN.md §23.2)
        $spam = $request->boolean('spam');
        $query = Intake::query()->with(['request', 'modelFile'])->orderByDesc('id');
        if ($filter === 'requests') {
            $query->whereHas('request');
        }
        if (! $spam) {
            $query->where('status', '!=', Intake::STATUS_SPAM);
        }

        return view('admin.intake.index', [
            'intakes' => $query->paginate(50)->withQueryString(),
            'filter' => $filter,
            'spam' => $spam,
            'held' => Intake::where('status', Intake::STATUS_SPAM)->count(),
            'open' => IntakeRequest::where('status', IntakeRequest::STATUS_NEW)->whereHas('intake', fn ($q) => $q->where('status', '!=', Intake::STATUS_SPAM))->count(),
        ]);
    }

    /** POST /admin/intake/{token}/spam {spam: 1|0}: held as advertising by hand, or given back (then decided by the model after all). */
    public function spam(Request $request, Intake $intake, IntakeService $service): RedirectResponse
    {
        $service->markSpam($intake, $request->boolean('spam'));

        return redirect()->route('admin.intake.show', $intake)->with('status', __('farm.admin.saved'));
    }

    /** GET /admin/intake/hints: the calendar of the examples under the first screen's field (docs/AN.md §23.3). */
    public function hints(): View
    {
        return view('admin.intake.hints', [
            'events' => IntakeHints::table(),
            'now' => ['cs' => IntakeHints::chips('cs'), 'en' => IntakeHints::chips('en'), 'es' => IntakeHints::chips('es')],
        ]);
    }

    /** POST /admin/intake/hints: per event on/off, the dates (D.M.), the examples per language (lines "label | wish"); empty = as the code says. */
    public function saveHints(Request $request): RedirectResponse
    {
        $keep = [];
        foreach (array_keys(IntakeHints::events()) as $key) {
            $in = (array) $request->input('events.'.$key, []);
            [$one, $error] = IntakeHints::parse($key, ['off' => empty($in['on']), 'from' => $in['from'] ?? '', 'to' => $in['to'] ?? '', 'texts' => (array) ($in['texts'] ?? [])]);
            if ($error !== null) {
                return back()->withInput()->withErrors(['events' => (IntakeHints::events()[$key]['name'] ?? $key).': '.$error]);
            }
            if ($one) {
                $keep[$key] = $one;
            }
        }
        IntakeHints::save($keep);

        return redirect()->route('admin.intake.hints')->with('status', __('farm.admin.saved'));
    }

    public function show(Intake $intake): View
    {
        return view('admin.intake.show', ['intake' => $intake->load(['request.repliedBy', 'modelFile', 'user'])]);
    }

    /** The picture of an intake, for the admin's eyes (the public address needs the token only; this one the role). */
    public function photo(Intake $intake): BinaryFileResponse
    {
        abort_unless($intake->hasPhoto(), 404);
        $path = Storage::disk(IntakeService::DISK)->path((string) $intake->picturePath());
        abort_unless(is_file($path), 404);

        return response()->file($path, ['X-Robots-Tag' => 'noindex, nofollow'])->setPrivate()->setMaxAge(0);
    }

    /** The operator's answer: one plain mail to the customer in their language, the request marked as answered. */
    public function reply(Request $request, Intake $intake): RedirectResponse
    {
        $made = $intake->request;
        abort_unless($made, 404);
        $data = $request->validate(['subject' => ['required', 'string', 'max:150'], 'text' => ['required', 'string', 'max:5000']]);
        Mail::to($made->email)->locale($made->locale)->queue(new PlainMessage($data['subject'], $data['text']));
        $made->forceFill(['reply' => $data['text'], 'replied_at' => now(), 'replied_by' => $request->user()->id, 'status' => IntakeRequest::STATUS_ANSWERED])->save();

        return redirect()->route('admin.intake.show', $intake)->with('status', __('farm.admin.saved'));
    }

    /** Closed by hand (done, no answer needed) or opened again. */
    public function status(Request $request, Intake $intake): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', [IntakeRequest::STATUS_NEW, IntakeRequest::STATUS_ANSWERED, IntakeRequest::STATUS_CLOSED])]]);
        if ($made = $intake->request) {
            $made->forceFill(['status' => $data['status']])->save();
        }
        $intake->forceFill(['status' => $data['status'] === IntakeRequest::STATUS_CLOSED ? Intake::STATUS_CLOSED : Intake::STATUS_HUMAN])->save();

        return redirect()->route('admin.intake.show', $intake)->with('status', __('farm.admin.saved'));
    }
}
