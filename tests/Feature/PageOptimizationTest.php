<?php

namespace Tests\Feature;

use App\Console\Commands\BlogSync;
use App\Domain\Tools\ToolVisibility;
use App\Models\Post;
use App\Support\BlogLinks;
use App\Support\BlogSeo;
use App\Support\LandingPage;
use App\Support\Locales;
use App\Support\PageTitle;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The pages for search engines, one by one (docs/AP.md §15, the brief of 13 Nov 2026): a title that fits a search
 * result, the trail of the page as a line and as structured data, an article's own title and description for a
 * search, the ways between the tools and the articles, and no link to a tool that is switched off.
 */
class PageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private string $sitemaps;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        $this->sitemaps = sys_get_temp_dir().'/mp_pageopt_'.uniqid();
        config(['seo.sitemap_dir' => $this->sitemaps]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sitemaps);
        parent::tearDown();
    }

    /** @return array{title: string, description: string, html: string, ld: list<array<string, mixed>>} */
    private function pageHead(string $path): array
    {
        $html = (string) $this->get($path)->assertOk()->getContent();
        preg_match('~<title>(.*?)</title>~s', $html, $t);
        preg_match('~<meta name="description" content="([^"]*)"~', $html, $d);
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $ld);

        return [
            'title' => html_entity_decode($t[1] ?? '', ENT_QUOTES | ENT_HTML5),
            'description' => html_entity_decode($d[1] ?? '', ENT_QUOTES | ENT_HTML5),
            'html' => $html,
            'ld' => array_map(fn (string $json) => (array) json_decode($json, true), $ld[1]),
        ];
    }

    /** @param  list<array<string, mixed>>  $ld */
    private function crumbs(array $ld): ?array
    {
        foreach ($ld as $thing) {
            if (($thing['@type'] ?? null) === 'BreadcrumbList') {
                return array_map(fn (array $i) => [$i['position'], $i['name'], $i['item']], $thing['itemListElement']);
            }
        }

        return null;
    }

    public function test_the_brand_stays_in_a_title_only_while_the_whole_fits_a_search_result(): void
    {
        $this->assertSame('Blog o 3D tisku · matplace', PageTitle::fit('Blog o 3D tisku · matplace'));
        $fits = str_repeat('a', 49);
        $this->assertSame($fits.' · matplace', PageTitle::of($fits));
        $this->assertSame(60, mb_strlen(PageTitle::of($fits)));
        $this->assertSame($fits.'a', PageTitle::of($fits.'a'), 'one character more and the brand goes');
        $this->assertSame('matplace', PageTitle::fit('matplace'));
        $this->assertSame(' · matplace', PageTitle::BRAND);

        // every tool's own title fits by itself, in every language, so no page of a tool has a title cut by a search engine
        $long = [];
        foreach (Locales::SUPPORTED as $locale) {
            foreach (ToolSeo::tools() as $tool) {
                $title = (string) (ToolSeo::texts($tool, $locale)['title'] ?? '');
                if ($title === '' || mb_strlen($title) > PageTitle::MAX) {
                    $long[] = "{$tool} ({$locale}): ".mb_strlen($title);
                }
            }
        }
        $this->assertSame([], $long, 'tool titles over 60 characters or missing');

        // the pages: a short title keeps the brand, a long one keeps its own words
        foreach (['/tools/keychain', '/en/tools/keychain', '/es/tools/keychain', '/tools/figure', '/3d-tisk-praha', '/gifts', '/tools', '/blog', '/materials', '/business'] as $path) {
            if ($path === '/blog') {
                Post::create(['slug' => 'neco', 'title' => ['cs' => 'Něco'], 'body' => ['cs' => 'Text.'], 'format' => 'markdown', 'published_at' => now()->subDay()]);
            }
            $title = $this->pageHead($path)['title'];
            $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen($title), "{$path}: {$title}");
            $this->assertGreaterThanOrEqual(25, mb_strlen($title), "{$path}: {$title}");
        }
        $this->assertStringEndsWith(PageTitle::BRAND, $this->pageHead('/materials')['title']);
        $this->assertStringEndsNotWith(PageTitle::BRAND, $this->pageHead('/tools/keychain')['title']);
        $this->assertSame(ToolSeo::texts('keychain', 'cs')['title'], $this->pageHead('/tools/keychain')['title']);
    }

    public function test_a_page_says_where_it_stands_as_a_line_and_as_structured_data(): void
    {
        // a tool: under the list of the tools; its own line above the heading names both
        $page = $this->pageHead('/tools/keychain');
        $this->assertSame([[1, 'matplace', url('/')], [2, __('tools.title', [], 'cs'), url('/tools')], [3, __('tools.keychain.title', [], 'cs'), url('/tools/keychain')]], $this->crumbs($page['ld']));
        $this->assertStringContainsString('id="tool-name"', $page['html']);

        // in the language of the page
        $en = $this->crumbs($this->pageHead('/en/tools/keychain')['ld']);
        $this->assertSame([2, 'Tools', url('/en/tools')], $en[1]);
        $this->assertSame(url('/en/tools/keychain'), $en[2][2]);

        // the gift page is a tool page too; the home page is the top and has no trail
        $this->assertSame(url('/gifts'), $this->crumbs($this->pageHead('/gifts')['ld'])[2][2]);
        $this->assertNull($this->crumbs($this->pageHead('/')['ld']));

        // the list of the tools, the entry pages, the materials: the line is on the page and says the same as the data
        foreach (['/tools' => __('tools.title', [], 'cs'), '/3d-tisk-praha' => __('footer.praha', [], 'cs'), '/business' => __('footer.business', [], 'cs'),
            '/about' => __('site.footer.about', [], 'cs'), '/faq' => __('site.footer.faq', [], 'cs'), '/materials' => __('site.materials.title', [], 'cs')] as $path => $name) {
            $page = $this->pageHead($path);
            $this->assertSame([[1, 'matplace', url('/')], [2, $name, url($path)]], $this->crumbs($page['ld']), $path);
            $this->assertSame(1, substr_count($page['html'], 'data-crumbs'), $path);
            $this->assertMatchesRegularExpression('~<nav[^>]*data-crumbs>.*?<a href="'.preg_quote(url('/'), '~').'"[^>]*>matplace</a>.*?<span[^>]*aria-current="page">'.preg_quote(e($name), '~').'</span>~s', $page['html'], $path);
            // one BreadcrumbList a page
            $this->assertCount(1, array_filter($page['ld'], fn (array $t) => ($t['@type'] ?? null) === 'BreadcrumbList'), $path);
        }
        // the list's heading says what the list is; the menu keeps the short name
        $this->assertStringContainsString('<h1 class="text-3xl font-semibold text-ink">'.e(__('tools.seo.h1', [], 'cs')).'</h1>', $this->pageHead('/tools')['html']);
    }

    public function test_an_article_has_a_title_and_a_description_made_for_a_search_and_keeps_its_heading(): void
    {
        $this->artisan('blog:sync')->assertSuccessful();

        // every article of the repository: a title that fits (its heading, or the header's seo_title) and a description of its own
        $seen = [];
        foreach (BlogSync::files() as $slug => $path) {
            $article = BlogSync::parse((string) file_get_contents($path));
            $post = Post::where('slug', $slug)->firstOrFail();
            $title = BlogSeo::title($post, 'cs');
            $description = BlogSeo::description($post, 'cs');
            $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen($title), "{$slug}: {$title}");
            $this->assertGreaterThanOrEqual(30, mb_strlen($title), "{$slug}: {$title}");
            $this->assertNotSame('', $article['seo_description'], "{$slug}: a description written for the search result");
            $this->assertSame($article['seo_description'], $description);
            $this->assertLessThanOrEqual(155, mb_strlen($description), "{$slug}: {$description}");
            $this->assertGreaterThanOrEqual(110, mb_strlen($description), "{$slug}: {$description}");
            $this->assertStringEndsNotWith('...', $description);
            $this->assertArrayNotHasKey($title, $seen, "{$slug}: the title is the one of {$slug}");
            $seen[$title] = $slug;
            // the header's two lines change nothing in the article itself
            $this->assertSame($article['title'], $post->titleIn('cs'));
            $this->assertStringNotContainsString('seo_title', (string) $post->body['cs']);
        }
        $this->assertGreaterThan(25, count($seen));

        // the page: the search title in <title> and og:title, the reader's heading in the H1 and in the Article
        $slug = 'vanocni-ozdoby-se-jmenem-z-3d-tiskarny';
        $post = Post::where('slug', $slug)->firstOrFail();
        $page = $this->pageHead('/blog/'.$slug);
        $this->assertSame('Vánoční ozdoby se jménem z 3D tiskárny: ceny a termíny', $page['title']);
        $this->assertStringContainsString('<meta property="og:title" content="'.e($page['title']).'">', $page['html']);
        $this->assertSame(BlogSeo::head($slug, 'cs')['description'], $page['description']);
        $this->assertStringContainsString(e($post->titleIn('cs')).'</h1>', $page['html']);
        $article = collect($page['ld'])->firstWhere('@type', 'Article');
        $this->assertSame($post->titleIn('cs'), $article['headline']);
        // an article without a photo is shown by its card, the picture of a shared link
        $this->assertSame(route('og', ['type' => 'article', 'id' => $slug]), $article['image']);
        // the date of the last change is the article's own, not the day of this deploy
        $this->assertSame($post->updated_at->toIso8601String(), $article['dateModified']);
        $this->assertSame('2026-11-06', $post->updated_at->toDateString());

        // an article written in the admin has no file: its heading and the start of its excerpt
        $own = Post::create(['slug' => 'z-adminu', 'title' => ['cs' => 'Krátký článek z adminu'], 'excerpt' => ['cs' => str_repeat('Věta o tisku. ', 20)], 'body' => ['cs' => 'Text.'], 'format' => 'markdown', 'published_at' => now()->subDay()]);
        $this->assertSame('Krátký článek z adminu · matplace', BlogSeo::title($own, 'cs'));
        $this->assertLessThanOrEqual(158, mb_strlen(BlogSeo::description($own, 'cs')));
        $this->assertSame(['title' => '', 'description' => ''], BlogSeo::head('../../.env', 'cs'));
    }

    public function test_tools_and_articles_lead_to_each_other(): void
    {
        $this->artisan('blog:sync')->assertSuccessful();

        // every tool with a page has a "where next"; everything it names exists
        $this->assertSame([], array_values(array_diff(ToolSeo::tools(), array_keys(LandingPage::all()))), 'tools without a where-next');
        $this->assertSame([], array_intersect_key((array) config('landing.pages'), (array) config('landing.next')), 'a tool is in one of the two lists');
        foreach (LandingPage::all() as $tool => $links) {
            $this->assertArrayHasKey($tool, config('tools'), $tool);
            $this->assertNotEmpty($links['related'], $tool);
            foreach ($links['related'] as $key) {
                $this->assertNotEmpty(config('tools.'.$key.'.route'), "{$tool}: {$key} is a tool with a page");
                $this->assertNotSame($tool, $key);
            }
        }

        // a tool without the landing blocks still shows its neighbours and its article, and no questions it has no texts for
        $html = $this->pageHead('/tools/stencil')['html'];
        $next = substr($html, (int) strpos($html, 'id="landing-next"'), 3000);
        $this->assertStringContainsString('href="'.url('/tools/stamp').'"', $next);
        $this->assertStringContainsString('href="'.url('/blog/svg-do-stl-logo-a-kresba-jako-3d-model').'"', $next);
        $this->assertStringNotContainsString('id="landing-order"', $html);
        $this->assertSame([], LandingPage::order('stencil'));

        // the article names the tools whose pages name it
        $html = $this->pageHead('/blog/klicenka-a-privesek-z-obrazku')['html'];
        $tools = substr($html, (int) strpos($html, 'id="article-tools"'), 4000);
        $this->assertStringContainsString(e(__('site.blog.tools_title', [], 'cs')), $tools);
        foreach (['/tools/keychain', '/tools/charm', '/tools/earrings'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $tools, $path);
        }
        // a tool that is switched off is not offered there
        DB::table('tool_flags')->insert(['tool' => 'charm', 'public' => false, 'created_at' => now(), 'updated_at' => now()]);
        ToolVisibility::forget();
        $html = $this->pageHead('/blog/klicenka-a-privesek-z-obrazku')['html'];
        $this->assertStringNotContainsString('href="'.url('/tools/charm').'"', $html);
        $this->assertStringContainsString('href="'.url('/tools/keychain').'"', $html);
    }

    public function test_an_article_does_not_link_to_a_tool_that_is_switched_off(): void
    {
        $html = '<p>Zkuste <a href="/tools/phone-stand">Stojánek na telefon</a>, <a href="https://matplace.com/en/tools/phone-stand?x=1#top" class="x">the <em>stand</em></a>'
            .' nebo <a href="/tools/keychain">klíčenku</a> a <a href="/tools/neexistuje">něco</a>, <a href="https://example.com/tools/phone-stand">jinde</a>.</p>';
        $this->assertSame($html, BlogLinks::live($html), 'every tool on offer keeps its link');

        DB::table('tool_flags')->insert(['tool' => 'phone_stand', 'public' => false, 'created_at' => now(), 'updated_at' => now()]);
        ToolVisibility::forget();
        $this->assertSame(
            '<p>Zkuste Stojánek na telefon, the <em>stand</em> nebo <a href="/tools/keychain">klíčenku</a> a <a href="/tools/neexistuje">něco</a>, <a href="https://example.com/tools/phone-stand">jinde</a>.</p>',
            BlogLinks::live($html),
        );

        // on the page: the words stay, the link to the page that answers 404 is gone
        $this->artisan('blog:sync')->assertSuccessful();
        $this->get('/tools/phone-stand')->assertNotFound();
        $page = (string) $this->get('/blog/stojanek-na-telefon-na-miru')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="/tools/phone-stand"', $page);
        $this->assertStringNotContainsString('href="'.url('/tools/phone-stand').'"', $page);
        $this->assertStringContainsString('Stojánek na telefon', $page);
    }

    public function test_pictures_of_an_article_carry_their_size_and_a_link_preview_says_how_big_it_is(): void
    {
        Storage::disk('public')->put('blog/foto.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAYAAACddGYaAAAAEUlEQVR4nGNgYGD4z4AAMAoAEAYB/2+XrIcAAAAASUVORK5CYII='));
        $url = Storage::disk('public')->url('blog/foto.png');
        $this->assertSame([3, 2], BlogLinks::size($url));
        $this->assertNull(BlogLinks::size('https://example.com/storage/blog/foto.png'), 'a picture of another site is not measured');
        $this->assertNull(BlogLinks::size('/storage/../.env'));
        $this->assertNull(BlogLinks::size('/storage/blog/neni.png'));

        $html = '<p><img src="'.$url.'" alt="První"></p><p><img src="'.$url.'" alt="Druhý" /></p><p><img src="https://example.com/x.jpg" alt="Cizí" width="10" height="5"></p>';
        $this->assertSame(
            '<p><img src="'.$url.'" alt="První" width="3" height="2" decoding="async"></p><p><img src="'.$url.'" alt="Druhý" width="3" height="2" loading="lazy" decoding="async"></p>'
            .'<p><img src="https://example.com/x.jpg" alt="Cizí" width="10" height="5" loading="lazy" decoding="async"></p>',
            BlogLinks::pictures($html),
        );

        // the article's own photo: named, sized, fetched first
        Post::create(['slug' => 's-fotkou', 'title' => ['cs' => 'Článek s fotkou'], 'body' => ['cs' => 'Text.'], 'format' => 'markdown', 'cover_path' => 'blog/foto.png', 'published_at' => now()->subDay()]);
        $page = $this->pageHead('/blog/s-fotkou');
        $this->assertMatchesRegularExpression('~<img src="[^"]*blog/foto\.png" alt="Článek s fotkou"\s+width="3" height="2"\s+fetchpriority="high"~', $page['html']);
        $this->assertSame($url, collect($page['ld'])->firstWhere('@type', 'Article')['image']);

        // our own cards for link previews are 1200 × 630 and say so
        $this->assertStringContainsString('<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">', $this->pageHead('/tools/keychain')['html']);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $page['html']);
    }

    public function test_the_sitemap_lists_the_materials_and_the_footer_leads_to_the_campaign_tools(): void
    {
        $this->artisan('matplace:sitemap')->assertSuccessful();
        foreach (['cs' => '/materials', 'en' => '/en/materials', 'es' => '/es/materials'] as $locale => $path) {
            $this->assertStringContainsString('<loc>'.url($path).'</loc>', (string) file_get_contents($this->sitemaps.'/sitemap-tools-'.$locale.'.xml'));
        }
        $html = $this->pageHead('/about')['html'];
        $footer = substr($html, (int) strpos($html, 'id="footer-make"'), 5000);
        foreach (['/tools/figure', '/tools/pet-figurine', '/tools/ornament', '/tools/medallion', '/tools/keychain', '/tools/charm', '/tools/organizer', '/gifts', '/tools', '/3d-tisk-praha', '/business'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $footer, $path);
        }
        // the list of the tools ends with the ways to a finished print
        $html = $this->pageHead('/tools')['html'];
        $more = substr($html, (int) strpos($html, 'id="tools-more"'), 3000);
        foreach (['/', '/gifts', '/3d-tisk-praha', '/business', '/materials'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $more, $path);
        }
    }
}
