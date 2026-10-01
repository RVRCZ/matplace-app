<?php

namespace App\Models;

use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use App\Support\Locales;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * One account per person: a customer. Roles are switches (UserRole); a designer's data live in the designer profile.
 * The e-mail must be verified before the first order, top-up or designer profile (see EnsureEmailVerified).
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_PRINTER = 'printer';

    public const ROLE_DESIGNER = 'designer';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'locale', 'country', 'street', 'zip', 'city', 'lat', 'lng', 'avatar_path',
        'notify_email', 'notify_push', 'legacy_user_id', 'legacy_printer_id', 'legacy_designer_id', 'email_verified_at', 'phone_verified_at',
        'delivery_name', 'pickup_point',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'pending_email_at' => 'datetime',
            'deleted_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'pickup_point' => 'array',
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

    /** E-mails and notifications are written in the language chosen in the profile; pages follow their address. */
    public function preferredLocale(): string
    {
        return Locales::supported($this->locale) ? $this->locale : Locales::DEFAULT;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailAddress);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    /** Scrubbed on the owner's request: the row stays for the orders and the ledger, nobody can log into it. */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /** The account can be entered with a password (false for accounts made through Google or Facebook only). */
    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /** What stands on a parcel: the delivery name when one is set, else the account name. */
    public function recipientName(): string
    {
        return (string) ($this->delivery_name ?: $this->name);
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
