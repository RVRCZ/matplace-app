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
        $limit = $this->limitFor($user);
        $used = GenerationRequest::usedToday($ip, $session?->id, $user?->id);
        $global = GenerationRequest::where('created_at', '>=', now()->startOfDay())->whereIn('type', ['image', 'text'])->whereNotNull('external_id')->count();
        if ($global >= (int) config('ai.daily_limits.generate_global')) {
            return ['allowed' => false, 'reason' => 'global_limit', 'used' => $used, 'limit' => $limit];
        }

        return ['allowed' => $used < $limit, 'reason' => $used < $limit ? null : 'daily_limit', 'used' => $used, 'limit' => $limit];
    }

    /** Daily count of generations: guest < account < printer. */
    public function limitFor(?User $user): int
    {
        return (int) config(match (true) {
            $user === null => 'ai.daily_limits.generate_guest',
            $user->isPrinter() => 'ai.daily_limits.generate_printer',
            default => 'ai.daily_limits.generate_user',
        });
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
     *
     * @param  array<string, string>  $views  extra sides of the same subject (left|back|right → relative path)
     */
    public function fromPhoto(string $imageRelPath, string $kind, int $targetMm, ?string $ip, ?AnonymousSession $session, ?User $user, array $pedestal = [], array $views = []): GenerationRequest
    {
        $req = $this->make('image', $ip, $session, $user, [
            'image_path' => $imageRelPath,
            'views' => array_filter(array_intersect_key($views, array_flip(['left', 'back', 'right']))) ?: null,
            'image_sha256' => null,
            'prompt' => $kind,
            'description' => ['kind' => $kind, 'name_en' => $kind === 'bust' ? 'bust' : 'figure', 'delete_photo' => true, 'consent_at' => now()->toIso8601String()] + array_filter([
                'pedestal' => $pedestal['type'] ?? null, 'pedestal_name' => $pedestal['name'] ?? null, 'pedestal_dedication' => $pedestal['dedication'] ?? null,
            ]),
            'target_mm' => $targetMm,
        ]);

        return $this->reuseOrDispatch($req, fn ($q) => $q->whereRaw('1 = 0'));
    }

    public function fromText(string $prompt, int $targetMm, ?string $ip, ?AnonymousSession $session, ?User $user, ?int $sourceRequestId = null): GenerationRequest
    {
        $prompt = trim($prompt);
        $req = $this->make('text', $ip, $session, $user, ['prompt' => $prompt, 'image_sha256' => hash('sha256', 'text:'.mb_strtolower($prompt)), 'target_mm' => $targetMm, 'source_request_id' => $sourceRequestId]);

        return $this->reuseOrDispatch($req, fn ($q) => $q->where('type', 'text')->where('image_sha256', $req->image_sha256));
    }

    /** What the first generation was about, in words; null when it was a personal photo (likeness cannot be described). */
    public function basePrompt(GenerationRequest $src): ?string
    {
        if (! empty($src->description['delete_photo'])) {
            return null;
        }
        $base = $src->type === 'text' ? (string) $src->prompt : (string) ($src->description['name_en'] ?? $src->prompt ?? '');

        return trim($base) !== '' ? trim($base) : null;
    }

    /** "Make the handle longer": a new text generation from the original subject plus the wish. Counts as a generation. */
    public function refine(GenerationRequest $src, string $instruction, ?string $ip, ?AnonymousSession $session, ?User $user): GenerationRequest
    {
        $base = $this->basePrompt($src);
        if ($base === null) {
            throw new \InvalidArgumentException('not_refinable');
        }
        $prompt = mb_substr(rtrim($base, '. ').'. '.trim($instruction), 0, 500);

        return $this->fromText($prompt, (int) ($src->target_mm ?: config('ai.default_target_mm', 80)), $ip, $session, $user, $src->id);
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
        $user = $req->owner_user_id ? User::find($req->owner_user_id) : null;
        $quota = $this->quota($req->ip, $req->anonymous_session_id ? AnonymousSession::find($req->anonymous_session_id) : null, $user);
        if (! $quota['allowed']) {
            // beyond the free quota a signed-in customer may pay for the generation from the farm credit
            $price = (float) app(\App\Domain\Farm\FarmSettings::class)->get('generation_price');
            if ($quota['reason'] !== 'daily_limit' || ! $user || $price <= 0 || ! config('farm.enabled')) {
                throw new QuotaExceeded($quota['reason'] ?? 'daily_limit', $quota['limit']);
            }
            try {
                app(\App\Domain\Farm\Wallet::class)->charge($user, $price, 'generation '.$req->token);
            } catch (\App\Domain\Farm\InsufficientCredit $e) {
                throw new QuotaExceeded('credit', $quota['limit'], $price, $e->missing());
            }
            $req->paid_credit = $price;
        }
        $req->save();
        GenerateModel::dispatch($req->id);

        return $req->refresh();
    }
}
