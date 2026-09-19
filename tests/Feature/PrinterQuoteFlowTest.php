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

    public function test_quote_from_calculation_to_acceptance(): void
    {
        Mail::fake();
        $printer = $this->printer();
        $uuid = $this->uploadCube();
        $calc = $this->actingAs($printer)->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quantity' => 2])->json('calculation');

        // create from calculation
        $this->actingAs($printer)->post(route('printer.quotes.store'), ['calculation' => $calc['token']])->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->assertSame('draft', $quote->status);
        $this->assertStringStartsWith('N-', $quote->number);
        $this->assertSame('cube.stl', $quote->title);
        $keys = array_column($quote->lines, 'key');
        $this->assertContains('material', $keys);
        $this->assertContains('time', $keys);
        $this->assertContains('setup', $keys);
        $own = collect($calc['prices'])->firstWhere('printer_profile_id', $printer->printerProfile->id);
        $this->assertEqualsWithDelta($own['total'], $quote->total, 1.0);

        // edit lines manually: the total follows the lines
        $lines = $quote->lines;
        $lines[0]['unit_price'] = 100;
        $lines[] = ['label' => 'Broušení', 'qty' => 1, 'unit_price' => 30];
        $this->actingAs($printer)->post(route('printer.quotes.update', $quote), [
            'client_name' => 'Jan Novák', 'client_email' => 'jan@example.com', 'lines' => $lines, 'valid_until' => now()->addDays(7)->toDateString(), 'lead_time_days' => 5,
        ])->assertRedirect();
        $quote->refresh();
        $this->assertSame('Jan Novák', $quote->client_name);
        $this->assertSame(Quote::sumLines($quote->lines), $quote->total);
        $this->assertSame(30.0, (float) $quote->lines[count($quote->lines) - 1]['total']);

        // send: PDF generated + mail + public page works without login
        $this->actingAs($printer)->post(route('printer.quotes.send', $quote))->assertRedirect();
        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertNotNull($quote->pdf_path);
        Storage::disk('local')->assertExists($quote->pdf_path);
        Mail::assertSent(QuoteSent::class, fn ($m) => $m->hasTo('jan@example.com'));

        $this->flushSession();
        $this->get(route('quote.public', $quote))->assertOk()->assertSee('Jan Novák')->assertSee('Tiskárna Test');
        $this->assertSame('viewed', $quote->fresh()->status);
        $this->get(route('quote.public.pdf', $quote))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->post(route('quote.accept', $quote))->assertRedirect(route('quote.public', $quote));
        $this->assertSame('accepted', $quote->fresh()->status);
        Mail::assertSent(\App\Mail\QuoteAccepted::class);

        // duplicate = repeat order
        $this->actingAs($printer)->post(route('printer.quotes.duplicate', $quote))->assertRedirect();
        $this->assertSame(2, Quote::count());
        $this->assertSame('draft', Quote::latest('id')->first()->status);
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
