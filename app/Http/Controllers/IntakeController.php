<?php

namespace App\Http\Controllers;

use App\Domain\Intake\IntakePage;
use App\Domain\Intake\IntakeService;
use App\Models\Intake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The home page's router (TODO #78, docs/AN.md §22): the form posts a photo, a 3D file or words and gets the decision
 * back as JSON; a tool's page fetches the photo by the token; "a person takes it over" leaves an e-mail.
 */
class IntakeController extends Controller
{
    /** POST /intake: multipart photo | model | text (at least one). 201 with the decision, 422 with a sentence. */
    public function store(Request $request, IntakeService $service): JsonResponse
    {
        $intake = $service->create($request);

        return response()->json($service->describe($intake), 201);
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

    /** POST /intake/{token}/reroute: "try it now without a designer", the decision made again without a person (round 2). 200 with the new decision. */
    public function reroute(Intake $intake, IntakeService $service): JsonResponse
    {
        return response()->json($service->describe($service->reroute($intake)));
    }

    /** POST /intake/{token}/request {email, consent, name?, phone?}: a person takes it over. */
    public function request(Request $request, Intake $intake, IntakeService $service): JsonResponse
    {
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
