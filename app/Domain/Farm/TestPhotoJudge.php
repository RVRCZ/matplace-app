<?php

namespace App\Domain\Farm;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Models\FarmOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the photos of a printed test object and fills in the evaluation form the operator would fill in: stringing,
 * overhangs, bridges, first layer, corners, ironing, walls, layer bond, warping, an overall score and a note. The
 * operator confirms or corrects it; TuningAdvisor turns the confirmed result into setting changes.
 *
 * The model gets the object's real geometry (angles, spans, gaps, wall thicknesses from calib_tool.py), the settings
 * the test was printed with, earlier tests of the same machine and what the farm has learned so far, plus a crop tool:
 * hairs between pillars and the underside of an overhang are only visible when it zooms in.
 */
final class TestPhotoJudge
{
    /** Longest edge of each photo; a request with many images allows at most 2000 px per side (the crop tool gets the detail back). */
    private const SENT_PX = 2000;

    private const CROP_PX = 1600;

    private const MAX_TURNS = 14;

    private const MAX_CROPS = 20;

    /** Form fields with the values the form offers; "unknown" = not judgeable from these photos. */
    public const FIELDS = [
        'stringing' => ['0', '1', '2', '3'],
        'overhang_ok' => ['0', '30', '40', '50', '60', '70'],
        'bridge' => ['ok', 'sag', 'fail'],
        'elephant' => ['0', '1', '2'],
        'corners' => ['ok', 'bulge', 'round', 'gaps'],
        'ironing' => ['ok', 'lines', 'bumps', 'rough'],
        'top' => ['ok', 'pillow', 'gaps'],
        'wall' => ['ok', 'gaps', 'missing'],
        'bond' => ['ok', 'weak'],
        'warp' => ['ok', 'lift'],
    ];

