<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\ProcessModelFile;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo → lithophane (picture visible against light) or relief plaque. Local height-map maths, no AI, no cost per piece.
 * The photo is only read, never stored. The result is a normal ModelFile.
 */
final class ReliefGenerator
{
    public const MODES = ['lithophane', 'relief'];

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    public function generate(UploadedFile $photo, array $p, ?AnonymousSession $session, ?User $user): ModelFile
    {
        if (! $this->available()) {
            throw new EngineException('The relief generator needs Python.');
        }
        $mode = in_array($p['mode'] ?? '', self::MODES, true) ? $p['mode'] : 'lithophane';
        $params = [
            'mode' => $mode,
            'width' => (float) ($p['width'] ?? 100),
            'min_thickness' => (float) ($p['min_thickness'] ?? ($mode === 'lithophane' ? 0.8 : 1.2)),
            'max_thickness' => (float) ($p['max_thickness'] ?? ($mode === 'lithophane' ? 3.0 : 4.0)),
            'frame' => ($p['frame'] ?? true) ? 2.0 : 0.0,
            'invert' => (bool) ($p['invert'] ?? false),
            'stand' => (bool) ($p['stand'] ?? false),
            'standing' => $mode === 'lithophane',
        ];

        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));

        $r = $this->python->runScript('relief_tool.py', [$photo->getRealPath(), $abs, json_encode($params)]);
        if (empty($r['ok']) || ! is_file($abs)) {
            File::deleteDirectory(dirname($abs));
            throw new EngineException('Relief generation failed: '.($r['error'] ?? 'unknown'));
        }

        $base = Str::slug(Str::limit(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME), 30, '')) ?: 'photo';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'original_name' => $mode.'-'.$base.'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => $mode, 'tool_params' => ['stand' => (bool) ($p['stand'] ?? false)], 'status' => ModelFile::STATUS_UPLOADED,
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }
}
