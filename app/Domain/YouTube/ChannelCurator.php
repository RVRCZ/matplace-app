<?php

namespace App\Domain\YouTube;

use App\Models\FarmVideo;
use Illuminate\Support\Facades\Storage;

/**
 * Housekeeping of the channel after the audit of 27 Oct 2026 (docs/AL.md §13.5): the table of every video with the
 * title it has and the title it should have (youtube:titles → a CSV Roman approves), the renaming itself
 * (youtube:retitle, dry run unless --apply), and the playlists that mirror the tools of the site (youtube:playlists).
 * Nothing here calls YouTube unless asked to apply; the plans are read from the database.
 */
final class ChannelCurator
{
    /** The columns of the table, in this order (a CSV with semicolons, the way docs/data keeps its tables). */
    public const COLUMNS = ['id', 'druh', 'zakazka', 'nastroj', 'zverejneno', 'dnesni_titulek', 'zhlednuti', 'novy_titulek', 'novy_popis', 'playlist', 'duplikat', 'navrh'];

    /** Where the ids of the channel's playlists are kept once made (name => id). */
    public const PLAYLISTS_FILE = 'youtube/playlists.json';

    public function __construct(private readonly YouTubeClient $youtube, private readonly FarmVideos $videos) {}

    /**
     * One row per video of ours on YouTube, the most viewed first: what it is called today, the title and description
     * the templates would give it now (with the video's id in the links), the playlist it belongs to, and whether it
     * is the 16:9 twin of a Short (then `navrh` says unlisted). With $api the channel's uploads the database does not
     * know (made by hand) come too, with empty proposals for Roman to fill.
     *
     * @return list<array<string, string>>
     */
    public function rows(bool $api = false): array
    {
        $rows = [];
        $known = [];
        $videos = FarmVideo::with(['order.modelFile', 'order.color.material', 'order.printer'])->whereNotNull('youtube_id')
            ->whereIn('status', [FarmVideo::STATUS_PUBLISHED, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_UPLOADED])->get();
        $shorts = $videos->where('kind', FarmVideo::KIND_SHORT)->pluck('farm_order_id')->filter()->all();
        foreach ($videos as $video) {
            $known[] = $video->youtube_id;
            $order = $video->order;
            $twin = $video->kind === FarmVideo::KIND_WIDE && $order && in_array($order->id, $shorts, true);
            $rows[] = [
                'id' => (string) $video->youtube_id,
                'druh' => (string) $video->kind,
                'zakazka' => (string) ($order?->number ?? ''),
                'nastroj' => $order ? (string) ($this->videos->toolKind($order) ?? '') : '',
                'zverejneno' => (string) ($video->published_at?->toDateString() ?? ''),
                'dnesni_titulek' => (string) $video->title,
                'zhlednuti' => (string) ($video->views ?? ''),
                'novy_titulek' => $order && in_array($video->kind, [FarmVideo::KIND_SHORT, FarmVideo::KIND_WIDE], true) ? $this->videos->defaultTitle($order, $video->kind) : '',
                'novy_popis' => $order && in_array($video->kind, [FarmVideo::KIND_SHORT, FarmVideo::KIND_WIDE], true) ? $this->videos->defaultDescription($order, $video->music, $video->kind, $video->youtube_id) : '',
                'playlist' => (string) ($this->playlistFor($video) ?? ''),
                'duplikat' => $twin ? (empty($video->meta['unlisted_at']) ? 'ano' : 'ano (unlisted)') : 'ne',
                'navrh' => $twin && empty($video->meta['unlisted_at']) ? 'unlisted' : '',
            ];
        }
        if ($api) {
            foreach ($this->youtube->uploads() as $u) {
                if (in_array($u['id'], $known, true)) {
                    continue;
                }
                $rows[] = ['id' => $u['id'], 'druh' => 'ručně', 'zakazka' => '', 'nastroj' => '', 'zverejneno' => substr((string) $u['published_at'], 0, 10), 'dnesni_titulek' => $u['title'], 'zhlednuti' => '',
                    'novy_titulek' => '', 'novy_popis' => '', 'playlist' => '', 'duplikat' => 'ne', 'navrh' => $u['privacy'] !== 'public' ? $u['privacy'] : ''];
            }
        }
        usort($rows, fn ($a, $b) => (int) $b['zhlednuti'] <=> (int) $a['zhlednuti']);
        // the same title twice: YouTube and the viewer tell them apart no better than we do
        $counts = array_count_values(array_map(fn ($r) => mb_strtolower(trim($r['dnesni_titulek'])), $rows));
        foreach ($rows as &$row) {
            if (($counts[mb_strtolower(trim($row['dnesni_titulek']))] ?? 0) > 1 && $row['duplikat'] === 'ne') {
                $row['duplikat'] = 'stejný titul';
            }
        }

        return $rows;
    }

