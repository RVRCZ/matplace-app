<?php

namespace Tests\Feature;

use App\Domain\Farm\Palette;
use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ReliefGenerator;
use App\Domain\Tools\ToolInput;
use App\Domain\Tools\ToolVisibility;
use App\Http\Controllers\Api\GenerationController;
use App\Models\User;
use App\Support\PreviewMeta;
use App\Support\WarningTexts;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    private const MODULES = ['relief' => 'relief', 'mold' => 'mold', 'repair' => 'repair', 'check' => 'check', 'figure' => 'figure', 'filament_art' => 'art', 'split' => 'edit', 'hollow' => 'edit', 'life_size' => 'edit', 'puzzle' => 'edit', 'holder_model' => 'edit', 'potion' => 'edit', 'flexi_cut' => 'edit', 'colors' => 'edit', 'soap_model' => 'edit', 'wearable' => 'edit', 'slider' => 'edit', 'gcode' => 'edit'];

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
            // one orange button on the screen: the main action of the price card; a bust or a pet has its "Create" in the
            // photo dialog, and while that is on the screen the way on waits in grey (app.css, #figure-create)
            $this->assertSame(str_contains($html, 'id="figure-create"') ? 2 : 1, substr_count($html, 'class="btn-primary'), $key);
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
        // the button says what happens (the audit, Roman 17 Oct 2026; "Pokračovat" from 16 Oct said nothing)
        $this->get('/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Spočítat cenu a možnosti tisku</span>', false)
            ->assertDontSee('<span id="tool-go-label">Pokračovat</span>', false)->assertSee('Stáhnout model')
            ->assertDontSee('Pokračovat ke kalkulaci')->assertDontSee('Odhad z objemu')->assertDontSee('Orientační cena')
            ->assertDontSee('Pokračovat k přesné ceně a tisku')
            ->assertDontSee('V dalším kroku uvidíte přesnou cenu a dobu tisku. V dalším kroku')
            ->assertDontSee('spočítáme v dalším kroku');
        $said = 'V kalkulaci uvidíte přesnou cenu a dobu tisku. Výtisk si pak objednáte u nás, nebo stáhnete model pro svou tiskárnu.';
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
            $this->assertSame('Spočítat cenu a možnosti tisku', trim($button[1] ?? ''), $key);
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
        $this->get('/en/tools/relief')->assertOk()->assertSee('<span id="tool-go-label">Get the price and print options</span>', false)->assertDontSee('Print it with us')->assertDontSee('Rough price');
        $this->get('/en/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Get the price and print options</span>', false)->assertSee('Download the model');
        $this->assertSame('The calculation shows the exact price and print time. Then order the print from us, or download the model for your own printer.', $note('/en/tools/letter-beads'));
        $this->get('/es/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Calcular precio y opciones de impresión</span>', false)->assertDontSee('Precio orientativo');
        $this->assertSame(1, substr_count($note('/es/tools/letter-beads'), 'el precio y el tiempo de impresión exactos'));

        // the farm not open to the public yet: the same button, the calculation ends with a download
        config(['farm.public' => false]);
        $this->get('/tools/letter-beads')->assertOk()->assertSee('<span id="tool-go-label">Spočítat cenu a možnosti tisku</span>', false);
        $this->assertSame('V kalkulaci uvidíte přesnou cenu a dobu tisku a stáhnete si model nebo projekt pro svou tiskárnu. Bez registrace.', $note('/tools/letter-beads'));
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
            if (in_array(basename($file, '.py'), ['art_tool', 'colors_tool', 'edit_tool', 'gcode_tool', 'map_tool', 'mold_tool'], true)) {
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
            $presets = ParametricGenerator::presets($kind);      // as the page gets them: colours resolved to spools
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

    public function test_the_medal_opens_at_first_place_and_offers_the_three_places(): void
    {
        // without the farm the places take the built-in colours: yellow, grey and orange medals with a red number
        $built = ParametricGenerator::presets('medallion');
        $this->assertSame(['place1', 'place2', 'place3'], array_keys($built));
        $this->assertSame(['1', '2', '3'], array_column($built, 'line1'));
        $this->assertSame(['yellow', 'grey', 'orange'], array_map(fn ($p) => $p['part_colors']['body'], array_values($built)));
        $this->assertSame('red', $built['place1']['part_colors']['color_1']);

        // with the farm's catalogue: gold, silver and copper Silk medals (no Marrakech bronze in the catalogue yet), red Silk number
        $this->seed(FarmSeeder::class);
        app()->forgetInstance(Palette::class);
        $farm = ParametricGenerator::presets('medallion');
        $this->assertSame(['39_PLA_Silk_zlata_Silk_Gold', '43_PLA_Silk_stribrna_Silk_Silver', '40_PLA_Silk_medena_Silk_Copper'], array_map(fn ($p) => $p['part_colors']['body'], array_values($farm)));
        $this->assertSame('44_PLA_Silk_cervena_Silk_Red', $farm['place3']['part_colors']['color_1']);

        foreach (['cs' => ['1. místo', '2. místo', '3. místo', 'Štít', 'Rozeta', 'Zarovnaný', 'Vystouplý', 'Velikost motivu', 'Lem'], 'en' => ['1st place', '2nd place', '3rd place', 'Shield', 'Rosette', 'Flush', 'Raised', 'Size of the motif', 'Rim'], 'es' => ['1.er puesto', '2.º puesto', '3.er puesto', 'Escudo', 'Roseta', 'Enrasado', 'En relieve', 'Tamaño del motivo', 'Borde']] as $lang => $words) {
            $page = $this->get($this->localized('/tools/medallion', $lang))->assertOk();
            foreach ($words as $word) {
                $page->assertSee($word);
            }
            $page->assertSee('data-preset="place1"', false)->assertSee('data-preset="place3"', false);
            $this->assertStringContainsString('39_PLA_Silk_zlata_Silk_Gold', $page->getContent(), 'the page hands its script the farm\'s spool');
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools)\.[a-z_.0-9]+\s*</', $page->getContent(), $lang);
        }
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

    /** docs/AF.md §1: on a phone the name and its sentence come first, then the model with one "View" button, then the way on. */
    public function test_on_a_phone_the_name_and_the_sentence_come_before_the_model_and_the_bar_folds(): void
    {
        foreach (['qr', 'sign', 'box', 'relief'] as $tool) {
            $html = (string) $this->get(route(config('tools.'.$tool.'.route')))->assertOk()->getContent();
            if (! str_contains($html, 'id="tool-viewer"')) {
                continue;
            }
            $h1 = strpos($html, '<h1');
            $lead = strpos($html, 'id="tool-lead"');
            $viewer = strpos($html, 'id="tool-viewer"');
            $go = strpos($html, 'id="tool-go-card"');
            $this->assertTrue($h1 < $lead && $lead < $viewer && $viewer < $go, "{$tool}: h1, sentence, viewer, action card");
            // the order on the screen under lg: the head, the stage, the panel (the panel stays first in the DOM on lg)
            foreach (['tool-head' => 'order-1', 'tool-stage' => 'order-2', 'tool-panel' => 'order-3'] as $id => $order) {
                $this->assertMatchesRegularExpression('/id="'.$id.'"[^>]*class="[^"]*\b'.$order.'\b/', $html, "{$tool} {$id}");
            }
            // one button "View" opens the bar of views, which stays as it was on lg
            $this->assertMatchesRegularExpression('/id="tool-view-toggle"[^>]*aria-controls="tool-viewbar"[^>]*>.*?'.preg_quote(__('toolpage.views'), '/').'/s', $html);
            $this->assertStringContainsString('id="tool-viewbar"', $html);
        }
        $this->get('/en/tools/box')->assertOk()->assertSee('id="tool-view-toggle"', false)->assertSee('View');
    }

    /** docs/AF.md §1, §3 and §7: a tool that starts from a photo shows a printed piece and says the time and the size before the upload. */
    public function test_a_photo_tool_shows_a_printed_piece_and_says_time_and_size_before_the_upload(): void
    {
        $html = (string) $this->get('/tools/relief')->assertOk()->getContent();
        if (str_contains($html, 'id="tool-viewer"')) {
            $this->assertStringContainsString('id="tool-sample"', $html);
            if (is_file(public_path('img/tools/relief-800.jpg'))) {
                $this->assertStringContainsString('img/tools/relief-800.jpg', $html);
            }
            $this->assertStringContainsString(e(__('toolpage.sample.minute')), $html);
            $this->assertStringContainsString(e(__('photo.limit', ['mb' => ReliefGenerator::PHOTO_MB])), $html);
            $this->assertStringContainsString(e(__('relief.before')), $html);
            $this->assertStringNotContainsString('pár vteřin', $html);
        }
        // a tool with a model from the start has no sample
        $this->assertStringNotContainsString('id="tool-sample"', (string) $this->get('/tools/box')->getContent());
        // the bust and the pet: the photos go in right in the empty viewer, the limit and the time over the button
        config(['ai.daily_limits.generate_guest' => 1, 'ai.daily_limits.generate_user' => 3]);
        foreach (['/tools/figure', '/tools/pet-figurine'] as $url) {
            $page = (string) $this->get($url)->getContent();
            if (! str_contains($page, 'id="tool-viewer"') || ! str_contains($page, 'id="figure-form"')) {
                continue;                                   // the generator is off here
            }
            $this->assertStringContainsString('id="figure-drop"', $page, $url);
            $this->assertStringContainsString(e(__('photo.add')), $page, $url);
            $limits = strpos($page, e(__('figure.limits', ['n' => 1, 'm' => 3])));
            $this->assertNotFalse($limits, $url);
            $this->assertLessThan(strpos($page, 'id="figure-submit"'), $limits, "{$url}: the limit and the time come before the button");
            $this->assertStringNotContainsString('otevírám kalkulaci', $page, $url);
        }
    }

    /** docs/AF.md §2: on a phone the catalogue is folded by category, each with its count, the first one open. */
    public function test_the_catalogue_on_a_phone_folds_the_categories_with_their_counts(): void
    {
        $html = (string) $this->get('/tools')->assertOk()->getContent();
        $this->assertStringContainsString('id="tool-cats"', $html);
        $phone = substr($html, (int) strpos($html, 'id="tool-cats"'));
        $phone = substr($phone, 0, (int) strpos($phone, 'class="hidden lg:block"'));
        $parts = explode('<section data-tool-section data-cat="', $phone);
        array_shift($parts);
        $this->assertGreaterThanOrEqual(5, count($parts));
        foreach ($parts as $i => $part) {
            preg_match('/^([a-z]+)"/', $part, $cat);
            preg_match('/data-total="(\d+)"/', $part, $total);
            // the count says how many rows the fold holds, and every row is a tool of that category
            $this->assertSame((int) $total[1], substr_count($part, 'data-key="'), $cat[1]);
            $this->assertSame((int) $total[1], preg_match_all('/data-cats="[^"]*\b'.$cat[1].'\b[^"]*"/', $part), $cat[1]);
            $this->assertStringContainsString(e(__('tools.cats.'.$cat[1])), $part);
            $this->assertStringContainsString('aria-expanded="'.($i === 0 ? 'true' : 'false').'"', $part, $cat[1]);
        }
        // the chips in one row that scrolls sideways on a phone, wrapping on lg
        $this->assertMatchesRegularExpression('/class="[^"]*overflow-x-auto[^"]*lg:flex-wrap[^"]*" role="group"/', $html);
        // lg keeps its three columns of big cards by what the visitor comes with
        $this->assertStringContainsString('lg:grid-cols-3', $html);
    }

    /** docs/AF.md §3: a photo over the limit is refused in the visitor's language, never in Laravel's English. */
    public function test_a_photo_over_the_limit_is_refused_in_the_visitors_language(): void
    {
        $big = UploadedFile::fake()->create('big.jpg', ReliefGenerator::PHOTO_MB * 1024 + 500, 'image/jpeg');
        $res = $this->post('/api/tools/relief', ['photo' => $big], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(__('photo.too_big', ['mb' => ReliefGenerator::PHOTO_MB]), $res->json('message'));
        $this->assertStringContainsString('15 MB', (string) $res->json('message'));
        $this->assertStringNotContainsString('must not be greater', (string) $res->getContent());
        $this->assertStringNotContainsString('kilobytes', (string) $res->getContent());
        // not a photo at all
        $txt = UploadedFile::fake()->create('notes.txt', 3, 'text/plain');
        $res = $this->post('/api/tools/relief', ['photo' => $txt], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(__('photo.not_image'), $res->json('message'));
        // the bust and the pet: their own limit, the same words
        $this->assertSame(__('photo.too_big', ['mb' => GenerationController::PHOTO_MB]), GenerationController::photoMessages()['image.max']);
        $this->assertSame(__('photo.too_big', ['mb' => GenerationController::PHOTO_MB]), GenerationController::photoMessages()['image_left.uploaded']);
    }

    /**
     * docs/AH.md §2 (the audit, point 11): every text a tool page asks for exists in Czech, English and Spanish, those in
     * the templates and those in the lists the pages hand to their scripts.
     *
     * @return array<string, list<string>> file => keys
     */
    public static function toolPageKeys(): array
    {
        return self::keysIn(array_merge(glob(resource_path('views/tools/*.blade.php')), glob(resource_path('views/components/tool-*.blade.php'))));
    }

    /**
     * The texts a set of templates asks for: written in the template, or in a list handed to the scripts
     * (collect([...])->mapWithKeys(…) or a $keys = [...] list mapped the same way, as the order page does).
     *
     * @param  list<string>  $files
     * @return array<string, list<string>> per file
     */
    public static function keysIn(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            preg_match_all('/(?:__|trans|NextStep::text)\(\s*\'([a-z0-9_.\-]+)\'\s*[,)]/i', $src, $direct);
            preg_match_all('/(?:collect\(\[([^\]]*)\]\)\s*->mapWithKeys\(fn \(\$k\) => \[\$k => (?:__|\\\\App\\\\Support\\\\NextStep::text)\(\$k|\$keys = \[([^\]]*)\];)/', $src, $lists);
            // a $keys list counts only where it is handed over whole (elsewhere its keys are parts joined later)
            $whole = str_contains($src, 'collect($keys)->mapWithKeys(fn ($k) => [$k => __($k)])');
            $lists[1] = array_merge(array_filter($lists[1]), $whole ? array_filter($lists[2]) : []);
            $keys = $direct[1];
            foreach ($lists[1] as $list) {
                preg_match_all('/\'([a-z0-9_.\-]+)\'/i', $list, $more);
                $keys = array_merge($keys, $more[1]);
            }
            $out[basename(dirname($file)).'/'.basename($file)] = array_values(array_unique(array_filter($keys, fn ($k) => str_contains($k, '.') && ! str_ends_with($k, '.'))));
        }

        return $out;
    }

    public function test_every_text_of_the_tool_pages_exists_in_all_three_languages(): void
    {
        $all = self::toolPageKeys();
        $count = array_sum(array_map('count', $all));
        $this->assertGreaterThan(450, $count, 'the scan finds the texts of the templates');
        $this->assertContains('figure.generating', $all['tools/figure.blade.php'], 'and those of the lists for the scripts');
        $missing = [];
        foreach ($all as $file => $keys) {
            foreach ($keys as $key) {
                foreach (['cs', 'en', 'es'] as $locale) {
                    if (trans($key, [], $locale) === $key) {
                        $missing[] = "{$file}: {$key} ({$locale})";
                    }
                }
            }
        }
        $this->assertSame([], $missing);
    }

    /** docs/AH.md §3 (the audit, point 8): our picks first, every card says what is entered, words that mean the same find the same tools. */
    public function test_the_catalogue_starts_with_our_picks_and_says_what_each_tool_asks_for(): void
    {
        $html = (string) $this->get('/tools')->assertOk()->getContent();
        preg_match('/<section id="tool-picks".*?<\/section>/s', $html, $picks);
        $this->assertNotEmpty($picks, 'our picks are on the page');
        $this->assertStringContainsString(e(__('tools.picks')), $picks[0]);
        $this->assertStringContainsString('data-when-unfiltered', $picks[0], 'they step aside while a filter or a search is on');
        foreach (['organizer', 'box', 'qr'] as $key) {
            $this->assertStringContainsString('data-key="'.$key.'"', $picks[0], $key);
        }
        $this->assertStringNotContainsString('data-cats=', $picks[0], 'the picks are not counted twice by the filter');
        // the picks come before the categories and the big cards
        $this->assertLessThan(strpos($html, 'id="tool-cats"'), strpos($html, 'id="tool-picks"'));
        // every tool says what it asks for, in all three languages
        foreach (ToolVisibility::listed() as $key => $tool) {
            $input = ToolInput::of($key, $tool);
            $this->assertContains($input, ToolInput::KINDS, $key);
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('tools.input.'.$input, __('tools.input.'.$input, [], $locale), "{$key} {$locale}");
            }
        }
        $this->assertSame('photo', ToolInput::of('figure'));
        $this->assertSame('file', ToolInput::of('hollow', ['intent' => 'file']));
        $this->assertSame('link', ToolInput::of('qr'));
        $this->assertSame('sizes', ToolInput::of('box', ['intent' => 'create']));
        $this->assertStringContainsString('Zadáte: rozměry', $html);
        $this->assertStringContainsString('Zadáte: fotku', $html);
        $this->get('/en/tools')->assertOk()->assertSee('You enter: sizes')->assertSee('Our picks');
        // the home page keeps its cards as they were
        $this->assertStringNotContainsString('Zadáte:', (string) $this->get('/')->getContent());
        // the search knows words that mean the same, and an empty result has a way back
        $this->assertStringContainsString('\u0022jmenovka\u0022,\u0022cedulka\u0022,\u0022stitek\u0022', $html);
        $this->assertStringContainsString('id="tool-none-clear"', $html);
        $this->assertStringContainsString(e(__('tools.search.clear')), $html);
        $this->assertStringContainsString('id="tool-none-related"', $html);
        foreach (['cs', 'en', 'es'] as $locale) {
            $groups = trans('tools.synonyms', [], $locale);
            $this->assertIsArray($groups, $locale);
            $this->assertGreaterThanOrEqual(8, count($groups), $locale);
        }
    }

    /** docs/AH.md §4: the empty viewer of a tool that takes a model is where the model goes (it said "tap" and did nothing). */
    public function test_the_empty_viewer_of_a_file_tool_takes_the_file(): void
    {
        foreach (['/tools/hollow' => 'edit-file', '/tools/mold' => 'mold-file', '/tools/repair' => 'repair-file', '/tools/check' => 'check-file'] as $url => $input) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            if (! str_contains($html, 'id="tool-viewer"')) {
                continue;                                   // the engine is not installed here
            }
            $this->assertStringContainsString('<label id="tool-empty" for="'.$input.'" data-file-for="'.$input.'"', $html, $url);
            $this->assertStringContainsString('id="'.$input.'"', $html, $url);
        }
        // a tool with a model from the start keeps the plain empty line
        $this->assertStringNotContainsString('data-file-for=', (string) $this->get('/tools/box')->getContent());
    }

    /** docs/AH.md §8 (Roman, 18 Oct 2026): the photo dialog of a bust and a pet takes photos away again and makes the model. */
    public function test_the_photo_dialog_has_remove_buttons_the_consent_and_create(): void
    {
        config(['ai.daily_limits.generate_guest' => 1, 'ai.daily_limits.generate_user' => 3]);
        $seen = 0;
        foreach (['/tools/figure' => ['figure.consent', 'figure.submit'], '/tools/pet-figurine' => ['pet.consent', 'pet.submit']] as $url => [$consent, $submit]) {
            $page = (string) $this->get($url)->assertOk()->getContent();
            if (! str_contains($page, 'id="figure-drop"')) {
                continue;                                   // the generator is off here
            }
            $seen++;
            preg_match('/<div id="figure-drop".*?\n<\/div>/s', $page, $dialog);
            $this->assertNotEmpty($dialog, $url);
            $d = $dialog[0];
            // the pictures with their crosses are drawn by figure.ts into this box; the pick is a button of its own now
            $this->assertStringContainsString('id="figure-drop-thumbs"', $d, $url);
            $this->assertStringContainsString('id="figure-drop-pick"', $d, $url);
            // the consent mirrored from the panel (no name: only the panel's travels with the form), with its red frame
            $this->assertMatchesRegularExpression('/<input id="figure-drop-consent" type="checkbox" required/', $d);
            $this->assertStringNotContainsString('name="consent"', $d, $url);
            $this->assertStringContainsString(e(__($consent)), $d, $url);
            // the limit and the time beside the one orange Create, which waits for the front photo
            $this->assertStringContainsString(e(__('figure.limits', ['n' => 1, 'm' => 3])), $d, $url);
            $this->assertStringContainsString('2 až 4 minuty', $d, $url);
            $this->assertMatchesRegularExpression('/<button type="button" id="figure-create" class="btn-primary[^"]*" disabled>/', $d);
            $this->assertStringContainsString(e(__($submit)), $d, $url);
            $this->assertStringContainsString('id="figure-drop-msg"', $d, $url);
            // the script has the words for the crosses
            $this->assertStringContainsString(e(__('figure.remove')), $page, $url);
        }
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('#tool-page:has(#tool-empty:not(.hidden) #figure-create) #tool-go', $css, 'one orange action while the dialog shows');
        $this->assertStringNotContainsString('1 až 3 minuty', __('toolpage.sample.minutes'));
        if ($seen === 0) {
            $this->markTestSkipped('The generator is off here.');
        }
    }
}
