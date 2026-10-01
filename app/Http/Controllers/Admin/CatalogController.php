<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CatalogImporter;
use App\Domain\Catalog\CategoryClassifier;
use App\Domain\Catalog\License;
use App\Domain\Catalog\ModelTexts;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;
use App\Engines\Exceptions\EngineException;
use App\Engines\Search\MakerOnlineSearch;
use App\Engines\Search\MakerWorldSearch;
use App\Engines\Search\PrintablesSearch;
use App\Http\Controllers\Controller;
use App\Jobs\ClassifyModel;
use App\Jobs\PrepareDesignerFile;
use App\Models\CatalogCategory;
use App\Models\CatalogModel;
use App\Models\DesignerImport;
use App\Models\DesignerModel;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/catalog: both catalogues in one place. Searching the outside sources and adding what was found to the
 * inspiration catalogue, editing and hiding models, categories by AI with a review of what it was not sure about,
 * and the designers' cards (category, visibility, slicing again).
 */
class CatalogController extends Controller
{
    private const SOURCES = ['printables' => PrintablesSearch::class, 'makerworld' => MakerWorldSearch::class, 'makeronline' => MakerOnlineSearch::class];

    /** The inspiration catalogue: a list with filters. */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $models = CatalogModel::query()->with('categoryRow')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%")->orWhere('external_url', 'like', "%{$q}%")))
            ->when($request->query('category') === 'none', fn ($w) => $w->whereNull('category_id'))
            ->when(is_numeric($request->query('category')), fn ($w) => $w->where('category_id', (int) $request->query('category')))
            ->when(in_array($request->query('visible'), ['0', '1'], true), fn ($w) => $w->where('visible', $request->query('visible') === '1'))
            ->when($request->query('source'), fn ($w) => $w->where('source', (string) $request->query('source')))
            ->orderByDesc('id')->paginate(40)->withQueryString();

