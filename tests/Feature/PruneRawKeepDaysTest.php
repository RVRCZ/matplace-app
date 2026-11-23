<?php

namespace Tests\Feature;

use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Syrový výstup generátoru (raw.glb s texturou ~60 MB) se maže po engines.raw_keep_days dnech (výchozí 3, env AI_RAW_KEEP_DAYS).
 */
class PruneRawKeepDaysTest extends TestCase
{
    use RefreshDatabase;

    private function rawAged(int $days): string
    {
        Storage::fake('models');
        $f = ModelFile::factory()->create(['origin' => 'generated']);
        $disk = Storage::disk('models');
        $disk->put($f->dir().'/raw.glb', 'glb');
        $path = $disk->path($f->dir().'/raw.glb');
        touch($path, time() - $days * 86400 - 3600);

        return $path;
    }

    public function test_default_keeps_raw_three_days(): void
    {
        $this->assertSame(3, config('engines.raw_keep_days'));
        $old = $this->rawAged(4);
        $this->artisan('matplace:prune')->assertSuccessful();
        $this->assertFileDoesNotExist($old);

        $fresh = $this->rawAged(2);
        $this->artisan('matplace:prune')->assertSuccessful();
        $this->assertFileExists($fresh);
    }

    public function test_keep_days_is_configurable(): void
    {
        config(['engines.raw_keep_days' => 7]);
        $p = $this->rawAged(5);
        $this->artisan('matplace:prune')->assertSuccessful();
        $this->assertFileExists($p);
    }
}
