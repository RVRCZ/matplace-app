<?php

namespace App\Domain\Stats;

use App\Models\SearchQuery;
use Illuminate\Support\Facades\Log;

/**
 * What people look for, for /admin/stats/search: the words, the language, how much our catalogue and the outside
 * sources returned. No account, no address and no session is kept with it, and anything in the words that looks
 * like an e-mail address or a phone number is blanked before it is stored.
 *
 * To tell "one person searched three times" from "three people searched", a row carries a mark of its visitor:
 * a keyed hash of the anonymous session and the day. It cannot be turned back into the session, and the same
 * visitor has another mark tomorrow, so searches do not add up into anybody's history.
 */
final class SearchLog
{
    public static function record(string $query, string $locale, int $local, int $external, ?int $sessionId = null): ?SearchQuery
    {
        $query = self::scrub($query);
        if ($query === '') {
            return null;
        }
        try {
            return SearchQuery::create(['locale' => $locale, 'query' => $query, 'results_local' => min(65535, max(0, $local)), 'results_external' => min(65535, max(0, $external)), 'visitor' => self::visitor($sessionId)]);
        } catch (\Throwable $e) {
            Log::warning('Search query was not recorded', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** The visitor's mark of today (null without a session). */
    public static function visitor(?int $sessionId): ?string
    {
        return $sessionId ? substr(hash_hmac('sha256', $sessionId.'|'.now()->toDateString(), (string) config('app.key')), 0, 16) : null;
    }

    /** Lower case, single spaces, no e-mail addresses, no long runs of digits (phones, card numbers). */
    public static function scrub(string $query): string
    {
        $query = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        $query = (string) preg_replace('/[^\s@]+@[^\s@]+\.[a-z]{2,}/iu', '[e-mail]', $query);
        $query = (string) preg_replace('/\+?\d[\d\s().-]{7,}\d/u', '[číslo]', $query);

        return mb_substr($query, 0, 200);
    }
}