    /** @param  list<array<string, string>>  $rows */
    public function writeCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        fputcsv($fh, self::COLUMNS, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, array_map(fn ($c) => (string) ($row[$c] ?? ''), self::COLUMNS), ';', '"', '\\');
        }
        fclose($fh);
    }

    /** @return list<array<string, string>> the rows of a table written by writeCsv (or edited by Roman in a spreadsheet) */
    public function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $head = fgetcsv($fh, 0, ';', '"', '\\') ?: [];
        $head[0] = ltrim((string) ($head[0] ?? ''), "\u{FEFF}");   // a spreadsheet may add a byte-order mark
        $rows = [];
        while (($line = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            $rows[] = array_combine($head, array_pad(array_map(fn ($v) => (string) $v, $line), count($head), '')) ?: [];
        }
        fclose($fh);

        return $rows;
    }

    /**
     * What a retitle would do with the approved table: every row with a new title or description that differs from
     * what the video has now, and the duplicates to unlist.
     *
     * @param  list<array<string, string>>  $rows
     * @return array{retitle: list<array{id: string, from: string, to: string, description: bool}>, unlist: list<string>}
     */
    public function plan(array $rows): array
    {
        $retitle = [];
        $unlist = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $video = FarmVideo::where('youtube_id', $id)->first();
            if ($video && $video->status === FarmVideo::STATUS_MISSING) {
                continue;   // YouTube no longer knows it: nothing to rename
            }
            $title = trim((string) ($row['novy_titulek'] ?? ''));
            $description = trim((string) ($row['novy_popis'] ?? ''));
            $titleNow = (string) ($video?->title ?? $row['dnesni_titulek'] ?? '');
            $descriptionNow = (string) ($video?->description ?? '');
            // the table was written before the links carried the video's id: compared as they will be sent (idempotent)
            if (($title !== '' && $title !== $titleNow) || ($description !== '' && FarmVideos::withVideoId($description, $id) !== $descriptionNow)) {
                $retitle[] = ['id' => $id, 'from' => $titleNow, 'to' => $title !== '' ? $title : $titleNow, 'description' => $description !== '' && $description !== $descriptionNow];
            }
            if (trim((string) ($row['navrh'] ?? '')) === 'unlisted' && empty($video?->meta['unlisted_at'])) {
                $unlist[] = $id;   // once: a twin we hid already is not sent again
            }
        }

        return ['retitle' => $retitle, 'unlist' => $unlist];
    }

    /**
     * Renames (and, with $unlist, hides) on YouTube what the plan says, 50 quota units a video, and keeps the new
     * texts on our rows. A video YouTube no longer knows (deleted in Studio) is marked missing and left alone from
     * then on. Dry run (the default of the command) only returns the plan.
     *
     * @param  list<array<string, string>>  $rows
     * @return array{retitled: int, unlisted: int, failed: list<string>, missing: list<string>}
     */
    public function apply(array $rows, bool $unlist = false): array
    {
        $plan = $this->plan($rows);
        $byId = [];
        foreach ($rows as $row) {
            $byId[trim((string) ($row['id'] ?? ''))] = $row;
        }
        $done = ['retitled' => 0, 'unlisted' => 0, 'failed' => [], 'missing' => []];
        foreach ($plan['retitle'] as $change) {
            $video = FarmVideo::where('youtube_id', $change['id'])->first();
            $description = trim((string) ($byId[$change['id']]['novy_popis'] ?? '')) ?: (string) ($video?->description ?? '');
            try {
                $this->youtube->retitle($change['id'], $change['to'], FarmVideos::withVideoId($description, $change['id']));
                $video?->update(['title' => $change['to'], 'description' => $description !== '' ? FarmVideos::withVideoId($description, $change['id']) : $video->description]);
                $done['retitled']++;
            } catch (YouTubeError $e) {
                if ($e->isMissing()) {
                    $this->gone($change['id'], $e->getMessage());
                    $done['missing'][] = $change['id'];
                } else {
                    $done['failed'][] = $change['id'].': '.$e->getMessage();
                }
            }
        }
        if ($unlist) {
            foreach ($plan['unlist'] as $id) {
                try {
                    $this->youtube->setPrivacy($id, 'unlisted');
                    FarmVideo::where('youtube_id', $id)->update(['meta' => array_merge((array) (FarmVideo::where('youtube_id', $id)->value('meta') ?? []), ['unlisted_at' => now()->toIso8601String(), 'privacy' => 'unlisted'])]);
                    $done['unlisted']++;
                } catch (YouTubeError $e) {
                    if ($e->isMissing()) {
                        $this->gone($id, $e->getMessage());
                        $done['missing'][] = $id;
                    } else {
                        $done['failed'][] = $id.': '.$e->getMessage();
                    }
                }
            }
        }

        return $done;
    }

    /** YouTube no longer knows the video: our row says missing, and the table, the stats and the playlists leave it out. */
    private function gone(string $youtubeId, string $why): void
    {
        FarmVideo::where('youtube_id', $youtubeId)->whereIn('status', [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_PUBLISHED])
            ->update(['status' => FarmVideo::STATUS_MISSING, 'error' => mb_substr('YouTube: '.$why, 0, 500)]);
    }

    /** Public by what we know: not hidden by us (meta.unlisted_at) and not reported private or unlisted by YouTube (meta.privacy). */
    private function publicByOurBook(FarmVideo $video): bool
    {
        return empty($video->meta['unlisted_at']) && in_array((string) ($video->meta['privacy'] ?? 'public'), ['public', ''], true);
    }

    /** The playlist a video belongs to by the tool of its print (config youtube.playlists), the how-to and weekly cuts by their kind; null when none fits. */
    public function playlistFor(FarmVideo $video): ?string
    {
        $kind = (string) $video->kind;
        $tool = $video->order ? $this->videos->toolKind($video->order) : null;
        foreach ((array) config('youtube.playlists') as $name => $spec) {
            if (in_array($kind, (array) ($spec['kinds'] ?? []), true) || ($tool !== null && in_array($tool, (array) ($spec['tools'] ?? []), true))) {
                return (string) $name;
            }
        }

        return null;
    }

    /**
     * The playlists as they should be: for every name whether it exists on the channel (the ids we kept, else the
     * channel's own list when asked) and the public videos of ours that belong in it and are not in it yet. Public
     * means public: not the twins we unlisted (meta.unlisted_at), not what YouTube reports unlisted or private, and
     * when the channel is asked (--apply) a videos.list (1 unit per 50) checks every candidate's privacy today; one
     * YouTube no longer knows is marked missing. $skip leaves named video ids out by hand.
     *
     * @param  list<string>  $skip
     * @return array<string, array{id: ?string, description: string, add: list<string>, has: int}>
     */
    public function playlistPlan(bool $askChannel = false, array $skip = []): array
    {
        $ids = $this->playlistIds();
        if ($askChannel) {
            foreach ($this->youtube->playlists() as $id => $title) {
                $ids[$title] = $ids[$title] ?? $id;
            }
        }
        $plan = [];
        foreach ((array) config('youtube.playlists') as $name => $spec) {
            $plan[$name] = ['id' => $ids[$name] ?? null, 'description' => (string) ($spec['description'] ?? ''), 'add' => [], 'has' => 0];
        }
        $videos = FarmVideo::with('order.modelFile')->whereNotNull('youtube_id')->where('status', FarmVideo::STATUS_PUBLISHED)->orderBy('published_at')->orderBy('id')->get()
            ->filter(fn (FarmVideo $v) => $this->publicByOurBook($v) && ! in_array((string) $v->youtube_id, $skip, true))->values();
        if ($askChannel && $videos->isNotEmpty()) {
            $facts = $this->youtube->statistics($videos->pluck('youtube_id')->all());
            foreach ($videos as $video) {
                $f = $facts[$video->youtube_id] ?? null;
                if ($f === null && $facts !== []) {
                    // a public video videos.list leaves out is gone (deleted in Studio); an empty answer for all is not trusted
                    $this->gone((string) $video->youtube_id, 'videos.list does not know this video (deleted in Studio?)');
                } elseif ($f !== null && $f['privacy'] !== '' && $f['privacy'] !== (string) ($video->meta['privacy'] ?? '')) {
                    $video->update(['meta' => ['privacy' => $f['privacy']] + (array) $video->meta]);
                }
            }
            $videos = $videos->filter(fn (FarmVideo $v) => isset($facts[$v->youtube_id]) && in_array($facts[$v->youtube_id]['privacy'], ['public', ''], true))->values();
        }
        foreach ($videos as $video) {
            $name = $this->playlistFor($video);
            if ($name === null || ! isset($plan[$name])) {
                continue;
            }
            if (($video->meta['playlist'] ?? null) === $name) {
                $plan[$name]['has']++;
            } else {
                $plan[$name]['add'][] = (string) $video->youtube_id;
            }
        }

        return $plan;
    }

    /**
     * Makes the playlists that are missing and puts the videos in (playlists.insert and playlistItems.insert, 50 quota
     * units each). Returns what was done per playlist.
     *
     * @param  list<string>  $skip  video ids to leave out by hand
     * @return array<string, array{id: string, created: bool, added: int, failed: list<string>}>
     */
    public function syncPlaylists(array $skip = []): array
    {
        $plan = $this->playlistPlan(askChannel: true, skip: $skip);
        $ids = $this->playlistIds();
        $out = [];
        foreach ($plan as $name => $p) {
            $result = ['id' => (string) $p['id'], 'created' => false, 'added' => 0, 'failed' => []];
            try {
                if (! $p['id']) {
                    $result['id'] = $this->youtube->createPlaylist($name, $p['description']);
                    $result['created'] = true;
                }
                $ids[$name] = $result['id'];
                foreach ($p['add'] as $videoId) {
                    try {
                        $this->youtube->addToPlaylist($result['id'], $videoId);
                        $video = FarmVideo::where('youtube_id', $videoId)->first();
                        $video?->update(['meta' => ['playlist' => $name] + (array) $video->meta]);
                        $result['added']++;
                    } catch (YouTubeError $e) {
                        $result['failed'][] = $videoId.': '.$e->getMessage();
                    }
                }
            } catch (YouTubeError $e) {
                $result['failed'][] = $name.': '.$e->getMessage();
            }
            $out[$name] = $result;
        }
        Storage::disk('local')->put(self::PLAYLISTS_FILE, json_encode($ids, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $out;
    }

    /** @return array<string, string> name => YouTube playlist id, as kept after a sync */
    public function playlistIds(): array
    {
        $json = Storage::disk('local')->exists(self::PLAYLISTS_FILE) ? json_decode((string) Storage::disk('local')->get(self::PLAYLISTS_FILE), true) : null;

        return is_array($json) ? array_map('strval', $json) : [];
    }
}
