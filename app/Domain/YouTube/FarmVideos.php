<?php

namespace App\Domain\YouTube;

use App\Domain\Farm\FarmSettings;
use App\Jobs\UploadFarmVideo;
use App\Mail\FarmAdminAlert;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\YouTubeAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The life of a print video: the only place a FarmVideo changes state.
 *
 * Nothing goes to YouTube without the customer's consent, and nothing becomes public without an admin.
 * The customer may take the consent back at any time: the video is then deleted from YouTube.
 */
class FarmVideos
{
    /** Models made from photos (busts and figures, lithophanes, reliefs): may show a person, so the consent box starts empty. */
    public const PRIVATE_KINDS = ['generated', 'lithophane', 'relief'];

    public function __construct(private readonly YouTubeClient $youtube) {}

    /** A finished customer print with a time-lapse whose owner agreed to share it. */
    public function eligible(FarmOrder $order): bool
    {
        return in_array($order->kind, [FarmOrder::KIND_PRINT, FarmOrder::KIND_SHOWCASE], true)
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
            'score' => $video->score ?? $this->score($order),
            'music' => $music = $video->music ?? $this->pickMusic($order),
            'description' => $video->description ?: $this->defaultDescription($order, $music),
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
            $withMusic = $this->withMusic($order, $video->music);
            $id = $this->youtube->upload($withMusic ?? Storage::disk(config('farm.disk'))->path($this->file($order)), $video->title, (string) $video->description);
        } catch (YouTubeError $e) {
            if ($e->isQuota()) {
                // YouTube's own limit (a young channel takes only a few videos a day): the admin reads when the next try is
                $next = now()->addMinutes((int) config('youtube.retry_after_minutes', 360));
                $video->update(['status' => FarmVideo::STATUS_QUEUED, 'error' => 'YouTube teď další video nepřijal (limit kanálu, ne náš); zkusíme to znovu '.$next->copy()->timezone('Europe/Prague')->format('j. n. H:i').'. '.$e->getMessage()]);
                UploadFarmVideo::dispatch($video->id)->delay($next);
            } else {
                $video->update(['status' => FarmVideo::STATUS_FAILED, 'error' => $e->getMessage()]);
                Log::warning('YouTube upload failed', ['video' => $video->id, 'reason' => $e->reason, 'error' => $e->getMessage()]);
            }

            return;
        }
        if ($withMusic) {
            @unlink($withMusic);
        }
        $video->update(['status' => FarmVideo::STATUS_UPLOADED, 'youtube_id' => $id, 'uploaded_at' => now()]);

        // the customer changed their mind while the upload ran
        if (! $order->refresh()->video_consent) {
            $this->withdraw($order);

            return;
        }
        $this->tellAdmin($video, $order);
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