    /** What the farm has learned so far; grows with every test the operator corrects. */
    private const KNOWLEDGE = <<<'TXT'
- Stringing: judge the hairs between the stringing pillars (gaps 10 and 20 mm) and around tall features. 0 = none, 1 = a few fine single hairs, 2 = many fine hairs or cobweb in the 10 mm gap, 3 = cobweb in both gaps or thick strands. Zoom in: fine hairs are invisible at full view.
- Heavy stringing together with a rough, weak or flaky layer bond is the signature of a wet spool (seen on T26-000003, PLA Silk): say so in the note; temperature changes will not fix it, drying will.
- PLA Silk strings more and shows every layer line because it is glossy; do not score it down for gloss.
- Overhang fan: fins at 30, 40, 50, 60, 70 and 80 degrees from vertical. overhang_ok is the steepest angle whose underside and edge are still clean (droop, curled or ragged edge, strands hanging = not clean). If even 80 is clean, answer 70 (the form's top value).
- Bridges (15 and 25 mm spans): sag = strands visibly drooping under the span, seen from the side; fail = broken or fallen strands.
- Elephant foot: the lowest 1-3 layers of the cube or walls flare out beyond the wall above. A glossy first layer on a smooth PEI plate is normal, not a defect.
- Corners of the cube: round = vertical edges visibly rounded instead of sharp; bulge = edges or seam bulge outward; gaps = holes in the wall.
- Top surface of the cube: pillow = bumps or holes between top lines.
- Ironed plateau (detailed test, when ironed): ok = smooth and even sheen; lines = the ironing passes or rows of dots are visible; bumps = ridges or excess material; rough = matte and grainy.
- Thin walls (0.4, 0.8, 1.2 mm): gaps = holes or missing lines in a wall; missing = a wall did not print. Small blobs on the seam are worth a note, not a wall defect.
- Layer bond bar (3 x 12 x 35 mm): weak = layers separating, cracks, or a rough, porous surface compared with the rest.
- Warp: lift = a corner of the base plate curled up, the plate not flat.
- A ring at the same height on several features comes from the layer time changing when lower features end; mention it in the note, it is not a defect of any single field.
- Photos may show other objects (props, earlier prints, hands): judge only the test object. Reflections and white-balance tints are not defects.
TXT;

    public function __construct(private readonly array $config, private readonly TestPhotos $photos, private readonly PythonTool $python) {}

    public function available(): bool
    {
        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    /**
     * @return array{fields: array<string, array{value: string|null, confidence: string, reason: string}>, score: int|null, note: string, model: string, crops: int, usage: array}
     *
     * @throws EngineException on transport or configuration failures and when the model gives no evaluation
     */
    public function judge(FarmOrder $order): array
    {
        if (! $this->available()) {
            throw new EngineException('Vision is not configured (ANTHROPIC_API_KEY).');
        }
        $photos = $this->photos->all($order);
        if (! $photos) {
            throw new EngineException('The test has no photos.');
        }
        $work = Storage::disk(config('farm.disk'))->path($order->dir().'/photos/api');
        if (! is_dir($work)) {
            mkdir($work, 0775, true);
        }

        // what the model sees: every photo at the high-resolution limit, numbered from 1; crops are cut from the stored photo
        $content = [];
        $sent = [];
        foreach ($photos as $i => $photo) {
            $target = $work.'/'.($i + 1).'.jpg';
            $r = $this->python->runScript('photo_tool.py', ['fit', $this->photos->path($photo), $target, (string) self::SENT_PX], 60);
            if (empty($r['ok'])) {
                throw new EngineException('Photo '.($i + 1).' could not be prepared: '.($r['error'] ?? '?'));
            }
            $sent[$i + 1] = ['w' => (int) $r['w'], 'h' => (int) $r['h'], 'scale' => $photo['w'] / max(1, (int) $r['w']), 'photo' => $photo];
            $content[] = ['type' => 'text', 'text' => sprintf('Photo %d (%s, %d x %d px):', $i + 1, $photo['view'], $r['w'], $r['h'])];
            $content[] = $this->imageBlock($target);
        }
        $content[] = ['type' => 'text', 'text' => $this->brief($order)];

        $messages = [['role' => 'user', 'content' => $content]];
        $crops = 0;
        $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'cache_read_input_tokens' => 0];
        $model = (string) ($this->config['inspect_model'] ?? 'claude-opus-5');

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $res = $this->call($model, $messages);
            foreach ($usage as $k => $v) {
                $usage[$k] = $v + (int) ($res['usage'][$k] ?? 0);
            }
            if (($res['stop_reason'] ?? '') === 'refusal') {
                throw new EngineException('The model declined to evaluate these photos.');
            }
            $messages[] = ['role' => 'assistant', 'content' => $res['content']];
            $results = [];
            foreach ($res['content'] as $block) {
                if (($block['type'] ?? '') !== 'tool_use') {
                    continue;
                }
                if ($block['name'] === 'submit_evaluation') {
                    return $this->result((array) $block['input'], $model, $crops, $usage);
                }
                if ($block['name'] === 'crop') {
                    $results[] = $this->crop((array) $block['input'], $block['id'], $sent, $work, ++$crops);
                }
            }
            if (! $results) {
                // it answered in prose: ask once more for the form, the photos stay in the conversation
                $messages[] = ['role' => 'user', 'content' => 'Submit the evaluation with the submit_evaluation tool.'];

                continue;
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        throw new EngineException('The model gave no evaluation within '.self::MAX_TURNS.' turns.');
    }

    private function call(string $model, array $messages): array
    {
        $res = Http::timeout((int) ($this->config['inspect_timeout'] ?? 300))
            ->withHeaders([
                'x-api-key' => $this->config['api_key'],
                'anthropic-version' => '2023-06-01',
                // a policy decline is re-run on the model the API picks for that category, inside the same call
                'anthropic-beta' => 'server-side-fallback-2026-07-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 16000,
                'fallbacks' => 'default',
                'thinking' => ['type' => 'adaptive'],
                'output_config' => ['effort' => (string) ($this->config['inspect_effort'] ?? 'high')],
                'cache_control' => ['type' => 'ephemeral'],   // the photos are resent every turn: read them from the cache
                'system' => $this->system(),
                'tools' => $this->tools(),
                'messages' => $messages,
            ]);
        if (! $res->ok()) {
            throw new EngineException('Vision API HTTP '.$res->status().': '.mb_substr($res->body(), 0, 300));
        }

        return (array) $res->json();
    }

    private function system(): string
    {
        return "You inspect photos of FDM calibration prints for a 3D print farm and fill in the operator's evaluation form. "
            ."The operator confirms or corrects your answers, and a rule-based advisor turns them into slicer changes, so an honest 'unknown' is worth more than a guess.\n\n"
            .'Look at the whole object first, then use the crop tool on every feature whose field you answer from a detail (the stringing pillars, the overhang fins, the bridges, the first layer at the cube, the ironed plateau, the thin walls, the bond bar). '
            ."Coordinates are pixels of the photo as you see it. Never estimate dimensions in millimetres.\n\n"
            ."What the farm has learned so far:\n".self::KNOWLEDGE."\n\n"
            .'Write every reason and the note in Czech, briefly, the way one printer operator tells another what they see.';
    }

    private function tools(): array
    {
        $field = fn (array $values) => [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string', 'enum' => [...$values, 'unknown']],
                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'reason' => ['type' => 'string'],
            ],
            'required' => ['value', 'confidence', 'reason'],
            'additionalProperties' => false,
        ];
        $props = [];
        foreach (self::FIELDS as $k => $values) {
            $props[$k] = $field($values);
        }
        $props['score'] = ['type' => 'string', 'enum' => ['1', '2', '3', '4', '5'], 'description' => '5 = print-ready, no defects; 4 = minor cosmetic; 3 = visible defects; 2 = serious problems; 1 = failed'];
        $props['note'] = ['type' => 'string', 'description' => 'Czech, 1-3 sentences: the main findings and anything the fields cannot express'];
        $props['better_photos'] = ['type' => 'string', 'description' => 'Czech; which photo or angle would have settled an unknown or low-confidence field; empty when nothing'];

        return [
            [
                'name' => 'crop',
                'description' => 'Returns a region of one photo cut from the full-resolution original and enlarged. Use it to inspect fine details (hairs, drooping strands, first layers, ironing lines, seams).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'photo' => ['type' => 'integer', 'description' => 'Photo number, from 1'],
                        'x0' => ['type' => 'integer'], 'y0' => ['type' => 'integer'], 'x1' => ['type' => 'integer'], 'y1' => ['type' => 'integer'],
                    ],
                    'required' => ['photo', 'x0', 'y0', 'x1', 'y1'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
            [
                'name' => 'submit_evaluation',
                'description' => 'The finished evaluation form. Call it once, after inspecting the details.',
                'input_schema' => ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false],
                'strict' => true,
            ],
        ];
    }

    private function crop(array $in, string $id, array $sent, string $work, int $n): array
    {
        $p = (int) ($in['photo'] ?? 0);
        if (! isset($sent[$p]) || $n > self::MAX_CROPS) {
            return ['type' => 'tool_result', 'tool_use_id' => $id, 'is_error' => true, 'content' => isset($sent[$p]) ? 'No more crops; submit the evaluation.' : 'No such photo.'];
        }
        $s = $sent[$p]['scale'];
        $target = $work.'/crop-'.$n.'.jpg';
        $r = $this->python->runScript('photo_tool.py', [
            'crop', $this->photos->path($sent[$p]['photo']), $target,
            (string) ((int) $in['x0'] * $s), (string) ((int) $in['y0'] * $s), (string) ((int) $in['x1'] * $s), (string) ((int) $in['y1'] * $s),
            (string) self::CROP_PX,
        ], 60);
        if (empty($r['ok'])) {
            return ['type' => 'tool_result', 'tool_use_id' => $id, 'is_error' => true, 'content' => 'Crop failed: '.($r['error'] ?? '?')];
        }

        return ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => [$this->imageBlock($target)]];
    }

