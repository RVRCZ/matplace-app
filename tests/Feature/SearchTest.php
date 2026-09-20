<?php

namespace Tests\Feature;

use App\Models\CatalogModel;
use App\Models\GenerationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Text and photo search: local catalogue + Printables (faked) + vision (faked), daily limits. */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.search' => ['local', 'printables', 'makerworld'], 'ai.anthropic.api_key' => 'test-key', 'ai.daily_limits.describe' => 2]);
        CatalogModel::create(['title' => 'Phone stand adjustable', 'keywords' => 'stojánek telefon', 'source' => 'printables', 'external_url' => 'https://www.printables.com/model/1-x', 'preview_url' => 'https://matplace.com/assets/thumbs/a.jpg', 'license' => 'cc_by']);
        CatalogModel::create(['title' => 'Remote control battery cover', 'keywords' => 'kryt baterie ovladač', 'source' => 'makerworld', 'external_url' => 'https://makerworld.com/en/models/7']);
        CatalogModel::create(['title' => 'Hidden model', 'active' => false]);
    }

    private function fakeExternal(): void
    {
        Http::fake([
            'api.printables.com/*' => Http::response(['data' => ['searchPrints2' => ['items' => [
                ['id' => '187125', 'slug' => 'phone-stand', 'name' => 'Phone Stand', 'image' => ['filePath' => 'media/p.jpg'], 'license' => ['name' => 'CC BY-NC'], 'user' => ['publicUsername' => 'PlatinumStars']],
            ]]]]),
            'makerworld.com/*' => Http::response('<html>Just a moment...</html>', 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_text_search_merges_local_and_printables(): void
    {
        $this->fakeExternal();
        $r = $this->postJson('/api/search', ['q' => 'phone stand']);
        $r->assertOk();
        $results = $r->json('results');
        $sources = array_column($results, 'source');
        $this->assertContains('local', $sources);
        $this->assertContains('printables', $sources);
        $this->assertNotContains('makerworld', $sources); // Cloudflare page → gracefully empty
        $titles = array_column($results, 'title');
        $this->assertContains('Phone stand adjustable', $titles);
        $this->assertContains('Phone Stand', $titles);
        $this->assertNotContains('Hidden model', $titles);
        $printables = collect($results)->firstWhere('source', 'printables');
        $this->assertSame('https://www.printables.com/model/187125-phone-stand', $printables['externalUrl']);
        $this->assertSame('https://media.printables.com/media/p.jpg', $printables['previewUrl']);
        $this->assertFalse($printables['fileAvailable']);
    }

    public function test_czech_keywords_hit_local_catalogue(): void
    {
        $this->fakeExternal();
        $r = $this->postJson('/api/search', ['q' => 'kryt ovladač']);
        $this->assertContains('Remote control battery cover', array_column($r->json('results'), 'title'));
        $this->postJson('/api/search', ['q' => 'x'])->assertStatus(422);
    }

    public function test_photo_describe_returns_description_range_and_results(): void
    {
        $this->fakeExternal();
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                'name' => 'Kryt baterie dálkového ovladače', 'name_en' => 'Remote control battery cover', 'category' => 'spare_part',
                'queries' => ['remote control battery cover', 'battery lid remote'], 'bbox_mm' => ['x' => 45, 'y' => 30, 'z' => 5],
                'size_known' => false, 'material' => 'PETG', 'printable' => true, 'notes' => 'Plastový kryt s klipem.',
            ])]]]),
        ]);
        Storage::fake('local');
        $photo = UploadedFile::fake()->image('cover.jpg', 640, 480);

        $r = $this->post('/api/describe', ['image' => $photo], ['Accept' => 'application/json']);
        $r->assertOk();
        $this->assertSame('Kryt baterie dálkového ovladače', $r->json('description.name'));
        $this->assertSame('PETG', $r->json('description.material'));
        $this->assertSame(45, $r->json('description.bbox_mm.x'));
        $this->assertGreaterThan(0, $r->json('range.price_min'));
        $this->assertGreaterThanOrEqual($r->json('range.price_min'), $r->json('range.price_max'));
        $this->assertContains('Remote control battery cover', array_column($r->json('results'), 'title'));
        $this->assertFalse($r->json('generator'));
        $this->assertSame(1, GenerationRequest::where('type', 'describe')->where('status', 'done')->count());

        // daily limit (2 in this test): third call is refused
        $this->post('/api/describe', ['image' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->post('/api/describe', ['image' => UploadedFile::fake()->image('c.jpg')], ['Accept' => 'application/json'])->assertStatus(429);
    }

    public function test_describe_without_key_is_503(): void
    {
        config(['ai.anthropic.api_key' => '']);
        Storage::fake('local');
        $this->post('/api/describe', ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertStatus(503);
    }

    public function test_catalogue_cards_are_named_after_the_site_they_open_and_dead_ends_are_hidden(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('', 403)]);
        \App\Models\CatalogModel::create(['title' => 'Dragon keychain A', 'keywords' => 'drak klicenka dragon', 'source' => 'printables', 'external_url' => 'https://www.printables.com/model/1-dragon', 'active' => true]);
        \App\Models\CatalogModel::create(['title' => 'Dragon keychain B', 'keywords' => 'drak klicenka dragon', 'source' => 'makerworld', 'external_url' => 'https://makerworld.com/en/models/2', 'active' => true]);
        \App\Models\CatalogModel::create(['title' => 'Dragon keychain C', 'keywords' => 'drak klicenka dragon', 'source' => 'drive', 'external_url' => 'drive-folder:abc', 'active' => true]);

        $results = collect($this->postJson('/api/search', ['q' => 'dragon keychain'])->assertOk()->json('results'))->keyBy('title');
        $this->assertSame('printables', $results['Dragon keychain A']['origin']);
        $this->assertSame('makerworld', $results['Dragon keychain B']['origin']);
        $this->assertStringStartsWith('https://', $results['Dragon keychain A']['externalUrl']);
        $this->assertArrayNotHasKey('Dragon keychain C', $results->all());          // no page to open, no file to price: not shown
    }
}
