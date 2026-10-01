<?php

namespace App\Http\Controllers\Designer;

use App\Domain\Designer\CardFiles;
use App\Domain\Designer\DesignerImages;
use App\Domain\Designer\DesignerProfiles;
use App\Domain\Designer\PortfolioImporter;
use App\Http\Controllers\Controller;
use App\Models\CatalogCategory;
use App\Models\DesignerModel;
use App\Models\DesignerModelImage;
use App\Models\DesignerProfile;
use App\Models\FarmOrder;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/** One card of the portfolio: texts, pictures, the file the farm prints, the reward and the download. */
class CardController extends Controller
{
    use DesignerPages;

    public function create(Request $request): View
    {
        $profile = $this->profileOf($request);

        return view('designer.card', ['profile' => $profile, 'card' => new DesignerModel(['royalty_czk' => $profile->default_royalty_czk]), 'share' => null, 'categories' => $this->categories()]);
    }

    /** A card made by hand, for designers who publish nowhere else. */
    public function store(Request $request, PortfolioImporter $importer, DesignerProfiles $profiles): RedirectResponse
    {
        $profile = $this->profileOf($request);
        if ($profile->models()->count() >= DesignerProfile::MAX_MODELS) {
            return back()->with('error', __('designer.card.limit', ['max' => DesignerProfile::MAX_MODELS]));
        }
        $data = $this->validated($request, $profile);
        [$locale, $description] = $this->texts($request, $importer, $profile, $data);
        $card = DesignerModel::create([
            'designer_profile_id' => $profile->id, 'title' => $data['title'], 'slug' => DesignerModel::makeSlug($data['title']),
            'description' => $description, 'source' => 'manual', 'source_locale' => $locale,
            'royalty_czk' => round((float) $data['royalty_czk'], 2), 'visible' => $request->boolean('visible', true),
            'catalog_category_id' => $data['catalog_category_id'] ?? null,
        ]);
        $profiles->cardShown($card);

        return redirect()->route('designer.models.edit', $card->id)->with('status', __('designer.card.created'));
    }

    public function edit(Request $request, int $card): View
    {
        $card = $this->cardOf($request, $card)->load(['images', 'modelFile', 'profile']);
        $texts = [];
        foreach (Locales::SUPPORTED as $locale) {
            $texts[$locale] = __('designer.share.text_model', ['title' => $card->title, 'url' => $this->shareUrl($card, $locale)], $locale);
        }

        return view('designer.card', ['profile' => $card->profile, 'card' => $card, 'share' => ['url' => $this->shareUrl($card), 'texts' => $texts], 'categories' => $this->categories()]);
    }

    public function update(Request $request, int $card, PortfolioImporter $importer, DesignerProfiles $profiles): RedirectResponse
    {
        $card = $this->cardOf($request, $card);
        $profile = $card->profile;
        $data = $this->validated($request, $profile, $card);
        [$locale, $description] = $this->texts($request, $importer, $profile, $data, $card);

        $card->fill([
            'title' => $data['title'], 'description' => $description, 'source_locale' => $card->source_locale ?? $locale,
            'royalty_czk' => round((float) $data['royalty_czk'], 2),
            'catalog_category_id' => $data['catalog_category_id'] ?? null,
            'visible' => $request->boolean('visible'),
            'download_allowed' => $request->boolean('download_allowed'),
            'download_license' => $request->boolean('download_allowed') ? $data['download_license'] : null,
        ]);
        // the designer's word that the original of a remix allows commercial use and derivatives
        if ($card->is_remix && $request->boolean('remix_confirmed') && $card->remix_confirmed_at === null) {
            $card->remix_confirmed_at = now();
        }
        $card->save();
        $profiles->cardShown($card);

        return redirect()->route('designer.models.edit', $card->id)->with('status', __('designer.card.saved'));
    }

    public function destroy(Request $request, int $card, DesignerImages $images): RedirectResponse
    {
        $card = $this->cardOf($request, $card);
        // a print on its way still owes this card its reward
        $busy = FarmOrder::where('designer_model_id', $card->id)->whereIn('status', [FarmOrder::STATUS_PAID, FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING])->exists();
        if ($busy) {
            return back()->with('error', __('designer.card.busy'));
        }
        $images->removeAll($card);
        $card->delete();

        return redirect()->route('designer.dashboard')->with('status', __('designer.card.deleted'));
    }

    // ── the file ─────────────────────────────────────────────────────────────