    /** The videos were built again (farm:timelapse): the private copy on YouTube goes, the new file goes up. */
    public function replace(FarmVideo $video): void
    {
        // a published one too, when the admin wants it (its views stay with the deleted copy; the new one waits for approval)
        if (! in_array($video->status, [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_PUBLISHED], true)) {
            throw new YouTubeError('not_replaceable', 'Only a video on YouTube can be replaced.');
        }
        if ($video->youtube_id) {
            $this->youtube->delete($video->youtube_id);
        }
        $video->update(['status' => FarmVideo::STATUS_QUEUED, 'youtube_id' => null, 'uploaded_at' => null, 'published_at' => null, 'error' => null,
            'views' => null, 'likes' => null, 'comments' => null, 'stats_at' => null]);
        UploadFarmVideo::dispatch($video->id);
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

    /** The farm admin hears that a video waits in /admin/youtube (farm setting admin_email; empty = nobody). */
    private function tellAdmin(FarmVideo $video, FarmOrder $order): void
    {
        $to = (string) app(FarmSettings::class)->get('admin_email');
        if ($to === '') {
            return;
        }
        try {
            Mail::to($to)->queue(new FarmAdminAlert('Video ke schválení: '.$order->number, [
                'Časosběr zakázky '.$order->number.' je na YouTube jako soukromé video a čeká na schválení.',
                'Název: '.$video->title,
            ], route('admin.youtube.index')));
        } catch (\Throwable $e) {
            Log::warning('YouTube approval mail failed', ['video' => $video->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Views, likes and comments from YouTube for every video that is there. A video made public by hand in
     * YouTube Studio (while the API project is not audited) becomes "published" here too.
     *
     * @return int videos updated
     */
    public function refreshStats(): int
    {
        $videos = FarmVideo::whereNotNull('youtube_id')->whereIn('status', [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_PUBLISHED])->get();
        if ($videos->isEmpty() || ! YouTubeAccount::current()) {
            return 0;
        }
        $stats = $this->youtube->statistics($videos->pluck('youtube_id')->all());
        $n = 0;
        foreach ($videos as $video) {
            $s = $stats[$video->youtube_id] ?? null;
            if (! $s) {
                continue;       // deleted in Studio: an admin sees it has no numbers
            }
            $fill = ['views' => $s['views'], 'likes' => $s['likes'], 'comments' => $s['comments'], 'stats_at' => now()];
            if ($video->score === null && $video->order) {
                $fill['score'] = $this->score($video->order);
            }
            if ($s['privacy'] === 'public' && $video->status === FarmVideo::STATUS_UPLOADED && $video->order?->video_consent) {
                $fill += ['status' => FarmVideo::STATUS_PUBLISHED, 'published_at' => now(), 'error' => null];
            }
            $video->update($fill);
            $n++;
        }

        return $n;
    }

    /**
     * How interesting the time-lapse is likely to be, 0-100. The channel's numbers say: complex prints get the views
     * (F26-000018, a gear with ~370 layers and dense detail, 1 100 views against a handful for simple parts).
     *   time    up to 35: long prints grow a lot (log scale, 10 h and more = full)
     *   layers  up to 35: every layer is a frame (350 and more = full)
     *   detail  up to 30: moves per layer, a busy layer looks alive (1 200 and more = full)
     */
    public function score(FarmOrder $order): ?int
    {
        $gcode = $order->absoluteGcodePath();
        if (! $gcode || ! is_file($gcode)) {
            return null;
        }
        $layers = 0;
        $moves = 0;
        $fh = fopen($gcode, 'rb');
        while (($line = fgets($fh)) !== false) {
            if ($line[0] === ';') {
                $layers += str_starts_with($line, ';LAYER_CHANGE') ? 1 : 0;
            } elseif (str_starts_with($line, 'G1 ') || str_starts_with($line, 'G2 ') || str_starts_with($line, 'G3 ')) {
                $moves++;
            }
        }
        fclose($fh);
        $minutes = (int) ($order->actual_minutes ?: $order->est_minutes);
        $time = 35 * min(1.0, log(1 + $minutes / 30, 2) / log(1 + 600 / 30, 2));
        $layerPart = 35 * min(1.0, $layers / 350);
        $detail = 30 * min(1.0, ($layers ? $moves / $layers : 0) / 1200);

        return (int) round($time + $layerPart + $detail);
    }

    /** @return list<string> the background tracks on this server (file names), sorted */
    public function tracks(): array
    {
        $files = glob(rtrim((string) config('youtube.music_dir'), '/\\').'/*.mp3') ?: [];
        $names = array_map('basename', $files);
        sort($names);

        return $names;
    }

    /** One track per video, in turn by order id: the channel does not sound the same every time. */
    public function pickMusic(FarmOrder $order): ?string
    {
        $tracks = $this->tracks();

        return $tracks ? $tracks[$order->id % count($tracks)] : null;
    }

    /**
     * The YouTube copy with the track under it: the picture copied as it is, the track cut to the video, faded in and
     * out. Returns a temporary file (deleted after the upload), null when there is no music or ffmpeg fails.
     */
    public function withMusic(FarmOrder $order, ?string $track): ?string
    {
        $mp3 = $track ? rtrim((string) config('youtube.music_dir'), '/\\').'/'.$track : null;
        $seconds = $this->videoSeconds($order);
        if (! $mp3 || ! is_file($mp3) || ! $seconds) {
            return null;
        }
        $disk = Storage::disk(config('farm.disk'));
        $out = $disk->path($order->dir().'/youtube-music.mp4');
        $fadeOut = max(0, $seconds - 1.5);
        $r = Process::timeout(120)->run([
            (string) config('farm.ffmpeg', 'ffmpeg'), '-y', '-loglevel', 'error',
            '-i', $disk->path($this->file($order)), '-i', $mp3,
            '-map', '0:v:0', '-map', '1:a:0', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '160k',
            '-af', sprintf('volume=%s,afade=t=in:st=0:d=0.5,afade=t=out:st=%s:d=1.5', (float) config('youtube.music_volume', 0.8), $fadeOut),
            '-t', (string) $seconds, '-movflags', '+faststart', $out,
        ]);
        if (! $r->successful() || ! is_file($out)) {
            Log::warning('YouTube music mix failed', ['order' => $order->id, 'error' => mb_substr($r->errorOutput(), 0, 300)]);

            return null;
        }

        return $out;
    }

    /** The square Short when it was built (it grows the channel), else the landscape time-lapse. */
    public function file(FarmOrder $order): string
    {
        $short = $order->timelapse_short_path;

        return $short && Storage::disk(config('farm.disk'))->exists($short) ? $short : (string) $order->timelapse_path;
    }

    /** "Louskáček – 6 h 10 min tisku za 25 s": what was printed, how long it took, how short the video is. */
    public function defaultTitle(FarmOrder $order): string
    {
        $locale = config('youtube.language');
        $facts = $this->facts($order);
        $what = $this->modelName($order, $locale) ?? trim($facts['material'].' '.$facts['color']);
        $seconds = $this->videoSeconds($order);
        $tail = $seconds
            ? __('youtube.video.tail', ['time' => $facts['time'], 'seconds' => $seconds], $locale)
            : __('youtube.video.tail_plain', ['time' => $facts['time']], $locale);

        return mb_substr(($what !== '' ? $what.' – ' : '').$tail, 0, 100);
    }

    /** What the customer printed, in words: the tool's name, or the uploaded file's name tidied up. Null when nothing readable is left. */
    public function modelName(FarmOrder $order, ?string $locale = null): ?string
    {
        $file = $order->modelFile;
        if (! $file) {
            return null;
        }
        $kind = $file->kind();
        $tool = $kind === 'generated' ? 'figure' : ($file->origin === 'tool' ? $kind : null);
        if ($tool && ($title = __('tools.'.$tool.'.title', [], $locale)) !== 'tools.'.$tool.'.title') {
            return $title;
        }
        $name = pathinfo((string) $file->original_name, PATHINFO_FILENAME);
        $name = preg_replace('/\(\d+\)|\bcopy\b/i', ' ', $name);             // "louskacek (1)", "part copy"
        $name = preg_replace('/\d+(?:[.,]\d+)?\s*(?:mm|cm)\b/i', ' ', (string) $name);   // "felpa100mm"
        $words = [];
        foreach (preg_split('/[\s_\-.]+/u', (string) $name, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            // sizes, versions, numbers and body parts of an export say nothing to a viewer
            if (preg_match('/^(\d+([.,]\d+)?(mm|cm|m)?|\d+x\d+(x\d+)?|v\d+|body\d*|part\d*|[0-9a-f]{12,})$/i', $w)) {
                continue;
            }
            if (! in_array(mb_strtolower($w), array_map('mb_strtolower', $words), true)) {
                $words[] = $w;
            }
        }
        $clean = trim(implode(' ', $words));

        return mb_strlen($clean) >= 3 ? mb_strtoupper(mb_substr($clean, 0, 1)).mb_substr($clean, 1, 60) : null;
    }

    /** Length of the video that goes to YouTube, in whole seconds (ffmpeg reads it), null when unknown. */
    private function videoSeconds(FarmOrder $order): ?int
    {
        $path = $this->file($order);
        if ($path === '' || ! Storage::disk(config('farm.disk'))->exists($path)) {
            return null;
        }
        $r = Process::timeout(20)->run([(string) config('farm.ffmpeg', 'ffmpeg'), '-hide_banner', '-i', Storage::disk(config('farm.disk'))->path($path)]);
        if (! preg_match('/Duration: (\d+):(\d+):(\d+(?:\.\d+)?)/', $r->errorOutput().$r->output(), $m)) {
            return null;
        }

        return (int) round($m[1] * 3600 + $m[2] * 60 + (float) $m[3]);
    }

    public function defaultDescription(FarmOrder $order, ?string $music = null): string
    {
        $text = __('youtube.video.description', $this->facts($order), config('youtube.language'));
        if ($music) {
            $text .= "\n\n".__('youtube.video.music', ['track' => pathinfo($music, PATHINFO_FILENAME)], config('youtube.language'));
        }

        return $order->timelapse_short_path ? $text."\n\n#Shorts #3Dprinting #timelapse" : $text;
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
