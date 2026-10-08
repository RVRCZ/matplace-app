<?php

namespace App\Engines\Image;

use App\Engines\Exceptions\EngineException;
use App\Support\AiUsage;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini image models over the Generative Language API (v1beta, generateContent with the IMAGE modality).
 * The key is GEMINI_API_KEY in .env (config/ai.php `gemini`); it is sent as a header and never logged. The model
 * is gemini-3-pro-image by default (Roman, 8 Oct 2026), `cheap_model` when `cheap` is set; the call is booked through AiUsage at the flat
 * price of config/ai.php `prices.models` (0.08 USD for the pro model, 0.04 for the rest).
 */
final class GeminiImageGenerator implements ImageGenerator
{
    /** @param  array{api_key?: string, base_url?: string, image_model?: string, cheap_model?: string, cheap?: bool, timeout?: int}  $config */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    public function fromText(string $prompt, string $style, string $size, array $context = []): ImageResult
    {
        if (! $this->available()) {
            throw new EngineException('The image generator is not configured (GEMINI_API_KEY).');
        }
        $model = (string) (! empty($this->config['cheap']) ? ($this->config['cheap_model'] ?? 'gemini-3.1-flash-lite-image') : ($this->config['image_model'] ?? 'gemini-3-pro-image'));
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $started = microtime(true);
        $res = Http::timeout((int) ($this->config['timeout'] ?? 90))
            ->withHeaders(['x-goog-api-key' => (string) $this->config['api_key']])
            ->post($base.'/models/'.$model.':generateContent', [
                'contents' => [['parts' => [['text' => self::prompt($prompt, $style, $size)]]]],
                'generationConfig' => ['responseModalities' => ['IMAGE']],
            ]);
        $ms = (int) round((microtime(true) - $started) * 1000);
        if (! $res->ok()) {
            // the body may carry the key back in an error message: only the status and the first words go on
            throw new EngineException('Image API HTTP '.$res->status().': '.mb_substr(preg_replace('/AQ\.[A-Za-z0-9_\-]+/', '…', (string) $res->json('error.message', $res->body())), 0, 200));
        }
        $usage = (array) $res->json('usageMetadata', []);
        $tokens = (int) ($usage['totalTokenCount'] ?? 0);
        AiUsage::record('image', $model, ['input_tokens' => (int) ($usage['promptTokenCount'] ?? 0), 'output_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0)], $ms, $context);
        foreach ((array) $res->json('candidates.0.content.parts', []) as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (is_array($inline) && ! empty($inline['data'])) {
                $bytes = base64_decode((string) $inline['data'], true);
                if ($bytes !== false && strlen($bytes) > 100) {
                    return new ImageResult($bytes, (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/jpeg'), $model, $tokens, $ms);
                }
            }
        }
        $reason = (string) ($res->json('candidates.0.finishReason') ?? $res->json('promptFeedback.blockReason') ?? 'no image');
        throw new EngineException('The model returned no picture ('.$reason.').');
    }

    /** What is asked of the model for each style: the tools want flat, centred, high-contrast pictures on white. */
    public static function prompt(string $prompt, string $style, string $size): string
    {
        $subject = trim($prompt);
        $shape = match ($size) {
            'wide' => 'a wide landscape image (4:3)',
            'tall' => 'a tall portrait image (3:4)',
            default => 'a square image',
        };
        $how = match ($style) {
            'line' => 'A simple black line drawing of '.$subject.' on a pure white background: uniform thick black lines, no shading, no colour, no text, no frame, the subject centred and filling most of the picture.',
            'colour' => 'A simple flat illustration of '.$subject.' on a pure white background: at most six flat solid colours, bold clear shapes, no gradients, no shadows, no text, no frame, the subject centred and filling most of the picture.',
            default => 'A clean solid black silhouette of '.$subject.' on a pure white background: one flat black shape, no grey, no gradients, no shadows, no outline, no text, no frame, the subject centred and filling most of the picture, suitable for cutting out.',
        };

        return $how.' Make it '.$shape.'.';
    }
}
