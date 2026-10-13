<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\DesignAgent;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use App\Models\GenerationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Describe what it should look like" on a tool's page: the customer's sentence becomes the values of the form
 * (App\Domain\Tools\DesignAgent). Daily limits per visitor and for the whole site like the print advice; every wish
 * is kept with what came of it (generation_requests, type `design`), so it can be read what customers ask for.
 */
class DesignController extends Controller
{
    /** POST /api/tools/design {tool, text, params} → {params, note, unmet} */
    public function store(Request $request, DesignAgent $agent): JsonResponse
    {
        $tool = is_string($request->input('tool')) ? $request->input('tool') : '';
        abort_unless(DesignAgent::opensFor($request->user(), $tool), 404);
        $data = $request->validate(['text' => ['required', 'string', 'min:3', 'max:'.DesignAgent::MAX_TEXT], 'params' => ['nullable', 'array']]);
        if (! $agent->available()) {
            return response()->json(['error' => 'design_unavailable', 'message' => __('param.design.failed')], 503);
        }

        $ip = $request->ip();
        $session = $request->attributes->get('anon_session');
        $today = GenerationRequest::where('type', 'design')->where('created_at', '>=', now()->startOfDay());
        $mine = (clone $today)->where(fn ($q) => $q->where('ip', $ip)->orWhere('anonymous_session_id', $session?->id)->orWhere('owner_user_id', $request->user()?->id ?? 0))->count();
        $limited = $mine >= (int) config('ai.daily_limits.design', 20) || (clone $today)->count() >= (int) config('ai.daily_limits.design_global', 500);
        if ($limited && ! $request->user()?->isAdmin()) {   // the admin tries the tools without a daily count
            return response()->json(['error' => 'daily_limit', 'message' => __('param.design.limit')], 429);
        }

        $locale = in_array(app()->getLocale(), ['cs', 'en', 'es'], true) ? app()->getLocale() : 'en';
        $req = GenerationRequest::create([
            'token' => Str::lower(Str::random(12)), 'owner_user_id' => $request->user()?->id, 'anonymous_session_id' => $session?->id,
            'ip' => $ip, 'type' => 'design', 'prompt' => $data['text'], 'engine' => 'designer', 'status' => 'running',
            'description' => ['tool' => $tool, 'locale' => $locale],
        ]);
        try {
            $design = $agent->design($tool, (array) ($data['params'] ?? []), $data['text'], $locale, array_filter(['user_id' => $request->user()?->id, 'session_id' => $session?->id]));
        } catch (EngineException|ValidationException $e) {
            $req->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            if ($e instanceof ValidationException) {
                throw $e;      // an answer that is no design of this tool: 422, the form stays as it was
            }
            report($e);

            return response()->json(['error' => 'design_failed', 'message' => __('param.design.failed')], 502);
        }
        $req->update(['status' => 'done', 'description' => ['tool' => $tool, 'locale' => $locale] + $design]);

        return response()->json($design);
    }
}
