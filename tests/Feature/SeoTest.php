<?php

namespace Tests\Feature;

use App\Domain\Designer\DesignerProfiles;
use App\Http\Controllers\PageController;
use App\Http\Middleware\ForwardEvents;
use App\Http\Middleware\LegacyRedirects;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Consent;
use App\Support\HtmlCleaner;
use App\Support\Sitemaps;
use App\Support\ToolSeo;
use Database\Seeders\FarmSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Step E: what search engines and link previews see (content of the tool pages, structured data, sitemaps, pictures),
 * the old site's addresses, the blog carried over, and measurement that waits for consent.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private string $sitemaps;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        $this->sitemaps = sys_get_temp_dir().'/mp_sitemaps_'.uniqid();
        config(['seo.sitemap_dir' => $this->sitemaps, 'engines.search' => ['local']]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sitemaps);
        parent::tearDown();
    }

    /** A visible designer with one card the farm can print. */
    private function printableCard(): DesignerModel
    {
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create(['name' => 'Jana Nováková', 'country' => 'CZ']);
        $profile = app(DesignerProfiles::class)->enable($user);
        $profile->forceFill(['visible' => true, 'published_at' => now()])->save();
        $card = DesignerModel::create(['designer_profile_id' => $profile->id, 'title' => 'Stojánek na telefon', 'slug' => 'stojanek-na-telefon',
            'description' => ['cs' => 'Stojánek, který drží.', 'en' => 'A stand that holds.'], 'source' => 'manual', 'royalty_czk' => 25, 'visible' => true, 'author_confirmed_at' => now()]);
        $path = sys_get_temp_dir().'/mp_seo_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 40);
        $this->actingAs($user)->post("/account/designer/models/{$card->id}/file", ['file' => new UploadedFile($path, 'model.stl', null, null, true), 'author' => 1])->assertSessionHas('status');
        $this->post('/logout');

        return $card->refresh();
    }

    /** Every structured-data block of a page, decoded; fails when one of them is not valid JSON. */
    private function structured(TestResponse $page): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page->getContent(), $m);
        $things = [];
        foreach ($m[1] as $json) {
            $thing = json_decode($json, true);
            $this->assertIsArray($thing, 'structured data is not valid JSON: '.mb_substr($json, 0, 200));
            $this->assertSame('https://schema.org', $thing['@context'] ?? null);
            $this->assertNotEmpty($thing['@type'] ?? null);
            $things[] = $thing;
        }

        return $things;
    }

    private function types(TestResponse $page): array
    {
        return array_column($this->structured($page), '@type');
    }

    public function test_every_tool_has_its_page_content_in_three_languages(): void
    {
        $tools = ToolSeo::tools();
        $this->assertGreaterThanOrEqual(22, count($tools));
        foreach ($tools as $tool) {
            $examples = count((array) config('tools')[$tool]['seo']['examples']);
            $counts = [];
            foreach (['cs', 'en', 'es'] as $locale) {
                $t = ToolSeo::texts($tool, $locale);
                $this->assertNotNull($t, "{$tool} has no texts in {$locale}");
                $where = "{$tool}/{$locale}";
                $this->assertLessThanOrEqual(60, mb_strlen($t['title']), "{$where}: title too long");
                $this->assertGreaterThanOrEqual(110, mb_strlen($t['description']), "{$where}: description too short");
                $this->assertLessThanOrEqual(160, mb_strlen($t['description']), "{$where}: description too long");
                $this->assertCount(2, $t['intro'], "{$where}: two paragraphs of introduction");
                $this->assertGreaterThanOrEqual(3, count($t['steps']), $where);
                $this->assertLessThanOrEqual(5, count($t['steps']), $where);
                $this->assertGreaterThanOrEqual(4, count($t['faq']), $where);
                $this->assertLessThanOrEqual(6, count($t['faq']), $where);
                $this->assertCount($examples, $t['examples'], "{$where}: a caption for every example of config/tools.php");
                // the product has no printers' marketplace: no text may speak of one
                $this->assertDoesNotMatchRegularExpression('/tiskař|impresor(es)? independiente|printers\' marketplace/iu', json_encode($t, JSON_UNESCAPED_UNICODE), $where);
                $counts[] = [count($t['steps']), count($t['faq'])];
            }
            $this->assertSame([$counts[0]], array_values(array_unique($counts, SORT_REGULAR)), "{$tool}: the three languages say the same number of things");
            // the pictures drawn by `matplace:tool-examples` are part of the repository
            foreach (array_keys((array) config('tools')[$tool]['seo']['examples']) as $i) {
                $this->assertFileExists(ToolSeo::examplePath($tool, $i));
                $this->assertSame([800, 600], array_slice((array) getimagesize(ToolSeo::examplePath($tool, $i)), 0, 2));
            }
        }

        // the page: the form first, the content under it, in the language of the address, with its language twins
        $cs = ToolSeo::texts('sign', 'cs');
        $page = $this->get('/tools/sign')->assertOk();
        $page->assertSee('<title>'.e($cs['title']).' · matplace</title>', false)->assertSee($cs['h1'])->assertSee($cs['steps'][0]['name'])->assertSee($cs['faq'][0]['q'])
            ->assertSee($cs['examples'][0])->assertSee('img/tool-examples/sign-1.png', false)
            ->assertSeeInOrder(['<form', 'id="about-tool"'], false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/tools/sign').'">', false)
            ->assertSee('<link rel="alternate" hreflang="es" href="'.url('/es/tools/sign').'">', false)
            ->assertSee('<meta property="og:image" content="'.url('/og/tool/sign.png').'">', false);
        $this->assertEqualsCanonicalizing(['HowTo', 'FAQPage'], $this->types($page));
        $en = ToolSeo::texts('sign', 'en');
        $this->get('/en/tools/sign')->assertOk()->assertSee($en['h1'])->assertSee($en['faq'][0]['q'])->assertDontSee($cs['h1'])
            ->assertSee('<meta property="og:image" content="'.url('/og/en/tool/sign.png').'">', false);
        $this->get('/es/tools/sign')->assertOk()->assertSee(ToolSeo::texts('sign', 'es')['h1']);
        // a tool whose address differs from its key, and the calculator on the home page
        $this->get('/en/tools/phone-stand')->assertOk()->assertSee(ToolSeo::texts('phone_stand', 'en')['h1'])->assertSee(url('/og/en/tool/phone-stand.png'), false);
        $this->get('/')->assertOk()->assertSee(ToolSeo::texts('calc', 'cs')['h1']);
    }

    public function test_structured_data_is_valid_on_every_kind_of_page(): void
    {
        $card = $this->printableCard();
        $inspiration = CatalogModel::create(['legacy_id' => 7, 'slug' => 'phone-stand', 'title' => 'Phone stand', 'description' => ['cs' => 'Jednoduchý stojánek.'], 'source' => 'printables',
            'external_url' => 'https://www.printables.com/model/7', 'license' => 'cc_by', 'license_restricted' => false, 'visible' => true, 'author_name' => 'Somebody']);
        $post = Post::create(['slug' => 'jak-na-to', 'title' => ['cs' => 'Jak na to'], 'body' => ['cs' => "Odstavec.\n\n## Nadpis"], 'published_at' => now()->subDay()]);

        $home = $this->get('/')->assertOk();
        $this->assertEqualsCanonicalizing(['Organization', 'WebSite', 'HowTo', 'FAQPage'], $this->types($home));
        $site = collect($this->structured($home))->firstWhere('@type', 'WebSite');
        $this->assertSame(url('/model').'?q={search_term_string}', $site['potentialAction']['target']['urlTemplate'], 'the search box opens the catalogue');

        $this->assertEqualsCanonicalizing(['Product', 'Person', 'BreadcrumbList'], $this->types($model = $this->get('/models/'.$card->slug)->assertOk()));
        $product = collect($this->structured($model))->firstWhere('@type', 'Product');
        $this->assertSame('CZK', $product['offers']['priceCurrency']);
        $this->assertGreaterThan(0, (float) $product['offers']['price']);
        $this->assertSame(['Person'], $this->types($this->get('/d/jana-novakova')->assertOk()));
        $this->assertEqualsCanonicalizing(['CreativeWork', 'BreadcrumbList'], $this->types($this->get('/model/'.$inspiration->slug)->assertOk()));
        $article = $this->get('/blog/'.$post->slug)->assertOk();
        $this->assertEqualsCanonicalizing(['Article', 'BreadcrumbList'], $this->types($article));
        $this->assertSame('Jak na to', collect($this->structured($article))->firstWhere('@type', 'Article')['headline']);
        $this->assertSame(['ItemList'], $this->types($this->get('/blog')->assertOk()));
        $faq = $this->get('/faq')->assertOk();
        $this->assertSame(['FAQPage'], $this->types($faq));
        $this->assertGreaterThanOrEqual(10, count($this->structured($faq)[0]['mainEntity']));
        // a text can never close the script: "<" is escaped
        $post->update(['title' => ['cs' => 'Past </script><b>x']]);
        $this->assertSame('Past </script><b>x', collect($this->structured($this->get('/blog/'.$post->slug)))->firstWhere('@type', 'Article')['headline']);
    }

    public function test_sitemaps_list_a_page_only_in_the_languages_it_exists_in(): void
    {
        $card = $this->printableCard();
        CatalogModel::create(['legacy_id' => 1, 'slug' => 'only-czech', 'title' => 'Only Czech', 'description' => ['cs' => 'Jen česky.'], 'source' => 'printables', 'visible' => true]);
        CatalogModel::create(['legacy_id' => 2, 'slug' => 'translated', 'title' => 'Translated', 'description' => ['cs' => 'Česky.', 'en' => 'In English.'], 'source' => 'printables', 'visible' => true]);
        CatalogModel::create(['legacy_id' => 3, 'slug' => 'hidden', 'title' => 'Hidden', 'description' => ['cs' => 'Skrytý.'], 'source' => 'printables', 'visible' => false]);
        Post::create(['slug' => 'cesky-clanek', 'title' => ['cs' => 'Český článek'], 'body' => ['cs' => 'Text.'], 'published_at' => now()->subDay()]);
        Post::create(['slug' => 'both', 'title' => ['cs' => 'Oba', 'en' => 'Both'], 'body' => ['cs' => 'Text.', 'en' => 'Text.'], 'published_at' => now()->subDay()]);
        Post::create(['slug' => 'draft', 'title' => ['cs' => 'Koncept'], 'body' => ['cs' => 'Text.'], 'published_at' => null]);

        $this->artisan('matplace:sitemap')->assertSuccessful();
        $read = fn (string $name) => (string) @file_get_contents($this->sitemaps.'/'.$name);

        $index = $read('sitemap.xml');
        foreach (['sitemap-tools-cs.xml', 'sitemap-tools-en.xml', 'sitemap-tools-es.xml', 'sitemap-models-cs.xml', 'sitemap-models-en.xml', 'sitemap-designers-es.xml', 'sitemap-catalog-1.xml', 'sitemap-blog-cs.xml', 'sitemap-blog-en.xml'] as $file) {
            $this->assertStringContainsString('<loc>'.url('/'.$file).'</loc>', $index);
            $this->assertNotFalse(simplexml_load_string($read($file)), "{$file} is valid XML");
        }
        // no Spanish article and no Spanish text of the card: no such files at all
        $this->assertStringNotContainsString('sitemap-blog-es.xml', $index);
        $this->assertStringNotContainsString('sitemap-models-es.xml', $index);
        $this->assertFileDoesNotExist($this->sitemaps.'/sitemap-blog-es.xml');

        $this->assertStringContainsString('<loc>'.url('/en/tools/sign').'</loc>', $read('sitemap-tools-en.xml'));
        $this->assertStringContainsString('<loc>'.url('/tools/sign').'</loc>', $read('sitemap-tools-cs.xml'));
        $this->assertStringContainsString('hreflang="es" href="'.url('/es/tools/sign').'"', $read('sitemap-tools-en.xml'));
        $this->assertStringContainsString('<loc>'.url('/en/faq').'</loc>', $read('sitemap-tools-en.xml'));
        $this->assertStringNotContainsString('/account', $read('sitemap-tools-cs.xml'));

        $this->assertStringContainsString('<loc>'.url('/models/'.$card->slug).'</loc><lastmod>'.$card->updated_at->toDateString().'</lastmod>', $read('sitemap-models-cs.xml'));
        $this->assertStringContainsString('<loc>'.url('/en/models/'.$card->slug).'</loc>', $read('sitemap-models-en.xml'));
        $this->assertStringContainsString('<loc>'.url('/es/d/jana-novakova').'</loc>', $read('sitemap-designers-es.xml'));

        $catalog = $read('sitemap-catalog-1.xml');
        $this->assertStringContainsString('<loc>'.url('/model/only-czech').'</loc>', $catalog);
        $this->assertStringNotContainsString(url('/en/model/only-czech'), $catalog);
        $this->assertStringContainsString('<loc>'.url('/en/model/translated').'</loc>', $catalog);
        $this->assertStringNotContainsString(url('/es/model/translated'), $catalog);
        $this->assertStringNotContainsString('hidden', $catalog);

        $this->assertStringContainsString('<loc>'.url('/blog/cesky-clanek').'</loc>', $read('sitemap-blog-cs.xml'));
        $this->assertStringContainsString('<loc>'.url('/en/blog/both').'</loc>', $read('sitemap-blog-en.xml'));
        $this->assertStringNotContainsString('cesky-clanek', $read('sitemap-blog-en.xml'));
        $this->assertStringNotContainsString('draft', $read('sitemap-blog-cs.xml'));

        // served by the site; robots.txt points to the index
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->get('/sitemap-tools-en.xml')->assertOk();
        $this->get('/sitemap-blog-es.xml')->assertNotFound();
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $robots);
        $this->assertStringContainsString('Disallow: /admin/', $robots);
        $this->assertStringNotContainsString("Disallow: /\n", $robots);

        // a staging copy keeps out of search engines altogether
        config(['seo.indexable' => false]);
        $this->assertStringContainsString("Disallow: /\n", $this->get('/robots.txt')->getContent());
        $this->get('/tools/sign')->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertDontSee('rel="canonical"', false);
        config(['seo.indexable' => true, 'seo.google_site_verification' => 'abc123']);
        $this->get('/tools/sign')->assertDontSee('noindex')->assertSee('<meta name="google-site-verification" content="abc123">', false);
        $this->get('/account')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/account')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_addresses_of_the_old_site_lead_where_their_content_went(): void
    {
        config(['legacy.host' => 'https://legacy.matplace.com']);
        $cases = [
            // pages that moved: for good
            '/kalkulator-ceny' => [url('/'), 301],
            '/materialy' => [url('/materials'), 301],
            '/o-nas' => [url('/about'), 301],
            '/kontakt' => [url('/contact'), 301],
            '/reklamace' => [url('/complaints'), 301],
            '/podminky-pouzivani' => [url('/terms'), 301],
            '/obchodni-podminky' => [url('/business-terms'), 301],
            '/zasady-ochrany-osobnich-udaju' => [url('/privacy'), 301],
            '/katalog' => [url('/model'), 301],
            '/stahnout/phone-stand' => [url('/model/phone-stand'), 301],
            '/koupit-model/phone-stand' => [url('/model/phone-stand'), 301],
            // accounts, orders and printers of the old site: there, and not for good
            '/tiskarny' => ['https://legacy.matplace.com/tiskarny', 302],
            '/tiskar/dashboard' => ['https://legacy.matplace.com/tiskar/dashboard', 302],
            '/designer/modely' => ['https://legacy.matplace.com/designer/modely', 302],
            '/ucet/objednavky?stav=nove' => ['https://legacy.matplace.com/ucet/objednavky?stav=nove', 302],
            '/moje-poptavka/abc123' => ['https://legacy.matplace.com/moje-poptavka/abc123', 302],
            '/poptat' => ['https://legacy.matplace.com/poptat', 302],
            '/objednat/X1/hotovo' => ['https://legacy.matplace.com/objednat/X1/hotovo', 302],
            '/kosik' => ['https://legacy.matplace.com/kosik', 302],
            '/pokladna' => ['https://legacy.matplace.com/pokladna', 302],
            '/blog-img/20260423_212136_5717aa99.png' => ['https://legacy.matplace.com/blog-img/20260423_212136_5717aa99.png', 302],
        ];
        foreach ($cases as $path => [$target, $status]) {
            $response = $this->get($path);
            $this->assertSame($status, $response->getStatusCode(), $path);
            $location = (string) $response->headers->get('Location');
            $this->assertSame($target, str_starts_with($location, '/') ? url($location) : $location, $path);
        }
        $this->assertNull(LegacyRedirects::targetFor('/model/phone-stand'), 'the catalogue keeps its addresses');
        $this->assertNull(LegacyRedirects::targetFor('/blog/kolik-stoji-3d-tisk'));
        $this->assertNull(LegacyRedirects::targetFor('/tools/sign'));
        // served here: not redirected
        $this->get('/faq')->assertOk();
        $this->get('/cookies')->assertOk();
        // a form posted to an old address is not replayed elsewhere, and an address nobody knows is honestly not found
        $this->post('/kontakt', ['email' => 'a@b.cz'])->assertNotFound();
        $this->get('/tohle-nikdy-nebylo')->assertNotFound();
        // the old page in English keeps the visitor's language when it has a twin
        $this->get('/en/models')->assertOk();
    }

    public function test_our_statistics_need_no_consent_and_third_parties_wait_for_it(): void
    {
        Notification::fake();
        config(['services.ga4.id' => 'G-TEST123', 'services.meta.pixel_id' => '987654321']);

        // nothing chosen yet: the bar is there, no third-party script is, yet our own event is recorded
        $page = $this->get('/')->assertOk();
        $page->assertSee('id="consent" class=" fixed', false)->assertSee(__('site.consent.only_necessary'))->assertSee(__('site.consent.all'))
            ->assertDontSee('googletagmanager.com', false)->assertDontSee('connect.facebook.net', false);
        $search = $this->postJson('/api/search', ['q' => 'stojánek'])->assertOk();
        $this->assertSame(1, Event::where('type', 'search')->count());
        $this->assertNull(Event::where('type', 'search')->first()->meta['q'] ?? null, 'the words typed are not kept in the statistics');
        // an answer to a script tells the page what was recorded, for the page to pass on if it may
        $this->assertSame([['type' => 'search', 'meta' => ['results' => 0]]], json_decode((string) $search->headers->get(ForwardEvents::HEADER), true));

        // an event of a request that ends in a redirect waits for the page the browser lands on, once
        $this->post('/register', ['name' => 'Eva', 'email' => 'eva@example.com', 'password' => 'tajneheslo1', 'terms' => 1])->assertRedirect();
        $this->assertSame(1, Event::where('type', 'register')->count());
        $landing = $this->get('/account')->assertOk();
        $this->assertStringContainsString('register', $this->measure($landing)['events'][0]['type'] ?? '');
        $this->assertSame([], $this->measure($this->get('/account'))['events']);
        $this->post('/logout');

        // analytics allowed, marketing refused: Google's script is on the page, the bar is gone
        $allowed = $this->withUnencryptedCookie(Consent::COOKIE, 'a1m0')->get('/')->assertOk();
        $allowed->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', false)->assertSee('id="consent" class="hidden fixed', false);
        $this->assertSame(['analytics' => true, 'marketing' => false], $this->measure($allowed)['consent']);
        // only the necessary: nothing of a third party
        $refused = $this->withUnencryptedCookie(Consent::COOKIE, 'a0m0')->get('/')->assertOk();
        $refused->assertDontSee('googletagmanager.com', false);
        $this->assertSame(['analytics' => false, 'marketing' => false], $this->measure($refused)['consent']);
        // a cookie somebody made up counts as no choice
        $this->withUnencryptedCookie(Consent::COOKIE, 'yes')->get('/')->assertDontSee('googletagmanager.com', false)->assertSee('id="consent" class=" fixed', false);

        // without the ids nothing is offered to load at all
        config(['services.ga4.id' => null]);
        $this->withUnencryptedCookie(Consent::COOKIE, 'a1m1')->get('/')->assertDontSee('googletagmanager.com', false);
    }

    /** window.MP_MEASURE of a page. */
    private function measure(TestResponse $page): array
    {
        $this->assertSame(1, preg_match("#window\.MP_MEASURE = JSON\.parse\('(.*?)'\);#s", $page->getContent(), $m));

        return json_decode(json_decode('"'.$m[1].'"'), true);
    }

    public function test_link_previews_are_pictures_of_1200_by_630(): void
    {
        $card = $this->printableCard();
        Post::create(['slug' => 'cesky-clanek', 'title' => ['cs' => 'Kolik stojí 3D tisk na zakázku'], 'body' => ['cs' => 'Text.'], 'published_at' => now()->subDay()]);

        foreach (['/og/site/home.png', '/og/en/site/home.png', '/og/tool/vase.png', '/og/es/tool/phone-stand.png', '/og/article/cesky-clanek.png', '/og/model/'.$card->slug.'.png', '/og/en/model/'.$card->slug.'.png', '/og/designer/jana-novakova.png'] as $address) {
            $picture = $this->get($address)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Robots-Tag', 'noindex');
            $this->assertSame([1200, 630], array_slice((array) getimagesize($picture->baseResponse->getFile()->getPathname()), 0, 2), $address);
        }
        // a page that does not exist in a language has no picture in it either
        $this->get('/og/en/article/cesky-clanek.png')->assertNotFound();
        $this->get('/og/es/model/'.$card->slug.'.png')->assertNotFound();
        $this->get('/og/tool/perpetuum-mobile.png')->assertNotFound();
        $this->get('/og/article/nothing.png')->assertNotFound();

        // the pages name them
        $this->get('/models/'.$card->slug)->assertSee('<meta property="og:image" content="'.url('/og/model/'.$card->slug.'.png').'">', false)->assertSee('<meta property="og:type" content="product">', false);
        $this->get('/blog/cesky-clanek')->assertSee('<meta property="og:image" content="'.url('/og/article/cesky-clanek.png').'">', false)->assertSee('<meta property="og:type" content="article">', false);
        $this->get('/en/models')->assertSee('<meta property="og:image" content="'.url('/og/en/site/home.png').'">', false)->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_the_old_blog_is_carried_over_with_its_addresses_and_only_in_czech(): void
    {
        // an "old site" database with three articles (one of them a draft) and a folder with its pictures
        $file = sys_get_temp_dir().'/mp_legacy_blog_'.uniqid().'.sqlite';
        touch($file);
        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => false]]);
        Schema::connection('legacy_test')->create('blog_posts', function (Blueprint $t) {
            $t->increments('id');
            $t->string('slug');
            $t->string('title');
            $t->text('perex')->nullable();
            $t->text('content');
            $t->string('cover_image')->nullable();
            $t->string('author')->nullable();
            $t->boolean('published')->default(1);
            $t->dateTime('published_at')->nullable();
            $t->timestamps();
        });
        $html = '<section class="lp" style="color:red"><h1 style="font-size:38px">Kolik stojí 3D tisk</h1><div class="hero" onclick="alert(1)"><p style="margin:0">Cena závisí na <strong>velikosti</strong>.</p>'
            .'<a href="javascript:alert(1)">klik</a> <a href="https://example.com/x">ven</a> <a href="/katalog">katalog</a></div>'
            .'<script>alert("x")</script><style>.lp{}</style><img src="/blog-img/a.jpg" style="max-width:100%"><img src="data:image/png;base64,AAAA"><h2 class="t">Materiály</h2><ul><li>PLA</li></ul></section>';
        $rows = [
            ['slug' => 'kolik-stoji-3d-tisk', 'title' => 'Kolik stojí 3D tisk?', 'perex' => 'Přehled cen.', 'content' => $html, 'cover_image' => '/blog-img/a.jpg', 'author' => 'matplace', 'published' => 1, 'published_at' => '2026-04-22 22:34:00'],
            ['slug' => 'pla-vs-petg', 'title' => 'PLA vs. PETG', 'perex' => null, 'content' => '<p>Dva materiály.</p>', 'cover_image' => 'https://images.example.com/x.png', 'author' => 'redakce', 'published' => 1, 'published_at' => '2026-04-23 17:42:14'],
            ['slug' => 'rozepsany', 'title' => 'Rozepsaný', 'perex' => null, 'content' => '<p>Ještě ne.</p>', 'cover_image' => null, 'author' => 'matplace', 'published' => 0, 'published_at' => null],
        ];
        foreach ($rows as $row) {
            DB::connection('legacy_test')->table('blog_posts')->insert($row + ['created_at' => '2026-04-20 10:00:00', 'updated_at' => '2026-04-20 10:00:00']);
        }
        $images = sys_get_temp_dir().'/mp_blogimg_'.uniqid();
        mkdir($images);
        imagejpeg(imagecreatetruecolor(60, 40), $images.'/a.jpg');
        $run = fn (array $options = []) => $this->artisan('matplace:import-blog', ['--connection' => 'legacy_test', '--images' => $images, '--assets' => 'https://legacy.matplace.com'] + $options)->assertSuccessful();

        $run(['--dry-run' => true]);
        $this->assertSame(0, Post::count(), 'a dry run writes nothing');
        $run();
        $run();
        $this->assertSame(['kolik-stoji-3d-tisk', 'pla-vs-petg'], Post::orderBy('legacy_id')->pluck('slug')->all(), 'the same addresses, published ones only, nothing doubled');

        $post = Post::where('slug', 'kolik-stoji-3d-tisk')->firstOrFail();
        $body = $post->body['cs'];
        foreach (['style=', 'class=', 'onclick', '<script', '<style', 'alert(', '<h1', 'javascript:', 'data:image', '<section', '<div'] as $gone) {
            $this->assertStringNotContainsString($gone, $body, "the old site's {$gone} must not survive");
        }
        $this->assertStringContainsString('<strong>velikosti</strong>', $body);
        $this->assertStringContainsString('<h2>Materiály</h2>', $body);
        $this->assertStringContainsString('<li>PLA</li>', $body);
        $this->assertStringContainsString('<a href="https://example.com/x" rel="nofollow noopener" target="_blank">ven</a>', $body);
        $this->assertStringContainsString('<a href="/katalog">katalog</a>', $body);
        $this->assertStringContainsString('src="'.Storage::disk('public')->url('blog/a.jpg').'"', $body);
        Storage::disk('public')->assertExists('blog/a.jpg');
        $this->assertSame(['blog/a.jpg', 'html', 'matplace', '2026-04-22'], [$post->cover_path, $post->format, $post->author_name, $post->published_at->toDateString()]);
        $this->assertNull(Post::where('slug', 'pla-vs-petg')->first()->cover_path, 'a cover kept on somebody else\'s server is not taken over');

        // the pages: Czech only, the other languages say so and offer the Czech one
        $this->get('/blog')->assertOk()->assertSee('Kolik stojí 3D tisk?')->assertSee('PLA vs. PETG')->assertSee('Přehled cen.')->assertDontSee('Rozepsaný');
        $this->get('/blog/kolik-stoji-3d-tisk')->assertOk()->assertSee('<h1', false)->assertSee('Cena závisí na', false)->assertSee('<h2>Materiály</h2>', false)
            ->assertDontSee('<link rel="alternate" hreflang="en"', false)->assertSee('<link rel="canonical" href="'.url('/blog/kolik-stoji-3d-tisk').'">', false);
        $this->get('/en/blog/kolik-stoji-3d-tisk')->assertNotFound()->assertSee('id="czech-version" href="'.url('/blog/kolik-stoji-3d-tisk').'"', false);
        $this->get('/en/blog')->assertNotFound();
        $this->get('/blog/rozepsany')->assertNotFound();
        $this->get('/')->assertSee(route('blog.index'), false);
        $this->get('/en')->assertDontSee('/en/blog', false);

        // a translation added later opens the article in that language, with its twin named
        $post->update(['title' => $post->title + ['en' => 'What does 3D printing cost?'], 'body' => $post->body + ['en' => '<p>The price depends on size.</p>']]);
        $this->get('/en/blog/kolik-stoji-3d-tisk')->assertOk()->assertSee('What does 3D printing cost?')->assertSee('hreflang="cs" href="'.url('/blog/kolik-stoji-3d-tisk').'"', false);
        $this->get('/en/blog')->assertOk()->assertSee('What does 3D printing cost?')->assertDontSee('PLA vs. PETG');
        $this->get('/es/blog/kolik-stoji-3d-tisk')->assertNotFound();

        // an article written here is Markdown; raw HTML in it is not printed, a draft is seen only by the people who write
        $draft = Post::create(['slug' => 'novy', 'title' => ['cs' => 'Nový článek'], 'body' => ['cs' => "Úvod.\n\n## Druhý nadpis\n\n<script>alert(1)</script>\n\n- bod"], 'published_at' => null]);
        $this->assertStringContainsString('<h2>Druhý nadpis</h2>', $draft->html('cs'));
        $this->assertStringNotContainsString('<script', $draft->html('cs'));
        $this->get('/blog/novy')->assertNotFound();
        $admin = User::factory()->create();
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin']);
        $this->actingAs($admin)->get('/blog/novy')->assertOk()->assertSee(__('site.blog.draft'))->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // the cleaner on its own: text survives, markup of the layout does not
        $this->assertSame('<p>Jen text</p>', HtmlCleaner::clean('<div style="x">Jen text</div>'));
        $this->assertSame('', HtmlCleaner::clean('<script>x</script>'));
    }

    public function test_static_pages_and_materials_exist_in_three_languages(): void
    {
        $this->seed(FarmSeeder::class);
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            foreach (PageController::PAGES as $key => $path) {
                $title = __('pages.'.$key.'.title', [], $locale);
                $this->assertNotSame('pages.'.$key.'.title', $title, "{$key} has no text in {$locale}");
                $this->get($prefix.'/'.$path)->assertOk()->assertSee($title)->assertSee('hreflang="es"', false);
            }
            $this->get($prefix.'/materials')->assertOk()->assertSee(__('site.materials.title', [], $locale))->assertSee(__('materials.PETG.label', [], $locale))->assertSee('95 °C');
            // the footer leads to them
            $this->get($prefix.'/tools')->assertSee(localized_route('pages.about', [], $locale), false)->assertSee(localized_route('pages.business_terms', [], $locale), false)->assertSee(__('site.consent.change', [], $locale));
        }
        // the three languages of the legal pages have the same shape
        foreach (array_keys(PageController::PAGES) as $key) {
            $shape = fn (string $l) => array_map(fn ($s) => [count($s['p'] ?? []), count($s['li'] ?? [])], (array) (__('pages.'.$key, [], $l)['sections'] ?? [])) + ['items' => count((array) (__('pages.'.$key, [], $l)['items'] ?? []))];
            $this->assertSame($shape('cs'), $shape('en'), $key);
            $this->assertSame($shape('cs'), $shape('es'), $key);
        }
        // nothing about a printers' marketplace is left in them
        $this->assertDoesNotMatchRegularExpression('/tiskař/iu', json_encode(__('pages', [], 'cs'), JSON_UNESCAPED_UNICODE));
        $this->get('/cookies')->assertSee('data-consent-open', false);
    }
}
