<?php

namespace Tests\Feature;

use App\Domain\Tools\MapBuilder;
use App\Domain\Tools\MapData;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The 3D map of a city or a landscape (/tools/map): the page, the places (Nominatim through the cache), the design
 * made in the queue from the fixtures of tests/fixtures/maps (Overpass and the height tiles faked: nothing here
 * touches the network), what the farm and the slicer read of it, the limits, the errors.
 */
class MapToolTest extends TestCase
{
    use RefreshDatabase;

    private const CENTER = ['lat' => 50.0875, 'lon' => 14.4213];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        MapData::$root = storage_path('framework/testing/maps');  // the answers of the services are kept on disk: a test starts without any, away from the real cache
        File::deleteDirectory(MapData::$root);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(MapData::root());
        MapData::$root = null;
        parent::tearDown();
    }

    private function needsPython(): void
    {
        if (! app(MapBuilder::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    /** The services as the fixtures answer them: Nominatim, Overpass (the hand-made town), the height tiles (a hill drawn here). */
    private function fakeServices(?string $overpass = null): void
    {
        Http::fake([
            // the geocoder answers the first question and is down for the next one
            'nominatim.openstreetmap.org/*' => Http::sequence()->push(file_get_contents(base_path('tests/fixtures/maps/nominatim.json')), 200, ['Content-Type' => 'application/json'])->whenEmpty(Http::response('', 503)),
            'overpass-api.de/*' => $overpass === 'down' ? Http::response('<html>502</html>', 502) : Http::response(file_get_contents(MapBuilder::fixture($overpass ?? 'mesto')), 200, ['Content-Type' => 'application/json']),
            'overpass.kumi.systems/*' => Http::response('<html>502</html>', 502),
            'overpass.openstreetmap.fr/*' => Http::response('<html>502</html>', 502),
            's3.amazonaws.com/*' => Http::response($this->tile(), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    /** A Terrarium tile of a hill: 256 × 256, height = (R·256 + G + B/256) − 32768 m, from 300 m at the rim to 700 m in the middle. */
    private function tile(): string
    {
        static $png = null;
        if ($png === null) {
            $img = imagecreatetruecolor(256, 256);
            for ($y = 0; $y < 256; $y++) {
                for ($x = 0; $x < 256; $x++) {
                    $d = hypot($x - 128, $y - 128) / 128;
                    $h = 300 + 400 * max(0.0, 1 - $d * $d) + 32768;
                    $r = (int) floor($h / 256);
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, (int) floor($h - $r * 256), (int) round(($h - floor($h)) * 255)));
                }
            }
            ob_start();
            imagepng($img);
            $png = (string) ob_get_clean();
            imagedestroy($img);
        }

        return $png;
    }

    private function make(array $params, ?User $user = null): array
    {
        $r = ($user ? $this->actingAs($user) : $this)->postJson('/api/tools/map', ['params' => $params + self::CENTER])->assertCreated();
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();

        return [$file, $this->getJson('/api/files/'.$file->uuid)->assertOk()->json('file')];
    }

    public function test_the_page_opens_in_three_languages_with_its_steps_and_the_attribution(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $html = $this->get($prefix.'/tools/map')->assertOk()->getContent();
            $this->assertStringContainsString(e(__('tools.map.title', [], $locale)), $html);
            $this->assertStringContainsString(e(ToolSeo::texts('map', $locale)['h1']), $html);
            foreach (['place', 'settings', 'colors', 'print'] as $step) {
                $this->assertStringContainsString('data-section="'.$step.'"', $html, "$locale $step");
            }
            $this->assertStringContainsString('OpenStreetMap', $html);
            $this->assertStringContainsString('id="map-place"', $html);
            $this->assertStringContainsString('data-choice="type"', $html);
            $this->assertStringContainsString('data-choice="side"', $html);
            $this->assertStringContainsString('data-param="size"', $html);
            $this->assertStringContainsString('data-text="name"', $html);
            $this->assertStringContainsString('window.MP_MAP', $html);
            $this->assertDoesNotMatchRegularExpression('/>\s*(map|tools|toolpage|param)\.[a-z_.]+\s*</', $html, $locale);
        }
        $this->get('/tools')->assertOk()->assertSee(route('tools.map'));
        $this->get('/tools/map?from=not-a-uuid')->assertOk()->assertSee('from: null', false);
    }

    public function test_places_come_from_nominatim_once_and_then_from_the_cache_and_coordinates_ask_nobody(): void
    {
        $this->fakeServices();
        $r = $this->postJson('/api/tools/map/places', ['q' => 'Praha'])->assertOk();
        $places = $r->json('places');
        $this->assertCount(3, $places);
        $this->assertSame(['Praha', 'city', 'Česko'], [$places[0]['name'], $places[0]['kind'], $places[0]['country']]);
        $this->assertEqualsWithDelta(50.0874654, $places[0]['lat'], 1e-6);
        $this->assertSame('Staroměstské náměstí', $places[1]['name']);
        $this->postJson('/api/tools/map/places', ['q' => ' praha '])->assertOk()->assertJsonCount(3, 'places');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'nominatim.openstreetmap.org') && $request->hasHeader('User-Agent', MapData::USER_AGENT) && str_contains($request->url(), 'q=Praha') && str_contains($request->url(), 'limit=6'));
        // a pair of coordinates is a place by itself
        $point = $this->postJson('/api/tools/map/places', ['q' => '50,0875, 14,4213'])->assertOk()->json('places');
        $this->assertCount(1, $point);
        $this->assertSame('point', $point[0]['kind']);
        $this->assertEqualsWithDelta(50.0875, $point[0]['lat'], 1e-6);
        Http::assertSentCount(1);
        $this->postJson('/api/tools/map/places', ['q' => 'P'])->assertStatus(422);
        // the geocoder down: the page is told, with words
        $this->postJson('/api/tools/map/places', ['q' => 'Brno'])->assertStatus(503)->assertJsonPath('error', 'places_down')->assertJsonPath('message', __('map.error.places_down'));
    }

    public function test_a_city_is_made_in_the_queue_with_its_three_colours_by_height(): void
    {
        $this->needsPython();
        $this->fakeServices();
        [$file, $info] = $this->make(['type' => 'city', 'side' => '500', 'size' => 150, 'name' => 'Staré Město', 'place' => 'Staroměstské náměstí']);
        $this->assertSame('ready', $info['status'], (string) ($info['error'] ?? ''));
        $this->assertSame('map', $info['kind']);
        $this->assertEqualsWithDelta(150, $info['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(150, $info['bbox']['y'], 0.05);
        $this->assertEqualsWithDelta(4 + 42 * 138 / 500, $info['bbox']['z'], 0.2, 'the plate and the church tower (42 m to the top of its spire) at 138 mm : 500 m');
        $this->assertGreaterThan(50000, $info['volume_mm3']);
        $p = $file->tool_params;
        $this->assertSame('city', $p['type']);
        $this->assertSame('500', $p['side']);
        $this->assertSame(8, $p['notes']['buildings'], 'the tower part and the tower node are not buildings');
        $this->assertSame([2, 2], [$p['notes']['roofs'], $p['notes']['towers']], 'the house without a word gets a gabled roof, the hipped one is mapped; a tower as a part, a tower as a point');
        $this->assertGreaterThan(1000, $p['notes']['roads_m']);
        $this->assertSame('1 : 3 600', $p['notes']['scale']);
        $this->assertSame('2026-10-01', $p['notes']['osm_date']);
        $this->assertSame('Staré Město', $p['notes']['name']);
        // the print: the plate in the first colour, the roads from its top in the second, the buildings from 0.6 mm above in the third
        $this->assertSame([[4.0, '#2b2b2b'], [4.6, '#c9a96b']], array_map(fn ($c) => [(float) $c['z'], $c['hex']], $p['color_changes']));
        $this->assertSame([['z' => 4.0, 'hex' => '#2b2b2b', 'code' => null], ['z' => 4.6, 'hex' => '#c9a96b', 'code' => null]], $file->colorChanges());
        $this->assertSame(['#e8e4d8', '#2b2b2b', '#c9a96b'], $file->designColors());
        $this->assertFalse($p['multi_material']);
        // the viewer paints by the same heights
        $this->assertSame(['#c9a96b', '#2b2b2b', '#e8e4d8'], array_column($p['regions'], 'color'));
        $this->assertSame(['base', 'roads', 'buildings'], $info['map']['parts']);
        $this->assertSame($p['notes']['scale'], $info['map']['scale']);
        $this->assertArrayNotHasKey('stage', $info['map'], 'a finished map has no phase');
        // the services were asked once each, with our name on the request
        Http::assertSent(fn ($request) => str_contains($request->url(), 'overpass-api.de') && $request->hasHeader('User-Agent', MapData::USER_AGENT) && str_contains((string) $request['data'], 'way["building"]'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 's3.amazonaws.com'));
        // the page reopens the design with its settings
        $this->assertSame(route('tools.map', ['from' => $file->uuid]), $info['tool']['url']);
        $this->get('/tools/map?from='.$file->uuid)->assertOk()->assertSee('from: "'.$file->uuid.'"', false);
        $this->assertStringContainsString('top_z="4.20"', $this->project($file), 'the first layer above the plate changes to the roads');
        // a second map of the same square takes the data from the disk: no second request
        $this->make(['type' => 'city', 'side' => '500', 'size' => 100, 'style' => 'miniature', 'green' => true, 'roads' => 'sunk']);
        Http::assertSentCount(1);
    }

    public function test_a_miniature_rounds_and_raises_and_sunk_roads_change_nothing_but_the_colours(): void
    {
        $this->needsPython();
        $this->fakeServices();
        [$sleek, $a] = $this->make(['type' => 'city', 'side' => '500', 'size' => 150, 'frame' => false]);
        [$mini, $b] = $this->make(['type' => 'city', 'side' => '500', 'size' => 150, 'frame' => false, 'style' => 'miniature']);
        $this->assertEqualsWithDelta($a['bbox']['z'] - 4, ($b['bbox']['z'] - 4) / 1.5, 0.15, 'a miniature is one and a half times as high');
        $this->assertEqualsWithDelta(150, $b['bbox']['x'], 0.2);
        [$sunk, $c] = $this->make(['type' => 'city', 'side' => '500', 'size' => 150, 'frame' => false, 'roads' => 'sunk']);
        $this->assertCount(1, $sunk->tool_params['color_changes'], 'roads sunk into the plate: only the buildings change the colour');
        $this->assertLessThan($a['volume_mm3'], $c['volume_mm3']);
        foreach ([$sleek, $mini, $sunk] as $file) {
            $this->assertSame([], $file->tool_params['notes']['warnings']);
        }
    }

    public function test_a_landscape_is_a_closed_relief_with_the_hill_of_the_tiles(): void
    {
        $this->needsPython();
        $this->fakeServices();
        [$file, $info] = $this->make(['type' => 'landscape', 'side' => '5000', 'size' => 120, 'exaggeration' => 2, 'frame' => false, 'name' => '']);
        $this->assertSame('ready', $info['status'], (string) ($info['error'] ?? ''));
        $p = $file->tool_params;
        $this->assertSame('landscape', $p['type']);
        // the tiles were fetched once each and kept
        Http::assertSent(fn ($request) => str_contains($request->url(), 'elevation-tiles-prod/terrarium/12/'));
        $this->assertNotEmpty(File::glob(MapData::root().'/dem/12/*/*.png'));
        // 400 m of relief over 5 km at 1 : 41 700, twice: about 19 mm above the plate; a closed solid, as the mesh report says
        $relief = $p['notes']['relief_m'];
        $this->assertEqualsWithDelta(400, $relief, 40);
        $this->assertEqualsWithDelta(4 + $relief * 2 * 120 / 5000, $info['bbox']['z'], 1.5);
        $this->assertSame([], array_values(array_intersect($info['issues'], ['not_watertight', 'open_edges'])));
        $this->assertSame(['base', 'terrain'], $info['map']['parts']);
        $this->assertSame(['#e8e4d8', '#2d6a4f'], $file->designColors());
        $this->assertSame([[4.0, '#2d6a4f']], array_map(fn ($c) => [(float) $c['z'], $c['hex']], $p['color_changes']), 'the relief in its own colour above the plate');
        // the same place again: nothing is fetched twice
        $sent = count(Http::recorded());
        $this->make(['type' => 'landscape', 'side' => '5000', 'size' => 120, 'exaggeration' => 1, 'frame' => false]);
        $this->assertSame($sent, count(Http::recorded()));
    }

    public function test_the_picture_of_the_area_is_drawn_before_the_model(): void
    {
        $this->needsPython();
        $this->fakeServices();
        $r = $this->postJson('/api/tools/map/preview', ['params' => ['type' => 'city', 'side' => '500', 'size' => 150] + self::CENTER])->assertOk();
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $meta = json_decode((string) $r->headers->get('X-Map-Meta'), true);
        $this->assertSame([8, 600], [$meta['buildings'], $meta['width']]);
        $this->assertSame('1 : 3 600', $meta['scale']);
        $png = imagecreatefromstring($r->getFile()->getContent());
        $this->assertSame(600, imagesx($png));
        // the middle of the window is paper, a building is the colour of the buildings
        $rgb = fn (int $x, int $y) => sprintf('#%06x', imagecolorat($png, $x, $y));
        $this->assertSame(MapBuilder::COLORS['base'], $rgb(300 + (int) round(-60 * 138 / 500 * 4), 300 + (int) round(60 * 138 / 500 * 4)), 'a spot with nothing on it');
        $this->assertSame(MapBuilder::COLORS['buildings'], $rgb(300 + (int) round(-105 * 138 / 500 * 4), 300 - (int) round(70 * 138 / 500 * 4)));
    }

    public function test_roofs_and_towers_follow_the_data_and_the_choice(): void
    {
        $this->needsPython();
        $this->fakeServices();
        [$houses, $a] = $this->make(['type' => 'city', 'side' => '500', 'size' => 150, 'frame' => false]);
        [$data, $b] = $this->make(['type' => 'city', 'side' => '500', 'size' => 150, 'frame' => false, 'roofs' => 'data']);
        [$flat, $c] = $this->make(['type' => 'city', 'side' => '500', 'size' => 150, 'frame' => false, 'roofs' => 'flat']);
        $this->assertSame(['ready', 'ready', 'ready'], [$a['status'], $b['status'], $c['status']]);
        // a roof takes material off a block of the same height: a flat town is the heaviest, houses with roofs the lightest
        $this->assertLessThan($c['volume_mm3'], $b['volume_mm3'], 'the hipped roof and the spire are mapped');
        $this->assertLessThan($b['volume_mm3'], $a['volume_mm3'], 'the house without a word has a roof only when houses get one');
        $this->assertSame([2, 1, 0], [$houses->tool_params['notes']['roofs'], $data->tool_params['notes']['roofs'], $flat->tool_params['notes']['roofs']]);
        $this->assertSame(2, $flat->tool_params['notes']['towers'], 'the towers stand whatever the roofs do');
        $this->assertEqualsWithDelta(4 + 42 * 150 / 500, $c['bbox']['z'], 0.2, 'without its spire the tower is as tall: the walls take the whole height (no frame: 150 mm for 500 m)');
        $this->assertSame('houses', $houses->tool_params['roofs']);
        Http::assertSent(fn ($request) => str_contains((string) $request['data'], 'way["building:part"]') && str_contains((string) $request['data'], 'node["man_made"="tower"]'));
    }

    public function test_the_services_down_and_the_daily_limit_are_told_in_words(): void
    {
        $this->needsPython();
        $this->fakeServices('down');
        [$file, $info] = $this->make(['type' => 'city', 'side' => '500']);
        $this->assertSame('failed', $info['status']);
        $this->assertSame('osm_down', $file->error);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'overpass.kumi.systems'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'overpass.openstreetmap.fr'));
        $this->assertStringContainsString('overpass-api.de: 502', (string) ($file->timings['failed_why'] ?? ''), 'the file keeps what every server answered');
        // the error the page shows comes from the file's own words
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertNotSame('map.error.osm_down', __('map.error.osm_down', [], $locale));
            foreach (['osm', 'terrain', 'reading', 'buildings', 'roads', 'writing', 'done'] as $stage) {
                $this->assertNotSame('map.stage.'.$stage, __('map.stage.'.$stage, [], $locale));
            }
        }
        // three maps a day without an account, the fourth is refused; a signed-in visitor has twenty
        RateLimiter::clear('map:ip127.0.0.1');
        $this->fakeServices();
        for ($i = 0; $i < 3; $i++) {
            $this->make(['type' => 'city', 'side' => '500', 'size' => 80, 'frame' => false]);
        }
        $refused = $this->postJson('/api/tools/map', ['params' => ['type' => 'city', 'side' => '500'] + self::CENTER])->assertStatus(429)->assertJsonPath('error', 'daily_limit');
        $this->assertGreaterThan(0, $refused->json('retry_in'));
        $this->assertStringContainsString('24 h', (string) $refused->json('message'));
        $this->make(['type' => 'city', 'side' => '500', 'size' => 80, 'frame' => false], User::factory()->create());
        // an area bigger than the type offers is brought back to what it offers
        [$file] = $this->make(['type' => 'city', 'side' => '20000', 'size' => 80, 'frame' => false], User::factory()->create());
        $this->assertSame('1000', $file->tool_params['side']);
        $this->postJson('/api/tools/map', ['params' => ['type' => 'city', 'lat' => 95, 'lon' => 0]])->assertStatus(422);
    }

    public function test_the_examples_are_drawn_offline_and_the_catalogue_knows_the_tool(): void
    {
        $this->needsPython();
        Http::fake();
        foreach (array_keys((array) config('tools.map.seo.examples')) as $i) {
            $this->assertFileExists(ToolSeo::examplePath('map', $i));
        }
        $this->artisan('matplace:tool-examples', ['tools' => ['map'], '--force' => true])->assertExitCode(0);
        Http::assertNothingSent();
        $this->assertContains('map', ToolSeo::tools());
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertStringContainsString('OpenStreetMap', json_encode(ToolSeo::texts('map', $locale), JSON_UNESCAPED_UNICODE));
        }
    }

    /** The custom G-code of the slicer project: the filament changes, as PrusaSlicer reads them. */
    private function project(ModelFile $file): string
    {
        $r = $this->get('/api/files/'.$file->uuid.'/project.3mf?printer=prusa-mk4s&quality=standard')->assertOk();
        $zip = new \ZipArchive;
        $zip->open($r->baseResponse->getFile()->getPathname());
        $xml = (string) $zip->getFromName('Metadata/custom_gcode_per_layer.xml');
        $zip->close();

        return $xml;
    }
}
