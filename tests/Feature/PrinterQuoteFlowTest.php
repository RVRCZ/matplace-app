<?php

namespace Tests\Feature;

use App\Mail\QuoteSent;
use App\Models\PricingProfile;
use App\Models\PrinterMaterial;
use App\Models\PrinterProfile;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Printer: price list → calculator with own price → quote (lines, PDF, link) → client accepts. */
class PrinterQuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function printer(array $pricing = []): User
    {
        $user = User::factory()->create();
        $user->setRole(User::ROLE_PRINTER, true);
        $profile = PrinterProfile::create(['user_id' => $user->id, 'display_name' => 'Tiskárna Test', 'slug' => PrinterProfile::makeSlug('Tiskárna Test'), 'contact_email' => 'tisk@example.com', 'visible' => true]);
        PricingProfile::create(['printer_profile_id' => $profile->id, 'name' => 'Standard', 'is_default' => true, 'hourly_rate' => 100, 'price_per_gram' => 5, 'setup_fee' => 50, 'lead_time_days' => 3] + $pricing);
        PrinterMaterial::create(['printer_profile_id' => $profile->id, 'material_code' => 'PLA']);

        return $user->fresh();
    }

    private function uploadCube(): string
    {
        Storage::fake('models');
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
    }

    public function test_profile_form_saves_five_fields_and_materials(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.roles.enable', 'printer'));
        $this->actingAs($user)->post(route('printer.profile.update'), [
            'hourly_rate' => 80, 'price_per_gram' => 3.5, 'setup_fee' => 40, 'margin_pct' => 10, 'lead_time_days' => 4,
            'materials' => ['PLA', 'PETG'], 'material_price' => ['PETG' => 4.2], 'display_name' => 'Moje tiskárna', 'capacity' => 'open',
            'qty_discounts' => '5:10, 20:20', 'machine_name' => 'Prusa MK4', 'machine_bed_x' => 250, 'machine_bed_y' => 210, 'machine_bed_z' => 220,
        ])->assertRedirect(route('printer.profile'));

        $profile = $user->fresh()->printerProfile->load(['materials', 'pricingProfiles', 'machines']);
        $this->assertSame(2, $profile->materials->count());
        $this->assertSame(4.2, (float) $profile->materials->firstWhere('material_code', 'PETG')->price_per_gram);
        $this->assertEquals([['from' => 5, 'pct' => 10], ['from' => 20, 'pct' => 20]], $profile->defaultPricing()->qty_discounts);
        $this->assertSame('Prusa MK4', $profile->machines->first()->name);
        $this->assertTrue($profile->visible);
        $this->assertSame(4.2, $profile->pricingDto('PETG')->pricePerGram);
        $this->assertSame(3.5, $profile->pricingDto('PLA')->pricePerGram);
    }

    public function test_customer_sees_real_printer_prices_and_printer_sees_own_first(): void
    {
        $printer = $this->printer();
        $uuid = $this->uploadCube();

        // anonymous customer: the real printer's price list replaces orientation profiles
        $c = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation');
        $this->assertSame('done', $c['status']);
        $this->assertCount(1, $c['prices']);
        $this->assertSame($printer->printerProfile->id, $c['prices'][0]['printer_profile_id']);
        $this->assertSame('Tiskárna Test', $c['prices'][0]['label']);
        // 3.6 g × 5 + (13 min / 60) × 100 + 50 setup ≈ 90 → rounded up to 90
        $this->assertGreaterThanOrEqual(80, $c['prices'][0]['total']);

        // material the printer does not offer → orientation profiles
        $c2 = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'ASA'])->json('calculation');
        $this->assertCount(3, $c2['prices']);
        $this->assertNull($c2['prices'][0]['printer_profile_id']);

        // the printer themself: own list first even for a second printer in range
        $this->printer(['hourly_rate' => 10]);
        $c3 = $this->actingAs($printer)->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation');
        $own = collect($c3['prices'])->firstWhere('printer_profile_id', $printer->printerProfile->id);
        $this->assertNotNull($own);
    }

    /** What a printer types into the quote form. */
    private function form(array $cost = [], array $over = []): array
    {
        return $over + [
            'client_name' => 'Jan Novák', 'client_email' => 'jan@example.com', 'title' => 'Krabička', 'material' => 'PETG', 'color' => 'černá',
            'valid_until' => now()->addDays(7)->toDateString(), 'lead_time_days' => 5, 'shipping_label' => 'Zásilkovna', 'shipping_price' => 89,
            'cost' => $cost + ['quantity' => 2, 'material_cost' => 40, 'machine_hours' => 2, 'machine_rate' => 100, 'setup_cost' => 50, 'labour_minutes' => 30, 'labour_rate' => 300, 'failure_pct' => 10, 'mode' => 'markup', 'pct' => 25, 'min_price' => 0],
        ];
    }

    public function test_quote_from_calculation_to_acceptance(): void
    {
        Mail::fake();
        $printer = $this->printer();
        $uuid = $this->uploadCube();
        $calc = $this->actingAs($printer)->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quantity' => 2])->json('calculation');

        // create from calculation: the cost sheet is filled from the price list and gives the calculator's price
        $this->actingAs($printer)->post(route('printer.quotes.store'), ['calculation' => $calc['token']])->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->assertSame('draft', $quote->status);
        $this->assertStringStartsWith('N-', $quote->number);
        $this->assertSame('cube.stl', $quote->title);
        $this->assertSame(32, strlen($quote->token));                       // hard to guess
        $this->assertSame(['production'], array_column($quote->lines, 'key'));
        $this->assertGreaterThan(0, $quote->cost['material_cost']);
        $this->assertSame(100.0, (float) $quote->cost['machine_rate']);
        $own = collect($calc['prices'])->firstWhere('printer_profile_id', $printer->printerProfile->id);
        $this->assertEqualsWithDelta($own['total'], $quote->total, 1.0);
        $this->actingAs($printer)->get(route('printer.quotes.edit', $quote))->assertOk()->assertSee($quote->number);
        $this->actingAs($printer)->get(route('printer.quotes'))->assertOk();
        $this->actingAs($printer)->get(route('printer.dashboard'))->assertOk();
        $this->actingAs($printer)->get(route('printer.calculator'))->assertOk();

        // cost sheet: (40 + 2×100) × 1.10 + 50 + 150 = 464 → 25 % markup = 580; + extra item 30 + shipping 89
        $this->actingAs($printer)->post(route('printer.quotes.update', $quote), $this->form([], ['lines' => [['label' => 'Broušení', 'qty' => 1, 'unit_price' => 30]]]))->assertRedirect()->assertSessionHasNoErrors();
        $quote->refresh();
        $this->assertSame('Jan Novák', $quote->client_name);
        $this->assertEqualsWithDelta(464.0, $quote->cost['cost'], 0.01);
        $this->assertEqualsWithDelta(580.0, $quote->cost['final'], 0.01);
        $this->assertEqualsWithDelta(20.0, $quote->cost['margin_pct'], 0.05);   // the same price said as a margin
        $this->assertSame(580.0 + 30 + 89, $quote->total);

        // send: version 1 frozen, PDF + mail, public page works without login
        $this->actingAs($printer)->post(route('printer.quotes.send', $quote))->assertRedirect();
        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertSame(1, $quote->version);
        $this->assertSame(699.0, (float) $quote->versions()->first()->snapshot['total']);
        Storage::disk('local')->assertExists($quote->pdf_path);
        Mail::assertSent(QuoteSent::class, fn ($m) => $m->hasTo('jan@example.com'));

        $this->flushSession();
        auth()->logout();
        $page = $this->get(route('quote.public', $quote))->assertOk()->assertSee('Jan Novák')->assertSee('Tiskárna Test')
            ->assertSee('PETG')->assertSee('černá')->assertSee('Zásilkovna')->assertSee('Broušení')->assertSee('699');
        // nothing of the cost sheet leaks: no cost, no margin, no machine rate, no reserve
        foreach (['464', __('quote.cost.margin'), __('quote.cost.markup'), __('quote.sheet.cost'), __('quote.cost.failure_pct'), __('quote.line.time')] as $secret) {
            $page->assertDontSee($secret);
        }
        $this->assertArrayNotHasKey('cost', $quote->fresh()->toArray());
        $this->assertSame('viewed', $quote->fresh()->status);
        $this->get(route('quote.public.pdf', $quote))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // the customer asks for a change → printer edits and re-sends → version 2; the old version can no longer be accepted
        $this->post(route('quote.change', $quote), ['version' => 1, 'message' => 'Šlo by to v bílé?'])->assertRedirect();
        $this->assertSame('change', $quote->fresh()->status);
        Mail::assertSent(\App\Mail\QuoteChangeRequested::class);
        $this->post(route('quote.accept', $quote), ['version' => 1])->assertNotFound();      // not open while a change is pending

        $this->actingAs($printer)->post(route('printer.quotes.update', $quote), $this->form([], ['color' => 'bílá']))->assertRedirect();
        $this->actingAs($printer)->post(route('printer.quotes.send', $quote))->assertRedirect();
        $quote->refresh();
        $this->assertSame(2, $quote->version);
        $this->assertSame(669.0, $quote->total);
        auth()->logout();
        $this->post(route('quote.accept', $quote), ['version' => 1])->assertRedirect()->assertSessionHasErrors('version');
        $this->assertSame('sent', $quote->fresh()->status);
        $this->post(route('quote.accept', $quote), ['version' => 2])->assertRedirect(route('quote.public', $quote));
        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame(2, $quote->accepted_version);
        $this->assertNotNull($quote->versions()->where('version', 2)->first()->accepted_at);
        $this->assertNull($quote->versions()->where('version', 1)->first()->accepted_at);
        Mail::assertSent(\App\Mail\QuoteAccepted::class);

        // an accepted quote is frozen; "repeat" makes a new draft with a new link
        $this->actingAs($printer)->post(route('printer.quotes.update', $quote), $this->form())->assertStatus(409);
        $this->actingAs($printer)->post(route('printer.quotes.duplicate', $quote))->assertRedirect();
        $copy = Quote::latest('id')->first();
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($quote->token, $copy->token);
    }

    public function test_link_can_be_revoked_and_replaced(): void
    {
        Mail::fake();
        $printer = $this->printer();
        $this->actingAs($printer)->post(route('printer.quotes.store'));
        $quote = Quote::firstOrFail();
        $this->actingAs($printer)->post(route('printer.quotes.update', $quote), $this->form(['final_price' => 500], ['client_email' => null]));
        $this->actingAs($printer)->post(route('printer.quotes.send', $quote))->assertRedirect();
        $old = $quote->fresh()->token;
        $this->assertSame(500.0 + 89, $quote->fresh()->total);                 // the printer's own price wins over the suggestion
        $this->assertTrue($quote->fresh()->cost['overridden']);

        $this->actingAs($printer)->post(route('printer.quotes.revoke', $quote))->assertRedirect();
        auth()->logout();
        $this->get('/q/'.$old)->assertStatus(410);
        $this->get('/q/'.$old.'/pdf')->assertStatus(410);
        $this->post('/q/'.$old.'/accept', ['version' => 1])->assertStatus(410);

        $this->actingAs($printer)->post(route('printer.quotes.relink', $quote))->assertRedirect();
        $new = $quote->fresh()->token;
        $this->assertNotSame($old, $new);
        auth()->logout();
        $this->get('/q/'.$old)->assertNotFound();                               // the old address is gone for good
        $this->get('/q/'.$new)->assertOk();
        $this->get('/q/'.substr($new, 0, 31).'x')->assertNotFound();
    }

    public function test_other_printer_cannot_open_my_quote(): void
    {
        $a = $this->printer();
        $b = User::factory()->create();
        $b->setRole(User::ROLE_PRINTER, true);
        PrinterProfile::create(['user_id' => $b->id, 'display_name' => 'B', 'slug' => 'b']);
        $this->actingAs($a)->post(route('printer.quotes.store'));
        $quote = Quote::firstOrFail();
        $this->actingAs($b->fresh())->get(route('printer.quotes.edit', $quote))->assertForbidden();
        $this->get(route('quote.public', $quote))->assertNotFound(); // drafts are not public
    }
}
