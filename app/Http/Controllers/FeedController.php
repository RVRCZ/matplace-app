<?php

namespace App\Http\Controllers;

use App\Domain\Tools\ToolVisibility;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * /feed: RSS of what is new on the site in the language of the address (docs/AN.md §12.5): the published articles of
 * the blog and the tools whose output was checked by printing (their `verified` date is the only date a tool has).
 * Linked from the footer and from the head of every page.
 */
class FeedController extends Controller
{
    public function __invoke(): Response
    {
        $locale = app()->getLocale();
        $items = [];
        foreach (Post::published()->latest('published_at')->limit(40)->get() as $post) {
            if (! in_array($locale, $post->locales(), true)) {
                continue;
            }
            $items[] = ['title' => $post->titleIn($locale), 'link' => route('blog.show', $post), 'description' => $post->excerptIn($locale), 'date' => $post->published_at];
        }
        foreach (ToolVisibility::listed() as $key => $tool) {
            if (empty($tool['verified']) || empty($tool['route']) || ! Route::has($tool['route'])) {
                continue;
            }
            $items[] = ['title' => __('tools.'.$key.'.title'), 'link' => route($tool['route']), 'description' => __('tools.'.$key.'.hint'), 'date' => Carbon::parse((string) $tool['verified'])];
        }
        usort($items, fn (array $a, array $b) => $b['date'] <=> $a['date']);

        return response()
            ->view('feed.rss', ['items' => array_slice($items, 0, 50), 'locale' => $locale])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=600');
    }
}
