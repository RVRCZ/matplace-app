<?php

namespace App\Http\Controllers;

use App\Mail\ModelLink;
use App\Models\ModelFile;
use App\Models\ModelLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * "Send me my bust" at a finished model from a photo (App\Models\ModelLead): the visitor's own generated model only; the
 * link goes out at once, the address stays, a reminder only when ticked. And the unsubscribe link of those mails.
 */
class ModelLeadController extends Controller
{
    /** POST /api/files/{modelFile}/keep {email, remind} */
    public function store(Request $request, ModelFile $modelFile): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190'], 'remind' => ['nullable', 'boolean']]);
        $session = $request->attributes->get('anon_session');
        $mine = ($request->user() && $modelFile->owner_user_id === $request->user()->id)
            || ($modelFile->owner_user_id === null && $session && $modelFile->anonymous_session_id === $session->id);
        abort_unless($mine && in_array($modelFile->origin, ['generated', 'paint'], true), 404);
        $remind = $request->boolean('remind');
        $lead = ModelLead::where('email', $data['email'])->where('model_file_id', $modelFile->id)->first()
            ?? new ModelLead(['email' => $data['email'], 'model_file_id' => $modelFile->id]);
        $lead->fill([
            'locale' => app()->getLocale(), 'anonymous_session_id' => $session?->id, 'ip' => $request->ip(),
            'remind' => $remind, 'consent_at' => $remind ? now() : null, 'unsubscribed_at' => null, 'sent_at' => now(),
        ])->save();
        Mail::to($lead->email)->locale($lead->locale)->queue((new ModelLink($lead))->locale($lead->locale));

        return response()->json(['ok' => true]);
    }

    /** GET /keep/off/{token}: no more mails about this model. */
    public function unsubscribe(string $token): View
    {
        $lead = ModelLead::where('token', $token)->firstOrFail();
        $lead->forceFill(['remind' => false, 'unsubscribed_at' => $lead->unsubscribed_at ?? now()])->save();

        return view('keep_unsubscribed');
    }
}
