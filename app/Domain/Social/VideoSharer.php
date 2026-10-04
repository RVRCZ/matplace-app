<?php

namespace App\Domain\Social;

use App\Domain\YouTube\FarmVideos;
use App\Engines\Social\MetaClient;
use App\Engines\Social\MetaFailed;
use App\Models\FarmVideo;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * The print videos the admin approved for the Facebook page and Instagram go there the moment they are public on
 * YouTube (the same evening slot): the page gets the file itself, Instagram a Reel from a signed address of ours.
 * One SocialPost row per platform says what came of it; a failure is kept for the admin, not retried on its own.
 */
final class VideoSharer
{
    public const SUBJECT = 'farm_video';

    /** facebook = the video on the page, facebook_link = a post with a clickable link to the site, instagram = a Reel */
    public const PLATFORMS = ['facebook', 'facebook_link', 'instagram'];

    public function __construct(private readonly MetaClient $meta, private readonly FarmVideos $videos) {}

    /** The videos whose time has come: public on YouTube (or past the slot YouTube flips them at) with a platform still waiting. */
    public function due(): int
    {
        if (! $this->meta->available()) {
            return 0;
        }
        $videos = FarmVideo::with('order')->whereNotNull('share')->where(fn ($q) => $q->where('status', FarmVideo::STATUS_PUBLISHED)
            ->orWhere(fn ($q) => $q->where('status', FarmVideo::STATUS_SCHEDULED)->where('scheduled_at', '<=', now())))->get();
        $n = 0;
        foreach ($videos as $video) {
            $n += $this->share($video);
        }

        return $n;
    }

    /** @return int platforms posted to now */
    public function share(FarmVideo $video): int
    {
        $order = $video->order;
        if (! $order || ! $order->video_consent || ! $this->videos->eligible($order)) {
            return 0;
        }
        $done = SocialPost::where('subject_type', self::SUBJECT)->where('subject_id', $video->id)->get()->keyBy('platform');
        $n = 0;
        foreach ((array) $video->share as $platform) {
            if ($done->has($platform)) {
                continue;   // posted, or failed and waiting for the admin
            }
            $text = trim($video->title."\n\n".$video->description);
            $post = SocialPost::create(['platform' => $platform, 'subject_type' => self::SUBJECT, 'subject_id' => $video->id, 'text' => $text,
                'link' => $video->watchUrl(), 'status' => SocialPost::STATUS_DRAFT, 'created_by' => $video->decided_by]);
            try {
                $id = match ($platform) {
                    'instagram' => $this->meta->postReelToInstagram(URL::temporarySignedRoute('social.video', now()->addDays(2), ['video' => $video->id]), $text),
                    // a plain post with a clickable link to the site (the video post's link is only text); the preview card is the site's OG picture
                    'facebook_link' => $this->meta->postToPage($text, $this->siteLink($post)),
                    default => $this->meta->postVideoToPage(Storage::disk(config('farm.disk'))->path($this->videos->file($order)), (string) $video->title, (string) $video->description),
                };
                $post->update(['status' => SocialPost::STATUS_POSTED, 'external_id' => $id, 'posted_at' => now()]);
                $n++;
            } catch (MetaFailed $e) {
                if ($e->retryLater) {
                    $post->delete();   // Meta is still working on it: the next run asks again
                    Log::info('Social video not ready yet', ['video' => $video->id, 'platform' => $platform, 'error' => $e->getMessage()]);

                    continue;
                }
                $post->update(['status' => SocialPost::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);
                Log::warning('Social video post failed', ['video' => $video->id, 'platform' => $platform, 'error' => $e->getMessage()]);
            }
        }

        return $n;
    }

    /** The site with the campaign tags of a video post, written on the row too. */
    private function siteLink(SocialPost $post): string
    {
        $link = rtrim((string) config('app.url'), '/').'/?'.http_build_query(['utm_source' => 'facebook', 'utm_medium' => 'social', 'utm_campaign' => 'video']);
        $post->update(['link' => $link]);

        return $link;
    }

    /** Forget a failed attempt so the next run tries that platform again. */
    public function retry(FarmVideo $video, string $platform): void
    {
        SocialPost::where('subject_type', self::SUBJECT)->where('subject_id', $video->id)->where('platform', $platform)->where('status', SocialPost::STATUS_FAILED)->delete();
    }
}
