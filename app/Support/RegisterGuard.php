<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * What stops a robot that fills in the real registration form (docs/AH.md §10; 58 accounts "Test" in a day from six
 * addresses, 18 Oct 2026): the address it comes from, counted over an hour and a day.
 */
class RegisterGuard
{
    /** Registrations from one address: an hour, a day (named limiter `register`, AppServiceProvider). */
    public const PER_HOUR = 3;

    public const PER_DAY = 5;

    /** The address a limit counts: IPv4 as it is, IPv6 by its /64 (one household or one server gets a whole /64). */
    public static function ipKey(Request $request): string
    {
        $ip = (string) $request->ip();
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }
        $packed = inet_pton($ip);

        return $packed === false ? $ip : bin2hex(substr($packed, 0, 8)).'::/64';
    }
}
