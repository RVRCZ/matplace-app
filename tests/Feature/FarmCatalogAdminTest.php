<?php

namespace Tests\Feature;

use App\Domain\Farm\ColorCatalog;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The catalogue as the operator keeps it while loading spools: who made a kind or a colour, a spool the catalogue
 * does not know typed in right at the printer slot, and removing what was created by mistake (never what printed).
 */
class FarmCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
    }

    private function printerForm(FarmPrinter $printer, array $slots): array
    {
        return $printer->only(['name', 'model', 'key', 'mode', 'bed_x', 'bed_y', 'bed_z', 'nozzle_mm', 'machine_profile', 'time_factor', 'weight_factor'])
            + ['enabled' => 1, 'process_profiles' => json_encode($printer->process_profiles), 'slots' => $slots];
    }

    public function test_every_kind_and_colour_of_the_first_catalogue_is_ours_and_a_maker_is_said_only_when_it_differs(): void
    {
        $pla = FarmMaterial::where('code', 'PLA+')->where('finish', 'solid')->firstOrFail();
        $this->assertSame('Matplace', $pla->maker());
        $this->assertSame('PLA+', $pla->label(), 'our own brand is not repeated in the label');
        $color = $pla->colors()->firstOrFail();
        $this->assertNull($color->manufacturer);
        $this->assertSame('Matplace', $color->maker(), 'a colour without a maker of its own is of its kind\'s');

        // a colour of another maker under our kind: the maker is kept on the colour; "Matplace" typed in is not
        $this->actingAs($this->admin)->post("/admin/farm/colors/{$color->id}", ['farm_material_id' => $pla->id, 'name' => $color->name, 'manufacturer' => 'Prusament', 'enabled' => 1, 'in_stock' => 1, 'code' => $color->code])->assertRedirect();
        $this->assertSame('Prusament', $color->fresh()->maker());
        $this->actingAs($this->admin)->post("/admin/farm/colors/{$color->id}", ['farm_material_id' => $pla->id, 'name' => $color->name, 'manufacturer' => ' Matplace ', 'enabled' => 1, 'in_stock' => 1, 'code' => $color->code])->assertRedirect();
        $this->assertNull($color->fresh()->manufacturer);

        // a kind of another maker with the same code and finish is a kind of its own, and its label says who made it
        $this->actingAs($this->admin)->post('/admin/farm/materials/new', ['code' => 'PLA+', 'finish' => 'solid', 'name' => 'PLA+', 'manufacturer' => 'Sunlu', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.1, 'enabled' => 1])->assertRedirect('/admin/farm/materials');
        $sunlu = FarmMaterial::where('code', 'PLA+')->where('manufacturer', 'Sunlu')->firstOrFail();
        $this->assertSame('PLA+ Sunlu', $sunlu->label());
        // the same kind of the same maker twice is refused
        $this->actingAs($this->admin)->post('/admin/farm/materials/new', ['code' => 'pla+', 'finish' => 'solid', 'name' => 'PLA+', 'manufacturer' => 'Sunlu', 'filament_profile' => 'filament_pla.json', 'density' => 1.24, 'price_per_gram' => 1.1])->assertSessionHasErrors('code');

        $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk()->assertSee('Sunlu')->assertSee('id="farm-makers"', false);
    }

    public function test_a_spool_the_catalogue_does_not_know_is_typed_in_at_the_printer_slot_and_offered_at_once(): void
    {
        $printer = FarmPrinter::firstOrFail();
        $petg = FarmMaterial::where('code', 'PETG')->firstOrFail();
        $slots = $printer->slots->keyBy('slot')->map(fn (FarmPrinterSlot $s) => ['color' => $s->farm_color_id, 'remaining_g' => $s->remaining_g, 'enabled' => $s->enabled ? 1 : 0])->all();
        $slots[3] = ['color' => 'new', 'remaining_g' => 950, 'enabled' => 1, 'new' => [
            'name' => 'lososová', 'name_en' => 'Salmon pink', 'material' => $petg->id, 'manufacturer' => 'Prusament', 'hex' => '#FA8072',
            'photo' => UploadedFile::fake()->image('spool.jpg', 300, 300),
        ]];

        $this->actingAs($this->admin)->post("/admin/farm/printers/{$printer->id}", $this->printerForm($printer, $slots))->assertRedirect('/admin/farm/printers');

        $color = FarmColor::where('name', 'lososová')->firstOrFail();
        $this->assertSame($petg->id, $color->farm_material_id);
        $this->assertSame('PETG_Salmon_Pink_Prusament', $color->code);
        $this->assertSame('Prusament', $color->maker());
        $this->assertSame('#fa8072', $color->hex);
        $this->assertTrue($color->enabled && $color->in_stock, 'offered at once');
        Storage::disk('public')->assertExists($color->photo_path);
        $this->assertSame([], $color->sliceOverrides(), 'prints with the settings of its kind until it gets its own');
        $slot = $printer->slots()->where('slot', 3)->firstOrFail();
        $this->assertSame($color->id, $slot->farm_color_id);
        $this->assertTrue($slot->enabled);
        $this->assertSame(950.0, $slot->remaining_g);

        // a second spool of the same name gets the next code; a new colour without a name or a kind is refused
        $slots[3] = ['color' => $color->id, 'remaining_g' => 950, 'enabled' => 1];
        $slots[2] = ['color' => 'new', 'remaining_g' => 1000, 'enabled' => 1, 'new' => ['name' => 'lososová', 'name_en' => 'Salmon pink', 'material' => $petg->id, 'manufacturer' => 'Prusament']];
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$printer->id}", $this->printerForm($printer, $slots))->assertRedirect('/admin/farm/printers');
        $this->assertSame('PETG_Salmon_Pink_Prusament_2', FarmColor::where('name', 'lososová')->orderByDesc('id')->firstOrFail()->code);
        $slots[2] = ['color' => 'new', 'remaining_g' => 1000, 'enabled' => 1, 'new' => ['name' => '', 'material' => $petg->id]];
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$printer->id}", $this->printerForm($printer, $slots))->assertSessionHasErrors('slots.2.new.name');
        $this->assertSame(2, FarmColor::where('name', 'lososová')->count());

        $this->actingAs($this->admin)->get("/admin/farm/printers/{$printer->id}")->assertOk()->assertSee('data-slot-new="3"', false)->assertSee('lososová / Salmon pink · Prusament');
    }

    public function test_codes_made_up_for_new_colours_follow_the_catalogue(): void
    {
        $silk = FarmMaterial::where('code', 'PLA')->where('finish', 'silk')->firstOrFail();
        $this->assertSame('PLA_Silk_Sage_Green', ColorCatalog::codeFor($silk, 'sage green'));
        $this->assertSame('PLA_Silk_Sage_Green', ColorCatalog::codeFor($silk, 'Sage green', 'Matplace'), 'our brand is not in the code');
        $this->assertSame('PLA_Silk_Salvejove_Zelena_Fiberlogy', ColorCatalog::codeFor($silk, 'šalvějově zelená', 'Fiberlogy'), 'accents go, words are capitalised');
    }

    public function test_a_colour_or_a_kind_goes_only_while_nothing_points_at_it(): void
    {
        $pla = FarmMaterial::where('code', 'PLA+')->firstOrFail();
        $loaded = FarmPrinterSlot::whereNotNull('farm_color_id')->firstOrFail()->color;
        $this->actingAs($this->admin)->from('/admin/farm/materials')->post("/admin/farm/colors/{$loaded->id}/delete")->assertRedirect('/admin/farm/materials')->assertSessionHas('error');
        $this->assertNotNull($loaded->fresh(), 'a loaded colour stays');

        $printed = FarmColor::create(['farm_material_id' => $pla->id, 'name' => 'tisklo se', 'code' => 'PLA+_Printed']);
        $file = ModelFile::create(['uuid' => (string) Str::uuid(), 'owner_user_id' => $this->admin->id, 'original_name' => 'a.stl', 'ext' => 'stl', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64), 'storage_path' => 'x', 'status' => ModelFile::STATUS_READY]);
        FarmOrder::create(['token' => Str::random(32), 'user_id' => $this->admin->id, 'model_file_id' => $file->id, 'status' => FarmOrder::STATUS_DONE, 'farm_material_id' => $pla->id, 'farm_color_id' => $printed->id]);
        $this->actingAs($this->admin)->from('/admin/farm/materials')->post("/admin/farm/colors/{$printed->id}/delete")->assertSessionHas('error');
        $this->assertNotNull($printed->fresh(), 'a colour an order was printed from stays');

        $mistake = FarmColor::create(['farm_material_id' => $pla->id, 'name' => 'omyl', 'code' => 'PLA+_Mistake', 'photo_path' => 'farm/colors/mistake.jpg']);
        Storage::disk('public')->put('farm/colors/mistake.jpg', 'x');
        $this->actingAs($this->admin)->from('/admin/farm/materials')->post("/admin/farm/colors/{$mistake->id}/delete")->assertRedirect('/admin/farm/materials')->assertSessionHas('status');
        $this->assertNull($mistake->fresh());
        Storage::disk('public')->assertMissing('farm/colors/mistake.jpg');

        // a kind with colours is refused; an empty one goes with its tuning rows
        $this->actingAs($this->admin)->post("/admin/farm/materials/{$pla->id}/delete")->assertSessionHas('error');
        $this->assertNotNull($pla->fresh());
        $this->actingAs($this->admin)->post('/admin/farm/materials/new', ['code' => 'PVA', 'finish' => 'solid', 'name' => 'PVA', 'filament_profile' => 'filament_pla.json', 'density' => 1.21, 'price_per_gram' => 2.5, 'enabled' => 1])->assertRedirect();
        $tpu = FarmMaterial::where('code', 'PVA')->firstOrFail();
        $this->assertGreaterThan(0, FarmPrinterMaterial::where('farm_material_id', $tpu->id)->count(), 'a new kind got its tuning rows');
        $this->actingAs($this->admin)->post("/admin/farm/materials/{$tpu->id}/delete")->assertRedirect('/admin/farm/materials')->assertSessionHas('status');
        $this->assertNull($tpu->fresh());
        $this->assertSame(0, FarmPrinterMaterial::where('farm_material_id', $tpu->id)->count());
    }

    public function test_a_new_kind_can_be_started_from_an_existing_one(): void
    {
        $petg = FarmMaterial::where('code', 'PETG')->firstOrFail();
        $this->actingAs($this->admin)->get('/admin/farm/materials?copy='.$petg->id)->assertOk()
            ->assertSee(__('farm.admin.copy_kind_hint', ['kind' => $petg->label()]))
            ->assertSee('value="'.$petg->filament_profile.'"', false);
        $this->actingAs($this->admin)->get('/admin/farm/materials?copy=999999')->assertOk();
    }
}
