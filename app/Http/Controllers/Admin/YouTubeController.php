<?php

namespace App\Http\Controllers\Admin;

use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\YouTubeClient;
use App\Domain\YouTube\YouTubeError;
use App\Http\Controllers\Controller;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\YouTubeAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** /admin/youtube: connect the channel, approve (publish) or reject the print videos customers agreed to share. */
class YouTubeController extends Controller
{
    public function __construct(private readonly YouTubeClient $youtube, private readonly FarmVideos $videos) {}

    public function index(): View
    {
        $videos = FarmVideo::with(['order.color.material', 'order.printer', 'order.user'])->latest('id')->limit(200)->get();

        return view('admin.youtube.index', [
            'configured' => $this->youtube->configured(),
            'account' => YouTubeAccount::current(),
            'waiting' => $videos->whereIn('status', [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_QUEUED, FarmVideo::STATUS_UPLOADING, FarmVideo::STATUS_FAILED]),
            'done' => $videos->whereIn('status', [FarmVideo::STATUS_PUBLISHED, FarmVideo::STATUS_REJECTED, FarmVideo::STATUS_WITHDRAWN]),
            // agreed and filmed, but never queued (e.g. finished before the channel was connected)
            'missing' => FarmOrder::with(['color.material'])->where('kind', FarmOrder::KIND_PRINT)->where('video_consent', true)
                ->whereNotNull('timelapse_path')->whereDoesntHave('video')->latest('id')->limit(50)->get(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($this->youtube->configured(), 422, 'YOUTUBE_CLIENT_ID / YOUTUBE_CLIENT_SECRET missing in .env');
        $state = Str::random(40);
        $request->session()->put('youtube_oauth_state', $state);

        return redirect()->away($this->youtube->authUrl(route('admin.youtube.callback'), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('youtube_oauth_state');
        if (! $state || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()->route('admin.youtube.index')->with('error', 'Přihlášení vypršelo, zkuste připojení znovu.');
        }
        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('admin.youtube.index')->with('error', 'Google připojení nepovolil: '.$request->query('error', 'bez kódu'));
        }
        try {
            $account = $this->youtube->connect((string) $request->query('code'), route('admin.youtube.callback'), $request->user()->id);
        } catch (YouTubeError $e) {
            return redirect()->route('admin.youtube.index')->with('error', 'Připojení se nepovedlo: '.$e->getMessage());
        }

        return redirect()->route('admin.youtube.index')->with('status', 'Kanál „'.$account->channel_title.'“ je připojený.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->youtube->disconnect();

        return redirect()->route('admin.youtube.index')->with('status', 'Kanál je odpojený. Nová videa se nahrávat nebudou.');
    }

    public function queue(FarmOrder $order): RedirectResponse
    {
        $video = $this->videos->queueFor($order);

        return back()->with($video ? 'status' : 'error', $video ? 'Video je ve frontě na nahrání.' : 'Tuhle zakázku teď nahrát nejde (chybí souhlas, video nebo připojený kanál).');
    }

    public function publish(Request $request, FarmVideo $video): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:5000']]);
        try {
            $this->videos->publish($video, $data['title'], (string) ($data['description'] ?? ''), $request->user()->id);
        } catch (YouTubeError $e) {
            return back()->with('error', $e->reason === 'locked_private'
                ? 'YouTube nechal video soukromé. Dokud neprojde audit API projektu, jde video zveřejnit jen ručně v YouTube Studiu.'
                : 'Zveřejnění se nepovedlo: '.$e->getMessage());
        }

        return back()->with('status', 'Video je zveřejněné.');
    }

    public function reject(Request $request, FarmVideo $video): RedirectResponse
    {
        try {
            $this->videos->reject($video, $request->user()->id);
        } catch (YouTubeError $e) {
            return back()->with('error', 'Smazání z YouTube se nepovedlo: '.$e->getMessage());
        }

        return back()->with('status', 'Video je zamítnuté a smazané z YouTube.');
    }

    public function retry(FarmVideo $video): RedirectResponse
    {
        $this->videos->retry($video);

        return back()->with('status', 'Zkouším video nahrát znovu.');
    }
}
