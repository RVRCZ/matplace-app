<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The one YouTube channel the print videos go to. Tokens are stored encrypted (APP_KEY). */
class YouTubeAccount extends Model
{
    protected $table = 'youtube_accounts';

    protected $fillable = ['channel_id', 'channel_title', 'refresh_token', 'access_token', 'access_expires_at', 'connected_by'];

    protected $hidden = ['refresh_token', 'access_token'];

    protected $casts = ['refresh_token' => 'encrypted', 'access_token' => 'encrypted', 'access_expires_at' => 'datetime'];

    public static function current(): ?self
    {
        return self::latest('id')->first();
    }

    public function url(): string
    {
        return 'https://www.youtube.com/channel/'.$this->channel_id;
    }
}
