<?php

namespace App\Domain\Generation;

use App\Jobs\ProcessModelFile;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A generated bust or figure keeps its closed body without a base next to the printable file (source.stl).
 * Another base, another name or a turned front is then a few seconds of geometry, not a new paid generation.
 */
final class PedestalChanger
{
    public const TYPES = ['round', 'square', 'hexagon', 'column', 'plaque', 'none'];

    public const FRONTS = ['keep', 'left', 'right', 'back'];

    public function __construct(private readonly ModelNormalizer $normalizer) {}

    /** Current base of the file, or null when this file has none to change. */
    public function state(ModelFile $f): ?array
    {
        $req = $this->request($f);
        if (! $req || ! in_array($req->description['kind'] ?? null, ['bust', 'figure'], true)) {
            return null;
        }
        $own = is_array($f->tool_params) ? $f->tool_params : [];

        return [
            'type' => $own['pedestal'] ?? $req->description['pedestal'] ?? 'round',
            'name' => $own['name'] ?? $req->description['pedestal_name'] ?? '',
            'dedication' => $own['dedication'] ?? $req->description['pedestal_dedication'] ?? '',
        ];
    }

    /**
     * @param  array{type: string, name?: ?string, dedication?: ?string}  $pedestal
     *
     * @throws \RuntimeException when the figure cannot be separated from its old base
     */
    public function change(ModelFile $f, array $pedestal, string $front = 'keep'): ModelFile
    {
        $req = $this->request($f);
        if (! $req || $this->state($f) === null) {
            throw new \InvalidArgumentException('not a generated figure');
        }
        $disk = Storage::disk(ModelFile::DISK);
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = $disk->path($rel);
        File::ensureDirectoryExists(dirname($abs));
        $newSource = dirname($abs).'/source.stl';

        $oldSource = dirname($disk->path($f->storage_path)).'/source.stl';
        $hasSource = is_file($oldSource);
        $plaque = $pedestal['type'] === 'plaque';
        $extras = array_filter([
            'pedestal' => $pedestal['type'],
            'name' => $plaque ? ($pedestal['name'] ?? null) : null,
            'dedication' => $plaque ? ($pedestal['dedication'] ?? null) : null,
            'front' => $front,
            'source_out' => $newSource,
            // files made before the source was kept: take the old base away first
            'strip_pedestal' => ! $hasSource,
        ]);
        $target = (float) ($req->target_mm ?: config('ai.default_target_mm', 80));
        try {
            $this->normalizer->toPrintableStl($hasSource ? $oldSource : $disk->path($f->storage_path), $abs, $target, false, ['pedestal', 'solid'], $extras);
        } catch (\Throwable $e) {
            File::deleteDirectory(dirname($abs));
            throw new \RuntimeException('pedestal_failed', 0, $e);
        }
        if (! is_file($abs) || ! is_file($newSource)) {
            File::deleteDirectory(dirname($abs));
            throw new \RuntimeException('pedestal_failed');
        }

        $new = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $f->owner_user_id, 'anonymous_session_id' => $f->anonymous_session_id,
            'original_name' => $f->original_name, 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'generated', 'origin_ref' => $f->origin_ref, 'status' => ModelFile::STATUS_UPLOADED,
            'tool_params' => ['pedestal' => $pedestal['type'], 'name' => $plaque ? (string) ($pedestal['name'] ?? '') : '', 'dedication' => $plaque ? (string) ($pedestal['dedication'] ?? '') : ''],
        ]);
        ProcessModelFile::dispatch($new->id);

        return $new;
    }

    private function request(ModelFile $f): ?GenerationRequest
    {
        return $f->origin === 'generated' && $f->origin_ref ? GenerationRequest::where('token', $f->origin_ref)->first() : null;
    }
}
