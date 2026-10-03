<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\DTO\SliceParams;
use App\Engines\Project\OrcaProjectExporter;
use App\Engines\Project\PrusaProjectExporter;
use App\Engines\Repair\PythonTool;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** "I have a printer": printer list, 3MF project download with tool-specific settings, profile catalogue from vendor presets. */
class ProjectExportTest extends TestCase
{
    use RefreshDatabase;

    private function uploadCube(): string
    {
        Storage::fake('models');
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'Kostka.stl', null, null, true)])->json('file.uuid');
    }

    private function settingsOf(string $file): array
    {
        $zip = new \ZipArchive;
        $zip->open($file);
        $cfg = json_decode((string) $zip->getFromName('Metadata/project_settings.config'), true);
        $zip->close();

        return $cfg;
    }

    /** A QR sign: the project stops the printer above the plate for the dark filament; a plain upload gets no such stop. */
    public function test_a_plate_with_a_code_gets_a_filament_change_above_the_plate(): void
    {
        $uuid = $this->uploadCube();
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $plain = $this->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s")->assertOk();
        $this->assertNull($this->entry($plain->baseResponse->getFile()->getPathname(), 'Metadata/custom_gcode_per_layer.xml'));

        $file->forceFill(['origin' => 'tool', 'origin_ref' => 'qr', 'tool_params' => ['url' => 'https://matplace.com', 'color_change_mm' => 2.4]])->save();
        $this->assertSame(2.4, $file->colorChangeMm());
        $this->assertSame(3.6, $file->colorChangeMm(1.5));
        $r = $this->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s&quality=standard")->assertOk();
        $xml = $this->entry($r->baseResponse->getFile()->getPathname(), 'Metadata/custom_gcode_per_layer.xml');
        $this->assertStringContainsString('top_z="2.60"', $xml, 'the first 0.2 mm layer above a 2.4 mm plate');
        $this->assertStringContainsString('gcode="M600"', $xml);
        $this->assertStringContainsString('color="'.ParametricGenerator::COLOR_HEX['black'].'"', $xml, 'a design without colours of its own: a black code');
        // the slicer shows the change in the colour the code was designed in
        $file->forceFill(['tool_params' => ['code_color' => 'blue'] + $file->tool_params])->save();
        $r = $this->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s&quality=standard")->assertOk();
        $this->assertStringContainsString('color="'.ParametricGenerator::COLOR_HEX['blue'].'"', $this->entry($r->baseResponse->getFile()->getPathname(), 'Metadata/custom_gcode_per_layer.xml'));
        $r = $this->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s&quality=standard&scale=2")->assertOk();
        $this->assertStringContainsString('top_z="5.00"', $this->entry($r->baseResponse->getFile()->getPathname(), 'Metadata/custom_gcode_per_layer.xml'), 'twice the size: the plate is 4.8 mm');
        $this->get('/')->assertOk()->assertSee('dl-color', false);
    }

    private function entry(string $file, string $name): ?string
    {
        $zip = new \ZipArchive;
        $zip->open($file);
        $s = $zip->getFromName($name);
        $zip->close();

        return $s === false ? null : $s;
    }

    public function test_printer_list_is_grouped_by_brand(): void
    {
        $r = $this->getJson('/api/printers')->assertOk();
        $this->assertSame('Bambu Lab', $r->json('vendors.0.vendor'));
        $this->assertSame('bbl-a1-mini', $r->json('vendors.0.printers.0.id'));
    }

    public function test_project_download_carries_printer_and_customer_settings(): void
    {
        $uuid = $this->uploadCube();
        $r = $this->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s&material=PETG&quality=fine&infill=40&supports=1");
        $r->assertOk()->assertHeader('content-disposition', 'attachment; filename=kostka-prusa-mk4s.3mf');
        $cfg = $this->settingsOf($r->baseResponse->getFile()->getPathname());
        $this->assertSame('prusa-mk4s', $cfg['printer']);
        $this->assertSame('40%', $cfg['sparse_infill_density']);
        $this->assertSame('1', $cfg['enable_support']);
        $this->assertSame('PETG', $cfg['material']);

        $this->getJson("/api/files/{$uuid}/project.3mf?printer=nonexistent")->assertStatus(422)->assertJsonPath('error', 'export_failed');
        $this->getJson("/api/files/{$uuid}/project.3mf")->assertStatus(422);
    }

    public function test_tool_kinds_shape_the_settings(): void
    {
        $litho = OrcaProjectExporter::overrides(new SliceParams('PLA', 'fine', 15, null), ['kind' => 'lithophane']);
        $this->assertSame('100%', $litho['sparse_infill_density']);
        $this->assertSame('12', $litho['wall_loops']);
        $this->assertSame('0', $litho['enable_support']);

        $figure = OrcaProjectExporter::overrides(new SliceParams('PLA', 'standard', 15, true), ['kind' => 'generated']);
        $this->assertSame('tree(auto)', $figure['support_type']);

        // "auto" supports on a generated model become the tool's recommendation (on)
        $uuid = $this->uploadCube();
        ModelFile::where('uuid', $uuid)->update(['origin' => 'generated', 'origin_ref' => 'tok']);
        $cfg = $this->settingsOf($this->get("/api/files/{$uuid}/project.3mf?printer=bbl-a1-mini&supports=auto")->baseResponse->getFile()->getPathname());
        $this->assertSame('1', $cfg['enable_support']);
        $this->assertSame('tree(auto)', $cfg['support_type']);
    }

    public function test_prusaslicer_bundle_conditions_and_project_config(): void
    {
        $python = app(PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $dir = sys_get_temp_dir().'/mp_prusa_'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/bundle.ini', implode(PHP_EOL, [
            '[printer_model:ONE]', 'name = Prusa ONE', '',
            '[printer:*common*]', 'printer_technology = FFF', 'bed_shape = 0x0,250x0,250x220,0x220', 'max_print_height = 270', 'printer_notes = PRINTER_VENDOR_PRUSA3D', '',
            '[printer:Prusa ONE HF0.4 nozzle]', 'inherits = *common*', 'printer_model = ONE', 'printer_variant = HF0.4', 'nozzle_diameter = 0.4', 'nozzle_high_flow = 1', '',
            '[printer:Prusa ONE 0.6 nozzle]', 'inherits = *common*', 'printer_model = ONE', 'printer_variant = 0.6', 'nozzle_diameter = 0.6', '',
            '[print:*base*]', 'perimeters = 2', 'fill_pattern = grid', '',
            '[print:0.20mm STRUCTURAL @ONE 0.4]', 'inherits = *base*', 'layer_height = 0.2', 'compatible_printers_condition = printer_model=~/(ONE|TWO)/ and nozzle_diameter[0]==0.4', '',
            '[print:0.10mm DETAIL @ONE 0.4]', 'inherits = *base*', 'layer_height = 0.1', 'compatible_printers_condition = printer_model=="ONE" and nozzle_diameter[0]==0.4 and ! single_extruder_multi_material', '',
            '[print:0.30mm DRAFT @ONE 0.6]', 'inherits = *base*', 'layer_height = 0.3', 'compatible_printers_condition = printer_model=="ONE" and nozzle_diameter[0]==0.6', '',
            '[filament:Generic PLA @ONE HF0.4]', 'temperature = 215', 'compatible_printers_condition = printer_notes=~/.*PRUSA3D.*/ and nozzle_high_flow[0]', '',
            '[filament:Generic PLA @ONE]', 'temperature = 210', 'compatible_printers_condition = printer_notes=~/.*PRUSA3D.*/ and ! nozzle_high_flow[0]', '',
        ]));
        $cat = $python->runScript('prusa_profiles.py', ['catalog', $dir.'/bundle.ini']);
        $this->assertTrue($cat['ok']);
        $this->assertCount(1, $cat['printers']);                                   // the 0.6 mm preset is not offered
        $p = $cat['printers'][0];
        $this->assertSame('Prusa ONE HF0.4 nozzle', $p['machine']);
        $this->assertSame('0.10mm DETAIL @ONE 0.4', $p['processes']['fine']);
        $this->assertSame('0.20mm STRUCTURAL @ONE 0.4', $p['processes']['draft']);     // the 0.6 mm draft is not compatible
        $this->assertSame(['PLA' => 'Generic PLA @ONE HF0.4'], $p['filaments']);      // the high-flow condition decides

        MeshFixtures::cubeStl($dir.'/cube.stl', 20);
        $over = PrusaProjectExporter::overrides(new SliceParams('PLA', 'fine', 15, null), ['kind' => 'lithophane']);
        $r = $python->runScript('prusa_profiles.py', ['project', $dir.'/bundle.ini', $p['machine'], $p['processes']['fine'], $p['filaments']['PLA'], $dir.'/cube.stl', $dir.'/out.3mf', json_encode($over)]);
        $this->assertTrue($r['ok'], json_encode($r));
        $zip = new \ZipArchive;
        $zip->open($dir.'/out.3mf');
        $cfg = (string) $zip->getFromName('Metadata/Slic3r_PE.config');
        $this->assertNotFalse($zip->locateName('3D/3dmodel.model'));
        $zip->close();
        $this->assertStringContainsString('; printer_settings_id = Prusa ONE HF0.4 nozzle', $cfg);
        $this->assertStringContainsString('; fill_density = 100%', $cfg);
        $this->assertStringContainsString('; fill_pattern = rectilinear', $cfg);     // PrusaSlicer refuses grid at 100 %
        $this->assertStringContainsString('; perimeters = 12', $cfg);
        $this->assertStringContainsString('; temperature = 215', $cfg);
        $this->assertStringNotContainsString('compatible_printers_condition', $cfg);
        File::deleteDirectory($dir);
    }

    public function test_catalogue_is_built_from_vendor_presets_with_inheritance(): void
    {
        $python = app(PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $root = sys_get_temp_dir().'/mp_profiles_'.uniqid();
        $put = function (string $rel, array $d) use ($root) {
            File::ensureDirectoryExists(dirname($root.'/'.$rel));
            File::put($root.'/'.$rel, json_encode($d));
        };
        $put('Acme/machine/common.json', ['type' => 'machine', 'name' => 'fdm_common', 'instantiation' => 'false', 'printable_height' => '200', 'nozzle_diameter' => ['0.4']]);
        $put('Acme/machine/one.json', ['type' => 'machine', 'name' => 'Acme One 0.4 nozzle', 'inherits' => 'fdm_common', 'instantiation' => 'true', 'printer_model' => 'Acme One', 'printable_area' => ['0x0', '220x0', '220x220', '0x220']]);
        $put('Acme/machine/big.json', ['type' => 'machine', 'name' => 'Acme One 0.8 nozzle', 'inherits' => 'fdm_common', 'instantiation' => 'true', 'printer_model' => 'Acme One', 'nozzle_diameter' => ['0.8']]);
        $put('Acme/process/base.json', ['type' => 'process', 'name' => 'proc_common', 'instantiation' => 'false', 'wall_loops' => '2', 'compatible_printers' => ['Acme One 0.4 nozzle']]);
        $put('Acme/process/std.json', ['type' => 'process', 'name' => '0.20mm Standard @Acme One', 'inherits' => 'proc_common', 'instantiation' => 'true', 'layer_height' => '0.2']);
        $put('Acme/process/fine.json', ['type' => 'process', 'name' => '0.12mm Fine @Acme One', 'inherits' => 'proc_common', 'instantiation' => 'true', 'layer_height' => '0.12']);
        $put('Acme/filament/pla.json', ['type' => 'filament', 'name' => 'Generic PLA @Acme', 'instantiation' => 'true', 'compatible_printers' => ['Acme One 0.4 nozzle']]);
        $put('Acme/filament/placf.json', ['type' => 'filament', 'name' => 'Acme PLA-CF @Acme', 'instantiation' => 'true', 'compatible_printers' => ['Acme One 0.4 nozzle']]);

        $cat = $python->runScript('orca_profiles.py', ['catalog', $root]);
        $this->assertTrue($cat['ok']);
        $this->assertCount(1, $cat['printers']);                       // the 0.8 mm preset is not offered
        $p = $cat['printers'][0];
        $this->assertSame('acme-one', $p['id']);
        $this->assertSame(['x' => 220, 'y' => 220, 'z' => 200], $p['bed']);
        $this->assertSame('0.12mm Fine @Acme One', $p['processes']['fine']);
        $this->assertSame('0.20mm Standard @Acme One', $p['processes']['draft']);   // nearest available
        $this->assertSame(['PLA' => 'Generic PLA @Acme'], $p['filaments']);          // carbon-filled is never picked

        $b = $python->runScript('orca_profiles.py', ['bundle', $root, 'Acme', $p['machine'], $p['processes']['fine'], $p['filaments']['PLA'], $root.'/out', json_encode(['sparse_infill_density' => '100%'])]);
        $this->assertTrue($b['ok']);
        $proc = json_decode((string) File::get($root.'/out/process.json'), true);
        $this->assertSame('2', $proc['wall_loops']);                  // inherited
        $this->assertSame('100%', $proc['sparse_infill_density']);    // override
        $this->assertSame(['Acme One 0.4 nozzle'], $proc['compatible_printers']);
        $this->assertArrayNotHasKey('inherits', $proc);
        File::deleteDirectory($root);
    }
}
