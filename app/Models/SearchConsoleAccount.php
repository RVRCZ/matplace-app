<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The one Google account Search Console is read with (docs/AN.md §14). Tokens are stored encrypted (APP_KEY). */
class SearchConsoleAccount extends Model
{
    protected $table = 'search_console_accounts';

    protected $fillable = ['site_url', 'sites', 'refresh_token', 'access_token', 'access_expires_at', 'connected_by', 'last_pulled_at', 'last_pulled_from', 'last_pulled_to', 'last_error'];

    protected $hidden = ['refresh_token', 'access_token'];

    protected $casts = [
        'sites' => 'array', 'refresh_token' => 'encrypted', 'access_token' => 'encrypted', 'access_expires_at' => 'datetime',
        'last_pulled_at' => 'datetime', 'last_pulled_from' => 'date', 'last_pulled_to' => 'date',
    ];

    public static function current(): ?self
    {
        return self::latest('id')->first();
    }

    /** A property is chosen, so rows can be pulled. */
    public function ready(): bool
    {
        return (string) $this->site_url !== '';
    }
}
