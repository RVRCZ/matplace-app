<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** One account per person. Roles are switches (UserRole); printer/designer data live in their profiles. */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_PRINTER = 'printer';

    public const ROLE_DESIGNER = 'designer';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'locale', 'country', 'street', 'zip', 'city', 'lat', 'lng', 'avatar_path',
        'notify_email', 'notify_push', 'legacy_user_id', 'legacy_printer_id', 'legacy_designer_id', 'email_verified_at', 'phone_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'password' => 'hashed',
            'notify_email' => 'bool',
            'notify_push' => 'bool',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OauthIdentity::class);
    }

    public function printerProfile(): HasOne
    {
        return $this->hasOne(PrinterProfile::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class, 'owner_user_id');
    }

    public function modelFiles(): HasMany
    {
        return $this->hasMany(ModelFile::class, 'owner_user_id');
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'to_user_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains(fn (UserRole $r) => $r->role === $role && $r->disabled_at === null);
    }

    public function isPrinter(): bool
    {
        return $this->hasRole(self::ROLE_PRINTER);
    }

    public function isDesigner(): bool
    {
        return $this->hasRole(self::ROLE_DESIGNER);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /** Switch a role on (creates the row) or off (keeps data, marks disabled). */
    public function setRole(string $role, bool $enabled): UserRole
    {
        $r = $this->roles()->firstOrNew(['role' => $role]);
        $r->enabled_at = $enabled ? ($r->enabled_at ?? now()) : $r->enabled_at;
        $r->disabled_at = $enabled ? null : now();
        $r->save();
        $this->unsetRelation('roles');

        return $r;
    }

    /** Pull everything an anonymous session created into this account. */
    public function claimSession(AnonymousSession $session): void
    {
        ModelFile::where('anonymous_session_id', $session->id)->whereNull('owner_user_id')->update(['owner_user_id' => $this->id]);
        Calculation::where('anonymous_session_id', $session->id)->whereNull('owner_user_id')->update(['owner_user_id' => $this->id]);
        if ($session->claimed_by_user_id === null) {
            $session->forceFill(['claimed_by_user_id' => $this->id])->saveQuietly();
        }
    }
}
