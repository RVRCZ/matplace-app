<?php

namespace App\Engines\Social;

/**
 * Meta (Facebook page, Instagram account, ad account, Conversions API). Everything that leaves towards Meta goes
 * through here; tests use FakeMetaClient.
 */
interface MetaClient
{
    /** The token and the page are set: posts can be published. */
    public function available(): bool;

    /**
     * A post on the Facebook page: with a picture when there is one, else text with a link.
     *
     * @return string the id Meta gave the post
     *
     * @throws MetaFailed with Meta's answer as the text
     */
    public function postToPage(string $message, ?string $link = null, ?string $imageUrl = null): string;

    /**
     * A post on Instagram (always with a picture; links in captions are not clickable there).
     *
     * @throws MetaFailed
     */
    public function postToInstagram(string $imageUrl, string $caption): string;

    /**
     * A video on the Facebook page, uploaded from a local file (a short vertical or square one shows as a Reel).
     *
     * @return string the id Meta gave the video
     *
     * @throws MetaFailed
     */
    public function postVideoToPage(string $path, string $title, string $description): string;

    /**
     * A Reel on Instagram from a video Meta can download (a public address). Meta processes the file first; when it
     * is not done in time the failure says `retryLater`.
     *
     * @throws MetaFailed
     */
    public function postReelToInstagram(string $videoUrl, string $caption): string;

    /**
     * Campaigns of the ad account with what they spent in a period: read only.
     *
     * @param  string  $period  last_7d | last_30d
     * @return list<array{id: string, name: string, status: string, spend: float, impressions: int, clicks: int, currency: ?string}>
     *
     * @throws MetaFailed
     */
    public function campaigns(string $period = 'last_7d'): array;

    /**
     * One conversion through the Conversions API (server to Meta). The caller sends it only for a visitor who agreed
     * to marketing cookies; the e-mail is hashed here, never sent as written.
     *
     * @param  string  $event  Purchase | CompleteRegistration
     * @param  string  $eventId  the same id the pixel in the browser reports, so Meta counts the conversion once
     * @param  array{email?: ?string, ip?: ?string, user_agent?: ?string, fbp?: ?string, fbc?: ?string}  $user
     * @param  array<string, mixed>  $data  value, currency…
     */
    public function sendConversion(string $event, string $eventId, array $user, array $data = [], ?string $url = null): bool;
}
