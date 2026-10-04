<?php

namespace Tests\Feature;

use App\Engines\Exceptions\SlicerException;
use App\Engines\Slicer\OrcaSlicer;
use App\Support\Stopwatch;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** ORCA_PARALLEL: at most that many slicers at once; the next one waits for a free slot, and says how long. */
class OrcaSlotTest extends TestCase
{
    public function test_a_slicer_waits_for_a_free_slot(): void
    {
        $dir = sys_get_temp_dir().'/mp_orcaslot_'.uniqid();
        File::ensureDirectoryExists($dir);
        $orca = new OrcaSlicer(['work_dir' => $dir, 'timeout' => 1, 'parallel' => 1] + config('engines.orca'));
        $slot = (new \ReflectionClass($orca))->getMethod('slot');

        $clock = new Stopwatch;
        $held = $slot->invoke($orca, $clock);
        $this->assertIsResource($held);
        $this->assertArrayHasKey('orca_wait_s', $clock->toArray());

        // the only slot is taken: the next slicer gives up after its timeout instead of running alongside
        try {
            $slot->invoke($orca, new Stopwatch);
            $this->fail('a second slicer ran while the only slot was taken');
        } catch (SlicerException) {
        }

        flock($held, LOCK_UN);
        fclose($held);
        $again = $slot->invoke($orca, new Stopwatch);
        $this->assertIsResource($again);
        fclose($again);

        // no limit: no lock at all
        $free = new OrcaSlicer(['work_dir' => $dir, 'parallel' => 0] + config('engines.orca'));
        $this->assertNull((new \ReflectionClass($free))->getMethod('slot')->invoke($free, new Stopwatch));
        File::deleteDirectory($dir);
    }
}
