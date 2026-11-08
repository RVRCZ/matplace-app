<?php

namespace App\Http\Controllers\Admin;

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
        $query = Intake::query()->with(['request', 'modelFile'])->orderByDesc('id');
        if ($filter === 'requests') {
            $query->whereHas('request');
        }

        return view('admin.intake.index', [
            'intakes' => $query->paginate(50)->withQueryString(),
            'filter' => $filter,
            'open' => IntakeRequest::where('status', IntakeRequest::STATUS_NEW)->count(),
        ]);
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