        return view('admin.catalog.index', [
            'models' => $models, 'q' => $q,
            'categories' => CatalogCategory::orderBy('parent_id')->orderBy('position')->get(),
            'sources' => CatalogModel::query()->distinct()->orderBy('source')->pluck('source')->filter()->values(),
            'counts' => ['all' => CatalogModel::count(), 'hidden' => CatalogModel::where('visible', false)->count(), 'uncategorised' => CatalogModel::whereNull('category_id')->count(), 'review' => $this->reviewQuery()->count()],
        ]);
    }

    /** Search Printables, MakerWorld and MakerOnline; the results can be ticked and added to the inspiration catalogue. */
    public function search(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $picked = array_values(array_intersect(array_keys(self::SOURCES), (array) $request->query('sources', (array) config('engines.admin_search'))));
        $set = new SearchResultSet([], $q, []);
        if (mb_strlen($q) >= 2) {
            foreach ($picked as $key) {
                $set = $set->merge(app(self::SOURCES[$key])->byText($q, new SearchOptions(limit: 24, locale: 'en')));
            }
        }
        $known = CatalogModel::whereIn('external_url', array_map(fn ($c) => CatalogImporter::canonical((string) $c->externalUrl), $set->items))->pluck('id', 'external_url');

        return view('admin.catalog.search', ['q' => $q, 'picked' => $picked, 'all' => array_keys(self::SOURCES), 'results' => $set->items, 'known' => $known,
            'imports' => DesignerImport::whereNull('designer_profile_id')->latest('id')->limit(10)->get()]);
    }

    /** Add the ticked search results, or a pasted list of addresses, to the inspiration catalogue. */
    public function import(Request $request, CatalogImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:'.CatalogImporter::MAX_ITEMS],
            'items.*' => ['string', 'max:4000'],
            'urls' => ['nullable', 'string', 'max:20000'],
        ]);
        $items = [];
        foreach ((array) ($data['items'] ?? []) as $json) {
            $item = json_decode($json, true);
            if (is_array($item) && ! empty($item['url'])) {
                $items[] = array_intersect_key($item, array_flip(['url', 'title', 'preview', 'source', 'author', 'license']));
            }
        }
        foreach (preg_split('/\s+/', (string) ($data['urls'] ?? '')) ?: [] as $url) {
            if (preg_match('#^https://#', $url)) {
                $items[] = ['url' => $url];
            }
        }
        if (! $items) {
            return back()->with('error', 'Nic k importu: zaškrtněte výsledky nebo vložte adresy modelů.');
        }
        $import = $importer->run($items);

        return redirect()->route('admin.catalog.imports.show', $import)->with('status', "Import dokončen: přidáno nebo přeskočeno {$import->done}, selhalo {$import->failed}.");
    }

    public function showImport(DesignerImport $import): View
    {
        abort_unless($import->designer_profile_id === null, 404);

        return view('admin.catalog.import', ['import' => $import, 'models' => CatalogModel::whereIn('id', array_filter(array_column((array) $import->items, 'model_id')))->get()->keyBy('id')]);
    }

    public function edit(Request $request, CatalogModel $model): View
    {
        return view('admin.catalog.edit', [
            'model' => $model->load('categoryRow'),
            'categories' => CatalogCategory::orderBy('parent_id')->orderBy('position')->get(),
            'licenses' => array_keys((array) __('models.license')),
            // an AI text offered by the buttons below: shown in the form, saved only with "Uložit"
            'offered' => (array) $request->session()->get('offered_text', []),
        ]);
    }

    public function update(Request $request, CatalogModel $model): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'category_id' => ['nullable', 'exists:catalog_categories,id'],
            'license' => ['nullable', 'string', 'max:30'],
            'author_name' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:20000'],
            'visible' => ['nullable', 'boolean'],
        ]);
        $license = License::key((string) ($data['license'] ?? ''));
        $description = array_filter(array_map(fn ($t) => trim((string) $t), array_intersect_key((array) ($data['description'] ?? []), array_flip(Locales::SUPPORTED))), fn ($t) => $t !== '');
        $model->forceFill([
            'title' => $data['title'], 'category_id' => $data['category_id'] ?? null, 'license' => $license, 'license_restricted' => CatalogModel::restricts($license),
            'author_name' => ($data['author_name'] ?? null) ?: null,
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? ''))))),
            'description' => $description ?: null, 'visible' => $request->boolean('visible'),
            // a person decided: the AI's suggestion is settled
            'ai_category_id' => null, 'ai_mismatch' => false,
        ])->save();

        return redirect()->route('admin.catalog.edit', $model)->with('status', 'Uloženo.');
    }

    public function toggle(CatalogModel $model): RedirectResponse
    {
        $model->forceFill(['visible' => ! $model->getAttribute('visible')])->save();

        return back()->with('status', $model->getAttribute('visible') ? 'Model je zase vidět.' : 'Model je skrytý.');
    }

    /** "Popis z obrázku" and "Přepsat popis": the assistant's text goes back into the form, not into the database. */
    public function text(Request $request, CatalogModel $model, ModelTexts $texts): RedirectResponse
    {
        $how = $request->validate(['how' => ['required', 'in:picture,rewrite']])['how'];
        try {
            $offered = $how === 'picture' ? $texts->fromPicture($model) : $texts->rewrite($model);
        } catch (EngineException $e) {
            return back()->with('error', 'AI text se nepodařil: '.$e->getMessage());
        }

        return redirect()->route('admin.catalog.edit', $model)->with('offered_text', $offered)->with('status', 'Návrh textu je ve formuláři. Zkontrolujte ho a uložte.');
    }

    /** Ask the assistant for the category of this one model right now. */
    public function classify(CatalogModel $model, CategoryClassifier $classifier): RedirectResponse
    {
        try {
            $r = $classifier->classify($model);
        } catch (EngineException $e) {
            return back()->with('error', 'Klasifikace se nepodařila: '.$e->getMessage());
        }
        $name = $r['category']?->label('cs') ?? '?';

        return back()->with('status', $r['applied']
            ? "Kategorie: {$name} (jistota ".round($r['confidence'] * 100).' %).'
            : "AI si není jistá ({$name}, ".round($r['confidence'] * 100).' %'.($r['mismatch'] ? ', obrázek neodpovídá názvu' : '').'). Čeká na kontrolu.');
    }

    /** Queue the classification of a batch of models without a category (the same as `matplace:classify-catalog`). */
    public function classifyBatch(Request $request): RedirectResponse
    {
        $limit = (int) $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:500']])['limit'] ?: 50;
        $ids = CatalogModel::shown()->whereNull('category_id')->whereNull('ai_checked_at')->orderByDesc('view_count')->limit($limit)->pluck('id');
        $ids->each(fn (int $id) => ClassifyModel::dispatch('catalog_model', $id));
        $cards = DesignerModel::where('visible', true)->whereNull('catalog_category_id')->whereNull('ai_checked_at')->limit(max(0, $limit - $ids->count()))->pluck('id');
        $cards->each(fn (int $id) => ClassifyModel::dispatch('designer_model', $id));

        return back()->with('status', 'Do fronty zařazeno '.($ids->count() + $cards->count()).' modelů. Co si AI nebude jistá, najdete v „Ke kontrole“.');
    }

    /** What the assistant was not sure about: its suggestion, why, and one click to accept it or choose otherwise. */
    public function review(): View
    {
        return view('admin.catalog.review', [
            'models' => $this->reviewQuery()->with('categoryRow')->orderByDesc('ai_checked_at')->paginate(30),
            'cards' => DesignerModel::with('profile')->where(fn ($q) => $q->whereNotNull('ai_category_id')->orWhere('ai_mismatch', true))->orderByDesc('ai_checked_at')->limit(50)->get(),
            'categories' => CatalogCategory::orderBy('parent_id')->orderBy('position')->get(),
        ]);
    }

    public function resolve(Request $request, CategoryClassifier $classifier): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:catalog_model,designer_model'], 'id' => ['required', 'integer'], 'category_id' => ['nullable', 'exists:catalog_categories,id']]);
        $model = $data['type'] === 'designer_model' ? DesignerModel::findOrFail($data['id']) : CatalogModel::findOrFail($data['id']);
        $classifier->resolve($model, isset($data['category_id']) ? CatalogCategory::find($data['category_id']) : null);

        return back()->with('status', 'Uloženo.');
    }

    /** Designers' cards: which exist, which the farm can print, their category and visibility. */
    public function cards(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.catalog.cards', [
            'cards' => DesignerModel::with(['profile', 'categoryRow', 'modelFile'])
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%")))
                ->when($request->query('file') === '1', fn ($w) => $w->whereNotNull('model_file_id'))
                ->when($request->query('file') === '0', fn ($w) => $w->whereNull('model_file_id'))
                ->orderByDesc('id')->paginate(40)->withQueryString(),
            'q' => $q, 'categories' => CatalogCategory::orderBy('parent_id')->orderBy('position')->get(),
        ]);
    }

    public function updateCard(Request $request, DesignerModel $card): RedirectResponse
    {
        $data = $request->validate(['catalog_category_id' => ['nullable', 'exists:catalog_categories,id'], 'visible' => ['nullable', 'boolean'], 'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'array'], 'description.*' => ['nullable', 'string', 'max:20000']]);
        $fill = ['catalog_category_id' => $data['catalog_category_id'] ?? null, 'visible' => $request->boolean('visible'), 'ai_category_id' => null, 'ai_mismatch' => false];
        if (! empty($data['title'])) {
            $fill['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $fill['description'] = array_filter(array_map(fn ($t) => trim((string) $t), array_intersect_key($data['description'], array_flip(Locales::SUPPORTED))), fn ($t) => $t !== '') ?: null;
        }
        $card->forceFill($fill)->save();

        return back()->with('status', 'Uloženo.');
    }

    /** Slice the card's file again with today's profiles: size, grams and minutes on the model's page follow. */
    public function reslice(DesignerModel $card): RedirectResponse
    {
        if (! $card->model_file_id) {
            return back()->with('error', 'Karta nemá soubor.');
        }
        PrepareDesignerFile::dispatch($card->id, (int) $card->model_file_id);

        return back()->with('status', 'Přeslicování je ve frontě. Rozměry, gramy a čas se po dokončení přepíší.');
    }

    private function reviewQuery()
    {
        return CatalogModel::query()->where(fn ($q) => $q->whereNotNull('ai_category_id')->orWhere('ai_mismatch', true));
    }
}
