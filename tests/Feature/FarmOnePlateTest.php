<?php

namespace Tests\Feature;

use App\Domain\Farm\GcodeSlot;
use App\Engines\Mesh\StlFile;
use App\Mail\FarmAdminAlert;
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
 * Two to four filaments in one plate (docs/AL.md §10): an order by parts whose parts print together, a filament per
 * spool, the machine changing spools as it goes. One 3MF with an object per part, one G-code with T0…T<n-1>, the copy
 * for the printer mapping every T<n> to its slot and naming them in the header; by parts on separate plates when the
 * machine is run by hand, has fewer slots, or the parts do not fit together.
 */
class FarmOnePlateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FarmPrinter $s1;

    private FarmPrinterSlot $white;

    private FarmPrinterSlot $red;

    private array $auth;

    private FarmAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['farm.settings.admin_email' => 'farm@example.com']);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        // the S1 run by an agent (a changer): white in slot 3, red in slot 1
        [$this->agent, $token] = FarmAgent::issue('test agent');
        $this->auth = ['Authorization' => 'Bearer '.$token];
        $this->s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $this->s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $this->agent->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE]);
        $this->white = $this->s1->slots()->whereNotNull('farm_color_id')->firstOrFail();
        $redColor = FarmColor::whereHas('material', fn ($q) => $q->where('code', 'like', 'PLA%'))->where('id', '!=', $this->white->farm_color_id)->firstOrFail();
        $redColor->update(['hex' => '#C01818', 'enabled' => true]);
        $this->red = $this->s1->slots()->whereNull('farm_color_id')->orderBy('slot')->firstOrFail();
        $this->red->update(['farm_color_id' => $redColor->id, 'remaining_g' => 800, 'enabled' => true]);
    }

    /**
     * A design of two parts in the same layers, as a tool that keeps part files makes it (a model out of G-code): a
     * 20 mm plate with a 6 mm bar set into it, the bar in another colour.
     */
    private function design(float $size = 20.0): ModelFile
    {
        $path = sys_get_temp_dir().'/mp_plate_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $size);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'inlay.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $dir = dirname(Storage::disk(ModelFile::DISK)->path($file->storage_path)).'/parts';
        mkdir($dir, 0777, true);
        MeshFixtures::cubeStl($dir.'/color_1.stl', $size);
        MeshFixtures::cubeStl($dir.'/color_2.stl', $size * 0.3);
        $file->forceFill(['origin' => 'tool', 'origin_ref' => 'gcode', 'tool_params' => [
            'parts' => ['color_1', 'color_2'], 'multi_material' => true,
            'part_colors' => ['color_1' => ['hex' => '#f4f4f0'], 'color_2' => ['hex' => '#c01818']],
        ]])->save();

        return $file;
    }

    private function order(ModelFile $file, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid, 'color' => $this->white->farm_color_id, 'by_parts' => 1] + $extra)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
    }

    private function sync(string $state = 'idle', ?array $job = null)
    {
        return $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => 'kobra-s1-01', 'state' => $state, 'telemetry' => ['extruder' => 215.2], 'job' => $job]]], $this->auth);
    }

    public function test_two_parts_from_two_spools_of_an_agents_machine_print_on_one_plate(): void
    {
        $file = $this->design();
        $order = $this->order($file, ['part_color' => ['color_2' => $this->red->farm_color_id]]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status);
        $this->assertTrue($order->isByParts());
        $this->assertTrue($order->onePlate(), 'the parts fit, the machine changes spools: one plate');
        $this->assertSame(1, $order->plates);
        $this->assertSame(['color_1', 'color_2'], array_column($order->partPlates(), 'part'));
        $this->assertSame([1, 2], array_column($order->partPlates(), 'extruder'));
        $this->assertSame([$this->white->id, $this->red->id], array_column($order->partPlates(), 'slot_id'));
        $this->assertSame([0 => $this->white->slot, 1 => $this->red->slot], $order->filamentSlots());
        $this->assertTrue($order->slice_params['one_plate']);
        $this->assertCount(2, $order->slice_params['filaments']);
        $this->assertCount(2, $order->slice_result['filament_grams']);
        $this->assertGreaterThan(0, $order->slice_result['changes']);
        $this->assertSame('1', $order->slice_params['overrides']['process']['enable_prime_tower'], 'the prime tower is on for this plate');
        $this->assertEqualsWithDelta(array_sum($order->slice_result['filament_grams']), $order->est_grams, 0.2, 'the grams of the plate are those of its filaments, purge included');
        $this->assertGreaterThan(0, $order->price['changes']);
        $disk = Storage::disk('farm');
        $this->assertTrue($disk->exists($order->dir().'/plate.3mf'));
        $this->assertTrue($disk->exists($order->dir().'/plate-part-1.stl'));
        $this->assertTrue($disk->exists($order->dir().'/plate-part-2.stl'));
        $this->assertSame($order->dir().'/print.gcode', $order->gcode_path);
        $this->assertSame($order->absoluteGcodePath(1), $order->absoluteGcodePath(2), 'one file for every part');
        // the project: an object per part, the second on the second filament
        $zip = new \ZipArchive;
        $zip->open($disk->path($order->dir().'/plate.3mf'));
        $config = (string) $zip->getFromName('Metadata/model_settings.config');
        $this->assertSame(2, substr_count((string) $zip->getFromName('3D/3dmodel.model'), '<object id='));
        $zip->close();
        $this->assertStringContainsString('value="color_1"/><metadata key="extruder" value="1"/>', $config);
        $this->assertStringContainsString('value="color_2"/><metadata key="extruder" value="2"/>', $config);
        // the parts kept their places against each other: the small bar inside the plate's footprint, both standing on the plate
        [$lo1, $hi1] = StlFile::bounds($disk->path($order->dir().'/plate-part-1.stl'));
        [$lo2, $hi2] = StlFile::bounds($disk->path($order->dir().'/plate-part-2.stl'));
        $this->assertEqualsWithDelta(0.0, $lo1[2], 0.001);
        $this->assertEqualsWithDelta(0.0, $lo2[2], 0.001);
        $this->assertEqualsWithDelta([125.0, 125.0], [($lo1[0] + $hi1[0]) / 2, ($lo1[1] + $hi1[1]) / 2], 0.01, 'the plate in the middle of the bed');
        $this->assertSame([$lo1[0], $lo1[1]], [$lo2[0], $lo2[1]], 'the bar where the design put it (both from the origin)');

        // the order page: one plate, the number of changes, every part on plate 1
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertTrue($state['one_plate']);
        $this->assertSame($order->slice_result['changes'], $state['filament_changes']);
        $this->assertSame([1, 1], array_column($state['parts'], 'plate'));
        $this->assertSame([$this->white->id, $this->red->id], array_column($state['parts'], 'slot_id'));
        // the preview: every part from its own STL, in the plate's coordinates (the page keeps them where they are)
        foreach ($order->partPlates() as $i => $plate) {
            $file = $this->actingAs($this->user)->get($state['parts'][$i]['model_url'])->assertOk();
            $this->assertSame(file_get_contents($disk->path($plate['stl_path'])), $file->streamedContent() ?: $file->getContent());
        }

        // the operator's copy maps the filaments to the slots and names them up front
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get(route('admin.farm.orders.show', $order))->assertOk()->assertSee('data-together="1"', false)->assertSee('data-parts=', false);
        $copy = (string) file_get_contents($this->actingAs($admin)->get("/admin/farm/orders/{$order->token}/print.gcode")->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString("\n".GcodeSlot::FILAMENTS_LINE." T0={$this->white->slot} T1={$this->red->slot}\n", substr($copy, 0, 16384));
        $this->assertStringContainsString("\nT{$this->white->slot} ; matplace slot for filament 0\n", $copy);
        $this->assertStringContainsString("\nT{$this->red->slot} ; matplace slot for filament 1\n", $copy);
        $this->assertStringContainsString('; filament used [g] = ', $copy);
        $this->assertStringContainsString('; filament_type = PLA;PLA', $copy);
        $page = $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk();
        $page->assertSee('data-one-plate', false)->assertSee('T0')->assertSee('T1');

        // paid: the machine gets one job whose start names the slot of every filament, and the file it downloads says the same
        $this->credit(5000);
        $main = collect($state['colors'])->first(fn ($c) => $c['slot'] === $this->white->id);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $main['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $main['total'], 'part_slots' => ['color_2' => $this->red->id]])
            ->assertOk()->assertJsonPath('status', 'queued');
        $order->refresh();
        $this->assertTrue($order->onePlate(), 'the same spools: no new slice');
        $this->s1->refresh()->update(['bed_clear' => true]);
        $cmd = $this->sync()->assertOk()->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->assertSame($this->white->slot, $cmd['payload']['slot']);
        $this->assertSame([0 => $this->white->slot, 1 => $this->red->slot], $cmd['payload']['filaments']);
        $agentCopy = (string) file_get_contents($this->getJson($cmd['payload']['gcode_url'], $this->auth)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString(GcodeSlot::FILAMENTS_LINE." T0={$this->white->slot} T1={$this->red->slot}", $agentCopy);
        // the operator hears which spools the print takes
        $this->sync('printing', ['id' => $cmd['job_id'], 'status' => 'printing', 'progress' => 2]);
        Mail::assertQueued(FarmAdminAlert::class, fn (FarmAdminAlert $m) => $m->kind === 'printing' && str_contains(implode(' ', $m->lines), $order->spoolText()) && str_contains($order->spoolText(), $this->red->code()));
        // done: one plate, the order is finished at once
        $this->sync('idle', ['id' => $cmd['job_id'], 'status' => 'done', 'progress' => 100, 'print_duration' => 3300, 'filament_used' => 4000]);
        $this->assertSame(FarmOrder::STATUS_DONE, $order->refresh()->status);
    }

    public function test_the_parts_print_one_plate_each_when_the_machine_cannot_change_spools_or_there_are_several_copies(): void
    {
        $file = $this->design();
        // several copies: the plate layout of parts is not done yet – one plate per part, as before
        $copies = $this->order($file, ['part_color' => ['color_2' => $this->red->farm_color_id], 'copies' => 2]);
        $this->assertFalse($copies->onePlate());
        $this->assertSame(2, $copies->plates);
        $this->assertFileExists($copies->absoluteGcodePath(2));
        // a machine run by hand: the operator feeds one spool at a time
        $this->s1->update(['mode' => FarmPrinter::MODE_MANUAL]);
        $manual = $this->order($file, ['part_color' => ['color_2' => $this->red->farm_color_id]]);
        $this->assertFalse($manual->onePlate());
        $this->assertSame(2, $manual->plates);
        $this->assertSame([$this->white->id, $this->red->id], array_column($manual->partPlates(), 'slot_id'));
        $this->assertArrayNotHasKey('one_plate', (array) $manual->slice_params);
        $this->s1->update(['mode' => FarmPrinter::MODE_AGENT]);
        // both parts from the main spool: nothing to change – one plate each, as a by-parts order always was
        $same = $this->order($file, ['part_color' => []]);
        $this->assertFalse($same->onePlate());
        $this->assertSame(2, $same->plates);
    }

    public function test_choosing_other_spools_for_the_parts_at_payment_slices_the_plate_again(): void
    {
        $file = $this->design();
        $order = $this->order($file, ['part_color' => []]);   // both parts on the white spool: two plates
        $this->assertSame(2, $order->plates);
        $this->credit(5000);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $main = collect($state['colors'])->first(fn ($c) => $c['slot'] === $this->white->id);
        $this->assertContains($this->red->id, array_column($main['second'], 'slot'), 'the red spool of the same machine is offered for the parts');
        // the red spool for the bar at payment: sliced again as one plate of two filaments (the price stays as quoted)
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $main['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $main['total'], 'part_slots' => ['color_2' => $this->red->id]])
            ->assertOk();
        $order->refresh();
        $this->assertTrue($order->onePlate());
        $this->assertSame(1, $order->plates);
        $this->assertSame([$this->white->id, $this->red->id], array_column($order->partPlates(), 'slot_id'));
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->status);
        $this->assertSame((float) $main['total'], (float) $order->price_total, 'the customer pays what the page said');
    }
}
