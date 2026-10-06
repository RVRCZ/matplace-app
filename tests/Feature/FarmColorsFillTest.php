<?php

namespace Tests\Feature;

use App\Domain\Farm\ColorCatalog;
use App\Engines\Translate\FakeTranslator;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The routine of a catalogue of 250 spools: hex from the photo, English names in one call, the list from a spreadsheet. */
class FarmColorsFillTest extends TestCase
{
    use RefreshDatabase;

    private FarmMaterial $pla;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        FakeTranslator::reset();
        $this->pla = FarmMaterial::create(['code' => 'PLA+', 'name' => 'PLA+', 'finish' => 'solid', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.3, 'enabled' => true]);
        FarmMaterial::create(['code' => 'PLA', 'name' => 'PLA', 'finish' => 'matte', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.3, 'enabled' => true]);
        FarmMaterial::create(['code' => 'PLA', 'name' => 'PLA', 'finish' => 'special', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.6, 'enabled' => true]);
    }

    /** A photo of a print: a sample of one colour in the middle of a plain backdrop, with a little noise. */
    private function photo(array $sample, array $backdrop = [236, 236, 232]): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('PHP GD is not installed.');
        }
        $im = imagecreatetruecolor(400, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, ...$backdrop));
        imagefilledellipse($im, 200, 150, 220, 170, imagecolorallocate($im, ...$sample));
        mt_srand(7);
        for ($i = 0; $i < 400; $i++) {
            $d = mt_rand(-6, 6);
            imagesetpixel($im, mt_rand(110, 290), mt_rand(80, 220), imagecolorallocate($im, max(0, min(255, $sample[0] + $d)), max(0, min(255, $sample[1] + $d)), max(0, min(255, $sample[2] + $d))));
        }
        ob_start();
        imagejpeg($im, null, 92);
        $rel = 'farm/colors/'.md5(json_encode($sample)).'.jpg';
        Storage::disk('public')->put($rel, (string) ob_get_clean());

        return $rel;
    }

    public function test_hex_is_read_from_the_photo_and_only_where_it_is_missing(): void
    {
        $green = FarmColor::create(['farm_material_id' => $this->pla->id, 'code' => '06_PLA+_zeleny', 'name' => 'zelená', 'name_en' => 'green', 'photo_path' => $this->photo([47, 158, 68])]);
        $kept = FarmColor::create(['farm_material_id' => $this->pla->id, 'code' => '05_PLA+_cerveny', 'name' => 'červená', 'name_en' => 'red', 'hex' => '#b8211f', 'photo_path' => $this->photo([10, 10, 200])]);
        $bare = FarmColor::create(['farm_material_id' => $this->pla->id, 'code' => '99_PLA+_bez_fotky', 'name' => 'bez fotky', 'name_en' => 'no photo']);
        $this->assertTrue(ColorCatalog::hexMissing($green->fresh()));

        $this->artisan('farm:colors-fill', ['--dry-run' => true, '--only' => 'hex'])->expectsOutputToContain('[dry-run] hex 1, name_en 0, skipped 1')->assertSuccessful();
        $this->assertTrue(ColorCatalog::hexMissing($green->fresh()));

        $this->artisan('farm:colors-fill', ['--only' => 'hex'])->expectsOutputToContain('hex 1, name_en 0, skipped 1')->assertSuccessful();
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($green->fresh()->hex, '#'), 2));
        $this->assertEqualsWithDelta(47, $r, 10);
        $this->assertEqualsWithDelta(158, $g, 10);
        $this->assertEqualsWithDelta(68, $b, 10);
        $this->assertSame('#b8211f', $kept->fresh()->hex);                 // a hex somebody set is never overwritten
        $this->assertTrue(ColorCatalog::hexMissing($bare->fresh()));
        $this->artisan('farm:colors-fill', ['--only' => 'nonsense'])->assertFailed();
    }

    public function test_a_filament_without_one_colour_gets_the_dominant_one_and_a_note(): void
    {
        $special = FarmMaterial::where('finish', 'special')->firstOrFail();
        $rainbow = FarmColor::create(['farm_material_id' => $special->id, 'code' => '80_PLA_duha', 'name' => 'duhová', 'name_en' => 'rainbow', 'photo_path' => $this->photo([200, 60, 160])]);
        $this->artisan('farm:colors-fill', ['--only' => 'hex'])->assertSuccessful();
        $this->assertFalse(ColorCatalog::hexMissing($rainbow->fresh()));
        $this->assertStringContainsString('hex z fotky, zkontrolovat', (string) $rainbow->fresh()->test_notes);
    }

    public function test_english_names_come_from_one_call_of_the_translator(): void
    {
        $a = FarmColor::create(['farm_material_id' => $this->pla->id, 'code' => 'A', 'name' => 'Nefritová bílá', 'hex' => '#ecebe2']);
        $b = FarmColor::create(['farm_material_id' => $this->pla->id, 'code' => 'B', 'name' => 'dýňově oranžová', 'hex' => '#e8731a']);
        $c = FarmColor::create(['farm_material_id' => $this->pla->id, 'code' => 'C', 'name' => 'černá', 'name_en' => 'black', 'hex' => '#111111']);

        $this->artisan('farm:colors-fill', ['--only' => 'name_en'])->expectsOutputToContain('hex 0, name_en 2, skipped 0')->assertSuccessful();
        $this->assertCount(1, FakeTranslator::$calls);                      // all the missing names in one request
        $this->assertSame(['en'], FakeTranslator::$calls[0]['to']);
        $this->assertSame('cs', FakeTranslator::$calls[0]['from']);
        $this->assertStringContainsString('filament', FakeTranslator::$calls[0]['text']);
        // the fake translator echoes the text: each line landed at its own colour
        $this->assertSame(['nefritová bílá', 'dýňově oranžová', 'black'], [$a->fresh()->name_en, $b->fresh()->name_en, $c->fresh()->name_en]);
        $this->artisan('farm:colors-fill')->expectsOutputToContain('hex 0, name_en 0, skipped 0')->assertSuccessful();
        $this->assertCount(1, FakeTranslator::$calls);                      // nothing left to ask
    }

    public function test_a_spreadsheet_creates_and_updates_by_code(): void
    {
        FarmColor::create(['farm_material_id' => $this->pla->id, 'code' => '01_PLA+_cerny', 'name' => 'cerna', 'name_en' => 'black', 'hex' => '#1b1b1d', 'in_stock' => true, 'sort' => 1]);
        $csv = storage_path('framework/testing/spools.csv');
        File::ensureDirectoryExists(dirname($csv));
        File::put($csv, "\xEF\xBB\xBFcode;material;name;name_en;hex;in_stock;sort\n"
            ."01_PLA+_cerny;PLA+;černá;;;0;5\n"
            ."45_PLA_Matte_Olive;PLA matte;olivová;;#6B7A3A;ano;450\n"
            ."46_PLA_Matte_Sand;PLA matný;písková;sand;;;\n"
            ."X1;Unobtainium;zlatá;;;;\n"
            ."X2;PLA+;modrá;;modra;;\n"
            .";PLA+;bez kódu;;;;\n");

        $this->artisan('farm:import-colors', ['csv' => $csv, '--dry-run' => true])->expectsOutputToContain('[dry-run] new 2, changed 1, unchanged 0, errors 3')->assertSuccessful();
        $this->assertSame(1, FarmColor::count());
        $this->assertSame('cerna', FarmColor::where('code', '01_PLA+_cerny')->value('name'));

        $this->artisan('farm:import-colors', ['csv' => $csv])->expectsOutputToContain('new 2, changed 1, unchanged 0, errors 3')->assertSuccessful();
        $black = FarmColor::where('code', '01_PLA+_cerny')->firstOrFail();
        $this->assertSame(['černá', 'black', '#1b1b1d', false, 5], [$black->name, $black->name_en, $black->hex, $black->in_stock, $black->sort]);   // empty cells leave what was there
        $olive = FarmColor::where('code', '45_PLA_Matte_Olive')->firstOrFail();
        $this->assertSame(['olivová', '#6b7a3a', true, 450, 'matte', true], [$olive->name, $olive->hex, $olive->in_stock, $olive->sort, $olive->material->finish, $olive->enabled]);
        $sand = FarmColor::where('code', '46_PLA_Matte_Sand')->firstOrFail();
        $this->assertSame('sand', $sand->name_en);
        $this->assertTrue(ColorCatalog::hexMissing($sand));                 // left for farm:colors-fill
        $this->artisan('farm:import-colors', ['csv' => $csv])->expectsOutputToContain('new 0, changed 0, unchanged 3, errors 3')->assertSuccessful();

        File::put($csv, "kod;barva\n1;x\n");
        $this->artisan('farm:import-colors', ['csv' => $csv])->expectsOutputToContain('code;material;name')->assertFailed();
    }

    public function test_the_admin_needs_neither_hex_nor_english_name_and_fills_them_with_a_button(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.farm.colors.create'), ['farm_material_id' => $this->pla->id, 'name' => 'tyrkysová', 'code' => '30_PLA+_tyrkys', 'enabled' => 1, 'in_stock' => 1])->assertSessionHasNoErrors();
        $color = FarmColor::where('code', '30_PLA+_tyrkys')->firstOrFail();
        $this->assertTrue(ColorCatalog::hexMissing($color));
        $color->update(['photo_path' => $this->photo([20, 170, 170])]);

        $page = $this->actingAs($admin)->get(route('admin.farm.materials'))->assertOk();
        $page->assertSee('Doplnit hex a anglické názvy')->assertSee('code;material;name;name_en;hex;in_stock;sort')->assertSee('chybí');
        $this->actingAs($admin)->post(route('admin.farm.colors.fill'))->assertRedirect()->assertSessionHas('status');
        $this->assertFalse(ColorCatalog::hexMissing($color->fresh()));
        $this->assertSame('tyrkysová', $color->fresh()->name_en);            // the fake translator echoes

        $csv = UploadedFile::fake()->createWithContent('spools.csv', "code;material;name\n31_PLA+_limetka;PLA+;limetková\n");
        $this->actingAs($admin)->post(route('admin.farm.colors.import'), ['csv' => $csv, 'preview' => 1])->assertRedirect()->assertSessionHas('color_report');
        $this->assertNull(FarmColor::where('code', '31_PLA+_limetka')->first());
        $this->actingAs($admin)->post(route('admin.farm.colors.import'), ['csv' => UploadedFile::fake()->createWithContent('spools.csv', "code;material;name\n31_PLA+_limetka;PLA+;limetková\n")])->assertRedirect();
        $this->assertNotNull(FarmColor::where('code', '31_PLA+_limetka')->first());
        $this->post(route('logout'));
        $this->post(route('admin.farm.colors.fill'))->assertRedirect();      // guests are sent to sign in
    }
}
