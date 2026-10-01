<?php

namespace App\Http\Controllers\Designer;

use App\Domain\Designer\CardFiles;
use App\Domain\Designer\ZipMatcher;
use App\Http\Controllers\Controller;
use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * One zip with the files of many cards. The zip waits on the disk while the designer looks at how its files were
 * paired with the cards (sure, to be confirmed, unpaired) and corrects it; then every file goes to its card.
 */
class BulkUploadController extends Controller
{
    use DesignerPages;

    public function form(Request $request): View
    {
        $profile = $this->profileOf($request);

        return view('designer.bulk', ['profile' => $profile, 'waiting' => $this->cards($profile)->count(), 'maxMb' => CardFiles::MAX_ZIP_MB]);
    }

    public function store(Request $request, CardFiles $files): RedirectResponse
    {
        $profile = $this->profileOf($request);
        $request->validate(['zip' => ['required', 'file', 'mimes:zip', 'max:'.(CardFiles::MAX_ZIP_MB * 1024)]]);
        $token = Str::lower(Str::random(24));
        File::ensureDirectoryExists($this->dir($profile));
        $request->file('zip')->move($this->dir($profile), $token.'.zip');
        if (! $files->entries($this->path($profile, $token))) {
            @unlink($this->path($profile, $token));

            return back()->with('error', __('designer.file.error.zip_empty'));
        }

        return redirect()->route('designer.bulk.match', $token);
    }

    public function match(Request $request, string $token, CardFiles $files, ZipMatcher $matcher): View
    {
        $profile = $this->profileOf($request);
        $entries = $files->entries($this->zip($profile, $token));
        $cards = $this->cards($profile);

        return view('designer.bulk_match', [
            'profile' => $profile, 'token' => $token, 'entries' => $entries, 'cards' => $cards,
            'matches' => $matcher->match($entries, $cards),
            'remixes' => $profile->models()->whereNull('model_file_id')->where('is_remix', true)->whereNull('remix_confirmed_at')->count(),
        ]);
    }

    public function confirm(Request $request, string $token, CardFiles $files): RedirectResponse
    {
        $profile = $this->profileOf($request);
        $zip = $this->zip($profile, $token);
        $data = $request->validate([
            'author' => ['accepted'],
            'pairs' => ['nullable', 'array'],
            'pairs.*' => ['nullable', 'integer'],
        ], ['author.accepted' => __('designer.file.author_required')]);

        $entries = $files->entries($zip);
        $cards = $this->cards($profile)->keyBy('id');
        $done = 0;
        $failed = [];
        $used = [];
        foreach ((array) ($data['pairs'] ?? []) as $index => $cardId) {
            $entry = $entries[(int) $index] ?? null;
            $card = $cardId ? $cards->get((int) $cardId) : null;
            if (! $entry || ! $card || isset($used[$card->id])) {
                continue;   // left unpaired, or two files for one card: the first one wins
            }
            $used[$card->id] = true;
            $path = $files->extract($zip, $entry);
            $result = $path ? $files->attach($card, $path, $entry, $request->user()) : ['ok' => false];
            $path && @unlink($path);
            $result['ok'] ? $done++ : $failed[] = basename($entry);
        }
        @unlink($zip);

        $status = trans_choice('designer.bulk.done', $done, ['n' => $done]);
        if ($failed) {
            $status .= ' '.__('designer.bulk.failed', ['files' => implode(', ', array_slice($failed, 0, 8))]);
        }

        return redirect()->route('designer.dashboard', ['show' => 'file'])->with('status', $status);
    }

    /**
     * Cards a file can go to: without a file, and not a remix still waiting for the word about its original.
     *
     * @return Collection<int, DesignerModel>
     */
    private function cards(DesignerProfile $profile): Collection
    {
        return $profile->models()->whereNull('model_file_id')->where('file_status', '!=', DesignerModel::FILE_CHECKING)
            ->where(fn ($q) => $q->where('is_remix', false)->orWhereNotNull('remix_confirmed_at'))->orderBy('title')->get();
    }

    private function dir(DesignerProfile $profile): string
    {
        return storage_path('app/designer_zips/'.$profile->id);
    }

    private function path(DesignerProfile $profile, string $token): string
    {
        return $this->dir($profile).'/'.$token.'.zip';
    }

    private function zip(DesignerProfile $profile, string $token): string
    {
        abort_unless((bool) preg_match('/^[a-z0-9]{24}$/', $token) && is_file($this->path($profile, $token)), 404);

        return $this->path($profile, $token);
    }
}
