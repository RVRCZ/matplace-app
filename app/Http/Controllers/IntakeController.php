<?php

namespace App\Http\Controllers;

use App\Domain\Intake\IntakePage;
use App\Domain\Intake\IntakeService;
use App\Models\Intake;
use App\Support\Bots;
use App\Support\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The home page's router (TODO #78, docs/AN.md §22): the form posts a photo, a 3D file or words and gets the decision
 * back as JSON; a tool's page fetches the photo by the token; "a person takes it over" leaves an e-mail.
 */
class IntakeController extends Controller
{
    /** What POST /intake/event may say; the event is `intake_<type>`. */
    public const EVENTS = ['view', 'chip', 'photo', 'voice', 'again'];

    /** POST /intake: multipart photo | model | text (at least one). 201 with the decision, 422 with a sentence. */
    public function store(Request $request, IntakeService $service): JsonResponse
    {
        $intake = $service->create($request);

        return response()->json($service->describe($intake), 201);
    }

    /**
     * POST /intake/event {type, code?}: what a visitor did on the first screen before sending (docs/AN.md §23.4):
     * `view` the page was shown, `chip` an example was clicked (code = which), `photo` a picture was added, `voice`
     * dictation was used, `again` the visitor came back from a tool or a design with "I wanted something else" (§24). Numbers only; the staff's browsers and robots are left out by Track itself. Always 204.
     */
    public function event(Request $request): Response
    {
        $type = (string) $request->input('type');
        $code = (string) $request->input('code');
        if (in_array($type, self::EVENTS, true) && ! Bots::is($request)) {
            Track::event('intake_'.$type, null, $type === 'chip' && preg_match('/^[a-z_]{1,24}\.[1-9]$/', $code) ? ['code' => $code] : [], 'intake');
        }

        return response()->noContent();
    }

    /** GET /intake/{token}/file: the picture for the tool's page (the normalised JPEG), only by the token, never indexed. */
    public function file(Intake $intake): BinaryFileResponse
    {
        abort_unless($intake->hasPhoto(), 404);
        $path = Storage::disk(IntakeService::DISK)->path((string) $intake->picturePath());
        abort_unless(is_file($path), 404);

        // a file response is public by default (Symfony): this picture is the visitor's own, no shared cache keeps it
        return response()->file($path, ['X-Robots-Tag' => 'noindex, nofollow', 'Content-Disposition' => 'inline; filename="photo.jpg"'])->setPrivate()->setMaxAge(600);
    }

    /**
     * GET /intake/{token}: the wish once more, for the way back from a tool or a design ("I wanted something else",
     * docs/AN.md §24): the words, the other tools that would do, the free design and the request to a person. Never indexed.
     */
    public function show(Intake $intake, IntakeService $service): JsonResponse
    {
        abort_if($intake->status === Intake::STATUS_SPAM, 404);

        return response()->json($service->describe($intake, true))->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** POST /intake/{token}/design: "try a free design", the generator started by the visitor's own press. 200 with the decision (its `go` opens the design), 422 with the sentence why not. */
    public function design(Request $request, Intake $intake, IntakeService $service): JsonResponse
    {
        return response()->json($service->describe($service->startDesign($intake, $request)));
    }

    /** POST /intake/{token}/reroute: "try it now without a designer", the decision made again without a person (round 2). 200 with the new decision. */
    public function reroute(Intake $intake, IntakeService $service): JsonResponse
    {
        return response()->json($service->describe($service->reroute($intake)));
    }

    /** POST /intake/{token}/request {email, consent, name?, phone?}: a person takes it over. */
    public function request(Request $request, Intake $intake, IntakeService $service): JsonResponse
    {
        abort_if($intake->status === Intake::STATUS_SPAM, 404);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'consent' => ['accepted'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ], ['email.required' => __('router.request.need_email'), 'email.email' => __('router.request.need_email'), 'consent.accepted' => __('router.request.need_consent')]);
        $made = $service->requestHuman($intake, $data);

        return response()->json(['ok' => true, 'message' => __('router.request.sent', ['hours' => IntakePage::hours(), 'email' => $made->email])]);
    }
}
