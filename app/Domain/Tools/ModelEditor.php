<?php

namespace App\Domain\Tools;

use App\Domain\Farm\FarmSettings;
use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\EditModel;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Editing a ready model (engines/python/edit_tool.py): split it so it fits a print bed, scale it… The result is a new
 * ModelFile of the operation's kind, with every piece beside it as its own STL and a report of what was done. Long
 * work runs in the queue (App\Jobs\EditModel) with its phases timed; the page asks /api/files/{uuid} for the state.
 */
final class ModelEditor
{
    public const KINDS = ['split', 'hollow', 'life_size', 'puzzle', 'holder', 'potion', 'scale'];

    /** print beds a model is cut for: the farm's, two common ones, or the visitor's own (usable size = bed − margins) */
    public const BEDS = ['farm' => [250, 250, 250], '220' => [220, 220, 250], '180' => [180, 180, 180], 'custom' => null];

    public const MARGIN = 5.0;

    /** op → field → [min, max, default, step] */
    public const FIELDS = [
        'split' => ['bed_x' => [50, 600, 250, 1], 'bed_y' => [50, 600, 250, 1], 'bed_z' => [50, 600, 250, 1]],
        // the wall left round the cavity, the drain holes through the lowest wall (0 = as many as there is room for)
        'hollow' => ['wall' => [1.5, 6, 2.5, 0.5], 'drain_d' => [3, 8, 5, 0.5], 'drains' => [0, 4, 0, 1]],
        // the height the model is to stand at, in centimetres; the bed it is cut for
        'life_size' => ['height_cm' => [5, 100, 40, 0.5], 'bed_x' => [50, 600, 250, 1], 'bed_y' => [50, 600, 250, 1], 'bed_z' => [50, 600, 250, 1]],
        // a jigsaw of a flat model: the grid, the head of a knob in % of a piece's side, the play of the knobs
        'puzzle' => ['rows' => [2, 8, 3, 1], 'cols' => [2, 8, 4, 1], 'knob' => [25, 45, 35, 1], 'clearance' => [0.1, 0.4, 0.2, 0.05]],
        // a holder out of a model: the model's height (0 = as it is), the play round the thing, the cavity's own sizes (custom) and offset
        'holder' => ['height' => [0, 400, 0, 1], 'clearance' => [0.3, 1.5, 0.6, 0.1], 'cav_d' => [20, 150, 60, 0.5], 'cav_d2' => [20, 150, 60, 0.5], 'cav_depth' => [10, 200, 80, 1], 'cav_w' => [20, 150, 90, 1], 'cav_l' => [20, 150, 60, 1], 'cav_x' => [-100, 100, 0, 1], 'cav_y' => [-100, 100, 0, 1]],
        // a potion bottle: the model's height (0 = as it is), the wall, the neck, how much of the bottom is cut flat
        'potion' => ['height' => [0, 300, 0, 1], 'wall' => [1.5, 4, 2, 0.5], 'neck_d' => [12, 60, 26, 1], 'neck_h' => [10, 80, 30, 1], 'cut' => [0, 30, 4, 1]],
        'scale' => ['height' => [10, 1000, 300, 1]],
    ];

    public const CHOICES = [
        'split' => ['bed' => ['farm', '220', '180', 'custom'], 'joint' => ['pins', 'dovetail', 'none']],
        'life_size' => ['bed' => ['farm', '220', '180', 'custom'], 'joint' => ['pins', 'dovetail', 'none']],
        'puzzle' => ['lock' => ['tabs', 'pins']],
        'holder' => ['cavity' => ['can330', 'slim330', 'can500', 'pint', 'soap', 'candle', 'custom']],
    ];

    public const FLAGS = ['split' => ['numbers', 'lay'], 'hollow' => ['drain'], 'life_size' => ['hollow', 'numbers', 'lay'], 'puzzle' => ['numbers', 'frame'], 'holder' => ['cav_depth_own'], 'potion' => ['label']];

    /** op → text input → max length */
    public const TEXTS = ['potion' => ['text' => 20]];

    public const FLAGS_ON = ['numbers', 'lay', 'drain', 'hollow', 'label'];

