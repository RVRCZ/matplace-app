<?php

namespace Tests\Unit;

use App\Domain\Farm\PlateLayout;
use App\Engines\DTO\Dimensions;
use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\ThreeMfWriter;
use App\Engines\Slicer\GcodeStats;
use App\Jobs\PrepareFarmOrder;
use PHPUnit\Framework\TestCase;

/**
 * The pieces of a tool changer's plate (docs/AM.md §12j): the filament list by position with the tools between filled
 * in, the grid of copies without the tower's band, Orca's per-layer tool change file, and the header of Orca 2.5 with a
 * figure per tool read back (the lines of the local slices of 6 Nov 2026).
 */
final class ToolChangerPlateTest extends TestCase
{
    private function filament(string $profile, string $colour, int $slot): array
    {
        return ['profile' => $profile, 'overrides' => ['nozzle_temperature' => ['215']], 'colour' => $colour, 'volume_mm3' => 100.0, 'slot_id' => $slot, 'color_id' => $slot * 10];
    }

    public function test_the_tools_between_the_ones_used_take_the_first_colours_profile_and_are_flagged(): void
    {
        $out = PrepareFarmOrder::withPlaceholders([4 => $this->filament('red.json', '#C01818', 3), 0 => $this->filament('white.json', '#F4F4F0', 1), 1 => $this->filament('black.json', '#101010', 2)], 0);
        $this->assertSame([0, 1, 2, 3, 4], array_keys($out), 'a filament per position up to the last tool used, in order');
        $this->assertSame([1, 2, null, null, 3], array_column($out, 'slot_id'));
        $this->assertSame(['white.json', 'black.json', 'white.json', 'white.json', 'red.json'], array_column($out, 'profile'));
        $this->assertSame(['#F4F4F0', '#101010', '#F4F4F0', '#F4F4F0', '#C01818'], array_column($out, 'colour'));
        $this->assertSame([false, false, true, true, false], array_map(fn ($f) => ! empty($f['placeholder']), $out));
        $this->assertSame(0.0, $out[2]['volume_mm3'], 'nothing prints from a placeholder');
        $this->assertSame(['nozzle_temperature' => ['215']], $out[3]['overrides']);
        // the first colour not among the tools (a change-only plate): the lowest tool's profile fills the gaps
        $out = PrepareFarmOrder::withPlaceholders([2 => $this->filament('black.json', '#101010', 2), 5 => $this->filament('red.json', '#C01818', 3)], 0);
        $this->assertSame([0, 1, 2, 3, 4, 5], array_keys($out));
        $this->assertSame(['black.json', 'black.json', 'black.json', 'black.json', 'black.json', 'red.json'], array_column($out, 'profile'));
        $this->assertSame([], PrepareFarmOrder::withPlaceholders([], 0));
        // eight tools used: nothing to fill
        $eight = [];
        for ($i = 0; $i < 8; $i++) {
            $eight[$i] = $this->filament('f'.$i.'.json', sprintf('#%06X', $i * 0x111111), $i + 1);
        }
        $this->assertSame($eight, PrepareFarmOrder::withPlaceholders($eight, 0));
    }

    public function test_the_grid_of_copies_without_the_towers_band_holds_more(): void
    {
        $bed = new Dimensions(248, 205, 270);
        $piece = new Dimensions(75, 75, 20);
        $with = PlateLayout::towerGrid($piece, $bed, 5.0, 5);
        $without = PlateLayout::towerGrid($piece, $bed, 5.0, 5, tower: false);
        $this->assertSame(4, $with['max'], 'the band of five filaments (12 mm each, the brim, the gap) takes a row or a column');
        $this->assertSame(6, $without['max'], 'three by two on the whole plate');
        $this->assertSame(['cols' => 3, 'rows' => 2], ['cols' => $without['cols'], 'rows' => $without['rows']]);
        $this->assertSame(['max' => 6, 'cols' => 2, 'rows' => 2], PlateLayout::towerGrid($piece, $bed, 5.0, 5, 4, tower: false));
    }

