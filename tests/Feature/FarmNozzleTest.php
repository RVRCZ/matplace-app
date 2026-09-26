<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Domain\Farm\ProfileLibrary;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Repair\PythonTool;
use App\Engines\Slicer\FakeSlicer;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A farm machine with a nozzle other than 0.4 mm (Kobra S1 #2 got a 0.2 mm nozzle on 25 Sep 2026): its own slicer
 * profiles, a layer ladder that follows the nozzle, and no tuning values borrowed from a 0.4 mm machine.
 */
class FarmNozzleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_quality_ladder_follows_the_nozzle(): void
    {
        $fine = new FarmPrinter(['nozzle_mm' => 0.2]);
        $this->assertSame(0.14, $fine->layerFor(0.28), 'draft');
        $this->assertSame(0.1, $fine->layerFor(0.20), 'standard');
        $this->assertSame(0.06, $fine->layerFor(0.12), 'fine');

        $standard = new FarmPrinter(['nozzle_mm' => 0.4]);
        foreach ([0.28, 0.20, 0.12] as $layer) {
            $this->assertSame($layer, $standard->layerFor($layer), 'the 0.4 nozzle keeps the settings as they are');
        }

        // never thicker than three quarters of the nozzle, never below what a machine can step
        $this->assertSame(0.45, (new FarmPrinter(['nozzle_mm' => 0.6]))->layerFor(0.6));
        $this->assertSame(0.04, (new FarmPrinter(['nozzle_mm' => 0.2]))->layerFor(0.02));
    }

    public function test_the_0_2_profile_set_is_written_for_a_0_2_nozzle(): void
    {
        $dir = base_path('engines/orca/profiles');
        $machine = json_decode((string) file_get_contents($dir.'/machine_kobras1_n02.json'), true);
        $this->assertSame(['0.2'], $machine['nozzle_diameter']);
        $this->assertSame('Anycubic Kobra S1 0.2 nozzle', $machine['name']);
        $this->assertSame(['0.15'], $machine['max_layer_height'], 'three quarters of the nozzle');

        foreach (['draft' => '0.14', 'standard' => '0.10', 'fine' => '0.06'] as $quality => $layer) {
            $p = json_decode((string) file_get_contents($dir.'/process_'.$quality.'_n02.json'), true);
            $this->assertSame($layer, $p['layer_height'], $quality);
            $this->assertSame([$machine['name']], $p['compatible_printers'], $quality);
            foreach (['line_width', 'outer_wall_line_width', 'inner_wall_line_width', 'initial_layer_line_width', 'sparse_infill_line_width'] as $w) {
                $this->assertLessThanOrEqual(0.25, (float) $p[$w], $quality.' '.$w.' must fit through a 0.2 nozzle');
                $this->assertGreaterThanOrEqual(0.2, (float) $p[$w], $quality.' '.$w);
            }
            $this->assertLessThanOrEqual(0.15, (float) $p['initial_layer_print_height'], $quality);
        }

        // the machine's own filament flavours: a 0.2 nozzle passes a fraction of the plastic of a 0.4 one
        foreach (['pla' => 2.0, 'petg' => 1.0, 'asa' => 1.0, 'tpu' => 1.0] as $f => $max) {
            $d = json_decode((string) file_get_contents($dir.'/filament_'.$f.'_kobras1_n02.json'), true);
            $this->assertStringContainsString('0.2 nozzle', $d['name'], $f);
            $this->assertLessThanOrEqual($max, (float) $d['filament_max_volumetric_speed'][0], $f);
        }
    }

    public function test_the_tuning_library_does_not_hand_0_4_values_to_another_nozzle(): void
    {
        $library = app(ProfileLibrary::class);
        $this->assertNotNull($library->lookup('Anycubic Kobra S1 Combo', 'PLA', 'solid'), 'the 0.4 machines still get the library');
        $this->assertNull($library->lookup('Anycubic Kobra S1 Combo', 'PLA', 'solid', 0.2));

        $this->seed(FarmSeeder::class);
        $printer = FarmPrinter::where('key', 'kobra-s1-02')->firstOrFail();
        $this->assertSame(0.2, (float) $printer->nozzle_mm);
        // a tuned row of the 0.4 machine is not inherited by the 0.2 one, however alike the machines are
        $pla = FarmMaterial::where('code', 'PLA')->where('finish', 'solid')->firstOrFail();
        FarmPrinterMaterial::where('farm_printer_id', FarmPrinter::where('key', 'kobra-s1-01')->value('id'))
            ->where('farm_material_id', $pla->id)->whereNull('farm_color_id')
            ->update(['status' => FarmPrinterMaterial::STATUS_TUNED, 'tested_at' => now(), 'overrides' => ['filament' => ['filament_max_volumetric_speed' => ['18']]]]);

        [$overrides, $source] = $library->startingValues($printer, $pla);
        $this->assertSame('generic', $source);
        $this->assertSame([], $overrides);
    }

    public function test_an_order_on_the_fine_machine_is_sliced_with_its_own_profiles(): void
    {
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);

        // only the 0.2 machine takes work: load a spool and switch the others off
        $printer = FarmPrinter::where('key', 'kobra-s1-02')->firstOrFail();
        $color = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail()->slots()->where('enabled', true)->firstOrFail()->farm_color_id;
        FarmPrinter::where('key', '!=', 'kobra-s1-02')->update(['enabled' => false]);
        $printer->update(['enabled' => true, 'mode' => FarmPrinter::MODE_MANUAL]);
        $printer->slots()->where('slot', 0)->update(['farm_color_id' => $color, 'remaining_g' => 900, 'enabled' => true]);

        $spy = new class(app(FakeSlicer::class)) implements Slicer
        {
            public ?SliceParams $seen = null;

            public function __construct(private readonly Slicer $inner) {}

            public function name(): string
            {
                return 'spy';
            }

            public function supportedFormats(): array
            {
                return $this->inner->supportedFormats();
            }

            public function slice(string $meshPath, SliceParams $params): SliceResult
            {
                $this->seen = $params;

                return $this->inner->slice($meshPath, $params);
            }

            public function measure(string $meshPath): Dimensions
            {
                return $this->inner->measure($meshPath);
            }
        };
        $this->app->instance(Slicer::class, $spy);

        $user = User::factory()->create();
        $path = sys_get_temp_dir().'/mp_nozzle_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid, 'quality' => 'fine'])->assertCreated();

        $order = FarmOrder::latest('id')->firstOrFail();
        $this->assertSame($printer->id, $order->farm_printer_id, 'the order landed on the fine machine');
        $this->assertNotNull($spy->seen, 'the order went through the slicer: '.$order->status.' '.$order->error);
        $this->assertSame('machine_kobras1_n02.json', $spy->seen->profiles['machine']);
        $this->assertSame('process_fine_n02.json', $spy->seen->profiles['process']);
        $this->assertSame('0.06', $spy->seen->overrides['process']['layer_height'], 'the fine quality is 0.06 mm on a 0.2 nozzle');
    }

    public function test_a_finer_nozzle_is_kept_for_fine_work(): void
    {
        $fine = new FarmPrinter(['nozzle_mm' => 0.2]);
        $this->assertTrue($fine->takesQuality('fine'));
        $this->assertFalse($fine->takesQuality('standard'));
        $this->assertFalse($fine->takesQuality('draft'));

        $standard = new FarmPrinter(['nozzle_mm' => 0.4]);
        foreach (['draft', 'standard', 'fine'] as $quality) {
            $this->assertTrue($standard->takesQuality($quality), $quality);
        }
    }

    public function test_only_fine_orders_reach_the_fine_machine(): void
    {
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);

        // the 0.2 machine is the only one with a spool: a standard order has nowhere to go, a fine one lands on it
        $printer = FarmPrinter::where('key', 'kobra-s1-02')->firstOrFail();
        $color = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail()->slots()->where('enabled', true)->firstOrFail()->farm_color_id;
        FarmPrinter::where('key', '!=', 'kobra-s1-02')->update(['enabled' => false]);
        $printer->update(['enabled' => true, 'mode' => FarmPrinter::MODE_MANUAL]);
        $printer->slots()->where('slot', 0)->update(['farm_color_id' => $color, 'remaining_g' => 900, 'enabled' => true]);

        $user = User::factory()->create();
        $path = sys_get_temp_dir().'/mp_nozzle_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');

        $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid, 'quality' => 'standard'])
            ->assertStatus(422)->assertJsonPath('error', 'no_printer');

        $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid, 'quality' => 'fine'])->assertCreated();
        $order = FarmOrder::latest('id')->firstOrFail();
        $this->assertSame($printer->id, $order->farm_printer_id);
        // and the colours it offers are the fine machine's own
        $this->assertNotEmpty(app(OrderService::class)->availableColors($order->fresh()));
        $order->update(['quality' => 'standard']);
        $this->assertEmpty(app(OrderService::class)->availableColors($order->fresh()), 'a coarser quality does not get this machine');
    }

    public function test_the_calibration_object_is_drawn_for_the_nozzle_of_its_machine(): void
    {
        $python = app(PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('python + manifold3d');
        }
        $out = sys_get_temp_dir().'/mp_calib_'.uniqid().'.stl';
        $fine = $python->runScript('calib_tool.py', ['detailed', $out, json_encode(['nozzle' => 0.2])], 60);
        $walls = collect($fine['features'])->firstWhere('name', 'thin_walls');
        $this->assertSame([0.2, 0.4, 0.6], $walls['thicknesses'], 'one, two and three lines of a 0.2 nozzle');

        $standard = $python->runScript('calib_tool.py', ['detailed', $out, json_encode([])], 60);
        $walls = collect($standard['features'])->firstWhere('name', 'thin_walls');
        $this->assertSame([0.4, 0.8, 1.2], $walls['thicknesses'], 'the 0.4 object is unchanged');
        @unlink($out);
    }
}
