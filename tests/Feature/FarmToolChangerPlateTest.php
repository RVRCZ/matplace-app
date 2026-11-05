<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\OrderService;
use App\Engines\Mesh\StlFile;
use App\Jobs\PrepareFarmOrder;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A tool changer (Prusa CORE One with the INDX, docs/AM.md §12j): the slice names the tools. Filament n of the slicer is
 * spool position n (slot 0 = tool 1 = T0), a part's object takes extruder = position + 1 whatever the order of the parts,
 * the positions between the tools used are filled with placeholders nothing prints from, there is no prime tower (the
 * station purges), a one-colour print goes from the tool its spool sits in, and a change of colour at a height is Orca's
 * own tool change in the project. The file goes to the printer as sliced.
 */
class FarmToolChangerPlateTest extends TestCase
{
    use RefreshDatabase;

    private const HEXES = ['#F4F4F0', '#101010', '#C01818', '#2E86DE', '#27AE60', '#F1C40F', '#8E44AD', '#E84393'];

    private User $user;

    private FarmPrinter $indx;

    private FarmAgent $agent;

    private array $auth;

    /** @var array<int, FarmPrinterSlot> position → spool */
    private array $tools = [];

    /** @var list<string> */
    private array $temp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['farm.settings.admin_email' => 'farm@example.com']);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        [$this->agent, $token] = FarmAgent::issue('test agent');
        $this->auth = ['Authorization' => 'Bearer '.$token];
        // the Core One with the INDX, run by the agent, the only machine of the farm: white in tool 1, black in tool 2, red in tool 5
        FarmPrinter::query()->update(['enabled' => false]);
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $white = FarmColor::findOrFail($s1->slots()->whereNotNull('farm_color_id')->firstOrFail()->farm_color_id);
        $white->update(['hex' => self::HEXES[0], 'enabled' => true]);
        $this->indx = FarmPrinter::create([
            'name' => 'Core One INDX', 'code' => 'C1', 'model' => 'Prusa CORE One+ INDX', 'key' => 'core-one-01', 'mode' => FarmPrinter::MODE_AGENT,
            'farm_agent_id' => $this->agent->id, 'enabled' => true, 'bed_x' => 248, 'bed_y' => 205, 'bed_z' => 270, 'nozzle_mm' => 0.4,
            'machine_profile' => 'machine_coreone_indx8t.json', 'process_profiles' => ['standard' => 'process_standard_coreone_indx8t.json'],
            'time_factor' => 1, 'weight_factor' => 1, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE, 'bed_clear' => true,
        ]);
        $this->load([0 => $white, 1 => $this->colour(self::HEXES[1], 'černá', $white), 4 => $this->colour(self::HEXES[2], 'červená', $white)]);
    }

    protected function tearDown(): void
    {
        putenv('MP_FAKE_ORCA_DIR');
        unset($_SERVER['MP_FAKE_ORCA_DIR']);
        foreach ($this->temp as $f) {
            is_dir($f) ? File::deleteDirectory($f) : @unlink($f);
        }
        parent::tearDown();
    }

    /** @param  array<int, FarmColor|null>  $colours  position → colour; the eight positions exist, the rest empty */
    private function load(array $colours): void
    {
        $this->indx->slots()->delete();
        $this->tools = [];
        for ($i = 0; $i < 8; $i++) {
            $c = $colours[$i] ?? null;
            $this->tools[$i] = $this->indx->slots()->create(['slot' => $i, 'farm_color_id' => $c?->id, 'remaining_g' => $c ? 900 : 0, 'enabled' => $c !== null]);
        }
        $this->indx->unsetRelation('slots');
    }

    private function colour(string $hex, string $name, FarmColor $like): FarmColor
    {
        return FarmColor::create(['farm_material_id' => $like->farm_material_id, 'name' => $name, 'hex' => $hex, 'enabled' => true]);
    }

    /** A tool's design of `$n` parts in the colours of HEXES (cubes one in another, as FarmOnePlateTest). */
    private function designOf(int $n): ModelFile
    {
        $path = sys_get_temp_dir().'/mp_tc_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'parts.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $dir = dirname(Storage::disk(ModelFile::DISK)->path($file->storage_path)).'/parts';
        mkdir($dir, 0777, true);
        $parts = [];
        foreach (range(1, $n) as $i) {
            MeshFixtures::cubeStl($dir.'/color_'.$i.'.stl', 20.0 - $i);
            $parts['color_'.$i] = ['hex' => self::HEXES[$i - 1]];
        }
        $file->forceFill(['origin' => 'tool', 'origin_ref' => 'gcode', 'tool_params' => ['parts' => array_keys($parts), 'multi_material' => true, 'part_colors' => $parts]])->save();

        return $file;
    }

    private function upload(float $size = 20.0): string
    {
        $path = sys_get_temp_dir().'/mp_tc_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $size);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function order(string $uuid, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid] + $extra)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function stateOf(FarmOrder $order): array
    {
        return $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
    }

    private function pay(FarmOrder $order, array $over = [])
    {
        $state = $this->stateOf($order);
        $main = collect($state['colors'])->firstWhere('slot', $state['slot']) ?? collect($state['colors'])->firstWhere('sliced', true) ?? $state['colors'][0];

        return $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", $over + ['slot' => $main['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $main['total']]);
    }

    private function sync(string $state = 'idle', ?array $job = null)
    {
        return $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => 'core-one-01', 'state' => $state, 'telemetry' => ['extruder' => 215.2], 'job' => $job]]], $this->auth);
    }

    /** @return array{0: string, 1: string} the 3MF's model_settings.config and its custom_gcode_per_layer.xml ('' when none) */
    private function project(string $path): array
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'the project '.$path);
        $out = [(string) $zip->getFromName('Metadata/model_settings.config'), (string) $zip->getFromName('Metadata/custom_gcode_per_layer.xml')];
        $zip->close();

        return $out;
    }

    public function test_three_parts_print_from_the_tools_their_spools_sit_in_and_the_tools_between_them_are_filled_in(): void
    {
        $file = $this->designOf(3);
        $order = $this->order($file->uuid, ['color' => $this->tools[0]->farm_color_id, 'by_parts' => 1, 'part_color' => ['color_2' => $this->tools[1]->farm_color_id, 'color_3' => $this->tools[4]->farm_color_id]]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertSame($this->indx->id, $order->farm_printer_id);
        $this->assertTrue($order->onePlate(), 'three spools of a changer: one plate');
        // the tools: white in 1, black in 2, red in 5 – T0, T1, T4, not T0, T1, T2
        $this->assertSame([1, 2, 5], array_column($order->partPlates(), 'extruder'));
        $this->assertSame([$this->tools[0]->id, $this->tools[1]->id, $this->tools[4]->id], array_column($order->partPlates(), 'slot_id'));
        $this->assertSame([0 => 0, 1 => 1, 4 => 4], $order->filamentSlots(), 'filament n = position n');
        $filaments = $order->slice_params['filaments'];
        $this->assertCount(5, $filaments, 'a filament per position up to the last tool used');
        $this->assertSame([$this->tools[0]->id, $this->tools[1]->id, null, null, $this->tools[4]->id], array_column($filaments, 'slot_id'));
        $this->assertSame([false, false, true, true, false], array_map(fn ($f) => ! empty($f['placeholder']), $filaments));
        $this->assertSame([self::HEXES[0], self::HEXES[1], self::HEXES[0], self::HEXES[0], self::HEXES[2]], array_column($filaments, 'colour'), 'the tools between take the first colour');
        $this->assertSame($filaments[0]['profile'], $filaments[2]['profile']);
        $this->assertTrue($order->slice_params['tool_changer']);
        // no prime tower, no room kept for it
        $this->assertSame('0', $order->slice_params['overrides']['process']['enable_prime_tower']);
        $this->assertArrayNotHasKey('wipe_tower_x', $order->slice_params['overrides']['process']);
        $this->assertNull($order->slice_params['tower']);
        // the project names the tools
        [$config] = $this->project(Storage::disk('farm')->path($order->dir().'/plate.3mf'));
        $this->assertStringContainsString('value="color_1"/><metadata key="extruder" value="1"/>', $config);
        $this->assertStringContainsString('value="color_2"/><metadata key="extruder" value="2"/>', $config);
        $this->assertStringContainsString('value="color_3"/><metadata key="extruder" value="5"/>', $config);
        // the grams by position (the header lists every filament loaded, the placeholders 0.00), the changes among the three
        $grams = $order->slice_result['filament_grams'];
        $this->assertCount(5, $grams);
        $this->assertEquals([0.0, 0.0], [$grams[2], $grams[3]]);
        $this->assertGreaterThan(0, $grams[4]);
        $this->assertEqualsWithDelta([$grams[0], $grams[1], $grams[4]], array_column($order->partPlates(), 'grams'), 0.051);
        $this->assertSame(2 * $order->slice_result['layers'], $order->slice_result['changes'], 'two changes a layer: three tools, not five');
        $state = $this->stateOf($order);
        $this->assertSame(8, $state['max_colors']);
        $this->assertTrue($state['one_plate']);

        // paid and started: the start names the positions, the agent gets the file as sliced (no slot rewrite for a tool changer)
        $this->credit(5000);
        $this->pay($order, ['part_slots' => ['color_2' => $this->tools[1]->id, 'color_3' => $this->tools[4]->id]])->assertOk()->assertJsonPath('status', 'queued');
        $cmd = $this->sync()->assertOk()->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->assertSame(0, $cmd['payload']['slot']);
        $this->assertSame([0 => 0, 1 => 1, 4 => 4], $cmd['payload']['filaments']);
        $agentCopy = (string) file_get_contents($this->getJson($cmd['payload']['gcode_url'], $this->auth)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertSame(hash('sha256', $agentCopy), $order->fresh()->gcode_sha256, 'the file as sliced');
        $this->assertStringNotContainsString('matplace slot', $agentCopy);
        $this->assertStringContainsString("\nT4\n", $agentCopy);
        $this->assertStringNotContainsString("\nT2\n", $agentCopy, 'nothing from the tools between');
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk()->assertSee('T4→slot 5');
    }

    public function test_eight_parts_take_the_eight_tools_and_a_ninth_colour_is_refused_with_eight(): void
    {
        $white = FarmColor::findOrFail($this->tools[0]->farm_color_id);
        $colours = [0 => $white];
        for ($i = 1; $i < 8; $i++) {
            $colours[$i] = $this->colour(self::HEXES[$i], 'barva '.$i, $white);
        }
        $this->load($colours);
        $file = $this->designOf(8);
        $order = $this->order($file->uuid);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertTrue($order->onePlate(), 'eight filaments in one plate');
        $this->assertSame(range(1, 8), array_column($order->partPlates(), 'extruder'), 'T0 to T7');
        $this->assertSame(array_map(fn (FarmPrinterSlot $s) => $s->id, array_values($this->tools)), array_column($order->partPlates(), 'slot_id'));
        $this->assertSame(array_combine(range(0, 7), range(0, 7)), $order->filamentSlots());
        $this->assertCount(8, $order->slice_params['filaments']);
        $this->assertSame([], array_filter($order->slice_params['filaments'], fn ($f) => ! empty($f['placeholder'])), 'no gap, no placeholder');
        $this->assertCount(8, $order->slice_result['filament_grams']);
        $this->assertSame(7 * $order->slice_result['layers'], $order->slice_result['changes']);
        $this->assertNull($order->slice_params['tower']);
        $state = $this->stateOf($order);
        $this->assertSame(8, $state['max_colors']);
        $this->assertNull($state['colors_over']);
        $card = collect(collect($state['colors'])->firstWhere('color_id', $white->id)['machines'])->firstWhere('key', 'core-one-01');
        $this->assertSame([8, true], [$card['limit'], $card['changer']]);
        $this->assertSame(array_map(fn ($i) => 'Nástroj '.$i, range(1, 8)), array_column($card['positions'], 'label'));
        // a ninth colour: as many as the machine has tools
        $this->assertSame(8, $this->indx->colorLimit());
        try {
            $parts = array_merge(array_keys($file->tool_params['part_colors']), ['color_9']);
            $ids = array_merge(array_map(fn (FarmColor $c) => $c->id, array_values($colours)), [$this->colour('#000000', 'devátá', $white)->id]);
            app(OrderService::class)->spoolsForParts($this->tools[0], $parts, array_combine($parts, $ids));
            $this->fail('nine colours on eight tools');
        } catch (FarmRefusal $e) {
            $this->assertSame('too_many_colors', $e->reason);
            $this->assertSame(8, $e->data['n']);
        }
    }

    public function test_a_one_colour_print_goes_from_the_tool_its_spool_sits_in_and_a_change_of_colour_is_a_tool_change_in_the_project(): void
    {
        // black sits in tool 2: the plate is a project on extruder 2 with a filament for tool 1 that nothing prints from
        $order = $this->order($this->upload(), ['color' => $this->tools[1]->farm_color_id]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertSame(['position' => 1, 'changes' => []], $order->slice_params['tool']);
        $this->assertSame([null, $this->tools[1]->id], array_column($order->slice_params['filaments'], 'slot_id'));
        $this->assertTrue(! empty($order->slice_params['filaments'][0]['placeholder']));
        $this->assertSame('0', $order->slice_params['overrides']['process']['enable_prime_tower']);
        $disk = Storage::disk('farm');
        [$config, $layers] = $this->project($disk->path($order->dir().'/plate.3mf'));
        $this->assertStringContainsString('<metadata key="extruder" value="2"/>', $config);
        $this->assertSame('', $layers, 'no change of colour');
        $this->assertSame(['position' => 1, 'changes' => []], PrepareFarmOrder::toolPlan($order));
        // paid for the same spool: no new slice
        $this->credit(20000);
        $this->pay($order)->assertOk()->assertJsonPath('status', 'queued');
        $this->assertSame(0, $order->events()->where('note', 'like', 'reslice%')->count());
        $cmd = $this->sync()->assertOk()->json('commands.0');
        $this->assertSame(1, $cmd['payload']['slot']);
        $this->assertArrayNotHasKey('filaments', $cmd['payload']);
        $agentCopy = (string) file_get_contents($this->getJson($cmd['payload']['gcode_url'], $this->auth)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertSame(hash('sha256', $agentCopy), $order->fresh()->gcode_sha256, 'the file as sliced');
        $this->sync('idle', ['id' => $cmd['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 600, 'filament_used' => 1000]);
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);

        // a plate with a text in a second colour: white body from tool 1, red letters from tool 5 above 3 mm – Orca's own tool change
        $uuid = $this->actingAs($this->user)->postJson('/api/tools/param', ['kind' => 'sign', 'params' => ['style' => 'emboss', 'thickness' => 3, 'relief' => 1.2, 'line1' => 'Emma']])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $file->forceFill(['tool_params' => ['color_changes' => [['z' => 3, 'hex' => '#c81c1c', 'code' => null]], 'part_colors' => ['body' => ['code' => 'white', 'hex' => '#f2f2ee']]] + array_diff_key($file->tool_params, ['color_change_mm' => 1])])->save();
        $sign = $this->order($uuid, ['color' => $this->tools[0]->farm_color_id, 'change_color' => [$this->tools[4]->farm_color_id]]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $sign->status, (string) $sign->error);
        $this->assertSame([['slot' => 4, 'z' => 3.0]], $sign->colorChanges());
        $this->assertEquals(['position' => 0, 'changes' => [['z' => 3.0, 'position' => 4]]], $sign->slice_params['tool']);
        $this->assertSame([$this->tools[0]->id, null, null, null, $this->tools[4]->id], array_column($sign->slice_params['filaments'], 'slot_id'));
        [$config, $layers] = $this->project($disk->path($sign->dir().'/plate.3mf'));
        $this->assertStringContainsString('<metadata key="extruder" value="1"/>', $config);
        // from the first layer whose middle lies above 3 mm (0.2 mm layers: the one ending at 3.2), as GcodeSlot does it for an ACE
        $this->assertStringContainsString('<layer top_z="3.1" type="2" extruder="5" color="'.self::HEXES[2].'" extra="" gcode="tool_change"/>', $layers);
        $this->assertStringContainsString('<mode value="MultiAsSingle"/>', $layers);
        // the body placed in the middle of this machine's bed
        $body = $disk->path($sign->print_stl_path);
        [$lo, $hi] = StlFile::bounds($body);
        $this->assertLessThan(248, $hi[0] - $lo[0]);
        $this->assertSame(1, $sign->plates);
        // paid with black (tool 2) for the letters instead: sliced again for the tools, the new project changes to extruder 2
        // (the queue runs at once in the tests: the answer already shows the order queued after its new slice)
        $this->pay($sign, ['change_slots' => [$this->tools[1]->id], 'second_slot' => $this->tools[1]->id])->assertOk();
        $sign->refresh();
        $this->assertSame(1, $sign->events()->where('note', 'reslice for the tools')->count());
        $this->assertSame(FarmOrder::STATUS_QUEUED, $sign->status, (string) $sign->error);
        $this->assertEquals(['position' => 0, 'changes' => [['z' => 3.0, 'position' => 1]]], $sign->slice_params['tool']);
        $this->assertSame([$this->tools[0]->id, $this->tools[1]->id], array_column($sign->slice_params['filaments'], 'slot_id'));
        [, $layers] = $this->project($disk->path($sign->dir().'/plate.3mf'));
        $this->assertStringContainsString('top_z="3.1" type="2" extruder="2"', $layers);
        // paid again the same way elsewhere would not slice again: the plan matches the file
        $this->assertTrue(PrepareFarmOrder::sameToolPlan(PrepareFarmOrder::toolPlan($sign->fresh(['slot'])), $sign->slice_params['tool']));
        $this->assertFalse(PrepareFarmOrder::sameToolPlan(PrepareFarmOrder::toolPlan($sign->fresh(['slot'])), null), 'an order sliced before the tools were named slices again');
    }

    /**
     * The slice itself with the slicer class the farm runs, against a stand-in binary that takes the real command line
     * (5 Nov 2026: a slice that passed with the fake engine failed on production): a filament file per position with its
     * colour, the project naming the tools, no prime tower in the process, the header read back by position.
     */
    public function test_the_slice_of_a_tool_changers_plate_runs_the_binary_with_a_filament_per_tool_and_no_tower(): void
    {
        $win = PHP_OS_FAMILY === 'Windows';
        $script = base_path('tests/Support/fake_orca.php');
        $fake = sys_get_temp_dir().'/mp_fake_orca_'.uniqid().($win ? '.cmd' : '.sh');
        file_put_contents($fake, $win ? "@echo off\r\n\"".PHP_BINARY."\" \"{$script}\" %*\r\n" : "#!/bin/sh\nexec \"".PHP_BINARY."\" \"{$script}\" \"\$@\"\n");
        chmod($fake, 0755);
        $work = sys_get_temp_dir().'/mp_fake_orca_work_'.uniqid();
        $copies = sys_get_temp_dir().'/mp_fake_orca_copies_'.uniqid();
        array_push($this->temp, $fake, $work, $copies);
        // the slicer's process inherits only what $_SERVER knows (Symfony Process): both
        putenv('MP_FAKE_ORCA_DIR='.$copies);
        $_SERVER['MP_FAKE_ORCA_DIR'] = $copies;
        config(['engines.slicer' => 'orca', 'engines.orca.bin' => $fake, 'engines.orca.bin_toolchanger' => null, 'engines.orca.xvfb' => false, 'engines.orca.work_dir' => $work, 'engines.orca.timeout' => 120, 'engines.orca.cache' => false]);

        $file = $this->designOf(3);
        $order = $this->order($file->uuid, ['color' => $this->tools[0]->farm_color_id, 'by_parts' => 1, 'part_color' => ['color_2' => $this->tools[1]->farm_color_id, 'color_3' => $this->tools[4]->farm_color_id]]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertSame('orca', $order->slice_params['engine']);
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame([1, 2, 5], array_column($order->partPlates(), 'extruder'));
        // what the binary was given
        $runs = glob($copies.'/run_*');
        $this->assertCount(1, $runs, 'one slice, no second attempt');
        $args = json_decode((string) file_get_contents($runs[0].'/args.json'), true);
        $this->assertContains('--arrange', $args);
        $this->assertSame('0', $args[array_search('--arrange', $args, true) + 1]);
        $this->assertCount(5, explode(';', $args[array_search('--load-filaments', $args, true) + 1]), 'a filament file per position up to tool 5');
        $this->assertStringEndsWith('plate.3mf', end($args));
        $process = json_decode((string) file_get_contents($runs[0].'/process.json'), true);
        $this->assertSame('0', $process['enable_prime_tower']);
        $this->assertSame(['Prusa CORE One INDX 8T HF 0.4 nozzle'], $process['compatible_printers']);
        $colours = [];
        for ($i = 1; $i <= 5; $i++) {
            $f = json_decode((string) file_get_contents($runs[0].'/filament_'.$i.'.json'), true);
            $colours[] = $f['filament_colour'][0];
            $this->assertSame('Generic PLA @Prusa CORE One INDX', $f['name'], 'the INDX flavour of the farm profile');
        }
        $this->assertSame(self::HEXES[0], $colours[0]);
        $this->assertSame(self::HEXES[1], $colours[1]);
        $this->assertSame(self::HEXES[2], $colours[4]);
        $this->assertCount(5, array_unique($colours), 'the placeholders nudged a shade, never two filaments alike');
        [$config] = $this->project($runs[0].'/plate.3mf');
        $this->assertSame(['1', '2', '5'], array_map(fn ($m) => $m, preg_match_all('/key="extruder" value="(\d+)"/', $config, $m) ? $m[1] : []));
        // what came back from the header: a figure per filament, 0.00 for the tools between, the grams of the parts by position
        $this->assertEquals([1.0, 1.0, 0.0, 0.0, 1.0], $order->slice_result['filament_grams']);
        $this->assertEquals([1.0, 1.0, 1.0], array_column($order->partPlates(), 'grams'));
        $this->assertSame(60, $order->slice_result['changes']);
        $this->assertSame(3.0, (float) $order->est_grams);
        $this->assertSame(18, $order->est_minutes);
        $gcode = (string) file_get_contents(Storage::disk('farm')->path($order->gcode_path));
        $this->assertStringContainsString("  T4 S1 L0 D0\n", $gcode);
        $this->assertStringNotContainsString('T2 S1', $gcode);
        $this->assertStringNotContainsString(';TYPE:Prime tower', $gcode);

        // a one-colour print from tool 2 through the same binary: two filament files, the project on extruder 2
        $one = $this->order($this->upload(), ['color' => $this->tools[1]->farm_color_id]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $one->status, (string) $one->error);
        $runs = glob($copies.'/run_*');
        $this->assertCount(2, $runs);
        $args = json_decode((string) file_get_contents($runs[1].'/args.json'), true);
        $this->assertCount(2, explode(';', $args[array_search('--load-filaments', $args, true) + 1]));
        [$config] = $this->project($runs[1].'/plate.3mf');
        $this->assertStringContainsString('<metadata key="extruder" value="2"/>', $config);
        $this->assertSame(['position' => 1, 'changes' => []], $one->slice_params['tool']);
        $this->assertSame(1.0, (float) $one->est_grams);
    }
}
