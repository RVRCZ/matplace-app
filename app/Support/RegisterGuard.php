<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * What stops a robot that fills in the real registration form (docs/AH.md §10; 58 accounts "Test" in a day from six
 * addresses, 18 Oct 2026): the address it comes from, counted over an hour and a day.
 */
class RegisterGuard
{
    /** Registrations from one address: an hour, a day (named limiter `register`, AppServiceProvider). */
    public const PER_HOUR = 3;

    public const PER_DAY = 5;

    /** A person needs a few seconds for the form; a page left open longer than this is sent again from a fresh one. */
    public const MIN_SECONDS = 4;

    public const MAX_SECONDS = 7200;

    /** The moment the form was shown, signed with APP_KEY (field `t`): a robot cannot write its own. */
    public static function stamp(?int $at = null): string
    {
        return Crypt::encryptString('register:'.($at ?? now()->getTimestamp()));
    }

    /**
     * Why a posted form is refused, or null when it is fine: missing / forged (not ours), early (under 4 s), late (over 2 h).
     */
    public static function refusal(mixed $stamp, ?int $now = null): ?string
    {
        if (! is_string($stamp) || $stamp === '') {
            return 'missing';
        }
        try {
            $plain = Crypt::decryptString($stamp);
        } catch (DecryptException) {
            return 'forged';
        }
        if (! preg_match('/^register:(\d+)$/', $plain, $m)) {
            return 'forged';
        }
        $age = ($now ?? now()->getTimestamp()) - (int) $m[1];

        return $age < self::MIN_SECONDS ? 'early' : ($age > self::MAX_SECONDS ? 'late' : null);
    }

    /**
     * Is this the first registration from the address today? The first one gets the verification mail right away,
     * the next ones only when the person asks for it on the account page (a robot then mails nobody).
     */
    public static function firstToday(Request $request): bool
    {
        return Cache::add('register-mail:'.self::ipKey($request).':'.now()->toDateString(), 1, now()->endOfDay());
    }

    /** Turnstile is on when both of its keys are set (config/services.php). */
    public static function turnstileOn(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret'));
    }

    /**
     * Cloudflare's word on the token the widget put into the form. A missing or refused token stops the registration;
     * Cloudflare not answering does not (the limits and the signed time still stand), it is written to the log.
     */
    public static function turnstilePasses(Request $request): bool
    {
        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '') {
            return false;
        }
        try {
            $answer = Http::asForm()->timeout(5)->post((string) config('services.turnstile.verify_url'), [
                'secret' => config('services.turnstile.secret'), 'response' => $token, 'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('turnstile unreachable, registration let through', ['error' => $e->getMessage()]);

            return true;
        }
        if (! $answer->successful()) {
            Log::warning('turnstile answered '.$answer->status().', registration let through');

            return true;
        }

        return $answer->json('success') === true;
    }

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
