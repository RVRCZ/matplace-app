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
    public const KINDS = ['split', 'scale'];

    /** print beds a model is cut for: the farm's, two common ones, or the visitor's own (usable size = bed − margins) */
    public const BEDS = ['farm' => [250, 250, 250], '220' => [220, 220, 250], '180' => [180, 180, 180], 'custom' => null];

    public const MARGIN = 5.0;

    /** op → field → [min, max, default, step] */
    public const FIELDS = [
        'split' => ['bed_x' => [50, 600, 250, 1], 'bed_y' => [50, 600, 250, 1], 'bed_z' => [50, 600, 250, 1]],
        'scale' => ['height' => [10, 1000, 300, 1]],
    ];

    public const CHOICES = ['split' => ['bed' => ['farm', '220', '180', 'custom'], 'joint' => ['pins', 'dovetail', 'none']]];

    public const FLAGS = ['split' => ['numbers', 'lay']];

    public const FLAGS_ON = ['numbers', 'lay'];

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
        if ($op === 'split') {
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
        if ($op === 'split') {
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
        $preset = self::BEDS[$clean['bed'] ?? 'farm'] ?? self::BEDS['farm'];
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
        if ($op === 'split') {
            $p['bed'] = self::bedOf($clean);
            $p['font'] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
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