    /** changes when the tool measures differently: stored analyses made by an older one are not used */
    private const ANALYSIS = 1;

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    public static function rules(string $op): array
    {
        $rules = [];
        foreach (self::FIELDS[$op] ?? [] as $key => [$min, $max, , $step]) {
            $rules[$key] = ['nullable', $step === 1 ? 'integer' : 'numeric', 'min:'.$min, 'max:'.$max];
        }
        foreach (self::FLAGS[$op] ?? [] as $flag) {
            $rules[$flag] = ['nullable', 'boolean'];
        }
        foreach (self::CHOICES[$op] ?? [] as $key => $options) {
            $rules[$key] = ['nullable', 'in:'.implode(',', $options)];
        }
        foreach (self::TEXTS[$op] ?? [] as $key => $max) {
            $rules[$key] = ['nullable', 'string', 'max:'.$max];
        }
        if (in_array($op, ['split', 'life_size'], true)) {
            $rules['planes'] = ['nullable', 'array'];
            foreach (['x', 'y', 'z'] as $axis) {
                $rules['planes.'.$axis] = ['nullable', 'array', 'max:6'];
                $rules['planes.'.$axis.'.*'] = ['numeric', 'min:0', 'max:1000'];
            }
        }

        return $rules;
    }

    /** Only what the tool knows, in its limits. */
    public static function clean(string $op, array $p): array
    {
        $out = [];
        foreach (self::FIELDS[$op] ?? [] as $key => [$min, $max, $default]) {
            $v = isset($p[$key]) && is_numeric($p[$key]) ? (float) $p[$key] : $default;
            $out[$key] = max($min, min($max, $v));
        }
        foreach (self::FLAGS[$op] ?? [] as $flag) {
            $out[$flag] = array_key_exists($flag, $p) ? filter_var($p[$flag], FILTER_VALIDATE_BOOLEAN) : in_array($flag, self::FLAGS_ON, true);
        }
        foreach (self::CHOICES[$op] ?? [] as $key => $options) {
            $out[$key] = in_array($p[$key] ?? null, $options, true) ? $p[$key] : $options[0];
        }
        foreach (self::TEXTS[$op] ?? [] as $key => $max) {
            $out[$key] = mb_substr(trim((string) ($p[$key] ?? '')), 0, $max);
        }
        if (in_array($op, ['split', 'life_size'], true)) {
            $planes = [];
            foreach (['x', 'y', 'z'] as $axis) {
                $list = array_values(array_filter((array) (($p['planes'] ?? [])[$axis] ?? []), 'is_numeric'));
                if ($list) {
                    $planes[$axis] = array_map(fn ($v) => round((float) $v, 2), array_slice($list, 0, 6));
                }
            }
            if ($planes) {
                $out['planes'] = $planes;
            }
        }

        return $out;
    }

    /** The usable bed of a split, in mm: the preset less the margins the farm keeps clear, or the visitor's own numbers. */
    public static function bedOf(array $clean): array
    {
        $choice = array_key_exists($clean['bed'] ?? '', self::BEDS) ? $clean['bed'] : 'farm';
        $preset = self::BEDS[$choice];
        if ($preset === null) {
            return [(float) $clean['bed_x'] - 2 * self::MARGIN, (float) $clean['bed_y'] - 2 * self::MARGIN, (float) $clean['bed_z']];
        }
        $margin = self::MARGIN;
        if (($clean['bed'] ?? 'farm') === 'farm') {
            $bed = config('pricing.bed_mm');
            $preset = [(float) $bed['x'], (float) $bed['y'], (float) $bed['z']];
            $margin = config('farm.enabled') ? max($margin, (float) app(FarmSettings::class)->get('bed_margin_mm')) : $margin;
        }

        return [round($preset[0] - 2 * $margin, 1), round($preset[1] - 2 * $margin, 1), (float) $preset[2]];
    }

    /** What the tool hands to the script. */
    public static function forTool(string $op, array $clean): array
    {
        $p = $clean;
        if (in_array($op, ['split', 'life_size'], true)) {
            $p['bed'] = self::bedOf($clean);
            $p['font'] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        }
        if ($op === 'life_size') {
            $p['height'] = round((float) $clean['height_cm'] * 10, 1);
        }
        if ($op === 'puzzle') {
            $p['font'] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        }
        if ($op === 'potion') {
            $p['font'] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
            if ((float) $clean['height'] <= 0) {
                unset($p['height']);
            }
        }
        if ($op === 'holder') {
            // the preset's own sizes unless the cavity is custom; a height of 0 keeps the model as it is
            if ($clean['cavity'] !== 'custom') {
                unset($p['cav_d'], $p['cav_d2'], $p['cav_w'], $p['cav_l']);
            }
            if (empty($p['cav_depth_own'])) {
                unset($p['cav_depth']);
            }
            if ((float) $clean['height'] <= 0) {
                unset($p['height']);
            }
        }
        if ($op === 'hollow') {
            $p['no_drain'] = empty($clean['drain']);
        }

        return $p;
    }

