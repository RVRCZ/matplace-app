<?php

namespace App\Support;

use App\Models\AiCall;
use Illuminate\Support\Facades\Log;

/**
 * Every call of an AI service goes through here, so /admin/ai can say what the site spends: one row in ai_calls
 * with the tokens and the price in crowns. Prices per million tokens (or per call) are in config/ai.php.
 * Booking never breaks the feature that made the call.
 */
final class AiUsage
{
    /**
     * @param  array<string, mixed>  $usage  the provider's usage object: input_tokens, output_tokens (+ cache_* for Claude)
     * @param  array{subject_type?: string, subject_id?: int, user_id?: int, session_id?: int}  $context
     */
    public static function record(string $kind, string $engine, array $usage = [], int $durationMs = 0, array $context = []): ?AiCall
    {
        try {
            $in = (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0);
            $out = (int) ($usage['output_tokens'] ?? 0);
            $request = app()->bound('request') ? request() : null;

            return AiCall::create([
                'kind' => mb_substr($kind, 0, 30),
                'engine' => mb_substr($engine, 0, 60),
                'tokens_in' => $in,
                'tokens_out' => $out,
                'cost_czk' => self::cost($engine, $in, $out),
                'user_id' => $context['user_id'] ?? $request?->user()?->id,
                'session_id' => $context['session_id'] ?? $request?->attributes->get('anon_session')?->id,
                'subject_type' => $context['subject_type'] ?? null,
                'subject_id' => $context['subject_id'] ?? null,
                'duration_ms' => max(0, $durationMs),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI usage was not recorded', ['kind' => $kind, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Price of a call in crowns: per-token for language models, a flat price per call for the rest. */
    public static function cost(string $engine, int $tokensIn, int $tokensOut): float
    {
        $usd = (float) config('ai.prices.usd_czk', 23);
        foreach ((array) config('ai.prices.models', []) as $prefix => $price) {
            if (str_starts_with($engine, (string) $prefix)) {
                return round((($tokensIn * (float) $price['in'] + $tokensOut * (float) $price['out']) / 1_000_000 + (float) ($price['call'] ?? 0)) * $usd, 4);
            }
        }

        return 0.0;
    }
}
