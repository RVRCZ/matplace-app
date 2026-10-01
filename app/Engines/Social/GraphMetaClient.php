<?php

namespace App\Engines\Social;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Graph API with a System User token (taken over from the old site's MetaGraphService, MetaPostService,
 * MetaAdsService and MetaCapiService). Posting as the page needs the page's own token, which is asked for once
 * per request from /me/accounts.
 */
final class GraphMetaClient implements MetaClient
{
    private const BASE = 'https://graph.facebook.com/';

    private ?string $pageToken = null;

    /** @param  array{token?: ?string, page_id?: ?string, ig_id?: ?string, ad_account_id?: ?string, pixel_id?: ?string, capi_token?: ?string, capi_test_code?: ?string, version?: string, timeout?: int}  $config */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return ! empty($this->config['token']) && ! empty($this->config['page_id']);
    }

    public function postToPage(string $message, ?string $link = null, ?string $imageUrl = null): string
    {
        $page = (string) ($this->config['page_id'] ?? '');
        if ($page === '') {
            throw new MetaFailed('META_PAGE_ID is not set.');
        }
        $token = $this->pageToken();
        $result = $imageUrl
            ? $this->call('POST', $page.'/photos', ['url' => $imageUrl, 'caption' => trim($message.($link ? "\n".$link : ''))], $token)
            : $this->call('POST', $page.'/feed', array_filter(['message' => $message, 'link' => $link]), $token);

        return (string) ($result['post_id'] ?? $result['id'] ?? '');
    }

    public function postToInstagram(string $imageUrl, string $caption): string
    {
        $ig = (string) ($this->config['ig_id'] ?? '');
        if ($ig === '') {
            throw new MetaFailed('META_IG_ID is not set.');
        }
        $token = $this->pageToken();
        // two steps: a media container, then publishing it
        $container = (string) ($this->call('POST', $ig.'/media', ['image_url' => $imageUrl, 'caption' => $caption], $token)['id'] ?? '');
        if ($container === '') {
            throw new MetaFailed('Instagram created no media container.');
        }

        return (string) ($this->call('POST', $ig.'/media_publish', ['creation_id' => $container], $token)['id'] ?? '');
    }

    public function campaigns(string $period = 'last_7d'): array
    {
        $account = trim((string) ($this->config['ad_account_id'] ?? ''));
        if ($account === '') {
            throw new MetaFailed('META_AD_ACCOUNT_ID is not set.');
        }
        $account = str_starts_with($account, 'act_') ? $account : 'act_'.$account;
        $period = in_array($period, ['last_7d', 'last_30d'], true) ? $period : 'last_7d';
        $rows = (array) ($this->call('GET', $account.'/campaigns', [
            'fields' => 'id,name,status,account_currency,insights.date_preset('.$period.'){spend,impressions,clicks}', 'limit' => 50,
        ])['data'] ?? []);

        return array_values(array_map(function (array $c) {
            $insights = $c['insights']['data'][0] ?? [];

            return [
                'id' => (string) ($c['id'] ?? ''), 'name' => (string) ($c['name'] ?? ''), 'status' => (string) ($c['status'] ?? ''),
                'spend' => (float) ($insights['spend'] ?? 0), 'impressions' => (int) ($insights['impressions'] ?? 0), 'clicks' => (int) ($insights['clicks'] ?? 0),
                'currency' => isset($c['account_currency']) ? (string) $c['account_currency'] : null,
            ];
        }, $rows));
    }

    public function sendConversion(string $event, string $eventId, array $user, array $data = [], ?string $url = null): bool
    {
        $pixel = (string) ($this->config['pixel_id'] ?? '');
        $token = (string) ($this->config['capi_token'] ?? '');
        if ($pixel === '' || $token === '') {
            return false;
        }
        $payload = ['data' => [array_filter([
            'event_name' => $event, 'event_time' => time(), 'event_id' => $eventId, 'action_source' => 'website', 'event_source_url' => $url,
            'user_data' => array_filter([
                // personal data leave only as SHA-256 of the trimmed lower-case value; address and browser go as they are (Meta asks for that)
                'em' => ! empty($user['email']) ? [hash('sha256', mb_strtolower(trim((string) $user['email'])))] : null,
                'client_ip_address' => $user['ip'] ?? null, 'client_user_agent' => $user['user_agent'] ?? null,
                'fbp' => $user['fbp'] ?? null, 'fbc' => $user['fbc'] ?? null,
            ]),
            'custom_data' => $data ?: null,
        ])]] + array_filter(['test_event_code' => $this->config['capi_test_code'] ?? null]);
        try {
            $res = Http::timeout(10)->asJson()->post(self::BASE.$this->version().'/'.rawurlencode($pixel).'/events?access_token='.rawurlencode($token), $payload);
            if (! $res->successful()) {
                Log::warning('Meta Conversions API refused an event', ['event' => $event, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
            }

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('Meta Conversions API could not be reached', ['event' => $event, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Posting as the page needs the page's token, not the system user's (else Graph answers #200). */
    private function pageToken(): string
    {
        if ($this->pageToken !== null) {
            return $this->pageToken;
        }
        $page = (string) ($this->config['page_id'] ?? '');
        foreach ((array) ($this->call('GET', 'me/accounts', ['fields' => 'id,access_token', 'limit' => 100])['data'] ?? []) as $p) {
            if (($p['id'] ?? '') === $page && ! empty($p['access_token'])) {
                return $this->pageToken = (string) $p['access_token'];
            }
        }
        throw new MetaFailed('No page access token: check that the page is assigned to the system user.');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws MetaFailed
     */
    private function call(string $method, string $path, array $params, ?string $token = null): array
    {
        if (empty($this->config['token'])) {
            throw new MetaFailed('Meta is not connected (META_SYSTEM_TOKEN).');
        }
        $params['access_token'] = $token ?: (string) $this->config['token'];
        $url = self::BASE.$this->version().'/'.ltrim($path, '/');
        try {
            $http = Http::timeout((int) ($this->config['timeout'] ?? 25));
            $res = $method === 'GET' ? $http->get($url, $params) : $http->asForm()->post($url, $params);
        } catch (\Throwable $e) {
            throw new MetaFailed('Meta could not be reached: '.$e->getMessage());
        }
        $data = $res->json();
        if (! is_array($data) || isset($data['error'])) {
            throw new MetaFailed((string) ($data['error']['message'] ?? ('Graph API HTTP '.$res->status())));
        }

        return $data;
    }

    private function version(): string
    {
        return (string) ($this->config['version'] ?? 'v21.0');
    }
}
