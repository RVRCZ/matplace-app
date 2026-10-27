<?php

namespace Tests\Feature;

use App\Domain\Catalog\ModelPricing;
use App\Models\Calculation;
use App\Models\CatalogModel;
use App\Models\ModelFile;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The value an inspiration page gets from a slice of its own file (Roman, 27 Oct 2026; docs/AN.md §13): the print time
 * and the price in three materials, a Product with offers for search engines where the licence allows a sale, and the
 * command that slices the catalogue in batches at night, the indexed pages first.
 */
class CatalogValueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        $this->seed(FarmSeeder::class);   // PLA, PLA+ and PETG loaded: prices can be named; ASA is priced in the cheapest kind
    }

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

    /** @return list<array<string, mixed>> every JSON-LD thing on the page */
    private function things(string $html): array
    {
        preg_match_all('~application/ld\+json"[^>]*>(.*?)</script>~s', $html, $m);

        return array_values(array_filter(array_map(fn (string $json) => json_decode($json, true), $m[1]), 'is_array'));
    }

    public function test_catalog_slice_takes_the_indexed_models_first_and_writes_the_slice_to_the_model(): void
    {
        $indexed = $this->model(['view_count' => 5, 'enriched_at' => now(), 'est_minutes' => 90, 'est_grams' => 30, 'model_file_id' => $this->fileOnDisk()->id]);
        $popular = $this->model(['view_count' => 500, 'model_file_id' => $this->fileOnDisk(20)->id]);
        $noFile = $this->model(['view_count' => 900, 'file_available' => true]);
        $this->assertTrue($indexed->isIndexable());
        $this->assertFalse($popular->isIndexable());

        $this->artisan('catalog:slice --dry-run')
            ->expectsOutputToContain('2 with a file we hold, 1 flagged file_available without a file')
            ->expectsOutputToContain('Candidates: 2 (1 indexable first)')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertNull($indexed->fresh()->sliced_at);

        $this->artisan('catalog:slice --limit=1 --sleep=0')->assertSuccessful();
        $indexed->refresh();
        $this->assertNotNull($indexed->sliced_at, 'the indexed page comes first although it is less visited');
        $this->assertNull($popular->fresh()->sliced_at);
        $this->assertGreaterThan(0, $indexed->slice_minutes);
        $this->assertGreaterThan(0, $indexed->slice_grams);
        $this->assertSame('PLA', $indexed->slice_material);
        $this->assertNull($indexed->slice_error);
        $calc = $indexed->sliceCalculation;
        $this->assertNotNull($calc);
        $this->assertSame(Calculation::STATUS_DONE, $calc->status);
        $this->assertSame($indexed->slice_minutes, (int) $calc->slicer['minutes']);
        $this->assertNull($calc->anonymous_session_id, 'the calculation belongs to nobody: its page is for every visitor');

        $this->artisan('catalog:slice --limit=5 --sleep=0')->expectsOutputToContain('Candidates: 1 (0 indexable first)')->assertSuccessful();
        $this->assertNotNull($popular->fresh()->sliced_at);
        $this->assertNull($noFile->fresh()->sliced_at, 'a flag without a file is nothing to slice');
        $this->artisan('catalog:slice --sleep=0')->expectsOutputToContain('Candidates: 0')->assertSuccessful();

        // a file that is gone: the error is written to the model and the run goes on
        $gone = $this->model(['model_file_id' => ModelFile::factory()->create()->id]);
        $this->artisan('catalog:slice --sleep=0')->expectsOutputToContain('failed: file_')->assertFailed();
        $this->assertNotNull($gone->fresh()->slice_error);
        $this->assertNull($gone->fresh()->sliced_at);
        // the slice counts as the price a page needs for the index
        $this->assertNotContains('price', $popular->fresh()->indexGaps());
        $this->assertContains('price', $gone->fresh()->indexGaps());
    }

    public function test_the_page_names_the_price_in_three_materials_and_the_time_and_offers_a_product_where_a_sale_is_allowed(): void
    {
        $sold = $this->model(['license' => 'cc_by', 'license_restricted' => false, 'model_file_id' => $this->fileOnDisk()->id, 'enriched_at' => now(),
            'description' => ['cs' => "Náš stojánek.\n\nJak ho tisknout.", 'en' => "Our stand.\n\nHow to print it.", 'original' => 'A simple stand for a phone.']]);
        $personal = $this->model(['license' => 'cc_by_nc', 'license_restricted' => true, 'model_file_id' => $this->fileOnDisk(20)->id]);
        $this->artisan('catalog:slice --sleep=0')->assertSuccessful();
        $sold->refresh();
        $personal->refresh();

        $html = (string) $this->get('/model/'.$sold->slug)->assertOk()->getContent();
        $this->assertStringContainsString('id="model-calc"', $html);
        foreach (['PLA', 'PETG', 'ASA'] as $code) {
            $this->assertStringContainsString('data-material="'.$code.'"', $html, $code);
        }
        $this->assertStringContainsString(__('models.inspiration.calc_title'), $html);
        $this->assertStringContainsString(__('models.inspiration.calc_open'), $html);
        $this->assertStringContainsString(url('/c/'.$sold->sliceCalculation->token), $html, 'the order goes through the calculation\'s own page');
        $this->assertStringContainsString('data-price', $html);
        $quote = app(ModelPricing::class)->quoteCatalog($sold);
        $this->assertSame('slice', $quote['basis']);
        $this->assertTrue($sold->isIndexable());

        // the price in three materials follows the slice: more grams of a denser material, the farm's list per material
        $sliced = app(ModelPricing::class)->sliced($sold);
        $this->assertSame(['PLA', 'PETG', 'ASA'], array_column($sliced['rows'], 'code'));
        $byCode = array_column($sliced['rows'], null, 'code');
        $this->assertGreaterThan($byCode['PLA']['grams'], $byCode['PETG']['grams']);
        $this->assertLessThan($byCode['PLA']['grams'], $byCode['ASA']['grams']);
        $this->assertGreaterThanOrEqual($byCode['PLA']['minutes'], $byCode['PETG']['minutes']);
        $this->assertSame('CZK', $sliced['currency']);
        $this->assertSame(min(array_column($sliced['rows'], 'total')), $sliced['low']);

        // Product with an AggregateOffer in crowns, beside the CreativeWork the page always had
        $product = collect($this->things($html))->first(fn (array $t) => ($t['@type'] ?? null) === 'Product');
        $this->assertNotNull($product);
        $this->assertSame($sold->title, $product['name']);
        $this->assertSame('CZK', $product['offers']['priceCurrency']);
        $this->assertSame(3, $product['offers']['offerCount']);
        $this->assertLessThanOrEqual((float) $product['offers']['highPrice'], (float) $product['offers']['lowPrice']);
        $this->assertSame('https://schema.org/InStock', $product['offers']['availability']);
        $this->assertSame(url('/').'#organization', $product['offers']['seller']['@id']);
        $this->assertNotNull(collect($this->things($html))->first(fn (array $t) => ($t['@type'] ?? null) === 'CreativeWork'));

        // in euros for a visitor who pays in euros
        $eur = (string) $this->withCookie('currency', 'EUR')->get('/en/model/'.$sold->slug)->assertOk()->getContent();
        $productEur = collect($this->things($eur))->first(fn (array $t) => ($t['@type'] ?? null) === 'Product');
        $this->assertSame('EUR', $productEur['offers']['priceCurrency']);
        $this->assertLessThan((float) $product['offers']['lowPrice'], (float) $productEur['offers']['lowPrice']);
        $this->assertStringContainsString('€', $eur);

        // a non-commercial licence: the prices and the time are shown, no order link, no Product
        $html = (string) $this->get('/model/'.$personal->slug)->assertOk()->getContent();
        $this->assertStringContainsString('id="model-calc"', $html);
        $this->assertStringNotContainsString(__('models.inspiration.calc_open'), $html);
        $this->assertStringNotContainsString('/c/'.$personal->sliceCalculation->token, $html);
        $this->assertNull(collect($this->things($html))->first(fn (array $t) => ($t['@type'] ?? null) === 'Product'));
        $this->assertStringContainsString('data-use="personal"', $html);
    }
}