    /**
     * What the tool would do before it does it (a split: the planes the bed asks for); stored beside the model, the
     * next ask is instant.
     *
     * @return array<string, mixed>
     *
     * @throws EngineException
     */
    public function analyse(ModelFile $src, string $op, array $p): array
    {
        $stl = $src->absoluteStlPath();
        if (! $src->isReady() || ! $stl || ! is_file($stl)) {
            throw new EngineException('edit_failed');
        }
        $clean = self::clean($op, $p);
        $stored = dirname($stl).'/edit-'.$op.'-'.md5(json_encode([$clean, self::ANALYSIS, filemtime($stl)])).'.json';
        if (is_file($stored) && is_array($known = json_decode((string) file_get_contents($stored), true))) {
            return $known;
        }
        $json = $stored.'.in';
        File::put($json, (string) json_encode(self::forTool($op, $clean)));
        try {
            $r = $this->python->runScript('edit_tool.py', ['analyse', $stl, '@'.$json], 180);
        } finally {
            @unlink($json);
        }
        if (empty($r['ok'])) {
            throw new EngineException((string) ($r['code'] ?? 'edit_failed'));
        }
        $answer = array_diff_key($r, ['ok' => 1]);
        file_put_contents($stored, json_encode($answer));

        return $answer;
    }

    /**
     * A new model file of the operation's kind: made in the queue (App\Jobs\EditModel), the page waits for it.
     *
     * @throws EngineException
     */
    public function make(ModelFile $src, string $op, array $p, ?AnonymousSession $session, ?User $user): ModelFile
    {
        if (! in_array($op, self::KINDS, true)) {
            throw new EngineException('edit_failed');
        }
        if (! $src->isReady() || ! $src->absoluteStlPath()) {
            throw new EngineException('edit_failed');
        }
        $clean = self::clean($op, $p);
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        File::ensureDirectoryExists(dirname(Storage::disk(ModelFile::DISK)->path($rel)));
        $name = Str::slug(pathinfo($src->original_name, PATHINFO_FILENAME)) ?: 'model';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id ?? $src->owner_user_id, 'anonymous_session_id' => $session?->id ?? $src->anonymous_session_id,
            'original_name' => $name.'-'.$op.'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 0, 'sha256' => '',
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => $op, 'status' => ModelFile::STATUS_UPLOADED,
            'tool_params' => $clean + ['source' => $src->uuid],
        ]);
        EditModel::dispatch($file->id);

        return $file->refresh();
    }

    /** The pieces a design made here consists of, as the tool listed them. */
    public static function partsOf(ModelFile $f): array
    {
        if ($f->origin !== 'tool' || ! in_array($f->kind(), [...self::KINDS, ArtGenerator::KIND], true)) {
            return [];
        }

        return array_values(array_filter((array) ($f->tool_params['parts'] ?? []), fn ($p) => is_string($p) && preg_match('/^[a-z]+(_[0-9]{1,2})?$/', $p)));
    }

    /** What the page shows of a design made here: the report of the tool, and while it works, the phase it is in. */
    public static function report(ModelFile $f): ?array
    {
        if ($f->origin !== 'tool' || ! in_array($f->kind(), self::KINDS, true)) {
            return null;
        }
        $report = (array) ($f->tool_params['report'] ?? []);
        $stage = dirname(Storage::disk(ModelFile::DISK)->path($f->storage_path)).'/original.stl.stage';
        if ($f->status !== ModelFile::STATUS_READY && is_file($stage)) {
            $report['stage'] = trim((string) file_get_contents($stage));
        }

        return $report + ['op' => $f->kind(), 'source' => $f->tool_params['source'] ?? null, 'pieces_tris' => array_values((array) ($f->tool_params['pieces'] ?? []))];
    }
}
