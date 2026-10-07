<?php

namespace App\Models;

use App\Domain\Farm\Palette;
use App\Domain\Generation\GenerationService;
use App\Domain\Generation\PedestalChanger;
use App\Domain\Tools\ParametricGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class ModelFile extends Model
{
    public const DISK = 'models';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DELETED = 'deleted';   // files removed by the owner; the row stays only because an old order points to it

    protected $fillable = [
        'uuid', 'owner_user_id', 'anonymous_session_id', 'original_name', 'ext', 'mime', 'size_bytes', 'sha256',
        'storage_path', 'stl_path', 'preview_path', 'bbox', 'volume_mm3', 'area_mm2', 'triangles', 'mesh_report',
        'origin', 'origin_ref', 'tool_params', 'status', 'error', 'timings',
    ];

    protected $casts = ['tool_params' => 'array', 'timings' => 'array',
        'bbox' => 'array',
        'mesh_report' => 'array',
        'deleted_at' => 'datetime',
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
            'vase' => ['supports' => false] + (($this->tool_params['purpose'] ?? 'vase') === 'vase' ? ['vase' => true] : []),   // a plain vase is one closed contour: it prints best in vase mode, one wall and no infill
            'relief', 'sign', 'logo', 'stamp', 'qr', 'stencil', 'lightbox', 'modular', 'organizer', 'box', 'phone_stand', 'cable_holder', 'cutter', 'holder', 'cap',
            'charm', 'keychain', 'earrings', 'ornament', 'magnet', 'coaster', 'gingerbread', 'name_letter' => ['supports' => false],
            // halves lie parting face up, supports would scar the cavity; the master of a silicone mold is the model itself and prints as it needs
            'mold' => ($this->tool_params['type'] ?? 'rigid') === 'silicone' ? ['supports' => true, 'infill' => 15] : ['supports' => false, 'infill' => 30],
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

        return [
            'token' => $req->token,
            'refinable' => app(GenerationService::class)->basePrompt($req) !== null,
            'pedestal' => app(PedestalChanger::class)->state($this),
        ];
    }

    /**
     * Height (mm) above which a tool model is meant to print in a second colour: the plate under a QR code, a raised
     * text or a logo; null for everything else. The model is never turned by the farm, so it only follows the size.
     */
    public function colorChangeMm(float $scale = 1.0): ?float
    {
        $z = $this->builtForPrinting() ? ($this->tool_params['color_change_mm'] ?? null) : null;

        return $z ? round((float) $z * $scale, 3) : null;
    }

    /**
     * Every height (mm) at which a tool model changes filament, bottom to top, with the colour it changes to. A plate
     * with a text has one; a picture whose colours lie one on another (pendant, magnet, ornament…) has one per colour.
     *
     * @return list<array{z: float, hex: string, code: ?string}>
     */
    public function colorChanges(float $scale = 1.0): array
    {
        if (! $this->builtForPrinting()) {
            return [];
        }
        $out = [];
        foreach ((array) ($this->tool_params['color_changes'] ?? []) as $change) {
            if (is_array($change) && isset($change['z']) && (float) $change['z'] > 0) {
                $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($change['hex'] ?? '')) ? (string) $change['hex'] : '#2B2B2B';
                $out[] = ['z' => round((float) $change['z'] * $scale, 3), 'hex' => $hex, 'code' => isset($change['code']) ? (string) $change['code'] : null];
            }
        }
        if (! $out && ($z = $this->colorChangeMm($scale))) {
            $out[] = ['z' => $z, 'hex' => $this->codeColors()[1] ?? '#D97706', 'code' => null];
        }

        return $out;
    }

    /**
     * The two filament colours a QR sign was designed in, as hex [plate, code]. A design from before the choice
     * existed is a white plate with a black code; null for everything that is not a code.
     *
     * @return array{0: string, 1: string}|null
     */
    public function codeColors(): ?array
    {
        if ($this->kind() !== 'qr' || ! $this->builtForPrinting()) {
            return null;
        }
        $hex = ParametricGenerator::COLOR_HEX;
        $palette = app(Palette::class);
        // the hex stored with the design wins: it is what the customer saw, even when the spool has left the catalogue
        $of = fn (string $key, string $default) => isset($this->tool_params[$key]) ? ($this->tool_params[$key.'_hex'] ?? $palette->hex($this->tool_params[$key]) ?? $hex[$default]) : $hex[$default];

        return [$of('plate_color', 'white'), $of('code_color', 'black')];
    }

    /** Made by one of our measured tools: the builder laid it the way it prints best, the farm must not turn it. */
    public function builtForPrinting(): bool
    {
        return $this->origin === 'tool' && array_key_exists($this->kind(), ParametricGenerator::FIELDS);
    }

    /** Organic AI meshes print best with tree supports. */
    public function wantsTreeSupports(): bool
    {
        return $this->origin === 'generated';
    }

    /** Address of the tool page that opens this design again; null when a tool did not make it or cannot reopen it. */
    public function toolUrl(): ?string
    {
        $route = 'tools.'.(in_array($this->origin_ref, ['lithophane', 'relief'], true) ? 'relief' : $this->origin_ref);
        if ($this->origin !== 'tool' || ! is_array($this->tool_params) || ! Route::has($route)) {
            return null;
        }
        // older reliefs stored only the stand flag: nothing to reopen
        if ($route === 'tools.relief' && ! isset($this->tool_params['mode'])) {
            return null;
        }

        return route($route, ['from' => $this->uuid]);
    }

    /** A small picture for lists: the stored one, else the card picture of the tool that made the model, else none. */
    public function previewUrl(): ?string
    {
        if ($this->preview_path && Storage::disk(self::DISK)->exists($this->preview_path)) {
            return route('api.files.preview', $this).'?v='.$this->updated_at?->timestamp;
        }
        $picture = 'img/tools/'.$this->kind().'-480.webp';

        return $this->origin === 'tool' && is_file(public_path($picture)) ? asset($picture) : null;
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
