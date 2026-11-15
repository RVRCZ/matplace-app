<?php

namespace App\Engines\Vision;

use App\Engines\Exceptions\EngineException;
use App\Support\AiUsage;
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
     * A further side (left, back, right) is judged for its content only: the back of a head shows no face and
     * no "clear subject", and that is exactly what such a photo should look like.
     *
     * @return array{ok: bool, subject: string, reason: string}
     */
    public function moderate(string $imagePath, string $view = 'front', ?string $want = null): array
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
        if ($want === 'pet' && $view === 'front') {
            // a pet figurine is made of one animal. A person beside it is no reason to refuse any more (Roman, 15 Nov 2026:
            // a dog on a leash with its owner's legs behind it was refused): the photo is redrawn as the animal alone
            // before the model is made (GenerateModel, the cleaning stage). What still stops it is an animal that
            // cannot be seen whole
            $system .= ' This photo is for a figurine of a PET. Besides the content rules, set ok=false with exactly one of these words as the reason: '
                .'"no_animal" when no animal is the subject; "several" when there is more than one animal; '
                .'"cropped" when the frame cuts off the legs or most of the body, only the head is shown, or a person holding the animal hides most of its body. '
                .'People in the picture are fine and are NOT a reason to refuse: an owner standing behind or beside the animal, legs, hands, somebody holding the leash. '
                .'A collar, a leash, a toy, grass or furniture are fine too. One whole animal, sitting, standing or lying, is ok.';
        }
        if ($want === 'pet_owner' && $view === 'front') {
            // a figurine of ONE animal TOGETHER WITH ONE person (the choice "pet with its owner", 15 Nov 2026): both have to be
            // there and whole, the page offers the animal alone when there is no person
            $system .= ' This photo is for a figurine of a PET TOGETHER WITH ITS OWNER: exactly one animal and exactly one person, side by side. '
                .'Besides the content rules, set ok=false with exactly one of these words as the reason: '
                .'"no_animal" when no animal is in the picture; "no_person" when no person is in the picture (a hand or a pair of legs only is no person); '
                .'"several" when there is more than one animal; "several_people" when there is more than one person; '
                .'"cropped" when the frame cuts off the person (the legs, the feet or the head) or the animal (the legs or most of the body), or the person or the animal is mostly hidden, or the person holds the animal in their arms or on their lap (its legs would hang in the air: Roman, 15 Nov 2026). '
                .'A person in the background, far behind the two, does not count as a second person only when they are clearly a passer-by; when in doubt about a second person use "several_people". '
                .'A collar, a leash, a toy, grass or furniture are fine. One whole animal and one whole person, each on the ground, sitting or standing, is ok.';
        }
        if ($want === 'figure' && $view === 'front') {
            // a whole figure is made of what the photo shows: of a photo to the waist the generator invents the legs, and
            // of a bust's photo comes a stranger's body (15 Nov 2026). Such a photo is sent back with the advice to make
            // a bust of it, or to take a photo of the whole subject
            $system .= ' This photo is for a figurine of the WHOLE subject (a whole person from head to feet, a whole toy or a whole object). '
                .'Besides the content rules, set ok=false with exactly the word "cropped" as the reason when the subject is a person '
                .'and the frame cuts the person off above the knees (a portrait, head and shoulders, a photo to the waist), or when most of a toy or an object is outside the frame. '
                .'A person seen down to the knees or lower, sitting or standing, is ok; so is any whole toy or object.';
        }
        if ($view !== 'front') {
            $system .= ' This photo is an additional view of a subject whose front photo was already accepted: it shows the subject from the '
                .$view.'. The face may be hidden or turned away, and a back view shows only hair, the back of a head or the back of an object. '
                .'That is expected and ok. Do NOT set ok=false because the face or the subject is unclear, cropped or unrecognisable; '
                .'set ok=false only for the content reasons listed above.';
        }
        $res = Http::timeout((int) ($this->config['timeout'] ?? 45))
            ->withHeaders(['x-api-key' => $this->config['api_key'], 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->config['model'] ?? 'claude-haiku-4-5-20251001',
                'max_tokens' => 120,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]],
                    ['type' => 'text', 'text' => $view === 'front' ? 'Screen this photo.' : 'Screen this photo (view from the '.$view.').'],
                ]]],
            ]);
        if (! $res->ok()) {
            return ['ok' => true, 'subject' => 'unknown', 'reason' => 'moderation_unavailable']; // fail open: the provider filters too
        }
        AiUsage::record('moderate', (string) ($res->json('model') ?? ($this->config['model'] ?? 'claude-haiku')), (array) $res->json('usage'));
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
        AiUsage::record('describe', (string) ($res->json('model') ?? ($this->config['model'] ?? 'claude-haiku')), (array) $res->json('usage'));
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
