<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\PrintAdvisor;
use App\Http\Controllers\Controller;
use App\Jobs\GiveModelAdvice;
use App\Models\Calculation;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "How do I print this?" in the calculator's model check: one request per model, language and chosen settings is
 * enough (the same question never costs twice); daily limits per visitor and for the whole site like photo recognition.
 */
class AdviceController extends Controller
{
    /** POST /api/files/{uuid}/advice {calculation?} → {token, status} */
    public function store(Request $request, ModelFile $modelFile, PrintAdvisor $advisor): JsonResponse
    {
        abort_unless($modelFile->isReady(), 404);
        if (! $advisor->available()) {
            return response()->json(['error' => 'advice_unavailable'], 503);
        }
        $locale = in_array(app()->getLocale(), ['cs', 'en', 'es'], true) ? app()->getLocale() : 'en';
        $context = $this->context($modelFile, (string) $request->input('calculation'));
        $key = 'advice:'.$locale.':'.substr(md5(json_encode($context['chosen'] ?? [])), 0, 12);

        $same = GenerationRequest::where('type', 'advise')->where('result_model_file_id', $modelFile->id)->where('prompt', $key)
            ->whereIn('status', ['running', 'done'])->latest('id')->first();
        if ($same && ($same->status === 'done' || $same->created_at->gt(now()->subMinutes(15)))) {
            return response()->json($this->state($same));
        }

        $ip = $request->ip();
        $session = $request->attributes->get('anon_session');
        $today = GenerationRequest::where('type', 'advise')->where('created_at', '>=', now()->startOfDay());
        $mine = (clone $today)->where(fn ($q) => $q->where('ip', $ip)->orWhere('anonymous_session_id', $session?->id)->orWhere('owner_user_id', $request->user()?->id ?? 0))->count();
        if ($mine >= (int) config('ai.daily_limits.advise', 10) || (clone $today)->count() >= (int) config('ai.daily_limits.advise_global', 300)) {
            return response()->json(['error' => 'daily_limit'], 429);
        }

        $req = GenerationRequest::create([
            'token' => Str::lower(Str::random(12)), 'owner_user_id' => $request->user()?->id, 'anonymous_session_id' => $session?->id,
            'ip' => $ip, 'type' => 'advise', 'prompt' => $key, 'engine' => 'advisor', 'status' => 'running',
            'result_model_file_id' => $modelFile->id, 'description' => ['locale' => $locale, 'context' => $context],
        ]);
        GiveModelAdvice::dispatch($req->id);

        return response()->json($this->state($req->fresh()), 202);
    }

    /** GET /api/advice/{token} */
    public function show(string $token): JsonResponse
    {
        $req = GenerationRequest::where('type', 'advise')->where('token', $token)->firstOrFail();

        return response()->json($this->state($req));
    }

    private function state(GenerationRequest $req): array
    {
        $advice = (array) ($req->description['advice'] ?? []);

        return [
            'token' => $req->token,
            'status' => $req->status,
            'advice' => $req->status === 'done' ? ['summary' => $advice['summary'] ?? '', 'items' => $advice['items'] ?? []] : null,
        ];
    }

    /** What the customer chose in the calculator and what the slicer made of it; only for a calculation of this file. */
    private function context(ModelFile $file, string $token): array
    {
        $calc = $token !== '' ? Calculation::where('token', $token)->where('model_file_id', $file->id)->first() : null;
        if (! $calc) {
            return ['kind' => $file->kind()];
        }
        $p = (array) $calc->params;
        $s = (array) $calc->slicer;

        return [
            'kind' => $file->kind(),
            'chosen' => array_intersect_key($p, array_flip(['material', 'quality', 'infill', 'supports', 'scale', 'vase', 'tree'])),
            'slicer' => array_intersect_key($s, array_flip(['grams', 'minutes', 'layers', 'supports_used', 'warnings', 'dims'])),
        ];
    }
}
