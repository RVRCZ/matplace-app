<?php

namespace App\Domain\YouTube;

use App\Domain\Farm\FarmSettings;
use App\Domain\Social\VideoSharer;
use App\Jobs\UploadFarmVideo;
use App\Mail\FarmAdminAlert;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\YouTubeAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * The life of a print video: the only place a FarmVideo changes state.
 *
 * Nothing goes to YouTube without the customer's consent, and nothing goes up at all without an admin: a video waits
 * here (`pending`), the admin settles the title and description and approves it, then it is uploaded and made public
 * in one go. YouTube counts every upload against the channel's daily limit, so rejected videos never get there.
 * The customer may take the consent back at any time: the video is then deleted from YouTube.
 */
class FarmVideos
{
    /** Models made from photos (busts and figures, lithophanes, reliefs): may show a person, so the consent box starts empty. */
    public const PRIVATE_KINDS = ['generated', 'lithophane', 'relief'];

    public function __construct(private readonly YouTubeClient $youtube) {}

    /** A finished customer print whose owner agreed to share it, with the file of that kind (Short or 16:9) built. */
    public function eligible(FarmOrder $order, string $kind = FarmVideo::KIND_SHORT): bool
    {
        $path = $kind === FarmVideo::KIND_WIDE ? $order->timelapse_wide_path : $order->timelapse_path;

        return in_array($order->kind, [FarmOrder::KIND_PRINT, FarmOrder::KIND_SHOWCASE], true)
            && $order->video_consent
            && $path
            && Storage::disk(config('farm.disk'))->exists($path);
    }

    /**
     * Called when the videos are ready and when the customer agrees later: the Short and the 16:9 each wait for the
     * admin as their own video. Rejected videos stay rejected. Returns the Short's row (the 16:9's when there is no Short).
     */
    public function queueFor(FarmOrder $order): ?FarmVideo
    {
        if (! YouTubeAccount::current()) {
            return null;
        }
        $rows = [];
        $fresh = false;
        foreach ([FarmVideo::KIND_SHORT, FarmVideo::KIND_WIDE] as $kind) {
            if (! $this->eligible($order, $kind)) {
                continue;
            }
            $video = $order->videos()->where('kind', $kind)->first();
            if ($video && ! in_array($video->status, [FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_FAILED], true)) {
                $rows[$kind] = $video;

                continue;
            }
            $video ??= new FarmVideo(['farm_order_id' => $order->id, 'kind' => $kind]);
            $video->fill([
                'status' => FarmVideo::STATUS_PENDING, 'youtube_id' => null, 'error' => null, 'approved_at' => null,
                'title' => $video->title ?: $this->defaultTitle($order, $kind),
                'score' => $video->score ?? $this->score($order),
                // one track for all versions of the order: the one the videos were built with
                'music' => $music = $video->music ?? $order->videos()->whereNotNull('music')->value('music') ?? $this->pickMusic($order),
                'description' => $video->description ?: $this->defaultDescription($order, $music, $kind),
            ])->save();
            $rows[$kind] = $video;
            $fresh = true;
        }
        $video = $rows[FarmVideo::KIND_SHORT] ?? $rows[FarmVideo::KIND_WIDE] ?? null;
        if ($video && $fresh) {
            $this->tellAdmin($video, $order);
        }

        return $video;
    }

    /**
     * The week's cut (farm:weekly-video) waits for the admin like any other video. Built again for the same week, it
     * takes the new file while it is not on YouTube yet; one already there stays (the admin replaces it).
     *
     * @param  list<int>  $orders  the orders in it (a withdrawn consent marks the cut)
     */
    public function queueWeekly(string $week, string $path, array $orders, string $title, string $description, ?string $music): FarmVideo
    {
        $video = FarmVideo::where('kind', FarmVideo::KIND_WEEKLY)->get()->first(fn (FarmVideo $v) => ($v->meta['week'] ?? null) === $week)
            ?? new FarmVideo(['kind' => FarmVideo::KIND_WEEKLY, 'status' => FarmVideo::STATUS_PENDING]);
        if ($video->youtube_id) {
            return $video;
        }
        $video->fill(['path' => $path, 'title' => $title, 'description' => $description, 'music' => $music, 'meta' => ['week' => $week, 'orders' => $orders],
            'status' => FarmVideo::STATUS_PENDING, 'error' => null, 'approved_at' => null])->save();
        $this->tellAdmin($video, null);

        return $video;
    }

