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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Filament art (engines/python/art_tool.py): a picture in the colours of the farm's filaments as a thing for the wall.
 * Two ways: one print with the colours as steps (the printer changes filament by height), or a layered picture of
 * one plate per colour with spacers and a frame, every plate printed on its own in its colour.
 * The picture goes through shape2d.colors like the pendants and coasters do; what differs is written here.
 */
final class ArtGenerator
{
    public const KIND = 'filament_art';

    /** field → [min, max, default, step] */
    public const FIELDS = [
        'width' => [50, 250, 150, 1], 'height' => [50, 250, 150, 1], 'margin' => [0, 20, 6, 1],
        // the picture: how many colours, how much of the background goes, how smooth the edges are, the photo's own sliders
        'colors_n' => [1, 8, 5, 1], 'bg_strength' => [0, 100, 30, 1], 'smooth' => [0, 1, 0.3, 0.05], 'contrast' => [50, 150, 100, 1], 'brightness' => [50, 150, 100, 1], 'saturation' => [0, 200, 100, 1],
        // one print: the base plate and the step of every colour (whole 0.2 mm layers); layered: the plates and the air between them
        'base' => [0.8, 3, 1.2, 0.2], 'step' => [0.4, 1.2, 0.4, 0.2], 'plate' => [1.5, 3, 2, 0.5], 'gap' => [0, 5, 3, 0.5], 'frame_w' => [6, 20, 10, 1],
    ];

    public const CHOICES = ['mode' => ['layered', 'stack'], 'shape' => ['image', 'rect', 'circle'], 'frame' => ['none', 'round', 'square']];

    public const FLAGS = ['remove_bg', 'flat', 'led'];

    public const FLAGS_ON = ['remove_bg'];

    public const FOLDED = ['contrast', 'brightness', 'saturation'];

    /** the picture a new visitor starts with (a tool that needs a picture must not open empty) */
    public const SAMPLE = 'lib:colour/snowman';

    public const PART = '/^(body|frame|color_[1-8]|plate_[1-8])$/';

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /** Laravel rules (the API); the same numbers are printed into the form as min/max. */
    public static function rules(): array
    {
        $rules = [];
        foreach (self::FIELDS as $key => [$min, $max, , $step]) {
            $rules['params.'.$key] = ['nullable', $step === 1 ? 'integer' : 'numeric', 'min:'.$min, 'max:'.$max];
        }
        foreach (self::FLAGS as $flag) {
            $rules['params.'.$flag] = ['nullable', 'boolean'];
        }
        foreach (self::CHOICES as $key => $options) {
            $rules['params.'.$key] = ['nullable', Rule::in($options)];
        }
        $rules['params.artwork'] = ['required', 'string', 'max:120', 'regex:/^(lib:[a-z0-9-]+\/[a-z0-9-]+|file:[0-9a-f-]{36}|[0-9a-f-]{36})$/'];
        $rules['params.part_colors'] = ['nullable', 'array', 'max:16'];
        $rules['params.part_colors.*.code'] = ['required', 'string', 'max:40'];
        $rules['params.part_colors.*.hex'] = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $rules['params.merge'] = ['nullable', 'array', 'max:8'];
        $rules['params.merge.*'] = ['array', 'size:2'];
        $rules['params.merge.*.*'] = ['integer', 'min:1', 'max:8'];
        $rules['params.order'] = ['nullable', 'array', 'max:8'];
        $rules['params.order.*'] = ['integer', 'min:1', 'max:8'];

        return $rules;
    }

