<?php

namespace Tests\Feature;

use App\Domain\Farm\ColorCatalog;
use App\Domain\Farm\FilamentFromShop;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\Shop\ShopFetcher;
use App\Domain\Farm\Shop\ShopPageReader;
use App\Engines\Ai\FakeAssistant;
use App\Models\AiCall;
use App\Models\FarmColor;
use App\Models\FarmColorPhotoProposal;
use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A spool bought on the web (TODO #71, docs/AL.md §21): the admin pastes the shop's link at a printer position, the
 * agent reads the page (four real shops as trimmed fixtures), the card is confirmed, the colour and the spool are
 * written. The fetcher refuses what is not the public web; the same link again is a spool only.
 */
class FilamentFromShopTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FarmPrinter $printer;

    private const PRUSA = 'https://www.prusa3d.com/product/prusament-pla-jet-black-1kg-nfc/';

    private const JAKE = 'https://www.3djake.cz/bambu-lab/petg-matte-avocado-green';

    private const MAJKL = 'https://www.majkl3d.cz/p/majkl3d-filaments-pla-1-75mm-1kg-cervena';

    private const SUNLU = 'https://store.sunlu.com/products/250g-spool-3d-printing-filaments-0-25kg-per-roll?variant=53031802863898';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
        $this->printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        FakeAssistant::reset();
        // no DNS in the suite: every host is public, except the one that says it is not
        ShopFetcher::$resolver = fn (string $host) => str_contains($host, 'intranet') ? ['10.0.0.5'] : ['93.184.216.34'];
        Http::preventStrayRequests();
        $html = fn (string $file) => Http::response(file_get_contents(base_path('tests/fixtures/shops/'.$file)), 200, ['Content-Type' => 'text/html; charset=utf-8']);
        $picture = Http::response($this->spoolPhoto([200, 30, 30]), 200, ['Content-Type' => 'image/jpeg']);
        Http::fake([
            'www.prusa3d.com/product/*' => $html('prusa.html'),
            'www.prusa3d.com/category/*' => $html('category.html'),
            'www.prusa3d.com/*' => $picture,
            'www.3djake.cz/*' => $html('jake.html'),
            '3d.nice-cdn.com/*' => $picture,
            'www.majkl3d.cz/*' => $html('majkl.html'),
            'majkl3d-admin.s18.cdn-upgates.com/*' => $picture,
            // Shopify serves robots a 429 for the HTML and answers /products/<handle>.json
            'store.sunlu.com/products/*.json' => Http::response(file_get_contents(base_path('tests/fixtures/shops/sunlu.json')), 200, ['Content-Type' => 'application/json; charset=utf-8']),
            'store.sunlu.com/*' => Http::response('local_rate_limited', 429, ['Content-Type' => 'text/plain']),
            'bots.myshopify.com/*' => Http::response('local_rate_limited', 429, ['Content-Type' => 'text/plain']),
            'cdn.shopify.com/*' => $picture,
            'redirect-shop.net/*' => Http::response('', 302, ['Location' => 'http://10.0.0.7/admin']),
            'shop.intranet.net/*' => $html('prusa.html'),
        ]);
    }

    protected function tearDown(): void
    {
        ShopFetcher::$resolver = null;
        parent::tearDown();
    }

    /** A product shot: a coloured spool in the middle of a white backdrop, as the shops photograph them. */
    private function spoolPhoto(array $rgb, array $backdrop = [255, 255, 255]): string
    {
        $im = imagecreatetruecolor(400, 400);
        imagefill($im, 0, 0, imagecolorallocate($im, $backdrop[0], $backdrop[1], $backdrop[2]));
        imagefilledellipse($im, 200, 200, 240, 240, imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]));
        imagefilledellipse($im, 200, 200, 40, 40, imagecolorallocate($im, 40, 40, 40));   // the hub
        ob_start();
        imagejpeg($im, null, 92);

        return (string) ob_get_clean();
    }

    private function kind(string $code, string $finish = 'solid'): FarmMaterial
    {
        return FarmMaterial::where('code', $code)->where('finish', $finish)->firstOrFail();
    }

    /** What the model answers for the Prusa page, with the test's own changes on top. */
    private function answer(array $over = []): array
    {
        return array_replace_recursive([
            'is_product_page' => true, 'name_cs' => 'jet černá', 'name_en' => 'jet black', 'manufacturer' => 'Prusament',
            'material' => ['id' => $this->kind('PLA')->id, 'code' => 'PLA', 'finish' => 'solid', 'suggestion' => null], 'colour_name' => 'Jet Black',
            'hex_declared' => null, 'hex_guess' => '#111111', 'best_image' => 0,
            'images' => [['index' => 0, 'shows_this_spool' => true, 'has_shop_logo_or_watermark' => false]],
            'settings' => ['nozzle' => 215, 'nozzle_first' => 220, 'bed' => 60, 'max_volumetric_speed' => null, 'drying' => null, 'declared' => true],
            'net_weight_g' => 1000, 'price' => ['amount' => 599.2, 'currency' => 'CZK'], 'confidence' => 0.9, 'notes' => 'Teploty z tabulky parametrů.',
        ], $over);
    }

    private function start(string $url, ?int $slot = 2, ?FarmPrinter $printer = null): array
    {
        $body = ['url' => $url];
        if ($slot !== null) {
            $body += ['printer_id' => ($printer ?? $this->printer)->id, 'slot' => $slot];
        }

        return (array) $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url', $body)->assertOk()->json();
    }

    private function cardForm(array $entry, array $over = []): array
    {
        $card = (array) $entry['card'];

        return $over + [
            'mode' => 'place', 'name' => $card['name_cs'], 'name_en' => $card['name_en'], 'farm_material_id' => $card['material_id'], 'manufacturer' => $card['manufacturer'],
            'hex' => $card['hex'], 'use_photo' => true, 'save_settings' => (bool) $card['print_overrides'], 'enabled' => true, 'test_notes' => $card['test_notes'], 'remaining_g' => $card['net_weight_g'] ?? 1000,
        ];
    }

    // ── the card from a position ─────────────────────────────────────────────

    public function test_a_card_from_a_position_saves_the_colour_the_spool_and_the_grams(): void
    {
        FakeAssistant::$answers['filament'] = $this->answer();
        $entry = $this->start(self::PRUSA.'?utm_source=newsletter&fbclid=abc#reviews');

        $this->assertSame('done', $entry['status']);
        $this->assertSame(self::PRUSA, $entry['url'], 'the link is stored without tracking parameters and the fragment');
        $this->assertSame($this->printer->id, $entry['printer_id']);
        $this->assertSame(2, $entry['slot']);
        $card = $entry['card'];
        $this->assertSame('jet černá', $card['name_cs']);
        $this->assertSame($this->kind('PLA')->id, $card['material_id']);
        $this->assertSame('PLA', $card['material_label']);
        $this->assertSame(0, $card['photo']);
        $this->assertSame('photo', $card['hex_from'], 'no HEX on the page: the shade is read from the chosen picture');
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $card['hex']);
        [$r, $g, $b] = sscanf($card['hex'], '#%02x%02x%02x');
        $this->assertGreaterThan(150, $r);
        $this->assertLessThan(90, $g, 'the white backdrop of the product shot does not wash the colour out');
        $this->assertSame(['nozzle_temp' => 215, 'nozzle_temp_first' => 220, 'bed_temp' => 60], $card['print_overrides']);
        $this->assertStringContainsString('Z e-shopu www.prusa3d.com', $card['test_notes']);
        $this->assertStringContainsString('tryska 215/220, podložka 60', $card['test_notes']);
        $this->assertStringContainsString('599,20 CZK', $card['test_notes']);
        $this->assertSame(1000, $card['net_weight_g']);
        $this->assertNotContains('logo', $entry['warnings']);
        $this->assertNotContains('no_settings', $entry['warnings']);
        $this->assertCount(1, $entry['images'], 'the Prusa fixture has one product picture and three colour swatches of 180 px that are skipped');
        $this->assertStringContainsString('/admin/farm/colors/from-url/'.$entry['id'].'/image/0', $entry['images'][0]['url']);
        $this->actingAs($this->admin)->get($entry['images'][0]['url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // the model saw the page's data and the farm's kinds, and the pictures as local copies, never the shop's addresses
        $this->assertCount(1, FakeAssistant::$calls);
        $call = FakeAssistant::$calls[0];
        $this->assertSame('filament', $call['kind']);
        $this->assertStringContainsString('Prusament PLA Jet Black 1kg', $call['user']);
        $this->assertStringContainsString('Nozzle temperature 215 °C', $call['user']);
        $this->assertStringContainsString('<kinds>', $call['user']);
        $this->assertStringContainsString('| PLA+ | matte |', $call['user']);
        $this->assertCount(1, $call['images']);
        $this->assertStringEndsWith('img-0.jpg', $call['images'][0]);
        $this->assertStringNotContainsString('prusa3d.com', $call['images'][0]);
        $this->assertSame('farm_color_shop', $call['context']['subject_type']);
        $this->assertSame(1, AiCall::where('kind', 'filament')->count(), 'the call is booked for /admin/ai');

        // the admin's yes: colour, photo, settings, note, the spool in the position
        $saved = $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry))->assertOk()->json();
        $this->assertTrue($saved['ok']);
        $this->assertTrue($saved['created']);
        $color = FarmColor::where('source_url', self::PRUSA)->with('material')->firstOrFail();
        $this->assertSame('jet černá', $color->name);
        $this->assertSame('jet black', $color->name_en);
        $this->assertSame('Prusament', $color->manufacturer, 'the maker differs from the kind\'s, so it is kept on the colour');
        $this->assertSame('PLA_Jet_Black_Prusament', $color->code);
        $this->assertTrue($color->enabled);
        $this->assertTrue($color->in_stock);
        $this->assertSame(FarmColor::PHOTO_ESHOP, $color->photo_source);
        $this->assertNotNull($color->photo_path);
        Storage::disk('public')->assertExists($color->photo_path);
        [$w, $h] = getimagesizefromstring((string) Storage::disk('public')->get($color->photo_path));
        $this->assertLessThanOrEqual(800, max($w, $h));
        $this->assertSame(['nozzle_temp' => 215, 'nozzle_temp_first' => 220, 'bed_temp' => 60], $color->print_overrides);
        $this->assertSame('www.prusa3d.com', $color->source_meta['host']);
        $this->assertSame(1000, $color->source_meta['weight_g']);
        $this->assertSame(599.2, $color->source_meta['price']['amount']);
        $this->assertTrue($color->source_meta['image']['used']);
        $this->assertSame('claude-sonnet-5-5', $color->source_meta['model']);
        $slot = $this->printer->slots()->where('slot', 2)->firstOrFail();
        $this->assertSame($color->id, $slot->farm_color_id);
        $this->assertSame(1000.0, $slot->remaining_g);
        $this->assertTrue($slot->enabled);
        // the spool's own temperatures reach the print profile as layer 3
        $profile = PrintProfile::for($this->printer, $color->material, $color);
        $this->assertSame(['nozzle' => 215, 'nozzle_first' => 220, 'bed' => 60], $profile->temps);
        $this->assertContains('spool', $profile->layers);
        // the card is forgotten and its pictures are gone
        $this->actingAs($this->admin)->getJson('/admin/farm/colors/from-url/'.$entry['id'])->assertNotFound();
        $this->assertDirectoryDoesNotExist(FilamentFromShop::dir($entry['id']));
    }

    public function test_the_shop_logo_on_the_picture_leaves_the_colour_without_a_photo(): void
    {
        FakeAssistant::$answers['filament'] = $this->answer(['images' => [['index' => 0, 'shows_this_spool' => true, 'has_shop_logo_or_watermark' => true]]]);
        $entry = $this->start(self::PRUSA);
        $this->assertSame('done', $entry['status']);
        $this->assertNull($entry['card']['photo']);
        $this->assertContains('logo', $entry['warnings']);
        $this->assertSame('model', $entry['card']['hex_from'], 'without a usable picture the shade is the model\'s estimate');
        $this->assertSame('#111111', $entry['card']['hex']);
        $this->assertStringContainsString(ColorCatalog::CHECK_NOTE, $entry['card']['test_notes']);
        $this->assertTrue($entry['images'][0]['logo']);

        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry, ['mode' => 'color']))->assertOk();
        $color = FarmColor::where('source_url', self::PRUSA)->firstOrFail();
        $this->assertNull($color->photo_path);
        $this->assertNull($color->photo_source);
        $this->assertFalse($color->source_meta['image']['used']);
        $this->assertTrue($color->source_meta['image']['logo']);
        $this->assertNotContains($color->id, $this->printer->slots()->pluck('farm_color_id')->all(), '"save the colour only" touches no position');

        // the admin may still pick a picture by hand (another one of the page): then it is used
        FakeAssistant::$answers['filament'] = $this->answer(['images' => [['index' => 0, 'shows_this_spool' => false, 'has_shop_logo_or_watermark' => false]]]);
        $entry = $this->start(self::JAKE);
        $this->assertContains('not_spool', $entry['warnings']);
        $this->assertNull($entry['card']['photo']);
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry, ['mode' => 'color', 'photo' => 1, 'use_photo' => true]))->assertOk();
        $this->assertSame(FarmColor::PHOTO_ESHOP, FarmColor::where('source_url', self::JAKE)->firstOrFail()->photo_source);
    }

    public function test_a_listing_page_is_not_a_product(): void
    {
        $entry = $this->start('https://www.prusa3d.com/category/filament/');
        $this->assertSame('done', $entry['status']);
        $this->assertNull($entry['card']);
        $this->assertContains('not_product', $entry['warnings']);
        $this->assertSame(__('farm.admin.shop.warn.not_product'), $entry['message']);
        $this->assertCount(0, FakeAssistant::$calls, 'a listing is refused before the model is asked');

        // the model may say so too, for a page without JSON-LD
        FakeAssistant::$answers['filament'] = $this->answer(['is_product_page' => false]);
        $entry = $this->start(self::MAJKL);
        $this->assertNull($entry['card']);
        $this->assertContains('not_product', $entry['warnings']);
        $this->assertCount(1, FakeAssistant::$calls);
    }

    // ── the fetcher's guards ─────────────────────────────────────────────────

    public function test_private_hosts_other_schemes_and_oversized_pages_are_refused(): void
    {
        FakeAssistant::$answers['filament'] = $this->answer();
        foreach (['ftp://www.prusa3d.com/x', 'javascript:alert(1)', 'www.prusa3d.com/product/x', 'https://user:pw@www.prusa3d.com/x'] as $bad) {
            $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url', ['url' => $bad])->assertStatus(422)->assertJsonPath('message', __('farm.admin.shop.refused.scheme'));
        }
        foreach (['http://localhost/x', 'http://192.168.1.5/x', 'http://10.0.0.1/x', 'http://127.0.0.1:8000/x', 'http://169.254.169.254/latest', 'http://[::1]/x', 'https://shop.intranet.net/x', 'https://printer.local/x'] as $bad) {
            $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url', ['url' => $bad])->assertStatus(422)->assertJsonPath('message', __('farm.admin.shop.refused.private_host'));
        }
        Http::assertNothingSent();

        // a public host that redirects into the LAN is refused at the redirect
        $entry = $this->start('https://redirect-shop.net/product/1');
        $this->assertSame('failed', $entry['status']);
        $this->assertStringContainsString(__('farm.admin.shop.refused.private_host'), $entry['message']);
        Http::assertSentCount(1);
        $this->assertCount(0, FakeAssistant::$calls);

        // more than 3 MB of HTML is not read
        Http::fake(['big.shop.net/*' => Http::response(str_repeat('<p>a</p>', 500_000), 200, ['Content-Type' => 'text/html'])]);
        $entry = $this->start('https://big.shop.net/product/1');
        $this->assertSame('failed', $entry['status']);
        $this->assertStringContainsString(__('farm.admin.shop.refused.too_big'), $entry['message']);

        // plain http to a public host is read (the brief: http(s))
        $entry = $this->start('http://www.majkl3d.cz/p/majkl3d-filaments-pla-1-75mm-1kg-cervena');
        $this->assertSame('done', $entry['status']);
        $this->assertSame('http://www.majkl3d.cz/p/majkl3d-filaments-pla-1-75mm-1kg-cervena', $entry['url']);
    }

    public function test_the_link_is_normalised_and_at_most_eight_pictures_are_taken(): void
    {
        $this->assertSame('https://www.prusa3d.com/product/x/?variant=5', ShopFetcher::normalize('HTTPS://WWW.Prusa3D.com/product/x/?utm_source=a&variant=5&fbclid=z&utm_campaign=c#top'));
        $this->assertSame('https://store.sunlu.com/products/pla?b=2&variant=1', ShopFetcher::normalize('https://store.sunlu.com/products/pla?variant=1&b=2&ref=x'));
        $this->assertSame('https://shop.example.net/', ShopFetcher::normalize('https://shop.example.net'));
        $this->assertNull(ShopFetcher::normalize('ftp://shop.example.net/x'));
        $this->assertNull(ShopFetcher::normalize('not a link'));
        $this->assertSame('https://store.sunlu.com/products/pla-red.json', ShopPageReader::shopifyJsonUrl('https://store.sunlu.com/collections/all/products/pla-red?variant=3'));
        $this->assertNull(ShopPageReader::shopifyJsonUrl(self::PRUSA));

        // a page with twelve gallery pictures hands the model eight at most
        $imgs = implode('', array_map(fn ($i) => '<li><img src="https://many.shop.net/img/spool-'.$i.'.jpg" alt="Spool '.$i.'"></li>', range(1, 12)));
        $page = '<!doctype html><html><head><title>Red PLA</title><script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"Red PLA 1kg","brand":{"@type":"Brand","name":"Many"},"image":"https://many.shop.net/img/main.jpg","offers":{"@type":"Offer","price":"19.90","priceCurrency":"EUR"}}</script></head>'
            .'<body><nav><img src="https://many.shop.net/img/menu.jpg"></nav><h1>Red PLA 1kg</h1><div class="product-gallery"><ul>'.$imgs.'</ul></div><div class="related-products"><img src="https://many.shop.net/img/other.jpg"></div></body></html>';
        Http::fake(['many.shop.net/product/*' => Http::response($page, 200, ['Content-Type' => 'text/html']), 'many.shop.net/img/*' => Http::response($this->spoolPhoto([30, 30, 200]), 200, ['Content-Type' => 'image/jpeg'])]);
        FakeAssistant::$answers['filament'] = $this->answer(['best_image' => 3, 'images' => [['index' => 3, 'shows_this_spool' => true, 'has_shop_logo_or_watermark' => false]]]);
        $entry = $this->start('https://many.shop.net/product/red-pla');
        $this->assertSame('done', $entry['status']);
        $this->assertCount(8, $entry['images']);
        $this->assertCount(8, FakeAssistant::$calls[0]['images']);
        $this->assertSame(3, $entry['card']['photo']);
        $this->assertStringNotContainsString('menu.jpg', FakeAssistant::$calls[0]['user']);
        $this->assertStringNotContainsString('other.jpg', FakeAssistant::$calls[0]['user']);
        Http::assertSentCount(9, 'the page and eight pictures, not twelve');
    }

    // ── the same link again ──────────────────────────────────────────────────

    public function test_the_same_link_again_adds_a_spool_only(): void
    {
        FakeAssistant::$answers['filament'] = $this->answer();
        $first = $this->start(self::PRUSA);
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$first['id'].'/save', $this->cardForm($first))->assertOk();
        $color = FarmColor::where('source_url', self::PRUSA)->firstOrFail();
        $colors = FarmColor::count();

        // another spool bought later, the link pasted with other tracking parameters, at another position
        $again = $this->start('https://WWW.prusa3d.com/product/prusament-pla-jet-black-1kg-nfc/?utm_source=google', 3);
        $this->assertSame('done', $again['status']);
        $this->assertSame($color->id, $again['existing']['id']);
        $this->assertSame($color->fullLabel(), $again['existing']['label']);
        $this->assertSame(1000, $again['existing']['weight_g']);
        $this->assertNull($again['card']);
        $this->assertCount(1, FakeAssistant::$calls, 'no second model call for a link the catalogue knows');
        $this->assertSame(1, AiCall::where('kind', 'filament')->count());

        $saved = $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$again['id'].'/save', ['mode' => 'place', 'remaining_g' => 750])->assertOk()->json();
        $this->assertFalse($saved['created']);
        $this->assertSame($color->id, $saved['color']['id']);
        $this->assertSame($colors, FarmColor::count(), 'no new colour row');
        $slot = $this->printer->slots()->where('slot', 3)->firstOrFail();
        $this->assertSame($color->id, $slot->farm_color_id);
        $this->assertSame(750.0, $slot->remaining_g);
        $this->assertTrue($slot->enabled);
        // the first position still holds its spool
        $this->assertSame($color->id, $this->printer->slots()->where('slot', 2)->firstOrFail()->farm_color_id);
    }

    // ── settings and kinds ───────────────────────────────────────────────────

    public function test_only_the_keys_of_the_spool_layer_go_into_the_settings(): void
    {
        // whatever else the model may put into settings, only the temperatures and the volumetric speed are written
        FakeAssistant::$answers['filament'] = $this->answer(['settings' => ['nozzle' => 240, 'nozzle_first' => 245, 'bed' => 75, 'max_volumetric_speed' => 12, 'drying' => '65 °C / 8 h', 'declared' => true, 'retraction' => 2, 'fan' => 40]]);
        $entry = $this->start(self::JAKE);
        $this->assertSame(['nozzle_temp' => 240, 'nozzle_temp_first' => 245, 'bed_temp' => 75, 'filament' => ['filament_max_volumetric_speed' => ['12']]], $entry['card']['print_overrides']);
        $this->assertStringContainsString('tryska 240/245, podložka 75, průtok 12 mm³/s', $entry['card']['test_notes']);
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry, ['farm_material_id' => $this->kind('PETG')->id]))->assertOk();
        $color = FarmColor::where('source_url', self::JAKE)->with('material')->firstOrFail();
        $this->assertSame(['nozzle_temp' => 240, 'nozzle_temp_first' => 245, 'bed_temp' => 75, 'filament' => ['filament_max_volumetric_speed' => ['12']]], $color->print_overrides);
        $this->assertArrayNotHasKey('retraction', (array) $color->print_overrides);
        $profile = PrintProfile::for($this->printer, $color->material, $color);
        $this->assertSame(['12'], $profile->filament['filament_max_volumetric_speed']);
        $this->assertSame(['nozzle' => 240, 'nozzle_first' => 245, 'bed' => 75], $profile->temps);

        // a page that states nothing: no settings, the kind's profile prints, and the admin cannot switch the toggle on
        FakeAssistant::$answers['filament'] = $this->answer(['name_cs' => 'červená', 'name_en' => 'red', 'manufacturer' => 'Majkl3D-Filaments', 'settings' => ['nozzle' => null, 'nozzle_first' => null, 'bed' => null, 'max_volumetric_speed' => null, 'drying' => null, 'declared' => false], 'price' => ['amount' => null, 'currency' => null]]);
        $entry = $this->start(self::MAJKL);
        $this->assertNull($entry['card']['print_overrides']);
        $this->assertContains('no_settings', $entry['warnings']);
        $this->assertStringNotContainsString('Z e-shopu', $entry['card']['test_notes']);
        $this->assertStringNotContainsString('Cena', $entry['card']['test_notes']);
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry, ['save_settings' => true]))->assertOk();
        $color = FarmColor::where('source_url', self::MAJKL)->with('material')->firstOrFail();
        $this->assertNull($color->print_overrides);
        $profile = PrintProfile::for($this->printer, $color->material, $color);
        $this->assertSame(PrintProfile::for($this->printer, $color->material)->temps, $profile->temps, 'the kind\'s temperatures (the general profile, with the machine\'s tuning row)');
        $this->assertNotContains('spool', $profile->layers);
        // what the model does not declare is not believed even when a number slipped in
        FakeAssistant::$answers['filament'] = $this->answer(['settings' => ['nozzle' => 230, 'declared' => false]]);
        $this->assertNull($this->start(self::PRUSA, null)['card']['print_overrides']);
    }

    public function test_an_unknown_kind_is_suggested_and_never_created(): void
    {
        $kinds = FarmMaterial::count();
        FakeAssistant::$answers['filament'] = $this->answer(['material' => ['id' => null, 'code' => 'PA12-CF', 'finish' => 'cf', 'suggestion' => 'PA12-CF od Prusament']]);
        $entry = $this->start(self::PRUSA);
        $this->assertNull($entry['card']['material_id']);
        $this->assertSame('PA12-CF od Prusament', $entry['card']['material_suggestion']);
        $this->assertContains('kind_unknown', $entry['warnings']);
        $this->assertFalse($entry['card']['enabled'], 'a colour without a kind is not offered until the admin decides');
        $this->assertSame($kinds, FarmMaterial::count());

        // the card cannot be saved without a kind; with one picked by the admin it can, and still no kind is created
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry, ['farm_material_id' => null]))->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['farm_material_id']]);
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry, ['farm_material_id' => $this->kind('PETG-CF', 'cf')->id]))->assertOk();
        $this->assertSame($kinds, FarmMaterial::count());
        $this->assertSame('PETG-CF', FarmColor::where('source_url', self::PRUSA)->firstOrFail()->material->code);

        // a kind named by code and finish (no id) is found, our own maker's first
        FakeAssistant::$answers['filament'] = $this->answer(['material' => ['id' => null, 'code' => 'pla+', 'finish' => 'matte', 'suggestion' => null]]);
        $this->assertSame($this->kind('PLA+', 'matte')->id, $this->start(self::JAKE, null)['card']['material_id']);
    }

    public function test_a_kind_that_breaks_in_the_changer_cannot_be_placed_into_an_agent_machine_from_the_card(): void
    {
        $glow = $this->kind('PLA', 'luminous');
        $glow->update(['manual_only' => true]);
        $this->printer->update(['mode' => FarmPrinter::MODE_AGENT]);
        FakeAssistant::$answers['filament'] = $this->answer(['material' => ['id' => $glow->id, 'code' => 'PLA', 'finish' => 'luminous', 'suggestion' => null]]);
        $entry = $this->start(self::PRUSA);
        $colors = FarmColor::count();
        $before = $this->printer->slots()->where('slot', 2)->value('farm_color_id');
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry))
            ->assertStatus(422)->assertJsonPath('message', __('farm.admin.manual_only_refused', ['color' => $glow->label().', jet černá', 'printer' => $this->printer->name]));
        $this->assertSame($colors, FarmColor::count(), 'nothing is written when the position refuses the spool');
        $this->assertSame($before, $this->printer->slots()->where('slot', 2)->value('farm_color_id'), 'the position keeps what it held');
        // the colour alone is fine
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', $this->cardForm($entry, ['mode' => 'color']))->assertOk();
        $this->assertSame($colors + 1, FarmColor::count());
    }

    // ── the shops ────────────────────────────────────────────────────────────

    public function test_a_shopify_shop_that_refuses_robots_is_read_from_its_product_json(): void
    {
        FakeAssistant::$answers['filament'] = $this->answer(['name_cs' => 'duhová', 'name_en' => 'rainbow', 'manufacturer' => 'SUNLU', 'material' => ['id' => $this->kind('PLA', 'special')->id], 'net_weight_g' => 250, 'price' => ['amount' => 7.49, 'currency' => 'USD'], 'hex_guess' => '#ff8800']);
        $entry = $this->start(self::SUNLU);
        $this->assertSame('done', $entry['status']);
        $this->assertSame(self::SUNLU, $entry['url'], 'the Shopify variant stays in the link: it is the colour');
        $call = FakeAssistant::$calls[0];
        $this->assertStringContainsString('Shopify product JSON', $call['user']);
        $this->assertStringContainsString('Ship to Europe / PLA Rainbow / 02', $call['user']);
        $this->assertStringContainsString('"chosen": true', $call['user']);
        $this->assertStringContainsString('250g Spool', $call['user']);
        $this->assertCount(6, $entry['images'], 'the six pictures of the product JSON');
        $this->assertSame(250, $entry['card']['net_weight_g']);
        $this->assertSame('rainbow', $entry['card']['name_en']);
        // a rainbow has no one colour: the photo's dominant tone stands in and a person should look at it
        $this->assertSame('photo', $entry['card']['hex_from']);
        $this->assertStringContainsString(ColorCatalog::CHECK_NOTE, $entry['card']['test_notes']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/products/250g-spool-3d-printing-filaments-0-25kg-per-roll.json'));

        // a shop that serves 429 to everything: the card opens empty with the reason, nothing is asked of the model
        FakeAssistant::reset();
        $entry = $this->start('https://bots.myshopify.com/products/pla-red');
        $this->assertSame('done', $entry['status']);
        $this->assertContains('rate_limited', $entry['warnings']);
        $this->assertSame('', $entry['card']['name_cs']);
        $this->assertNull($entry['card']['material_id']);
        $this->assertCount(0, FakeAssistant::$calls);
        // the admin fills it in by hand and saves
        $this->actingAs($this->admin)->postJson('/admin/farm/colors/from-url/'.$entry['id'].'/save', ['mode' => 'place', 'name' => 'červená', 'farm_material_id' => $this->kind('PLA')->id, 'hex' => '#cc0000', 'remaining_g' => 1000, 'enabled' => true])->assertOk();
        $color = FarmColor::where('source_url', 'https://bots.myshopify.com/products/pla-red')->firstOrFail();
        $this->assertSame('#cc0000', $color->hex);
        $this->assertNull($color->photo_path);
        $this->assertSame('PLA_Cervena', $color->code);
    }

    public function test_a_shop_without_json_ld_is_read_from_its_text_and_og_image(): void
    {
        FakeAssistant::$answers['filament'] = $this->answer(['name_cs' => 'červená', 'name_en' => 'red', 'manufacturer' => 'Majkl3D-Filaments', 'settings' => ['nozzle' => 205, 'nozzle_first' => 215, 'bed' => 60, 'max_volumetric_speed' => null, 'drying' => '45 °C / 6-8 h', 'declared' => true], 'price' => ['amount' => 299, 'currency' => 'CZK']]);
        $entry = $this->start(self::MAJKL);
        $this->assertSame('done', $entry['status']);
        $call = FakeAssistant::$calls[0];
        $this->assertStringContainsString('Teplota trysky', $call['user']);
        $this->assertStringContainsString('190-220 °C', $call['user']);
        $this->assertStringContainsString('1 kg netto', $call['user']);
        $this->assertStringContainsString('og:image', $call['user']);
        $this->assertStringNotContainsString('JSON-LD Product', $call['user']);
        $this->assertCount(2, $entry['images'], 'the og:image and the gallery picture; the two variant boxes are other colours and are skipped');
        $this->assertSame('og', $entry['images'][0]['from']);
        $this->assertSame('Majkl3D-Filaments', $entry['card']['manufacturer']);
        $this->assertStringContainsString('tryska 205/215, podložka 60', $entry['card']['test_notes']);
        $this->assertStringContainsString('299,00 CZK', $entry['card']['test_notes']);

        // 3DJake: JSON-LD Product with the one picture in four sizes, og:image, the attribute banner with the temperatures
        FakeAssistant::reset();
        FakeAssistant::$answers['filament'] = $this->answer(['name_cs' => 'avokádově zelená', 'name_en' => 'avocado green', 'manufacturer' => 'Bambu Lab', 'material' => ['id' => $this->kind('PETG')->id]]);
        $entry = $this->start(self::JAKE);
        $call = FakeAssistant::$calls[0];
        $this->assertStringContainsString('"brand": "Bambu Lab"', $call['user']);
        $this->assertStringContainsString('230 - 260 °C', $call['user']);
        $this->assertStringContainsString('1.000 g', $call['user']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '117889_d2d5098a.1024x1024.png'));   // the largest size of the one product picture
        $this->assertSame('jsonld', $entry['images'][0]['from']);
    }

    // ── the pieces ───────────────────────────────────────────────────────────

    public function test_the_shade_is_read_from_a_product_shot_on_a_white_backdrop(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mps');
        file_put_contents($file, $this->spoolPhoto([30, 90, 200]));
        $hex = ColorCatalog::hexFromPhoto($file);
        @unlink($file);
        [$r, $g, $b] = sscanf((string) $hex, '#%02x%02x%02x');
        $this->assertLessThan(80, $r);
        $this->assertGreaterThan(150, $b, 'the white backdrop is flooded away, the spool\'s blue is read');
    }

    public function test_the_data_of_the_colour_and_the_photo_proposals(): void
    {
        $color = FarmColor::firstOrFail();
        $this->assertNull($color->photo_source);
        $color->update(['source_url' => 'https://shop.example.net/p/1', 'source_meta' => ['host' => 'shop.example.net', 'weight_g' => 800], 'photo_source' => FarmColor::PHOTO_UPLOAD]);
        $color->refresh();
        $this->assertSame(['host' => 'shop.example.net', 'weight_g' => 800], $color->source_meta);
        $this->assertSame('upload', $color->photo_source);
        $proposal = FarmColorPhotoProposal::create(['farm_color_id' => $color->id, 'path' => 'farm/colors/proposals/x.jpg']);
        $this->assertSame(FarmColorPhotoProposal::STATUS_PENDING, $proposal->fresh()->status);
        $this->assertSame($color->id, $proposal->color->id);
    }

    public function test_the_admin_pages_carry_the_link_field_and_the_card(): void
    {
        // nobody else: a guest is asked to log in, a customer is refused (before any actingAs, which stays for the test)
        $this->postJson('/admin/farm/colors/from-url', ['url' => self::PRUSA])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/admin/farm/colors/from-url', ['url' => self::PRUSA])->assertRedirect();   // the role middleware sends a customer away
        $this->assertCount(0, FakeAssistant::$calls);

        $this->actingAs($this->admin)->get('/admin/farm/printers/load')->assertOk()
            ->assertSee(__('farm.admin.shop.load'))->assertSee('data-filament-shop', false)->assertSee('data-shop-url', false)
            ->assertSee('data-printer="'.$this->printer->id.'" data-slot="0"', false)->assertSee('data-shop-dialog', false);
        $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk()
            ->assertSee(__('farm.admin.shop.materials_hint'))->assertSee('data-kind="'.$this->kind('PLA')->id.'"', false)->assertSee('data-filament-shop', false);
    }
}
