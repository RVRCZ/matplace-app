<?php

namespace Tests\Feature;

use App\Domain\Farm\Palette;
use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ParametricGenerator;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\PreviewMeta;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A design is drawn in any colour: the tool pages offer sixteen basic colours and a free one, the value is the colour
 * itself ("#2a7fd5"). The farm's spools come later: the calculation names the nearest spool, the farm's page ticks it.
 */
class CustomColorTest extends TestCase
{
    use RefreshDatabase;

    /** A small catalogue: white, black, blue and red PLA+ in stock, a green one out of stock. */
    private function spools(): void
    {
        $pla = FarmMaterial::create(['code' => 'PLA+', 'name' => 'PLA+', 'finish' => 'solid', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.3, 'enabled' => true]);
        foreach ([['01_PLA+_cerny', 'černá', 'black', '#1b1b1d', true], ['02_PLA+_bily', 'bílá', 'white', '#f4f4f2', true], ['07_PLA+_modry', 'modrá', 'blue', '#1f5fc0', true],
            ['05_PLA+_cerveny', 'červená', 'red', '#c81c1c', true], ['06_PLA+_zeleny', 'zelená', 'green', '#2e9e4f', false]] as $i => [$code, $name, $en, $hex, $stock]) {
            FarmColor::create(['farm_material_id' => $pla->id, 'code' => $code, 'name' => $name, 'name_en' => $en, 'hex' => $hex, 'enabled' => true, 'in_stock' => $stock, 'sort' => $i + 1]);
        }
        app()->forgetScopedInstances();
    }

    private function python(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    private function meta($response): array
    {
        return PreviewMeta::whole($response->headers->get('X-Model-Meta'));      // the heaviest notes wait beside the header
    }

    private function entry(string $file, string $name): ?string
    {
        $zip = new \ZipArchive;
        $zip->open($file);
        $s = $zip->getFromName($name);
        $zip->close();

        return $s === false ? null : $s;
    }

    public function test_the_palette_knows_free_colours_and_offers_sixteen_basic_ones(): void
    {
        $palette = app(Palette::class);
        $this->assertTrue(Palette::isCustom('#2A7FD5'));
        foreach (['#12', '2a7fd5', '#2a7fd5ff', 'red;drop', '', null, ['#2a7fd5']] as $bad) {
            $this->assertFalse(Palette::isCustom($bad), json_encode($bad));
        }
        $this->assertSame('#2a7fd5', Palette::canonical('#2A7FD5'));
        $this->assertSame('02_PLA+_bily', Palette::canonical('02_PLA+_bily'));
        $this->assertTrue($palette->has('#2A7FD5'));
        $this->assertSame('#2a7fd5', $palette->hex('#2A7FD5'));
        $this->assertTrue($palette->inStock('#2a7fd5'), 'a free colour is no spool: nothing of it is out of stock');

        // the browser gets the basic colours with their names, in the visitor's language, and what the built-in names look like
        $colors = $this->getJson('/api/config')->assertOk()->json('colors');
        $this->assertCount(16, $colors['basic']);
        $this->assertSame(array_keys(Palette::BASIC), array_column($colors['basic'], 'key'));
        $this->assertSame(['key' => 'blue', 'hex' => Palette::BASIC['blue'], 'name' => 'modrá'], collect($colors['basic'])->firstWhere('key', 'blue'));
        $this->assertSame('#ede6d6', $colors['named']['white']);
        foreach (['cs', 'en', 'es'] as $lang) {
            app()->setLocale($lang);
            foreach (Palette::basic() as $c) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $c['hex']);
                $this->assertStringNotContainsString('color.basic', $c['name'], $lang.' '.$c['key']);
            }
            foreach (['color.custom', 'color.custom_hex', 'color.custom_bad', 'color.use', 'color.basic', 'color.recent', 'color.window'] as $key) {
                $this->assertNotSame('toolpage.'.$key, __('toolpage.'.$key), $lang.' '.$key);
            }
            foreach (['farm.calc_nearest', 'farm.calc_nearest_out'] as $key) {
                $this->assertNotSame($key, __($key), $lang.' '.$key);
            }
        }
        app()->setLocale('cs');

