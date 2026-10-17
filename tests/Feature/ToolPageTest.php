<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ToolVisibility;
use App\Models\User;
use App\Support\PreviewMeta;
use App\Support\WarningTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Every tool lives on the one tool page: panel of sections, viewer with its status line, price card, download menu. */
class ToolPageTest extends TestCase
{
    use RefreshDatabase;

    /** tool key → the script module that drives its page */
    private const MODULES = ['relief' => 'relief', 'mold' => 'mold', 'repair' => 'repair', 'check' => 'check', 'figure' => 'figure', 'filament_art' => 'art', 'split' => 'edit', 'hollow' => 'edit', 'life_size' => 'edit', 'puzzle' => 'edit', 'holder_model' => 'edit', 'potion' => 'edit', 'flexi_cut' => 'edit', 'colors' => 'edit', 'soap_model' => 'edit', 'wearable' => 'edit', 'slider' => 'edit'];

    /** warnings that are advice on the technique, said whatever the numbers are: a thread and a sealing lip are tried on one piece first */
    private const ADVICE = ['thread_try', 'seal_try'];

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
                ->assertSee('id="tool-go-card"', false)->assertSee('id="tool-go"', false)                                   // the one main action
                ->assertDontSee('id="tool-price"', false)->assertDontSee('Orientační cena')                                 // no estimate: the price shows first in the calculation
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
            // a generator kept out of the catalogue until its print was tried has a page (HeldShapesTest), but no card
            if (config('tools.'.$kind.'.available') === false) {
                continue;
            }
            $this->assertTrue($tools->has($kind), $kind);
        }
        $this->get('/en/tools/box')->assertOk()->assertSee('X-ray')->assertSee('Download')->assertSee('Top');
        $this->get('/es/tools/vase')->assertOk()->assertSee('Rayos X')->assertSee('Descargar');
    }

    public function test_the_button_leads_to_the_calculation_and_the_next_step_is_said_once(): void
    {
        // what stands under the buttons of a tool's page
        $note = function (string $path): string {
            preg_match('/<p class="w-full text-xs text-muted">(.*?)<\/p>/s', (string) $this->get($path)->assertOk()->getContent(), $m);

            return html_entity_decode(trim($m[1] ?? ''));
        };
        // the farm prints for everybody: the site as it runs
        config(['features.marketplace' => false, 'farm.enabled' => true, 'farm.open' => true, 'farm.public' => true]);
        $this->get('/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Pokračovat</span>', false)
            ->assertDontSee('Pokračovat ke kalkulaci')->assertDontSee('Odhad z objemu')->assertDontSee('Orientační cena')
            ->assertDontSee('Pokračovat k přesné ceně a tisku')
            ->assertDontSee('V dalším kroku uvidíte přesnou cenu a dobu tisku. V dalším kroku')
            ->assertDontSee('spočítáme v dalším kroku');
        $said = 'V kalkulaci uvidíte přesnou cenu a dobu tisku. Výtisk si objednáte u nás, nebo si stáhnete soubor pro svou tiskárnu.';
        $this->assertSame($said, $note('/tools/letter-beads'));
        // every tool says the same: a picture, a photo, a file to edit, a check, a repair, a mold all go on to the calculator
        // (the page opens it with the very file), so no tool has a button or a sentence of its own
        $seen = 0;
        foreach (config('tools') as $key => $tool) {
            if (! $tool['available'] || ! Route::has($tool['route']) || (! isset(ParametricGenerator::FIELDS[$key]) && ! isset(self::MODULES[$key]))) {
                continue;                                   // the calculator, the gifts page and the spare-part inquiry are pages of their own
            }
            $html = (string) $this->get(route($tool['route']))->assertOk()->getContent();
            preg_match('/<span id="tool-go-label">(.*?)<\/span>/s', $html, $button);
            preg_match('/<p class="w-full text-xs text-muted">(.*?)<\/p>/s', $html, $under);
            $this->assertSame('Pokračovat', trim($button[1] ?? ''), $key);
            $this->assertSame($said, html_entity_decode(trim($under[1] ?? '')), $key);
            // and ends with the same step: the material and how many pieces, once, after the tool's own steps
            $this->assertSame(1, substr_count($html, 'id="param-material"'), $key);
            $this->assertSame(1, substr_count($html, 'id="param-qty"'), $key);
            preg_match_all('/data-nav="([a-z]+)" class="tool-nav-item"><span class="tool-nav-no">\d+<\/span>([^<]*)</', $html, $steps);
            preg_match_all('/data-section="([a-z]+)"/', $html, $sections);
            $this->assertSame('print', end($steps[1]), $key);
            $this->assertSame('Materiál a počet kusů', trim((string) end($steps[2])), $key);
            $this->assertSame('print', end($sections[1]), $key);
            $this->assertGreaterThanOrEqual(3, count($steps[1]), $key);
            preg_match('/<section id="sec-print".*?<\/section>/s', $html, $step);
            $this->assertStringContainsString('Materiál a počet kusů', $step[0] ?? '', $key);
            $this->assertStringContainsString('max="1000"', $step[0] ?? '', $key);
            $seen++;
        }
        $this->assertGreaterThanOrEqual(50, $seen);
        $this->get('/en/tools/relief')->assertOk()->assertSee('<span id="tool-go-label">Continue</span>', false)->assertDontSee('Print it with us')->assertDontSee('Rough price');
        $this->get('/en/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Continue</span>', false);
        $this->assertSame('The calculation shows the precise price and print time. Order the print from us, or download the file for your own printer.', $note('/en/tools/letter-beads'));
        $this->get('/es/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Continuar</span>', false)->assertDontSee('Precio orientativo');
        $this->assertSame(1, substr_count($note('/es/tools/letter-beads'), 'precio exacto'));

        // the farm not open to the public yet: the same button, the calculation ends with a download
        config(['farm.public' => false]);
        $this->get('/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Pokračovat</span>', false);
        $this->assertSame('V kalkulaci uvidíte přesnou dobu tisku a spotřebu materiálu a stáhnete si soubor nebo hotový projekt pro svou tiskárnu. Bez registrace.', $note('/tools/letter-beads'));
    }

    public function test_a_tool_that_edits_a_file_says_what_it_is_doing_in_words_never_in_a_key(): void
    {
        // the phases the tools report while they work (engines/python/edit_tool.py `stage`), and "queued" before they start
        preg_match_all('/stage\(dst, "([a-z_]+)"\)/', (string) file_get_contents(base_path('engines/python/edit_tool.py')), $reported);
        $stages = array_values(array_unique(['queued', ...$reported[1]]));
        $this->assertContains('repairing', $stages);
        foreach (['cs', 'en', 'es'] as $locale) {
            foreach ($stages as $stage) {
                $this->assertNotSame('edit.stage.'.$stage, __('edit.stage.'.$stage, [], $locale), "edit.stage.{$stage} has no text in {$locale}");
            }
        }
        // hollowing a statue said "edit.stage.repairing": only the splitter had words for the phases
        $ops = ['holder_model' => 'holder', 'soap_model' => 'soap'];
        foreach (array_keys(self::MODULES, 'edit', true) as $tool) {
            $op = $ops[$tool] ?? $tool;
            foreach (['', '/en', '/es'] as $prefix) {
                $html = (string) $this->get($prefix.'/tools/'.basename(route(config('tools.'.$tool.'.route'))))->assertOk()->getContent();
                foreach ($stages as $stage) {
                    $this->assertStringContainsString('edit.'.$op.'.stage.'.$stage, $html, "{$tool}: no text for the phase {$stage}");
                }
                // no text the page hands its script is a key (in the page's data a text sits between " … ")
                $this->assertDoesNotMatchRegularExpression('/:\\\\u0022edit\.[a-z0-9_.]+\\\\u0022/', $html, $prefix.' '.$tool);
            }
        }
    }

    public function test_a_warning_of_a_parametric_tool_is_said_in_words_never_in_a_key(): void
    {
        // what the engines of the parametric tools may warn about: `warn.append("thin_lines")`, `warn = ["missing_chars"] if …`,
        // `"stand_angle_%d" % used`. The engines of the tools that edit a file, make a picture for the wall, a map or a
        // mold have texts of their own (edit.*, map.*, mold.*); every other engine file is a parametric tool's.
        $codes = [];
        foreach (glob(base_path('engines/python/*.py')) as $file) {
            if (in_array(basename($file, '.py'), ['art_tool', 'colors_tool', 'edit_tool', 'map_tool', 'mold_tool'], true)) {
                continue;
            }
            foreach (preg_grep('/warn/', (array) file($file)) as $line) {
                preg_match_all('/\.append\("([a-z0-9_%]+)"|(?<![\w\])])\[\(?"([a-z0-9_]+)"\)?\]/', $line, $m);
                foreach (array_filter([...$m[1], ...$m[2]]) as $code) {
                    $codes[$code][basename($file, '.py')] = true;
                }
            }
        }
        $codes = array_map('array_keys', $codes);
        foreach (['thin_flutes', 'steep_twist', 'missing_chars', 'stand_angle_%d', 'magnet_no_room', 'seal_try'] as $known) {
            $this->assertArrayHasKey($known, $codes);
        }
        $this->assertGreaterThanOrEqual(25, count($codes));
        foreach (['cs', 'en', 'es'] as $locale) {
            app()->setLocale($locale);
            $said = [...WarningTexts::codes('param.warn.'), ...WarningTexts::codes('param.shape.warn.')];
            foreach ($codes as $code => $files) {
                // "stand_angle_%d": the angle the stand was kept to, a text for each of them
                $names = str_contains($code, '%d') ? preg_grep('/^'.str_replace('%d', '\d+', preg_quote($code, '/')).'$/', $said) : [$code];
                $this->assertNotEmpty($names, "{$code} has no text in {$locale}");
                foreach ($names as $name) {
                    // a tool of the picture in colours may have a wording of its own, every other tool reads the general one
                    $keys = $files === ['shape_kinds'] ? ['param.shape.warn.'.$name, 'param.warn.'.$name] : ['param.warn.'.$name];
                    $texts = array_filter($keys, fn ($key) => Lang::hasForLocale($key, $locale) && trim((string) __($key, [], $locale)) !== '');
                    $this->assertNotEmpty($texts, "{$name} (".implode(', ', $files).") has no text in {$locale}");
                }
            }
        }
        app()->setLocale('cs');

        // and every one of them is handed to the page's script: the vase said "param.warn.thin_flutes" in a yellow frame
        $this->needsPython();
        $q = '\\'.'u0022';                                 // a quote, as the page's data spell it
        $vase = (string) $this->get('/tools/vase')->assertOk()->getContent();
        $this->assertStringContainsString('param.warn.thin_flutes'.$q.':'.$q.'Mezi ', $vase);
        $this->assertStringContainsString('param.warn.steep_twist'.$q.':'.$q, $vase);
        $this->get('/en/tools/phone-stand')->assertOk()->assertSee('param.warn.stand_angle_55'.$q.':'.$q.'This stand shape', false);
        $this->get('/tools/magnet')->assertOk()->assertSee('shape.warn.magnet_shows'.$q.':'.$q, false)->assertSee('param.warn.thin_lines'.$q.':'.$q, false);
        $this->get('/tools/papel-picado')->assertOk()->assertSee('param.warn.portrait_fine'.$q.':'.$q, false);
    }

    public function test_no_tool_opens_with_a_warning_and_no_preset_brings_one(): void
    {
        $this->needsPython();
        // what a page of a parametric tool asks its first preview for (param.ts): the defaults of the fields, the preset
        // its address starts with, the sample texts, one of our pictures; and the same with each preset it offers
        $states = [];
        foreach (config('tools') as $key => $tool) {
            $route = Route::getRoutes()->getByName($tool['route']);
            $kind = $route?->defaults['kind'] ?? null;
            if (! is_string($kind) || ! isset(ParametricGenerator::FIELDS[$kind]) || $kind === 'modular') {
                continue;                                   // the modular organizer opens empty: its bins are drawn by the visitor
            }
            $as = (string) ($route->defaults['as'] ?? $kind);
            $texts = array_filter(array_map(fn (array $t) => $t[2], ParametricGenerator::TEXTS[$kind] ?? []), fn ($text) => $text !== '');
            $sample = $kind === 'compose' ? null : (ParametricGenerator::SAMPLE[$as] ?? ParametricGenerator::SAMPLE[$kind] ?? null);
            if (! $texts && ! $sample && in_array($kind, ParametricGenerator::ARTWORK, true)) {
                continue;                                   // opens empty and waits for the visitor's picture (the drawer insert)
            }
            $given = $texts + ($sample ? ['artwork' => $sample] : []) + ($kind === 'papel' ? ['border_mm' => 20] : []);       // the panel opens as a cut-out
            $presets = ParametricGenerator::PRESETS[$kind] ?? [];
            $states[$key] = [$kind, ($presets[$route->defaults['preset'] ?? ''] ?? []) + $given];
            foreach ($presets as $name => $preset) {
                $states[$key.' · '.$name] = [$kind, $preset + $given];
            }
        }
        $this->assertGreaterThanOrEqual(70, count($states));
        $this->assertArrayHasKey('vase · spiral', $states);
        $generator = app(ParametricGenerator::class);
        $said = [];
        $seen = [];
        foreach ($states as $name => [$kind, $params]) {
            $same = $kind.' '.json_encode($params);
            if (isset($seen[$same])) {
                continue;                                   // a page that opens with one of its presets
            }
            $seen[$same] = true;
            try {
                $built = $generator->build($kind, $params);
                @unlink($built['path']);
                $warnings = array_diff((array) ($built['meta']['notes']['warnings'] ?? []), self::ADVICE);
                if ($warnings) {
                    $said[$name] = implode(' ', $warnings);
                }
            } catch (ValidationException $e) {
                $said[$name] = 'cannot be made: '.implode(' ', $e->validator->errors()->all());
            }
        }
        // one note stays, because it is the answer to a choice: the word LOGO cut out alone is four loose letters, and the
        // page says so when the visitor picks "the outline alone" for a text
        $this->assertSame('separate_pieces', $said['logo · extrude'] ?? null);
        unset($said['logo · extrude']);
        // the first thing a visitor saw of the vase was a yellow frame: "the wall between the flutes is thin"
        $this->assertSame([], $said, 'tools that warn before the visitor has changed anything');
    }

    public function test_no_tool_page_hands_its_script_a_key_instead_of_a_text(): void
    {
        // the names texts are kept under: lang/cs/<name>.php and the first word of the keys of lang/cs.json
        $groups = array_map(fn ($file) => basename($file, '.php'), glob(lang_path('cs/*.php')));
        foreach (array_keys((array) json_decode((string) file_get_contents(lang_path('cs.json')), true)) as $key) {
            $groups[] = explode('.', (string) $key)[0];
        }
        $groups = implode('|', array_map(fn ($g) => preg_quote($g, '/'), array_unique(array_filter($groups, fn ($g) => preg_match('/^[a-z_]+$/', $g)))));
        // a text that is a key: "param.warn.thin_flutes" between quotes, as a value (in the page's data a quote is spelt as its code or stands as it is)
        $bare = '/:\s*(?:\\\\u0022|")((?:'.$groups.'|shape|compose|cookie)\.[a-z0-9_]+(?:\.[a-z0-9_]+)*)(?:\\\\u0022|")/';
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);        // the tools kept out of the catalogue have pages too
        // the addresses first: after a page in another language is asked for, route() answers in that language
        $paths = [];
        foreach (config('tools') as $key => $tool) {
            if (! Route::has($tool['route']) || (! isset(ParametricGenerator::FIELDS[$key]) && ! isset(self::MODULES[$key]))) {
                continue;                                   // the calculator, the gifts page and the spare-part inquiry are pages of their own
            }
            $paths[] = (string) parse_url(route($tool['route']), PHP_URL_PATH);
        }
        $this->assertGreaterThanOrEqual(55, count($paths));
        $keys = [];
        foreach ($paths as $path) {
            foreach (['', '/en', '/es'] as $prefix) {
                $html = (string) $this->actingAs($admin)->get($prefix.$path)->assertOk()->getContent();
                if (preg_match_all($bare, $html, $found)) {
                    $keys[$prefix.$path] = implode(' ', array_unique($found[1]));
                }
            }
        }
        $this->assertSame([], $keys, 'pages that hand their script keys without a text');
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
        // tools that take a picture open the picture window (upload, library, my pictures); `form` asks for the form
        // where the address opens the composer of layers now (the key ring)
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        foreach (ParametricGenerator::ARTWORK as $kind) {
            // a generator kept out of the catalogue has a page for an admin only (ToolVisibility)
            if (! ToolVisibility::isPublic($kind)) {
                $this->actingAs($admin);
            }
            $this->get(route(config('tools.'.$kind.'.route'), ['form' => 1]))->assertOk()->assertSee('id="param-artwork-open"', false);
            auth()->logout();
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
        $home->assertSee(__('nav.price'))->assertDontSee(__('models.nav'))->assertSee(__('footer.tools'))->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_the_preview_says_which_triangles_are_which_piece(): void
    {
        $this->needsPython();
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['lid' => true], 'view' => 'use', 'pieces' => true])->assertOk();
        $meta = PreviewMeta::whole($r->headers->get('X-Model-Meta'));
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
        $plain = PreviewMeta::whole($this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['lid' => true], 'view' => 'use'])->assertOk()->headers->get('X-Model-Meta'));
        $this->assertArrayNotHasKey('parts', $plain);
        $this->assertSame([$plain['bbox'], $plain['volume_mm3'], $plain['triangles']], [$meta['bbox'], $meta['volume_mm3'], $meta['triangles']]);
        // one body is one piece
        $one = PreviewMeta::whole($this->postJson('/api/tools/param/preview', ['kind' => 'organizer', 'params' => [], 'pieces' => true])->assertOk()->headers->get('X-Model-Meta'));
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

    public function test_verified_by_printing_shows_on_the_page_but_not_on_the_card(): void
    {
        // the catalogue as it was before anything was printed (config/tools.php carries the real dates since 7 Oct 2026)
        config(['tools' => array_map(fn (array $tool) => array_diff_key($tool, ['verified' => true]), config('tools'))]);
        $this->get('/tools')->assertOk()->assertDontSee('ověřeno tiskem');
        config(['tools.box.verified' => '2026-10-01']);
        // the cards stopped carrying the badge on 13 Oct 2026 (Roman); the tool's own page still says it
        $this->get('/tools')->assertOk()->assertDontSee('ověřeno tiskem');
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
            ->assertSee('data-filter="toys"', false)                                       // the biscuit with icing is its first tool
            ->assertSee('data-cats="home parts"', false)                                   // the box
            ->assertSee('krabicka vicko pouzdro', false);                                  // its keywords, without accents, for the search
        $this->get('/en/tools')->assertOk()->assertSee('Search the tools')->assertSee('Pictures and logos');
        // a category without a tool has no filter: nothing to click that shows nothing
        // (whichever tools are in it today: the category is taken from all of them)
        config(['tools' => array_map(fn (array $tool) => ['categories' => array_values(array_diff($tool['categories'], ['toys']))] + $tool, config('tools'))]);
        $this->get('/tools')->assertOk()->assertDontSee('data-filter="toys"', false);
    }
}
