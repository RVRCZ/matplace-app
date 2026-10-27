<?php

namespace Tests\Unit;

use App\Engines\Photo\RembgBackgroundRemover;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * rembg as the server runs it (production, 27 Oct 2026): www-data cannot write numba's cache next to the package, and
 * the model may be missing, so the engine hands the script its own cache folder and says it is available only with the
 * model in place.
 */
final class RembgBackgroundRemoverTest extends TestCase
{
    public function test_the_probe_tells_rembg_and_the_model_apart_and_the_script_gets_the_cache_and_the_home(): void
    {
        Cache::flush();
        $cache = sys_get_temp_dir().'/mp_numba_'.uniqid();
        $config = ['bin' => 'python3', 'timeout' => 30, 'photo_home' => '/opt/matplace-py/u2net', 'photo_numba_cache' => $cache];
        Process::fake(['*photo_cut.py*' => Process::result(json_encode(['ok' => true, 'rembg' => '2.0.85', 'model_present' => false, 'home' => '/opt/matplace-py/u2net']))]);

        $engine = new RembgBackgroundRemover($config);
        $this->assertFalse($engine->available(), 'rembg without its model cuts nothing');
        $this->assertSame(['ok' => true, 'rembg' => '2.0.85', 'model_present' => false, 'home' => '/opt/matplace-py/u2net'], $engine->probe());
        $this->assertDirectoryExists($cache, 'the cache folder is made for the script');
        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), '--probe')
            && ($process->environment['NUMBA_CACHE_DIR'] ?? null) === $cache && ($process->environment['U2NET_HOME'] ?? null) === '/opt/matplace-py/u2net');

        // the model arrived: a new probe (another hour, or the cache cleared) says available
        Cache::flush();
        Process::fake(['*photo_cut.py*' => Process::result(json_encode(['ok' => true, 'rembg' => '2.0.85', 'model_present' => true, 'home' => '/opt/matplace-py/u2net']))]);
        $this->assertTrue((new RembgBackgroundRemover($config))->available());

        // no rembg at all: the error rides along for the command's report
        Cache::flush();
        Process::fake(['*photo_cut.py*' => Process::result(json_encode(['ok' => false, 'error' => "No module named 'rembg'"]))]);
        $engine = new RembgBackgroundRemover($config);
        $this->assertFalse($engine->available());
        $this->assertSame("No module named 'rembg'", $engine->probe()['error']);
        @rmdir($cache);
    }
}
