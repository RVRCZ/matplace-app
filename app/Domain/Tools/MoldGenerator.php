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
 */
final class MoldGenerator
{
    public const WALLS = [6, 8, 10, 12];

    public const AXES = ['auto', 'x', 'y'];

    /** Parting plane position in percent of the model's width along the chosen axis; "auto" = least undercut. */
    public const SPLITS = [30, 40, 50, 60, 70];

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /**
     * @param  array{wall?: int, axis?: string, split?: int|string|null}  $p
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
            'wall' => in_array((int) ($p['wall'] ?? 8), self::WALLS, true) ? (int) $p['wall'] : 8,
            'axis' => in_array($p['axis'] ?? 'auto', self::AXES, true) ? $p['axis'] : 'auto',
            'split' => in_array((int) ($p['split'] ?? 0), self::SPLITS, true) ? (int) $p['split'] : 'auto',
        ];

        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));

        $r = $this->python->runScript('mold_tool.py', [$stl, $abs, json_encode([
            'wall' => $params['wall'], 'axis' => $params['axis'], 'split' => $params['split'] === 'auto' ? 'auto' : $params['split'] / 100,
        ])], 180);
        if (empty($r['ok']) || ! is_file($abs)) {
            File::deleteDirectory(dirname($abs));
            $reason = (string) ($r['error'] ?? 'mold_failed');
            throw new EngineException(in_array($reason, ['too_small', 'too_big', 'not_watertight'], true) ? $reason : 'mold_failed');
        }

        $name = Str::slug(pathinfo($src->original_name, PATHINFO_FILENAME)) ?: 'model';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $src->owner_user_id, 'anonymous_session_id' => $src->anonymous_session_id,
            'original_name' => $name.'-mold.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => 'mold', 'status' => ModelFile::STATUS_UPLOADED,
            'tool_params' => $params + ['source' => $src->uuid, 'report' => array_intersect_key($r, array_flip(['axis', 'split_mm', 'undercut_pct', 'box', 'plate', 'resin_ml', 'mold_cm3', 'keys', 'wall', 'warnings']))],
        ]);
        ProcessModelFile::dispatch($file->id);

        // with a sync queue the file is already processed: answer with its current state
        return $file->refresh();
    }
}
