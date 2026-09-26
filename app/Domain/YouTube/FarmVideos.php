<?php

namespace App\Domain\YouTube;

use App\Jobs\UploadFarmVideo;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\YouTubeAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The life of a print video: the only place a FarmVideo changes state.
 *
 * Nothing goes to YouTube without the customer's consent, and nothing becomes public without an admin.
 * The customer may take the consent back at any time: the video is then deleted from YouTube.
 */
class FarmVideos
{
    public function __construct(private readonly YouTubeClient $youtube) {}

    /** A finished customer print with a time-lapse whose owner agreed to share it. */
    public function eligible(FarmOrder $order): bool
    {
        return $order->kind === FarmOrder::KIND_PRINT
            && $order->video_consent
            && $order->timelapse_path
            && Storage::disk(config('farm.disk'))->exists($order->timelapse_path);
    }

    /** Called when the time-lapse is ready and when the customer agrees later. Rejected videos stay rejected. */
    public function queueFor(FarmOrder $order): ?FarmVideo
    {
        if (! $this->eligible($order) || ! YouTubeAccount::current()) {
            return null;
        }
        $video = $order->video()->first();
        if ($video && ! in_array($video->status, [FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_FAILED], true)) {
            return $video;
        }
        $video ??= new FarmVideo(['farm_order_id' => $order->id]);
        $video->fill([
            'status' => FarmVideo::STATUS_QUEUED, 'youtube_id' => null, 'error' => null,
            'title' => $video->title ?: $this->defaultTitle($order),
            'description' => $video->description ?: $this->defaultDescription($order),
        ])->save();
        UploadFarmVideo::dispatch($video->id);

        return $video;
    }

    /** The queue job: send the time-lapse to YouTube as a private video. */
    public function upload(FarmVideo $video): void
    {
        $order = $video->order;
        // a second run of the same job (queue retry) or a video the customer took back meanwhile
        if ($video->status !== FarmVideo::STATUS_QUEUED) {
            return;
        }
        if (! $order || ! $this->eligible($order)) {
            $video->update(['status' => FarmVideo::STATUS_WITHDRAWN]);

            return;
        }
        $video->update(['status' => FarmVideo::STATUS_UPLOADING, 'error' => null]);

        try {
            $id = $this->youtube->upload(Storage::disk(config('farm.disk'))->path($order->timelapse_path), $video->title, (string) $video->description);
        } catch (YouTubeError $e) {
            if ($e->isQuota()) {
                $video->update(['status' => FarmVideo::STATUS_QUEUED, 'error' => $e->getMessage()]);
                UploadFarmVideo::dispatch($video->id)->delay(now()->addMinutes((int) config('youtube.retry_after_minutes', 360)));
            } else {
                $video->update(['status' => FarmVideo::STATUS_FAILED, 'error' => $e->getMessage()]);
                Log::warning('YouTube upload failed', ['video' => $video->id, 'reason' => $e->reason, 'error' => $e->getMessage()]);
            }

            return;
        }
        $video->update(['status' => FarmVideo::STATUS_UPLOADED, 'youtube_id' => $id, 'uploaded_at' => now()]);

        // the customer changed their mind while the upload ran
        if (! $order->refresh()->video_consent) {
            $this->withdraw($order);
        }
    }

    /** Make an uploaded video public, with the title and description the admin settled on. */
    public function publish(FarmVideo $video, string $title, string $description, int $adminId): void
    {
        if (! in_array($video->status, [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_PUBLISHED], true) || ! $video->youtube_id) {
            throw new YouTubeError('not_uploaded', 'The video is not on YouTube yet.');
        }
        if (! $video->order?->video_consent) {
            throw new YouTubeError('no_consent', 'The customer has taken the consent back.');
        }
        $privacy = $this->youtube->publish($video->youtube_id, $title, $description);
        $public = $privacy === 'public';
        $video->update([
            'title' => $title, 'description' => $description,
            'status' => $public ? FarmVideo::STATUS_PUBLISHED : FarmVideo::STATUS_UPLOADED,
            'published_at' => $public ? now() : null, 'decided_by' => $adminId, 'decided_at' => now(),
            // an API project YouTube has not audited yet keeps every uploaded video locked private
            'error' => $public ? null : 'YouTube left the video '.$privacy.' (API project not audited yet?).',
        ]);
        if (! $public) {
            throw new YouTubeError('locked_private', (string) $video->error);
        }
    }

    /** Not for the channel: delete it from YouTube, remember the decision (a later consent does not upload it again). */
    public function reject(FarmVideo $video, int $adminId): void
    {
        if ($video->youtube_id) {
            $this->youtube->delete($video->youtube_id);
        }
        $video->update(['status' => FarmVideo::STATUS_REJECTED, 'youtube_id' => null, 'published_at' => null, 'decided_by' => $adminId, 'decided_at' => now()]);
    }

    /** Failed, or stuck in `uploading` after a crash: try again. */
    public function retry(FarmVideo $video): void
    {
        if (in_array($video->status, [FarmVideo::STATUS_FAILED, FarmVideo::STATUS_UPLOADING, FarmVideo::STATUS_QUEUED], true)) {
            $video->update(['status' => FarmVideo::STATUS_QUEUED, 'error' => null]);
            UploadFarmVideo::dispatch($video->id);
        }
    }

    /** The customer's switch on the order page (and the checkbox when paying). */
    public function setConsent(FarmOrder $order, bool $consent): void
    {
        $order->forceFill(['video_consent' => $consent, 'video_consent_at' => now()])->save();
        $consent ? $this->queueFor($order) : $this->withdraw($order);
    }

    /** Consent taken back: the YouTube copy goes away at once. */
    private function withdraw(FarmOrder $order): void
    {
        $video = $order->video()->first();
        if (! $video || in_array($video->status, [FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_REJECTED], true)) {
            return;
        }
        if ($video->youtube_id) {
            try {
                $this->youtube->delete($video->youtube_id);
            } catch (YouTubeError $e) {
                // keep the id so an admin sees the video still has to be removed by hand
                $video->update(['status' => FarmVideo::STATUS_WITHDRAWN, 'error' => 'Delete on YouTube failed: '.$e->getMessage()]);
                Log::error('YouTube delete after withdrawn consent failed', ['video' => $video->id, 'youtube_id' => $video->youtube_id, 'error' => $e->getMessage()]);

                return;
            }
        }
        $video->update(['status' => FarmVideo::STATUS_WITHDRAWN, 'youtube_id' => null, 'published_at' => null]);
    }

    public function defaultTitle(FarmOrder $order): string
    {
        return __('youtube.video.title', $this->facts($order), config('youtube.language'));
    }

    public function defaultDescription(FarmOrder $order): string
    {
        return __('youtube.video.description', $this->facts($order), config('youtube.language'));
    }

    private function facts(FarmOrder $order): array
    {
        $order->loadMissing(['color.material', 'printer']);
        $minutes = (int) ($order->actual_minutes ?: $order->est_minutes);

        return [
            'material' => $order->color?->material?->label() ?? '',
            'color' => $order->color?->displayName() ?? '',
            'printer' => $order->printer?->model ?: ($order->printer?->name ?? ''),
            'time' => $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min',
            'url' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
