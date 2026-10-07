<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Every tool lives on the one tool page: panel of sections, viewer with its status line, price card, download menu. */
class ToolPageTest extends TestCase
{
    use RefreshDatabase;

    /** tool key → the script module that drives its page */
    private const MODULES = ['relief' => 'relief', 'mold' => 'mold', 'repair' => 'repair', 'check' => 'check', 'figure' => 'figure'];

    private function needsPython(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    public function test_every_tool_route_renders_the_shared_page(): void
    {
        config(['ai.daily_limits.generate_guest' => 1]);
        $tools = collect(config('tools'))->filter(fn ($t) => $t['available'] && Route::has($t['route']));
        $seen = 0;
        foreach ($tools as $key => $tool) {
            $module = isset(ParametricGenerator::FIELDS[$key]) ? 'param' : (self::MODULES[$key] ?? null);
            if ($module === null) {
                continue;                                   // the calculator, the gifts page and the spare-part inquiry are pages of their own
            }
            $page = $this->get(route($tool['route']))->assertOk();
            $html = (string) $page->getContent();
            if (! str_contains($html, 'id="tool-viewer"')) {
                // a tool whose engine is not installed here says so instead of showing a form
                $this->assertStringContainsString('note-warn', $html, $key);

                continue;
            }
            $seen++;
            $page->assertSee('id="tool-page" data-tool="'.$key.'" data-module="'.$module.'"', false)
                ->assertSee('id="tool-nav"', false)->assertSee('data-nav="', false)->assertSee('data-section="', false)   // the numbered sections
                ->assertSee('id="tool-status"', false)                                                                      // the status line
                ->assertSee('id="tool-price-card"', false)->assertSee('id="tool-go"', false)                                // price and the one main action
                ->assertSee('id="tool-download"', false)->assertSee('id="tool-download-menu"', false)                       // the download menu
                ->assertSee('data-view="top"', false)->assertSee('id="tool-xray"', false)->assertSee('id="tool-bed"', false)->assertSee('id="tool-spread"', false)
                ->assertSee('id="tool-undo"', false)->assertSee('id="tool-restore"', false)
                ->assertSee('window.MP_TOOL', false)->assertSee(__('tools.'.$key.'.title'));
            // one orange button on the screen: the main action of the price card
            $this->assertSame(1, substr_count($html, 'class="btn-primary'), $key);
            // every section named in the steps exists
            preg_match_all('/data-nav="([a-z]+)"/', $html, $nav);
            preg_match_all('/data-section="([a-z]+)"/', $html, $sections);
            $this->assertSame($nav[1], $sections[1], $key);
            $this->assertGreaterThanOrEqual(2, count($nav[1]), $key);
            // the steps wrap in the 340 px panel: a row that scrolls sideways cut the fourth one off ("4 St…")
            preg_match('/<nav id="tool-nav" class="([^"]*)"/', $html, $row);
            $this->assertContains('flex-wrap', explode(' ', $row[1] ?? ''), $key);
            $this->assertStringNotContainsString('overflow-x-auto', $row[1] ?? '', $key);
        }
        $this->assertGreaterThanOrEqual(18, $seen);
        // all 15 generators are on it, in every language
        foreach (array_keys(ParametricGenerator::FIELDS) as $kind) {
            $this->assertTrue($tools->has($kind), $kind);
        }
        $this->get('/en/tools/box')->assertOk()->assertSee('X-ray')->assertSee('Download')->assertSee('Top');
        $this->get('/es/tools/vase')->assertOk()->assertSee('Rayos X')->assertSee('Descargar');
    }

    public function test_the_parametric_page_keeps_its_fields_and_adds_sliders_units_and_colours(): void
    {
        $html = (string) $this->get('/tools/box')->assertOk()->getContent();
        foreach (ParametricGenerator::MAIN['box'] as $field) {
            $this->assertStringContainsString('data-param="'.$field.'"', $html);
            $this->assertStringContainsString('data-range="'.$field.'"', $html);   // a slider next to the number
        }
        $this->assertStringContainsString('data-unit="mm"', $html);
        $this->assertStringContainsString('id="tool-parts"', $html);                // the parts and their swatches
        $this->assertStringContainsString('id="param-3mf"', $html);
        // conditional fields fold away by the same attribute as before
        $this->get('/tools/phone-stand')->assertSee('data-when="style=car"', false);
        // sizes that can be dragged in the viewer
        $this->assertStringContainsString('inner_w', (string) json_encode(ParametricGenerator::HANDLES['box']));
        foreach (ParametricGenerator::HANDLES as $kind => $handles) {
            foreach ($handles as $field => $axis) {
                $this->assertArrayHasKey($field, ParametricGenerator::FIELDS[$kind], "{$kind}.{$field}");
                $this->assertContains($axis, ['x', 'y', 'z']);
            }
        }
        // tools that take a picture open the picture window (upload, library, my pictures)
        foreach (ParametricGenerator::ARTWORK as $kind) {
            $this->get(route(config('tools.'.$kind.'.route')))->assertOk()->assertSee('id="param-artwork-open"', false);
        }
        $this->get('/tools/box')->assertDontSee('id="param-artwork-open"', false);
    }

    public function test_no_beta_badge_and_no_emoji_in_the_tool_pages(): void
    {
        $emoji = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}]/u';
        // (the gifts page is left out: its sample names carry the very symbols the sign tool prints)
        foreach (['/tools', '/tools/box', '/tools/relief', '/tools/figure', '/tools/mold', '/tools/repair', '/tools/check'] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/>\s*beta\s*</i', $html, $url);
            $this->assertDoesNotMatchRegularExpression($emoji, (string) preg_replace('/<script\b.*?<\/script>/s', '', $html), $url);
        }
        // the symbols of a text tool stay: they are a function (a click writes one into the text), drawn in the tool's own face
        $sign = (string) $this->get('/tools/sign')->assertOk()->getContent();
        $this->assertStringContainsString('data-symbol="♥" class="tool-symbol"', $sign);
        $withoutSymbols = (string) preg_replace('/<button type="button" data-symbol=.*?<\/button>/su', '', (string) preg_replace('/<script\b.*?<\/script>/s', '', $sign));
        $this->assertDoesNotMatchRegularExpression($emoji, $withoutSymbols);
        // one typeface from our own server, the header has the four entries
        $home = $this->get('/')->assertOk();
        $home->assertSee(__('nav.price'))->assertSee(__('models.nav'))->assertSee(__('footer.tools'))->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_the_preview_says_which_triangles_are_which_piece(): void
    {
        $this->needsPython();
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['lid' => true], 'view' => 'use', 'pieces' => true])->assertOk();
        $meta = json_decode((string) $r->headers->get('X-Model-Meta'), true);
        $this->assertSame(['body', 'lid'], array_column($meta['parts'], 'name'));
        // the ranges follow each other and cover the file exactly
        $stl = file_get_contents($r->baseResponse->getFile()->getPathname());
        $triangles = unpack('V', substr($stl, 80, 4))[1];
        $this->assertSame(84 + 50 * $triangles, strlen($stl));
        $this->assertSame($meta['triangles'], $triangles);
        $at = 0;
        foreach ($meta['parts'] as $part) {
            $this->assertSame($at, $part['tris'][0]);
            $this->assertGreaterThan($at, $part['tris'][1]);
            $this->assertCount(6, $part['bbox']);
            $at = $part['tris'][1];
        }
        $this->assertSame($triangles, $at);
        // the same model as without the grouping: same size, same volume, same number of triangles
        $plain = json_decode((string) $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['lid' => true], 'view' => 'use'])->assertOk()->headers->get('X-Model-Meta'), true);
        $this->assertArrayNotHasKey('parts', $plain);
        $this->assertSame([$plain['bbox'], $plain['volume_mm3'], $plain['triangles']], [$meta['bbox'], $meta['volume_mm3'], $meta['triangles']]);
        // one body is one piece
        $one = json_decode((string) $this->postJson('/api/tools/param/preview', ['kind' => 'organizer', 'params' => [], 'pieces' => true])->assertOk()->headers->get('X-Model-Meta'), true);
        $this->assertSame([[0, $one['triangles']]], array_column($one['parts'], 'tris'));
    }

    public function test_all_parts_come_in_one_zip(): void
    {
        $this->needsPython();
        $r = $this->postJson('/api/tools/param/zip', ['kind' => 'box', 'params' => ['lid' => true]])->assertOk();
        $this->assertSame('application/zip', $r->headers->get('Content-Type'));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($r->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
            $this->assertGreaterThan(84, $zip->statIndex($i)['size']);
        }
        $zip->close();
        sort($names);
        $this->assertSame(['box-body.stl', 'box-lid.stl', 'box.stl'], $names);
        $this->postJson('/api/tools/param/zip', ['kind' => 'box', 'params' => ['inner_w' => 9999]])->assertStatus(422);
    }

    public function test_a_stored_design_opens_again_with_its_settings(): void
    {
        $this->needsPython();
        Storage::fake('models');
        $params = ['inner_w' => 123, 'inner_d' => 45, 'inner_h' => 20, 'lid' => true, 'part_colors' => ['body' => 'blue', 'lid' => 'orange']];
        $uuid = $this->postJson('/api/tools/param', ['kind' => 'box', 'params' => $params])->assertCreated()->json('file.uuid');
        // the page is told which design to load, and the design answers with everything the form needs
        $this->get('/tools/box?from='.$uuid)->assertOk()->assertSee($uuid, false);
        $tool = $this->getJson('/api/files/'.$uuid)->assertOk()->json('file.tool');
        $this->assertSame('box', $tool['kind']);
        $this->assertSame([123, 45, 20, true], [$tool['params']['inner_w'], $tool['params']['inner_d'], $tool['params']['inner_h'], $tool['params']['lid']]);
        $this->assertSame(['body' => ['code' => 'blue', 'hex' => '#213D78'], 'lid' => ['code' => 'orange', 'hex' => '#D1521F']], $tool['params']['part_colors']);
    }

    public function test_verified_by_printing_shows_on_the_card_and_on_the_page(): void
    {
        // the catalogue as it was before anything was printed (config/tools.php carries the real dates since 7 Oct 2026)
        config(['tools' => array_map(fn (array $tool) => array_diff_key($tool, ['verified' => true]), config('tools'))]);
        $this->get('/tools')->assertOk()->assertDontSee('ověřeno tiskem');
        config(['tools.box.verified' => '2026-10-01']);
        $this->get('/tools')->assertOk()->assertSee('ověřeno tiskem')->assertSee('1. 10. 2026');
        $this->get('/tools/box')->assertOk()->assertSee('ověřeno tiskem');
        $this->get('/en/tools/box')->assertOk()->assertSee('verified by printing');
        $this->get('/tools/vase')->assertOk()->assertDontSee('ověřeno tiskem');
    }

    public function test_the_catalogue_has_categories_search_words_and_cards(): void
    {
        $known = ['images', 'names', 'home', 'parts', 'toys', 'signs', 'craft', 'edit', 'sell'];
        foreach (config('tools') as $key => $tool) {
            $this->assertNotEmpty($tool['categories'], $key);
            $this->assertSame([], array_diff($tool['categories'], $known), "{$key} has a category the catalogue does not know");
            if (! $tool['available']) {
                continue;
            }
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('tools.keywords.'.$key, __('tools.keywords.'.$key, [], $locale), "{$key} has no keywords in {$locale}");
                $this->assertNotSame('tools.'.$key.'.title', __('tools.'.$key.'.title', [], $locale), "{$key} has no title in {$locale}");
                $this->assertNotSame('tools.'.$key.'.hint', __('tools.'.$key.'.hint', [], $locale), "{$key} has no hint in {$locale}");
            }
            $this->assertTrue(Route::has($tool['route']), $key);
        }
        foreach ($known as $cat) {
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('tools.cats.'.$cat, __('tools.cats.'.$cat, [], $locale));
            }
        }
        $page = $this->get('/tools')->assertOk();
        $page->assertSee('id="tool-search"', false)->assertSee('data-filter="home"', false)->assertSee('data-filter="edit"', false)->assertSee(__('tools.cats.images'))
            ->assertDontSee('data-filter="toys"', false)                                   // no tool in it yet: no empty filter
            ->assertSee('data-cats="home parts"', false)                                   // the box
            ->assertSee('krabicka vicko pouzdro', false);                                  // its keywords, without accents, for the search
        $this->get('/en/tools')->assertOk()->assertSee('Search the tools')->assertSee('Pictures and logos');
    }
}
