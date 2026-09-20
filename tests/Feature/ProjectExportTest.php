<?php

namespace Tests\Feature;

use App\Engines\DTO\SliceParams;
use App\Engines\Project\OrcaProjectExporter;
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
