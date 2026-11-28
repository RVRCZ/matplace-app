<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Social\VideoSharer;
use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\StudioCutter;
use App\Domain\YouTube\StudioMedia;
use App\Domain\YouTube\TimelapseCutter;
use App\Domain\YouTube\YouTubeError;
use App\Engines\Social\MetaClient;
use App\Http\Controllers\Controller;
use App\Jobs\BuildStudioVideos;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\FarmVideoMedia;
use App\Models\SocialPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * /admin/youtube: the videos filmed from prints (a list, whether each is published) and the video studio of one print
 * (docs/AL.md §13.10): its media in the admin's order, the Short and the 16:9 built from them side by side, their
 * texts, and which of them goes to YouTube when. The channel, the statistics and the cuts of many prints live on
 * /admin/youtube/channel (YouTubeController).
 */
class VideoStudioController extends Controller
{
    private const KINDS = [FarmVideo::KIND_SHORT, FarmVideo::KIND_WIDE];

    public function __construct(private readonly FarmVideos $videos, private readonly StudioMedia $media, private readonly StudioCutter $studio) {}

    public function index(Request $request): View
    {
        $filter = in_array($request->query('show'), ['waiting', 'published'], true) ? $request->query('show') : 'all';
        $query = FarmOrder::whereHas('videos', fn ($q) => $q->whereIn('kind', self::KINDS))
            ->with(['videos' => fn ($q) => $q->whereIn('kind', self::KINDS), 'color.material']);
        if ($filter === 'published') {
            $query->whereHas('videos', fn ($q) => $q->whereIn('kind', self::KINDS)->whereIn('status', [FarmVideo::STATUS_PUBLISHED, FarmVideo::STATUS_SCHEDULED]));
        } elseif ($filter === 'waiting') {
            $query->whereDoesntHave('videos', fn ($q) => $q->whereIn('kind', self::KINDS)->whereIn('status', [FarmVideo::STATUS_PUBLISHED, FarmVideo::STATUS_SCHEDULED, FarmVideo::STATUS_REJECTED, FarmVideo::STATUS_WITHDRAWN]));
        }

        return view('admin.youtube.list', [
            'orders' => $query->orderByDesc('id')->paginate(40)->withQueryString(),
            'filter' => $filter,
            // the cuts of many prints and the how-to videos still wait on the channel page
            'otherWaiting' => FarmVideo::whereNotIn('kind', self::KINDS)->whereIn('status', [FarmVideo::STATUS_PENDING, FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_FAILED])->count(),
        ]);
    }

    public function show(FarmOrder $order): View
    {
        $videos = $order->videos()->whereIn('kind', self::KINDS)->get()->keyBy('kind');
        $items = $this->media->items($order);

        return view('admin.youtube.studio', [
            'order' => $order->load(['color.material', 'user', 'modelFile']),
            'videos' => $videos,
            'items' => $items,
            // the app's own pictures shown when they are drawn already (drawing one takes up to a minute: the build does it)
            'pictures' => $items->mapWithKeys(fn (FarmVideoMedia $m) => [$m->id => $m->kind === FarmVideoMedia::KIND_CLIP || $m->kind === FarmVideoMedia::KIND_GROWTH
                ? null : ($this->media->file($order, $m, FarmVideo::KIND_SHORT, false) !== null)])->all(),
            'growth' => ['master' => $this->studio->growthMaster($order, false) !== null, 'frames' => app(TimelapseCutter::class)->frames($order)->count()],
            'state' => BuildStudioVideos::state($order->id),
            'intros' => collect(self::KINDS)->mapWithKeys(fn ($k) => [$k => $this->studio->intro($order, $k, $videos->get($k))])->all(),
            'nextSlot' => $this->videos->nextSlot()->timezone((string) config('youtube.publish_timezone', 'Europe/Prague')),
            'metaAvailable' => app(MetaClient::class)->available(),
            'shares' => SocialPost::where('subject_type', VideoSharer::SUBJECT)->whereIn('subject_id', $videos->pluck('id'))->get()->groupBy('subject_id'),
        ]);
    }

    /** What the page polls while a build runs: the state and the files' times (the players reload when they change). */
    public function state(FarmOrder $order): JsonResponse
    {
        $disk = Storage::disk(config('farm.disk'));
        $mtime = fn (?string $path) => $path && $disk->exists($path) ? $disk->lastModified($path) : null;

        return response()->json(BuildStudioVideos::state($order->id) + ['files' => ['short' => $mtime($order->timelapse_short_path), 'wide' => $mtime($order->timelapse_wide_path)]]);
    }

    /** Build both videos again now (the media and texts as they are). */
    public function rebuild(FarmOrder $order): RedirectResponse
    {
        BuildStudioVideos::later($order, 0);

        return back()->with('status', 'Videa se staví znovu, za chvíli se tu objeví.');
    }

