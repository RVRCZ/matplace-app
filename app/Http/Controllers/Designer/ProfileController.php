<?php

namespace App\Http\Controllers\Designer;

use App\Domain\Designer\DesignerImages;
use App\Domain\Designer\DesignerProfiles;
use App\Domain\Designer\DesignerStats;
use App\Domain\Farm\Wallet;
use App\Http\Controllers\Controller;
use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** /account/designer: switching the profile on, the overview of what it brings, and the public details. */
class ProfileController extends Controller
{
    use DesignerPages;

    private const PER_PAGE = 24;

    public const FILTERS = ['nofile', 'file', 'hidden'];

    public function enable(Request $request, DesignerProfiles $profiles): RedirectResponse
    {
        $fresh = ! DesignerProfile::where('user_id', $request->user()->id)->exists();
        $profiles->enable($request->user());

        return redirect()->route('designer.dashboard')->with('status', $fresh ? __('designer.enabled') : null);
    }

    public function dashboard(Request $request, DesignerStats $stats, Wallet $wallet): View
    {
        $profile = $this->profileOf($request);
        $filter = in_array($request->query('show'), self::FILTERS, true) ? (string) $request->query('show') : null;
        $cards = $profile->models()->with(['images', 'modelFile'])
            ->when($filter === 'nofile', fn ($q) => $q->whereNull('model_file_id'))
            ->when($filter === 'file', fn ($q) => $q->whereNotNull('model_file_id'))
            ->when($filter === 'hidden', fn ($q) => $q->where('visible', false))
            ->latest('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('designer.dashboard', [
            'profile' => $profile,
            'cards' => $cards,
            'filter' => $filter,
            'counts' => [
                'all' => $profile->models()->count(),
                'nofile' => $profile->models()->whereNull('model_file_id')->count(),
                'file' => $profile->models()->whereNotNull('model_file_id')->count(),
                'hidden' => $profile->models()->where('visible', false)->count(),
            ],
            'week' => $stats->visits($profile, 7),
            'month' => $stats->visits($profile, 30),
            'prints' => $stats->prints($profile),
            'rewards' => $stats->rewards($profile),
            'lastRewards' => $stats->lastRewards($profile),
            'balance' => $wallet->balance($request->user()),
            'share' => $this->share($profile),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('designer.profile', ['profile' => $this->profileOf($request)]);
    }

    public function update(Request $request, DesignerImages $images): RedirectResponse
    {
        $profile = $this->profileOf($request);
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('designer_profiles', 'slug')->ignore($profile->id)],
            'bio' => ['nullable', 'string', 'max:2000'],
            'links' => ['nullable', 'array'],
            'links.*' => ['nullable', 'url:http,https', 'max:300'],
            'default_royalty_czk' => ['required', 'numeric', 'min:0', 'max:'.DesignerProfile::MAX_ROYALTY_CZK],
            'visible' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:8192'],
            'cover' => ['nullable', 'image', 'max:8192'],
        ], ['slug.regex' => __('designer.profile.slug_format')]);

        $visible = $request->boolean('visible');
        $profile->fill([
            'display_name' => $data['display_name'], 'slug' => $data['slug'], 'bio' => $data['bio'] ?? null,
            'links' => array_filter(array_intersect_key((array) ($data['links'] ?? []), array_flip(DesignerProfile::LINKS))),
            'default_royalty_czk' => round((float) $data['default_royalty_czk'], 2),
            'visible' => $visible,
        ]);
        if ($visible && $profile->published_at === null) {
            $profile->published_at = now();   // switched on by hand before the first card
        }
        $profile->save();
        foreach (['avatar', 'cover'] as $picture) {
            if ($request->hasFile($picture)) {
                $images->{$picture}($profile, (string) file_get_contents($request->file($picture)->getRealPath()));
            }
        }

        return redirect()->route('designer.profile')->with('status', __('designer.profile.saved'));
    }

    /**
     * Links the designer hands out, and a ready sentence for the description of a model elsewhere, in three languages.
     *
     * @return array{profile: string, texts: array<string, string>}
     */
    private function share(DesignerProfile $profile): array
    {
        $texts = [];
        foreach (Locales::SUPPORTED as $locale) {
            $texts[$locale] = __('designer.share.text', ['url' => $profile->publicUrl($locale, true)], $locale);
        }

        return ['profile' => $profile->publicUrl(null, true), 'texts' => $texts];
    }

    /** Whether a card can be reached by customers in the catalogue, for the list. */
    public static function state(DesignerModel $card): string
    {
        return match (true) {
            ! $card->visible => 'hidden',
            $card->file_status === DesignerModel::FILE_CHECKING => 'checking',
            $card->file_status === DesignerModel::FILE_FAILED => 'failed',
            $card->model_file_id !== null && $card->file_status === DesignerModel::FILE_READY => 'printable',
            default => 'link',
        };
    }
}