    private function imageBlock(string $path): array
    {
        return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode((string) file_get_contents($path))]];
    }

    /** The test itself: object geometry, what it was printed with, and what earlier tests of this machine showed. */
    private function brief(FarmOrder $order): string
    {
        $tp = (array) $order->test_params;
        $object = (string) ($tp['object'] ?? 'quick');
        $c = (array) ($tp['candidate'] ?? []);
        $lines = ['Test '.$order->number.': object "'.$object.'", printed on '.($order->printer?->name ?? '?')
            .' with '.($order->material?->name ?? '?').' '.($order->color?->name ?? '').', plate '.($order->printer?->process_overrides['curr_bed_type'] ?? '?').'.'];
        $lines[] = 'Nozzle '.($c['nozzle_temp'] ?? '?').' °C (first layer '.($c['nozzle_temp_first'] ?? '?').' °C), bed '.($c['bed_temp'] ?? '?').' °C'
            .(! empty($tp['ironing']) ? ', the plateau was ironed' : ', no ironing').'.';
        $lines[] = 'Features on the plate (positions in mm from the plate corner):';
        foreach ((array) ($tp['features'] ?? []) as $f) {
            $lines[] = '- '.json_encode($f, JSON_UNESCAPED_SLASHES);
        }
        if ($object === 'temp_tower') {
            $lines[] = 'This is a temperature tower; floors bottom first: '.implode(', ', (array) ($tp['temps'] ?? [])).' °C. Judge the fields for the tower as a whole.';
        }
        if ($object !== 'detailed') {
            $lines[] = 'There is no ironed plateau on this object: answer ironing "unknown".';
        }

        $earlier = FarmOrder::where('kind', FarmOrder::KIND_TEST)->where('farm_printer_id', $order->farm_printer_id)
            ->where('id', '<', $order->id)->latest('id')->limit(6)->get()
            ->filter(fn (FarmOrder $o) => ! empty($o->test_params['result']));
        if ($earlier->isNotEmpty()) {
            $lines[] = 'Earlier tests on this machine, as the operator confirmed them:';
            foreach ($earlier as $o) {
                $r = (array) $o->test_params['result'];
                $lines[] = '- '.$o->number.' ('.($o->material?->name ?? '?').' '.($o->color?->name ?? '').', nozzle '.($o->test_params['candidate']['nozzle_temp'] ?? '?').' °C): '
                    .json_encode(array_diff_key($r, ['note' => 1]), JSON_UNESCAPED_UNICODE).(! empty($r['note']) ? ' — '.$r['note'] : '');
            }
        }
        $lines[] = 'Inspect the photos, zoom into the details, then submit the evaluation.';

        return implode("\n", $lines);
    }

    private function result(array $in, string $model, int $crops, array $usage): array
    {
        $fields = [];
        foreach (self::FIELDS as $k => $values) {
            $f = (array) ($in[$k] ?? []);
            $value = in_array($f['value'] ?? null, $values, true) ? $f['value'] : null;
            $fields[$k] = ['value' => $value, 'confidence' => in_array($f['confidence'] ?? '', ['low', 'medium', 'high'], true) ? $f['confidence'] : 'low', 'reason' => mb_substr((string) ($f['reason'] ?? ''), 0, 300)];
        }
        $score = (int) ($in['score'] ?? 0);

        return [
            'fields' => $fields,
            'score' => $score >= 1 && $score <= 5 ? $score : null,
            'note' => mb_substr((string) ($in['note'] ?? ''), 0, 500),
            'better_photos' => mb_substr((string) ($in['better_photos'] ?? ''), 0, 300),
            'model' => $model,
            'crops' => $crops,
            'usage' => $usage,
        ];
    }
}
