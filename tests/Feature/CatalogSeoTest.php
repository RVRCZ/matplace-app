<?php

namespace Tests\Feature;

use App\Domain\Catalog\CatalogEnricher;
use App\Domain\Catalog\ModelPricing;
use App\Jobs\EnrichCatalogModel;
use App\Models\AiCall;
use App\Models\CatalogModel;
use App\Models\FarmPrinterSlot;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The inspiration catalogue and search engines (Roman, 18 Oct 2026): a page is indexed only when it has something
 * of its own – a text written here, a real price, the file – and the sitemap lists exactly those; every other page
 * stays reachable with "noindex, follow". `catalog:enrich` writes the texts with Gemini (faked here) and keeps the
 * source's text folded under ours.
 */
class CatalogSeoTest extends TestCase
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
        $this->seed(FarmSeeder::class);   // a kind loaded on the farm: prices can be named
        $this->sitemaps = sys_get_temp_dir().'/mp_cseo_'.uniqid();
        config(['seo.sitemap_dir' => $this->sitemaps, 'engines.search' => ['local'], 'ai.gemini.api_key' => 'test-key', 'ai.gemini.text_model' => 'gemini-2.5-flash']);
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
            'slug' => 'phone-stand-'.$n, 'title' => 'Phone stand '.$n, 'description' => ['en' => 'A simple stand for a phone.', 'cs' => 'Jednoduchý stojánek na telefon.'],
            'source_locale' => 'en', 'source' => 'printables', 'external_url' => 'https://www.printables.com/model/'.$n, 'license' => 'cc_by', 'license_restricted' => false,
            'author_name' => 'jana', 'visible' => true, 'view_count' => 100 - $n, 'max_mm' => 120,
        ]);
    }

    /** What makes a page worth indexing besides the text: the old site's slicer figures and the file. */
    private const READY = ['file_available' => true, 'est_minutes' => 90, 'est_grams' => 30];

    private function fakeGemini(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => function (Request $request) {
            $wanted = array_keys((array) data_get($request->data(), 'generationConfig.responseSchema.properties'));
            $answer = [];
            foreach ($wanted as $locale) {
                $answer[$locale] = ['p1' => "[{$locale}] Stojánek na telefon na stůl, drží ho nakloněný při nabíjení.", 'p2' => "[{$locale}] Tiskne se naplocho bez podpěr; doporučujeme PLA+ matný."];
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($answer, JSON_UNESCAPED_UNICODE)]]]]], 'usageMetadata' => ['promptTokenCount' => 900, 'candidatesTokenCount' => 300, 'thoughtsTokenCount' => 0], 'modelVersion' => 'gemini-2.5-flash']);
        }]);
    }

    public function test_a_page_without_a_text_of_ours_stays_reachable_but_is_not_for_search_engines(): void
    {
        $plain = $this->model();
        $this->assertFalse($plain->isIndexable());
        $this->assertSame(['enriched', 'price', 'file'], $plain->indexGaps());

        $page = $this->get('/model/'.$plain->slug)->assertOk()->assertSee($plain->title)->assertSee('Jednoduchý stojánek na telefon.')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)->assertDontSee('rel="canonical"', false)
            ->assertSee('data-cta="print"', false)->assertDontSee('data-our-text', false)->assertDontSee('data-price', false);
        // the language twins stay: the page exists in English too and people switch between them
        $page->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/model/'.$plain->slug).'">', false);
        // nothing is hidden from visitors
        $this->get('/model')->assertOk()->assertSee($plain->title);

        $this->artisan('matplace:sitemap')->expectsOutputToContain('0 indexable of 1 shown pages, 1 noindex')->assertSuccessful();
        $this->assertFileDoesNotExist($this->sitemaps.'/sitemap-catalog-1.xml', 'no page of the catalogue is listed');
        $this->assertStringNotContainsString('sitemap-catalog', (string) file_get_contents($this->sitemaps.'/sitemap.xml'));
    }

    public function test_an_enriched_model_with_a_price_and_the_file_is_indexed_listed_and_shows_our_text_first(): void
    {
        $plain = $this->model();
        $ours = $this->model(self::READY + [
            'enriched_at' => now()->subDay(), 'enriched_price_czk' => 150,
            'description' => ['cs' => "Náš stojánek: co to je.\n\nJak ho tisknout.", 'en' => "Our stand: what it is.\n\nHow to print it.", 'original' => 'A simple stand for a phone.'],
        ]);
        $this->assertTrue($ours->isIndexable());
        $this->assertSame([], $ours->indexGaps());

        $cs = $this->get('/model/'.$ours->slug)->assertOk();
        $cs->assertDontSee('noindex')->assertSee('<link rel="canonical" href="'.url('/model/'.$ours->slug).'">', false)
            ->assertSee('data-our-text', false)->assertSee('Náš stojánek: co to je.')
            ->assertSeeInOrder(['data-our-text', 'Náš stojánek', 'data-original-text', __('models.inspiration.original', ['language' => 'English']), 'A simple stand for a phone.'], false)
            ->assertSee('data-price', false)->assertSee(__('models.inspiration.price'))->assertSee(__('models.inspiration.size_value', ['mm' => 120]))->assertSee('1 h 30 min');
        $this->assertStringContainsString('<details', $cs->getContent(), 'the source\'s text is folded');
        $en = $this->get('/en/model/'.$ours->slug)->assertOk();
        $en->assertSee('Our stand: what it is.')->assertSee('data-original-text', false)->assertDontSee('noindex')->assertDontSee('Náš stojánek');
        // the price in the dl is the same figure the enrichment wrote (the cheapest loaded kind, one piece)
        $this->assertSame(150.0, $ours->enriched_price_czk);

        $this->artisan('matplace:sitemap')->expectsOutputToContain('1 indexable of 2 shown pages, 1 noindex')->assertSuccessful();
        $catalog = (string) file_get_contents($this->sitemaps.'/sitemap-catalog-1.xml');
        $this->assertStringContainsString('<loc>'.url('/model/'.$ours->slug).'</loc><lastmod>'.now()->subDay()->toDateString().'</lastmod>', $catalog);
        $this->assertStringContainsString('<loc>'.url('/en/model/'.$ours->slug).'</loc>', $catalog);
        $this->assertStringNotContainsString($plain->slug, $catalog);

        // the rule is the admin's to loosen: a text alone, when config says so
        config(['catalog.index_requires' => ['enriched']]);
        $textOnly = $this->model(['enriched_at' => now()]);
        $this->assertTrue($textOnly->isIndexable());
        $this->assertSame([], $textOnly->indexGaps());
        $this->assertSame(2, CatalogModel::indexable()->count());
        config(['catalog.index_requires' => ['enriched', 'price', 'file']]);
        $this->assertSame(['price', 'file'], $textOnly->indexGaps(), 'the gaps follow the config');
        $this->assertSame(1, CatalogModel::indexable()->count());
        // nothing loaded on the farm: no price can be named, nothing is indexable
        FarmPrinterSlot::query()->update(['enabled' => false]);
        $this->app->forgetInstance(ModelPricing::class);
        $this->assertSame(0, CatalogModel::indexable()->count());
        $this->assertContains('price', $ours->fresh()->indexGaps());
    }

    public function test_catalog_enrich_runs_dry_without_a_call_and_for_real_writes_our_text_over_the_source_s(): void
    {
        $this->fakeGemini();
        $first = $this->model(self::READY + ['view_count' => 500]);
        $second = $this->model(self::READY + ['view_count' => 400, 'description' => ['en' => 'Second stand.']]);   // English page only: no Czech text yet
        $noFile = $this->model(['view_count' => 900]);

        $this->artisan('catalog:enrich', ['--dry-run' => true])
            ->expectsOutputToContain('3 shown, 0 with a text of ours, 0 indexable now (index needs: enriched, price, file); 2 could be indexed once they have a text.')
            ->expectsOutputToContain('Candidates: 2')->expectsOutputToContain('Estimate: 2 calls')->expectsOutputToContain('Dry run: nothing called, nothing written.')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame(0, AiCall::count());
        $this->assertNull($first->fresh()->enriched_at);

        $this->artisan('catalog:enrich', ['--top' => 5])->expectsOutputToContain('[cs] [cs] Stojánek na telefon')->expectsOutputToContain('Written: 2, failed: 0')->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/models/gemini-2.5-flash:generateContent') && $r->hasHeader('x-goog-api-key', 'test-key')
            && data_get($r->data(), 'generationConfig.responseMimeType') === 'application/json' && str_contains((string) data_get($r->data(), 'contents.0.parts.0.text'), 'Title: Phone stand'));
        $first->refresh();
        $this->assertNotNull($first->enriched_at);
        $this->assertSame("[cs] Stojánek na telefon na stůl, drží ho nakloněný při nabíjení.\n\n[cs] Tiskne se naplocho bez podpěr; doporučujeme PLA+ matný.", $first->description['cs']);
        $this->assertStringStartsWith('[en] ', $first->description['en'], 'the English page gets a text of ours too');
        $this->assertSame(['text' => 'A simple stand for a phone.', 'locale' => 'en'], $first->original(), 'the source\'s text is kept');
        $this->assertGreaterThan(0, $first->enriched_price_czk);
        $this->assertTrue($first->isIndexable());
        // a model with an English text alone has a Czech page too (the old addresses): both get a text of ours, Spanish none
        $this->assertSame(['cs', 'en'], array_values(array_intersect(['cs', 'en', 'es'], array_keys($second->fresh()->description))));
        $this->assertSame('Second stand.', $second->fresh()->original()['text']);
        $this->get('/es/model/'.$second->slug)->assertStatus(301)->assertRedirect('/model/'.$second->slug);   // no Spanish text: on to the Czech page (docs/AN.md §16)
        // every call is booked with the Gemini prices
        $this->assertSame(2, AiCall::where('kind', 'enrich')->where('engine', 'gemini-2.5-flash')->count());
        $this->assertEqualsWithDelta((900 * 0.30 + 300 * 2.50) / 1_000_000 * config('ai.prices.usd_czk'), AiCall::first()->cost_czk, 0.001);
        // the one without a file waits unless asked for; asked again, the enriched ones are left alone
        $this->assertNull($noFile->fresh()->enriched_at);
        $this->artisan('catalog:enrich', ['--dry-run' => true, '--any' => true])->expectsOutputToContain('Candidates: 1')->assertSuccessful();
        $this->artisan('catalog:enrich', ['--dry-run' => true])->expectsOutputToContain('Candidates: 0')->assertSuccessful();
        // the original is written once: a second text over the first keeps the source's words, not ours
        $this->artisan('catalog:enrich', ['--ids' => (string) $first->id, '--force' => true])->assertSuccessful();
        $this->assertSame('A simple stand for a phone.', $first->fresh()->original()['text']);
        $this->assertSame(3, AiCall::count());
    }

    public function test_the_admin_sees_who_is_enriched_and_indexed_and_queues_the_ticked_ones(): void
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $plain = $this->model();
        $ours = $this->model(self::READY + ['enriched_at' => now(), 'enriched_price_czk' => 150]);

        $list = $this->actingAs($admin)->get('/admin/catalog')->assertOk();
        $list->assertSee('id="count-enriched"', false)->assertSee('obohacených 1')->assertSee('indexovaných 1 z 2')->assertSee('chybí: text, cena, soubor')->assertSee('Obohatit vybrané (Gemini)')
            ->assertSee('data-model="'.$ours->id.'" data-index="yes"', false)->assertSee('data-model="'.$plain->id.'" data-index="no"', false);
        $this->actingAs($admin)->get('/admin/catalog?index=1')->assertOk()->assertSee('data-model="'.$ours->id.'"', false)->assertDontSee('data-model="'.$plain->id.'"', false);
        $this->actingAs($admin)->get('/admin/catalog?enriched=0')->assertOk()->assertSee('data-model="'.$plain->id.'"', false)->assertDontSee('data-model="'.$ours->id.'"', false);
        $this->actingAs($admin)->get('/admin/catalog/models/'.$plain->id)->assertOk()->assertSee('Napsat náš text (Gemini)')->assertSee('index: ne (chybí text, cena, soubor)');

        Queue::fake();
        $this->actingAs($admin)->from('/admin/catalog')->post('/admin/catalog/enrich', ['ids' => [$plain->id, $ours->id, 999999]])->assertRedirect('/admin/catalog')->assertSessionHas('status');
        Queue::assertPushed(EnrichCatalogModel::class, 2);
        Queue::assertPushedOn('ai', EnrichCatalogModel::class);

        // the job itself: the plain one gets its text, the enriched one is left alone without "force"
        $this->fakeGemini();
        (new EnrichCatalogModel($plain->id))->handle(app(CatalogEnricher::class));
        (new EnrichCatalogModel($ours->id))->handle(app(CatalogEnricher::class));
        $this->assertNotNull($plain->fresh()->enriched_at);
        Http::assertSentCount(1);
    }
}
