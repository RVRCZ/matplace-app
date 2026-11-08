<?php

namespace Tests\Feature;

use App\Domain\Farm\Wallet;
use App\Engines\Mesh\StlFile;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Several copies of a design whose colours share a layer on one plate (TODO #72, docs/AL.md §10.13): the parts of every
 * piece are objects of the project placed once per copy (the same offsets for all of them, so the motif stays set in its
 * body), as many pieces as a plate takes beside the tower's band, more on full plates one after another with the rest on
 * the last one; the payment takes the copies (it used to refuse them with one_plate_needed and give the credit back).
 */
class FarmOnePlateCopiesTest extends TestCase
{
    use RefreshDatabase;

    private const HEXES = ['#F4F4F0', '#101010', '#C01818'];

    private User $user;

    private FarmAgent $agent;

    private array $auth;

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
    }

    /** The S1 with the ACE run by the agent: white in its loaded slot, red in a free one. @return array{0: FarmPrinter, 1: FarmPrinterSlot, 2: FarmPrinterSlot} */
    private function s1(): array
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $this->agent->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE, 'bed_clear' => true]);
        $white = $s1->slots()->whereNotNull('farm_color_id')->firstOrFail();
        $redColor = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'like', 'PLA%'))->where('id', '!=', $white->farm_color_id)->firstOrFail();
        $redColor->update(['hex' => self::HEXES[2], 'enabled' => true]);
        $red = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->firstOrFail();
        $red->update(['farm_color_id' => $redColor->id, 'remaining_g' => 800, 'enabled' => true]);

        return [$s1, $white, $red];
    }

    /**
     * The Core One with the INDX as the only machine of the farm: white in tool 1, black in tool 2 (FarmToolChangerPlateTest).
     *
     * @return array{0: FarmPrinter, 1: array<int, FarmPrinterSlot>}
     */
    private function indx(): array
    {
        FarmPrinter::query()->update(['enabled' => false]);
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $white = FarmColor::findOrFail($s1->slots()->whereNotNull('farm_color_id')->firstOrFail()->farm_color_id);
        $white->update(['hex' => self::HEXES[0], 'enabled' => true]);
        $black = FarmColor::create(['farm_material_id' => $white->farm_material_id, 'name' => 'černá', 'hex' => self::HEXES[1], 'enabled' => true]);
        $indx = FarmPrinter::create([
            'name' => 'Core One INDX', 'code' => 'C1', 'model' => 'Prusa CORE One+ INDX', 'key' => 'core-one-01', 'mode' => FarmPrinter::MODE_AGENT,
            'farm_agent_id' => $this->agent->id, 'enabled' => true, 'bed_x' => 248, 'bed_y' => 205, 'bed_z' => 270, 'nozzle_mm' => 0.4,
            'machine_profile' => 'machine_coreone_indx8t.json', 'process_profiles' => ['standard' => 'process_standard_coreone_indx8t.json'],
            'time_factor' => 1, 'weight_factor' => 1, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE, 'bed_clear' => true,
        ]);
        $tools = [];
        foreach ([0 => $white, 1 => $black] + array_fill(2, 6, null) as $i => $c) {
            $tools[$i] = $indx->slots()->create(['slot' => $i, 'farm_color_id' => $c?->id, 'remaining_g' => $c ? 900 : 0, 'enabled' => $c !== null]);
        }

        return [$indx, $tools];
    }

    /** A design of two parts in the same layers (a plate with a bar set into it), its colours flush: one plate or nothing. */
    private function design(float $size = 20.0): ModelFile
    {
        $path = sys_get_temp_dir().'/mp_flush_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $size);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'inlay.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $dir = dirname(Storage::disk(ModelFile::DISK)->path($file->storage_path)).'/parts';
        mkdir($dir, 0777, true);
        MeshFixtures::cubeStl($dir.'/color_1.stl', $size);
        MeshFixtures::cubeStl($dir.'/color_2.stl', $size * 0.3);
        $file->forceFill(['origin' => 'tool', 'origin_ref' => 'gcode', 'tool_params' => [
            'parts' => ['color_1', 'color_2'], 'multi_material' => true, 'one_plate_required' => true,
            'part_colors' => ['color_1' => ['hex' => self::HEXES[0]], 'color_2' => ['hex' => self::HEXES[1]]],
        ]])->save();
        $this->assertTrue($file->onePlateRequired());

        return $file;
    }

    private function order(ModelFile $file, int $colorId, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid, 'color' => $colorId, 'by_parts' => 1] + $extra)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function state(FarmOrder $order): array
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

    /** @return list<array{0: string, 1: string}> the build items of a project as [objectid, transform] */
    private function items(string $path): array
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'the project '.$path);
        $model = (string) $zip->getFromName('3D/3dmodel.model');
        $zip->close();
        preg_match_all('/<item objectid="(\d+)" transform="([^"]+)"\/>/', $model, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => [$x[1], $x[2]], $m);
    }

    public function test_four_copies_of_a_flush_design_print_on_one_plate_of_a_tool_changer_and_the_payment_takes_them(): void
    {
        [$indx, $tools] = $this->indx();
        $file = $this->design();
        $order = $this->order($file, $tools[0]->farm_color_id, ['part_color' => ['color_2' => $tools[1]->farm_color_id], 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.(string) $order->error_detail.' '.json_encode($order->timings));
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame($indx->id, $order->farm_printer_id);
        $this->assertSame([1, 4, null, null], [$order->plates, $order->plate_copies, $order->rest_copies, $order->rest_gcode_path]);
        $this->assertSame([4], $order->plateLayout());
        $this->assertSame([4, 4], array_column($order->partPlates(), 'copies'));
        $this->assertSame([1, 2], array_column($order->partPlates(), 'extruder'), 'white in tool 1, black in tool 2');
        $this->assertSame(['per_plate' => 4, 'grid' => [2, 2], 'rest_grid' => null], $order->slice_params['copies_grid']);
        $this->assertSame('parts', $order->timings['one_plate_project']);
        $this->assertSame(4, $order->timings['one_plate_instances']);
        $this->assertGreaterThanOrEqual(4, $order->check['max_copies']);
        // a tool changer: no tower and no band kept for it
        $this->assertNull($order->slice_params['tower']);
        $this->assertSame('0', $order->slice_params['overrides']['process']['enable_prime_tower']);

        // the project: two objects, each placed four times with the very same moves (2 × 2, a piece and a gap apart), no turn
        $disk = Storage::disk('farm');
        $items = $this->items($disk->path($order->dir().'/plate.3mf'));
        $this->assertCount(8, $items);
        $this->assertSame(['1', '1', '1', '1', '2', '2', '2', '2'], array_column($items, 0));
        $body = array_column(array_slice($items, 0, 4), 1);
        $this->assertSame($body, array_column(array_slice($items, 4), 1), 'the bar moves with its plate');
        $moves = array_map(fn ($t) => array_map('floatval', array_slice(explode(' ', $t), 9)), $body);
        $this->assertSame([0.0, 0.0, 0.0], $moves[0]);
        $this->assertEqualsWithDelta([25.0, 0.0, 0.0], $moves[1], 0.01, 'the second copy a piece and a gap to the right');
        $this->assertEqualsWithDelta([0.0, 25.0, 0.0], $moves[2], 0.01, 'the third above the first');
        $this->assertEqualsWithDelta([25.0, 25.0, 0.0], $moves[3], 0.01);
        foreach ($body as $t) {
            $this->assertStringStartsWith('1 0 0 0 1 0 0 0 1 ', $t, 'the pieces lie as the tool laid them');
        }
        // the preview's parts hold the four copies, centred on the bed; nothing of the slicer's own files stays behind
        [$lo, $hi] = StlFile::bounds($disk->path($order->dir().'/plate-part-1.stl'));
        $this->assertEqualsWithDelta(45.0, $hi[0] - $lo[0], 0.01);
        $this->assertEqualsWithDelta([124.0, 102.5], [($lo[0] + $hi[0]) / 2, ($lo[1] + $hi[1]) / 2], 0.01, 'the set in the middle of the 248 × 205 bed');
        $this->assertFileDoesNotExist($disk->path($order->dir().'/plate-part-1.stl.one'));
        $this->assertFileDoesNotExist($disk->path($order->dir().'/plate-part-2.stl.one'));
        // the grams by position: a figure per filament loaded, the parts' grams from them, the whole plate's four pieces
        $this->assertCount(2, $order->slice_result['filament_grams']);
        $this->assertEqualsWithDelta($order->slice_result['filament_grams'], array_column($order->partPlates(), 'grams'), 0.051);
        $this->assertEqualsWithDelta(array_sum($order->slice_result['filament_grams']), $order->est_grams, 0.2);
        $one = $this->order($file, $tools[0]->farm_color_id, ['part_color' => ['color_2' => $tools[1]->farm_color_id]]);
        $this->assertGreaterThan($one->est_grams * 3.5, $order->est_grams, 'four pieces weigh about four times one');
        $this->assertLessThan($one->est_grams * 4.5, $order->est_grams);

        // the page: the copies, the grid, how many fit – and no "one piece at a time"
        $state = $this->state($order);
        $this->assertSame([4, 1, 4, [4], [2, 2]], [$state['copies'], $state['plates'], $state['plate_copies'], $state['plate_layout'], $state['copies_grid']]);
        $this->assertGreaterThanOrEqual(4, $state['max_copies']);
        $this->assertTrue($state['one_plate_required']);
        $this->assertTrue($state['one_plate']);
        $this->assertFalse($state['painted_colors']);
        $this->assertSame(8, $state['max_colors']);
        $this->assertStringNotContainsString('jednom kuse', __('farm.order.one_plate_required'));
        $this->assertNull($this->state($one)['copies_grid'], 'one piece: no grid');

        // paid with four copies: queued (it was refused with one_plate_needed and the credit given back), one job for the plate
        $this->credit(5000);
        $main = collect($state['colors'])->firstWhere('slot', $state['slot']) ?? $state['colors'][0];
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $main['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $main['total'], 'part_slots' => ['color_2' => $tools[1]->id]])
            ->assertOk()->assertJsonPath('status', 'queued');
        $order->refresh();
        $this->assertSame(4, $order->copies);
        $this->assertLessThan(5000.0, app(Wallet::class)->balance($this->user)->amount);
        $cmd = $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => 'core-one-01', 'state' => 'idle', 'telemetry' => ['extruder' => 25.0], 'job' => null]]], $this->auth)->assertOk()->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->assertSame([0 => 0, 1 => 1], $cmd['payload']['filaments']);
    }

    public function test_more_copies_than_a_plate_takes_go_on_full_plates_and_the_rest_on_the_last_one(): void
    {
        [, $tools] = $this->indx();
        // 80 mm pieces on the 248 × 205 bed (244 × 201 usable, a 5 mm gap): 2 × 2 fit, nine copies take 4 + 4 + 1
        $file = $this->design(80.0);
        $order = $this->order($file, $tools[0]->farm_color_id, ['part_color' => ['color_2' => $tools[1]->farm_color_id], 'copies' => 9]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.(string) $order->error_detail.' '.json_encode($order->timings));
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame([3, 4, 1], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertSame([4, 4, 1], $order->plateLayout());
        $this->assertSame(4, $order->check['max_copies']);
        $this->assertSame(['per_plate' => 4, 'grid' => [2, 2], 'rest_grid' => [1, 1]], $order->slice_params['copies_grid']);
        $this->assertNotNull($order->rest_gcode_path);
        $this->assertFileExists($order->absoluteGcodePath(3));
        $this->assertNotSame($order->absoluteGcodePath(1), $order->absoluteGcodePath(3));
        $this->assertSame($order->absoluteGcodePath(1), $order->absoluteGcodePath(2), 'the second plate is the first one again');
        $disk = Storage::disk('farm');
        $this->assertCount(8, $this->items($disk->path($order->dir().'/plate.3mf')));
        $rest = $this->items($disk->path($order->dir().'/plate-rest.3mf'));
        $this->assertCount(2, $rest, 'the last plate: every object once');
        $this->assertSame($rest[0][1], $rest[1][1]);
        // the single piece of the last plate stands in the middle of the bed: the full set's first piece moved by half the set less half a piece
        $move = array_map('floatval', array_slice(explode(' ', $rest[0][1]), 9));
        $this->assertEqualsWithDelta([(80 + 5) / 2, (80 + 5) / 2, 0.0], $move, 0.01, json_encode($rest));
        // the figures: two full plates and the last one
        $full = (float) $order->slice_result['grams'];
        $last = (float) $order->slice_result['rest']['grams'];
        $this->assertEqualsWithDelta(2 * $full + $last, (float) $order->est_grams, 0.2);
        $this->assertGreaterThan($last * 3.5, $full);
        $this->assertSame(1, $order->slice_result['rest']['plate_copies'] ?? 1);
        $state = $this->state($order);
        $this->assertSame([9, 3, [4, 4, 1], 4], [$state['copies'], $state['plates'], $state['plate_layout'], $state['max_copies']]);
    }

    public function test_on_a_machine_with_an_ace_the_tower_keeps_its_band_beside_the_copies_and_a_manual_machine_is_refused(): void
    {
        [$s1, $white, $red] = $this->s1();
        $file = $this->design();
        $order = $this->order($file, $white->farm_color_id, ['part_color' => ['color_2' => $red->farm_color_id], 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.(string) $order->error_detail.' '.json_encode($order->timings));
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame([1, 4], [$order->plates, $order->plate_copies]);
        $this->assertSame([2, 2], $order->slice_params['copies_grid']['grid']);
        // the tower: on, below the set, its spot in the process; the set pushed up when the band needs it (small pieces: not)
        $tower = $order->slice_params['tower'];
        $this->assertNotNull($tower);
        $this->assertSame('1', $order->slice_params['overrides']['process']['enable_prime_tower']);
        $this->assertSame([(string) $tower['x']], $order->slice_params['overrides']['process']['wipe_tower_x']);
        $this->assertSame([(string) $tower['y']], $order->slice_params['overrides']['process']['wipe_tower_y']);
        $this->assertSame(7.0, (float) $tower['y'], 'below the pieces: the bed margin and the brim');
        $this->assertEquals([0, 0], $tower['shift']);
        $this->assertGreaterThan(0, $order->slice_result['changes']);
        $this->assertCount(8, $this->items(Storage::disk('farm')->path($order->dir().'/plate.3mf')));
        // the copies of a design the tower leaves fewer places for: 115 mm pieces on the 250 plate are 2 × 2 without the band
        // (235 of 246 mm), 2 × 1 with the 25 mm band below them – two plates of two
        $big = $this->order($this->design(115.0), $white->farm_color_id, ['part_color' => ['color_2' => $red->farm_color_id], 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $big->status, (string) $big->error.' '.(string) $big->error_detail.' '.json_encode($big->timings));
        $this->assertSame([2, 2, null], [$big->plates, $big->plate_copies, $big->rest_copies]);
        $this->assertSame(2, $big->check['max_copies']);
        $this->assertSame([2, 1], $big->slice_params['copies_grid']['grid']);

        // a machine run by hand: refused with the reason, with copies as without them
        $s1->update(['mode' => FarmPrinter::MODE_MANUAL]);
        $manual = $this->order($file, $white->farm_color_id, ['part_color' => ['color_2' => $red->farm_color_id], 'copies' => 2]);
        $this->assertSame([FarmOrder::STATUS_FAILED, 'one_plate_needed', 'manual'], [$manual->status, $manual->error, $manual->error_detail]);
    }

    public function test_the_tool_page_learns_how_many_colours_one_print_takes(): void
    {
        // the S1 of the seed with its four positions
        $this->assertSame(4, $this->getJson('/api/config')->assertOk()->json('max_colors'));
        // the Core One with the INDX run by the agent: eight
        $this->indx();
        $this->assertSame(8, $this->getJson('/api/config')->assertOk()->json('max_colors'));
        $this->assertStringContainsString(':max', __('param.shape.print.plate.many.farm'));
        $this->assertStringNotContainsString('čtyři', __('param.shape.print.plate.many.farm'));
        $this->assertStringNotContainsString('jednom kuse', __('param.shape.print.plate.farm'));
    }
}
