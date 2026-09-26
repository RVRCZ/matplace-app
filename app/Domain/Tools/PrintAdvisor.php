<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Models\ModelFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * "How do I print this?" for someone who may never have printed: the geometry gives the numbers (engines/python/
 * advise_tool.py: plate contact, overhang area, wall thickness, the orientation with the least overhang, four pictures
 * with overhangs painted orange), the model turns them and the pictures into a few plain sentences in the visitor's
 * language: how to turn the part, whether it needs supports, where it will be weak, what to watch for.
 *
 * The numbers are never the model's own: it may only explain and weigh what the geometry measured.
 */
final class PrintAdvisor
{
    public const TOPICS = ['orientation', 'supports', 'strength', 'splitting', 'material', 'details', 'adhesion', 'settings', 'other'];

    public const LEVELS = ['important', 'tip', 'fine'];

    private const LANGS = ['cs' => 'Czech', 'en' => 'English', 'es' => 'Spanish'];

    /** Bump when advise_tool.py measures differently: stored analyses of an older version are computed again. */
    private const ANALYSIS_VERSION = 2;

    public function __construct(private readonly array $config, private readonly PythonTool $python) {}

    public function available(): bool
    {
        return (string) ($this->config['api_key'] ?? '') !== '' && $this->python->available();
    }

    /** Numbers and pictures of the model, computed once per file and kept next to it. */
    public function analysis(ModelFile $file): array
    {
        $disk = Storage::disk(ModelFile::DISK);
        $dir = $file->dir().'/advice';
        if ($disk->exists($dir.'/facts.json')) {
            $cached = json_decode((string) $disk->get($dir.'/facts.json'), true);
            if (is_array($cached) && ! empty($cached['facts']) && ($cached['version'] ?? 1) === self::ANALYSIS_VERSION) {
                return $cached;
            }
        }
        $stl = $file->absoluteStlPath();
        if (! $stl || ! is_file($stl)) {
            throw new EngineException('The model has no STL.');
        }
        $disk->makeDirectory($dir);
        $r = $this->python->runScript('advise_tool.py', ['analyze', $stl, $disk->path($dir)], 240);
        if (empty($r['ok'])) {
            throw new EngineException('Analysis failed: '.($r['error'] ?? '?'));
        }
        $result = ['version' => self::ANALYSIS_VERSION, 'facts' => $r['facts'], 'views' => array_map(fn ($v) => ['file' => $dir.'/'.basename($v['file']), 'label' => $v['label']], $r['views'])];
        $disk->put($dir.'/facts.json', json_encode($result));

        return $result;
    }

