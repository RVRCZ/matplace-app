<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ModelFile extends Model
{
    public const DISK = 'models';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'owner_user_id', 'anonymous_session_id', 'original_name', 'ext', 'mime', 'size_bytes', 'sha256',
        'storage_path', 'stl_path', 'preview_path', 'bbox', 'volume_mm3', 'area_mm2', 'triangles', 'mesh_report',
        'origin', 'origin_ref', 'status', 'error',
    ];

    protected $casts = [
        'bbox' => 'array',
        'mesh_report' => 'array',
        'volume_mm3' => 'float',
        'area_mm2' => 'float',
        'triangles' => 'int',
        'size_bytes' => 'int',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AnonymousSession::class, 'anonymous_session_id');
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->storage_path);
    }

    public function absoluteStlPath(): ?string
    {
        return $this->stl_path ? Storage::disk(self::DISK)->path($this->stl_path) : null;
    }

    /** What the model is, for tool-specific defaults and texts: upload | generated | sign | lithophane | relief | … */
    public function kind(): string
    {
        return $this->origin === 'tool' ? (string) ($this->origin_ref ?: 'tool') : (string) ($this->origin ?: 'upload');
    }

    /**
     * Sensible print settings for this kind of model; the calculator starts from them, the customer can still change them.
     *
     * @return array<string, mixed>
     */
    public function printHints(): array
    {
        return match ($this->kind()) {
            'generated' => ['supports' => true],                                           // organic shapes: tree supports
            'lithophane' => ['infill' => 100, 'quality' => 'fine', 'supports' => false],   // must be solid, fine layers = smooth picture
            'relief', 'sign' => ['supports' => false],
            default => [],
        };
    }

    /** For generated models: the generation token and whether it can be changed in words. */
    public function generationInfo(): ?array
    {
        if ($this->origin !== 'generated' || ! $this->origin_ref) {
            return null;
        }
        $req = GenerationRequest::where('token', $this->origin_ref)->first();
        if (! $req) {
            return null;
        }

        return ['token' => $req->token, 'refinable' => app(\App\Domain\Generation\GenerationService::class)->basePrompt($req) !== null];
    }

    /** Organic AI meshes print best with tree supports. */
    public function wantsTreeSupports(): bool
    {
        return $this->origin === 'generated';
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->stl_path !== null;
    }

    /** Directory (relative to the disk) holding everything for this file. */
    public function dir(): string
    {
        return 'files/'.$this->uuid;
    }
}