    /** Only what the tool knows, in its limits; everything else is dropped. */
    public static function clean(array $p): array
    {
        $out = [];
        foreach (self::FIELDS as $key => [$min, $max, $default]) {
            $v = isset($p[$key]) && is_numeric($p[$key]) ? (float) $p[$key] : $default;
            $out[$key] = max($min, min($max, $v));
        }
        foreach (self::FLAGS as $flag) {
            $out[$flag] = array_key_exists($flag, $p) ? filter_var($p[$flag], FILTER_VALIDATE_BOOLEAN) : in_array($flag, self::FLAGS_ON, true);
        }
        foreach (self::CHOICES as $key => $options) {
            $out[$key] = in_array($p[$key] ?? null, $options, true) ? $p[$key] : $options[0];
        }
        $out['artwork'] = (string) ($p['artwork'] ?? '');
        $palette = app(\App\Domain\Farm\Palette::class);
        $colors = [];
        foreach ((array) ($p['part_colors'] ?? []) as $part => $c) {
            $code = is_array($c) ? (string) ($c['code'] ?? '') : (string) $c;
            if (is_string($part) && preg_match(self::PART, $part) && $code !== '') {
                $hex = is_array($c) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($c['hex'] ?? '')) ? (string) $c['hex'] : ($palette->hex($code) ?? '#888888');
                $colors[$part] = ['code' => $code, 'hex' => $hex];
            }
        }
        if ($colors) {
            $out['part_colors'] = $colors;
        }
        $merge = array_values(array_filter(array_map(fn ($pair) => is_array($pair) && count($pair) === 2 ? [(int) $pair[0], (int) $pair[1]] : null, (array) ($p['merge'] ?? []))));
        if ($merge) {
            $out['merge'] = $merge;
        }
        $order = array_values(array_unique(array_map('intval', array_filter((array) ($p['order'] ?? []), 'is_numeric'))));
        if ($order) {
            $out['order'] = $order;
        }

