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
 * "Repair my model": a downloaded STL with holes, doubled faces or faces turned inside out becomes a printable one
 * (engines/python/repair_tool.py). The original stays as it was; the repaired model is a new ModelFile of kind
 * "repaired" that carries the report: what was wrong, what was done, what is left.
 */
final class ModelRepair
{
    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /** @throws EngineException repair_unavailable | repair_failed */
    public function make(ModelFile $src): ModelFile
    {
        if (! $this->available()) {
            throw new EngineException('repair_unavailable');
        }
        $stl = $src->absoluteStlPath();
        if (! $src->isReady() || ! $stl || ! is_file($stl)) {
            throw new EngineException('repair_failed');
        }
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));

        $r = $this->python->runScript('repair_tool.py', [$stl, $abs], 300);
        if (empty($r['ok']) || ! is_file($abs)) {
            File::deleteDirectory(dirname($abs));
            throw new EngineException('repair_failed');
        }

        $name = Str::slug(pathinfo($src->original_name, PATHINFO_FILENAME)) ?: 'model';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $src->owner_user_id, 'anonymous_session_id' => $src->anonymous_session_id,
            'original_name' => $name.'-fixed.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => 'repaired', 'status' => ModelFile::STATUS_UPLOADED,
            'tool_params' => ['source' => $src->uuid, 'report' => array_intersect_key($r, array_flip(['verdict', 'before', 'after', 'actions']))],
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }
}
