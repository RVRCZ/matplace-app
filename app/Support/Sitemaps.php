<?php

namespace App\Support;

use App\Http\Controllers\PageController;
use App\Models\CatalogModel;
use App\Models\Collection;
use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;

/**
 * Sitemaps for search engines, written as files into storage/app/sitemaps by `php artisan matplace:sitemap`
 * (daily) and served from there:
 *
 *   sitemap.xml                      the index of the files below
 *   sitemap-tools-{locale}.xml       the home page, the tools and the other fixed pages
 *   sitemap-models-{locale}.xml      models the farm prints (/models/{slug})
 *   sitemap-catalog-{n}.xml          the inspiration catalogue (/model/{slug}), 5 000 addresses a file, every language a model has
 *   sitemap-designers-{locale}.xml   public portfolios
 *   sitemap-collections-{locale}.xml collections
 *   sitemap-blog-{locale}.xml        articles
 *
 * A page is listed only in the languages it exists in; its language twins are named beside it (hreflang).
 */
final class Sitemaps
{
    public const PER_FILE = 5000;

    public static function dir(): string
    {
        return (string) (config('seo.sitemap_dir') ?: storage_path('app/sitemaps'));
    }

    /** @return array<string, int> file name → number of addresses */
    public function build(): array
    {
        $dir = self::dir().'.new';
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        $written = [];
        $write = function (string $name, array $urls) use ($dir, &$written) {
            if ($urls) {
                File::put($dir.'/'.$name, $this->urlset($urls));
                $written[$name] = count($urls);
            }
        };

        foreach (Locales::SUPPORTED as $locale) {
            $write("sitemap-tools-{$locale}.xml", $this->pages($locale));
            $write("sitemap-models-{$locale}.xml", $this->models($locale));
            $write("sitemap-designers-{$locale}.xml", $this->designers($locale));
            $write("sitemap-collections-{$locale}.xml", $this->collections($locale));
            $write("sitemap-blog-{$locale}.xml", $this->blog($locale));
        }
        $n = 0;
        $chunk = [];
        foreach ($this->catalog() as $url) {
            $chunk[] = $url;
            if (count($chunk) === self::PER_FILE) {
                $write('sitemap-catalog-'.(++$n).'.xml', $chunk);
                $chunk = [];
            }
        }
        $write('sitemap-catalog-'.(++$n).'.xml', $chunk);

        File::put($dir.'/sitemap.xml', $this->index(array_keys($written)));
        // swapped in at once: a crawler never meets a half-written set
        File::deleteDirectory(self::dir().'.old');
        if (File::isDirectory(self::dir())) {
            File::moveDirectory(self::dir(), self::dir().'.old');
        }
        File::moveDirectory($dir, self::dir());
        File::deleteDirectory(self::dir().'.old');

        return $written;
    }

    /**
     * The home page, the lists, the tools and the static pages.
     *
     * @return list<array{loc: string, lastmod?: ?string, alternates?: array<string, string>}>
     */
    private function pages(string $locale): array
    {
        $all = fn (string $route) => $this->entry($route, [], Locales::SUPPORTED, $locale);
        $urls = [$all('home'), $all('tools'), $all('models.index'), $all('catalog.index')];
        foreach (ToolSeo::tools() as $tool) {
            if ($tool !== 'calc') {
                $urls[] = $all((string) config('tools')[$tool]['route']);
            }
        }
        foreach (array_keys(PageController::PAGES) as $page) {
            if (Lang::hasForLocale('pages.'.$page.'.title', $locale)) {
                $urls[] = $all('pages.'.$page);
            }
        }
        $urls[] = $all('privacy');
        $urls[] = $all('farm.terms');

        return array_values(array_filter($urls));
    }

    private function models(string $locale): array
    {
        return DesignerModel::printable()->get()
            ->map(fn (DesignerModel $m) => $this->entry('models.show', ['designerModel' => $m->slug], $m->locales(), $locale, $m->updated_at))
            ->filter()->values()->all();
    }

    private function designers(string $locale): array
    {
        return DesignerProfile::where('visible', true)->get()
            ->map(fn (DesignerProfile $p) => $this->entry('designers.show', ['designer' => $p->slug], Locales::SUPPORTED, $locale, $p->updated_at))
            ->filter()->values()->all();
    }

    private function collections(string $locale): array
    {
        if (! Route::has('collections.show')) {
            return [];   // the public pages of collections come with the admin (step F)
        }

        return Collection::where('visible', true)->get()
            ->map(fn (Collection $c) => $this->entry('collections.show', ['collection' => $c->slug], method_exists($c, 'locales') ? $c->locales() : Locales::SUPPORTED, $locale, $c->updated_at))
            ->filter()->values()->all();
    }

    private function blog(string $locale): array
    {
        $posts = Post::published()->latest('published_at')->get()->filter(fn (Post $p) => in_array($locale, $p->locales(), true));
        if ($posts->isEmpty()) {
            return [];
        }
        $languages = array_values(array_filter(Locales::SUPPORTED, fn ($l) => Post::published()->get()->contains(fn (Post $p) => in_array($l, $p->locales(), true))));

        return array_merge(
            [$this->entry('blog.index', [], $languages, $locale, $posts->max('updated_at'))],
            $posts->map(fn (Post $p) => $this->entry('blog.show', ['post' => $p->slug], $p->locales(), $locale, $p->updated_at))->values()->all(),
        );
    }

    /** Every language version of every shown model, one after another (lazily: the catalogue has thousands). */
    private function catalog(): \Generator
    {
        foreach (CatalogModel::shown()->whereNotNull('slug')->orderBy('id')->lazy(1000) as $model) {
            $languages = $model->locales();
            foreach ($languages as $locale) {
                if ($url = $this->entry('catalog.show', ['catalogModel' => $model->slug], $languages, $locale, $model->updated_at)) {
                    yield $url;
                }
            }
        }
    }

    /**
     * One address in one language, with its twins; null when the page does not exist in that language.
     *
     * @param  list<string>  $languages  the languages the page exists in
     */
    private function entry(string $route, array $parameters, array $languages, string $locale, mixed $updated = null): ?array
    {
        $languages = array_values(array_intersect(Locales::SUPPORTED, $languages));
        if (! in_array($locale, $languages, true) || ! Route::has($route)) {
            return null;
        }
        $alternates = [];
        if (count($languages) > 1) {
            foreach ($languages as $l) {
                $alternates[$l] = localized_route($route, $parameters, $l);
            }
            $alternates['x-default'] = $alternates[Locales::DEFAULT] ?? reset($alternates);
        }

        return ['loc' => localized_route($route, $parameters, $locale), 'lastmod' => $updated ? Carbon::parse($updated)->toDateString() : null, 'alternates' => $alternates];
    }

    /** @param  list<array{loc: string, lastmod?: ?string, alternates?: array<string, string>}>  $urls */
    private function urlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.self::x($u['loc']).'</loc>';
            if (! empty($u['lastmod'])) {
                $xml .= '<lastmod>'.self::x($u['lastmod']).'</lastmod>';
            }
            foreach ($u['alternates'] ?? [] as $lang => $href) {
                $xml .= '<xhtml:link rel="alternate" hreflang="'.self::x($lang).'" href="'.self::x($href).'"/>';
            }
            $xml .= "</url>\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /** @param  list<string>  $files */
    private function index(array $files): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($files as $file) {
            $xml .= '  <sitemap><loc>'.self::x(url('/'.$file)).'</loc><lastmod>'.now()->toDateString().'</lastmod></sitemap>'."\n";
        }

        return $xml.'</sitemapindex>'."\n";
    }

    private static function x(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