    public function file(Request $request, int $card, CardFiles $files): RedirectResponse
    {
        $card = $this->cardOf($request, $card);
        $request->validate([
            'file' => ['required', 'file', 'max:'.(CardFiles::MAX_ZIP_MB * 1024)],
            'author' => ['accepted'],
        ], ['author.accepted' => __('designer.file.author_required')]);
        $upload = $request->file('file');
        $result = $files->attach($card, $upload->getRealPath(), $upload->getClientOriginalName(), $request->user());
        if (! $result['ok']) {
            return back()->with('error', __('designer.file.error.'.$result['error'], ['max' => CardFiles::MAX_FILE_MB]));
        }
        $status = __('designer.file.received');
        if (! empty($result['others'])) {
            $status .= ' '.__('designer.file.zip_others', ['files' => implode(', ', array_slice($result['others'], 0, 8))]);
        }

        return redirect()->route('designer.models.edit', $card->id)->with('status', $status);
    }

    public function removeFile(Request $request, int $card, CardFiles $files): RedirectResponse
    {
        $files->detach($this->cardOf($request, $card));

        return back()->with('status', __('designer.file.removed'));
    }

    // ── pictures ─────────────────────────────────────────────────────────────

    public function addImages(Request $request, int $card, DesignerImages $images): RedirectResponse
    {
        $card = $this->cardOf($request, $card);
        $request->validate(['images' => ['required', 'array', 'max:'.DesignerModel::MAX_IMAGES], 'images.*' => ['image', 'max:12288']]);
        $added = 0;
        foreach ($request->file('images') as $upload) {
            $added += $images->add($card, (string) file_get_contents($upload->getRealPath())) ? 1 : 0;
        }

        return back()->with($added ? 'status' : 'error', $added ? __('designer.images.added', ['n' => $added]) : __('designer.images.full', ['max' => DesignerModel::MAX_IMAGES]));
    }

    public function removeImage(Request $request, int $card, int $image, DesignerImages $images): RedirectResponse
    {
        $images->remove($this->imageOf($request, $card, $image));

        return back()->with('status', __('designer.images.removed'));
    }

    public function coverImage(Request $request, int $card, int $image, DesignerImages $images): RedirectResponse
    {
        $images->makeCover($this->imageOf($request, $card, $image));

        return back()->with('status', __('designer.images.cover_set'));
    }

    private function imageOf(Request $request, int $card, int $image): DesignerModelImage
    {
        return DesignerModelImage::where('designer_model_id', $this->cardOf($request, $card)->id)->findOrFail($image);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, DesignerProfile $profile, ?DesignerModel $card = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:8000'],
            'royalty_czk' => ['required', 'numeric', 'min:0', 'max:'.DesignerProfile::MAX_ROYALTY_CZK],
            'catalog_category_id' => ['nullable', 'integer', 'exists:catalog_categories,id'],
            'download_license' => [Rule::requiredIf($request->boolean('download_allowed')), 'nullable', Rule::in(DesignerModel::DOWNLOAD_LICENSES)],
        ], ['download_license.required' => __('designer.card.license_required')]);
    }

    /**
     * Descriptions by language as typed; with "translate the rest" ticked the empty ones are filled from the
     * language the designer wrote in (the language of the page comes first).
     *
     * @return array{0: ?string, 1: array<string, string>}
     */
    private function texts(Request $request, PortfolioImporter $importer, DesignerProfile $profile, array $data, ?DesignerModel $card = null): array
    {
        $typed = array_filter(array_map(fn ($t) => trim((string) $t), array_intersect_key((array) ($data['description'] ?? []), array_flip(Locales::SUPPORTED))), fn ($t) => $t !== '');
        if (! $typed || ! $request->boolean('translate')) {
            return [$typed ? (isset($typed[Locales::current()]) ? Locales::current() : array_key_first($typed)) : null, $typed];
        }
        $from = isset($typed[Locales::current()]) ? Locales::current() : array_key_first($typed);

        return $importer->describe('', ['subject_type' => 'designer_model', 'subject_id' => $card?->id, 'user_id' => $profile->user_id], $typed, $from);
    }

    /** The tree of categories a card can be filed under (two levels). */
    private function categories(): Collection
    {
        return CatalogCategory::whereNull('parent_id')->with('children')->orderBy('position')->get();
    }

    private function shareUrl(DesignerModel $card, ?string $locale = null): string
    {
        // a card the farm cannot print yet has no page of its own: its link leads to the portfolio
        return $card->model_file_id && Route::has('models.show')
            ? $card->publicUrl($locale, true)
            : $card->profile->publicUrl($locale, true);
    }
}
