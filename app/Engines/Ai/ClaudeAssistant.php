<?php

namespace App\Engines\Ai;

use App\Engines\Exceptions\EngineException;
use App\Support\AiUsage;
use Illuminate\Support\Facades\Http;

/**
 * Claude over the Messages API, with the answer constrained to a JSON schema. The same plain HTTP call the
 * translator and the print advisor make; model and effort come from config/ai.php (`assistant_model`).
 */
final class ClaudeAssistant implements Assistant
{
    /** @param  array{api_key?: string, assistant_model?: string, assistant_effort?: string, timeout?: int}  $config */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    public function ask(string $kind, string $system, string $user, array $schema, array $images = [], array $context = []): array
    {
        if (! $this->available()) {
            throw new EngineException('The assistant is not configured (ANTHROPIC_API_KEY).');
        }
        $model = (string) ($this->config['assistant_model'] ?? 'claude-opus-5-5');
        // effort and the server-side fallback exist on the current Opus / Sonnet / Fable models; a Haiku set in .env gets neither
        $current = (bool) preg_match('/^claude-(opus-5|sonnet-5|fable|mythos)/', $model);
        $content = [];
        foreach ($images as $image) {
            if ($block = self::image($image)) {
                $content[] = $block;
            }
        }
        $content[] = ['type' => 'text', 'text' => $user];
        $format = ['type' => 'json_schema', 'schema' => $schema];

        $started = microtime(true);
        $res = Http::timeout((int) ($this->config['timeout'] ?? 120))
            ->withHeaders(['x-api-key' => $this->config['api_key'], 'anthropic-version' => '2023-06-01'] + ($current ? ['anthropic-beta' => 'server-side-fallback-2026-07-01'] : []))
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 8000,
                'output_config' => $current ? ['effort' => (string) ($this->config['assistant_effort'] ?? 'low'), 'format' => $format] : ['format' => $format],
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $content]],
            ] + ($current ? ['fallbacks' => 'default'] : []));
        if (! $res->ok()) {
            throw new EngineException('Assistant API HTTP '.$res->status().': '.mb_substr($res->body(), 0, 300));
        }
        AiUsage::record($kind, (string) ($res->json('model') ?? $model), (array) $res->json('usage'), (int) round((microtime(true) - $started) * 1000), $context);
        if ($res->json('stop_reason') === 'refusal') {
            throw new EngineException('The model declined the request.');
        }
        $answer = json_decode(collect((array) $res->json('content'))->where('type', 'text')->pluck('text')->implode(''), true);
        if (! is_array($answer)) {
            throw new EngineException('The answer of the assistant was not readable.');
        }

        return $answer;
    }

    /** A picture as a content block: by address when it is on the web, as data when it is a file here. */
    private static function image(string $image): ?array
    {
        if (preg_match('#^https://#', $image)) {
            return ['type' => 'image', 'source' => ['type' => 'url', 'url' => $image]];
        }
        if (! is_file($image) || filesize($image) > 4_500_000) {
            return null;
        }
        // the kind by what the file is, not by its name: thumbnails of the old catalogue are JPEGs called .png,
        // and the API refuses a picture whose declared type does not match its bytes
        $type = @getimagesize($image)['mime'] ?? null;

        return in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
            ? ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $type, 'data' => base64_encode((string) file_get_contents($image))]]
            : null;
    }
}
