<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** The blog: published articles, each in the languages it was written in (the old site's ones are Czech only). */
class BlogController extends Controller
{
    public function index(): View
    {
        $locale = app()->getLocale();
        // articles that can be read in this language; a language without any has no blog page
        $posts = Post::published()->latest('published_at')->get()->filter(fn (Post $p) => in_array($locale, $p->locales(), true))->values();
        Locales::only($this->languages());

        return view('blog.index', ['posts' => $posts]);
    }

    public function show(Request $request, Post $post): View
    {
        // a draft is visible to the people who write the blog, to nobody else
        abort_unless($post->isPublished() || $request->user()?->isAdmin(), 404);
        Locales::only($post->locales());
        $locale = app()->getLocale();
        $others = Post::published()->whereKeyNot($post->id)->latest('published_at')->limit(12)->get()
            ->filter(fn (Post $p) => in_array($locale, $p->locales(), true))->take(3)->values();

        return view('blog.show', ['post' => $post, 'others' => $others, 'draft' => ! $post->isPublished()]);
    }

    /** @return list<string> languages that have at least one published article */
    public static function languages(): array
    {
        // asked on every page (the footer links the blog only where there is one): remembered for a while
        return Cache::remember('blog.languages', 600, function () {
            $found = [];
            foreach (Post::published()->get(['id', 'title', 'body']) as $post) {
                $found = array_unique(array_merge($found, $post->locales()));
            }

            return array_values($found);
        });
    }
}
