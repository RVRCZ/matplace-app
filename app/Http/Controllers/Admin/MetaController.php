<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Social\SocialPublisher;
use App\Engines\Exceptions\EngineException;
use App\Engines\Social\MetaClient;
use App\Engines\Social\MetaFailed;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\DesignerModel;
use App\Models\SocialPost;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * /admin/content/meta: posts about models and collections on the Facebook page and Instagram (the assistant drafts
 * the text, the admin sees a preview and decides), and a read-only overview of what the ads spent.
 */
class MetaController extends Controller
{
    public function index(Request $request, MetaClient $meta): View
    {
        $period = $request->query('period') === 'last_30d' ? 'last_30d' : 'last_7d';
        $campaigns = [];
        $adsError = null;
        if (config('services.meta.ad_account_id') || config('engines.social') === 'fake') {
            try {
                $campaigns = $meta->campaigns($period);
            } catch (MetaFailed $e) {
                $adsError = $e->getMessage();
            }
        }

        return view('admin.meta.index', [
            'available' => $meta->available(), 'period' => $period, 'campaigns' => $campaigns, 'adsError' => $adsError,
            'posts' => SocialPost::latest('id')->limit(30)->get(),
            'cards' => DesignerModel::printable()->with('images')->latest('id')->limit(24)->get(),
            'collections' => Collection::where('visible', true)->orderBy('position')->get(),
        ]);
    }

    /** The preview: the picture, the link and the text the assistant wrote, in a field the admin can change. */
    public function compose(Request $request, SocialPublisher $publisher): View|RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:designer_model,collection'], 'id' => ['required', 'integer'], 'locale' => ['nullable', Rule::in(Locales::SUPPORTED)]]);
        $subject = $this->subject($data['type'], (int) $data['id']);
        $locale = $data['locale'] ?? 'cs';
        try {
            $draft = $publisher->compose($subject, $locale);
        } catch (EngineException $e) {
            return redirect()->route('admin.meta.index')->with('error', 'Text se nepodařilo připravit: '.$e->getMessage());
        }

        return view('admin.meta.compose', ['draft' => $draft, 'type' => $data['type'], 'id' => (int) $data['id'], 'locale' => $locale,
            'already' => SocialPost::where('subject_type', $data['type'])->where('subject_id', $data['id'])->where('status', SocialPost::STATUS_POSTED)->latest('id')->get()]);
    }

    public function publish(Request $request, SocialPublisher $publisher): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:designer_model,collection'], 'id' => ['required', 'integer'], 'locale' => ['nullable', Rule::in(Locales::SUPPORTED)],
            'text' => ['required', 'string', 'min:5', 'max:2000'],
            'platforms' => ['required', 'array', 'min:1'], 'platforms.*' => [Rule::in(SocialPublisher::PLATFORMS)],
        ]);
        $posts = $publisher->publish($this->subject($data['type'], (int) $data['id']), $data['text'], $data['platforms'], $data['locale'] ?? 'cs', $request->user()->id);
        $failed = array_filter($posts, fn (SocialPost $p) => $p->status === SocialPost::STATUS_FAILED);

        return redirect()->route('admin.meta.index')->with($failed ? 'error' : 'status', $failed
            ? 'Nepodařilo se zveřejnit: '.implode('; ', array_map(fn (SocialPost $p) => $p->platform.': '.$p->error, $failed))
            : 'Zveřejněno: '.implode(', ', array_map(fn (SocialPost $p) => $p->platform, $posts)).'.');
    }

    private function subject(string $type, int $id): DesignerModel|Collection
    {
        return $type === 'designer_model' ? DesignerModel::with(['images', 'profile'])->findOrFail($id) : Collection::findOrFail($id);
    }
}