    /** Every customer in it still agrees: the order's consent, for the weekly cut every order in it. */
    public function consented(FarmVideo $video): bool
    {
        if ($video->kind === FarmVideo::KIND_WEEKLY) {
            $ids = (array) ($video->meta['orders'] ?? []);

            return FarmOrder::whereIn('id', $ids)->where('video_consent', true)->count() === count($ids);
        }

        return (bool) $video->order?->video_consent;
    }

    /** May go up: the file is there and everybody in it agrees. */
    private function allowed(FarmVideo $video): bool
    {
        if ($video->kind === FarmVideo::KIND_WEEKLY) {
            return $video->path && Storage::disk(config('farm.disk'))->exists($video->path) && $this->consented($video);
        }

        return $video->order && $this->eligible($video->order, $video->kind ?? FarmVideo::KIND_SHORT);
    }

    /** The queue job: send an approved time-lapse to YouTube and make it public. */
    public function upload(FarmVideo $video): void
    {
        $order = $video->order;
        // a second run of the same job (queue retry) or a video the customer took back meanwhile
        if ($video->status !== FarmVideo::STATUS_QUEUED) {
            return;
        }
        if (! $this->allowed($video)) {
            $video->update(['status' => FarmVideo::STATUS_WITHDRAWN]);

            return;
        }
        $video->update(['status' => FarmVideo::STATUS_UPLOADING, 'error' => null]);
        // the slot the admin's approval got; a wait for the quota may have carried the video past it
        $at = $video->approved_at && $video->scheduled_at ? ($video->scheduled_at->gt(now()->addMinutes(5)) ? $video->scheduled_at : $this->nextSlot()) : null;

        try {
            // the music is in the file already (TimelapseCutter)
            $id = $this->youtube->upload(Storage::disk(config('farm.disk'))->path($this->pathOf($video)), $video->title, (string) $video->description, $at);
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
        $video->update(['status' => FarmVideo::STATUS_UPLOADED, 'youtube_id' => $id, 'uploaded_at' => now()]);

        // the customer changed their mind while the upload ran
        if ($order && ! $order->refresh()->video_consent) {
            $this->withdraw($order);

            return;
        }
        if (! $video->approved_at) {
            $this->tellAdmin($video, $order);   // an older copy that went up unapproved: the admin decides on YouTube's private video

            return;
        }
        if ($at) {
            $video->update(['status' => FarmVideo::STATUS_SCHEDULED, 'scheduled_at' => $at]);   // YouTube makes it public at that time

            return;
        }
        try {
            $this->publish($video, (string) $video->title, (string) $video->description, (int) $video->decided_by, now: true);
        } catch (YouTubeError $e) {
            // `locked_private` is already written on the video; anything else leaves it private for the admin to publish by hand
            if ($e->reason !== 'locked_private') {
                $video->update(['error' => 'Publish: '.$e->getMessage()]);
            }
        }
    }

    /**
     * The admin's approval with the title and description they settled on. A video that is not on YouTube yet goes up
     * now; one already there (private) is dealt with at once. Public at the next free slot (nextSlot), or right away
     * with $now.
     */
    public function publish(FarmVideo $video, string $title, string $description, int $adminId, bool $now = false, ?array $share = null): FarmVideo
    {
        if ($share !== null) {
            $video->share = array_values(array_intersect(VideoSharer::PLATFORMS, $share));   // where else it goes (VideoSharer), once public
        }
        if (! $video->youtube_id && in_array($video->status, [FarmVideo::STATUS_PENDING, FarmVideo::STATUS_QUEUED, FarmVideo::STATUS_FAILED], true)) {
            $video->update(['title' => $title, 'description' => $description, 'status' => FarmVideo::STATUS_QUEUED, 'error' => null,
                'approved_at' => now(), 'scheduled_at' => $now ? null : $this->nextSlot(), 'decided_by' => $adminId, 'decided_at' => now()]);
            UploadFarmVideo::dispatch($video->id);

            return $video;
        }
        if (! in_array($video->status, [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_PUBLISHED], true) || ! $video->youtube_id) {
            throw new YouTubeError('not_uploaded', 'The video is not on YouTube yet.');
        }
        if (! $this->consented($video)) {
            throw new YouTubeError('no_consent', 'The customer has taken the consent back.');
        }
        if (! $now && $video->status === FarmVideo::STATUS_UPLOADED) {
            // private on YouTube: give it the slot and let YouTube flip it
            $at = $this->nextSlot();
            $r = $this->youtube->publish($video->youtube_id, $title, $description, $at);
            if ($r['publishAt'] === '') {
                throw new YouTubeError('not_scheduled', 'YouTube did not take the publishing time.');
            }
            $video->update(['title' => $title, 'description' => $description, 'status' => FarmVideo::STATUS_SCHEDULED, 'scheduled_at' => $at,
                'approved_at' => $video->approved_at ?? now(), 'decided_by' => $adminId, 'decided_at' => now(), 'error' => null]);

            return $video;
        }
        $privacy = $this->youtube->publish($video->youtube_id, $title, $description)['privacy'];
        $public = $privacy === 'public';
        $video->update([
            'title' => $title, 'description' => $description,
            'status' => $public ? FarmVideo::STATUS_PUBLISHED : FarmVideo::STATUS_UPLOADED,
            'published_at' => $public ? now() : null, 'scheduled_at' => null, 'decided_by' => $adminId, 'decided_at' => now(),
            // an API project YouTube has not audited yet keeps every uploaded video locked private
            'error' => $public ? null : 'YouTube left the video '.$privacy.' (API project not audited yet?).',
        ]);
        if (! $public) {
            throw new YouTubeError('locked_private', (string) $video->error);
        }

        return $video;
    }

    /** Not for the channel: delete it from YouTube, remember the decision (a later consent does not upload it again). */
    public function reject(FarmVideo $video, int $adminId): void
    {
        if ($video->youtube_id) {
            $this->youtube->delete($video->youtube_id);
        }
        $video->update(['status' => FarmVideo::STATUS_REJECTED, 'youtube_id' => null, 'published_at' => null, 'decided_by' => $adminId, 'decided_at' => now()]);
    }

    /** The videos were built again (farm:timelapse): the copy on YouTube goes, the new file waits for the admin again. */
    public function replace(FarmVideo $video): void
    {
        // a published one too, when the admin wants it (its views stay with the deleted copy)
        if (! in_array($video->status, [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_PUBLISHED], true)) {
            throw new YouTubeError('not_replaceable', 'Only a video on YouTube can be replaced.');
        }
        if ($video->youtube_id) {
            $this->youtube->delete($video->youtube_id);
        }
        $video->update(['status' => FarmVideo::STATUS_PENDING, 'youtube_id' => null, 'uploaded_at' => null, 'published_at' => null, 'approved_at' => null, 'scheduled_at' => null, 'error' => null,
            'views' => null, 'likes' => null, 'comments' => null, 'stats_at' => null]);
    }

    /** Failed, or stuck in `uploading` after a crash: try again (an approved video goes up, an unapproved one waits for the admin). */
    public function retry(FarmVideo $video): void
    {
        if (! in_array($video->status, [FarmVideo::STATUS_FAILED, FarmVideo::STATUS_UPLOADING, FarmVideo::STATUS_QUEUED], true)) {
            return;
        }
        if (! $video->approved_at) {
            $video->update(['status' => FarmVideo::STATUS_PENDING, 'error' => null]);

            return;
        }
        $video->update(['status' => FarmVideo::STATUS_QUEUED, 'error' => null]);
        UploadFarmVideo::dispatch($video->id);
    }

    /** The customer's switch on the order page (and the checkbox when paying). */
    public function setConsent(FarmOrder $order, bool $consent): void
    {
        $order->forceFill(['video_consent' => $consent, 'video_consent_at' => now()])->save();
        $consent ? $this->queueFor($order) : $this->withdraw($order);
    }

    /**
     * Consent taken back: the YouTube copies (Short and 16:9) go away at once. A weekly cut with the print in it is
     * marked for the admin: built again without it (farm:weekly-video), or taken down.
     */
    private function withdraw(FarmOrder $order): void
    {
        foreach ($order->videos()->get() as $video) {
            if (in_array($video->status, [FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_REJECTED], true)) {
                continue;
            }
            if ($video->youtube_id) {
                try {
                    $this->youtube->delete($video->youtube_id);
                } catch (YouTubeError $e) {
                    // keep the id so an admin sees the video still has to be removed by hand
                    $video->update(['status' => FarmVideo::STATUS_WITHDRAWN, 'error' => 'Delete on YouTube failed: '.$e->getMessage()]);
                    Log::error('YouTube delete after withdrawn consent failed', ['video' => $video->id, 'youtube_id' => $video->youtube_id, 'error' => $e->getMessage()]);

                    continue;
                }
            }
            $video->update(['status' => FarmVideo::STATUS_WITHDRAWN, 'youtube_id' => null, 'published_at' => null]);
        }
        FarmVideo::where('kind', FarmVideo::KIND_WEEKLY)->whereNotIn('status', [FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_REJECTED])->get()
            ->filter(fn (FarmVideo $v) => in_array($order->id, (array) ($v->meta['orders'] ?? []), true))
            ->each(fn (FarmVideo $v) => $v->update(['error' => 'Zákazník zakázky '.$order->number.' odvolal souhlas: sestavte týden znovu (php artisan farm:weekly-video --week='.($v->meta['week'] ?? '?').') nebo video stáhněte.']));
    }

    /** The farm admin hears that a video waits in /admin/youtube (farm setting admin_email; empty = nobody). */
    private function tellAdmin(FarmVideo $video, ?FarmOrder $order): void
    {
        $to = (string) app(FarmSettings::class)->get('admin_email');
        if ($to === '') {
            return;
        }
        $what = $order ? 'Časosběr zakázky '.$order->number : 'Týdenní sestřih '.($video->meta['week'] ?? '');
        try {
            Mail::to($to)->queue(new FarmAdminAlert('Video ke schválení: '.($order->number ?? $video->title), [
                $video->youtube_id
                    ? $what.' je na YouTube jako soukromé video a čeká na schválení.'
                    : $what.' čeká na schválení; na YouTube se nahraje až po něm.',
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
        $videos = FarmVideo::whereNotNull('youtube_id')->whereIn('status', [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_PUBLISHED])->get();
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
            // made public by hand in Studio, or by YouTube at the scheduled time
            if ($s['privacy'] === 'public' && in_array($video->status, [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED], true) && $this->consented($video)) {
                $fill += ['status' => FarmVideo::STATUS_PUBLISHED, 'published_at' => $video->scheduled_at ?? now(), 'error' => null];
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

    /**
     * The next free publishing slot: the times of day in config youtube.publish_times (channel time zone), at most
     * youtube.max_per_day videos a day, never within the next ten minutes. Videos already holding a slot (approved,
     * scheduled) and ones published on a day count against it, so approvals spread out over the days by themselves.
     */
    public function nextSlot(): Carbon
    {
        $tz = (string) config('youtube.publish_timezone', 'Europe/Prague');
        $times = (array) config('youtube.publish_times', ['18:00']) ?: ['18:00'];
        sort($times);
        $max = max(1, (int) config('youtube.max_per_day', 1));
        $earliest = now()->addMinutes(10);
        $holding = FarmVideo::whereIn('status', [FarmVideo::STATUS_QUEUED, FarmVideo::STATUS_UPLOADING, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_PUBLISHED])
            ->where(fn ($q) => $q->whereNotNull('scheduled_at')->orWhereNotNull('published_at'))
            ->get(['scheduled_at', 'published_at'])
            ->map(fn (FarmVideo $v) => ($v->scheduled_at ?? $v->published_at)->copy()->timezone($tz));
        for ($d = 0; $d < 120; $d++) {
            $day = now($tz)->startOfDay()->addDays($d);
            $onDay = $holding->filter(fn (Carbon $t) => $t->isSameDay($day));
            if ($onDay->count() >= $max) {
                continue;
            }
            foreach ($times as $time) {
                $slot = $day->copy()->setTimeFromTimeString($time);
                if ($slot->gte($earliest) && ! $onDay->contains(fn (Carbon $t) => $t->equalTo($slot))) {
                    return $slot->utc();
                }
            }
        }

        return $earliest;
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
        if (! $tracks) {
            return null;
        }
        // the tracks the last videos used, newest first: no track comes again before the others had their turn
        // (17 Oct 2026: two prints sixteen orders apart got the same track out of sixteen, and Roman heard it at once)
        $recent = FarmVideo::whereNotNull('music')->where('farm_order_id', '!=', $order->id)->orderByDesc('id')->limit(200)->pluck('music')->unique()->values()->all();
        $fresh = array_values(array_diff($tracks, array_slice($recent, 0, count($tracks) - 1)));
        $pool = $fresh ?: $tracks;

        return $pool[$order->id % count($pool)];
    }

    /** The square Short when it was built (it grows the channel), else the customer's time-lapse. */
    public function file(FarmOrder $order): string
    {
        $short = $order->timelapse_short_path;

        return $short && Storage::disk(config('farm.disk'))->exists($short) ? $short : (string) $order->timelapse_path;
    }

    /** The file that goes to YouTube for this video (relative to the farm disk): the Short, the 16:9 or the week's cut. */
    public function pathOf(FarmVideo $video): string
    {
        return match ($video->kind) {
            FarmVideo::KIND_WEEKLY => (string) $video->path,
            FarmVideo::KIND_WIDE => (string) $video->order?->timelapse_wide_path,
            default => $video->order ? $this->file($video->order) : '',
        };
    }

    /** The page of the tool that made the model (a photo figure: the figure tool), else the home page; absolute, without marks. */
    public function toolUrl(?FarmOrder $order): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $file = $order?->modelFile;
        $route = match (true) {
            $file === null => null,
            $file->origin === 'generated' => 'tools.figure',
            $file->origin === 'tool' => 'tools.'.(in_array($file->origin_ref, ['lithophane', 'relief'], true) ? 'relief' : $file->origin_ref),
            default => null,
        };

        return $route && Route::has($route) ? $base.route($route, [], false) : $base;
    }

    /** "matplace.com/tools/vase": the address as the end card shows it. */
    public function address(?FarmOrder $order): string
    {
        return rtrim((string) preg_replace('#^https?://(www\.)?#', '', $this->toolUrl($order)), '/');
    }

    /** The tool's page marked with the video's campaign (video_short, video_wide, video_weekly), so the visits can be counted. */
    public function link(?FarmOrder $order, string $kind): string
    {
        $url = $this->toolUrl($order);

        return ($url === rtrim((string) config('app.url'), '/') ? $url.'/' : $url).'?'.http_build_query(['utm_source' => 'youtube', 'utm_medium' => 'social', 'utm_campaign' => 'video_'.$kind]);
    }

    /**
     * The words on the title card of the 16:9 and under a print in the weekly cut: the model's name (or the material
     * and colour) and "PLA+ Červená · 6 h 10 min tisku".
     *
     * @return array{name: string, line: string}
     */
    public function cardFacts(FarmOrder $order, ?string $locale = null): array
    {
        $facts = $this->facts($order);
        $material = trim($facts['material'].' '.$facts['color']);
        $name = $this->modelName($order, $locale);

        // without a readable name the material is the name: the line under it says only the time
        return ['name' => $name ?? ($material ?: 'Matplace'),
            'line' => __($name !== null && $material !== '' ? 'youtube.video.card' : 'youtube.video.card_time', ['material' => $material, 'time' => $facts['time']], $locale)];
    }

    /** "Louskáček – 6 h 10 min tisku za 25 s": what was printed, how long it took, how short the video is. */
    public function defaultTitle(FarmOrder $order, string $kind = FarmVideo::KIND_SHORT): string
    {
        $locale = config('youtube.language');
        $facts = $this->facts($order);
        $what = $this->modelName($order, $locale) ?? trim($facts['material'].' '.$facts['color']);
        $seconds = $this->videoSeconds($kind === FarmVideo::KIND_WIDE ? (string) $order->timelapse_wide_path : $this->file($order));
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

    /** Length of a video on the farm disk in whole seconds (ffmpeg reads it), null when unknown. */
    private function videoSeconds(string $path): ?int
    {
        if ($path === '' || ! Storage::disk(config('farm.disk'))->exists($path)) {
            return null;
        }
        $r = Process::timeout(20)->run([(string) config('farm.ffmpeg', 'ffmpeg'), '-hide_banner', '-i', Storage::disk(config('farm.disk'))->path($path)]);
        if (! preg_match('/Duration: (\d+):(\d+):(\d+(?:\.\d+)?)/', $r->errorOutput().$r->output(), $m)) {
            return null;
        }

        return (int) round($m[1] * 3600 + $m[2] * 60 + (float) $m[3]);
    }

    /**
     * The credit line of a track from the file's name: the YouTube Audio Library names "Title - Author.mp3", Pixabay
     * "author-title-words-123456.mp3" (the author's slug, the title in dashes, the track's number). Pixabay's licence
     * asks for no credit, Roman wants the author named anyway (17 Oct 2026); anything else keeps its file name.
     */
    public static function musicCredit(string $file): string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (preg_match('/^(.+?) - (.+)$/', $name, $m)) {
            return $m[1].' – '.$m[2].' (YouTube Audio Library)';
        }
        if (preg_match('/^([a-z0-9_.]+)-(.+?)-(\d{3,})$/i', $name, $m)) {
            return ucwords(str_replace(['-', '_'], ' ', $m[2])).' – '.$m[1].' (Pixabay)';
        }

        return $name;
    }

    /** What was printed and how, the link with the kind's campaign, the music credit; the Short gets its hashtags. */
    public function defaultDescription(FarmOrder $order, ?string $music = null, string $kind = FarmVideo::KIND_SHORT): string
    {
        $text = __('youtube.video.description', $this->facts($order, $kind), config('youtube.language'));
        if ($music) {
            $text .= "\n\n".__('youtube.video.music', ['track' => self::musicCredit($music)], config('youtube.language'));
        }

        return $kind === FarmVideo::KIND_SHORT && $order->timelapse_short_path ? $text."\n\n#Shorts #3Dprinting #timelapse" : $text;
    }

    /**
     * The weekly cut's description: one chapter per print (YouTube makes chapters from a list starting at 0:00), each
     * with its tool's page, then the music credit.
     *
     * @param  list<array{0: float, 1: string, 2: ?string}>  $chapters
     */
    public function weeklyDescription(string $dates, array $chapters, ?string $music): string
    {
        $locale = config('youtube.language');
        $lines = array_map(fn ($c) => sprintf('%d:%02d %s', intdiv((int) $c[0], 60), (int) $c[0] % 60, $c[1]).($c[2] ? ' – '.$c[2] : ''), $chapters);
        $text = __('youtube.weekly.description', ['dates' => $dates, 'count' => count($chapters), 'url' => $this->link(null, FarmVideo::KIND_WEEKLY)], $locale)."\n\n".implode("\n", $lines);
        if ($music) {
            $text .= "\n\n".__('youtube.video.music', ['track' => self::musicCredit($music)], $locale);
        }

        return $text."\n\n#3Dprinting #timelapse";
    }

    private function facts(FarmOrder $order, string $kind = FarmVideo::KIND_SHORT): array
    {
        $order->loadMissing(['color.material', 'printer']);
        $minutes = (int) ($order->actual_minutes ?: $order->est_minutes);

        return [
            'material' => $order->color?->material?->label() ?? '',
            'color' => $order->color?->displayName() ?? '',
            'printer' => $order->printer?->model ?: ($order->printer?->name ?? ''),
            'time' => $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min',
            // marked, so the people a video brings can be counted (YouTube often sends no referrer), per kind of video
            'url' => $this->link(null, $kind),
        ];
    }
}
