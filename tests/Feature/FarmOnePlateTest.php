<?php

namespace Tests\Feature;

use App\Domain\Farm\GcodeSlot;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\Wallet;
use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\StlTopology;
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
        // the tower has a band of its own below the parts and every filament its spool's colour (Orca 2.4 falls over without)
        $tower = $order->slice_params['tower'];
        $this->assertEquals([0, 0], $tower['shift'], 'small parts stay centred');
        $this->assertSame(7.0, (float) $tower['y'], 'below the parts: the bed margin and the brim');
        $this->assertSame([(string) $tower['x']], $order->slice_params['overrides']['process']['wipe_tower_x']);
        $this->assertSame([(string) $tower['y']], $order->slice_params['overrides']['process']['wipe_tower_y']);
        $this->assertSame([$this->white->color->hex, '#C01818'], array_column($order->slice_params['filaments'], 'colour'));
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

    /**
     * A design that says `one_plate_required` (a medal with its motif flush in the first layers) never falls back to
     * parts on separate plates: by parts from the start, only the spools of machines with a changer, one piece, and
     * when that cannot be, the order stops with a reason instead of printing a loose motif (docs/AL.md §10.8).
     */
    public function test_a_design_that_must_print_on_one_plate_stops_with_a_reason_instead_of_falling_back(): void
    {
        $file = $this->design();
        $file->forceFill(['tool_params' => ['one_plate_required' => true] + $file->tool_params])->save();
        $this->assertTrue($file->onePlateRequired());
        // the parts of a generator's design are listed by the flag alone (the raised medal is no assembled design)
        $medal = new ModelFile(['origin' => 'tool', 'origin_ref' => 'medallion', 'tool_params' => ['parts' => ['body', 'color_1', 'color_2'], 'one_plate_required' => true]]);
        $this->assertSame(['body', 'color_1', 'color_2'], OrderService::designParts($medal));
        $medal->tool_params = ['parts' => ['body', 'color_1']];
        $this->assertSame([], OrderService::designParts($medal), 'without the flag a medal prints as one piece (its raised motif by height)');

        // by parts from the start, without any colour named: the order reaches the one-plate slice
        $plain = FarmOrder::where('token', basename($this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid, 'color' => $this->white->farm_color_id])->assertCreated()->json('url')))->firstOrFail();
        $this->assertTrue($plain->isByParts());

        // a second machine run by hand holds the same white: not offered for this design, offered for any other
        $max = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $max->slots()->where('slot', 0)->firstOrFail()->update(['farm_color_id' => $this->white->farm_color_id, 'remaining_g' => 900, 'enabled' => true]);
        $order = $this->order($file, ['part_color' => ['color_2' => $this->red->farm_color_id]]);
        $this->assertTrue($order->onePlate(), 'two spools of the agent\'s machine: one plate');
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertTrue($state['one_plate_required']);
        $white = collect($state['colors'])->first(fn ($c) => $c['slot'] === $this->white->id);
        $this->assertSame([$this->white->code()], $white['codes'], 'the Max (manual) is not among the spools of this colour');
        $this->assertNotContains($max->slots()->where('slot', 0)->value('id'), array_column($white['second'], 'slot'));
        $other = $this->order($this->design(), ['part_color' => ['color_2' => $this->red->farm_color_id]]);
        $otherWhite = collect($this->actingAs($this->user)->getJson("/farm/orders/{$other->token}/status")->json('colors'))->first(fn ($c) => $c['slot'] === $this->white->id);
        $this->assertContains($max->shortCode().'1', $otherWhite['codes'], 'an ordinary design may go to the Max');

        // two copies: stopped, not printed by parts; the customer reads why
        $two = $this->order($file, ['part_color' => ['color_2' => $this->red->farm_color_id], 'copies' => 2]);
        $this->assertSame(FarmOrder::STATUS_FAILED, $two->status);
        $this->assertSame('one_plate_needed', $two->error);
        $this->assertSame('copies', $two->error_detail);
        $this->assertNull($two->gcode_path);
        $this->assertSame('one_plate_needed', $this->actingAs($this->user)->getJson("/farm/orders/{$two->token}/status")->json('error'));
        $this->assertStringContainsString('jedné vrstvě', __('farm.error.one_plate_needed'));

        // a machine run by hand: stopped as well, with the reason for the operator
        $this->s1->update(['mode' => FarmPrinter::MODE_MANUAL]);
        $manual = $this->order($file, ['part_color' => ['color_2' => $this->red->farm_color_id]]);
        $this->assertSame([FarmOrder::STATUS_FAILED, 'one_plate_needed', 'manual'], [$manual->status, $manual->error, $manual->error_detail]);
        $this->s1->update(['mode' => FarmPrinter::MODE_AGENT]);

        // paid after the machine went to manual operation: refused before any money moves, with the same reason
        $this->credit(5000);
        $main = collect($state['colors'])->first(fn ($c) => $c['slot'] === $this->white->id);
        $this->s1->update(['mode' => FarmPrinter::MODE_MANUAL]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $main['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $main['total'], 'part_slots' => ['color_2' => $this->red->id]])
            ->assertStatus(422)->assertJsonPath('error', 'one_plate_needed');
        $this->assertSame(5000.0, app(Wallet::class)->balance($this->user)->amount);
        $this->s1->update(['mode' => FarmPrinter::MODE_AGENT, 'last_seen_at' => now()]);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $main['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $main['total'], 'part_slots' => ['color_2' => $this->red->id]])
            ->assertOk()->assertJsonPath('status', 'queued');
    }

    /**
     * A motif sitting in a pocket of the body shares its walls with it: glued into one mesh the two look non-manifold
     * (the medal of 19 Oct 2026 on production: 1 371 bad edges, "3 862 bodies", the order failed as not watertight),
     * although each part is closed. Every part is checked on its own; the plate as a whole is only measured.
     */
    public function test_parts_that_share_walls_are_checked_one_by_one_and_keep_their_places(): void
    {
        $path = sys_get_temp_dir().'/mp_pocket_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'medal.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $dir = dirname(Storage::disk(ModelFile::DISK)->path($file->storage_path)).'/parts';
        mkdir($dir, 0777, true);
        // the body: two blocks with a 6 mm gap between them; the motif: the block that fills the gap, its walls on theirs
        StlFile::writeBox($dir.'/left.stl', 7, 20, 3);
        StlFile::writeBox($dir.'/right-at-origin.stl', 7, 20, 3);
        StlFile::translate($dir.'/right-at-origin.stl', $dir.'/right.stl', [13.0, 0.0, 0.0]);
        StlFile::concat([$dir.'/left.stl', $dir.'/right.stl'], $dir.'/body.stl');
        StlFile::writeBox($dir.'/motif-at-origin.stl', 6, 20, 3);
        StlFile::translate($dir.'/motif-at-origin.stl', $dir.'/color_1.stl', [7.0, 0.0, 0.0]);
        StlFile::concat([$dir.'/body.stl', $dir.'/color_1.stl'], $dir.'/glued.stl');
        $this->assertTrue(StlTopology::check($dir.'/body.stl')['watertight']);
        $this->assertTrue(StlTopology::check($dir.'/color_1.stl')['watertight']);
        $this->assertFalse(StlTopology::check($dir.'/glued.stl')['watertight'], 'glued together the shared walls are non-manifold');
        $file->forceFill(['origin' => 'tool', 'origin_ref' => 'gcode', 'tool_params' => [
            'parts' => ['body', 'color_1'], 'multi_material' => true, 'one_plate_required' => true,
            'part_colors' => ['body' => ['hex' => '#f4f4f0'], 'color_1' => ['hex' => '#c01818']],
        ]])->save();

        $order = $this->order($file, ['part_color' => ['color_1' => $this->red->farm_color_id]]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, 'checked part by part, the plate is sliced');
        $this->assertTrue($order->onePlate());
        $this->assertTrue($order->check['ok']);
        $this->assertTrue($order->check['mesh']['watertight']);
        $this->assertSame(2, $order->check['mesh']['shells'], 'one shell counted per part');
        $this->assertSame(['x' => 20.0, 'y' => 20.0, 'z' => 3.0], array_map(fn ($v) => round((float) $v, 2), $order->check['mesh']['bbox']));
        $this->assertSame(2, $order->orientation['parts']);
        // the parts kept their places against each other after each was checked alone: the motif's left wall 7 mm in from the body's
        $disk = Storage::disk('farm');
        [$bodyLo] = StlFile::bounds($disk->path($order->dir().'/plate-part-1.stl'));
        [$motifLo, $motifHi] = StlFile::bounds($disk->path($order->dir().'/plate-part-2.stl'));
        $this->assertEqualsWithDelta(7.0, $motifLo[0] - $bodyLo[0], 0.001);
        $this->assertEqualsWithDelta(6.0, $motifHi[0] - $motifLo[0], 0.001);
        $this->assertEqualsWithDelta(0.0, $motifLo[2], 0.001);
        $this->assertFileExists($disk->path($order->dir().'/print.stl'));
        $this->assertTrue($disk->exists($order->dir().'/plate.3mf'));
    }
}
