<?php

namespace Tests\Feature;

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
 * The colour chooser of the order page as the customer reads it (TODO #69, docs/AL.md §20): one tile per colour with its
 * kinds of plastic inside (the state keys the colours of one tile by `group`), no spool code anywhere in his state, page
 * or mail, a colour short of filament at the end; the admin's view of the same page names the printer and the position
 * of every spool ("Kobra S1 #1 · ACE 3"); a plate with a text offers its changes by machine, the way the parts do.
 */
final class FarmColorTilesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private FarmPrinter $s1;

    private FarmPrinter $max;

    /** The seeded PLA+ white in the S1's third position (ACE 3). */
    private FarmPrinterSlot $white;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        app()->setLocale('cs');
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
        // the S1 run by an agent, free right now (its colours "start right away"); the Max by hand, switched off until a test needs a second machine
        [$agent] = FarmAgent::issue('test agent');
        $this->s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $this->s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE, 'bed_clear' => true]);
        $this->max = FarmPrinter::where('key', 'kobra-3-max-01')->firstOrFail();
        $this->max->update(['enabled' => false, 'mode' => FarmPrinter::MODE_MANUAL, 'farm_agent_id' => null]);
        $this->white = $this->s1->slots()->where('slot', 2)->firstOrFail();
        $this->white->color->update(['hex' => '#F4F4F0', 'enabled' => true]);
    }

    private function kind(string $code, string $finish = 'solid'): FarmMaterial
    {
        return tap(FarmMaterial::where('code', $code)->where('finish', $finish)->firstOrFail())->update(['enabled' => true]);
    }

    private function colour(string $name, string $hex, ?FarmMaterial $kind = null): FarmColor
    {
        return FarmColor::create(['farm_material_id' => ($kind ?? $this->white->color->material)->id, 'name' => $name, 'hex' => $hex, 'enabled' => true]);
    }

    /** A spool in position `$slot` (0-based) of a machine. */
    private function load(FarmPrinter $printer, int $slot, FarmColor $colour, float $grams = 800): FarmPrinterSlot
    {
        return tap($printer->slots()->where('slot', $slot)->firstOrFail())->update(['farm_color_id' => $colour->id, 'remaining_g' => $grams, 'enabled' => true]);
    }

    private function cube(float $size = 20.0): ModelFile
    {
        $path = sys_get_temp_dir().'/mp_tiles_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $size);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');

        return ModelFile::where('uuid', $uuid)->firstOrFail();
    }

    /** A plate with a raised text in another colour, as the sign tool stores it: one change of colour at 3 mm. */
    private function sign(string $textHex = '#101012'): ModelFile
    {
        $file = $this->cube(30.0);
        $file->forceFill(['origin' => 'tool', 'origin_ref' => 'sign', 'tool_params' => ['style' => 'emboss', 'line1' => 'Emma', 'color_changes' => [['z' => 3.0, 'hex' => $textHex]]]])->save();

        return $file;
    }

    /** A tool's design of `$n` parts, each in a colour of `$hexes` (cubes one in another, as FarmColorsPerMachineTest). */
    private function design(array $hexes): ModelFile
    {
        $file = $this->cube(20.0);
        $dir = dirname(Storage::disk(ModelFile::DISK)->path($file->storage_path)).'/parts';
        mkdir($dir, 0777, true);
        $parts = [];
        foreach (array_values($hexes) as $i => $hex) {
            MeshFixtures::cubeStl($dir.'/color_'.($i + 1).'.stl', 20.0 - $i);
            $parts['color_'.($i + 1)] = ['hex' => $hex];
        }
        $file->forceFill(['origin' => 'tool', 'origin_ref' => 'gcode', 'tool_params' => ['parts' => array_keys($parts), 'multi_material' => true, 'part_colors' => $parts]])->save();

        return $file;
    }

    private function order(ModelFile $file, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid] + $extra)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function state(FarmOrder $order, ?User $as = null): array
    {
        return $this->actingAs($as ?? $this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
    }

    private function credit(int $amount): void
    {
        $this->actingAs($this->user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->user->refresh();
    }

    /** Every spool code the state could carry, wherever it sits: the colours, their second spools, machines, positions, the order's own. */
    private static function codesIn(array $state): array
    {
        $out = [];
        foreach ($state['colors'] as $c) {
            $out = array_merge($out, (array) ($c['codes'] ?? []), [$c['code'] ?? null], array_column($c['second'], 'code'));
            foreach ($c['machines'] as $m) {
                $out = array_merge($out, [$m['code'] ?? null, $m['printer_code'] ?? null], array_column($m['positions'], 'code'), array_column($m['colors'], 'code'));
            }
        }
        $out = array_merge($out, [$state['color']['code'] ?? null], array_column($state['spools'], 'code'), array_column($state['changes'], 'slot_code'), array_column($state['parts'], 'slot_code'));

        return array_values(array_unique(array_filter($out)));
    }

    public function test_one_tile_per_colour_with_its_kinds_inside_and_the_kind_switch_lands_on_its_slot(): void
    {
        $petg = $this->kind('PETG');
        $pla = $this->kind('PLA');
        // the seeded PLA+ "bílá" in ACE 3; the same colour in PETG and PLA (its name in another case) in ACE 1 and 2; a black PLA+ in ACE 4
        $whitePetg = $this->colour('bílá', '#F6F6F2', $petg);
        $whitePla = $this->colour('Bílá', '#F2F2F0', $pla);
        $black = $this->colour('černá', '#101012');
        $this->load($this->s1, 0, $whitePetg);
        $this->load($this->s1, 1, $whitePla);
        $this->load($this->s1, 3, $black);

        $order = $this->order($this->cube());
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $state = $this->state($order);
        $colors = collect($state['colors']);
        $this->assertCount(4, $colors, 'one entry per colour and kind, as before');
        // the three whites share one tile key, the black has its own; the key is the name in lower case and the finish
        $whites = $colors->where('group', 'bílá|solid');
        $this->assertCount(3, $whites);
        $this->assertEqualsCanonicalizing(['PLA+', 'PETG', 'PLA'], $whites->pluck('kind')->all());
        $this->assertCount(3, $whites->pluck('slot')->unique());
        $this->assertSame('černá|solid', $colors->firstWhere('color_id', $black->id)['group']);
        // the tile opens on the kind the order was made with (the calculator's choice), which the state names
        $this->assertSame($order->material->label(), $state['material_kind']);
        $this->assertSame($state['material_kind'], $colors->first(fn ($c) => $c['sliced'])['kind']);

        // the kind switch inside the tile names a slot like any tile: the PETG white recalculates the order for PETG on that spool
        $petgEntry = $whites->firstWhere('kind', 'PETG');
        $this->assertFalse($petgEntry['sliced']);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'slot' => $petgEntry['slot']])->assertOk();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertSame([$petg->id, $whitePetg->id, $petgEntry['slot']], [$order->farm_material_id, $order->farm_color_id, $order->farm_printer_slot_id]);
        $after = $this->state($order);
        $this->assertSame($petgEntry['slot'], $after['slot']);
        $this->assertSame('PETG', $after['material_kind']);
        $this->assertTrue(collect($after['colors'])->firstWhere('slot', $petgEntry['slot'])['sliced']);
    }

    public function test_the_customer_gets_no_spool_codes_and_the_admin_reads_the_printer_and_the_position(): void
    {
        // the white in two machines: the S1 (ACE 3) and the Max, run by hand (its first position)
        $this->max->update(['enabled' => true]);
        $this->load($this->max, 0, $this->white->color);
        $this->s1->update(['code' => 'QZXWV']);
        $this->max->update(['code' => 'QZXWM']);
        $order = $this->order($this->cube());
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);

        // the customer: no code anywhere in the state, nor in the page
        $state = $this->state($order);
        $this->assertFalse($state['admin']);
        $this->assertSame([], self::codesIn($state));
        foreach ($state['colors'] as $c) {
            $this->assertArrayNotHasKey('codes', $c);
            $this->assertArrayNotHasKey('code', $c);
        }
        $json = (string) $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->getContent();
        $this->assertStringNotContainsString('QZXW', $json);
        $this->assertStringNotContainsString('ACE ', $json, 'a plain print names no position either');
        $html = (string) $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->getContent();
        $this->assertStringNotContainsString('QZXW', $html);
        $this->assertStringNotContainsString(__('farm.order.codes_hint'), $html, 'the sentence about the codes is gone');
        $this->assertStringContainsString('id="farm-colors"', $html);

        // the admin: the machine's name and the position, every spool of the colour, this entry's own machine first
        $his = $this->state($order, $this->admin);
        $this->assertTrue($his['admin']);
        $white = collect($his['colors'])->firstWhere('color_id', $this->white->farm_color_id);
        $this->assertSame(['Kobra S1 #1 · ACE 3', 'Kobra 3 Max #1 · ACE 1'], $white['codes']);
        $this->assertSame('Kobra S1 #1 · ACE 3', $white['code']);
        $this->assertSame(['Kobra S1 #1 · ACE 3', 'Kobra 3 Max #1 · ACE 1'], self::codesIn($his));

        // paid in white: the line under the number names the colour for the customer and the place for the admin
        $this->credit(5000);
        $paid = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $white['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $white['total']])->assertOk()->json();
        $this->assertNull($paid['color']['code']);
        $this->assertSame([null], array_column($paid['spools'], 'code'));
        $this->assertSame('bílá', $paid['spools'][0]['name']);
        $his = $this->state($order->fresh(), $this->admin);
        $this->assertSame('Kobra S1 #1 · ACE 3', $his['color']['code']);
        $this->assertSame(['Kobra S1 #1 · ACE 3'], array_column($his['spools'], 'code'));
        $this->assertStringContainsString('(QZXWV3)', $order->fresh()->spoolText(), 'the operator keeps the code');
    }

    public function test_a_colour_short_of_filament_comes_last(): void
    {
        $red = $this->colour('červená', '#C01818');
        $blue = $this->colour('modrá', '#1E4FA0');
        $this->load($this->s1, 0, $red);
        $this->load($this->s1, 1, $blue);
        // a big cube: the red spool has enough for a small print (it stays on offer) but not for this one
        $order = $this->order($this->cube(80.0));
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $need = (float) $order->est_grams * 1.05 + 5;
        $this->assertGreaterThan(55, $need, 'the print needs more than the spool will hold');
        $colors = collect($this->state($order)['colors']);
        $this->assertSame([$red->id, $blue->id, $this->white->farm_color_id], $colors->pluck('color_id')->all(), 'red first by hue while every spool has enough');

        $this->load($this->s1, 0, $red, 50.0);
        $colors = collect($this->state($order)['colors']);
        $this->assertSame([$blue->id, $this->white->farm_color_id, $red->id], $colors->pluck('color_id')->all(), 'the short one last');
        $this->assertSame([true, true, false], $colors->pluck('enough')->all());
        $this->assertNotSame($red->id, FarmPrinterSlot::find($this->state($order)['slot'])?->farm_color_id, 'never the one picked for the order');
    }

    public function test_a_plate_with_a_text_offers_its_changes_by_machine(): void
    {
        // the S1 holds white and red, the Max (by hand) white and black: the text of a white plate can be red on the one, black on the other
        $red = $this->colour('červená', '#C01818');
        $black = $this->colour('černá', '#101012');
        $this->load($this->s1, 0, $red);
        $this->max->update(['enabled' => true]);
        $this->load($this->max, 0, $this->white->color);
        $this->load($this->max, 1, $black);

        $order = $this->order($this->sign('#101012'), ['color' => $this->white->farm_color_id]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error);
        $this->assertCount(1, $order->wantedChanges());
        $state = $this->state($order);
        $this->assertSame([], self::codesIn($state));
        $white = collect($state['colors'])->firstWhere('color_id', $this->white->farm_color_id);
        // a card per machine that holds the white, each with its positions and the spools a change may take there
        $machines = collect($white['machines'])->keyBy('key');
        $this->assertEqualsCanonicalizing(['kobra-s1-01', 'kobra-3-max-01'], $machines->keys()->all());
        $this->assertSame([$red->id], array_column($machines['kobra-s1-01']['colors'], 'color_id'));
        $this->assertSame([$black->id], array_column($machines['kobra-3-max-01']['colors'], 'color_id'));
        $this->assertSame(['ACE 1', 'ACE 2', 'ACE 3', 'ACE 4'], array_column($machines['kobra-s1-01']['positions'], 'label'));
        $this->assertSame([true, false, false, false], array_column($machines['kobra-3-max-01']['positions'], 'main'));
        foreach ($machines as $m) {
            $this->assertArrayNotHasKey('code', $m);
            $this->assertArrayNotHasKey('printer_code', $m);
            $this->assertArrayNotHasKey('code', $m['positions'][0]);
            $this->assertArrayNotHasKey('code', $m['colors'][0]);
        }
        // the flat list of the second colours stays for the older page, every colour once with the white of its machine
        $this->assertEqualsCanonicalizing([$red->id, $black->id], FarmPrinterSlot::whereIn('id', array_column($white['second'], 'slot'))->pluck('farm_color_id')->all());

        // the admin: the place of every spool, the machine's code on its card
        $his = collect($this->state($order, $this->admin)['colors'])->firstWhere('color_id', $this->white->farm_color_id);
        $maxCard = collect($his['machines'])->firstWhere('key', 'kobra-3-max-01');
        $this->assertSame(['Kobra 3 Max #1 · ACE 1', $this->max->shortCode()], [$maxCard['code'], $maxCard['printer_code']]);
        $this->assertSame(['Kobra 3 Max #1 · ACE 1', 'Kobra 3 Max #1 · ACE 2', 'Kobra 3 Max #1 · ACE 3', 'Kobra 3 Max #1 · ACE 4'], array_column($maxCard['positions'], 'code'));
        $this->assertSame(['Kobra 3 Max #1 · ACE 2'], array_column($maxCard['colors'], 'code'));

        // paid the way the page sends it once a change has a spool: the white of the Max and the black beside it
        $this->credit(5000);
        $quoted = $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $maxCard['main'], 'delivery' => 'pickup', 'change_slots' => [$maxCard['colors'][0]['slot']]])->assertOk()->json('total');
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $maxCard['main'], 'change_slots' => [$maxCard['colors'][0]['slot']], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $quoted])->assertOk();
        $order->refresh();
        $this->assertSame([$this->max->id, $maxCard['main'], $maxCard['colors'][0]['slot']], [$order->farm_printer_id, $order->farm_printer_slot_id, $order->second_slot_id]);
        $this->assertSame([null, null], array_column($this->state($order)['spools'], 'code'));
        $this->assertSame(['Kobra 3 Max #1 · ACE 1', 'Kobra 3 Max #1 · ACE 2'], array_column($this->state($order, $this->admin)['spools'], 'code'));
    }

    /**
     * Not a test of behaviour: with MP_DUMP_ORDER_PAGE=<folder> it writes three order pages (one colour in four pieces, a
     * plate with a text, parts), the customer's and the admin's, their state JSON and their models into that folder, to be
     * opened in a browser next to a copy of public/build (docs/AL.md §19.4, §20).
     */
    public function test_the_order_pages_can_be_written_out_for_a_look(): void
    {
        $dir = (string) getenv('MP_DUMP_ORDER_PAGE');
        if ($dir === '' || ! is_dir($dir)) {
            $this->markTestSkipped('set MP_DUMP_ORDER_PAGE to a folder to write the pages out');
        }
        $petg = $this->kind('PETG');
        $pla = $this->kind('PLA');
        $this->load($this->s1, 0, $this->colour('bílá', '#F6F6F2', $petg));
        $this->load($this->s1, 1, $this->colour('Bílá', '#F2F2F0', $pla));
        $this->load($this->s1, 3, $this->colour('černá', '#101012'));
        // the Max by hand (its colours "join the queue"): the white again, a red, a blue with little left, a yellow in PETG
        $this->max->update(['enabled' => true]);
        $this->load($this->max, 0, $this->white->color);
        $this->load($this->max, 1, $this->colour('červená', '#C01818'));
        $this->load($this->max, 2, $this->colour('modrá', '#1E4FA0'), 50.0);
        $this->load($this->max, 3, $this->colour('žlutá', '#E0D000', $petg));
        $tokens = [];
        $write = function (string $name, FarmOrder $order) use ($dir, &$tokens): void {
            $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, $name.': '.$order->error);
            file_put_contents($dir.'/'.$name.'.html', (string) $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->getContent());
            file_put_contents($dir.'/'.$name.'-admin.html', (string) $this->actingAs($this->admin)->get("/farm/orders/{$order->token}")->assertOk()->getContent());
            file_put_contents($dir.'/'.$name.'.json', json_encode($this->state($order), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            file_put_contents($dir.'/'.$name.'-admin.json', json_encode($this->state($order, $this->admin), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($stl = $order->absolutePrintStlPath()) {
                copy($stl, $dir.'/'.$name.'.stl');
            }
            foreach ($order->partPlates() as $p) {
                if ($stl = $order->absolutePartStlPath($p)) {
                    copy($stl, $dir.'/'.$name.'-'.$p['part'].'.stl');
                }
            }
            $tokens[$order->token] = $name;
        };
        $write('one', $this->order($this->cube(40.0), ['copies' => 4]));   // four big cubes: the Max's blue (50 g) is short of filament for them
        $write('sign', $this->order($this->sign('#C01818'), ['color' => $this->white->farm_color_id]));
        $write('parts', $this->order($this->design(['#F4F4F0', '#C01818', '#101012'])));
        file_put_contents($dir.'/tokens.json', json_encode($tokens));
        $this->assertFileExists($dir.'/parts.html');
    }
}
