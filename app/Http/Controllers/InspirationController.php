<?php

namespace App\Http\Controllers;

use App\Models\CatalogCategory;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
use App\Support\Locales;
use App\Support\Track;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * /model/{slug}: the inspiration catalogue taken over from the old site, at its old addresses. A page shows a model
 * that lives elsewhere and links to it; the farm offers to print it only where the licence allows it, or where its
 * author has brought it to matplace.
 */
class InspirationController extends Controller
{
    public function index(Request $request, ?CatalogCategory $category = null): View
    {
        $q = trim((string) $request->query('q', ''));
        $models = CatalogModel::shown()
            ->when($category, fn (Builder $b) => $b->whereIn('category_id', $category->withChildrenIds()))
            ->when(mb_strlen($q) >= 2, fn (Builder $b) => $this->search($b, $q), fn (Builder $b) => $b->orderByDesc('view_count')->orderByDesc('id'))
            ->paginate((int) config('catalog.per_page.inspiration', 48))->withQueryString();

        return view('inspiration.index', [
            'models' => $models, 'category' => $category, 'q' => $q,
            'categories' => CatalogCategory::whereNull('parent_id')->with('children')->orderBy('position')->get(),
        ]);
    }

    public function show(Request $request, CatalogModel $catalogModel): View
    {
        $model = $catalogModel;
        abort_unless($model->getAttribute('visible'), 404);
        Locales::only($model->locales());

        if (Track::view($model)) {
            $model->increment('view_count');
        }
        $cards = $model->designerModels()->with('profile')->where('visible', true)->get();
        $printable = $cards->first(fn (DesignerModel $c) => $c->isPrintable());
        $author = ($printable ?? $cards->first())?->profile;

        return view('inspiration.show', [
            'model' => $model,
            'category' => $model->categoryRow,
            'card' => $printable,                                   // the author's own card the farm can print
            'author' => $author?->getAttribute('visible') ? $author : null,   // "the author is on matplace"
            'similar' => $model->category_id
                ? CatalogModel::shown()->where('category_id', $model->category_id)->whereKeyNot($model->id)->orderByDesc('view_count')->limit(8)->get()
                : collect(),
        ]);
    }

    /** Full text over title and tags (keywords); LIKE on drivers without it (sqlite in tests). */
    private function search(Builder $query, string $q): Builder
    {
        $terms = array_values(array_filter(preg_split('/\s+/', $q) ?: [], fn ($t) => mb_strlen($t) >= 2));
        if ($terms && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $boolean = implode(' ', array_map(fn ($t) => '+'.preg_replace('/[+\-<>()~*"@]/', '', $t).'*', $terms));

            return $query->whereRaw('MATCH(title, description, keywords) AGAINST (? IN BOOLEAN MODE)', [$boolean])
                ->orderByRaw('MATCH(title, description, keywords) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$q]);
        }
        foreach ($terms as $term) {
            $query->where(fn (Builder $w) => $w->where('title', 'like', "%{$term}%")->orWhere('keywords', 'like', "%{$term}%"));
        }

        return $query->orderByDesc('view_count');
    }
}
