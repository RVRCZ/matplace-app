<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\ProcessModelFile;
use App\Models\ModelFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Two-part casting mold around any ready model (engines/python/mold_tool.py): the model is cut out of a box, a pouring
 * funnel goes through the bottom, ball keys align the halves. Both halves come as one STL, parting face up, ready to
 * print. Pure geometry, seconds, nothing paid per piece. The result is a normal ModelFile of kind "mold".
 * The tool measures how much of the surface a hard mold cannot let go of (report.undercut_pct, report.verdict).
 * Type "silicone" is for such shapes: a base with the model on it and a sleeve, silicone is poured over the model.
 */
final class MoldGenerator
{
    public const WALLS = [6, 8, 10, 12];

    public const TYPES = ['rigid', 'silicone'];

    public const AXES = ['auto', 'x', 'y', 'z'];

    /** Pieces of a printed mold: two halves, or three or four wedges round an upright axis. */
    public const PARTS = [2, 3, 4];

    /** Changes when the tool measures differently: stored analyses made by an older one are not used. */
    private const ANALYSIS = 2;

    /** Parting plane position in percent of the model's width along the chosen axis; "auto" = least undercut. */
    public const SPLITS = [30, 40, 50, 60, 70];

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /**
     * @param  array{type?: string, parts?: int, fill?: bool, wall?: int, axis?: string, split?: int|string|null}  $p
     *
     * @throws EngineException with a short reason code (mold_unavailable, too_small, too_big, not_watertight, mold_failed)
     */
    public function make(ModelFile $src, array $p): ModelFile
    {
        if (! $this->available()) {
            throw new EngineException('mold_unavailable');
        }
        $stl = $src->absoluteStlPath();
        if (! $src->isReady() || ! $stl || ! is_file($stl)) {
            throw new EngineException('mold_failed');
        }
        $params = [
            'type' => in_array($p['type'] ?? null, self::TYPES, true) ? $p['type'] : 'rigid',
            'parts' => in_array((int) ($p['parts'] ?? 0), self::PARTS, true) ? (int) $p['parts'] : 2,
            'fill' => filter_var($p['fill'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'wall' => in_array((int) ($p['wall'] ?? 0), self::WALLS, true) ? (int) $p['wall'] : 8,
            'axis' => in_array($p['axis'] ?? null, self::AXES, true) ? $p['axis'] : 'auto',
            'split' => in_array((int) ($p['split'] ?? 0), self::SPLITS, true) ? (int) $p['split'] : 'auto',
        ];

        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));

        $r = $this->python->runScript('mold_tool.py', [$stl, $abs, json_encode([
            'type' => $params['type'], 'parts' => $params['parts'], 'fill' => $params['fill'], 'cast' => str_replace('\\', '/', dirname($abs)).'/cast.stl', 'wall' => $params['wall'], 'axis' => $params['axis'], 'split' => $params['split'] === 'auto' ? 'auto' : $params['split'] / 100,
        ])], 300);
        if (empty($r['ok']) || ! is_file($abs)) {
            File::deleteDirectory(dirname($abs));
            $reason = (string) ($r['error'] ?? 'mold_failed');
            throw new EngineException(in_array($reason, ['too_small', 'too_big', 'not_watertight'], true) ? $reason : 'mold_failed');
        }

        $name = Str::slug(pathinfo($src->original_name, PATHINFO_FILENAME)) ?: 'model';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $src->owner_user_id, 'anonymous_session_id' => $src->anonymous_session_id,
            'original_name' => $name.($params['type'] === 'silicone' ? '-silicone-mold.stl' : '-mold.stl'), 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => 'mold', 'status' => ModelFile::STATUS_UPLOADED,
            'tool_params' => $params + ['source' => $src->uuid, 'report' => array_intersect_key($r, array_flip(['type', 'parts', 'pieces', 'fill', 'added_ml', 'undercut_before_pct', 'axis', 'angle_deg', 'split_mm', 'undercut_pct', 'verdict', 'box', 'sleeve', 'plate', 'resin_ml', 'silicone_ml', 'mold_cm3', 'keys', 'wall', 'warnings']))],
        ]);
        ProcessModelFile::dispatch($file->id);

        // with a sync queue the file is already processed: answer with its current state
        return $file->refresh();
    }

    /**
     * What a printed mold would do with this model, before any is made: the division with the least hidden surface
     * for the asked number of parts, the hidden share for 2, 3 and 4 parts, and one byte per triangle of the model's
     * STL (piece 0..3, plus 8 when no piece lets go of it), base64. Stored beside the model, the next ask is instant.
     *
     * @param  array{parts?: int, axis?: string, split?: int|string|null}  $p
     * @return array<string, mixed>
     *
     * @throws EngineException
     */
    public function analyse(ModelFile $src, array $p): array
    {
        if (! $this->available()) {
            throw new EngineException('mold_unavailable');
        }
        $stl = $src->absoluteStlPath();
        if (! $src->isReady() || ! $stl || ! is_file($stl)) {
            throw new EngineException('mold_failed');
        }
        $params = [
            'parts' => in_array((int) ($p['parts'] ?? 0), self::PARTS, true) ? (int) $p['parts'] : 2,
            'axis' => in_array($p['axis'] ?? null, self::AXES, true) ? $p['axis'] : 'auto',
            'split' => in_array((int) ($p['split'] ?? 0), self::SPLITS, true) ? (int) $p['split'] / 100 : 'auto',
        ];
        $stored = dirname($stl).'/mold-analysis-'.md5(json_encode([$params, self::ANALYSIS, filemtime($stl)])).'.json';
        if (is_file($stored) && is_array($known = json_decode((string) file_get_contents($stored), true))) {
            return $known;
        }
        $faces = $stored.'.bin';
        $r = $this->python->runScript('mold_tool.py', ['analyse', $stl, $faces, json_encode($params)], 180);
        if (empty($r['ok']) || ! is_file($faces)) {
            @unlink($faces);
            $reason = (string) ($r['error'] ?? 'mold_failed');
            throw new EngineException(in_array($reason, ['too_small', 'too_big', 'not_watertight'], true) ? $reason : 'mold_failed');
        }
        $answer = array_intersect_key($r, array_flip(['parts', 'axis', 'angle_deg', 'split_mm', 'undercut_pct', 'verdict', 'options', 'faces', 'hidden_faces']))
            + ['flags' => base64_encode((string) file_get_contents($faces))];
        @unlink($faces);
        file_put_contents($stored, json_encode($answer));

        return $answer;
    }

    /** The shape a mold with filled undercuts really casts (cast.stl) or which of its triangles are added (cast.bin). */
    public static function castPath(ModelFile $mold, string $name): ?string
    {
        if ($mold->kind() !== 'mold' || empty($mold->tool_params['report']['fill']) || ! in_array($name, ['cast.stl', 'cast.bin'], true)) {
            return null;
        }
        $path = dirname(Storage::disk(ModelFile::DISK)->path($mold->storage_path)).'/'.($name === 'cast.bin' ? 'cast.stl.bin' : 'cast.stl');

        return is_file($path) ? $path : null;
    }
}