    public function test_the_tool_change_file_names_the_layer_and_the_tool(): void
    {
        $xml = ThreeMfWriter::toolChangesXml([['z' => 7.2, 'extruder' => 5, 'colour' => '#C01818'], ['z' => 3.0, 'extruder' => 2, 'colour' => null]]);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="utf-8"?>'."\n<custom_gcodes_per_layer>\n<plate>\n<plate_info id=\"1\"/>\n", $xml);
        $this->assertStringContainsString('<layer top_z="3" type="2" extruder="2" color="" extra="" gcode="tool_change"/>'."\n".'<layer top_z="7.2" type="2" extruder="5" color="#C01818" extra="" gcode="tool_change"/>', $xml, 'bottom to top');
        $this->assertStringEndsWith("<mode value=\"MultiAsSingle\"/>\n</plate>\n</custom_gcodes_per_layer>\n", $xml);
        // written into the project next to the objects
        $stl = tempnam(sys_get_temp_dir(), 'tc').'.stl';
        StlFile::writeBox($stl, 10, 10, 10);
        $path = tempnam(sys_get_temp_dir(), 'tc').'.3mf';
        try {
            ThreeMfWriter::write($path, [['name' => 'print', 'stl' => $stl, 'extruder' => 3]], [['z' => 5.0, 'extruder' => 2]]);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $this->assertStringContainsString('<metadata key="extruder" value="3"/>', (string) $zip->getFromName('Metadata/model_settings.config'));
            $this->assertStringContainsString('top_z="5" type="2" extruder="2"', (string) $zip->getFromName('Metadata/custom_gcode_per_layer.xml'));
            $zip->close();
            ThreeMfWriter::write($path, [['name' => 'print', 'stl' => $stl, 'extruder' => 1]]);
            $zip->open($path);
            $this->assertFalse($zip->getFromName('Metadata/custom_gcode_per_layer.xml'), 'no change, no file');
            $zip->close();
        } finally {
            @unlink($stl);
            @unlink($path);
        }
    }

    public function test_the_header_of_orca_2_5_gives_a_figure_per_tool_with_the_gaps_at_zero(): void
    {
        $three = "; HEADER_BLOCK_START\n; total layer number: 30\n; HEADER_BLOCK_END\nT0 S1 L2 D0\n  T1 S1 L0 D0\n  T4 S1 L0 D0\n"
            ."; filament used [mm] = 432.58, 373.02, 0.00, 0.00, 368.86\n; filament used [g] = 1.29, 1.11, 0.00, 0.00, 1.10\n; total filament used [g] = 3.50\n"
            ."; total filament change = 60\n; estimated printing time (normal mode) = 27m 54s\n; filament_type = PLA;PLA;PLA;PLA;PLA\n";
        $path = tempnam(sys_get_temp_dir(), 'gc');
        file_put_contents($path, $three);
        try {
            $s = GcodeStats::fromFile($path);
            $this->assertSame([1.29, 1.11, 0.0, 0.0, 1.10], $s['filament_grams']);
            $this->assertSame(3.5, $s['grams']);
            $this->assertSame(60, $s['changes']);
            $this->assertSame(28, $s['minutes']);
            $this->assertEqualsWithDelta(1.17446, $s['meters'], 0.0001);
            $eight = "; total layer number: 30\n; filament used [mm] = 374.35, 431.25, 431.25, 431.25, 431.25, 431.25, 373.02, 427.09\n"
                ."; filament used [g] = 1.12, 1.29, 1.29, 1.29, 1.29, 1.29, 1.11, 1.27\n; total filament used [g] = 9.93\n; total filament change = 210\n; estimated printing time (normal mode) = 1h 21m 48s\n";
            file_put_contents($path, $eight);
            $s = GcodeStats::fromFile($path);
            $this->assertCount(8, $s['filament_grams']);
            $this->assertSame([1.12, 1.29, 1.29, 1.29, 1.29, 1.29, 1.11, 1.27], $s['filament_grams']);
            $this->assertSame(9.93, $s['grams']);
            $this->assertSame(210, $s['changes']);
            $this->assertSame(82, $s['minutes']);
        } finally {
            @unlink($path);
        }
    }
}
