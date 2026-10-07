<?php

namespace Tests\Unit;

use App\Domain\Farm\GcodeSlot;
use PHPUnit\Framework\TestCase;

/** A print in several colours: `T<n>` written into the G-code at every change height, bottom to top. */
class GcodeColorChangesTest extends TestCase
{
    /** Five layers of 0.2 mm after a start macro, the way OrcaSlicer writes them for the S1. */
    private function gcode(): string
    {
        $out = "M117\nT0\nG9111 bedTemp=60 extruderTemp=215\n";
        for ($i = 1; $i <= 5; $i++) {
            $top = number_format($i * 0.2, 2, '.', '');
            $out .= ";LAYER_CHANGE\n;Z:{$top}\n;HEIGHT:0.20\n; AFTER_LAYER_CHANGE\nG1 Z{$top}\n;TYPE:Inner wall\nG1 X10 Y10 E1\n";
        }

        return $out;
    }

    /** @return list<array{0: string, 1: int}> every tool line with the index of the layer it sits in (0 = before the first) */
    private function tools(string $gcode): array
    {
        $found = [];
        $layer = 0;
        foreach (explode("\n", $gcode) as $line) {
            if ($line === ';LAYER_CHANGE') {
                $layer++;
            } elseif (preg_match('/^T(\d)/', $line, $m)) {
                $found[] = [$line, $layer];
            }
        }

        return $found;
    }

    public function test_every_change_lands_in_the_first_layer_wholly_above_it(): void
    {
        $out = GcodeSlot::colorChanges($this->gcode(), [['slot' => 2, 'z' => 0.4], ['slot' => 1, 'z' => 0.8]]);
        $tools = $this->tools($out);
        $this->assertSame(['T0', 0], [substr($tools[0][0], 0, 2), $tools[0][1]], 'the start slot stays');
        // the layer 0.4–0.6 has its middle at 0.5 ≥ 0.4: the first all-new layer; 0.8–1.0 for the second change
        $this->assertSame([['T2 ; colour change by matplace farm', 3], ['T1 ; colour change by matplace farm', 5]], array_slice($tools, 1));
        $this->assertStringContainsString("; AFTER_LAYER_CHANGE\nT2 ;", $out, 'after the slicer\'s own layer change block');
    }

    public function test_two_changes_in_one_layer_keep_the_upper_colour_and_a_change_to_the_running_slot_is_dropped(): void
    {
        $out = GcodeSlot::colorChanges($this->gcode(), [['slot' => 3, 'z' => 0.41], ['slot' => 1, 'z' => 0.45], ['slot' => 1, 'z' => 0.8], ['slot' => 0, 'z' => 0.9]]);
        $tools = array_slice($this->tools($out), 1);
        $this->assertSame([['T1 ; colour change by matplace farm', 3], ['T0 ; colour change by matplace farm', 5]], $tools);
    }

    public function test_the_one_second_colour_of_older_orders_is_the_one_entry_case(): void
    {
        $g = $this->gcode();
        $this->assertSame(GcodeSlot::colorChanges($g, [['slot' => 2, 'z' => 0.6]]), GcodeSlot::secondColor($g, ['slot' => 2, 'z' => 0.6]));
        $this->assertSame($g, GcodeSlot::colorChanges($g, []), 'nothing to change, nothing written');
        $this->assertSame($g, GcodeSlot::colorChanges($g, [['slot' => 0, 'z' => 0.6]]), 'the slot already printing');
    }

    public function test_the_retargeted_copy_starts_in_the_chosen_slot_and_changes_from_there(): void
    {
        $out = GcodeSlot::colorChanges(GcodeSlot::retarget($this->gcode(), 2), [['slot' => 2, 'z' => 0.4], ['slot' => 3, 'z' => 0.8]]);
        $tools = $this->tools($out);
        $this->assertStringStartsWith('T2 ; slot chosen', $tools[0][0]);
        $this->assertSame([['T3 ; colour change by matplace farm', 5]], array_slice($tools, 1), 'the first change goes to the running slot: no line for it');
    }
}
