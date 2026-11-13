<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\CatalogModel;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The quality of the pages that are to be indexed (docs/AP.md §14, point 6 of docs/prompts/uklid-indexace.md): every
 * address the sitemap lists – the tools, the entry pages, the articles, in every language – is read as a search engine
 * reads it. Hard rules are asserted: 200, one H1, a title and a description of its own, the canonical on itself,
 * hreflang that the twins return. What is only weak (a long title, a short text) is counted and, with SEO_AUDIT_OUT
 * set to a file, written there as a table for the report – it does not fail the test.
 */
class SeoQualityTest extends TestCase
{
    use RefreshDatabase;

    private string $sitemaps;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        $this->sitemaps = sys_get_temp_dir().'/mp_quality_'.uniqid();
        config(['seo.sitemap_dir' => $this->sitemaps]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sitemaps);
        parent::tearDown();
    }

    /** @return array<string, mixed> what a search engine reads in the head and the body of a page */
    private function read(string $url): array
    {
        $response = $this->get($url);
        $html = (string) $response->getContent();
        $one = fn (string $pattern) => preg_match($pattern, $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
        preg_match_all('~<link rel="alternate" hreflang="([a-z-]+)" href="([^"]+)"~', $html, $alt, PREG_SET_ORDER);
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $ld);
        $types = [];
        foreach ($ld[1] as $json) {
            $thing = json_decode($json, true);
            $this->assertIsArray($thing, "{$url}: valid JSON-LD");
            $types[] = (string) ($thing['@type'] ?? '?');
        }
        // the text of the page itself: <main> without scripts, styles and markup
        $main = preg_match('~<main\b[^>]*>(.*)</main>~s', $html, $m) ? $m[1] : '';
        $main = (string) preg_replace(['~<(script|style|svg|template|noscript)\b.*?</\1>~s', '~<[^>]+>~'], ' ', $main);
        $words = count(preg_split('~\s+~u', trim(html_entity_decode($main, ENT_QUOTES | ENT_HTML5, 'UTF-8')), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        preg_match_all('~<img\b(?![^>]*\balt=)[^>]*>~', $html, $noAlt);

        return [
            'status' => $response->getStatusCode(),
            'title' => $one('~<title>(.*?)</title>~s'),
            'description' => $one('~<meta name="description" content="([^"]*)"~'),
            'h1' => preg_match_all('~<h1[\s>]~', $html),
            'h1_text' => $one('~<h1[^>]*>(.*?)</h1>~s') !== null ? trim(strip_tags((string) $one('~<h1[^>]*>(.*?)</h1>~s'))) : null,
            'canonical' => $one('~<link rel="canonical" href="([^"]+)"~'),
            'robots' => $one('~<meta name="robots" content="([^"]+)"~'),
            'alternates' => array_column($alt, 2, 1),
            'types' => $types,
            'words' => $words,
            'img_without_alt' => count($noAlt[0]),
            'html_lang' => $one('~<html lang="([a-z-]+)"~'),
            'og_image' => $one('~<meta property="og:image" content="([^"]+)"~'),
            'bytes' => strlen($html),
            'links' => $this->links($html),
        ];
    }

    /** @return list<string> the addresses of our own site the page links to (<a href>), without fragments */
    private function links(string $html): array
    {
        preg_match_all('~<a\s[^>]*href="([^"#]+)[^"]*"~', $html, $m);
        $own = rtrim(url('/'), '/');
        $out = [];
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
                $href = $own.$href;
            }
            if ($href === $own || str_starts_with($href, $own.'/') || str_starts_with($href, $own.'?')) {
                $out[$href] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Googlebot got hundreds of 301 a day (docs/prompts/uklid-indexace.md, point 3): no link on a page that is to be
     * indexed, or on a page of the catalogue, may lead through a redirect or to a page that is not there.
     */
    public function test_no_link_on_the_pages_search_engines_read_leads_through_a_redirect(): void
    {
        $this->artisan('blog:sync')->assertSuccessful();
        $category = CatalogCategory::create(['slug' => 'domacnost', 'name' => ['cs' => 'Domácnost', 'en' => 'Home'], 'position' => 1]);
        $model = fn (string $slug, array $text) => CatalogModel::create(['slug' => $slug, 'title' => ucfirst($slug), 'description' => $text, 'source' => 'printables', 'source_locale' => 'en',
            'external_url' => 'https://www.printables.com/model/1', 'license' => 'cc_by', 'license_restricted' => false, 'visible' => true, 'category_id' => $category->id, 'tags' => ['box', 'drawer']]);
        $model('drawer-box', ['cs' => 'Krabička.', 'en' => 'A box.']);
        $model('only-czech', ['cs' => 'Jen česky.']);
        $this->artisan('matplace:sitemap')->assertSuccessful();

        $start = [url('/model'), url('/en/model'), url('/model/drawer-box'), url('/en/model/drawer-box'), url('/model/only-czech'), url('/model/kategorie/domacnost'), url('/login')];
        foreach (File::files($this->sitemaps) as $file) {
            if (preg_match_all('~<loc>([^<]+)</loc>~', $file->getContents(), $m) && $file->getFilename() !== 'sitemap.xml') {
                $start = array_merge($start, $m[1]);
            }
        }
        $links = [];   // address → a page that links to it
        foreach (array_unique($start) as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode(), $url);
            foreach ($this->links((string) $response->getContent()) as $href) {
                $links[$href] ??= $url;
            }
        }
        $this->assertGreaterThan(300, count($links));

        $articles = array_map(fn (string $file) => basename($file, '.md'), glob(resource_path('blog/cs/*.md')) ?: []);
        $bad = [];
        $elsewhere = [];   // content that lives only in the production database: named, not judged here
        foreach ($links as $href => $from) {
            $path = (string) parse_url($href, PHP_URL_PATH);
            // a calculation's own page (/c/<token>, the examples articles and the page for businesses link to): the tokens are
            // production's, the page is private ("noindex", closed in robots.txt)
            if (preg_match('~^(/[a-z]{2})?/c/[A-Za-z0-9]+$~', $path)) {
                $elsewhere[] = $href;

                continue;
            }
            $status = $this->get($href)->getStatusCode();
            // an article the old site brought to production and this repository does not hold
            if ($status === 404 && preg_match('~^(/[a-z]{2})?/blog/([a-z0-9-]+)$~', $path, $m) && ! in_array($m[2], $articles, true)) {
                $elsewhere[] = $href;

                continue;
            }
            // the order's entrance and the account send a guest to sign in (302): private pages, closed to crawlers in robots.txt or noindex
            $private = (bool) preg_match('~/(farm|account|logout)(/|\?|$)~', $path.'?');
            if ($status !== 200 && ! ($private && $status === 302)) {
                $bad[] = "{$status} {$href} (linked from {$from})";
            }
        }
        $this->assertSame([], $bad, 'links that do not answer 200: '.implode(' | ', $bad));
        if ($out = getenv('SEO_AUDIT_OUT')) {
            file_put_contents($out.'.links', count($links)." internal links checked\nonly on production (not judged):\n".implode("\n", $elsewhere)."\n");
        }
    }

    public function test_every_page_of_the_sitemap_reads_well_for_a_search_engine(): void
    {
        $this->artisan('blog:sync')->assertSuccessful();
        $this->artisan('matplace:sitemap')->assertSuccessful();

        $urls = [];   // address → language
        foreach (Locales::SUPPORTED as $locale) {
            foreach (["sitemap-tools-{$locale}.xml", "sitemap-blog-{$locale}.xml"] as $file) {
                if (is_file($this->sitemaps.'/'.$file) && preg_match_all('~<loc>([^<]+)</loc>~', (string) file_get_contents($this->sitemaps.'/'.$file), $m)) {
                    foreach ($m[1] as $url) {
                        $urls[$url] = $locale;
                    }
                }
            }
        }
        $this->assertGreaterThan(200, count($urls), 'the tools and entry pages in three languages and the articles');
        foreach (['/tools', '/gifts', '/3d-tisk-praha', '/business', '/tools/figure', '/tools/pet-figurine', '/tools/charm', '/tools/medallion', '/tools/organizer', '/blog', '/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026', '/en/tools', '/es/gifts'] as $path) {
            $this->assertArrayHasKey(url($path), $urls, "{$path} is in the sitemap");
        }

        // every page is read and every broken rule collected, so one run names them all
        $pages = [];
        $broken = [];
        foreach ($urls as $url => $locale) {
            $pages[$url] = $p = $this->read($url);
            $rules = [
                'answers 200' => $p['status'] === 200,
                'no robots meta' => $p['robots'] === null,
                'canonical on itself' => $p['canonical'] === $url,
                'one H1 with a text' => $p['h1'] === 1 && (string) $p['h1_text'] !== '',
                'the language of the page' => $p['html_lang'] === $locale,
                'a title' => (string) $p['title'] !== '',
                'a description worth a snippet (50+)' => mb_strlen((string) $p['description']) >= 50,
                'a picture for sharing' => (string) $p['og_image'] !== '',
                'every picture has an alt' => $p['img_without_alt'] === 0,
                // the tools, the entry pages and the articles say what they are; the list of the taken-over catalogue and the
                // legal texts describe nothing to a search engine, no structured data is made up for them
                'structured data' => $p['types'] !== [] || preg_match('~/(model|complaints|terms|business-terms|cookies|privacy)$~', (string) parse_url($url, PHP_URL_PATH)) === 1,
            ];
            foreach (array_keys(array_filter($rules, fn (bool $ok) => ! $ok)) as $rule) {
                $broken[] = "{$url}: {$rule}";
            }
        }

        // a title and a description of its own (within a language; the brand suffix is shared)
        foreach (['title', 'description'] as $field) {
            $seen = [];
            foreach ($pages as $url => $p) {
                $key = $urls[$url].'|'.$p[$field];
                if (isset($seen[$key])) {
                    $broken[] = "{$url}: {$field} is the same as of {$seen[$key]}";
                }
                $seen[$key] ??= $url;
            }
        }
        $this->assertSame([], $broken, 'pages that break a rule: '.implode(' | ', $broken));

        // hreflang: the page names itself, every twin exists in the sitemap and names the page back, x-default is the Czech one
        foreach ($pages as $url => $p) {
            if ($p['alternates'] === []) {
                continue;   // a page in one language (an article without a translation)
            }
            $this->assertSame($url, $p['alternates'][$urls[$url]] ?? null, "{$url}: names itself");
            $this->assertSame($p['alternates']['cs'] ?? null, $p['alternates']['x-default'] ?? null, "{$url}: x-default");
            foreach ($p['alternates'] as $lang => $twin) {
                $this->assertArrayHasKey($twin, $pages, "{$url}: its {$lang} twin {$twin} is listed");
                $this->assertContains($url, $pages[$twin]['alternates'], "{$twin} names {$url} back");
                $this->assertStringNotContainsString('?', $twin);
            }
        }

        // what the kinds of page say they are
        $types = fn (string $path) => $pages[url($path)]['types'];
        $this->assertContains('HowTo', $types('/tools/sign'));
        $this->assertContains('FAQPage', $types('/tools/figure'));
        $this->assertContains('FAQPage', $types('/3d-tisk-praha'));
        $this->assertContains('Organization', $types('/'));
        $this->assertContains('Organization', $types('/about'));
        $this->assertContains('Article', $types('/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026'));
        $this->assertContains('ItemList', $types('/tools'));
        $this->assertContains('Service', $types('/3d-tisk-praha'));
        $this->assertContains('Service', $types('/gifts'));

        // what is only weak: counted, written for the report, never a failure
        $weak = [];
        foreach ($pages as $url => $p) {
            $notes = [];
            if (mb_strlen((string) $p['title']) > 70) {
                $notes[] = 'title '.mb_strlen((string) $p['title']);
            }
            if (mb_strlen((string) $p['description']) > 160) {
                $notes[] = 'description '.mb_strlen((string) $p['description']);
            }
            if (mb_strlen((string) $p['description']) < 90) {
                $notes[] = 'short description '.mb_strlen((string) $p['description']);
            }
            if ($p['words'] < 300) {
                $notes[] = 'words '.$p['words'];
            }
            if ($notes) {
                $weak[$url] = implode(', ', $notes);
            }
        }
        if ($out = getenv('SEO_AUDIT_OUT')) {
            $lines = ["url\tlang\ttitle_len\tdesc_len\twords\tbytes\th1\ttypes\tweak"];
            foreach ($pages as $url => $p) {
                $lines[] = implode("\t", [$url, $urls[$url], mb_strlen((string) $p['title']), mb_strlen((string) $p['description']), $p['words'], $p['bytes'], $p['h1_text'], implode(',', $p['types']), $weak[$url] ?? '']);
            }
            file_put_contents($out, implode("\n", $lines)."\n");
        }
        $this->addToAssertionCount(1);
    }
}
