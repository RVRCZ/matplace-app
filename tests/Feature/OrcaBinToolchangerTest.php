<?php

namespace Tests\Feature;

use App\Engines\DTO\SliceParams;
use App\Engines\Exceptions\SlicerException;
use App\Engines\Slicer\CachedSlicer;
use App\Engines\Slicer\OrcaSlicer;
use App\Models\FarmPrinter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Two slicers side by side (docs/AM.md §12e): the INDX tool changer slices with Orca 2.5 (`bin_toolchanger`) because
 * Orca 2.4 crashes on its profiles, every other machine keeps the farm's slicer; without the second binary everything
 * runs as before.
 */
class OrcaBinToolchangerTest extends TestCase
{
    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function fakeBinary(string $content): string
    {
        $f = tempnam(sys_get_temp_dir(), 'orca');
        file_put_contents($f, $content);
        $this->temp[] = $f;

        return $f;
    }

    public function test_the_tool_changer_takes_the_second_binary_and_the_rest_of_the_farm_the_first(): void
    {
        $orca = new OrcaSlicer(['bin' => '/opt/orca24/AppRun', 'bin_toolchanger' => '/opt/orca25/AppRun'] + config('engines.orca'));

        $this->assertSame('/opt/orca25/AppRun', $orca->binFor('machine_coreone_indx8t.json'));
        $this->assertSame('/opt/orca24/AppRun', $orca->binFor('machine_kobrax.json'));
        $this->assertSame('/opt/orca24/AppRun', $orca->binFor('machine.json'), 'the S1');
        $this->assertSame('/opt/orca24/AppRun', $orca->binFor(null), 'the calculator without a farm profile');
    }

    public function test_without_a_second_binary_everything_slices_with_the_first(): void
    {
        foreach ([[], ['bin_toolchanger' => null], ['bin_toolchanger' => '']] as $extra) {
            $orca = new OrcaSlicer($extra + ['bin' => '/opt/orca24/AppRun'] + config('engines.orca'));
            $this->assertSame('/opt/orca24/AppRun', $orca->binFor('machine_coreone_indx8t.json'));
        }
    }

    public function test_the_printer_and_the_slicer_agree_on_what_a_tool_changer_is(): void
    {
        $this->assertTrue((new FarmPrinter(['machine_profile' => 'machine_coreone_indx8t.json']))->isToolChanger());
        $this->assertFalse((new FarmPrinter(['machine_profile' => 'machine_kobrax.json']))->isToolChanger());
        $this->assertTrue(OrcaSlicer::isToolChangerProfile('machine_coreone_indx8t.json'));
        $this->assertFalse(OrcaSlicer::isToolChangerProfile('machine.json'));
        $this->assertFalse(OrcaSlicer::isToolChangerProfile(null));
    }

    public function test_the_slice_cache_keys_on_the_binary_the_slice_really_runs_with(): void
    {
        $bin24 = $this->fakeBinary('orca 2.4');
        $bin25 = $this->fakeBinary('orca 2.5 alpha, a longer file');
        $mesh = $this->fakeBinary("solid a\nendsolid a\n");
        $cache = function (?string $toolchanger) use ($bin24): CachedSlicer {
            $orca = new OrcaSlicer(['bin' => $bin24, 'bin_toolchanger' => $toolchanger] + config('engines.orca'));

            return new CachedSlicer($orca, sys_get_temp_dir(), sys_get_temp_dir(), [], $bin24);
        };
        $indx = new SliceParams('pla', profiles: ['machine' => 'machine_coreone_indx8t.json']);
        $s1 = new SliceParams('pla', profiles: ['machine' => 'machine.json']);

        // the tool changer's key changes with its own binary, the S1's does not
        $this->assertNotSame($cache(null)->key($mesh, $indx), $cache($bin25)->key($mesh, $indx));
        $this->assertSame($cache(null)->key($mesh, $s1), $cache($bin25)->key($mesh, $s1));
        // and the same binary gives the same key
        $this->assertSame($cache($bin25)->key($mesh, $indx), $cache($bin25)->key($mesh, $indx));
    }

    /** The inner attempt of a slice must see the binary (5 Nov 2026: `$bin` was set outside the closure and every farm slice failed). */
    public function test_the_slice_itself_runs_the_binary_it_chose(): void
    {
        $win = PHP_OS_FAMILY === 'Windows';
        $fake = tempnam(sys_get_temp_dir(), 'orca').($win ? '.cmd' : '.sh');
        file_put_contents($fake, $win ? "@echo off\r\necho fake slicer %*\r\nexit /b 0\r\n" : "#!/bin/sh\necho fake slicer \"$@\"\nexit 0\n");
        chmod($fake, 0755);
        $this->temp[] = $fake;
        $work = sys_get_temp_dir().'/mp_fake_orca_'.uniqid();
        $orca = new OrcaSlicer(['bin' => $fake, 'xvfb' => false, 'work_dir' => $work, 'timeout' => 60] + config('engines.orca'));
        try {
            $orca->slice(base_path('tests/fixtures/cube10.stl'), new SliceParams('PLA', supports: false));
            $this->fail('a slicer that writes no G-code must be reported');
        } catch (SlicerException $e) {
            // the fake ran (its words come back in the slicer's output), so the command was built with the binary
            $this->assertStringContainsString('Slice failed (no gcode)', $e->getMessage());
            $this->assertStringContainsString('fake slicer', $e->getMessage());
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function test_a_missing_second_binary_stops_the_tool_changers_slice_with_a_clear_message(): void
    {
        $orca = new OrcaSlicer(['bin' => __FILE__, 'bin_toolchanger' => '/nowhere/orca25/AppRun'] + config('engines.orca'));
        $this->expectExceptionMessage('OrcaSlicer binary not found: /nowhere/orca25/AppRun');
        $orca->slice(__FILE__, new SliceParams('pla', profiles: ['machine' => 'machine_coreone_indx8t.json']));
    }
}
