<?php

namespace App\Engines\Vision;

use App\Engines\Exceptions\EngineException;
use Illuminate\Support\Facades\Http;

/**
 * Photo → structured description of the part (what it is, size guess, material hint, search queries).
 * Anthropic Messages API with an image block. Cheap (Haiku); the caller enforces daily quotas.
 * Returns an array; never throws for "model said something odd" — only for transport/config failures.
 */
final class VisionDescriber
{
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    /**
     * Safety check before a photo of a person is turned into a figure: what is on it and whether it is acceptable.
     *
     * @return array{ok: bool, subject: string, reason: string}
     */
    public function moderate(string $imagePath): array
    {
        if (! $this->available()) {
            return ['ok' => true, 'subject' => 'unknown', 'reason' => 'moderation_unavailable'];
        }
        $bytes = (string) file_get_contents($imagePath);
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? 'image/jpeg';
        $system = 'You screen photos for a service that turns a photo into a 3D-printable figure or bust. '
            .'Answer ONLY with JSON: {"ok": <bool>, "subject": "person|pet|object|character|other", "reason": "<short English reason when ok=false, else empty>"}. '
            .'Set ok=false for nudity or sexual content, violence or gore, hate symbols, weapons presented as the main subject, '
            .'or when no clear single subject is visible. Ordinary portraits, pets, toys and objects are ok.';
        $res = Http::timeout((int) ($this->config['timeout'] ?? 45))
            ->withHeaders(['x-api-key' => $this->config['api_key'], 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->config['model'] ?? 'claude-haiku-4-5-20251001',
                'max_tokens' => 120,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]],
                    ['type' => 'text', 'text' => 'Screen this photo.'],
                ]]],
            ]);
        if (! $res->ok()) {
            return ['ok' => true, 'subject' => 'unknown', 'reason' => 'moderation_unavailable']; // fail open: the provider filters too
        }
        $text = (string) ($res->json('content.0.text') ?? '');
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }
        $d = json_decode($text, true);

        return ['ok' => (bool) ($d['ok'] ?? true), 'subject' => (string) ($d['subject'] ?? 'other'), 'reason' => (string) ($d['reason'] ?? '')];
    }

    /**
     * @return array{name: string, query: string, queries: string[], category: string, bbox_mm: array{x:int,y:int,z:int}|null,
     *               size_known: bool, material: string, printable: bool, notes: string, lang: string}
     */
    public function describe(string $imagePath, string $locale = 'cs'): array
    {
        if (! $this->available()) {
            throw new EngineException('Vision is not configured (ANTHROPIC_API_KEY).');
        }
        if (! is_file($imagePath)) {
            throw new EngineException('Image not found: '.$imagePath);
        }
        $bytes = (string) file_get_contents($imagePath);
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? 'image/jpeg';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new EngineException('Unsupported image type: '.$mime);
        }

        $langName = ['cs' => 'Czech', 'en' => 'English', 'es' => 'Spanish'][$locale] ?? 'English';
        $system = <<<TXT
You identify physical objects in photos for a 3D-printing service. The user usually photographs a broken or missing part
(a lid, a knob, a bracket, a clip, a holder, a toy piece) or an object they want reproduced.
Answer ONLY with a JSON object:
{
  "name": "<short name of the object in {$langName}, 2-6 words>",
  "name_en": "<short English name>",
  "category": "spare_part|holder|decor|toy|tool|enclosure|other",
  "queries": ["<3 short English search queries a maker would type on Printables, most specific first>"],
  "bbox_mm": {"x": <int>, "y": <int>, "z": <int>} or null,
  "size_known": <true if a reference object/scale is visible, else false>,
  "material": "PLA|PETG|ASA|TPU|PA",
  "printable": <true if a plain FDM print could replace it>,
  "notes": "<one sentence in {$langName}: what it is and what matters for printing (threads, flexibility, outdoor use)>"
}
Estimate bbox_mm from context (hands, coins, furniture); when unsure, give a typical size for that object and size_known=false.
TXT;

        $payload = [
            'model' => $this->config['model'] ?? 'claude-haiku-4-5-20251001',
            'max_tokens' => 400,
            'system' => $system,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]],
                    ['type' => 'text', 'text' => 'Identify the object in the photo.'],
                ],
            ]],
        ];
        $res = Http::timeout((int) ($this->config['timeout'] ?? 45))
            ->withHeaders(['x-api-key' => $this->config['api_key'], 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', $payload);
        if (! $res->ok()) {
            throw new EngineException('Vision API HTTP '.$res->status().': '.mb_substr($res->body(), 0, 300));
        }
        $text = (string) ($res->json('content.0.text') ?? '');
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }
        $d = json_decode($text, true);
        if (! is_array($d)) {
            $d = [];
        }

        $queries = array_values(array_filter(array_map(fn ($q) => trim((string) $q), (array) ($d['queries'] ?? [])), fn ($q) => $q !== ''));
        $name = trim((string) ($d['name'] ?? ''));
        $nameEn = trim((string) ($d['name_en'] ?? ''));
        if (! $queries && $nameEn !== '') {
            $queries = [$nameEn];
        }
        $bbox = null;
        if (is_array($d['bbox_mm'] ?? null) && (int) ($d['bbox_mm']['x'] ?? 0) > 0) {
            $bbox = ['x' => (int) $d['bbox_mm']['x'], 'y' => (int) ($d['bbox_mm']['y'] ?? 0), 'z' => (int) ($d['bbox_mm']['z'] ?? 0)];
        }

        return [
            'name' => $name !== '' ? $name : ($nameEn !== '' ? $nameEn : ''),
            'name_en' => $nameEn,
            'query' => $queries[0] ?? $nameEn,
            'queries' => $queries,
            'category' => (string) ($d['category'] ?? 'other'),
            'bbox_mm' => $bbox,
            'size_known' => (bool) ($d['size_known'] ?? false),
            'material' => in_array($d['material'] ?? '', ['PLA', 'PETG', 'ASA', 'TPU', 'PA'], true) ? $d['material'] : 'PLA',
            'printable' => (bool) ($d['printable'] ?? true),
            'notes' => (string) ($d['notes'] ?? ''),
            'lang' => $locale,
            'engine' => $payload['model'],
        ];
    }
}
