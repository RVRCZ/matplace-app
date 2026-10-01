<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CollectionSuggester;
use App\Engines\Exceptions\EngineException;
use App\Engines\Translate\Translator;
use App\Http\Controllers\Controller;
use App\Models\CatalogModel;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\DesignerModel;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * /admin/collections: hand-picked sets of models from both catalogues. Made by hand or from the assistant's
 * suggestions; shown at /collections once switched to visible.
 */
class CollectionController extends Controller
{
    public function index(): View
    {
        return view('admin.collections.index', ['collections' => Collection::withCount('items')->orderBy('position')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:160']]);
        $collection = Collection::create(['slug' => CollectionSuggester::slug($data['title']), 'title' => ['cs' => $data['title']], 'visible' => false, 'position' => (int) Collection::max('position') + 1]);

        return redirect()->route('admin.collections.edit', $collection->id)->with('status', 'Kolekce založena. Přidejte modely a zveřejněte ji.');
    }

    public function edit(int $collection): View
    {
        $collection = Collection::findOrFail($collection);

        return view('admin.collections.edit', ['collection' => $collection, 'items' => $collection->items()->with(['designerModel.images', 'catalogModel'])->get()]);
    }

    public function update(Request $request, int $collection, Translator $translator): RedirectResponse
    {
        $collection = Collection::findOrFail($collection);
        $data = $request->validate([
            'title' => ['required', 'array'], 'title.cs' => ['required', 'string', 'max:160'], 'title.*' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'array'], 'description.*' => ['nullable', 'string', 'max:1000'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'visible' => ['nullable', 'boolean'], 'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cover' => ['nullable', 'image', 'max:8192'],
            'order' => ['nullable', 'array'], 'order.*' => ['integer', 'min:0', 'max:100000'],
            'remove' => ['nullable', 'array'], 'remove.*' => ['integer'],
            'add' => ['nullable', 'string', 'max:4000'],
        ]);
        $clean = fn (array $values) => array_filter(array_map(fn ($v) => trim((string) $v), array_intersect_key($values, array_flip(Locales::SUPPORTED))), fn ($v) => $v !== '');
        $title = $clean((array) $data['title']);
        $description = $clean((array) ($data['description'] ?? []));

        // "translate the empty languages": a title and a description for every language the page should exist in
        if ($request->boolean('translate')) {
            try {
                $fill = function (array $values) use ($translator, $collection): array {
                    $missing = array_values(array_diff(Locales::SUPPORTED, array_keys($values)));

                    return $missing && isset($values['cs'])
                        ? $values + $translator->translate($values['cs'], $missing, 'cs', Translator::STYLE_FAITHFUL, ['kind' => 'translate', 'subject_type' => 'collection', 'subject_id' => $collection->id])->texts
                        : $values;
                };
                $title = $fill($title);
                $description = $fill($description);
            } catch (EngineException $e) {
                return back()->withInput()->with('error', 'Překlad se nepodařil: '.$e->getMessage());
            }
        }

        $fill = ['title' => $title, 'description' => $description ?: null, 'slug' => CollectionSuggester::slug($data['slug'], $collection->id), 'visible' => $request->boolean('visible'), 'position' => (int) ($data['position'] ?? $collection->position)];
        if ($request->hasFile('cover')) {
            if ($collection->cover_path) {
                Storage::disk('public')->delete($collection->cover_path);
            }
            $fill['cover_path'] = $request->file('cover')->storeAs('collections', $collection->id.'-'.Str::lower(Str::random(8)).'.'.$request->file('cover')->extension(), 'public');
        }
        $collection->fill($fill)->save();

        CollectionItem::where('collection_id', $collection->id)->whereIn('id', (array) ($data['remove'] ?? []))->delete();
        foreach ((array) ($data['order'] ?? []) as $itemId => $position) {
            CollectionItem::where('collection_id', $collection->id)->whereKey((int) $itemId)->update(['position' => (int) $position]);
        }
        $added = $this->add($collection, (string) ($data['add'] ?? ''));

        return redirect()->route('admin.collections.edit', $collection->id)->with('status', 'Uloženo.'.($added ? " Přidáno modelů: {$added}." : ''));
    }

    public function destroy(int $collection): RedirectResponse
    {
        $collection = Collection::findOrFail($collection);
        if ($collection->cover_path) {
            Storage::disk('public')->delete($collection->cover_path);
        }
        $collection->items()->delete();
        $collection->delete();

        return redirect()->route('admin.collections.index')->with('status', 'Kolekce smazána. Modely zůstaly v katalogu.');
    }

    /** The assistant's suggestions: asked for with a button (one AI call), kept in the session to pick from. */
    public function suggestions(Request $request): View
    {
        return view('admin.collections.suggest', ['themes' => (array) $request->session()->get('collection_suggestions', []), 'asked' => $request->session()->has('collection_suggestions')]);
    }

    public function suggest(Request $request, CollectionSuggester $suggester): RedirectResponse
    {
        try {
            $request->session()->put('collection_suggestions', $suggester->suggest());
        } catch (EngineException $e) {
            return back()->with('error', 'Návrhy se nepodařilo získat: '.$e->getMessage());
        }

        return redirect()->route('admin.collections.suggestions');
    }

    public function fromSuggestion(Request $request, CollectionSuggester $suggester): RedirectResponse
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:20']])['key'];
        $themes = (array) $request->session()->get('collection_suggestions', []);
        $theme = collect($themes)->firstWhere('key', $key);
        abort_unless($theme, 404);
        $collection = $suggester->create((array) $theme['title'], (array) $theme['description'], (array) $theme['items']);
        $request->session()->put('collection_suggestions', array_values(array_filter($themes, fn ($t) => $t['key'] !== $key)));

        return redirect()->route('admin.collections.edit', $collection->id)->with('status', 'Kolekce je založená jako skrytá. Projděte modely a zveřejněte ji.');
    }

    /** Lines of addresses or slugs → items: /models/{slug} is a designer's card, /model/{slug} an inspiration model. */
    private function add(Collection $collection, string $lines): int
    {
        $position = (int) $collection->items()->max('position');
        $added = 0;
        foreach (preg_split('/[\s,]+/', trim($lines)) ?: [] as $ref) {
            if ($ref === '') {
                continue;
            }
            $path = (string) (parse_url($ref, PHP_URL_PATH) ?: $ref);
            $slug = basename(rtrim($path, '/'));
            $card = str_contains($path, '/models/') || ! str_contains($path, '/') ? DesignerModel::where('slug', $slug)->first() : null;
            $model = ! $card && (str_contains($path, '/model/') || ! str_contains($path, '/')) ? CatalogModel::where('slug', $slug)->first() : null;
            $key = $card ? ['designer_model_id' => $card->id] : ($model ? ['catalog_model_id' => $model->id] : null);
            if ($key && ! CollectionItem::where('collection_id', $collection->id)->where($key)->exists()) {
                CollectionItem::create(['collection_id' => $collection->id, 'position' => ++$position] + $key);
                $added++;
            }
        }

        return $added;
    }
}
