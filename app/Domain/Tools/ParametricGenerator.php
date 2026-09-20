<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\ProcessModelFile;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Made-to-measure products from a handful of numbers: organizer, box with a lid and openings, phone stand, cable holder.
 * Exact solids from engines/python/param_tool.py. Limits live here (web layer) and again in the tool itself.
 */
final class ParametricGenerator
{
    /** kind → field → [min, max, default, step]; integers have step 1 */
    public const FIELDS = [
        'organizer' => [
            'width' => [30, 400, 200, 1], 'depth' => [30, 400, 120, 1], 'height' => [10, 150, 40, 1],
            'rows' => [1, 8, 2, 1], 'cols' => [1, 8, 3, 1], 'wall' => [0.8, 4, 1.6, 0.2], 'floor' => [0.8, 4, 1.2, 0.2],
        ],
        'box' => [
            'inner_w' => [10, 300, 80, 1], 'inner_d' => [10, 300, 50, 1], 'inner_h' => [8, 200, 30, 1],
            'wall' => [1.2, 5, 2, 0.2], 'floor' => [1, 5, 1.6, 0.2], 'clearance' => [0.1, 0.6, 0.25, 0.05],
        ],
        'phone_stand' => [
            'width' => [50, 140, 70, 1], 'device' => [7, 20, 12, 1], 'angle' => [50, 80, 65, 1], 'back' => [60, 150, 100, 1], 'thickness' => [3, 8, 3.5, 0.5],
        ],
        'cable_holder' => [
            'count' => [1, 8, 3, 1], 'cable' => [3, 14, 6, 0.5], 'depth' => [10, 40, 20, 1], 'wall' => [2, 5, 3, 0.5],
        ],
    ];

    public const FLAGS = ['box' => ['lid'], 'phone_stand' => ['cable'], 'cable_holder' => ['screws']];

    public const PRESETS = [
        'organizer' => [
            'drawer' => ['width' => 300, 'depth' => 200, 'height' => 45, 'rows' => 2, 'cols' => 4, 'wall' => 1.6, 'floor' => 1.2],
            'office' => ['width' => 200, 'depth' => 100, 'height' => 60, 'rows' => 1, 'cols' => 3, 'wall' => 1.6, 'floor' => 1.2],
            'parts' => ['width' => 160, 'depth' => 120, 'height' => 25, 'rows' => 4, 'cols' => 5, 'wall' => 1.2, 'floor' => 1.0],
        ],
    ];

    public const MAX_HOLES = 8;

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /** Laravel rules for one kind (used by the API; the same numbers are printed into the form as min/max). */
    public static function rules(string $kind): array
    {
        $rules = [];
        foreach (self::FIELDS[$kind] as $key => [$min, $max, , $step]) {
            $rules['params.'.$key] = ['nullable', $step === 1 ? 'integer' : 'numeric', 'min:'.$min, 'max:'.$max];
        }
        foreach (self::FLAGS[$kind] ?? [] as $flag) {
            $rules['params.'.$flag] = ['nullable', 'boolean'];
        }
        if ($kind === 'box') {
            $rules += [
                'params.holes' => ['nullable', 'array', 'max:'.self::MAX_HOLES],
                'params.holes.*.wall' => ['required', 'in:front,back,left,right'],
                'params.holes.*.shape' => ['required', 'in:circle,rect'],
                'params.holes.*.w' => ['required', 'numeric', 'min:2', 'max:200'],
                'params.holes.*.h' => ['nullable', 'numeric', 'min:2', 'max:200'],
                'params.holes.*.x' => ['required', 'numeric', 'min:0', 'max:300'],
                'params.holes.*.z' => ['required', 'numeric', 'min:0', 'max:200'],
            ];
        }

        return $rules;
    }

    /** Known keys only, numbers as numbers, defaults filled in: what is stored and what the tool receives. */
    public static function clean(string $kind, array $p): array
    {
        $out = [];
        foreach (self::FIELDS[$kind] as $key => [, , $default, $step]) {
            $v = $p[$key] ?? $default;
            $out[$key] = $step === 1 ? (int) $v : round((float) $v, 2);
        }
        foreach (self::FLAGS[$kind] ?? [] as $flag) {
            $out[$flag] = filter_var($p[$flag] ?? ($flag === 'cable'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($kind === 'box') {
            $out['holes'] = array_values(array_map(fn ($h) => [
                'wall' => (string) $h['wall'], 'shape' => (string) $h['shape'], 'w' => round((float) $h['w'], 1),
                'h' => round((float) ($h['h'] ?? $h['w']), 1), 'x' => round((float) $h['x'], 1), 'z' => round((float) $h['z'], 1),
            ], array_slice((array) ($p['holes'] ?? []), 0, self::MAX_HOLES)));
        }

        return $out;
    }

    /**
     * @return array{path: string, meta: array<string, mixed>} temporary STL (caller deletes) + size, volume, notes
     */
    public function build(string $kind, array $params, string $part = 'all', string $view = 'print'): array
    {
        if (! isset(self::FIELDS[$kind])) {
            throw new EngineException('Unknown product.');
        }
        $dir = storage_path('app/tmp/param');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.Str::uuid().'.stl';
        $r = $this->python->runScript('param_tool.py', [$kind, $path, json_encode(self::clean($kind, $params)), $part, $view], 30);
        if (empty($r['ok']) || ! is_file($path)) {
            @unlink($path);
            $code = (string) ($r['code'] ?? 'failed');
            // geometry the numbers cannot make (cells too small, openings that collide…): a form error, not a crash
            throw ValidationException::withMessages(['params' => [self::explain($code, (string) ($r['error'] ?? ''))]])->status(422);
        }

        return ['path' => $path, 'meta' => ['bbox' => $r['bbox'], 'volume_mm3' => $r['volume_mm3'], 'area_mm2' => $r['area_mm2'], 'triangles' => $r['triangles'], 'notes' => $r['notes'] ?? []]];
    }

    public static function explain(string $code, string $raw = ''): string
    {
        $key = 'param.error.'.$code;
        $n = preg_match('/:\s*(.+)$/', $raw, $m) ? $m[1] : '';
        $text = __($key, ['n' => $n]);

        return $text === $key ? __('param.error.failed') : $text;
    }

    public function create(string $kind, array $params, ?AnonymousSession $session, ?User $user): ModelFile
    {
        $clean = self::clean($kind, $params);
        $built = $this->build($kind, $clean);
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));
        File::move($built['path'], $abs);

        $o = $built['meta']['notes']['outer'] ?? [$built['meta']['bbox']['x'], $built['meta']['bbox']['y'], $built['meta']['bbox']['z']];
        $name = str_replace('_', '-', $kind).'-'.implode('x', array_map(fn ($v) => (string) round((float) $v), $o));
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'original_name' => $name.'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => $kind, 'tool_params' => $clean, 'status' => ModelFile::STATUS_UPLOADED,
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }
}
