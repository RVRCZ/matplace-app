<?php

namespace App\Domain\Generation;

use App\Engines\Contracts\ModelGenerator;
use App\Jobs\GenerateModel;
use App\Models\AnonymousSession;
use App\Models\GenerationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Starts generations with daily quotas (count per day, never money) and reuses results for identical inputs. */
final class GenerationService
{
    public function __construct(private readonly ModelGenerator $generator) {}

    public function enabled(): bool
    {
        return $this->generator->name() !== 'null';
    }

    /** @return array{allowed: bool, reason: ?string, used: int, limit: int} */
    public function quota(?string $ip, ?AnonymousSession $session, ?User $user): array
    {
        $limit = (int) config($user ? 'ai.daily_limits.generate_user' : 'ai.daily_limits.generate_guest');
        $used = GenerationRequest::usedToday($ip, $session?->id, $user?->id);
        $global = GenerationRequest::where('created_at', '>=', now()->startOfDay())->whereIn('type', ['image', 'text'])->whereNotNull('external_id')->count();
        if ($global >= (int) config('ai.daily_limits.generate_global')) {
            return ['allowed' => false, 'reason' => 'global_limit', 'used' => $used, 'limit' => $limit];
        }

        return ['allowed' => $used < $limit, 'reason' => $used < $limit ? null : 'daily_limit', 'used' => $used, 'limit' => $limit];
    }

    public function fromDescribe(GenerationRequest $describe, int $targetMm, ?string $ip, ?AnonymousSession $session, ?User $user): GenerationRequest
    {
        $abs = Storage::disk('local')->path((string) $describe->image_path);
        $sha = is_file($abs) ? hash_file('sha256', $abs) : null;

        $req = $this->make('image', $ip, $session, $user, [
            'image_path' => $describe->image_path,
            'image_sha256' => $sha,
            'prompt' => $describe->description['name_en'] ?? null,
            'description' => $describe->description,
            'target_mm' => $targetMm,
            'source_request_id' => $describe->id,
        ]);

        return $this->reuseOrDispatch($req, fn ($q) => $q->where('type', 'image')->where('image_sha256', $sha));
    }

    /**
     * Figure / bust from a personal photo. No result sharing between visitors (no cache key) and the photo is
     * deleted as soon as the generation ends; consent is recorded with the request.
     */
    public function fromPhoto(string $imageRelPath, string $kind, int $targetMm, ?string $ip, ?AnonymousSession $session, ?User $user): GenerationRequest
    {
        $req = $this->make('image', $ip, $session, $user, [
            'image_path' => $imageRelPath,
            'image_sha256' => null,
            'prompt' => $kind,
            'description' => ['kind' => $kind, 'name_en' => $kind === 'bust' ? 'bust' : 'figure', 'delete_photo' => true, 'consent_at' => now()->toIso8601String()],
            'target_mm' => $targetMm,
        ]);

        return $this->reuseOrDispatch($req, fn ($q) => $q->whereRaw('1 = 0'));
    }

    public function fromText(string $prompt, int $targetMm, ?string $ip, ?AnonymousSession $session, ?User $user): GenerationRequest
    {
        $prompt = trim($prompt);
        $req = $this->make('text', $ip, $session, $user, ['prompt' => $prompt, 'image_sha256' => hash('sha256', 'text:'.mb_strtolower($prompt)), 'target_mm' => $targetMm]);

        return $this->reuseOrDispatch($req, fn ($q) => $q->where('type', 'text')->where('image_sha256', $req->image_sha256));
    }

    private function make(string $type, ?string $ip, ?AnonymousSession $session, ?User $user, array $attrs): GenerationRequest
    {
        return new GenerationRequest([
            'token' => Str::lower(Str::random(12)), 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id, 'ip' => $ip,
            'type' => $type, 'engine' => $this->generator->name(), 'status' => 'queued', 'progress' => 0,
        ] + $attrs);
    }

    /** Identical input already generated → copy the result (free, instant). Otherwise queue the job. */
    private function reuseOrDispatch(GenerationRequest $req, \Closure $sameInput): GenerationRequest
    {
        $prev = $req->image_sha256
            ? $sameInput(GenerationRequest::query())->where('status', 'done')->whereNotNull('result_model_file_id')->latest('id')->first()
            : null;
        if ($prev && $prev->target_mm === $req->target_mm) {
            $req->fill(['status' => 'done', 'progress' => 100, 'result_model_file_id' => $prev->result_model_file_id, 'cost_cents' => 0, 'engine' => preg_replace('/\+cache$/', '', (string) $prev->engine).'+cache']);
            $req->save();

            return $req;
        }
        // only real (paid) generations are subject to the daily quota
        $quota = $this->quota($req->ip, $req->anonymous_session_id ? AnonymousSession::find($req->anonymous_session_id) : null, $req->owner_user_id ? User::find($req->owner_user_id) : null);
        if (! $quota['allowed']) {
            throw new QuotaExceeded($quota['reason'] ?? 'daily_limit', $quota['limit']);
        }
        $req->save();
        GenerateModel::dispatch($req->id);

        return $req->refresh();
    }
}
