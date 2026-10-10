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
    public const TYPES = ['socle', 'antique', 'cut', 'round', 'square', 'hexagon', 'column', 'plaque', 'none'];

    /** Finishes of a sculptor's bust: they shape the chest too, so they are offered for busts first. */
    public const BUST_STYLES = ['socle', 'antique', 'cut'];

    /** Bases that carry a name; only the plinth has room for a dedication below it. */
    public const NAMED = ['socle', 'antique', 'plaque'];

    public const DEDICATED = ['plaque'];

    public const FRONTS = ['keep', 'left', 'right', 'back'];

    /**
     * The bases of a pet figurine (engines/python/pet_kind.py): they follow the footprint of four paws, not the
     * circle round a chest. All three carry the pet's name; the plinth has room for a dedication too.
     */
    public const PET_TYPES = ['oval', 'round', 'plaque'];

    /**
     * Where the name stands on the base of a pet: the generator's front is the side the photo was taken from, not always
     * the animal's face, so the customer may say. "auto": along the flank of a long animal, in front of a compact one.
     */
    public const PET_NAME_SIDES = ['auto', 'front', 'right', 'back', 'left'];

    /** Under this a leg or a tail breaks: the page warns below it (the same number as SAFE_MM in pet_kind.py). */
    public const PET_SAFE_MM = 2.5;

    /** How far the figure is lowered into the base, in percent of its height. */
    public const SINKS = [0, 10, 20, 30];

    public function __construct(private readonly ModelNormalizer $normalizer) {}

    /** The base of a pet figurine and what was measured on it; null for every other file. */
    public function petState(ModelFile $f): ?array
    {
        $req = $this->request($f);
        if (! $req || ($req->description['kind'] ?? null) !== 'pet') {
            return null;
        }
        $own = is_array($f->tool_params) ? $f->tool_params : [];
        $type = $own['pedestal'] ?? $req->description['pedestal'] ?? 'oval';
        $thin = $own['thinnest_mm'] ?? $req->description['thinnest_mm'] ?? null;

        return [
            'style' => (string) ($req->description['style'] ?? 'realistic'),
            'type' => in_array($type, self::PET_TYPES, true) ? $type : 'oval',
            'name' => (string) ($own['name'] ?? $req->description['pedestal_name'] ?? ''),
            'dedication' => (string) ($own['dedication'] ?? $req->description['pedestal_dedication'] ?? ''),
            'name_side' => (string) ($own['name_side'] ?? $req->description['name_side'] ?? 'auto'),
            'thinnest_mm' => $thin !== null ? (float) $thin : null,
            'safe_mm' => self::PET_SAFE_MM,
        ];
    }

    /**
     * Another base or another name under a pet figurine, from the figure kept beside the file: no new generation.
     *
     * @param  array{type: string, name?: ?string, dedication?: ?string, name_side?: ?string}  $pedestal
     *
     * @throws \RuntimeException when the base cannot be joined to the figure
     */
    public function changePet(ModelFile $f, array $pedestal): ModelFile
    {
        $req = $this->request($f);
        $now = $this->petState($f);
        if (! $req || $now === null) {
            throw new \InvalidArgumentException('not a pet figurine');
        }
        $disk = Storage::disk(ModelFile::DISK);
        $source = dirname($disk->path($f->storage_path)).'/source.stl';
        if (! is_file($source)) {
            throw new \RuntimeException('pedestal_failed');
        }
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = $disk->path($rel);
        File::ensureDirectoryExists(dirname($abs));
        $newSource = dirname($abs).'/source.stl';
        // a miniature stands on its disc; the other two looks take any of the three
        $type = $now['style'] === 'miniature' ? 'round' : (in_array($pedestal['type'], self::PET_TYPES, true) ? $pedestal['type'] : 'oval');
        $name = mb_substr(trim((string) ($pedestal['name'] ?? '')), 0, 24);
        $dedication = $type === 'plaque' ? mb_substr(trim((string) ($pedestal['dedication'] ?? '')), 0, 40) : '';
        $side = in_array($pedestal['name_side'] ?? '', self::PET_NAME_SIDES, true) ? (string) $pedestal['name_side'] : $now['name_side'];
        try {
            $this->normalizer->toPrintableStl($source, $abs, (float) ($req->target_mm ?: config('ai.default_target_mm', 80)), false, ['pedestal', 'solid'], array_filter([
                'pet' => true, 'pedestal' => $type, 'name' => $name ?: null, 'dedication' => $dedication ?: null, 'name_side' => $side !== 'auto' ? $side : null, 'source_out' => $newSource,
            ]));
        } catch (\Throwable $e) {
            File::deleteDirectory(dirname($abs));
            throw new \RuntimeException('pedestal_failed', 0, $e);
        }
        $report = $this->normalizer->report;
        if (! is_file($abs) || ! is_file($newSource) || ($report['pedestal'] ?? null) !== $type) {
            File::deleteDirectory(dirname($abs));
            throw new \RuntimeException('pedestal_failed');
        }
        $new = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $f->owner_user_id, 'anonymous_session_id' => $f->anonymous_session_id,
            'original_name' => $f->original_name, 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'generated', 'origin_ref' => $f->origin_ref, 'status' => ModelFile::STATUS_UPLOADED,
            'tool_params' => ['pedestal' => $type, 'name' => $name, 'dedication' => $dedication, 'name_side' => $side] + (isset($report['thinnest_mm']) ? ['thinnest_mm' => (float) $report['thinnest_mm']] : []),
        ]);
        ProcessModelFile::dispatch($new->id);

        return $new;
    }

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
            'sink' => (int) ($own['sink'] ?? 0),
            'tidy' => (bool) ($own['tidy'] ?? true),
        ];
    }

    /**
     * @param  array{type: string, name?: ?string, dedication?: ?string}  $pedestal
     *
     * @throws \RuntimeException when the figure cannot be separated from its old base
     */
    public function change(ModelFile $f, array $pedestal, string $front = 'keep', int $sink = 0, bool $tidy = true): ModelFile
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
        $named = in_array($pedestal['type'], self::NAMED, true);
        $plaque = in_array($pedestal['type'], self::DEDICATED, true);
        $extras = array_filter([
            'pedestal' => $pedestal['type'],
            'name' => $named ? ($pedestal['name'] ?? null) : null,
            'dedication' => $plaque ? ($pedestal['dedication'] ?? null) : null,
            'front' => $front,
            'sink' => $sink > 0 ? $sink / 100 : null,
            'tidy' => $tidy ?: null,
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
            'tool_params' => ['pedestal' => $pedestal['type'], 'name' => $named ? (string) ($pedestal['name'] ?? '') : '', 'dedication' => $plaque ? (string) ($pedestal['dedication'] ?? '') : '', 'sink' => $sink, 'tidy' => $tidy],
        ]);
        ProcessModelFile::dispatch($new->id);

        return $new;
    }

    private function request(ModelFile $f): ?GenerationRequest
    {
        return $f->origin === 'generated' && $f->origin_ref ? GenerationRequest::where('token', $f->origin_ref)->first() : null;
    }
}
