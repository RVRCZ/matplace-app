<?php

namespace Tests\Feature;

use App\Domain\Farm\Palette;
use App\Domain\Tools\ParametricGenerator;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** One palette for every tool: the farm's spools by their code, the nine built-in names as the fallback and for old designs. */
class ColorsPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function spools(): void
    {
        $pla = FarmMaterial::create(['code' => 'PLA+', 'name' => 'PLA+', 'finish' => 'solid', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.3, 'enabled' => true]);
        $silk = FarmMaterial::create(['code' => 'PLA', 'name' => 'PLA', 'finish' => 'silk', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.5, 'enabled' => true]);
        $off = FarmMaterial::create(['code' => 'ABS', 'name' => 'ABS', 'finish' => 'solid', 'filament_profile' => 'filament_pla.json', 'density' => 1.04, 'price_per_gram' => 1.5, 'enabled' => false]);
        FarmColor::create(['farm_material_id' => $pla->id, 'code' => '01_PLA+_cerny', 'name' => 'černá', 'name_en' => 'black', 'hex' => '#1b1b1d', 'enabled' => true, 'in_stock' => true, 'sort' => 1]);
        FarmColor::create(['farm_material_id' => $pla->id, 'code' => '02_PLA+_bily', 'name' => 'bílá', 'name_en' => 'white', 'hex' => '#f4f4f2', 'enabled' => true, 'in_stock' => true, 'sort' => 2]);
        FarmColor::create(['farm_material_id' => $pla->id, 'code' => '07_PLA+_modry', 'name' => 'modrá', 'name_en' => 'blue', 'hex' => '#1f4fa3', 'enabled' => true, 'in_stock' => false, 'sort' => 3]);
        FarmColor::create(['farm_material_id' => $silk->id, 'code' => '60_Silk_gold', 'name' => 'zlatá', 'name_en' => 'gold', 'hex' => '#c9a227', 'enabled' => true, 'in_stock' => true, 'sort' => 4]);
        FarmColor::create(['farm_material_id' => $pla->id, 'code' => '09_PLA+_vypnuta', 'name' => 'vypnutá', 'hex' => '#00ff00', 'enabled' => false, 'in_stock' => true, 'sort' => 5]);
        FarmColor::create(['farm_material_id' => $off->id, 'code' => '70_ABS_cerny', 'name' => 'černá ABS', 'hex' => '#111111', 'enabled' => true, 'in_stock' => true, 'sort' => 6]);
        app()->forgetScopedInstances();
    }

    public function test_without_a_catalogue_the_nine_built_in_colours_stand_in(): void
    {
        $colors = $this->getJson('/api/config')->assertOk()->json('colors');
        $this->assertFalse($colors['farm']);
        $this->assertSame(array_keys(Palette::BUILT_IN), array_column($colors['items'], 'code'));
        $this->assertSame('white', $colors['legacy']['white']);
        // the calculator's own page does not carry the palette: 250 rows it has no use for
        $this->assertStringNotContainsString('white_grey', (string) $this->get('/')->assertOk()->getContent());
    }

    public function test_the_palette_is_the_farms_catalogue(): void
    {
        $this->spools();
        $colors = $this->getJson('/api/config')->assertOk()->json('colors');
        $this->assertTrue($colors['farm']);
        // offered spools of offered kinds, in the catalogue's order; out of stock stays in, marked
        $this->assertSame(['01_PLA+_cerny', '02_PLA+_bily', '07_PLA+_modry', '60_Silk_gold'], array_column($colors['items'], 'code'));
        $blue = collect($colors['items'])->firstWhere('code', '07_PLA+_modry');
        $this->assertSame(['modrá', '#1f4fa3', 'PLA+', 'solid', false, 'blue_violet'], [$blue['name'], $blue['hex'], $blue['material'], $blue['finish'], $blue['in_stock'], $blue['hue']]);
        $this->assertStringContainsString('modra blue 07_pla+_modry', $blue['search']);
        $this->assertSame('black', collect($colors['items'])->firstWhere('code', '01_PLA+_cerny')['hue']);
        // the names older designs carry become the nearest spool in stock
        $this->assertSame('01_PLA+_cerny', $colors['legacy']['black']);
        $this->assertSame('02_PLA+_bily', $colors['legacy']['white']);
        $this->assertSame('60_Silk_gold', $colors['legacy']['yellow']);
        // the page of a tool carries the catalogue (a design stored with a spool is still read), but its colour fields
        // start at colours, never at spools: the window of a tool page offers free colours only
        $page = (string) $this->get('/tools/qr')->assertOk()->getContent();
        $this->assertStringContainsString('07_PLA+_modry', $page);
        $this->assertStringContainsString('data-choice="plate_color" data-color value="#ede6d6"', $page);
        $this->assertSame('modrá', collect($this->getJson('/api/config')->json('colors.items'))->firstWhere('code', '07_PLA+_modry')['name']);
        app()->setLocale('en');
        app()->forgetScopedInstances();
        $this->assertSame('blue', collect(app(Palette::class)->all())->firstWhere('code', '07_PLA+_modry')['name']);
    }

    public function test_hues_group_the_colours_of_the_window(): void
    {
        $this->assertSame('white_grey', Palette::hue('#f4f4f2'));
        $this->assertSame('white_grey', Palette::hue('#8c9199'));
        $this->assertSame('black', Palette::hue('#17171a'));
        $this->assertSame('red', Palette::hue('#b8211f'));
        $this->assertSame('red', Palette::hue('#f49ac1'));
        $this->assertSame('orange_yellow', Palette::hue('#ebbd29'));
        $this->assertSame('green', Palette::hue('#2f9e44'));
        $this->assertSame('blue_violet', Palette::hue('#213d78'));
        $this->assertSame('blue_violet', Palette::hue('#7a3fb0'));
        $this->assertSame('brown_beige', Palette::hue('#c2996b'));
        $this->assertSame('special', Palette::hue('#2f9e44', 'luminous'));
        $this->assertSame('special', Palette::hue('#b8211f', 'silk', 'duhová rainbow'));
    }

    public function test_a_tool_takes_a_spool_code_keeps_its_hex_and_still_takes_the_old_names(): void
    {
        $this->spools();
        $rules = ParametricGenerator::rules('qr');
        $this->assertTrue(validator(['params' => ['plate_color' => '02_PLA+_bily', 'url' => 'https://matplace.com']], $rules)->passes());
        $this->assertTrue(validator(['params' => ['plate_color' => 'yellow', 'url' => 'https://matplace.com']], $rules)->passes());
        $this->assertFalse(validator(['params' => ['plate_color' => 'pink', 'url' => 'https://matplace.com']], $rules)->passes());
        $this->assertFalse(validator(['params' => ['plate_color' => '09_PLA+_vypnuta', 'url' => 'https://matplace.com']], $rules)->passes());

        $clean = ParametricGenerator::clean('qr', ['url' => 'https://matplace.com', 'plate_color' => '02_PLA+_bily', 'code_color' => '07_PLA+_modry', 'part_colors' => ['stand' => '60_Silk_gold', 'nonsense!' => '01_PLA+_cerny', 'body' => 'pink']]);
        $this->assertSame(['02_PLA+_bily', '#f4f4f2', '07_PLA+_modry', '#1f4fa3'], [$clean['plate_color'], $clean['plate_color_hex'], $clean['code_color'], $clean['code_color_hex']]);
        $this->assertSame(['stand' => ['code' => '60_Silk_gold', 'hex' => '#c9a227']], $clean['part_colors']);
        // stored and read again (a design reopened with ?from=): nothing is lost on the second pass
        $this->assertSame($clean, ParametricGenerator::clean('qr', $clean));
        // an old design: the name stays what it was and keeps the hex it was designed with
        $old = ParametricGenerator::clean('qr', ['url' => 'https://matplace.com', 'plate_color' => 'yellow', 'code_color' => 'blue']);
        $this->assertSame(['yellow', '#EBBD29', 'blue', '#213D78'], [$old['plate_color'], $old['plate_color_hex'], $old['code_color'], $old['code_color_hex']]);
        // bins of a modular set carry the code and what it looks like
        $bins = ParametricGenerator::clean('modular', ['bins' => [['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => '60_Silk_gold'], ['x' => 1, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => 'red']]])['bins'];
        $this->assertSame(['60_Silk_gold', '#c9a227'], [$bins[0]['color'], $bins[0]['hex']]);
        $this->assertSame(['x' => 1, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => 'red'], $bins[1]);
    }

    public function test_a_design_keeps_its_colour_when_the_spool_leaves_the_stock_or_the_catalogue(): void
    {
        $this->spools();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        Storage::fake('models');
        $uuid = $this->postJson('/api/tools/param', ['kind' => 'qr', 'params' => ['url' => 'https://matplace.com', 'plate_color' => '02_PLA+_bily', 'code_color' => '07_PLA+_modry']])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(['#f4f4f2', '#1f4fa3'], $file->codeColors());
        $palette = app(Palette::class);
        $this->assertFalse($palette->inStock('07_PLA+_modry'));
        $this->assertTrue($palette->inStock('02_PLA+_bily'));
        // the preview paints by the code and warns about the contrast from the real colours
        $meta = json_decode((string) $this->postJson('/api/tools/param/preview', ['kind' => 'qr', 'params' => ['url' => 'https://matplace.com', 'plate_color' => '02_PLA+_bily', 'code_color' => '02_PLA+_bily']])->assertOk()->headers->get('X-Model-Meta'), true);
        $this->assertContains('qr_one_color', $meta['notes']['warnings']);
        $this->assertSame('02_PLA+_bily', $meta['notes']['regions'][0]['color']);
        // the spool is deleted from the catalogue: the stored design still knows what it looked like
        FarmColor::where('code', '07_PLA+_modry')->delete();
        app()->forgetScopedInstances();
        $this->assertSame(['#f4f4f2', '#1f4fa3'], $file->fresh()->codeColors());
    }
}
