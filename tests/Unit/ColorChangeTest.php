<?php

namespace Tests\Unit;

use App\Engines\Project\ColorChange;
use PHPUnit\Framework\TestCase;

/** The filament change goes on the first layer above the plate, in the file each slicer reads. */
class ColorChangeTest extends TestCase
{
    private function project(array $files): string
    {
        $path = sys_get_temp_dir().'/mp_cc_'.uniqid().'.3mf';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function read(string $path, string $name): ?string
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        $s = $zip->getFromName($name);
        $zip->close();

        return $s === false ? null : $s;
    }

    public function test_the_layer_above_the_plate_is_found(): void
    {
        $this->assertSame(2.6, ColorChange::layerAbove(2.4, 0.2, 0.2));      // the plate ends exactly on a layer: the next one changes
        $this->assertSame(2.6, ColorChange::layerAbove(2.5, 0.2, 0.2));
        $this->assertSame(2.44, ColorChange::layerAbove(2.4, 0.2, 0.28));    // 0.2 + 8 × 0.28
        $this->assertSame(2.48, ColorChange::layerAbove(2.4, 0.2, 0.12));    // 0.2 + 19 × 0.12
        $this->assertSame(0.2, ColorChange::layerAbove(0.1, 0.2, 0.2));
    }

    public function test_orca_project_gets_a_layer_entry(): void
    {
        $p = $this->project(['Metadata/project_settings.config' => json_encode(['layer_height' => '0.2', 'initial_layer_print_height' => '0.2'])]);
        $this->assertSame(2.6, ColorChange::add($p, 2.4, '#222222'));
        $xml = $this->read($p, 'Metadata/custom_gcode_per_layer.xml');
        // a custom entry with the text in `extra`: the only one OrcaSlicer turns into M600 on a single-nozzle machine
        $this->assertStringContainsString('<layer top_z="2.60" type="4" extruder="1" color="#222222" extra="M600" gcode="M600"/>', $xml);
        $this->assertStringContainsString('<mode value="SingleExtruder"/>', $xml);
        $this->assertNull($this->read($p, 'Metadata/Prusa_Slicer_custom_gcode_per_print_z.xml'));
    }

    public function test_a_picture_in_colours_changes_filament_at_every_step(): void
    {
        // three colours one on another, 0.6 mm each on a 3 mm plate; the last two fall into one layer and are one change
        $changes = [['z' => 3.0, 'hex' => '#F4F4F2'], ['z' => 3.6, 'hex' => '#1B1B1D'], ['z' => 4.2, 'hex' => '#F49AC1'], ['z' => 4.25, 'hex' => 'not a colour']];
        $orca = $this->project(['Metadata/project_settings.config' => json_encode(['layer_height' => '0.2', 'initial_layer_print_height' => '0.2'])]);
        $this->assertSame([3.2, 3.8, 4.4], ColorChange::addAll($orca, $changes));
        $xml = (string) $this->read($orca, 'Metadata/custom_gcode_per_layer.xml');
        $this->assertSame(3, substr_count($xml, '<layer '));
        $this->assertStringContainsString('<layer top_z="3.20" type="4" extruder="1" color="#F4F4F2" extra="M600" gcode="M600"/>', $xml);
        $this->assertStringContainsString('<layer top_z="4.40" type="4" extruder="1" color="#2B2B2B" extra="M600" gcode="M600"/>', $xml);
        $this->assertLessThan(strpos($xml, 'top_z="3.80"'), strpos($xml, 'top_z="3.20"'));

        $prusa = $this->project(['Metadata/Slic3r_PE.config' => "; layer_height = 0.2\n; first_layer_height = 0.2\n"]);
        $this->assertSame([3.2, 3.8, 4.4], ColorChange::addAll($prusa, $changes));
        $this->assertSame(3, substr_count((string) $this->read($prusa, 'Metadata/Prusa_Slicer_custom_gcode_per_print_z.xml'), '<code print_z='));
        $this->assertSame([], ColorChange::addAll($orca, []));
    }

    public function test_prusa_project_gets_a_print_z_entry(): void
    {
        $p = $this->project(['Metadata/Slic3r_PE.config' => "; generated\n; layer_height = 0.15\n; first_layer_height = 0.2\n; printer_model = COREONE\n"]);
        $this->assertSame(2.45, ColorChange::add($p, 2.4));                  // 0.2 + 15 × 0.15
        $xml = $this->read($p, 'Metadata/Prusa_Slicer_custom_gcode_per_print_z.xml');
        $this->assertStringContainsString('<code print_z="2.45" type="0" extruder="1"', $xml);
        $this->assertStringContainsString('gcode="M600"', $xml);
    }

    public function test_a_project_without_settings_is_left_alone(): void
    {
        $p = $this->project(['3D/3dmodel.model' => '<model/>']);
        $this->assertNull(ColorChange::add($p, 2.4));
    }
}
