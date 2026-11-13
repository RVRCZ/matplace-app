<?php

namespace App\Support;

use App\Console\Commands\BlogSync;
use App\Domain\Tools\ToolVisibility;
use App\Models\Post;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * What an article says to a search engine (docs/AP.md §15). The article's heading is written for a reader and is often
 * longer than the 60 characters a result shows; its file in the repository (resources/blog/<locale>/<slug>.md) may
 * carry two more header lines, `seo_title` and `seo_description`. They change nothing in the article itself (the
 * database, the heading, the date of the last change): they are read here, for the <title> and the description only.
 * An article without the file (written in the admin, carried over from the old site) keeps its heading and the start
 * of its excerpt.
 */
final class BlogSeo
{
    /** @var array<string, array{title: string, description: string}> */
    private static array $heads = [];

    /** @return array{title: string, description: string} the two header lines of the article's file, empty when there are none */
    public static function head(string $slug, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $key = $locale.'/'.$slug;
        if (! isset(self::$heads[$key])) {
            $head = ['title' => '', 'description' => ''];
            $path = resource_path('blog/'.$locale.'/'.$slug.'.md');
            if (preg_match('/^[a-z0-9-]+$/', $slug) && is_file($path)) {
                try {
                    $article = BlogSync::parse((string) file_get_contents($path));
                    $head = ['title' => trim($article['seo_title']), 'description' => trim($article['seo_description'])];
                } catch (\InvalidArgumentException) {
                    // a file without a header says nothing
                }
            }
            self::$heads[$key] = $head;
        }

        return self::$heads[$key];
    }

    public static function title(Post $post, ?string $locale = null): string
    {
        return PageTitle::of(self::head($post->slug, $locale)['title'] ?: $post->titleIn($locale));
    }

    public static function description(Post $post, ?string $locale = null): string
    {
        return self::head($post->slug, $locale)['description'] ?: Str::limit($post->excerptIn($locale), 155);
    }

    /**
     * The tools an article is about: the ones whose page names it under "where next" (config/landing.php), public and
     * with a page, so the article and the tool link to each other.
     *
     * @return list<array{key: string, url: string}>
     */
    public static function tools(Post $post): array
    {
        $out = [];
        foreach (LandingPage::all() as $tool => $page) {
            $route = config('tools.'.$tool.'.route');
            if (in_array($post->slug, (array) ($page['blog'] ?? []), true) && $tool !== 'calc' && $route && Route::has($route) && ToolVisibility::isPublic($tool)) {
                $out[] = ['key' => $tool, 'url' => route($route)];
            }
        }

        return $out;
    }
}