        return $out;
    }

    /**
     * @return array{path: string, meta: array<string, mixed>} temporary STL (caller deletes) + size, volume, notes, parts
     */
    public function build(array $params, string $view = 'use', bool $pieces = true, ?string $partsDir = null): array
    {
        $clean = self::clean($params);
        $clean['view'] = $view === 'print' ? 'print' : 'use';
        $clean['palette'] = ParametricGenerator::spools();
        $clean['artwork_path'] = $clean['artwork'] !== '' ? ParametricGenerator::artworkPath($clean['artwork']) : null;
        if (! $clean['artwork_path']) {
            throw ValidationException::withMessages(['params' => [__($clean['artwork'] === '' ? 'param.error.no_text' : 'param.error.artwork_gone')]])->status(422);
        }
        $dir = storage_path('app/tmp/param');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.Str::uuid().'.stl';
        $json = $dir.'/'.Str::uuid().'.json';
        File::put($json, (string) json_encode($clean, JSON_UNESCAPED_UNICODE));
        try {
            $r = $this->python->runScript('art_tool.py', [$path, '@'.$json, ...($pieces || $partsDir ? ['parts'] : []), ...($partsDir ? [$partsDir] : [])], 90);
        } finally {
            @unlink($json);
        }
        if (empty($r['ok']) || ! is_file($path)) {
            @unlink($path);
            throw ValidationException::withMessages(['params' => [ParametricGenerator::explain((string) ($r['code'] ?? 'failed'), (string) ($r['error'] ?? ''))]])->status(422);
        }

        return ['path' => $path, 'meta' => ['part' => 'all', 'bbox' => $r['bbox'], 'volume_mm3' => $r['volume_mm3'], 'area_mm2' => $r['area_mm2'], 'triangles' => $r['triangles'], 'notes' => $r['notes'] ?? []] + ($pieces ? ['parts' => $r['parts'] ?? []] : [])];
    }

    /**
     * The design as a model file: the pieces laid out for printing as one STL, every piece beside it as its own STL,
     * the filaments and the guide kept with the design.
     */
    public function create(array $params, ?AnonymousSession $session, ?User $user): ModelFile
    {
        $clean = self::clean($params);
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));
        try {
            $built = $this->build($clean, 'print', true, dirname($abs).'/parts');
        } catch (\Throwable $e) {
            File::deleteDirectory(dirname($abs));
            throw $e;
        }
        File::move($built['path'], $abs);
        if (($src = ParametricGenerator::artworkPath($clean['artwork']))) {
            File::copy($src, dirname($abs).'/artwork.'.pathinfo($src, PATHINFO_EXTENSION));
            $clean['artwork'] = 'file:'.$uuid;
        }
        $notes = $built['meta']['notes'];
        $clean['parts'] = array_values((array) ($notes['parts'] ?? []));
        // the filaments the tool chose are kept with the design: opened again next month it looks the same
        $chosen = ['body' => $notes['body_color'] ?? null, 'frame' => $notes['frame_color'] ?? null] + array_column((array) ($notes['colors'] ?? []), null, 'part');
        foreach ($chosen as $part => $color) {
            if (is_array($color) && ! empty($color['code']) && ! isset($clean['part_colors'][$part]) && in_array($part, $clean['parts'], true)) {
                $clean['part_colors'][$part] = ['code' => (string) $color['code'], 'hex' => (string) $color['hex']];
            }
        }
        $clean['color_changes'] = array_values((array) ($notes['color_changes'] ?? []));
        if (isset($notes['color_change_mm'])) {
            $clean['color_change_mm'] = (float) $notes['color_change_mm'];
        }
        $clean['multi_material'] = false;
        $clean['paint'] = (array) ($notes['paint'] ?? []);
        $clean['guide'] = array_map(fn ($g) => array_diff_key((array) $g, ['svg' => 1, 'own_svg' => 1]), (array) ($notes['guide'] ?? []));
        $clean['parts_bbox'] = array_map(fn ($p) => [round($p['bbox'][3] - $p['bbox'][0], 2), round($p['bbox'][4] - $p['bbox'][1], 2), round($p['bbox'][5] - $p['bbox'][2], 2)], array_column((array) ($built['meta']['parts'] ?? []), null, 'name'));
        $clean['pieces'] = array_values((array) ($built['meta']['parts'] ?? []));
        $clean['each'] = (array) ($notes['each'] ?? []);
        $clean['report'] = array_intersect_key($notes, array_flip(['mode', 'shape', 'frame', 'plates', 'depth', 'filaments', 'outer', 'picture', 'warnings', 'found', 'wanted', 'frame_outer', 'frame_depth']));
        // the guide with its drawings is bigger than a row should carry: it lives beside the model
        File::put(dirname($abs).'/guide.json', (string) json_encode(['guide' => $notes['guide'] ?? [], 'colors' => $notes['colors'] ?? [], 'paint' => $notes['paint'] ?? []]));

        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'original_name' => ($clean['mode'] === 'layered' ? 'layered-picture' : 'filament-art').'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => self::KIND, 'status' => ModelFile::STATUS_UPLOADED, 'tool_params' => $clean,
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }

    /** The guide kept beside a layered picture: the plates back to front with their drawings; null for other designs. */
    public static function guide(ModelFile $file): ?array
    {
        if ($file->kind() !== self::KIND) {
            return null;
        }
        $path = dirname(Storage::disk(ModelFile::DISK)->path($file->storage_path)).'/guide.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Every piece of a design as its own STL in one ZIP, with the whole set as it is laid out.
     *
     * @return string path of a temporary ZIP (caller deletes)
     */
    public function zip(array $params): string
    {
        $dir = storage_path('app/tmp/param/'.Str::uuid());
        $built = $this->build($params, 'print', true, $dir);
        $path = storage_path('app/tmp/param/'.Str::uuid().'.zip');
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($built['path']);
            File::deleteDirectory($dir);
            throw new EngineException('The archive could not be written.');
        }
        $zip->addFile($built['path'], 'filament-art.stl');
        foreach (File::files($dir) as $piece) {
            $zip->addFile($piece->getPathname(), 'filament-art-'.$piece->getFilename());
        }
        $zip->close();
        @unlink($built['path']);
        File::deleteDirectory($dir);

        return $path;
    }
}