        // what a tool chooses from by itself: the basic colours and what the visitor picked, never a spool
        $this->spools();
        $free = app(Palette::class)->free(['#2A7FD5', '02_PLA+_bily', 'yellow', 'nonsense', null, Palette::BASIC['red']]);
        $this->assertSame(array_values(Palette::BASIC), array_slice(array_column($free, 0), 0, 16));
        $this->assertSame([['#2a7fd5', '#2a7fd5'], ['02_PLA+_bily', '#f4f4f2'], ['yellow', '#ebbd29']], array_slice($free, 16));
    }

    public function test_one_rule_takes_a_free_colour_a_spool_and_an_old_name_and_nothing_else(): void
    {
        $this->spools();
        $ok = fn (string $kind, array $params) => validator(['params' => $params], ParametricGenerator::rules($kind))->passes();
        $sign = ['line1' => 'Emma'];
        $this->assertTrue($ok('sign', $sign + ['part_colors' => ['text' => '#2a7fd5']]));
        $this->assertTrue($ok('sign', $sign + ['part_colors' => ['text' => '#2A7FD5']]));
        $this->assertTrue($ok('sign', $sign + ['part_colors' => ['text' => '02_PLA+_bily']]), 'a design stored with a spool');
        $this->assertTrue($ok('sign', $sign + ['part_colors' => ['text' => 'yellow']]), 'a design from before the catalogue');
        $this->assertTrue($ok('sign', $sign + ['part_colors' => ['text' => ['code' => '#2a7fd5', 'hex' => '#2a7fd5']]]), 'a stored design comes back with both');
        $this->assertTrue($ok('sign', $sign + ['part_colors' => ['text' => ['code' => '99_PLA+_gone', 'hex' => '#336699']]]), 'its spool has left the catalogue: the colour stays');
        foreach (['#12', 'red;drop', '#2a7fd5; drop', 'pink', '#gggggg', ['code' => 'red;drop', 'hex' => '#336699'], ['code' => '99_PLA+_gone'], ['hex' => '#336699']] as $bad) {
            $this->assertFalse($ok('sign', $sign + ['part_colors' => ['text' => $bad]]), json_encode($bad));
        }
        // every colour field of every tool goes by the same rule
        $this->assertTrue($ok('qr', ['url' => 'https://matplace.com', 'plate_color' => '#fafafa', 'code_color' => '#101010']));
        $this->assertFalse($ok('qr', ['url' => 'https://matplace.com', 'plate_color' => '#12']));
        $this->assertTrue($ok('modular', ['bins' => [['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => '#2a7fd5']]]));
        $this->assertFalse($ok('modular', ['bins' => [['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => 'red;drop']]]));
        $this->assertTrue($ok('compose', ['layers' => [['kind' => 'shape', 'shape' => 'circle', 'w' => 40, 'code' => '#2a7fd5']]]));
        $this->assertFalse($ok('compose', ['layers' => [['kind' => 'shape', 'shape' => 'circle', 'w' => 40, 'code' => '#12']]]));
        $art = fn ($color) => validator(['params' => ['artwork' => 'lib:colour/snowman', 'part_colors' => ['plate_1' => $color]]], ArtGenerator::rules())->passes();
        $this->assertTrue($art('#2a7fd5'));
        $this->assertTrue($art(['code' => '#2a7fd5', 'hex' => '#2a7fd5']));
        $this->assertFalse($art('#12'));
        $this->assertFalse($art('red;drop'));

        // what is stored: a free colour is its own code and its own look, in lower case
        $clean = ParametricGenerator::clean('qr', ['url' => 'https://matplace.com', 'plate_color' => '#FAFAFA', 'code_color' => '#101010', 'part_colors' => ['stand' => '#2A7FD5', 'body' => 'red;drop']]);
        $this->assertSame(['#fafafa', '#fafafa', '#101010', '#101010'], [$clean['plate_color'], $clean['plate_color_hex'], $clean['code_color'], $clean['code_color_hex']]);
        $this->assertSame(['stand' => ['code' => '#2a7fd5', 'hex' => '#2a7fd5']], $clean['part_colors']);
        $this->assertSame($clean, ParametricGenerator::clean('qr', $clean), 'a design opened again and stored again stays what it was');
        $bins = ParametricGenerator::clean('modular', ['bins' => [['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => '#2A7FD5']]])['bins'];
        $this->assertSame(['#2a7fd5', '#2a7fd5'], [$bins[0]['color'], $bins[0]['hex']]);
        $layers = ParametricGenerator::clean('compose', ['layers' => [['kind' => 'shape', 'code' => '#2A7FD5'], ['kind' => 'shape', 'code' => 'white'], ['kind' => 'shape', 'code' => 'nonsense']]])['layers'];
        $this->assertSame(['#2a7fd5', 'white', 'white'], array_column($layers, 'code'), 'a layer is drawn in a colour: a built-in name is never turned into a spool');
        $this->assertSame(['plate_1' => ['code' => '#2a7fd5', 'hex' => '#2a7fd5']], ArtGenerator::clean(['artwork' => 'lib:colour/snowman', 'part_colors' => ['plate_1' => ['code' => '#2A7FD5', 'hex' => '#ffffff']]])['part_colors']);
    }

    public function test_a_sign_with_a_free_colour_of_its_text_is_stored_with_it_and_printed_in_it(): void
    {
        $this->python();
        Storage::fake('models');
        $this->spools();
        $params = ['style' => 'emboss', 'line1' => 'Emma', 'two_color' => true, 'thickness' => 3, 'relief' => 1.2];
        foreach (['#12', 'red;drop'] as $bad) {
            $this->postJson('/api/tools/param', ['kind' => 'sign', 'params' => $params + ['part_colors' => ['text' => $bad]]])->assertStatus(422)->assertJsonValidationErrors('params.part_colors.text');
        }
        $created = $this->postJson('/api/tools/param', ['kind' => 'sign', 'params' => $params + ['part_colors' => ['text' => '#2A7FD5']]])->assertCreated();
        $uuid = $created->json('file.uuid');
        $this->assertSame(['code' => '#2a7fd5', 'hex' => '#2a7fd5'], $created->json('file.tool.params.part_colors.text'));
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(['#2a7fd5'], $file->designColors());

        // the next page names the spool of ours nearest to the colour; nothing is chosen by it
        $nearest = $this->getJson('/api/files/'.$uuid)->assertOk()->json('file.nearest');
        $this->assertCount(1, $nearest);
        $this->assertSame('#2a7fd5', $nearest[0]['hex']);
        $this->assertSame(['07_PLA+_modry', 'modrá', 'PLA+', true], [$nearest[0]['spool']['code'], $nearest[0]['spool']['name'], $nearest[0]['spool']['material'], $nearest[0]['spool']['in_stock']]);
        $calc = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15, 'quantity' => 1])->assertCreated();
        $this->assertSame('07_PLA+_modry', $calc->json('calculation.file.nearest.0.spool.code'));
        $this->assertSame('07_PLA+_modry', $this->getJson('/api/calculations/'.$calc->json('calculation.token'))->assertOk()->json('calculation.file.nearest.0.spool.code'));
        $this->assertFalse($nearest[0]['spool']['loaded'], 'nothing is loaded in a machine yet: the whole catalogue answers');
        // the calculator's page carries the words of that line
        $this->get('/')->assertOk()->assertSee('"farm.calc_nearest"', false)->assertSee('"farm.calc_nearest_out"', false);

        // a spool code of a design stored before still passes and keeps the look of its spool
        $old = $this->postJson('/api/tools/param', ['kind' => 'sign', 'params' => $params + ['part_colors' => ['text' => '05_PLA+_cerveny']]])->assertCreated();
        $this->assertSame(['code' => '05_PLA+_cerveny', 'hex' => '#c81c1c'], $old->json('file.tool.params.part_colors.text'));
        $this->assertSame('05_PLA+_cerveny', $old->json('file.nearest.0.spool.code'));

        // the project for a slicer at home changes filament above the plate, in the colour the text was given
        $r = $this->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s&quality=standard")->assertOk();
        $xml = (string) $this->entry($r->baseResponse->getFile()->getPathname(), 'Metadata/custom_gcode_per_layer.xml');
        $this->assertStringContainsString('gcode="M600"', $xml);
        $this->assertStringContainsString('color="#2a7fd5"', $xml);

        // once spools sit in the machines, the calculation names the loaded one nearest to the colour - the very spool
        // the farm's page then ticks - even when the catalogue holds a closer colour that is not loaded
        $printer = FarmPrinter::create(['name' => 'S1', 'model' => 'Kobra S1', 'key' => 'k1', 'mode' => 'manual', 'enabled' => true, 'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250, 'nozzle_mm' => 0.4, 'machine_profile' => 'machine.json', 'process_profiles' => ['standard' => 'process_standard.json'], 'time_factor' => 1, 'weight_factor' => 1]);
        $printer->slots()->create(['slot' => 0, 'farm_color_id' => FarmColor::where('code', '02_PLA+_bily')->value('id'), 'remaining_g' => 900, 'enabled' => true]);
        $printer->slots()->create(['slot' => 1, 'farm_color_id' => FarmColor::where('code', '01_PLA+_cerny')->value('id'), 'remaining_g' => 900, 'enabled' => true]);
        app()->forgetScopedInstances();
        $loaded = $this->getJson('/api/files/'.$uuid)->assertOk()->json('file.nearest.0.spool');
        $expect = collect(['02_PLA+_bily' => '#f4f4f2', '01_PLA+_cerny' => '#1b1b1d'])->sortBy(fn ($hex) => Palette::distance('#2a7fd5', $hex))->keys()->first();
        $this->assertSame([$expect, true], [$loaded['code'], $loaded['loaded']], 'the blue is not loaded: of the loaded white and black the nearer one in Lab is named, as the farm page measures it');
        // a design stored with a spool that is not loaded is told the same: the loaded spool it would be printed from
        $this->assertSame($expect, $this->getJson('/api/files/'.$old->json('file.uuid'))->assertOk()->json('file.nearest.0.spool.code'));
        $this->assertSame('07_PLA+_modry', app(Palette::class)->nearest('#2a7fd5', false), 'the whole catalogue still answers for the names of stored designs');
        $this->assertLessThan(Palette::distance('#2a7fd5', '#1b1b1d'), Palette::distance('#2a7fd5', '#1f5fc0'));
    }

    public function test_the_nearest_spool_says_when_it_is_out_of_stock_and_a_site_without_a_farm_names_none(): void
    {
        $palette = app(Palette::class);
        $this->assertNull($palette->nearestSpool('#2a7fd5'), 'no catalogue: the built-in names are no spools');
        $this->spools();
        $palette = app(Palette::class);
        $this->assertSame('07_PLA+_modry', $palette->nearestSpool('#2a7fd5')['code']);
        $this->assertSame('02_PLA+_bily', $palette->nearestSpool('#ffffff')['code']);
        $this->assertNull($palette->nearestSpool('not a colour'));
        // green is only out of stock: the nearest spool is still the green one when nothing else is near, and says so
        FarmColor::whereIn('code', ['07_PLA+_modry', '05_PLA+_cerveny', '01_PLA+_cerny', '02_PLA+_bily'])->delete();
        app()->forgetScopedInstances();
        $green = app(Palette::class)->nearestSpool('#2e9e4f');
        $this->assertSame(['06_PLA+_zeleny', false], [$green['code'], $green['in_stock']]);
    }

    public function test_a_picture_in_colours_keeps_its_own_colours(): void
    {
        $this->python();
        $this->spools();
        $preview = fn (array $params) => $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'magnet', 'params' => ['artwork' => 'lib:colour/snowman', 'width' => 60, 'colors_n' => 4] + $params, 'pieces' => true])->assertOk())['notes'];
        $n = $preview([]);
        $this->assertGreaterThanOrEqual(2, count($n['colors']));
        $spools = array_column(app(Palette::class)->all(), 'code');
        foreach ([...$n['colors'], $n['body_color']] as $c) {
            // the colour of the picture itself: its hex is its value, no spool of the farm is named
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $c['code']);
            $this->assertSame($c['code'], $c['hex']);
            $this->assertNotContains($c['code'], $spools);
        }
        foreach ($n['colors'] as $c) {
            $this->assertSame($c['rgb'], $c['code'], 'a colour of the picture stays the colour it has in the picture');
        }
        foreach ($n['color_changes'] as $change) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $change['code']);
            $this->assertSame($change['code'], $change['hex']);
        }
        // the visitor gives one colour of the picture and the plate their own
        $part = $n['colors'][0]['part'];
        $own = $preview(['part_colors' => [$part => '#2A7FD5', 'body' => '#FFD000']]);
        $this->assertSame(['#2a7fd5', '#2a7fd5'], [$own['colors'][0]['code'], $own['colors'][0]['hex']]);
        $this->assertSame($n['colors'][0]['rgb'], $own['colors'][0]['rgb'], 'what the picture had there is still told');
        $this->assertSame(['#ffd000', '#ffd000'], [$own['body_color']['code'], $own['body_color']['hex']]);
        $this->assertSame('#2a7fd5', $own['paint'][$part]);
        // a design stored before, with a spool: the spool's code and its look stay
        $old = $preview(['part_colors' => [$part => '05_PLA+_cerveny']]);
        $this->assertSame(['05_PLA+_cerveny', '#c81c1c'], [$old['colors'][0]['code'], $old['colors'][0]['hex']]);

        // stored: every colour of the design is {code: hex, hex}
        Storage::fake('models');
        $stored = $this->postJson('/api/tools/param', ['kind' => 'magnet', 'params' => ['artwork' => 'lib:colour/snowman', 'width' => 60, 'colors_n' => 4, 'part_colors' => [$part => '#2a7fd5']]])->assertCreated()->json('file.tool.params');
        $this->assertSame(['code' => '#2a7fd5', 'hex' => '#2a7fd5'], $stored['part_colors'][$part]);
        foreach ($stored['part_colors'] as $c) {
            $this->assertSame($c['code'], $c['hex']);
        }
    }

    /**
     * What the preview of a tool page paints from. A design that is one body in two colours (a sign: the plate and the
     * text raised on it) is painted by regions; each region names its part, so the page can show the region in the
     * colour that part was given. Without the name the preview keeps the tool's own white and orange (10 Oct 2026).
     */
    public function test_the_preview_knows_which_part_each_of_its_colours_belongs_to(): void
    {
        $this->python();
        $notes = fn (string $kind, array $params) => $this->meta($this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'view' => 'use', 'pieces' => true])->assertOk());
        $twoColours = [
            'a sign, raised text' => ['sign', ['style' => 'emboss', 'line1' => 'Jana', 'two_color' => true], ['text', 'plate']],
            'a sign, outlined text' => ['sign', ['style' => 'outline', 'line1' => 'Jana', 'two_color' => true], ['text', 'plate']],
            'a sign, a name alone' => ['sign', ['style' => 'name', 'line1' => 'Jana', 'two_color' => true], ['text', 'plate']],
            'letter beads' => ['beads', ['line1' => 'JANA'], ['text', 'body']],
            'a standing logo' => ['logo', ['line1' => 'LOGO', 'mode' => 'standing', 'width' => 80], ['body', 'stand']],
        ];
        foreach ($twoColours as $what => [$kind, $params, $parts]) {
            $m = $notes($kind, $params + ['part_colors' => [$parts[0] => '#8a2be2', $parts[1] => '#ff3fa4']]);
            $regions = $m['notes']['regions'] ?? [];
            $this->assertSame($parts, array_column($regions, 'part'), $what.': the upper region first, then the rest of the model');
            // every part a region names has a row in the colours section, so a colour can be picked for it
            $rows = ParametricGenerator::partsOf($kind, ParametricGenerator::clean($kind, $params) + ['parts' => $m['notes']['parts'] ?? []]);
            $this->assertSame([], array_values(array_diff($parts, $rows)), $what.': '.implode(', ', $rows));
        }
        // a sign in one colour and an engraved one have no regions: the one row colours the one piece
        $this->assertArrayNotHasKey('regions', $notes('sign', ['style' => 'emboss', 'line1' => 'Jana'])['notes']);
        $this->assertArrayNotHasKey('regions', $notes('sign', ['style' => 'engrave', 'line1' => 'Jana', 'two_color' => true])['notes']);

        // a picture in colours: every colour is a piece of its own and `paint` says what each piece looks like
        $m = $notes('magnet', ['artwork' => 'lib:colour/snowman', 'width' => 60, 'colors_n' => 4]);
        $part = $m['notes']['colors'][0]['part'];
        $picked = $notes('magnet', ['artwork' => 'lib:colour/snowman', 'width' => 60, 'colors_n' => 4, 'part_colors' => [$part => '#8a2be2', 'body' => '#ff3fa4']]);
        $this->assertSame(['#8a2be2', '#ff3fa4'], [$picked['notes']['paint'][$part], $picked['notes']['paint']['body']]);
        $pieces = array_column($picked['parts'], 'name');
        foreach (array_keys($picked['notes']['paint']) as $painted) {
            $this->assertContains($painted, $pieces, 'a colour without a piece would never be seen');
        }
    }

    public function test_a_layered_picture_takes_a_free_colour_for_a_plate(): void
    {
        if (! app(ArtGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $this->spools();
        $base = ['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'none', 'width' => 150, 'colors_n' => 5];
        $preview = fn (array $params) => $this->meta($this->postJson('/api/tools/art/preview', ['params' => $base + $params, 'view' => 'use'])->assertOk())['notes'];
        $plain = $preview([]);
        foreach ($plain['colors'] as $c) {
            $this->assertSame([$c['rgb'], $c['rgb']], [$c['code'], $c['hex']], 'a plate has the colour its part of the picture has');
        }
        $part = $plain['colors'][0]['part'];
        // the page sends the colour as it is; a stored design sends {code, hex} back
        foreach (['#2A7FD5', ['code' => '#2a7fd5', 'hex' => '#2a7fd5']] as $sent) {
            $own = $preview(['part_colors' => [$part => $sent]]);
            $this->assertSame(['#2a7fd5', '#2a7fd5'], [$own['colors'][0]['code'], $own['colors'][0]['hex']]);
            $this->assertSame('#2a7fd5', $own['paint'][$part]);
        }
        $this->postJson('/api/tools/art/preview', ['params' => $base + ['part_colors' => [$part => '#12']], 'view' => 'use'])->assertStatus(422);
    }

    public function test_the_farm_ticks_the_spools_nearest_to_the_free_colours_of_a_design(): void
    {
        $this->python();
        Storage::fake('models');
        Storage::fake('farm');
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        // the S1 holds a white spool; a red, a green and a blue one of the same family join it
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $white = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail()->color;
        $white->update(['hex' => '#F4F4F0']);
        [$red, $green, $blue] = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'like', 'PLA%'))->where('id', '!=', $white->id)->take(3)->get()->all();
        $red->update(['hex' => '#C01818', 'enabled' => true]);
        $green->update(['hex' => '#2E8B3A', 'enabled' => true]);
        $blue->update(['hex' => '#1E4FA0', 'enabled' => true]);
        $free = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->get();
        $this->assertGreaterThanOrEqual(3, $free->count(), 'the seeded S1 has three empty positions');
        foreach ([$red, $green, $blue] as $i => $color) {
            $free[$i]->update(['farm_color_id' => $color->id, 'remaining_g' => 800, 'enabled' => true]);
        }
        $ticked = fn (string $html, string $name) => preg_match('/name="'.preg_quote($name, '/').'" value="(\d*)"[^>]*\schecked/', $html, $m) ? (int) $m[1] : null;

        // a QR sign whose code was given a free red: the white plate and the red spool of that machine
        $qr = $this->actingAs($user)->postJson('/api/tools/param', ['kind' => 'qr', 'params' => ['url' => 'https://matplace.com', 'plate_color' => '#fafaf5', 'code_color' => '#d01f1f']])->assertCreated()->json('file.uuid');
        $html = $this->actingAs($user)->get('/farm?file='.$qr)->assertOk()->getContent();
        $this->assertSame([$white->id, $red->id], [$ticked($html, 'color'), $ticked($html, 'change_color[0]')]);
        $this->assertStringContainsString('data-want="#d01f1f"', $html);

        // a box whose body and lid were given free colours on the tool's page: by parts, each from the nearest spool
        $box = $this->actingAs($user)->postJson('/api/tools/param', ['kind' => 'box', 'params' => ['lid' => 1, 'inner_w' => 30, 'inner_d' => 20, 'inner_h' => 12, 'part_colors' => ['body' => '#30a040', 'lid' => '#2050b0']]])->assertCreated();
        $this->assertSame(['body' => ['code' => '#30a040', 'hex' => '#30a040'], 'lid' => ['code' => '#2050b0', 'hex' => '#2050b0']], $box->json('file.tool.params.part_colors'));
        $html = $this->actingAs($user)->get('/farm?file='.$box->json('file.uuid'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="farm-by-parts"[^>]*\schecked/', $html);
        $this->assertSame([$green->id, $blue->id], [$ticked($html, 'color'), $ticked($html, 'part_color[lid]')]);
    }
}
