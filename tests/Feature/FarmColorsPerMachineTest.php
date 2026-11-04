<?php

namespace Tests\Feature;

use App\Domain\Farm\ColorLimit;
use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\SpoolPositions;
use App\Domain\Files\PaintedColors;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
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
 * The colour limit of a print is the number of spool positions of the machine that prints it, and the order page
 * chooses by machine (TODO #66, docs/AL.md §19): a Kobra X with three places on its rack and four in the ACE takes
 * seven colours, a Kobra S1 four; the positions are named as the operator sees them; a painted 3MF keeps as many
 * colour groups as the farm's biggest changer holds; the admin gives a machine eight positions and names them.
 */
final class FarmColorsPerMachineTest extends TestCase
{
    use RefreshDatabase;

    /** The colours of the designs: the S1's white first, then six the Kobra X holds, then one it does not. */
    private const HEXES = ['#f4f4f0', '#c01818', '#1830c0', '#18a018', '#e0d000', '#101010', '#f08020', '#8020c0'];

    private User $user;

    private FarmPrinter $s1;

    private FarmPrinterSlot $white;

    private FarmAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        [$this->agent] = FarmAgent::issue('test agent');
        // the S1 run by an agent (a changer), white in its third position as seeded
        $this->s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $this->s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $this->agent->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE, 'bed_clear' => true]);
        $this->white = $this->s1->slots()->whereNotNull('farm_color_id')->firstOrFail();
        FarmColor::findOrFail($this->white->farm_color_id)->update(['hex' => '#F4F4F0', 'enabled' => true]);
    }

    /**
     * A Kobra X run by the agent with `$positions` slot rows: the S1's white in the first (the rack), then `$colours`
     * PLA colours of HEXES, the rest empty.
     *
     * @return array{0: FarmPrinter, 1: list<FarmColor>}
     */
    private function kobraX(int $positions = 7, int $colours = 6): array
    {
        $x = $this->s1->replicate(['code'])->fill([
            'name' => 'Kobra X #1', 'code' => 'X1', 'model' => 'Anycubic Kobra X', 'key' => 'kobra-x-01', 'machine_profile' => 'machine_kobrax.json',
            'bed_x' => 260, 'bed_y' => 260, 'bed_z' => 260, 'mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $this->agent->id,
            'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE, 'bed_clear' => true, 'enabled' => true,
        ]);
        $x->save();
        $made = [];
        for ($i = 0; $i < $positions; $i++) {
            $color = $i === 0 ? FarmColor::find($this->white->farm_color_id) : ($i <= $colours ? $this->colour(self::HEXES[$i], 'barva '.$i) : null);
            $x->slots()->create(['slot' => $i, 'farm_color_id' => $color?->id, 'remaining_g' => $color ? 900 : 0, 'enabled' => $color !== null]);
            if ($i > 0 && $color) {
                $made[] = $color;
            }
        }

        return [$x->fresh(), $made];
    }

    private function colour(string $hex, string $name, ?FarmMaterial $kind = null): FarmColor
    {
        $kind ??= FarmColor::findOrFail($this->white->farm_color_id)->material;

        return FarmColor::create(['farm_material_id' => $kind->id, 'name' => $name, 'hex' => $hex, 'enabled' => true]);
    }

    /** A tool's design of `$n` parts, each in a colour of HEXES, as a tool that keeps part files makes it (cubes one in another, as FarmOnePlateTest). */
    private function designOf(int $n): ModelFile
    {
        $path = sys_get_temp_dir().'/mp_cpm_'.uniqid().'.stl';
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

    private function order(ModelFile $file, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid] + $extra)->assertCreated();

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

    private function uploadSixCubes(): array
    {
        $path = sys_get_temp_dir().'/mp_six_'.uniqid().'.3mf';
        copy(base_path('tests/fixtures/painted-six-cubes.3mf'), $path);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'six.3mf', null, null, true)])->assertCreated()->json('file');
    }

    public function test_the_limit_is_the_machines_number_of_positions_within_the_ceiling(): void
    {
        app()->setLocale('cs');
        $this->assertSame(4, $this->s1->colorLimit(), 'a Kobra S1 with its ACE');
        $this->assertSame(4, ColorLimit::farm());
        [$x] = $this->kobraX(7);
        $this->assertSame(7, $x->colorLimit(), 'three on the rack and four in the ACE');
        $this->assertSame(7, ColorLimit::farm(), 'the biggest changer of the farm');

        // the positions as the operator sees them
        $this->assertSame(['Stojan 1', 'Stojan 2', 'Stojan 3', 'ACE 1', 'ACE 2', 'ACE 3', 'ACE 4'], SpoolPositions::names($x));
        $this->assertSame(['ACE 1', 'ACE 2', 'ACE 3', 'ACE 4'], SpoolPositions::names($this->s1));
        $this->assertSame(['Stojan 1', 'Stojan 2', 'Stojan 3', 'Stojan 4'], SpoolPositions::names($x, 4), 'a Kobra X without the changer');
        $this->assertSame('ACE2 3', SpoolPositions::name($x, 9, 11), 'a second ACE');
        $this->assertSame('Stojan 1', $x->slots()->first()->positionLabel());
        $x->slots()->first()->update(['label' => 'INDX 1']);
        $this->assertSame('INDX 1', $x->slots()->first()->positionLabel(), 'what the admin typed wins');

        // the ceiling of config/farm.php caps any machine; a machine without positions keeps the old default
        config(['farm.max_colors_ceiling' => 5]);
        $this->assertSame(5, $x->fresh()->colorLimit());
        $this->assertSame(5, ColorLimit::farm());
        config(['farm.max_colors_ceiling' => 16]);
        $bare = $this->s1->replicate(['code'])->fill(['key' => 'bare-01', 'name' => 'Bez pozic', 'model' => 'Prusa CORE One', 'machine_profile' => 'none.json']);
        $bare->save();
        $this->assertSame(FarmOrder::MAX_COLORS, $bare->colorLimit());
        $this->assertSame(['Pozice 1', 'Pozice 2'], SpoolPositions::names($bare, 2), 'a machine the farm does not know by model');

        // the farm's limit is the biggest changer's: a machine run by hand does not count while a changer exists
        $x->update(['mode' => FarmPrinter::MODE_MANUAL, 'farm_agent_id' => null]);
        $this->assertSame(4, ColorLimit::farm());
    }

    /** The S1 beside the X: a PETG spool, a spool nearly empty, a spool switched off, an empty fifth position: each said with its reason. */
    private function s1WithReasons(): void
    {
        $petg = FarmMaterial::where('code', 'like', 'PETG%')->firstOrFail();
        $petg->update(['enabled' => true]);
        [$a, $b, $d] = $this->s1->slots()->where('id', '!=', $this->white->id)->orderBy('slot')->get()->all();
        $a->update(['farm_color_id' => $this->colour('#2a9d8f', 'PETG zelená', $petg)->id, 'remaining_g' => 900, 'enabled' => true]);
        $b->update(['farm_color_id' => $this->colour('#777777', 'šedá')->id, 'remaining_g' => 20, 'enabled' => true]);
        $d->update(['farm_color_id' => $this->colour('#333333', 'tmavá')->id, 'remaining_g' => 900, 'enabled' => false]);
        $this->s1->slots()->create(['slot' => 4, 'farm_color_id' => null, 'remaining_g' => 0, 'enabled' => false]);
    }

    public function test_a_kobra_x_with_seven_positions_prints_seven_colours_and_the_page_chooses_by_machine(): void
    {
        app()->setLocale('cs');
        [$x] = $this->kobraX(7, 6);
        $this->s1WithReasons();

        $file = $this->designOf(7);
        $order = $this->order($file);
        $this->assertTrue($order->isByParts());
        $this->assertSame($x->id, $order->farm_printer_id, 'the machine meeting all seven colours');
        $this->assertCount(7, array_unique(array_column($order->partPlates(), 'color_id')));
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertTrue($order->onePlate(), 'seven filaments in one plate');
        $this->assertCount(7, (array) ($order->slice_params['filaments'] ?? []));

        // what the page gets: the limit of the machine, no "too many", a card per machine with its positions and their reasons
        $state = $this->stateOf($order);
        $this->assertSame(7, $state['max_colors']);
        $this->assertNull($state['colors_over']);
        $white = collect($state['colors'])->firstWhere('color_id', $this->white->farm_color_id);
        $this->assertNotNull($white);
        $machines = collect($white['machines'])->keyBy('key');
        $this->assertSame(['kobra-x-01', 'kobra-s1-01'], $machines->keys()->all(), 'the machine the order sits on first');
        $card = $machines['kobra-x-01'];
        $this->assertSame([7, true, 'X1'], [$card['limit'], $card['changer'], $card['printer_code']]);
        $this->assertSame(['Stojan 1', 'Stojan 2', 'Stojan 3', 'ACE 1', 'ACE 2', 'ACE 3', 'ACE 4'], array_column($card['positions'], 'label'));
        $this->assertSame([true, false, false, false, false, false, false], array_column($card['positions'], 'main'));
        $this->assertSame([false, true, true, true, true, true, true], array_column($card['positions'], 'offer'));
        $this->assertSame(['main', null, null, null, null, null, null], array_column($card['positions'], 'reason'));
        $this->assertCount(6, $card['colors'], 'the spools a part may take are the positions on offer');
        $s1Card = $machines['kobra-s1-01'];
        $this->assertSame(5, $s1Card['limit']);
        $this->assertSame(['ACE 1', 'ACE 2', 'ACE 3', 'ACE 4', 'ACE2 1'], array_column($s1Card['positions'], 'label'));
        $this->assertSame(['kind', 'low', 'main', 'off', 'empty'], array_column($s1Card['positions'], 'reason'));
        $this->assertSame([], $s1Card['colors'], 'nothing of the S1 goes with white');
        $this->assertSame([$x->id], FarmPrinterSlot::whereIn('id', array_column($order->partPlates(), 'slot_id'))->pluck('farm_printer_id')->map(fn ($v) => (int) $v)->unique()->values()->all());

        // paid with the seven spools of the X as the page sends them: the plate prints there
        $this->credit(20000);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $card['main'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $white['total'], 'part_slots' => array_column($order->partPlates(), 'slot_id', 'part')])->assertOk();
        $order->refresh();
        $this->assertSame($x->id, $order->farm_printer_id);
        $this->assertNotNull($order->paid_at);
        $this->assertCount(7, array_unique(array_column($order->partPlates(), 'color_id')));
    }

    public function test_an_eighth_colour_is_refused_with_the_number_of_the_machine_that_prints(): void
    {
        [$x, $colours] = $this->kobraX(7, 6);
        // the eighth colour sits in the S1 next to white; the S1 is run by hand, so the X is the machine that would print
        $this->s1->update(['mode' => FarmPrinter::MODE_MANUAL, 'farm_agent_id' => null]);
        $eighth = $this->colour(self::HEXES[7], 'fialová');
        $this->s1->slots()->where('id', '!=', $this->white->id)->orderBy('slot')->firstOrFail()->update(['farm_color_id' => $eighth->id, 'remaining_g' => 900, 'enabled' => true]);
        $file = $this->designOf(8);

        // picked for the customer: seven colours on the X, the page says why with the X's number
        $order = $this->order($file);
        $this->assertSame($x->id, $order->farm_printer_id);
        $this->assertCount(7, array_unique(array_column($order->partPlates(), 'color_id')));
        $state = $this->stateOf($order);
        $this->assertSame(7, $state['max_colors']);
        $this->assertSame(__('farm.refuse.too_many_colors', ['n' => 7]), $state['colors_over']['text']);
        $this->assertStringContainsString(' 7', $state['colors_over']['text']);

        // eight colours the customer names himself: refused with the machine's number; seven go through
        $ids = array_merge([$this->white->farm_color_id], array_map(fn (FarmColor $c) => $c->id, $colours), [$eighth->id]);
        $parts = array_map(fn ($i) => 'color_'.$i, range(1, 8));
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid, 'color' => $ids[0], 'by_parts' => 1, 'part_color' => array_combine($parts, $ids)])
            ->assertStatus(422)->assertJsonPath('error', 'too_many_colors');
        $this->assertSame(__('farm.refuse.too_many_colors', ['n' => 7]), $r->json('message'));
        $sevenIds = array_merge(array_slice($ids, 0, 7), [$ids[0]]);
        $seven = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid, 'color' => $ids[0], 'by_parts' => 1, 'part_color' => array_combine($parts, $sevenIds)])->assertCreated();
        $this->assertCount(7, array_unique(array_column(FarmOrder::where('token', basename($seven->json('url')))->firstOrFail()->partPlates(), 'color_id')));

        // the service itself: the machine of the first colour's slot decides, with its own number
        $orders = app(OrderService::class);
        $xWhite = $x->slots()->where('slot', 0)->firstOrFail();
        $this->assertCount(8, $orders->spoolsForParts($xWhite, $parts, array_combine($parts, $sevenIds)));
        foreach ([[$xWhite, 7], [$this->white, 4]] as [$main, $n]) {
            try {
                $orders->spoolsForParts($main, $parts, array_combine($parts, $ids));
                $this->fail('eight colours went through a machine of '.$n.' positions');
            } catch (FarmRefusal $e) {
                $this->assertSame('too_many_colors', $e->reason);
                $this->assertSame(__('farm.refuse.too_many_colors', ['n' => $n]), $e->text());
            }
        }
    }

    public function test_a_painted_3mf_keeps_six_colour_groups_on_a_farm_with_a_kobra_x(): void
    {
        [$x] = $this->kobraX(7, 6);
        $f = $this->uploadSixCubes();
        $this->assertSame('ready', $f['status'], (string) ($f['error'] ?? ''));
        $file = ModelFile::where('uuid', $f['uuid'])->firstOrFail();
        $this->assertSame(['count' => 6, 'merged' => 0, 'too_many' => false, 'max' => 7], array_intersect_key($file->colors, array_flip(['count', 'merged', 'too_many', 'max'])));
        $this->assertCount(6, PaintedColors::printed($file));

        // the order: a part per colour, the six nearest spools of the X, one plate of six filaments
        $order = $this->order($file);
        $this->assertTrue($order->isByParts(), 'painted: by colour from the start');
        $this->assertSame(array_map(fn ($i) => 'color_'.$i, range(1, 6)), array_column($order->partPlates(), 'part'));
        $this->assertCount(6, array_unique(array_column($order->partPlates(), 'color_id')));
        $this->assertSame($x->id, $order->farm_printer_id);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertTrue($order->onePlate());
        $this->assertCount(6, (array) ($order->slice_params['filaments'] ?? []));
        $this->assertNull($this->stateOf($order)['colors_over']);

        // the same file on a farm whose biggest changer is a Kobra S1: four colours, the two smallest joined
        $x->update(['enabled' => false]);
        $g = $this->uploadSixCubes();
        $again = ModelFile::where('uuid', $g['uuid'])->firstOrFail();
        $this->assertSame(['count' => 4, 'merged' => 2, 'too_many' => true, 'max' => 4], array_intersect_key($again->colors, array_flip(['count', 'merged', 'too_many', 'max'])));
    }

    /**
     * Not a test of behaviour: with MP_DUMP_ORDER_PAGE=<folder> it writes the order page of the seven-colour design on the
     * Kobra X and its state JSON into that folder, to be opened in a browser next to a copy of public/build. That is how the
     * chooser was looked at on 4 Nov 2026, when the shared dev.sqlite had no current schema (docs/AL.md §19.4).
     */
    public function test_the_order_page_of_the_seven_colour_design_can_be_written_out_for_a_look(): void
    {
        $dir = (string) getenv('MP_DUMP_ORDER_PAGE');
        if ($dir === '' || ! is_dir($dir)) {
            $this->markTestSkipped('set MP_DUMP_ORDER_PAGE to a folder to write the page out');
        }
        app()->setLocale('cs');
        $this->kobraX(7, 6);
        $this->s1WithReasons();
        $order = $this->order($this->designOf(7));
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        file_put_contents($dir.'/order.html', (string) $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->getContent());
        file_put_contents($dir.'/state.json', json_encode($this->stateOf($order), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertFileExists($dir.'/order.html');
    }

    public function test_the_admin_gives_a_machine_eight_positions_and_names_them(): void
    {
        app()->setLocale('cs');
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get("/admin/farm/printers/{$this->s1->id}?slots=8")->assertOk()
            ->assertSee('name="slots[7][label]"', false)->assertSee('placeholder="ACE2 4"', false)->assertSee('přidat pozici');
        $form = $this->s1->only(['name', 'model', 'key', 'mode', 'bed_x', 'bed_y', 'bed_z', 'nozzle_mm', 'machine_profile', 'time_factor', 'weight_factor'])
            + ['enabled' => 1, 'process_profiles' => json_encode($this->s1->process_profiles), 'farm_agent_id' => $this->agent->id];
        $slots = [];
        for ($i = 0; $i < 8; $i++) {
            $slots[$i] = ['color' => $i === 2 ? $this->white->farm_color_id : null, 'remaining_g' => $i === 2 ? 900 : 0, 'enabled' => $i === 2 ? 1 : 0, 'label' => $i === 7 ? 'INDX 8' : ''];
        }
        $this->actingAs($admin)->post("/admin/farm/printers/{$this->s1->id}", $form + ['slots' => $slots])->assertSessionHasNoErrors()->assertRedirect('/admin/farm/printers');
        $this->s1->refresh();
        $this->assertSame(8, $this->s1->slots()->count());
        $this->assertSame(8, $this->s1->colorLimit());
        $this->assertSame(['ACE 1', 'ACE 2', 'ACE 3', 'ACE 4', 'ACE2 1', 'ACE2 2', 'ACE2 3', 'INDX 8'], $this->s1->slots->map(fn (FarmPrinterSlot $s) => $s->positionLabel())->all());
        $this->assertSame(8, ColorLimit::farm());
    }
}
