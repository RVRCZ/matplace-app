<?php

namespace Tests\Feature;

use App\Domain\Stats\SearchConsoleIndex;
use App\Http\Middleware\LegacyRedirects;
use App\Models\CatalogModel;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\Post;
use App\Models\SearchConsoleAccount;
use App\Models\SearchConsoleInspection;
use App\Models\SearchConsoleSitemap;
use App\Support\Bots;
use App\Support\Locales;
use App\Support\ToolSeo;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The cleanup for search engines of 12 Nov 2026 (docs/AP.md §14, docs/prompts/uklid-indexace.md): Google spent its
 * visits on the taken-over catalogue and on what is left of the old site, and none of the tools, entry pages and
 * articles was in its index. The catalogue keeps only pages with a value of our own, old addresses without a
 * successor are gone (410), no link or redirect makes a chain, the sitemap lists what is to be indexed with true
 * dates, robots are robots in the statistics, and one command asks Google what it keeps (faked here).
 */
class SeoCleanupTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    private string $sitemaps;

    /** @var list<array{url: string, site: string, language: string, auth: string}> what the faked Google was asked about */
    private array $asked = [];

    /** the faked Google answers 429 once it was asked this many times */
    private ?int $quotaAfter = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        $this->seed(FarmSeeder::class);   // kinds loaded on the farm: a price can be named
        $this->sitemaps = sys_get_temp_dir().'/mp_cleanup_'.uniqid();
        config(['seo.sitemap_dir' => $this->sitemaps, 'legacy.host' => 'https://legacy.matplace.com']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sitemaps);
        parent::tearDown();
    }

    private function model(array $over = []): CatalogModel
    {
        static $n = 0;
        $n++;

        return CatalogModel::create($over + [
            'slug' => 'drawer-box-'.$n, 'title' => 'Drawer box '.$n, 'description' => ['en' => 'A box for a drawer.', 'cs' => 'Krabička do šuplíku.'],
            'source_locale' => 'en', 'source' => 'printables', 'external_url' => 'https://www.printables.com/model/'.$n, 'license' => 'cc_by', 'license_restricted' => false,
            'author_name' => 'jana', 'visible' => true, 'view_count' => 100 - $n, 'max_mm' => 120,
        ]);
    }

    /** A text of our own, as catalog:enrich writes it. */
    private const OURS = ['cs' => "Naše krabička: co to je.\n\nJak ji tisknout.", 'en' => "Our box: what it is.\n\nHow to print it.", 'original' => 'A box for a drawer.'];

    /** A processed upload with a real cube on the models disk, so the (fake) slicer has something to measure. */
    private function fileOnDisk(float $size = 30.0): ModelFile
    {
        $uuid = (string) Str::uuid();
        $tmp = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($tmp, $size);
        Storage::disk('models')->put("files/{$uuid}/original.stl", (string) file_get_contents($tmp));
        Storage::disk('models')->put("files/{$uuid}/model.stl", (string) file_get_contents($tmp));
        @unlink($tmp);

        return ModelFile::factory()->create([
            'uuid' => $uuid, 'storage_path' => "files/{$uuid}/original.stl", 'stl_path' => "files/{$uuid}/model.stl",
            'volume_mm3' => $size ** 3, 'area_mm2' => 6 * $size ** 2, 'bbox' => ['x' => $size, 'y' => $size, 'z' => $size],
        ]);
    }

    /** A paid, finished, photographed order of a catalogue model. */
    private function printed(CatalogModel $model, bool $consent, string $number): FarmOrder
    {
        $order = FarmOrder::factory()->paid()->done(now()->parse('2026-11-02'))->create(['video_consent' => $consent, 'number' => $number, 'catalog_model_id' => $model->id]);
        Storage::disk('public')->put("farm/photos/{$number}.jpg", 'jpg');
        Storage::disk('public')->put("farm/photos/{$number}.webp", 'webp');
        $order->forceFill(['photo_path' => "farm/photos/{$number}.jpg", 'photo_meta' => ['files' => ['web' => "farm/photos/{$number}.jpg", 'webp' => "farm/photos/{$number}.webp"]]])->save();

        return $order;
    }

    /** @return list<array<string, mixed>> every JSON-LD thing on the page */
    private function things(string $html): array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

        return array_values(array_filter(array_map(fn (string $json) => json_decode($json, true), $m[1]), 'is_array'));
    }

    private function types(string $html): array
    {
        return array_column($this->things($html), '@type');
    }

    // ── 1. the catalogue ────────────────────────────────────────────────────────────────────────────────────────────

    public function test_the_catalogue_keeps_in_the_index_only_a_page_with_our_text_and_a_value_of_our_own(): void
    {
        $this->assertSame(['enriched', 'value'], CatalogModel::indexNeeds(), 'the default rule');

        $plain = $this->model();                                                                       // the source's text alone
        $textOnly = $this->model(['enriched_at' => now(), 'description' => self::OURS]);               // our text, nothing else of ours
        $oldFigures = $this->model(['enriched_at' => now(), 'description' => self::OURS, 'file_available' => true, 'est_minutes' => 90, 'est_grams' => 30]);   // the old site's estimate is not ours
        $sliced = $this->model(['enriched_at' => now(), 'description' => self::OURS, 'model_file_id' => $this->fileOnDisk()->id]);
        $slicedNoText = $this->model(['model_file_id' => $this->fileOnDisk(20)->id]);                  // a price of ours, the text is the source's
        $photographed = $this->model(['enriched_at' => now(), 'description' => self::OURS]);
        $private = $this->model(['enriched_at' => now(), 'description' => self::OURS]);
        $this->artisan('catalog:slice --sleep=0')->assertSuccessful();
        $this->printed($photographed, true, 'F26-000501');
        $this->printed($private, false, 'F26-000502');                                                 // no consent: the photo is not ours to show

        $this->assertSame(['enriched', 'value'], $plain->indexGaps());
        $this->assertSame(['value'], $textOnly->indexGaps());
        $this->assertSame(['value'], $oldFigures->indexGaps());
        $this->assertSame(['enriched'], $slicedNoText->fresh()->indexGaps());
        $this->assertSame(['value'], $private->indexGaps());
        foreach ([$sliced, $photographed] as $kept) {
            $this->assertSame([], $kept->fresh()->indexGaps(), $kept->slug);
            $this->assertTrue($kept->fresh()->isIndexable(), $kept->slug);
        }
        foreach ([$plain, $textOnly, $oldFigures, $slicedNoText, $private] as $out) {
            $this->assertFalse($out->fresh()->isIndexable(), $out->slug);
        }

        // a page out of the index: reachable, "noindex, follow", no canonical, no Product; nothing hidden from people
        foreach ([$plain, $textOnly, $slicedNoText] as $out) {
            $html = (string) $this->get('/model/'.$out->slug)->assertOk()->assertSee($out->title)->getContent();
            $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html, $out->slug);
            $this->assertStringNotContainsString('rel="canonical"', $html, $out->slug);
            $this->assertNotContains('Product', $this->types($html), $out->slug.' does not pose as a product of ours');
            $this->assertContains('CreativeWork', $this->types($html));
        }
        // … yet the visitor still sees our price of the print there
        $this->assertStringContainsString('id="model-calc"', (string) $this->get('/model/'.$slicedNoText->slug)->getContent());

        // a page in the index: canonical on itself, the Product only where our price of a print we may sell stands on it
        $html = (string) $this->get('/model/'.$sliced->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('noindex', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/model/'.$sliced->slug).'">', $html);
        $product = collect($this->things($html))->firstWhere('@type', 'Product');
        $this->assertNotNull($product);
        $this->assertSame('AggregateOffer', $product['offers']['@type']);
        $this->assertGreaterThan(0, $product['offers']['lowPrice']);

        $html = (string) $this->get('/model/'.$photographed->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('noindex', $html);
        $this->assertNotContains('Product', $this->types($html), 'a photo is a value, not a price: no offer is made up');
        $this->assertStringContainsString('id="model-prints"', $html);
        $this->assertStringContainsString('F26-000501.webp', $html);
        $this->assertSame(1, preg_match_all('/data-model-print/', $html));
        $this->assertStringContainsString('2. 11. 2026', $html);
        $this->assertStringNotContainsString('F26-000502', (string) $this->get('/model/'.$private->slug)->getContent(), 'no consent: not shown');

        // the sitemap lists exactly the two, in each language they have, and the command says what every rule would keep
        $this->artisan('matplace:sitemap')
            ->expectsOutputToContain('2 indexable of 7 shown pages, 5 noindex')
            ->expectsOutputToContain('By rule: enriched,value = 2; enriched = 5; value = 3; enriched,price,file = 2.')
            ->assertSuccessful();
        $catalog = (string) file_get_contents($this->sitemaps.'/sitemap-catalog-1.xml');
        $this->assertSame(4, substr_count($catalog, '<url>'));
        foreach ([$sliced, $photographed] as $kept) {
            $this->assertStringContainsString('<loc>'.url('/model/'.$kept->slug).'</loc>', $catalog);
            $this->assertStringContainsString('<loc>'.url('/en/model/'.$kept->slug).'</loc>', $catalog);
        }
        foreach ([$plain, $textOnly, $oldFigures, $slicedNoText, $private] as $out) {
            $this->assertStringNotContainsString('/model/'.$out->slug.'<', $catalog, $out->slug);
        }

        // the switch still works: every model with a text of ours
        config(['catalog.index_requires' => ['enriched']]);
        $this->assertTrue($textOnly->fresh()->isIndexable());
        $this->assertSame(5, CatalogModel::indexable()->count());
    }

    public function test_a_preview_taken_over_as_our_own_old_address_goes_straight_to_the_old_host_and_can_be_copied_home(): void
    {
        config(['app.url' => 'https://matplace.com']);
        $this->assertSame('https://legacy.matplace.com/assets/thumbs/2026/a.jpg', CatalogModel::directPreview('https://matplace.com/assets/thumbs/2026/a.jpg'));
        $this->assertSame('https://legacy.matplace.com/assets/thumbs/a.jpg', CatalogModel::directPreview('https://www.matplace.com/assets/thumbs/a.jpg'));
        $this->assertSame('https://legacy.matplace.com/assets/thumbs/a.jpg', CatalogModel::directPreview('https://legacy.matplace.com/assets/thumbs/a.jpg'));
        $this->assertSame('https://media.printables.com/a.jpg', CatalogModel::directPreview('https://media.printables.com/a.jpg'));
        $this->assertSame('https://matplace.com/storage/catalog/a.webp', CatalogModel::directPreview('https://matplace.com/storage/catalog/a.webp'), 'our own storage is left alone');
        $this->assertNull(CatalogModel::directPreview(null));

        $kept = $this->model(['enriched_at' => now(), 'description' => self::OURS, 'sliced_at' => now(), 'slice_minutes' => 60, 'slice_grams' => 20, 'preview_url' => 'https://matplace.com/assets/thumbs/2026/kept.jpg']);
        $other = $this->model(['preview_url' => 'https://matplace.com/assets/thumbs/2026/other.jpg']);
        $this->assertSame('https://legacy.matplace.com/assets/thumbs/2026/kept.jpg', $kept->thumbUrl());
        $this->get('/model/'.$other->slug)->assertOk()->assertSee('https://legacy.matplace.com/assets/thumbs/2026/other.jpg')->assertDontSee('https://matplace.com/assets/thumbs');

        // the indexed page gets a copy of its own; the other one is left where it is
        $png = (function () {
            $im = imagecreatetruecolor(40, 30);
            ob_start();
            imagepng($im);

            return (string) ob_get_clean();
        })();
        Http::fake(['legacy.matplace.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
        $this->artisan('catalog:own-images --dry-run')->expectsOutputToContain('1 model(s) show a picture from another address')->assertSuccessful();
        Http::assertNothingSent();
        $this->artisan('catalog:own-images')->expectsOutputToContain('1 copied, 0 could not be fetched')->assertSuccessful();
        Http::assertSentCount(1);
        $kept->refresh();
        $this->assertStringStartsWith('catalog/own/', (string) $kept->thumbnail_path);
        Storage::disk('public')->assertExists($kept->thumbnail_path);
        $this->assertStringContainsString('/storage/catalog/own/', (string) $kept->thumbUrl());
        $this->assertNull($other->fresh()->thumbnail_path);
    }

    // ── 2. what is left of the old site ─────────────────────────────────────────────────────────────────────────────

    public function test_old_addresses_without_a_successor_are_gone_and_the_ones_with_a_successor_take_one_step(): void
    {
        // thumbnails of the old catalogue: gone, in two words (Googlebot-Image asked for thousands a day)
        $thumb = $this->get('/assets/thumbs/2026/04/phone-stand.jpg');
        $thumb->assertStatus(410);
        $this->assertSame("Gone\n", $thumb->getContent());
        $this->assertNull($thumb->headers->get('Location'));
        $this->call('HEAD', '/assets/thumbs/x.webp')->assertStatus(410);

        // product pages and numbers of the e-shop that was here before: gone, with a page that leads somewhere
        foreach (['/14763', '/pla-luminous-filament-1-75mm-zeleny', '/petg-filament-cerny', '/klicenka-srdce-se-jmenem'] as $path) {
            $page = $this->get($path);
            $page->assertStatus(410)->assertSee(__('site.gone.title'))->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertSee('href="'.route('tools').'"', false);
            $this->assertTrue(LegacyRedirects::gone($path), $path);
        }

        // what still works for people stays: other files of the old site, its accounts and orders (302 there), moved pages (one 301)
        $this->get('/assets/img/logo.png')->assertStatus(302)->assertRedirect('https://legacy.matplace.com/assets/img/logo.png');
        $this->get('/blog-img/20260423_212136_5717aa99.png')->assertStatus(302);
        $this->get('/ucet/objednavky')->assertStatus(302)->assertRedirect('https://legacy.matplace.com/ucet/objednavky');
        $this->get('/materialy')->assertStatus(301)->assertRedirect(url('/materials'));
        $this->get('/stahnout/phone-stand')->assertStatus(301)->assertRedirect(url('/model/phone-stand'));
        // an address nobody knows is still honestly "not found", and a page of ours is never gone
        $this->get('/tohle-nikdy-nebylo')->assertNotFound();
        $this->get('/tools')->assertOk();
        $this->get('/3d-tisk-praha')->assertOk();
        $this->assertFalse(LegacyRedirects::gone('/tools/sign'));
        $this->assertFalse(LegacyRedirects::gone('/model/pla-filament-holder'), 'a model named after a filament is a page of ours');
        $this->get('/model/'.$this->model(['slug' => 'pla-filament-holder'])->slug)->assertOk();

        // a pattern that would match a route of ours never takes the page away
        config(['legacy.gone' => ['~^/tools$~', '~^/assets/thumbs/~']]);
        $this->get('/tools')->assertOk();
        // the switch back to the old behaviour
        config(['legacy.gone' => []]);
        $this->get('/assets/thumbs/x.jpg')->assertStatus(302)->assertRedirect('https://legacy.matplace.com/assets/thumbs/x.jpg');
    }

    public function test_sign_in_pages_are_not_for_search_engines_and_no_link_leads_robots_to_them(): void
    {
        foreach (['/login', '/en/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertDontSee('rel="canonical"', false);
        }
        $home = (string) $this->get('/tools')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<a href="'.preg_quote(route('login'), '~').'"[^>]*rel="nofollow"~', $home, 'the header\'s sign-in link');
        $login = (string) $this->get('/login')->getContent();
        preg_match_all('~<a href="[^"]*/auth/[^"]*"[^>]*>~', $login, $m);
        foreach ($m[0] as $link) {
            $this->assertStringContainsString('rel="nofollow"', $link);
        }

        $robots = (string) $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['/admin/', '/account/', '/auth/', '/farm?', '/c/', '/*?q=', '/*?sort='] as $path) {
            $this->assertStringContainsString("Disallow: {$path}\n", $robots, $path);
        }
        // nothing that is to be indexed, or that a page needs to render, is closed
        foreach (['/tools', '/gifts', '/model', '/blog', '/build', '/storage', '/img', '/login', '/assets'] as $open) {
            $this->assertDoesNotMatchRegularExpression('~^Disallow: '.preg_quote($open, '~').'/?$~m', $robots, $open);
        }
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $robots);
    }

    // ── 3. redirects and languages ──────────────────────────────────────────────────────────────────────────────────

    public function test_the_old_language_query_takes_one_step_and_no_link_of_ours_carries_it(): void
    {
        $both = $this->model(['slug' => 'cursed-furby-mask', 'description' => ['cs' => 'Maska.', 'en' => 'A mask.']]);
        $this->model(['slug' => 'drybox', 'description' => ['cs' => 'Krabice na filament.']]);

        $this->get('/model/cursed-furby-mask?lang=en')->assertStatus(301)->assertRedirect('/en/model/cursed-furby-mask');
        $this->get('/en/model/cursed-furby-mask')->assertOk();
        // no text in that language: straight to the Czech page (it was /en/model/drybox → /model/drybox, two steps)
        $this->get('/model/drybox?lang=en')->assertStatus(301)->assertRedirect('/model/drybox');
        $this->get('/model/drybox?lang=es')->assertStatus(301)->assertRedirect('/model/drybox');
        $this->get('/model/drybox')->assertOk();
        $this->get('/tools/sign?lang=en')->assertStatus(301)->assertRedirect('/en/tools/sign');
        $this->get('/en/tools/sign')->assertOk();
        $this->get('/en/tools?lang=cs')->assertStatus(301)->assertRedirect('/tools');

        Post::create(['slug' => 'clanek', 'title' => ['cs' => 'Článek', 'en' => 'Article'], 'body' => ['cs' => 'Text.', 'en' => 'Text.'], 'published_at' => now()->subDay()]);
        foreach (['/', '/en', '/es', '/tools', '/en/tools', '/gifts', '/3d-tisk-praha', '/en/tools/sign', '/model', '/model/'.$both->slug, '/en/model/'.$both->slug, '/blog', '/en/blog/clanek'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('lang=', preg_replace('~\s(hreflang|lang)="[a-z-]+"~', '', $html), "{$path}: no link, canonical or hreflang with the old ?lang=");
            preg_match_all('~<link rel="(?:canonical|alternate)"[^>]*href="([^"]+)"~', $html, $m);
            foreach ($m[1] as $href) {
                $this->assertStringNotContainsString('?', $href, "{$path}: {$href}");
            }
        }
        $this->artisan('matplace:sitemap')->assertSuccessful();
        foreach (File::files($this->sitemaps) as $file) {
            $this->assertStringNotContainsString('?lang=', $file->getContents(), $file->getFilename());
            $this->assertStringNotContainsString('amp;lang=', $file->getContents(), $file->getFilename());
        }
    }

    // ── 4. the sitemap ──────────────────────────────────────────────────────────────────────────────────────────────

    public function test_the_sitemap_lists_every_tool_and_entry_page_in_three_languages_and_only_pages_that_are_to_be_indexed(): void
    {
        Post::create(['slug' => 'kolik-stoji', 'title' => ['cs' => 'Kolik stojí 3D tisk'], 'body' => ['cs' => 'Text.'], 'published_at' => now()->subDays(3)]);
        $this->model();   // out of the index: not listed
        $this->artisan('matplace:sitemap')->assertSuccessful();
        $read = fn (string $name) => (string) @file_get_contents($this->sitemaps.'/'.$name);

        $tools = ToolSeo::tools();
        $this->assertGreaterThan(40, count($tools));
        foreach (Locales::SUPPORTED as $locale) {
            $xml = $read("sitemap-tools-{$locale}.xml");
            foreach ($tools as $tool) {
                if ($tool !== 'calc') {
                    $this->assertStringContainsString('<loc>'.localized_route(config('tools')[$tool]['route'], [], $locale).'</loc>', $xml, "{$tool} in {$locale}");
                }
            }
            foreach (['home', 'tools', 'tools.gifts', 'pages.praha', 'tools.business', 'catalog.index'] as $route) {
                $this->assertStringContainsString('<loc>'.localized_route($route, [], $locale).'</loc>', $xml, "{$route} in {$locale}");
            }
        }
        $this->assertStringContainsString('<loc>'.url('/blog').'</loc>', $read('sitemap-blog-cs.xml'));
        $this->assertStringContainsString('<loc>'.url('/blog/kolik-stoji').'</loc><lastmod>'.now()->toDateString().'</lastmod>', $read('sitemap-blog-cs.xml'), 'the day the article was last changed');
        $this->assertFileDoesNotExist($this->sitemaps.'/sitemap-catalog-1.xml');

        // true dates only: a tool's page says the day its text was written, the index says a file's newest date and none where there is none
        $sign = lang_path('cs/tools_seo/sign.php');
        $this->assertStringContainsString('<loc>'.url('/tools/sign').'</loc><lastmod>'.date('Y-m-d', filemtime($sign)).'</lastmod>', $read('sitemap-tools-cs.xml'));
        $this->assertStringContainsString('<loc>'.url('/').'</loc><xhtml:link', $read('sitemap-tools-cs.xml'), 'the home page names no date: nothing true to say');
        $index = $read('sitemap.xml');
        $this->assertStringContainsString('<loc>'.url('/sitemap-blog-cs.xml').'</loc><lastmod>'.now()->toDateString().'</lastmod>', $index, 'the newest date in the file');
        // the newest text among the tools the file lists (the home page's own text, calc, is not one of them)
        $newest = max(array_map(fn (string $tool) => date('Y-m-d', filemtime(lang_path('cs/tools_seo/'.$tool.'.php'))), array_values(array_diff($tools, ['calc']))));
        $this->assertStringContainsString('<loc>'.url('/sitemap-tools-cs.xml').'</loc><lastmod>'.$newest.'</lastmod>', $index, 'not the day the sitemap was built');
        $this->assertSame(substr_count($index, '<sitemap>'), substr_count($index, '<loc>'));

        // every listed address answers 200, is not closed to search engines and names itself as canonical (the Czech and English tools, all entry pages)
        preg_match_all('~<loc>([^<]+)</loc>~', $read('sitemap-tools-cs.xml').$read('sitemap-tools-en.xml').$read('sitemap-blog-cs.xml'), $m);
        $this->assertGreaterThan(100, count($m[1]));
        foreach ($m[1] as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode(), $url);
            $html = (string) $response->getContent();
            $this->assertStringNotContainsString('noindex', $html, $url);
            $this->assertStringContainsString('<link rel="canonical" href="'.$url.'">', $html, $url);
            $this->assertSame(1, preg_match_all('~<h1[\s>]~', $html), "{$url}: one H1");
        }
    }

    // ── 5. links from the pages search engines visit most ───────────────────────────────────────────────────────────

    public function test_the_catalogue_the_blog_and_every_other_page_lead_to_the_tools_and_the_entry_pages(): void
    {
        $model = $this->model();
        Post::create(['slug' => 'clanek', 'title' => ['cs' => 'Článek'], 'body' => ['cs' => 'Text.'], 'published_at' => now()->subDay()]);
        foreach (['/model/'.$model->slug, '/model', '/blog/clanek', '/about'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $footer = substr($html, (int) strpos($html, 'id="footer-make"'), 4000);
            $this->assertNotSame('', $footer, $path);
            foreach (['tools', 'tools.gifts', 'tools.figure', 'tools.pet', 'tools.charm', 'tools.medallion', 'tools.keychain', 'tools.organizer', 'pages.praha', 'tools.business'] as $route) {
                $this->assertStringContainsString('href="'.route($route).'"', $footer, "{$path} → {$route}");
            }
            $this->assertSame(1, substr_count($html, 'id="footer-make"'));
        }
        // in the language of the page
        $en = (string) $this->get('/en/tools')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.url('/en/tools/figure').'"', substr($en, (int) strpos($en, 'id="footer-make"'), 4000));
        $this->assertStringContainsString('Custom gifts', $en);
        // a tool switched off is not linked
        config(['seo.footer_tools' => ['figure', 'no_such_tool']]);
        $this->assertStringNotContainsString('no_such_tool', (string) $this->get('/about')->getContent());

        // the model's page: what next, as plain followed links
        $html = (string) $this->get('/model/'.$model->slug)->getContent();
        $next = substr($html, (int) strpos($html, 'id="model-next"'), 1500);
        foreach ([route('home'), route('tools'), route('tools.gifts')] as $href) {
            $this->assertMatchesRegularExpression('~<a href="'.preg_quote($href, '~').'"(?![^>]*nofollow)~', $next);
        }
        $this->assertStringContainsString(__('catalog.next.title'), $next);
    }

    // ── 7. measuring ────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_seo_tools_and_listed_data_centres_are_robots(): void
    {
        foreach ([
            'Mozilla/5.0 (compatible; SERankingBacklinksBot/1.0; +https://seranking.com/backlinks-crawler)' => 'seranking',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 SE Ranking Site Audit' => 'seranking',
            'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)' => 'semrush',
            'Mozilla/5.0 (compatible; SemrushBot-SI/0.97; +http://www.semrush.com/bot.html)' => 'semrush',
            'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Semrush-Site-Audit' => 'semrush',
            'Mozilla/5.0 (compatible; BLEXBot/1.0; +http://webmeup-crawler.com/)' => 'seo',
            'Screaming Frog SEO Spider/20.2' => 'seo',
            'Mozilla/5.0 (compatible; SISTRIX Crawler; http://crawler.sistrix.net/)' => 'seo',
        ] as $agent => $family) {
            $this->assertSame($family, Bots::byAgent($agent), $agent);
        }
        $firefox = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:159.0) Gecko/20100101 Firefox/159.0';
        $this->assertNull(Bots::byAgent($firefox), 'a browser by its name: a version alone gives nobody away');
        $this->assertNull(Bots::byAgent(self::BROWSER));

        // the operator's lists (config/bots.php): a data centre's block, a header no browser sends
        $this->assertTrue(Bots::inNetworks('203.0.113.77', ['203.0.113.0/24']));
        $this->assertTrue(Bots::inNetworks('203.0.113.77', ['198.51.100.0/24', '203.0.112.0/23']));
        $this->assertFalse(Bots::inNetworks('203.0.114.1', ['203.0.112.0/23']));
        $this->assertTrue(Bots::inNetworks('2001:db8:12::1', ['2001:db8::/32']));
        $this->assertFalse(Bots::inNetworks('2001:db9::1', ['2001:db8::/32']));
        $this->assertFalse(Bots::inNetworks('203.0.113.77', ['2001:db8::/32', 'nonsense', '']));
        $this->assertTrue(Bots::inNetworks('203.0.113.77', ['203.0.113.77']));
        config(['bots.networks' => ['203.0.113.0/24'], 'bots.agents' => ['FunnyBrowser/1.0']]);
        $this->assertSame('datacenter', Bots::byList($firefox, '203.0.113.9'));
        $this->assertSame('listed', Bots::byList('Mozilla/5.0 (X11) FunnyBrowser/1.0', '192.0.2.1'));
        $this->assertNull(Bots::byList($firefox, '192.0.2.1'));

        // on the site: a crawl of that robot, never a visit, and no session row; the same browser from elsewhere is a visit
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/tools/vase', ['User-Agent' => $firefox])->assertOk()->assertCookieMissing('mp_sid');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('/tools/vase', ['User-Agent' => 'Mozilla/5.0 (compatible; SERankingBacklinksBot/1.0)'])->assertOk();
        $this->assertSame(0, Event::where('type', Event::VISIT)->count());
        $this->assertSame(['datacenter', 'seranking'], Event::where('type', Event::CRAWL)->orderBy('id')->pluck('source')->all());
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('/tools/vase', ['User-Agent' => $firefox])->assertOk()->assertCookie('mp_sid');
        $this->assertSame(1, Event::where('type', Event::VISIT)->count());
    }

    public function test_one_repeated_header_from_one_address_block_without_our_cookie_is_a_robot_from_the_limit_on(): void
    {
        config(['bots.burst_per_hour' => 5]);
        $firefox = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:159.0) Gecko/20100101 Firefox/159.0';
        $page = fn (string $ip, string $path, array $cookies = []) => $this->withServerVariables(['REMOTE_ADDR' => $ip])->withUnencryptedCookies($cookies)->get($path, ['User-Agent' => $firefox]);

        // a robot farm in one block: every page a new "visitor", the cookie never comes back
        foreach (range(1, 9) as $i) {
            $this->flushSession();
            $page('203.0.113.'.$i, '/tools/vase')->assertOk();
        }
        $this->assertSame(5, Event::where('type', Event::VISIT)->count(), 'up to the limit they pass, as any browser on its first page');
        $this->assertSame(4, Event::where('type', Event::CRAWL)->where('source', 'burst')->count());

        // a person in the same block with the same browser who kept the cookie is not counted against the limit, nor stopped by it
        $this->flushSession();
        $first = $page('203.0.113.200', '/tools/box')->assertOk();   // over the limit already: not a visit …
        $this->assertSame(5, Event::where('type', Event::VISIT)->count());
        $token = $first->getCookie('mp_sid', false)?->getValue();
        $this->assertNotEmpty($token, '… but they get the page and the cookie as everybody');
        $page('203.0.113.200', '/tools/sign', ['mp_sid' => $token])->assertOk();   // … and with the cookie they are a browser again
        $this->assertSame(6, Event::where('type', Event::VISIT)->count());
        $this->assertSame(5, Event::where('type', Event::CRAWL)->where('source', 'burst')->count());

        // another block, or another browser in the same block, starts from nothing; the next hour too
        $this->flushSession();
        $page('198.51.100.7', '/tools/vase')->assertOk();
        $this->assertSame(7, Event::where('type', Event::VISIT)->count());
        $this->flushSession();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->get('/tools/vase', ['User-Agent' => self::BROWSER])->assertOk();
        $this->assertSame(8, Event::where('type', Event::VISIT)->count());
        $this->travel(61)->minutes();
        $this->flushSession();
        $page('203.0.113.60', '/tools/vase')->assertOk();
        $this->assertSame(9, Event::where('type', Event::VISIT)->count());

        // 0 switches the rule off
        config(['bots.burst_per_hour' => 0]);
        foreach (range(1, 8) as $i) {
            $this->flushSession();
            $page('203.0.113.'.$i, '/tools/vase')->assertOk();
        }
        $this->assertSame(17, Event::where('type', Event::VISIT)->count());
    }

    public function test_an_order_is_started_by_a_person_once_and_never_by_a_robot_following_the_link(): void
    {
        config(['farm.enabled' => true, 'farm.open' => true, 'farm.public' => true]);   // guests order (docs/AO.md)
        $a = $this->model();
        $b = $this->model();
        $href = route('farm.start', ['source' => $a->id]);
        $this->get('/model/'.$a->slug)->assertOk()->assertSee('href="'.e($href).'"', false);

        // the crawler that walked the catalogue and followed "upload and print" on every model (942 events on 9 Nov 2026)
        foreach ([$a, $b] as $model) {
            $this->get('/farm?source='.$model->id, ['User-Agent' => 'Mozilla/5.0 (compatible; SERankingBacklinksBot/1.0)'])->assertOk();
            $this->get('/farm?source='.$model->id, ['User-Agent' => self::BROWSER, 'Accept' => '*/*'])->assertOk();
        }
        $this->assertSame(0, Event::where('type', 'order_started')->count());

        // a person: once per thing, a reload is the same start; another thing is another start; half an hour later it counts again
        $browser = ['User-Agent' => self::BROWSER];
        $this->get('/farm?source='.$a->id, $browser)->assertOk();
        $this->get('/farm?source='.$a->id, $browser)->assertOk();
        $this->assertSame(1, Event::where('type', 'order_started')->count());
        $this->get('/farm?source='.$b->id, $browser)->assertOk();
        $this->assertSame(2, Event::where('type', 'order_started')->count());
        $this->travel(31)->minutes();
        $this->get('/farm?source='.$a->id, $browser)->assertOk();
        $this->assertSame(3, Event::where('type', 'order_started')->count());

        // and crawlers are told not to go there at all
        $this->assertStringContainsString("Disallow: /farm?\n", (string) $this->get('/robots.txt')->getContent());
    }

    // ── 8. the state of the index in one command ────────────────────────────────────────────────────────────────────

    /** Google as the command sees it: the token, the sitemaps of the account, and an answer per address. */
    private function fakeGoogle(): void
    {
        Http::fake(function (HttpRequest $r) {
            $url = $r->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'access-2', 'expires_in' => 3600]);
            }
            if (str_ends_with($url, '/sitemaps')) {
                return Http::response(['sitemap' => [
                    ['path' => url('/sitemap.xml'), 'lastSubmitted' => '2026-10-03T08:00:00Z', 'lastDownloaded' => '2026-11-11T04:10:00Z', 'isPending' => false, 'isSitemapsIndex' => true, 'warnings' => '0', 'errors' => '0',
                        'contents' => [['type' => 'web', 'submitted' => '603', 'indexed' => '0']]],
                    ['path' => 'https://matplace.com/sitemap_old.xml', 'lastSubmitted' => '2013-05-01T08:00:00Z', 'lastDownloaded' => '2018-02-01T00:00:00Z', 'isPending' => false, 'isSitemapsIndex' => false, 'warnings' => '2', 'errors' => '3',
                        'contents' => [['type' => 'web', 'submitted' => '13645', 'indexed' => '0']]],
                ]]);
            }
            if (str_contains($url, 'urlInspection/index:inspect')) {
                if ($this->quotaAfter !== null && count($this->asked) >= $this->quotaAfter) {
                    return Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded for quota metric', 'status' => 'RESOURCE_EXHAUSTED']], 429);
                }
                $this->asked[] = ['url' => $r['inspectionUrl'], 'site' => $r['siteUrl'], 'language' => $r['languageCode'], 'auth' => $r->header('Authorization')[0] ?? ''];
                $page = (string) parse_url($r['inspectionUrl'], PHP_URL_PATH);
                $status = match (true) {
                    in_array($page, ['', '/'], true) => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed', 'robotsTxtState' => 'ALLOWED', 'indexingState' => 'INDEXING_ALLOWED', 'pageFetchState' => 'SUCCESSFUL', 'lastCrawlTime' => '2026-11-11T05:00:00Z', 'googleCanonical' => $r['inspectionUrl'], 'userCanonical' => $r['inspectionUrl']],
                    $page === '/tools' => ['verdict' => 'NEUTRAL', 'coverageState' => 'Discovered - currently not indexed', 'robotsTxtState' => 'ALLOWED', 'indexingState' => 'INDEXING_ALLOWED', 'pageFetchState' => 'PAGE_FETCH_STATE_UNSPECIFIED'],
                    $page === '/gifts' => ['verdict' => 'NEUTRAL', 'coverageState' => 'Crawled - currently not indexed'],
                    $page === '/materials' => ['verdict' => 'NEUTRAL', 'coverageState' => 'Page with redirect'],
                    $page === '/about' => null,
                    default => ['verdict' => 'NEUTRAL', 'coverageState' => 'URL is unknown to Google'],
                };
                if ($status === null) {
                    return Http::response(['error' => ['code' => 400, 'message' => 'Invalid inspection URL', 'status' => 'INVALID_ARGUMENT']], 400);
                }

                return Http::response(['inspectionResult' => ['inspectionResultLink' => 'https://search.google.com/search-console/inspect?…', 'indexStatusResult' => $status]]);
            }

            return Http::response([], 404);
        });
    }

    public function test_one_command_asks_google_what_it_keeps_under_a_cap_and_the_weekly_report_says_it(): void
    {
        $this->travelTo('2026-11-13 08:00:00');
        $this->fakeGoogle();

        // not connected: said, nothing asked
        $this->artisan('searchconsole:inspect')->expectsOutputToContain('Search Console is not connected')->assertFailed();
        $this->artisan('searchconsole:inspect --summary')->expectsOutputToContain('Nothing was asked yet')->assertSuccessful();
        Http::assertNothingSent();

        SearchConsoleAccount::create(['site_url' => 'sc-domain:matplace.com', 'sites' => ['sc-domain:matplace.com'], 'refresh_token' => 'refresh-1', 'access_token' => 'access-1', 'access_expires_at' => now()->addHour()]);
        $this->artisan('matplace:sitemap')->assertSuccessful();
        $index = app(SearchConsoleIndex::class);
        $important = $index->important();
        $this->assertSame([url('/'), url('/tools'), url('/gifts'), url('/3d-tisk-praha'), url('/tools/figure')], array_slice($important, 0, 5), 'by importance, in Czech');
        $this->assertContains(url('/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026'), $important);
        $listed = $index->listed();
        $this->assertContains(url('/en/tools/sign'), $listed);
        $this->assertGreaterThan(150, count($listed));

        // a dry run lists and sends nothing
        $this->artisan('searchconsole:inspect --dry-run --cap=3')->expectsOutputToContain('* '.url('/tools'))->expectsOutputToContain('3 address(es) would be asked')->assertSuccessful();
        Http::assertNothingSent();

        // the important addresses, with the read-only grant, in English, for the chosen property; the answers are kept
        $this->artisan('searchconsole:inspect --important')
            ->expectsOutputToContain('Sitemaps listed by the account: 2.')
            ->expectsOutputToContain('Asked about '.count($important).' address(es).')
            ->expectsOutputToContain('zaindexováno 1')
            ->expectsOutputToContain('Důležité stránky v indexu: 1 z '.count($important).'.')
            ->expectsOutputToContain('mimo index: '.url('/tools').' — objeveno, neindexováno')
            ->expectsOutputToContain('Mapa stránek '.url('/sitemap.xml').': odesláno 603, zaindexováno 0, staženo 11. 11. 2026')
            ->expectsOutputToContain('sitemap_old.xml: odesláno 13645, zaindexováno 0, chyb 3, staženo 1. 2. 2018 — stará mapa, smazat v Search Console')
            ->assertSuccessful();
        $this->assertSame($important, array_column($this->asked, 'url'));
        $this->assertSame(['sc-domain:matplace.com'], array_values(array_unique(array_column($this->asked, 'site'))));
        $this->assertSame(['en-US'], array_values(array_unique(array_column($this->asked, 'language'))));
        $this->assertSame(['Bearer access-1'], array_values(array_unique(array_column($this->asked, 'auth'))));
        $by = SearchConsoleInspection::pluck('state', 'url');
        $this->assertSame('indexed', $by[url('/')]);
        $this->assertSame('discovered', $by[url('/tools')]);
        $this->assertSame('crawled', $by[url('/gifts')]);
        $this->assertSame('excluded', $by[url('/materials')]);
        $this->assertSame('error', $by[url('/about')]);
        $this->assertSame('unknown', $by[url('/3d-tisk-praha')]);
        $home = SearchConsoleInspection::where('url', url('/'))->first();
        $this->assertSame('2026-11-11 05:00:00', $home->last_crawl_at->toDateTimeString());
        $this->assertTrue($home->important);
        $this->assertSame('Submitted and indexed', $home->coverage);
        $this->assertStringContainsString('Invalid inspection URL', (string) SearchConsoleInspection::where('url', url('/about'))->value('error'));
        $this->assertSame(2, SearchConsoleSitemap::count());
        $this->assertSame(603, SearchConsoleSitemap::where('path', url('/sitemap.xml'))->value('submitted'));
        foreach (SearchConsoleInspection::STATES as $state) {
            $this->assertLessThanOrEqual(20, strlen($state), 'fits the column');
        }

        // the same day again: what was asked today is not asked twice; the rest of the sitemap follows, under the cap
        $before = count($this->asked);
        $this->artisan('searchconsole:inspect --cap=7')->expectsOutputToContain('Asked about 7 address(es).')->assertSuccessful();
        $this->assertCount($before + 7, $this->asked);
        $this->assertSame([], array_intersect($important, array_slice(array_column($this->asked, 'url'), $before)));
        $this->assertSame(count($important) + 7, SearchConsoleInspection::count());
        $this->assertSame(2, SearchConsoleSitemap::count(), 'the day\'s sitemaps are written over, not doubled');

        // the day's own cap counts what the day already asked
        config(['seo.inspect.daily_cap' => count($important) + 9]);
        $this->artisan('searchconsole:inspect --cap=500')->expectsOutputToContain('Asked about 2 address(es).')->assertSuccessful();
        config(['seo.inspect.daily_cap' => 1800]);

        // Google's quota: the run stops, keeps what it has and says so
        $this->asked = [];
        $this->quotaAfter = 3;
        $this->artisan('searchconsole:inspect --cap=20')->expectsOutputToContain('Stopped after 3 address(es): URL inspection: Quota exceeded')->assertSuccessful();
        $this->assertSame(count($important) + 12, SearchConsoleInspection::count());

        // next week: the important ones are asked again, a new row per day; the summary takes each address's latest answer
        $this->travelTo('2026-11-20 08:00:00');
        $this->asked = [];
        $this->quotaAfter = null;
        $this->artisan('searchconsole:inspect --important')->assertSuccessful();
        $this->assertSame(2, SearchConsoleInspection::where('url', url('/tools'))->count());
        $summary = SearchConsoleIndex::summary();
        $this->assertSame('2026-11-20', $summary['asked_on']);
        $this->assertSame(count($important) + 12, $summary['total']);
        $this->assertSame(1, $summary['states']['indexed']);
        $this->assertSame(count($important), $summary['important']['total']);

        // the weekly report for the owner carries it
        $this->artisan('matplace:stats-report --print')
            ->expectsOutputToContain('Google: co má v indexu')
            ->expectsOutputToContain('Důležité stránky v indexu: 1 z '.count($important).'.')
            ->expectsOutputToContain('odesláno 603, zaindexováno 0')
            ->assertSuccessful();
    }

    public function test_a_lost_grant_stops_the_command_with_a_word_to_connect_again(): void
    {
        SearchConsoleAccount::create(['site_url' => 'sc-domain:matplace.com', 'sites' => ['sc-domain:matplace.com'], 'refresh_token' => 'refresh-1', 'access_token' => null]);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);
        $this->artisan('searchconsole:inspect --important')->expectsOutputToContain('Connect the account again in /admin/search-console')->assertFailed();
        $this->assertSame(0, SearchConsoleInspection::count());
    }
}
