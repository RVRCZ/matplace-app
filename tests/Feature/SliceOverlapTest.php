<?php

namespace Tests\Feature;

use App\Domain\Stats\Speed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** How often heavy slices meet and what pace they keep alone and together (docs/AL.md §22, part C): read from the timings. */
final class SliceOverlapTest extends TestCase
{
    use RefreshDatabase;

    public function test_heavy_slices_that_met_are_counted_with_the_time_they_shared(): void
    {
        $t = fn (float $from, float $to, float $slicer, int $triangles = 0, array $more = []) => ['started' => $from, 'finished' => $to, 'slice1_s' => $slicer, 'triangles_sliced' => $triangles] + $more;
        $o = Speed::overlapOf([
            'farm' => [
                $t(1000, 1100, 90, 300000),                       // alone: 30 s per 100 000 triangles
                $t(2000, 2200, 180, 300000),                      // met the calculation below for 150 of its 200 s: 60 s per 100 000
                $t(5000, 5100, 80, 0, ['slice_cached' => true]),  // served from the slice cache: no slicer ran
                $t(6000, 6010, 5, 1000),                          // a light one, alone
                $t(7000, 7100, 60, 200000, ['slice2_s' => 30, 'queue_s' => 172.8, 'orca_wait_s' => 12.5]),   // slicer twice: heavy by the sum; alone
            ],
            'calc' => [
                $t(2050, 2300, 200, 400000),                      // met the farm run for 150 of its 250 s
                ['cache' => 'calculation'],                       // an earlier calculation's answer: nothing ran
            ],
        ], 30, 20);

        $this->assertSame(5, $o['slicing'], 'the runs a slicer worked for');
        $this->assertSame(4, $o['heavy']);
        $this->assertSame(2, $o['met']);
        $this->assertSame(2, $o['met_half']);
        $this->assertSame(300.0, $o['overlap_s'], '150 s for each of the two that met');
        $this->assertSame(560.0, $o['slicer_s']);
        $this->assertSame(2, $o['peak']);
        $this->assertSame(2, $o['pace_alone_n']);
        $this->assertSame(30.0, $o['pace_alone'], 'the median of 30 and 45, nearest rank');
        $this->assertSame(2, $o['pace_met_n']);
        $this->assertSame(50.0, $o['pace_met'], 'the median of 60 and 50, nearest rank');
        $this->assertSame(12.5, $o['slot_wait_s']);
        $this->assertSame(1, $o['queued_over_5s']);
        $this->assertSame(172.8, $o['queue_max_s']);
        $this->assertCount(2, $o['worst']);
        $this->assertSame(150.0, $o['worst'][0]['shared_s']);
        $this->assertSame(1, $o['worst'][0]['others']);
    }

    public function test_the_command_reads_the_timings_and_writes_nothing(): void
    {
        $this->artisan('matplace:slice-overlap', ['--days' => 30])->expectsOutputToContain('Heavy runs that met another slicing job: 0')->assertExitCode(0);
        $this->artisan('matplace:slice-overlap', ['--json' => true])->expectsOutputToContain('"heavy": 0')->assertExitCode(0);
    }
}
