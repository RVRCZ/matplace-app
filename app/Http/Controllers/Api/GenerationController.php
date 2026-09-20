<?php

namespace App\Http\Controllers\Api;

use App\Domain\Generation\GenerationService;
use App\Domain\Generation\QuotaExceeded;
use App\Http\Controllers\Controller;
use App\Models\GenerationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Rough 3D model from a photo (after /api/describe) or from text. Free, limited per day by count. */
class GenerationController extends Controller
{
    /** POST /api/generate {describe: token, target_mm?} | {prompt, target_mm?} */
    public function store(Request $request, GenerationService $service): JsonResponse
    {
        if (! $service->enabled()) {
            return response()->json(['error' => 'generator_unavailable'], 503);
        }
        $data = $request->validate([
            'describe' => ['nullable', 'string', 'max:16', 'required_without:prompt'],
            'prompt' => ['nullable', 'string', 'min:3', 'max:500', 'required_without:describe'],
            'target_mm' => ['nullable', 'integer', 'min:5', 'max:1000'],
            'consent' => ['nullable', 'boolean'],
        ]);
        $session = $request->attributes->get('anon_session');
        $user = $request->user();

        try {
            if (! empty($data['describe'])) {
                $describe = GenerationRequest::where('token', $data['describe'])->where('type', 'describe')->where('status', 'done')->firstOrFail();
                $bbox = $describe->description['bbox_mm'] ?? null;
                $target = (int) ($data['target_mm'] ?? ($bbox ? max($bbox['x'], $bbox['y'], $bbox['z']) : config('ai.default_target_mm', 80)));
                $req = $service->fromDescribe($describe, max(5, $target), $request->ip(), $session, $user);
            } else {
                $req = $service->fromText($data['prompt'], (int) ($data['target_mm'] ?? config('ai.default_target_mm', 80)), $request->ip(), $session, $user);
            }
        } catch (QuotaExceeded $e) {
            return response()->json(['error' => $e->reason, 'limit' => $e->limit, 'login_limit' => (int) config('ai.daily_limits.generate_user')], 429);
        }

        return response()->json(['generation' => self::describe($req)], 201);
    }

    /** GET /api/generate/{token} */
    public function show(GenerationRequest $generation): JsonResponse
    {
        abort_unless(in_array($generation->type, ['image', 'text'], true), 404);

        return response()->json(['generation' => self::describe($generation)]);
    }

    public static function describe(GenerationRequest $g): array
    {
        $file = $g->result_model_file_id ? $g->resultFile : null;

        return [
            'token' => $g->token,
            'status' => $g->status,
            'progress' => (int) $g->progress,
            'error' => $g->status === 'failed' ? ($g->error ?: 'failed') : null,
            'target_mm' => $g->target_mm,
            'file' => $file ? UploadController::describe($file) : null,
        ];
    }
}
