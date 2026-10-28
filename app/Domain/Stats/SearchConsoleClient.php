<?php

namespace App\Domain\Stats;

use App\Models\SearchConsoleAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The two Search Console API calls the statistics need, over plain HTTP like the YouTube client (no Google SDK):
 * connect the account (OAuth code → refresh token, the properties it may read) and the search analytics of one day.
 * The same Google Cloud OAuth client as YouTube unless config/seo.php `search_console` names its own; the scope is
 * read-only, the callback https://matplace.com/admin/search-console/callback is registered in that client (docs/AN.md §14).
 */
class SearchConsoleClient
{
    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const REVOKE = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/webmasters/v3';

    public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function configured(): bool
    {
        return (string) config('seo.search_console.client_id') !== '' && (string) config('seo.search_console.client_secret') !== '';
    }

    public function authUrl(string $redirect, string $state): string
    {
        return self::AUTH.'?'.http_build_query([
            'client_id' => config('seo.search_console.client_id'),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',          // always hand out a refresh token, also on a second connect
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * OAuth callback: keep the refresh token and the properties the account may read; the property is chosen at once
     * when there is one, or the one of this site. Replaces an earlier connection.
     */
    public function connect(string $code, string $redirect, ?int $userId): SearchConsoleAccount
    {
        $r = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'code' => $code, 'client_id' => config('seo.search_console.client_id'), 'client_secret' => config('seo.search_console.client_secret'),
            'redirect_uri' => $redirect, 'grant_type' => 'authorization_code',
        ]);
        if (! $r->successful()) {
            throw SearchConsoleError::from($r, 'Token exchange');
        }
        $refresh = (string) $r->json('refresh_token');
        if ($refresh === '') {
            throw new SearchConsoleError('no_refresh_token', 'Google returned no refresh token.');
        }
        $access = (string) $r->json('access_token');
        $sites = $this->sitesWith($access);

        SearchConsoleAccount::query()->delete();

        return SearchConsoleAccount::create([
            'site_url' => self::pick($sites),
            'sites' => $sites,
            'refresh_token' => $refresh,
            'access_token' => $access,
            'access_expires_at' => now()->addSeconds(max(60, (int) $r->json('expires_in', 3600) - 60)),
            'connected_by' => $userId,
        ]);
    }

    /**
     * Forgets the account. Google's revoke takes the whole grant of the user for the OAuth client, every scope of it: with
     * the client shared with YouTube that would log the channel out too (it did, 28 Oct 2026), so the token is revoked at
     * Google only when Search Console has a client of its own; otherwise it is only forgotten here (Roman can drop it in
     * his Google account).
     */
    public function disconnect(): void
    {
        if (($account = SearchConsoleAccount::current()) && ! self::sharesClientWithYouTube()) {
            Http::asForm()->timeout(10)->post(self::REVOKE, ['token' => $account->refresh_token]);   // best effort
        }
        SearchConsoleAccount::query()->delete();
    }

    /** The same OAuth client as the YouTube channel (config/seo.php `search_console` falls back to it): one grant for both. */
    public static function sharesClientWithYouTube(): bool
    {
        $own = (string) config('seo.search_console.client_id');

        return $own !== '' && $own === (string) config('youtube.client_id');
    }

    /** @return list<string> the properties the connected account may read (sites.list), read again */
    public function sites(): array
    {
        return $this->sitesWith($this->accessToken());
    }

    /**
     * The rows of one day for one set of dimensions (`page,country` or `page,query,country`), request by request:
     * each yield is one page of the API's answer (config `row_limit` rows at most), the next starts where it ended.
     *
     * @param  list<string>  $dimensions
     * @return \Generator<int, list<array{keys: list<string>, clicks: int|float, impressions: int|float, ctr: float, position: float}>>
     */
    public function rows(string $site, string $date, array $dimensions): \Generator
    {
        $limit = max(1, (int) config('seo.search_console.row_limit', 25000));
        $url = self::API.'/sites/'.rawurlencode($site).'/searchAnalytics/query';
        for ($start = 0; ; $start += $limit) {
            $r = $this->post($url, ['startDate' => $date, 'endDate' => $date, 'dimensions' => array_values($dimensions), 'rowLimit' => $limit, 'startRow' => $start, 'type' => 'web']);
            $rows = (array) $r->json('rows', []);
            if ($rows) {
                yield array_values($rows);
            }
            if (count($rows) < $limit) {
                return;
            }
        }
    }

    /** The property of this site among the account's: the only one, the domain property of our host, or our address as a prefix; null = the admin picks. */
    public static function pick(array $sites): ?string
    {
        if (count($sites) === 1) {
            return $sites[0];
        }
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        foreach (['sc-domain:'.$host, 'sc-domain:'.preg_replace('/^www\./', '', $host), rtrim((string) config('app.url'), '/').'/'] as $wanted) {
            foreach ($sites as $site) {
                if (strtolower($site) === strtolower($wanted)) {
                    return $site;
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function sitesWith(string $access): array
    {
        $r = Http::withToken($access)->acceptJson()->timeout(20)->get(self::API.'/sites');
        if (! $r->successful()) {
            throw SearchConsoleError::from($r, 'Properties');
        }
        $sites = array_values(array_filter(array_map(fn ($s) => (string) ($s['siteUrl'] ?? ''), (array) $r->json('siteEntry', []))));
        sort($sites);

        return $sites;
    }

    /** One POST; a 429 (the minute's quota) waits once and tries again. */
    private function post(string $url, array $body): Response
    {
        $r = $this->api()->post($url, $body);
        if ($r->status() === 429) {
            sleep(max(0, (int) config('seo.search_console.retry_seconds', 60)));
            $r = $this->api()->post($url, $body);
        }
        if (! $r->successful()) {
            throw SearchConsoleError::from($r, 'Search analytics');
        }

        return $r;
    }

    private function api(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(60);
    }

    private function accessToken(): string
    {
        $account = SearchConsoleAccount::current();
        if (! $account) {
            throw new SearchConsoleError('not_connected', 'Search Console is not connected.');
        }
        if ($account->access_token && $account->access_expires_at?->isFuture()) {
            return $account->access_token;
        }
        $r = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'refresh_token' => $account->refresh_token, 'client_id' => config('seo.search_console.client_id'),
            'client_secret' => config('seo.search_console.client_secret'), 'grant_type' => 'refresh_token',
        ]);
        if (! $r->successful()) {
            throw SearchConsoleError::from($r, 'Token refresh');
        }
        $account->forceFill([
            'access_token' => (string) $r->json('access_token'),
            'access_expires_at' => now()->addSeconds(max(60, (int) $r->json('expires_in', 3600) - 60)),
        ])->save();

        return $account->access_token;
    }
}
