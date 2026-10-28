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
        // only Shorts (a print's, a how-to video's vertical one): the 16:9 stays on YouTube (one video per print on the page and Instagram)
        $videos = FarmVideo::with('order')->whereIn('kind', [FarmVideo::KIND_SHORT, FarmVideo::KIND_HOWTO, FarmVideo::KIND_PRICE])->whereNotNull('share')->where(fn ($q) => $q->where('status', FarmVideo::STATUS_PUBLISHED)
            ->orWhere(fn ($q) => $q->where('status', FarmVideo::STATUS_SCHEDULED)->where('scheduled_at', '<=', now())))->get()
            ->filter(fn (FarmVideo $v) => in_array($v->kind, [FarmVideo::KIND_SHORT, FarmVideo::KIND_PRICE], true) || ($v->meta['variant'] ?? null) === 'short');
        $n = 0;
        foreach ($videos as $video) {
            $n += $this->share($video);
        }

        return $n;
    }

    /** @return int platforms posted to now */
    public function share(FarmVideo $video): int
    {
        $path = $this->videos->pathOf($video);
        if (! $this->videos->consented($video) || $path === '' || ! Storage::disk(config('farm.disk'))->exists($path)) {
            return 0;
        }
        $done = SocialPost::where('subject_type', self::SUBJECT)->where('subject_id', $video->id)->get()->keyBy('platform');
        $n = 0;
        foreach ((array) $video->share as $platform) {
            if ($done->has($platform)) {
                continue;   // posted, or failed and waiting for the admin
            }
            // the description's links lead where they lead on YouTube, marked for this platform
            $source = $platform === 'instagram' ? 'instagram' : 'facebook';
            $text = $this->videos->forChannel(trim($video->title."\n\n".$video->description), $source);
            $post = SocialPost::create(['platform' => $platform, 'subject_type' => self::SUBJECT, 'subject_id' => $video->id, 'text' => $text,
                'link' => $video->watchUrl(), 'status' => SocialPost::STATUS_DRAFT, 'created_by' => $video->decided_by]);
            try {
                $id = match ($platform) {
                    'instagram' => $this->meta->postReelToInstagram(URL::temporarySignedRoute('social.video', now()->addDays(2), ['video' => $video->id]), $text),
                    // a plain post with a clickable link to the site (the video post's link is only text); the preview card is the site's OG picture
                    'facebook_link' => $this->meta->postToPage($text, $this->siteLink($video, $post)),
                    default => $this->meta->postVideoToPage(Storage::disk(config('farm.disk'))->path($path), (string) $video->title, $this->videos->forChannel((string) $video->description, $source)),
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

    /** The video's own target (FarmVideos::linkOf: the tool's page, the how-to's, the home page) marked for Facebook, written on the row too. */
    private function siteLink(FarmVideo $video, SocialPost $post): string
    {
        $link = $this->videos->linkOf($video, 'facebook');
        $post->update(['link' => $link]);

        return $link;
    }

    /** Forget a failed attempt so the next run tries that platform again. */
    public function retry(FarmVideo $video, string $platform): void
    {
        SocialPost::where('subject_type', self::SUBJECT)->where('subject_id', $video->id)->where('platform', $platform)->where('status', SocialPost::STATUS_FAILED)->delete();
    }
}
