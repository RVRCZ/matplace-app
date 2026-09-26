<?php

namespace App\Domain\YouTube;

use App\Models\YouTubeAccount;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The few YouTube Data API v3 calls the print videos need, over plain HTTP (no Google SDK):
 * connect the channel (OAuth code → refresh token), upload (resumable), update title/privacy, delete.
 * Scope `youtube`: `youtube.upload` alone cannot make a private video public.
 */
class YouTubeClient
{
    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const REVOKE = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/youtube/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/youtube/v3/videos';

    public const SCOPE = 'https://www.googleapis.com/auth/youtube';

    public function configured(): bool
    {
        return (string) config('youtube.client_id') !== '' && (string) config('youtube.client_secret') !== '';
    }

    public function authUrl(string $redirect, string $state): string
    {
        return self::AUTH.'?'.http_build_query([
            'client_id' => config('youtube.client_id'),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',          // always hand out a refresh token, also on a second connect
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** OAuth callback: keep the refresh token and remember which channel it belongs to. Replaces an earlier connection. */
    public function connect(string $code, string $redirect, ?int $userId): YouTubeAccount
    {
        $r = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'code' => $code, 'client_id' => config('youtube.client_id'), 'client_secret' => config('youtube.client_secret'),
            'redirect_uri' => $redirect, 'grant_type' => 'authorization_code',
        ]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Token exchange');
        }
        $refresh = (string) $r->json('refresh_token');
        if ($refresh === '') {
            throw new YouTubeError('no_refresh_token', 'Google returned no refresh token.');
        }
        $access = (string) $r->json('access_token');

        $ch = Http::withToken($access)->timeout(20)->get(self::API.'/channels', ['part' => 'snippet', 'mine' => 'true']);
        if (! $ch->successful()) {
            throw YouTubeError::from($ch, 'Channel lookup');
        }
        $channel = $ch->json('items.0');
        if (! $channel) {
            throw new YouTubeError('no_channel', 'This Google account has no YouTube channel.');
        }

        YouTubeAccount::query()->delete();

        return YouTubeAccount::create([
            'channel_id' => $channel['id'],
            'channel_title' => $channel['snippet']['title'] ?? null,
            'refresh_token' => $refresh,
            'access_token' => $access,
            'access_expires_at' => now()->addSeconds(max(60, (int) $r->json('expires_in', 3600) - 60)),
            'connected_by' => $userId,
        ]);
    }

    public function disconnect(): void
    {
        if ($account = YouTubeAccount::current()) {
            Http::asForm()->timeout(10)->post(self::REVOKE, ['token' => $account->refresh_token]);   // best effort
        }
        YouTubeAccount::query()->delete();
    }

    /** Upload an MP4 as a private video; returns the YouTube video id. */
    public function upload(string $path, string $title, string $description): string
    {
        $size = filesize($path);
        $init = $this->api()->withHeaders(['X-Upload-Content-Type' => 'video/mp4', 'X-Upload-Content-Length' => (string) $size])
            ->post(self::UPLOAD.'?uploadType=resumable&part=snippet,status', [
                'snippet' => $this->snippet($title, $description),
                'status' => ['privacyStatus' => 'private', 'selfDeclaredMadeForKids' => false, 'embeddable' => true],
            ]);
        if (! $init->successful() || ! $init->header('Location')) {
            throw YouTubeError::from($init, 'Upload start');
        }

        $put = $this->api()->timeout(600)->withBody(Utils::streamFor(fopen($path, 'rb')), 'video/mp4')->put($init->header('Location'));
        if (! $put->successful() || ! $put->json('id')) {
            throw YouTubeError::from($put, 'Upload');
        }

        return (string) $put->json('id');
    }

    /** New title/description and public. Returns the privacy YouTube really set: an unaudited API project stays `private`. */
    public function publish(string $id, string $title, string $description): string
    {
        $r = $this->api()->put(self::API.'/videos?part=snippet,status', [
            'id' => $id,
            'snippet' => $this->snippet($title, $description),
            'status' => ['privacyStatus' => 'public', 'selfDeclaredMadeForKids' => false, 'embeddable' => true],
        ]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Publish');
        }

        return (string) $r->json('status.privacyStatus', 'public');
    }

    /** Remove the video from YouTube; a video that is already gone counts as removed. */
    public function delete(string $id): void
    {
        $r = $this->api()->delete(self::API.'/videos?id='.urlencode($id));
        if (! $r->successful() && $r->status() !== 404) {
            throw YouTubeError::from($r, 'Delete');
        }
    }

    private function snippet(string $title, string $description): array
    {
        return [
            'title' => mb_substr($title, 0, 100),
            'description' => mb_substr($description, 0, 5000),
            'categoryId' => (string) config('youtube.category_id'),
            'tags' => (array) config('youtube.tags'),
            'defaultLanguage' => config('youtube.language'),
            'defaultAudioLanguage' => config('youtube.language'),
        ];
    }

    private function api(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(60);
    }

    private function accessToken(): string
    {
        $account = YouTubeAccount::current();
        if (! $account) {
            throw new YouTubeError('not_connected', 'No YouTube channel is connected.');
        }
        if ($account->access_token && $account->access_expires_at?->isFuture()) {
            return $account->access_token;
        }
        $r = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'refresh_token' => $account->refresh_token, 'client_id' => config('youtube.client_id'),
            'client_secret' => config('youtube.client_secret'), 'grant_type' => 'refresh_token',
        ]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Token refresh');
        }
        $account->forceFill([
            'access_token' => (string) $r->json('access_token'),
            'access_expires_at' => now()->addSeconds(max(60, (int) $r->json('expires_in', 3600) - 60)),
        ])->save();

        return $account->access_token;
    }
}