    /**
     * @param  array  $context  what the customer chose and what the slicer said (material, quality, supports, vase, grams…)
     * @return array{summary: string, items: array<int, array{topic: string, level: string, title: string, text: string}>, facts: array, model: string, usage: array}
     */
    public function advise(ModelFile $file, string $locale, array $context = []): array
    {
        if (! $this->available()) {
            throw new EngineException('Advice is not configured.');
        }
        $analysis = $this->analysis($file);
        $disk = Storage::disk(ModelFile::DISK);
        $content = [];
        foreach ($analysis['views'] as $i => $v) {
            $content[] = ['type' => 'text', 'text' => 'Picture '.($i + 1).': '.$v['label']];
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode((string) $disk->get($v['file']))]];
        }
        $content[] = ['type' => 'text', 'text' => "Measured geometry (millimetres, Z up, as uploaded):\n".json_encode($analysis['facts'], JSON_PRETTY_PRINT)
            ."\n\nWhat kind of model it is: ".$file->kind().($file->original_name ? ' (file "'.$file->original_name.'")' : '')
            ."\nWhat the customer chose and what the slicer computed:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)];

        $model = (string) ($this->config['advise_model'] ?? 'claude-opus-5');
        $res = Http::timeout((int) ($this->config['inspect_timeout'] ?? 300))
            ->withHeaders(['x-api-key' => $this->config['api_key'], 'anthropic-version' => '2023-06-01', 'anthropic-beta' => 'server-side-fallback-2026-07-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 16000,
                'fallbacks' => 'default',
                'thinking' => ['type' => 'adaptive'],
                'output_config' => ['effort' => (string) ($this->config['advise_effort'] ?? 'medium'), 'format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
                'system' => $this->system($locale),
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);
        if (! $res->ok()) {
            throw new EngineException('Advice API HTTP '.$res->status().': '.mb_substr($res->body(), 0, 300));
        }
        if ($res->json('stop_reason') === 'refusal') {
            throw new EngineException('The model declined.');
        }
        $text = collect((array) $res->json('content'))->where('type', 'text')->pluck('text')->implode('');
        $d = json_decode($text, true);
        if (! is_array($d) || ! isset($d['summary'], $d['items'])) {
            throw new EngineException('The advice was not readable.');
        }
        $items = [];
        foreach (array_slice((array) $d['items'], 0, 7) as $it) {
            if (! in_array($it['topic'] ?? '', self::TOPICS, true) || ! in_array($it['level'] ?? '', self::LEVELS, true)) {
                continue;
            }
            $items[] = ['topic' => $it['topic'], 'level' => $it['level'], 'title' => mb_substr((string) $it['title'], 0, 120), 'text' => mb_substr((string) $it['text'], 0, 600)];
        }

        return [
            'summary' => mb_substr((string) $d['summary'], 0, 400),
            'items' => $items,
            'facts' => $analysis['facts'],
            'model' => $model,
            'usage' => array_intersect_key((array) $res->json('usage'), array_flip(['input_tokens', 'output_tokens', 'cache_read_input_tokens', 'cache_creation_input_tokens'])),
        ];
    }

    private function system(string $locale): string
    {
        $lang = self::LANGS[$locale] ?? 'English';

        return <<<TXT
You are an experienced FDM 3D-printing technician. A customer uploaded a model to a print service and asks how it should be printed. They may never have printed anything: write plainly, no jargon without a short explanation, in {$lang}.

You get four pictures of the model as uploaded (grey; faces overhanging more than the stated limit and facing down are painted orange: they print in the air and need supports or a better orientation; faces touching the build plate are blue) and numbers measured from the mesh. Every number you mention must come from those numbers; never estimate sizes from the pictures. The pictures tell you what the object is and where the problem areas are.

What matters, most important first:
- Orientation: when best_orientation differs from the upload, say which side to put down and what it saves (overhang area, supports). Our print farm turns models automatically before printing; someone printing at home turns it in their slicer.
- Supports: needed when orange areas are large or hang in the air; say where and that supports leave marks there. If the slicer said supports_used, account for it.
- Adhesion and stability: a small plate contact compared with the footprint, or a tall slender part, may come loose or wobble: suggest a brim (a thin rim around the first layer).
- Thin walls and fine details: wall thickness under 0.8 mm may not print with a common 0.4 mm nozzle; under 1.2 mm is fragile.
- Strength: layers are weakest when pulled apart along Z; for hooks, clips, handles or anything carrying load, say how to orient it or what infill and material help.
- Several separate bodies, an open (not watertight) mesh, a vase printed in vase mode (one wall, not watertight), anything special about the kind of model.

When best_orientation is null or same_as_uploaded, do not suggest turning the model; read measurement_note when it is set.

Address the customer politely and without assuming their gender (in Czech avoid past-tense forms like "nahrál/nahrála": write "model, jak je nahraný"; in Spanish avoid gendered adjectives about the customer).

Give at most six items, the most important first. If the model prints well as it is, say so and keep to two or three items; mark confirmations as "fine". "important" = the print may fail or disappoint without it; "tip" = it makes the print better. Titles are short (a few words); texts one to three sentences. The summary is one sentence.
TXT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string', 'enum' => self::TOPICS],
                        'level' => ['type' => 'string', 'enum' => self::LEVELS],
                        'title' => ['type' => 'string'],
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['topic', 'level', 'title', 'text'],
                    'additionalProperties' => false,
                ]],
            ],
            'required' => ['summary', 'items'],
            'additionalProperties' => false,
        ];
    }
}