    /** A clip or a picture added: uploaded (several at once) or recorded in the browser (field `clip`). */
    public function store(Request $request, FarmOrder $order): JsonResponse|RedirectResponse
    {
        $request->validate(['media' => ['array'], 'media.*' => ['file', 'max:153600'], 'clip' => ['nullable', 'file', 'max:153600']]);
        $files = array_values(array_filter([...(array) $request->file('media', []), $request->file('clip')]));
        if (! $files) {
            return back()->with('error', 'Vyberte video nebo obrázek.');
        }
        foreach ($files as $file) {
            $this->media->add($order, $file);
        }
        $message = count($files) === 1 ? 'Přidáno, videa se přestaví.' : 'Přidáno ('.count($files).'), videa se přestaví.';

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message]) : back()->with('status', $message);
    }

    /** The order of the list after a drag (ids in the new order). */
    public function reorder(Request $request, FarmOrder $order): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->media->reorder($order, $data['ids']);

        return response()->json(['ok' => true]);
    }

    /** In or out of the videos, seconds, a clip's own sound. */
    public function update(Request $request, FarmVideoMedia $media): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['included' => ['sometimes', 'boolean'], 'seconds' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:30'], 'sound' => ['sometimes', 'boolean']]);
        $this->media->update($media, $data);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Uloženo, videa se přestaví.');
    }

    public function destroy(Request $request, FarmVideoMedia $media): JsonResponse|RedirectResponse
    {
        $ok = $this->media->delete($media);
        $message = $ok ? 'Smazáno, videa se přestaví.' : 'Tuhle položku vytváří aplikace, smazat nejde. Můžete ji vypnout.';

        return $request->expectsJson() ? response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422) : back()->with($ok ? 'status' : 'error', $message);
    }

    /** The picture or clip of an item (?kind=wide: the 16:9's drawing); the growing print: growth.mp4. */
    public function file(Request $request, FarmVideoMedia $media): BinaryFileResponse
    {
        $order = $media->order;
        $kind = $request->query('kind') === FarmVideo::KIND_WIDE ? FarmVideo::KIND_WIDE : FarmVideo::KIND_SHORT;
        $path = $media->kind === FarmVideoMedia::KIND_GROWTH ? $this->studio->growthMaster($order, false) : $this->media->file($order, $media, $kind, false);
        abort_unless($path !== null && is_file($path), 404);
        $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'webp' => 'image/webp', 'jpg', 'jpeg' => 'image/jpeg', 'mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };

        return response()->file($path, ['Content-Type' => $type, 'Cache-Control' => 'no-cache']);
    }

    /**
     * The texts of one video (title, description, opening text); with `publish` also its way to YouTube: at the next
     * free slot or now, with the Facebook / Instagram choice for the Short.
     */
    public function texts(Request $request, FarmVideo $video): RedirectResponse
    {
        abort_unless(in_array($video->kind, self::KINDS, true) && $video->order, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:5000'], 'intro' => ['nullable', 'string', 'max:120'],
            'publish' => ['nullable', 'in:slot,now'], 'when' => ['nullable', 'in:slot,now'], 'share' => ['nullable', 'array'], 'share.*' => [Rule::in(VideoSharer::PLATFORMS)],
        ]);
        try {
            $rebuilding = $this->videos->saveTexts($video, $data['title'], (string) ($data['description'] ?? ''), $data['intro'] ?? null);
        } catch (YouTubeError $e) {
            return back()->withInput()->with('error', 'YouTube texty nepřijal: '.$e->getMessage());
        }
        if (! ($data['publish'] ?? null)) {
            return back()->with('status', $video->youtube_id ? 'Texty jsou uložené i na YouTube.' : ($rebuilding ? 'Uloženo, video se přestaví s novým úvodním textem.' : 'Texty jsou uložené.'));
        }
        if ($rebuilding) {
            return back()->with('error', 'Texty jsou uložené, ale video se kvůli úvodnímu textu staví znovu. Zveřejněte ho, až bude hotové.');
        }
        try {
            $video = $this->videos->publish($video->refresh(), $data['title'], (string) ($data['description'] ?? ''), $request->user()->id, now: ($data['when'] ?? $data['publish']) === 'now',
                share: $request->has('share_choice') ? (array) ($data['share'] ?? []) : null);
        } catch (YouTubeError $e) {
            return back()->with('error', $e->reason === 'locked_private'
                ? 'YouTube nechal video soukromé. Dokud neprojde audit API projektu, jde video zveřejnit jen ručně v YouTube Studiu.'
                : 'Zveřejnění se nepovedlo: '.$e->getMessage());
        }
        $slot = $video->scheduled_at?->timezone((string) config('youtube.publish_timezone', 'Europe/Prague'))->format('j. n. H:i');

        return back()->with('status', match ($video->status) {
            FarmVideo::STATUS_PUBLISHED => 'Video je zveřejněné.',
            FarmVideo::STATUS_SCHEDULED => 'Video je na YouTube a zveřejní se '.$slot.'.',
            default => 'Video se nahrává na YouTube'.($slot ? ' a zveřejní se '.$slot : ' a hned po nahrání bude veřejné').'.',
        });
    }
}
