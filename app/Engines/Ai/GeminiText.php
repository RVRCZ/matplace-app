<?php

namespace App\Engines\Ai;

use App\Engines\Exceptions\EngineException;
use App\Models\AiCall;
use App\Support\AiUsage;
use Illuminate\Support\Facades\Http;

/**
 * Gemini asked for one JSON answer in the shape of a schema (generateContent with a response schema). The cheap
 * text model for work done by the hundred: the descriptions of the inspiration catalogue. Every call is booked in
 * ai_calls with its tokens (the thinking tokens count as output), the price per million comes from config/ai.php.
 */
final class GeminiText
{
    /** @var array{api_key?: string, base_url?: string, text_model?: string, timeout?: int} */
    private readonly array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('ai.gemini');
    }

    public function available(): bool
    {
        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    public function model(): string
    {
        return (string) (($this->config['text_model'] ?? '') ?: 'gemini-2.5-flash');
    }

    /**
     * @param  string  $kind  what the call is for (booked in ai_calls)
     * @param  string  $system  the rules of the task
     * @param  string  $user  the material to work on (treated as text, never as instructions)
     * @param  array<string, mixed>  $schema  the answer's shape, in the subset of JSON schema Gemini takes (type, properties, required, items, enum)
     * @param  array{subject_type?: string, subject_id?: int, user_id?: int}  $context  who to book the call on
     * @return array{answer: array<string, mixed>, usage: array{input_tokens: int, output_tokens: int}, call: ?AiCall}
     *
     * @throws EngineException
     */
    public function json(string $kind, string $system, string $user, array $schema, array $context = []): array
    {
        if (! $this->available()) {
            throw new EngineException('Gemini is not configured (GEMINI_API_KEY).');
        }
        $model = $this->model();
        $body = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => $schema, 'temperature' => 0.5, 'maxOutputTokens' => 2048],
        ];
        // no thinking: a description needs none, and the thinking tokens would cost more than the answer. The Flash models take
        // the budget, the Lite ones refuse it with HTTP 400 and do not think anyway (18 Oct 2026); a refusal is tried once without
        $thinking = ! str_contains($model, 'lite');
        $started = microtime(true);
        $send = fn (bool $withThinking) => Http::timeout((int) ($this->config['timeout'] ?? 90))
            ->withHeaders(['x-goog-api-key' => (string) $this->config['api_key']])
            ->post(rtrim((string) ($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/')."/models/{$model}:generateContent",
                $withThinking ? array_replace_recursive($body, ['generationConfig' => ['thinkingConfig' => ['thinkingBudget' => 0]]]) : $body);
        $res = $send($thinking);
        if ($thinking && $res->status() === 400) {
            $res = $send(false);
        }
        if (! $res->ok()) {
            throw new EngineException('Gemini HTTP '.$res->status().': '.mb_substr($res->body(), 0, 300));
        }
        $meta = (array) $res->json('usageMetadata');
        $usage = ['input_tokens' => (int) ($meta['promptTokenCount'] ?? 0), 'output_tokens' => (int) ($meta['candidatesTokenCount'] ?? 0) + (int) ($meta['thoughtsTokenCount'] ?? 0)];
        $call = AiUsage::record($kind, (string) ($res->json('modelVersion') ?: $model), $usage, (int) round((microtime(true) - $started) * 1000), $context);
        if ($reason = $res->json('promptFeedback.blockReason')) {
            throw new EngineException('Gemini declined the request: '.$reason);
        }
        $text = collect((array) $res->json('candidates.0.content.parts'))->pluck('text')->filter()->implode('');
        $answer = json_decode($text, true);
        if (! is_array($answer)) {
            throw new EngineException('The answer of Gemini was not readable JSON: '.mb_substr($text, 0, 200));
        }

        return ['answer' => $answer, 'usage' => $usage, 'call' => $call];
    }
}
