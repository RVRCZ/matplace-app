<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\ModelPricing;
use App\Models\CatalogCategory;
use App\Models\DesignerModel;
use App\Support\Locales;
use App\Support\Track;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /models: designers' models the farm prints. A card is here once it has a checked file and a public profile. */
class ModelCatalogController extends Controller
{
    public const SORTS = ['new', 'printed'];

    public const SIZES = ['s', 'm', 'l'];

    public function index(Request $request): View
    {
        $category = $request->query('category') ? CatalogCategory::where('slug', (string) $request->query('category'))->first() : null;
        $size = in_array($request->query('size'), self::SIZES, true) ? (string) $request->query('size') : null;
        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'new';
        $download = $request->boolean('download');
        [$small, $medium] = [(int) config('catalog.sizes.s'), (int) config('catalog.sizes.m')];

        $cards = DesignerModel::printable()->with(['images', 'modelFile', 'profile'])
            ->when($category, fn ($q) => $q->whereIn('catalog_category_id', $category->withChildrenIds()))
            ->when($size === 's', fn ($q) => $q->where('max_mm', '<=', $small))
            ->when($size === 'm', fn ($q) => $q->where('max_mm', '>', $small)->where('max_mm', '<=', $medium))
            ->when($size === 'l', fn ($q) => $q->where('max_mm', '>', $medium))
            ->when($download, fn ($q) => $q->where('download_allowed', true))
            ->when($sort === 'printed', fn ($q) => $q->orderByDesc('order_count'))
            ->latest('designer_models.id')
            ->paginate((int) config('catalog.per_page.models', 24))->withQueryString();

        return view('models.index', [
            'cards' => $cards, 'category' => $category, 'size' => $size, 'sort' => $sort, 'download' => $download,
            // only categories that have something to print are offered
            'categories' => CatalogCategory::whereIn('id', DesignerModel::printable()->whereNotNull('catalog_category_id')->select('catalog_category_id'))->orderBy('position')->get(),
        ]);
    }

    public function show(Request $request, DesignerModel $designerModel, ModelPricing $pricing): View
    {
        $card = $designerModel->load(['images', 'modelFile', 'profile.user', 'categoryRow']);
        // a card that cannot be printed has no page of its own; its designer sees it as a preview
        $owner = $request->user()?->id === $card->profile?->user_id;
        abort_unless($card->isPrintable() || ($owner && $card->model_file_id !== null), 404);
        Locales::only($card->locales() ?: Locales::SUPPORTED);

        if ($card->isPrintable() && Track::view($card)) {
            $card->increment('view_count');
        }

        return view('models.show', [
            'card' => $card,
            'designer' => $card->profile,
            'quote' => $pricing->quote($card, 1, null, $request->user()?->id),
            'materials' => $pricing->materials(),
            'more' => DesignerModel::printable()->with(['images', 'modelFile', 'profile'])->where('designer_profile_id', $card->designer_profile_id)->whereKeyNot($card->id)->latest('id')->limit(4)->get(),
            'preview' => ! $card->isPrintable(),
        ]);
    }

    /** GET /api/models/{slug}/quote?copies=&material=: the price on the model's page follows what the visitor picks. */
    public function quote(Request $request, DesignerModel $designerModel, ModelPricing $pricing): JsonResponse
    {
        abort_unless($designerModel->isPrintable(), 404);
        $data = $request->validate(['copies' => ['nullable', 'integer', 'min:1', 'max:64'], 'material' => ['nullable', 'string', 'max:20']]);

        return response()->json($pricing->quote($designerModel, (int) ($data['copies'] ?? 1), $data['material'] ?? null, $request->user()?->id));
    }
}
