<?php

namespace App\Domain\YouTube;

use App\Domain\Stats\SearchConsoleClient;
use App\Models\SearchConsoleAccount;
use App\Models\YouTubeAccount;
use Carbon\Carbon;
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

    /** commentThreads.insert takes only this one (YOUTUBE_PIN_COMMENT); asking for it means a new consent on connecting. */
    public const SCOPE_COMMENTS = 'https://www.googleapis.com/auth/youtube.force-ssl';

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
            'scope' => $this->scopes(),
            'access_type' => 'offline',
            'prompt' => 'consent',          // always hand out a refresh token, also on a second connect
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function scopes(): string
    {
        return self::SCOPE.(config('youtube.pin_comment') ? ' '.self::SCOPE_COMMENTS : '');
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

    /**
     * Forgets the channel. Google's revoke takes the user's whole grant for the OAuth client, so while Search Console reads
     * with the same client and account (docs/AN.md §14), the token is only forgotten here and the grant stays for it.
     */
    public function disconnect(): void
    {
        if (($account = YouTubeAccount::current()) && ! (SearchConsoleClient::sharesClientWithYouTube() && SearchConsoleAccount::query()->exists())) {
            Http::asForm()->timeout(10)->post(self::REVOKE, ['token' => $account->refresh_token]);   // best effort
        }
        YouTubeAccount::query()->delete();
    }

    /** Upload an MP4 as a private video (public by itself at $publishAt when given); returns the YouTube video id. */
    public function upload(string $path, string $title, string $description, ?\DateTimeInterface $publishAt = null): string
    {
        // the worker lives long and the music mix is written to the same path every time: a cached size would be the old one
        clearstatcache(true, $path);
        $size = filesize($path);
        $init = $this->api()->withHeaders(['X-Upload-Content-Type' => 'video/mp4', 'X-Upload-Content-Length' => (string) $size])
            ->post(self::UPLOAD.'?uploadType=resumable&part=snippet,status', [
                'snippet' => $this->snippet($title, $description),
                'status' => $this->status('private', $publishAt),
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

    /**
     * New title/description and public now, or private until $publishAt (YouTube makes it public then). Returns what
     * YouTube really set: `privacy` (an unaudited API project stays `private`) and `publishAt` (empty when not scheduled).
     *
     * @return array{privacy: string, publishAt: string}
     */
    public function publish(string $id, string $title, string $description, ?\DateTimeInterface $publishAt = null): array
    {
        $r = $this->api()->put(self::API.'/videos?part=snippet,status', [
            'id' => $id,
            'snippet' => $this->snippet($title, $description),
            'status' => $this->status($publishAt ? 'private' : 'public', $publishAt),
        ]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Publish');
        }

        return ['privacy' => (string) $r->json('status.privacyStatus', $publishAt ? 'private' : 'public'), 'publishAt' => (string) $r->json('status.publishAt', '')];
    }

    private function status(string $privacy, ?\DateTimeInterface $publishAt): array
    {
        return ['privacyStatus' => $privacy, 'selfDeclaredMadeForKids' => false, 'embeddable' => true]
            + ($publishAt ? ['publishAt' => Carbon::instance($publishAt)->utc()->format('Y-m-d\TH:i:s\Z')] : []);
    }

    /**
     * Numbers and privacy of up to 50 videos per request (1 quota unit each request).
     *
     * @param  list<string>  $ids
     * @return array<string, array{views: int, likes: int|null, comments: int|null, privacy: string}> missing ids are gone
     */
    public function statistics(array $ids): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            $r = $this->api()->get(self::API.'/videos', ['part' => 'statistics,status', 'id' => implode(',', $chunk), 'maxResults' => 50]);
            if (! $r->successful()) {
                throw YouTubeError::from($r, 'Statistics');
            }
            foreach ((array) $r->json('items') as $item) {
                $s = (array) ($item['statistics'] ?? []);
                $out[(string) $item['id']] = [
                    'views' => (int) ($s['viewCount'] ?? 0),
                    'likes' => isset($s['likeCount']) ? (int) $s['likeCount'] : null,       // hidden likes are missing
                    'comments' => isset($s['commentCount']) ? (int) $s['commentCount'] : null,
                    'privacy' => (string) ($item['status']['privacyStatus'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * The channel's own comment under its video (commentThreads.insert, 50 quota units, scope youtube.force-ssl);
     * returns the comment thread's id. The API cannot pin it: that is one click in Studio.
     */
    public function comment(string $videoId, string $text): string
    {
        $account = YouTubeAccount::current();
        $r = $this->api()->post(self::API.'/commentThreads?part=snippet', ['snippet' => [
            'channelId' => (string) $account?->channel_id, 'videoId' => $videoId, 'topLevelComment' => ['snippet' => ['textOriginal' => $text]],
        ]]);
        if (! $r->successful() || ! $r->json('id')) {
            throw YouTubeError::from($r, 'Comment');
        }

        return (string) $r->json('id');
    }

    /** A new title and description, the privacy as it is (videos.update part=snippet, 50 units): the retitle of the channel. */
    public function retitle(string $id, string $title, string $description): void
    {
        $r = $this->api()->put(self::API.'/videos?part=snippet', ['id' => $id, 'snippet' => $this->snippet($title, $description)]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Retitle');
        }
    }

    /** public | unlisted | private, nothing else changed (videos.update part=status, 50 units). */
    public function setPrivacy(string $id, string $privacy): void
    {
        $r = $this->api()->put(self::API.'/videos?part=status', ['id' => $id, 'status' => $this->status($privacy, null)]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Privacy');
        }
    }

    /**
     * Every video of the channel (its uploads playlist), newest first: the ones made by hand too. 1 unit for the
     * channel, 1 per 50 videos.
     *
     * @return list<array{id: string, title: string, published_at: string, privacy: string}>
     */
    public function uploads(): array
    {
        $ch = $this->api()->get(self::API.'/channels', ['part' => 'contentDetails', 'mine' => 'true']);
        if (! $ch->successful()) {
            throw YouTubeError::from($ch, 'Channel');
        }
        $playlist = (string) $ch->json('items.0.contentDetails.relatedPlaylists.uploads');
        if ($playlist === '') {
            return [];
        }
        $out = [];
        $page = null;
        do {
            $r = $this->api()->get(self::API.'/playlistItems', array_filter(['part' => 'snippet,status', 'playlistId' => $playlist, 'maxResults' => 50, 'pageToken' => $page]));
            if (! $r->successful()) {
                throw YouTubeError::from($r, 'Uploads');
            }
            foreach ((array) $r->json('items') as $item) {
                $out[] = ['id' => (string) ($item['snippet']['resourceId']['videoId'] ?? ''), 'title' => (string) ($item['snippet']['title'] ?? ''),
                    'published_at' => (string) ($item['snippet']['publishedAt'] ?? ''), 'privacy' => (string) ($item['status']['privacyStatus'] ?? '')];
            }
            $page = $r->json('nextPageToken');
        } while ($page);

        return $out;
    }

    /**
     * The channel's own playlists (1 unit).
     *
     * @return array<string, string> id => title
     */
    public function playlists(): array
    {
        $r = $this->api()->get(self::API.'/playlists', ['part' => 'snippet', 'mine' => 'true', 'maxResults' => 50]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Playlists');
        }
        $out = [];
        foreach ((array) $r->json('items') as $item) {
            $out[(string) $item['id']] = (string) ($item['snippet']['title'] ?? '');
        }

        return $out;
    }

    /** A public playlist (playlists.insert, 50 units); returns its id. */
    public function createPlaylist(string $title, string $description = ''): string
    {
        $r = $this->api()->post(self::API.'/playlists?part=snippet,status', [
            'snippet' => ['title' => mb_substr($title, 0, 150), 'description' => mb_substr($description, 0, 5000), 'defaultLanguage' => config('youtube.language')],
            'status' => ['privacyStatus' => 'public'],
        ]);
        if (! $r->successful() || ! $r->json('id')) {
            throw YouTubeError::from($r, 'Playlist');
        }

        return (string) $r->json('id');
    }

    /** The video at the end of the playlist (playlistItems.insert, 50 units). */
    public function addToPlaylist(string $playlistId, string $videoId): void
    {
        $r = $this->api()->post(self::API.'/playlistItems?part=snippet', ['snippet' => ['playlistId' => $playlistId, 'resourceId' => ['kind' => 'youtube#video', 'videoId' => $videoId]]]);
        if (! $r->successful()) {
            throw YouTubeError::from($r, 'Playlist item');
        }
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
