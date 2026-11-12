<?php

namespace Tests\Feature;

use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Exceptions\SlicerException;
use App\Engines\Slicer\CachedSlicer;
use App\Engines\Slicer\OrcaSlicer;
use App\Models\FarmOrder;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * One line of .env says which OrcaSlicer slices everything (docs/AL.md §22): ORCA_SLICER=2.5 moves the calculator and
 * every farm printer to the 2.5 binary, 2.4 or no line keeps ORCA_BIN; the tool changer keeps its own binary either
 * way, an admin's orders can try another version first, and the slice cache never mixes the two.
 */
final class OrcaSlicerSwitchTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $f) {
            is_dir($f) ? File::deleteDirectory($f) : @unlink($f);
        }
        parent::tearDown();
    }

    private function orca(array $config): OrcaSlicer
    {
        return new OrcaSlicer($config + ['bin' => '/opt/orca24/AppRun', 'bin_version' => '2.4', 'bin_toolchanger' => null, 'slicer' => '', 'bins' => ['2.5' => '/opt/orca25/AppRun']] + config('engines.orca'));
    }

    public function test_the_shipped_config_keeps_the_slicer_as_it_was_until_the_line_is_set(): void
    {
        // phpunit sets none of the ORCA_* lines: the default is "no switch", bin is 2.4
        $this->assertSame('', config('engines.orca.slicer'));
        $this->assertSame('2.4', config('engines.orca.bin_version'));
        $this->assertArrayHasKey('2.5', config('engines.orca.bins'));
        $this->assertNull(config('engines.orca.slicer_trial'));
    }

    public function test_one_setting_moves_every_machine_and_the_calculator_to_the_other_slicer_and_back(): void
    {
        $machines = ['machine.json', 'machine_kobrax.json', 'machine_kobra3max.json', 'machine_kobras1_n02.json', null];
        foreach ($machines as $m) {
            $this->assertSame('/opt/orca24/AppRun', $this->orca([])->binFor($m), 'no line: as before');
            $this->assertSame('/opt/orca24/AppRun', $this->orca(['slicer' => '2.4'])->binFor($m), 'back to 2.4');
            $this->assertSame('/opt/orca25/AppRun', $this->orca(['slicer' => '2.5'])->binFor($m), 'all on 2.5');
            $this->assertSame('2.4', $this->orca([])->slicerFor($m));
            $this->assertSame('2.4', $this->orca(['slicer' => '2.4'])->slicerFor($m));
            $this->assertSame('2.5', $this->orca(['slicer' => '2.5'])->slicerFor($m));
        }
    }

    public function test_the_tool_changer_keeps_its_own_binary_whatever_the_switch_says(): void
    {
        foreach (['', '2.4', '2.5'] as $slicer) {
            $orca = $this->orca(['slicer' => $slicer, 'bin_toolchanger' => '/opt/orca25-indx/AppRun']);
            $this->assertSame('/opt/orca25-indx/AppRun', $orca->binFor('machine_coreone_indx8t.json'));
            $this->assertSame('toolchanger', $orca->slicerFor('machine_coreone_indx8t.json'));
        }
        // without a binary of its own it follows the switch like every other machine
        $this->assertSame('/opt/orca25/AppRun', $this->orca(['slicer' => '2.5'])->binFor('machine_coreone_indx8t.json'));
    }

    public function test_the_production_env_of_today_needs_only_the_one_line(): void
    {
        // the server has ORCA_BIN (2.4) and ORCA_BIN_TOOLCHANGER (2.5): config/engines.php hands the tool changer's binary
        // to `bins` as the 2.5 when ORCA_BIN_25 is not set – rebuilt here the way the config file does it
        $toolchanger = '/opt/orca/squashfs-root-250a/AppRun';
        $config = ['bin' => '/opt/orca/squashfs-root/AppRun', 'bin_toolchanger' => $toolchanger, 'bins' => ['2.5' => null ?: ($toolchanger ?: null)]];
        $this->assertSame('/opt/orca/squashfs-root/AppRun', $this->orca($config)->binFor('machine.json'));
        $this->assertSame($toolchanger, $this->orca($config + ['slicer' => '2.5'])->binFor('machine.json'));
        $this->assertSame($toolchanger, $this->orca($config + ['slicer' => '2.5'])->binFor('machine_kobrax.json'));
        $this->assertSame($toolchanger, $this->orca($config + ['slicer' => '2.5'])->binFor(null));
        $this->assertSame('/opt/orca/squashfs-root/AppRun', $this->orca($config + ['slicer' => '2.4'])->binFor('machine_kobrax.json'));
    }

    public function test_a_version_without_a_binary_falls_back_on_the_usual_one_and_says_so(): void
    {
        Log::spy();
        $orca = $this->orca(['slicer' => '2.6']);
        $this->assertSame('/opt/orca24/AppRun', $orca->binFor('machine.json'));
        $this->assertSame('2.4', $orca->slicerFor('machine.json'), 'the record names the slicer that really ran');
        Log::shouldHaveReceived('warning')->once();

        $none = $this->orca(['slicer' => '2.5', 'bins' => ['2.5' => null]]);
        $this->assertSame('/opt/orca24/AppRun', $none->binFor('machine.json'));
        $this->assertSame('2.4', $none->slicerFor('machine.json'));
    }

    public function test_a_slice_can_ask_for_a_version_of_its_own(): void
    {
        $orca = $this->orca(['slicer' => '2.4']);
        $this->assertSame('/opt/orca25/AppRun', $orca->binFor('machine.json', '2.5'), 'a trial order');
        $this->assertSame('2.5', $orca->slicerFor('machine.json', '2.5'));
        $this->assertSame('/opt/orca24/AppRun', $this->orca(['slicer' => '2.5'])->binFor('machine.json', '2.4'), 'and the other way round');

        $params = (new SliceParams('PLA'))->withSlicer('2.5');
        $this->assertSame('2.5', $params->slicer);
        // every copy of the parameters keeps it
        $this->assertSame('2.5', $params->withFarmProfile(['machine' => 'machine.json'], [])->withoutThinning()->withProcess(['a' => 'b'])->withProject(null)->withTimeout(30)->withFilaments([], 'p.3mf')->withSupportsLeftOut()->slicer);
        $this->assertArrayNotHasKey('slicer', $params->toArray(), 'the cache keys on the binary, not on the name');
    }

    private function fakeBinary(string $content): string
    {
        $f = tempnam(sys_get_temp_dir(), 'orca');
        file_put_contents($f, $content);
        $this->temp[] = $f;

        return $f;
    }

    public function test_the_slice_cache_never_hands_out_the_other_slicers_gcode(): void
    {
        $bin24 = $this->fakeBinary('orca 2.4');
        $bin25 = $this->fakeBinary('orca 2.5, a longer file');
        $mesh = $this->fakeBinary("solid a\nendsolid a\n");
        $cache = fn (string $slicer): CachedSlicer => new CachedSlicer($this->orca(['bin' => $bin24, 'bins' => ['2.5' => $bin25], 'slicer' => $slicer]), sys_get_temp_dir(), sys_get_temp_dir(), [], $bin24);
        $s1 = new SliceParams('PLA', profiles: ['machine' => 'machine.json']);

        $this->assertNotSame($cache('2.4')->key($mesh, $s1), $cache('2.5')->key($mesh, $s1), 'the switch');
        $this->assertSame($cache('')->key($mesh, $s1), $cache('2.4')->key($mesh, $s1), 'no line and 2.4 are the same slicer');
        $this->assertSame($cache('2.5')->key($mesh, $s1), $cache('2.4')->key($mesh, $s1->withSlicer('2.5')), 'a trial order shares the 2.5 results');
        $this->assertNotSame($cache('2.4')->key($mesh, $s1), $cache('2.4')->key($mesh, $s1->withSlicer('2.5')));
    }

    /** The command really runs the binary of the version chosen, and the result says which. */
    public function test_the_slice_runs_the_binary_of_the_version_chosen(): void
    {
        $win = PHP_OS_FAMILY === 'Windows';
        $make = function (string $says) use ($win): string {
            $fake = tempnam(sys_get_temp_dir(), 'orca').($win ? '.cmd' : '.sh');
            file_put_contents($fake, $win ? "@echo off\r\necho {$says} %*\r\nexit /b 0\r\n" : "#!/bin/sh\necho {$says} \"$@\"\nexit 0\n");
            chmod($fake, 0755);
            $this->temp[] = $fake;

            return $fake;
        };
        $work = sys_get_temp_dir().'/mp_switch_work_'.uniqid();
        $this->temp[] = $work;
        $config = ['bin' => $make('slicer-two-four'), 'bins' => ['2.5' => $make('slicer-two-five')], 'xvfb' => false, 'work_dir' => $work, 'timeout' => 60];
        foreach (['2.4' => 'slicer-two-four', '2.5' => 'slicer-two-five'] as $version => $says) {
            try {
                $this->orca($config + ['slicer' => (string) $version])->slice(base_path('tests/fixtures/cube10.stl'), new SliceParams('PLA', supports: false));
                $this->fail('a slicer that writes no G-code must be reported');
            } catch (SlicerException $e) {
                $this->assertStringContainsString($says, $e->getMessage(), 'version '.$version);
            }
        }
    }

    /** A slicer that only notes what it was asked. */
    private function recordingSlicer(): object
    {
        $inner = app(Slicer::class);
        $spy = new class($inner) implements Slicer
        {
            /** @var list<SliceParams> */
            public array $asked = [];

            public function __construct(private Slicer $inner) {}

            public function name(): string
            {
                return $this->inner->name();
            }

            public function supportedFormats(): array
            {
                return $this->inner->supportedFormats();
            }

            public function measure(string $meshPath): Dimensions
            {
                return $this->inner->measure($meshPath);
            }

            public function slice(string $meshPath, SliceParams $params): SliceResult
            {
                $this->asked[] = $params;

                return $this->inner->slice($meshPath, $params);
            }
        };
        $this->app->instance(Slicer::class, $spy);

        return $spy;
    }

    public function test_an_admins_farm_order_takes_the_trial_slicer_and_a_customers_does_not(): void
    {
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['engines.orca.slicer_trial' => '2.5']);
        $spy = $this->recordingSlicer();
        $order = function (User $user): FarmOrder {
            $path = sys_get_temp_dir().'/mp_switch_'.uniqid().'.stl';
            MeshFixtures::cubeStl($path, 20.0);
            $this->temp[] = $path;
            $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
            $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();

            return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        };

        $customer = User::factory()->create();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order($customer)->status);
        $this->assertNull(end($spy->asked)->slicer, 'a customer: the configured slicer');

        $admin = User::factory()->create();
        $admin->roles()->create(['role' => User::ROLE_ADMIN]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order($admin->fresh())->status);
        $this->assertSame('2.5', end($spy->asked)->slicer, 'an admin: the trial slicer');

        config(['engines.orca.slicer_trial' => null]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order($admin->fresh())->status);
        $this->assertNull(end($spy->asked)->slicer, 'no trial set: the configured slicer for everybody');
    }
}
