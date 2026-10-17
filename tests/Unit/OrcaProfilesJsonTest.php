<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every slicer profile the farm ships must be JSON OrcaSlicer can load: a broken one fails every calculation on the
 * site with "return -5" and nothing else notices, because the test suite slices with the fake engine (a lost quote
 * in filament_pla.json took the calculations down for forty minutes on 17 Oct 2026).
 */
class OrcaProfilesJsonTest extends TestCase
{
    public function test_every_orca_profile_and_the_tuning_library_parse_as_json(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(glob($root.'/engines/orca/profiles/*.json') ?: [], [$root.'/database/data/farm_profile_library.json']);
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            $this->assertIsArray($data, basename($file).': '.json_last_error_msg());
            $this->assertNotEmpty($data, basename($file));
        }
    }
}
