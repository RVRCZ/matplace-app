<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An address that ended in "not found" and how often (App\Http\Middleware\RecordMissing, matplace:events-bots). */
class MissingPage extends Model
{
    public $timestamps = false;

    protected $fillable = ['path', 'hits', 'bot_hits', 'log_hits', 'log_bot_hits', 'referer', 'first_at', 'live_at', 'last_at'];

    protected $casts = ['hits' => 'int', 'bot_hits' => 'int', 'log_hits' => 'int', 'log_bot_hits' => 'int', 'first_at' => 'datetime', 'live_at' => 'datetime', 'last_at' => 'datetime'];

    /**
     * Addresses worth a decision look like pages. Probes for other systems (wp-login.php, .env, /cgi-bin/…) and
     * missing pictures or scripts are noise: thousands of different ones, none of them an old page of ours.
     */
    public static function worthKeeping(string $path): bool
    {
        if (strlen($path) > 190 || ! preg_match('#^/[A-Za-z0-9/_\-.~%]+$#', $path)) {
            return false;
        }
        if (preg_match('#\.(?!html?$)[A-Za-z0-9]{1,8}$#', $path) || preg_match('#(^|/)\.#', $path)) {
            return false;   // a file (anything but .html) or a dot-file
        }

        return ! preg_match('#^/(wp-|wordpress|cgi-bin|vendor|phpmyadmin|pma|admin|administrator|api|storage|build|\.well-known|xmlrpc|owa|autodiscover|actuator|solr|_ignition|telescope|horizon)#i', $path);
    }
}
