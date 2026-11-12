<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Engines\Contracts\Slicer;
use App\Engines\Exceptions\SupersededException;
use App\Engines\Mesh\MeshDecimator;
use App\Engines\Repair\ScriptRunner;
use App\Jobs\PrepareFarmOrder;
use App\Models\FarmOrder;
use App\Models\FarmOrderEvent;
use App\Models\User;
use App\Support\ProcessTree;
use App\Support\RunGuard;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A slice nobody wants any more is stopped, not finished (docs/AL.md §22): when an order asks for another slice while
 * one runs, the running slicer process is killed with everything it started, the old run writes nothing and leaves no
 * "failed", and the new run slices. Real processes that can be killed: PHP scripts that sleep, and the farm's own
 * OrcaSlicer class over the tests' stand-in binary (tests/Support/fake_orca.php) told to take its time.
 */
final class SupersededSliceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        $this->dir = sys_get_temp_dir().'/mp_superseded_'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        RunGuard::clear();
        foreach (['MP_FAKE_ORCA_PID', 'MP_FAKE_ORCA_SLEEP'] as $name) {
            putenv($name);
            unset($_SERVER[$name]);
        }
        foreach (glob($this->dir.'/*.pid') ?: [] as $f) {
            ProcessTree::kill((int) file_get_contents($f), 0.2);   // whatever a failed test left running
        }
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function gone(int $pid, float $within = 5.0): bool
    {
        $until = microtime(true) + $within;
        while (ProcessTree::alive($pid)) {
            if (microtime(true) > $until) {
                return false;
            }
            usleep(100000);
        }

        return true;
    }

    private function waitFor(string $file, float $within = 10.0): bool
    {
        $until = microtime(true) + $within;
        while (! is_file($file) || filesize($file) === 0) {
            if (microtime(true) > $until) {
                return false;
            }
            usleep(50000);
            clearstatcache(true, $file);
        }

        return true;
    }

    public function test_without_a_watcher_a_process_runs_as_it_always_did(): void
    {
        $this->assertFalse(RunGuard::watching());
        $r = RunGuard::run(Process::timeout(30), [PHP_BINARY, '-r', 'echo "done";']);
        $this->assertTrue($r->successful());
        $this->assertSame('done', trim($r->output()));
    }

    public function test_a_watched_process_that_is_still_wanted_runs_to_its_end(): void
    {
        RunGuard::watch(fn () => false);
        $r = RunGuard::run(Process::timeout(30), [PHP_BINARY, '-r', 'usleep(300000); echo "done";']);
        $this->assertSame('done', trim($r->output()));
    }

    public function test_a_superseded_process_is_killed_with_everything_it_started(): void
    {
        // a wrapper that starts the real work as its child and waits for it, the way xvfb-run starts the slicer
        $child = $this->dir.'/child.php';
        file_put_contents($child, '<?php file_put_contents($argv[1]."/child.pid", (string) getmypid()); sleep(120);');
        $parent = $this->dir.'/parent.php';
        file_put_contents($parent, '<?php file_put_contents($argv[1]."/parent.pid", (string) getmypid());'
            .' $p = proc_open([PHP_BINARY, $argv[1]."/child.php", $argv[1]], [], $pipes); sleep(120);');

        // not wanted any more as soon as the child is up
        RunGuard::watch(fn () => is_file($this->dir.'/child.pid') && filesize($this->dir.'/child.pid') > 0);
        $start = microtime(true);
        try {
            RunGuard::run(Process::timeout(300), [PHP_BINARY, $parent, $this->dir]);
            $this->fail('the process ran to its end');
        } catch (SupersededException) {
            // as it should
        }
        $this->assertLessThan(30, microtime(true) - $start, 'stopped long before its two minutes');
        $this->assertTrue($this->gone((int) file_get_contents($this->dir.'/parent.pid')), 'the wrapper is gone');
        $this->assertTrue($this->gone((int) file_get_contents($this->dir.'/child.pid')), 'and what it started as well');
    }

    public function test_a_job_already_superseded_starts_no_process_at_all(): void
    {
        RunGuard::watch(fn () => true);
        $this->expectException(SupersededException::class);
        RunGuard::run(Process::timeout(30), [PHP_BINARY, '-r', 'file_put_contents('.var_export($this->dir.'/ran', true).', "1");']);
        $this->assertFileDoesNotExist($this->dir.'/ran');
    }

    private function upload(): string
    {
        $path = $this->dir.'/dragon.stl';
        MeshFixtures::cubeStl($path, 20.0);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'dragon.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function bigOrder(): FarmOrder
    {
        config(['engines.mesh.decimate_above' => 5]);   // a cube has 12 triangles: a "big model", sliced on the customer's word
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $this->upload()])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    /** The farm's own OrcaSlicer over the stand-in binary; `$sleep` seconds before it does anything. */
    private function realSlicerProcess(int $sleep): void
    {
        $win = PHP_OS_FAMILY === 'Windows';
        $script = base_path('tests/Support/fake_orca.php');
        $fake = $this->dir.'/fake_orca'.($win ? '.cmd' : '.sh');
        file_put_contents($fake, $win ? "@echo off\r\n\"".PHP_BINARY."\" \"{$script}\" %*\r\n" : "#!/bin/sh\n\"".PHP_BINARY."\" \"{$script}\" \"\$@\"\n");
        chmod($fake, 0755);
        // the slicer's process inherits only what $_SERVER knows (Symfony Process): both
        foreach (['MP_FAKE_ORCA_PID' => $this->dir.'/slicer.pid', 'MP_FAKE_ORCA_SLEEP' => (string) $sleep] as $name => $value) {
            putenv($name.'='.$value);
            $_SERVER[$name] = $value;
        }
        @unlink($this->dir.'/slicer.pid');
        config(['engines.slicer' => 'orca', 'engines.orca.bin' => $fake, 'engines.orca.bin_toolchanger' => null, 'engines.orca.slicer' => '', 'engines.orca.xvfb' => false,
            'engines.orca.work_dir' => $this->dir.'/work', 'engines.orca.timeout' => 300, 'engines.orca.cache' => false]);
        $this->app->forgetInstance(Slicer::class);
    }

    public function test_a_change_of_the_choices_stops_the_running_slicer_and_the_new_run_slices(): void
    {
        $order = $this->bigOrder();
        Queue::fake();
        $choices = ['quality' => 'fine', 'strength' => 'standard', 'unit' => 'mm', 'copies' => 1, 'supports' => 'auto'];
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", $choices)->assertOk()->assertJsonPath('slice_run', 1);
        $first = Queue::pushed(PrepareFarmOrder::class)->last();
        $this->assertSame(1, $first->run);

        // the slicer of run 1 would take two minutes; while it runs the customer picks another quality (the reslice
        // request arrives in another process on the server: here it is made the moment the job asks whether it is overtaken)
        $this->realSlicerProcess(120);
        $changed = false;
        DB::listen(function ($query) use (&$changed, $order, $choices) {
            if (! $changed && str_contains($query->sql, 'slice_run') && str_starts_with(ltrim(strtolower($query->sql)), 'select') && is_file($this->dir.'/slicer.pid')) {
                $changed = true;
                app(OrderService::class)->reslice($order->fresh(), 'draft', $choices['strength'], 'mm');
            }
        });
        $start = microtime(true);
        app()->call([$first, 'handle']);
        $took = microtime(true) - $start;

        $this->assertTrue($changed, 'the slicer was running when the choices changed');
        $this->assertLessThan(60, $took, 'the old run did not wait for its slicer');
        $this->assertTrue($this->gone((int) file_get_contents($this->dir.'/slicer.pid')), 'the slicer process of the old run is gone');
        $this->assertSame([], glob($this->dir.'/work/job_*') ?: [], 'and so is its working directory');
        $this->assertFalse(RunGuard::watching());

        // the order: still being prepared for the new choices, no error in between, nothing of the old run written
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_UPLOADED, $order->status);
        $this->assertSame(2, $order->slice_run);
        $this->assertSame('draft', $order->quality);
        $this->assertNull($order->error);
        $this->assertNull($order->gcode_path);
        $this->assertNull($order->est_minutes);
        $this->assertNull($order->timings, 'an overtaken run leaves the timings to the run that replaced it');
        $this->assertFalse(FarmOrderEvent::where('farm_order_id', $order->id)->where('to', FarmOrder::STATUS_FAILED)->exists(), 'no "failed" in between');
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk();
        $state->assertJsonPath('status', 'uploaded')->assertJsonPath('error', null)->assertJsonPath('slice_run', 2);
        $this->assertSame('choices changed while slicing: run 2 replaces run 1', FarmOrderEvent::where('farm_order_id', $order->id)->latest('id')->first()->note);

        // the run that replaced it was queued by the change, starts with a two-second wait at most and slices
        $second = Queue::pushed(PrepareFarmOrder::class)->last();
        $this->assertSame(2, $second->run);
        $this->assertSame(2, $second->middleware()[0]->releaseAfter);
        $this->realSlicerProcess(0);
        app()->call([$second, 'handle']);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertSame('draft', $order->slice_params['quality']);
        $this->assertSame(2, $order->timings['run']);
        $this->assertNotNull($order->gcode_path);
    }

    public function test_a_job_without_a_run_number_is_overtaken_by_a_later_run_too(): void
    {
        $order = $this->bigOrder();
        Queue::fake();
        $order->update(['status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'checking']);
        // the safety net's job (ProcessModelFile, an admin's override) carries no number: it works for the run current at its start
        $job = new PrepareFarmOrder($order->id);
        $this->realSlicerProcess(120);
        $changed = false;
        DB::listen(function ($query) use (&$changed, $order) {
            if (! $changed && str_contains($query->sql, 'slice_run') && str_starts_with(ltrim(strtolower($query->sql)), 'select') && is_file($this->dir.'/slicer.pid')) {
                $changed = true;
                FarmOrder::whereKey($order->id)->increment('slice_run');
            }
        });
        $start = microtime(true);
        app()->call([$job, 'handle']);
        $this->assertTrue($changed);
        $this->assertLessThan(60, microtime(true) - $start);
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_UPLOADED, $order->status);
        $this->assertNull($order->error);
        $this->assertNull($order->gcode_path);
    }

    public function test_an_admins_override_while_the_order_is_sliced_starts_a_new_run(): void
    {
        $order = $this->bigOrder();
        Queue::fake();
        $order->update(['status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'slicing', 'slice_run' => 1]);

        app(OrderService::class)->overrideByAdmin($order->fresh(), ['wall_loops' => '3']);

        $order->refresh();
        $this->assertSame(2, $order->slice_run, 'the running slice is overtaken');
        $this->assertSame(FarmOrder::STATUS_UPLOADED, $order->status);
        Queue::assertPushed(PrepareFarmOrder::class, fn (PrepareFarmOrder $j) => $j->orderId === $order->id && $j->run === 2);
    }

    public function test_a_thinning_that_was_stopped_leaves_nothing_behind_and_remembers_nothing(): void
    {
        $mesh = $this->dir.'/big.stl';
        MeshFixtures::cubeStl($mesh, 20.0);
        $cache = $this->dir.'/mesh-cache';
        $runner = new class implements ScriptRunner
        {
            public function runScript(string $script, array $args, int $timeout = 90): array
            {
                file_put_contents($args[1], 'half a file');   // what the killed script had written so far
                throw new SupersededException('stopped');
            }
        };
        $decimator = new MeshDecimator($runner, ['decimate_above' => 5, 'decimate_target' => 6, 'max_deviation_mm' => 0.05, 'decimate_timeout' => 30, 'decimate_cache' => $cache]);
        $out = $this->dir.'/thin.stl';
        try {
            $decimator->decimate($mesh, $out, 0.2);
            $this->fail('a stopped thinning must end the run, not slice the original');
        } catch (SupersededException) {
            // as it should
        }
        $this->assertFileDoesNotExist($out);
        $this->assertSame([], glob($cache.'/*') ?: [], 'nothing remembered: the next run thins the file itself');
    }
}
