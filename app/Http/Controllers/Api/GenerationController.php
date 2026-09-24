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
            'describe' => ['nullable', 'string', 'max:16', 'required_without_all:prompt,image'],
            'prompt' => ['nullable', 'string', 'min:3', 'max:500', 'required_without_all:describe,image'],
            'image' => ['nullable', 'image', 'max:12288'],
            // more sides of the same subject (optional): the model then matches the likeness from all of them
            'image_left' => ['nullable', 'image', 'max:12288'],
            'image_back' => ['nullable', 'image', 'max:12288'],
            'image_right' => ['nullable', 'image', 'max:12288'],
            'kind' => ['nullable', 'in:bust,figure', 'required_with:image'],
            'pedestal' => ['nullable', 'in:round,square,hexagon,column,plaque,none'],
            'pedestal_name' => ['nullable', 'string', 'max:24'],
            'pedestal_dedication' => ['nullable', 'string', 'max:40'],
            'target_mm' => ['nullable', 'integer', 'min:5', 'max:1000'],
            'consent' => $request->hasFile('image') ? ['accepted'] : ['nullable'],
        ]);
        $session = $request->attributes->get('anon_session');
        $user = $request->user();

        try {
            if ($request->hasFile('image')) {
                // every photo is stored and moderated; one rejected side rejects the whole request
                $disk = \Illuminate\Support\Facades\Storage::disk('local');
                $stored = [];
                foreach (['front' => 'image', 'left' => 'image_left', 'back' => 'image_back', 'right' => 'image_right'] as $view => $field) {
                    if (! $request->hasFile($field)) {
                        continue;
                    }
                    $rel = 'photos/figures/'.\Illuminate\Support\Str::uuid().'.'.(strtolower($request->file($field)->getClientOriginalExtension()) ?: 'jpg');
                    $disk->put($rel, file_get_contents($request->file($field)->getRealPath()));
                    $stored[$view] = $rel;
                    $check = app(\App\Engines\Vision\VisionDescriber::class)->moderate($disk->path($rel));
                    if (! $check['ok']) {
                        $disk->delete(array_values($stored));

                        return response()->json(['error' => 'photo_rejected', 'reason' => $check['reason'], 'view' => $view], 422);
                    }
                }
                try {
                    $req = $service->fromPhoto($stored['front'], $data['kind'], (int) ($data['target_mm'] ?? config('ai.default_target_mm', 80)), $request->ip(), $session, $user, [
                        'type' => $data['pedestal'] ?? 'round',
                        'name' => ($data['pedestal'] ?? '') === 'plaque' ? ($data['pedestal_name'] ?? null) : null,
                        'dedication' => ($data['pedestal'] ?? '') === 'plaque' ? ($data['pedestal_dedication'] ?? null) : null,
                    ], array_diff_key($stored, ['front' => 1]));
                } catch (QuotaExceeded $e) {
                    $disk->delete(array_values($stored));
                    throw $e;
                }
            } elseif (! empty($data['describe'])) {
                $describe = GenerationRequest::where('token', $data['describe'])->where('type', 'describe')->where('status', 'done')->firstOrFail();
                $bbox = $describe->description['bbox_mm'] ?? null;
                $target = (int) ($data['target_mm'] ?? ($bbox ? max($bbox['x'], $bbox['y'], $bbox['z']) : config('ai.default_target_mm', 80)));
                $req = $service->fromDescribe($describe, max(5, $target), $request->ip(), $session, $user);
            } else {
                $req = $service->fromText($data['prompt'], (int) ($data['target_mm'] ?? config('ai.default_target_mm', 80)), $request->ip(), $session, $user);
            }
        } catch (QuotaExceeded $e) {
            return response()->json(['error' => $e->reason, 'limit' => $e->limit, 'login_limit' => (int) config('ai.daily_limits.generate_user'), 'price' => $e->price, 'missing' => $e->missing, 'topup_url' => $request->user() ? route('account.credit', ['need' => (int) ceil($e->missing)]) : null], 429);
        }

        return response()->json(['generation' => self::describe($req)], 201);
    }

    /** POST /api/generate/{token}/refine {instruction} → a new generation: the same subject changed in words */
    public function refine(Request $request, GenerationRequest $generation, GenerationService $service): JsonResponse
    {
        abort_unless(in_array($generation->type, ['image', 'text'], true), 404);
        if (! $service->enabled()) {
            return response()->json(['error' => 'generator_unavailable'], 503);
        }
        $data = $request->validate(['instruction' => ['required', 'string', 'min:3', 'max:300']]);
        try {
            $req = $service->refine($generation, $data['instruction'], $request->ip(), $request->attributes->get('anon_session'), $request->user());
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'not_refinable'], 422);
        } catch (QuotaExceeded $e) {
            return response()->json(['error' => $e->reason, 'limit' => $e->limit, 'login_limit' => (int) config('ai.daily_limits.generate_user'), 'price' => $e->price, 'missing' => $e->missing, 'topup_url' => $request->user() ? route('account.credit', ['need' => (int) ceil($e->missing)]) : null], 429);
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
