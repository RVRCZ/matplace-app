<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A designer's public face: /d/{slug}. Belongs to an ordinary account (role "designer"); the designer needs no printer.
 * Cards (DesignerModel) are the portfolio; a card with an uploaded, checked file can be printed by the farm.
 */
class DesignerProfile extends Model
{
    public const LINKS = ['printables', 'makerworld', 'instagram', 'web'];

    public const MAX_MODELS = 500;

    /** The reward a designer may ask per piece, in CZK. The 30 % cap is applied when an order is made. */
    public const MAX_ROYALTY_CZK = 5000;

    protected $fillable = [
        'user_id', 'display_name', 'slug', 'bio', 'avatar_path', 'cover_path', 'links', 'default_royalty_czk', 'visible', 'published_at',
        'printables_token', 'printables_username', 'printables_user_id', 'printables_verified_at',
        'makerworld_token', 'makerworld_handle', 'makerworld_uid', 'makerworld_verified_at',
    ];

    protected $hidden = ['iban', 'iban_owner', 'ico', 'dic', 'printables_token', 'makerworld_token'];

    protected $casts = [
        'links' => 'array', 'visible' => 'bool', 'default_royalty_czk' => 'float',
        'published_at' => 'datetime', 'printables_verified_at' => 'datetime', 'makerworld_verified_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(DesignerModel::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(DesignerImport::class);
    }

    public static function makeSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::limit(Str::slug($name) ?: 'designer', 120, '');
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /** The token the designer puts into their bio (or a model's description) on the other site: 16 hex characters. */
    public function tokenFor(string $source): string
    {
        $column = $source.'_token';
        if (! $this->{$column}) {
            $this->forceFill([$column => bin2hex(random_bytes(8))])->save();
        }

        return 'matplace-'.$this->{$column};
    }

    public function verifiedOn(string $source): bool
    {
        return $this->{$source.'_verified_at'} !== null;
    }

    /** Who the designer is at the source (numeric id there): every imported card must come from this author. */
    public function externalIdOn(string $source): ?string
    {
        return $source === 'printables' ? $this->printables_user_id : ($source === 'makerworld' ? $this->makerworld_uid : null);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /** Links the designer filled in, as full addresses. */
    public function linkList(): array
    {
        return array_filter(array_intersect_key((array) $this->links, array_flip(self::LINKS)), fn ($v) => is_string($v) && preg_match('~^https?://~i', $v));
    }

    /**
     * The first visible card makes the profile public, once. Hiding it by hand afterwards is respected:
     * later cards do not switch it back on.
     */
    public function publishOnce(): void
    {
        if ($this->published_at === null) {
            $this->forceFill(['visible' => true, 'published_at' => now()])->save();
        }
    }

    public function publicUrl(?string $locale = null, bool $withRef = false): string
    {
        return localized_route('designers.show', ['designer' => $this->slug] + ($withRef ? ['ref' => $this->slug] : []), $locale);
    }
}
