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
            'rows' => [1, 8, 2, 1], 'cols' => [1, 8, 3, 1], 'radius' => [0, 20, 4, 0.5], 'wall' => [0.8, 4, 1.6, 0.2], 'floor' => [0.8, 4, 1.2, 0.2],
        ],
        'box' => [
            'inner_w' => [10, 300, 80, 1], 'inner_d' => [10, 300, 50, 1], 'inner_h' => [8, 200, 30, 1],
            'wall' => [1.2, 5, 2, 0.2], 'floor' => [1, 5, 1.6, 0.2], 'clearance' => [0.1, 0.6, 0.25, 0.05], 'radius' => [0, 30, 2.5, 0.5],
        ],
        'phone_stand' => [
            'width' => [50, 260, 70, 1], 'device' => [7, 20, 12, 1], 'angle' => [35, 80, 65, 1], 'back' => [60, 200, 100, 1], 'thickness' => [3, 8, 5, 0.5], 'radius' => [0, 4, 2, 0.1], 'depth' => [40, 120, 60, 1], 'vent' => [1, 4, 1.5, 0.1],
        ],
        'cable_holder' => [
            'count' => [1, 8, 4, 1], 'cable' => [3, 14, 6, 0.5], 'depth' => [10, 80, 45, 1], 'wall' => [2, 12, 7, 0.5], 'radius' => [0, 6, 3, 0.5],
        ],
        'modular' => [
            'inner_w' => [60, 600, 300, 1], 'inner_d' => [60, 600, 150, 1], 'height' => [15, 120, 40, 1], 'cols' => [1, 12, 6, 1], 'rows' => [1, 12, 3, 1],
            'radius' => [0, 15, 6, 0.5], 'wall' => [0.8, 3, 1.6, 0.2], 'floor' => [0.8, 3, 1.2, 0.2], 'gap' => [0.3, 1.5, 0.6, 0.1],
        ],
        'vase' => [
            'height' => [40, 300, 180, 1], 'top_d' => [30, 250, 62, 1], 'bottom_d' => [30, 250, 54, 1], 'wall' => [0.8, 4, 1.6, 0.2], 'floor' => [0.8, 5, 1.6, 0.2],
            'ribs' => [6, 48, 20, 1], 'flute' => [0, 45, 20, 1], 'twist' => [0, 360, 200, 1],
        ],
        'sign' => ['text_height' => [4, 80, 12, 1], 'thickness' => [1.2, 10, 3, 0.2], 'relief' => [0.4, 5, 1.2, 0.2], 'margin' => [2, 30, 5, 1], 'radius' => [0, 30, 6, 0.5]],
        'logo' => ['width' => [20, 250, 80, 1], 'thickness' => [0.6, 10, 2, 0.2], 'plate' => [0.8, 6, 2, 0.2], 'margin' => [0, 20, 5, 1], 'base_h' => [8, 40, 11, 1]],
        'stamp' => ['width' => [15, 120, 50, 1], 'relief' => [0.8, 4, 1.6, 0.2], 'plate' => [2, 6, 3, 0.5]],
        'qr' => ['size' => [30, 150, 70, 1], 'plate' => [1.6, 4, 2.4, 0.2], 'relief' => [0.6, 2, 1, 0.2]],
        'stencil' => ['width' => [30, 250, 120, 1], 'thickness' => [0.8, 3, 1.2, 0.2], 'margin' => [5, 40, 12, 1], 'bridge' => [0.8, 3, 1.2, 0.2]],
        'lightbox' => [
            'width' => [80, 300, 180, 1], 'depth' => [25, 80, 35, 1], 'wall' => [1.6, 4, 2, 0.2], 'face' => [0.8, 2, 1.2, 0.2], 'margin' => [6, 40, 12, 1],
            'bridge' => [0.8, 3, 1.4, 0.2], 'cable' => [3, 10, 5, 0.5], 'clearance' => [0.1, 0.6, 0.25, 0.05],
        ],
    ];

    /** kind → choice → allowed values (the first one is the default) */
    public const CHOICES = [
        'phone_stand' => ['style' => ['plate', 'wave', 'desk', 'wedge', 'wall', 'car']],
        'vase' => ['purpose' => ['vase', 'pot'], 'profile' => ['neck', 'belly', 'cone', 'tulip'], 'style' => ['twist', 'ribs', 'smooth']],
        'sign' => ['style' => ['emboss', 'engrave', 'outline'], 'shape' => ['rounded', 'rect', 'oval'], 'typeface' => ['sans', 'serif', 'mono']],
        'logo' => ['mode' => ['relief', 'height', 'cutout', 'standing'], 'shape' => ['rounded', 'rect', 'circle']],
        'stamp' => ['mode' => ['raised', 'recessed'], 'handle' => ['knob', 'none']],
        'lightbox' => ['led' => ['strip8', 'strip10', 'module']],
    ];

    /** kind → text input → [max length, required, default] */
    public const TEXTS = [
        'sign' => ['line1' => [40, true, 'Jana'], 'line2' => [40, false, '']],
        'logo' => ['line1' => [30, false, 'LOGO'], 'line2' => [30, false, '']],
        'stamp' => ['line1' => [20, false, 'EVA'], 'line2' => [20, false, '']],
        'qr' => ['url' => [300, true, 'https://matplace.com'], 'label' => [40, false, 'matplace.com']],
        'stencil' => ['line1' => [24, false, 'BOA 8'], 'line2' => [24, false, '']],
        'lightbox' => ['line1' => [16, false, 'OPEN'], 'line2' => [16, false, '']],
    ];

    /** kinds that accept an uploaded SVG or picture instead of text */
    public const ARTWORK = ['logo', 'stamp', 'stencil', 'lightbox'];

    /** the fields shown first; everything else sits under "more" */
    public const MAIN = [
        'organizer' => ['width', 'depth', 'height', 'rows', 'cols', 'radius'], 'box' => ['inner_w', 'inner_d', 'inner_h', 'radius'], 'phone_stand' => ['width', 'device', 'angle', 'back', 'depth', 'vent', 'thickness', 'radius'],
        'cable_holder' => ['count', 'cable', 'depth'], 'modular' => ['inner_w', 'inner_d', 'height', 'cols', 'rows', 'radius'], 'vase' => ['height', 'top_d', 'bottom_d', 'ribs', 'flute', 'twist'], 'sign' => ['text_height', 'thickness', 'relief', 'radius'], 'logo' => ['width', 'thickness', 'base_h'], 'stamp' => ['width', 'relief'], 'qr' => ['size'], 'stencil' => ['width', 'margin'], 'lightbox' => ['width', 'depth'],
    ];

    public const PARTS = ['all', 'body', 'lid', 'saucer', 'handle', 'stand', 'imprint', 'face', 'diffuser', 'back', 'plate', 'text'];

    public const FLAGS = ['box' => ['lid'], 'phone_stand' => ['cable', 'window', 'screws'], 'cable_holder' => ['screws'], 'modular' => ['tray'], 'vase' => ['drainage', 'saucer'], 'sign' => ['keyring', 'border', 'bevel', 'two_color'], 'logo' => ['invert'], 'stamp' => ['invert'], 'stencil' => ['invert'], 'lightbox' => ['invert'], 'qr' => ['stand', 'hole']];

    /** kind → field or flag → [choice key, values it belongs to]; the form hides it for the other choices */
    public const WHEN = [
        'phone_stand' => ['angle' => ['style', ['plate', 'wave', 'desk', 'wedge']], 'back' => ['style', ['plate', 'wave', 'desk']], 'depth' => ['style', ['wedge']], 'vent' => ['style', ['car']], 'thickness' => ['style', ['plate', 'wave', 'desk', 'wall', 'car']], 'cable' => ['style', ['wave', 'desk', 'wedge', 'wall', 'car']], 'window' => ['style', ['desk']], 'screws' => ['style', ['wall']]],
        'vase' => ['drainage' => ['purpose', ['pot']], 'saucer' => ['purpose', ['pot']], 'ribs' => ['style', ['twist', 'ribs']], 'flute' => ['style', ['twist', 'ribs']], 'twist' => ['style', ['twist']]],
        'sign' => ['radius' => ['shape', ['rounded']], 'border' => ['style', ['emboss', 'outline']], 'two_color' => ['style', ['emboss', 'outline']]],
    ];

    /** flags that start switched on */
    public const FLAGS_ON = ['cable', 'window', 'drainage', 'saucer', 'border'];

    public const PRESETS = [
        'vase' => [
            'spiral' => ['style' => 'twist', 'profile' => 'neck', 'height' => 180, 'top_d' => 62, 'bottom_d' => 54, 'ribs' => 20, 'flute' => 20, 'twist' => 200],
            'ribs' => ['style' => 'ribs', 'profile' => 'neck', 'height' => 170, 'top_d' => 70, 'bottom_d' => 60, 'ribs' => 18, 'flute' => 16],
            'smooth' => ['style' => 'smooth', 'profile' => 'belly', 'height' => 150, 'top_d' => 80, 'bottom_d' => 70],
            'pot' => ['purpose' => 'pot', 'style' => 'ribs', 'profile' => 'cone', 'height' => 120, 'top_d' => 130, 'bottom_d' => 100, 'ribs' => 16, 'flute' => 10],
        ],
        'organizer' => [
            'drawer' => ['width' => 300, 'depth' => 200, 'height' => 45, 'rows' => 2, 'cols' => 4, 'radius' => 4, 'wall' => 1.6, 'floor' => 1.2],
            'office' => ['width' => 200, 'depth' => 100, 'height' => 60, 'rows' => 1, 'cols' => 3, 'wall' => 1.6, 'floor' => 1.2],
            'parts' => ['width' => 160, 'depth' => 120, 'height' => 25, 'rows' => 4, 'cols' => 5, 'wall' => 1.2, 'floor' => 1.0],
        ],
    ];

    public const MAX_HOLES = 8;

    public const MAX_BINS = 24;

    public const COLORS = ['white', 'black', 'grey', 'brown', 'red', 'blue', 'green', 'yellow', 'orange'];

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
        foreach (self::CHOICES[$kind] ?? [] as $key => $options) {
            $rules['params.'.$key] = ['nullable', 'in:'.implode(',', $options)];
        }
        foreach (self::TEXTS[$kind] ?? [] as $key => [$max, $required]) {
            $rules['params.'.$key] = [$required ? 'required' : 'nullable', 'string', 'max:'.$max];
        }
        if (in_array($kind, self::ARTWORK, true)) {
            $rules['params.artwork'] = ['nullable', 'string', 'regex:/^(file:)?[0-9a-f-]{36}$/'];
        }
        if ($kind === 'modular') {
            $rules += [
                'params.bins' => ['required', 'array', 'min:1', 'max:'.self::MAX_BINS],
                'params.bins.*.x' => ['required', 'integer', 'min:0', 'max:11'],
                'params.bins.*.y' => ['required', 'integer', 'min:0', 'max:11'],
                'params.bins.*.w' => ['required', 'integer', 'min:1', 'max:12'],
                'params.bins.*.h' => ['required', 'integer', 'min:1', 'max:12'],
                'params.bins.*.color' => ['nullable', 'in:'.implode(',', self::COLORS)],
            ];
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
            $out[$flag] = filter_var($p[$flag] ?? in_array($flag, self::FLAGS_ON, true), FILTER_VALIDATE_BOOLEAN);
        }
        foreach (self::CHOICES[$kind] ?? [] as $key => $options) {
            $out[$key] = in_array($p[$key] ?? null, $options, true) ? $p[$key] : $options[0];
        }
        foreach (self::TEXTS[$kind] ?? [] as $key => [$max, , $default]) {
            // an emptied field stays empty (the framework turns '' into null); the default is only for a field that was never sent
            $out[$key] = mb_substr(trim((string) (array_key_exists($key, $p) ? ($p[$key] ?? '') : $default)), 0, $max);
        }
        if (in_array($kind, self::ARTWORK, true) && ! empty($p['artwork'])) {
            $out['artwork'] = (string) $p['artwork'];
        }
        if ($kind === 'modular') {
            $out['bins'] = array_values(array_map(fn ($b) => [
                'x' => (int) $b['x'], 'y' => (int) $b['y'], 'w' => (int) $b['w'], 'h' => (int) $b['h'],
                'color' => in_array($b['color'] ?? null, self::COLORS, true) ? $b['color'] : 'white',
            ], array_slice((array) ($p['bins'] ?? []), 0, self::MAX_BINS)));
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
        $r = $this->python->runScript('param_tool.py', [$kind, $path, json_encode($this->forTool($kind, self::clean($kind, $params)), JSON_UNESCAPED_UNICODE), $part, $view], 60);
        if (empty($r['ok']) || ! is_file($path)) {
            @unlink($path);
            $code = (string) ($r['code'] ?? 'failed');
            // geometry the numbers cannot make (cells too small, openings that collide…): a form error, not a crash
            throw ValidationException::withMessages(['params' => [self::explain($code, (string) ($r['error'] ?? ''))]])->status(422);
        }

        return ['path' => $path, 'meta' => ['part' => $r['part'] ?? 'all', 'bbox' => $r['bbox'], 'volume_mm3' => $r['volume_mm3'], 'area_mm2' => $r['area_mm2'], 'triangles' => $r['triangles'], 'notes' => $r['notes'] ?? []]];
    }

    /** Adds what only the server knows: the font file and where the uploaded artwork lives. */
    private function forTool(string $kind, array $clean): array
    {
        if (isset(self::TEXTS[$kind]) || in_array($kind, self::ARTWORK, true)) {
            $face = ['serif' => 'DejaVuSerif-Bold.ttf', 'mono' => 'DejaVuSansMono-Bold.ttf'][$clean['typeface'] ?? ''] ?? 'DejaVuSans-Bold.ttf';
            $clean['font'] = base_path('vendor/dompdf/dompdf/lib/fonts/'.$face);
            $clean['lines'] = array_values(array_filter([$clean['line1'] ?? '', $clean['line2'] ?? ''], fn ($l) => $l !== ''));
        }
        if (! empty($clean['artwork'])) {
            $clean['artwork_path'] = self::artworkPath($clean['artwork']);
            if (! $clean['artwork_path']) {
                throw ValidationException::withMessages(['params' => [__('param.error.artwork_gone')]])->status(422);
            }
        } elseif (in_array($kind, self::ARTWORK, true) && empty($clean['lines'])) {
            throw ValidationException::withMessages(['params' => [__('param.error.no_text')]])->status(422);
        }

        return $clean;
    }

    /** "<uuid>" = fresh upload (kept for a day), "file:<uuid>" = artwork stored with a created model (kept with it). */
    public static function artworkPath(string $ref): ?string
    {
        $stored = str_starts_with($ref, 'file:');
        $id = $stored ? substr($ref, 5) : $ref;
        $pattern = $stored ? Storage::disk(ModelFile::DISK)->path('files/'.$id.'/artwork.*') : storage_path('app/tmp/artwork/'.$id.'.*');
        $hit = File::glob($pattern);

        return $hit ? str_replace('\\', '/', $hit[0]) : null;
    }

    /** Uploaded SVG or picture → a reference the form sends along with the numbers. */
    public static function storeArtwork(\Illuminate\Http\UploadedFile $file): string
    {
        $id = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension()) === 'svg' ? 'svg' : (['image/png' => 'png', 'image/webp' => 'webp'][$file->getMimeType()] ?? 'jpg');
        File::ensureDirectoryExists(storage_path('app/tmp/artwork'));
        File::copy($file->getRealPath(), storage_path('app/tmp/artwork/'.$id.'.'.$ext));

        return $id;
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
        if (! empty($clean['artwork']) && ($src = self::artworkPath($clean['artwork']))) {
            File::copy($src, dirname($abs).'/artwork.'.pathinfo($src, PATHINFO_EXTENSION));
            $clean['artwork'] = 'file:'.$uuid;
        }

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
