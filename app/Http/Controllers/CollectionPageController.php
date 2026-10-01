<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** /collections: hand-picked sets of models. A collection is public once it is visible and has a title in the language. */
class CollectionPageController extends Controller
{
    /** @return list<string> languages at least one public collection has a title in (remembered: the footer asks on every page) */
    public static function languages(): array
    {
        return Cache::remember('collections.languages', 600, function () {
            $found = [];
            foreach (Collection::where('visible', true)->get(['id', 'title']) as $collection) {
                $found = array_unique(array_merge($found, $collection->locales()));
            }

            return array_values($found);
        });
    }

    public static function has(string $locale): bool
    {
        return in_array($locale, self::languages(), true);
    }

    public function index(): View
    {
        $locale = app()->getLocale();
        $collections = Collection::where('visible', true)->orderBy('position')->orderBy('id')->get()
            ->filter(fn (Collection $c) => in_array($locale, $c->locales(), true))->values();
        // a language no collection is written in has no such page
        Locales::only(self::languages());
        abort_if($collections->isEmpty(), 404);

        return view('collections.index', ['collections' => $collections]);
    }

    public function show(Request $request, Collection $collection): View
    {
        // a hidden collection can be looked over by the people who make it
        abort_unless($collection->getAttribute('visible') || $request->user()?->isAdmin(), 404);
        Locales::only($collection->locales());

        return view('collections.show', ['collection' => $collection, 'entries' => $collection->entries(), 'preview' => ! $collection->getAttribute('visible')]);
    }
}
