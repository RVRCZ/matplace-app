<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\TestPhotos;
use App\Domain\YouTube\FarmVideos;
use App\Domain\YouTube\ShowcasePrints;
use App\Domain\YouTube\YouTubeClient;
use App\Domain\YouTube\YouTubeError;
use App\Http\Controllers\Controller;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\FarmVideo;
use App\Models\YouTubeAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** /admin/youtube: connect the channel, approve (publish) or reject the print videos customers agreed to share. */
class YouTubeController extends Controller
{
    public function __construct(private readonly YouTubeClient $youtube, private readonly FarmVideos $videos, private readonly TestPhotos $photos) {}

    public function index(): View
    {
        $videos = FarmVideo::with(['order.color.material', 'order.printer', 'order.user'])->latest('id')->limit(200)->get();

        return view('admin.youtube.index', [
            'configured' => $this->youtube->configured(),
            'account' => YouTubeAccount::current(),
            // the likely hits first (FarmVideos::score)
            'waiting' => $videos->whereIn('status', [FarmVideo::STATUS_PENDING, FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_QUEUED, FarmVideo::STATUS_UPLOADING, FarmVideo::STATUS_FAILED])->sortByDesc(fn ($v) => $v->score ?? -1)->values(),
            // what the end of each waiting video shows (the cleaned piece from the photo box), by order id
            'finish' => $videos->whereIn('status', [FarmVideo::STATUS_PENDING, FarmVideo::STATUS_QUEUED, FarmVideo::STATUS_FAILED])
                ->mapWithKeys(fn ($v) => [$v->farm_order_id => $v->order ? $this->photos->finishIndex($v->order) : null])->all(),
            'done' => $videos->whereIn('status', [FarmVideo::STATUS_PUBLISHED, FarmVideo::STATUS_REJECTED, FarmVideo::STATUS_WITHDRAWN]),
            'showcases' => FarmOrder::with(['color.material', 'printer', 'video'])->where('kind', FarmOrder::KIND_SHOWCASE)->latest('id')->limit(10)->get(),
            'slots' => FarmPrinterSlot::with(['color.material', 'printer'])->whereNotNull('farm_color_id')
                ->whereHas('printer', fn ($q) => $q->where('enabled', true)->where('mode', FarmPrinter::MODE_AGENT))->orderBy('farm_printer_id')->orderBy('slot')->get(),
            'qualities' => array_keys((array) app(FarmSettings::class)->get('qualities')),
            'top' => $videos->where('status', FarmVideo::STATUS_PUBLISHED)->whereNotNull('views')->sortByDesc('views')->take(5)->values(),
            'totals' => ['views' => (int) $videos->sum('views'), 'likes' => (int) $videos->sum('likes'), 'comments' => (int) $videos->sum('comments'),
                'published' => $videos->where('status', FarmVideo::STATUS_PUBLISHED)->count(), 'at' => $videos->max('stats_at')],
            // agreed and filmed, but never queued (e.g. finished before the channel was connected)
            'missing' => FarmOrder::with(['color.material'])->where('kind', FarmOrder::KIND_PRINT)->where('video_consent', true)
                ->whereNotNull('timelapse_path')->whereDoesntHave('video')->latest('id')->limit(50)->get(),
        ]);
    }

    /** The file that goes to YouTube for this order (the square Short when there is one). */
    public function file(FarmOrder $order): BinaryFileResponse
    {
        $path = $this->videos->file($order);
        abort_unless($path !== '' && Storage::disk(config('farm.disk'))->exists($path), 404);

        // a rebuilt video keeps its name: the browser has to ask every time (farm:timelapse)
        return response()->file(Storage::disk(config('farm.disk'))->path($path), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'no-cache']);
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
            $video = $this->videos->publish($video, $data['title'], (string) ($data['description'] ?? ''), $request->user()->id);
        } catch (YouTubeError $e) {
            return back()->with('error', $e->reason === 'locked_private'
                ? 'YouTube nechal video soukromé. Dokud neprojde audit API projektu, jde video zveřejnit jen ručně v YouTube Studiu.'
                : 'Zveřejnění se nepovedlo: '.$e->getMessage());
        }

        return back()->with('status', $video->status === FarmVideo::STATUS_PUBLISHED ? 'Video je zveřejněné.' : 'Video se nahrává na YouTube a hned po nahrání bude veřejné.');
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

    public function replace(FarmVideo $video): RedirectResponse
    {
        try {
            $this->videos->replace($video);
        } catch (YouTubeError $e) {
            return back()->with('error', 'Nahrazení se nepovedlo: '.$e->getMessage());
        }

        return back()->with('status', 'Stará verze je z YouTube smazaná, nová se nahrává.');
    }

    /** A print for the channel on a free machine (ShowcasePrints). */
    public function showcase(Request $request, ShowcasePrints $showcases): RedirectResponse
    {
        $data = $request->validate([
            'model' => ['required', 'string', 'max:300'],
            'slot' => ['required', 'integer', 'exists:farm_printer_slots,id'],
            'quality' => ['required', 'in:'.implode(',', array_keys((array) app(FarmSettings::class)->get('qualities')))],
        ]);
        try {
            $order = $showcases->create($data['model'], FarmPrinterSlot::findOrFail($data['slot']), $data['quality'], $request->user());
        } catch (FarmRefusal $e) {
            return back()->withInput()->with('error', $e->reason === 'model'
                ? 'Model nenalezen nebo ještě není zpracovaný. Vložte odkaz na kalkulaci (…/c/…) nebo UUID souboru.'
                : 'Ve zvoleném slotu není cívka, nebo tiskárna není zapnutá s agentem.');
        }

        return back()->with('status', 'Ukázka '.$order->number.' se připravuje a půjde do fronty. Spustí se, až potvrdíte volnou podložku.');
    }

    public function stats(): RedirectResponse
    {
        try {
            $n = $this->videos->refreshStats();
        } catch (YouTubeError $e) {
            return back()->with('error', 'Statistiky se nepodařilo načíst: '.$e->getMessage());
        }

        return back()->with('status', 'Statistiky načtené ('.$n.' videí).');
    }

    public function retry(FarmVideo $video): RedirectResponse
    {
        $this->videos->retry($video);

        return back()->with('status', 'Zkouším video nahrát znovu.');
    }
}
