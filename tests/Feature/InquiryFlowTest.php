<?php

namespace Tests\Feature;

use App\Mail\CustomerInquiryVerify;
use App\Mail\CustomerNewOffer;
use App\Mail\PrinterNewInquiry;
use App\Mail\PrinterOfferAccepted;
use App\Mail\PrinterOfferLost;
use App\Models\Inquiry;
use App\Models\InquiryDispatch;
use App\Models\PricingProfile;
use App\Models\PrinterMachine;
use App\Models\PrinterMaterial;
use App\Models\PrinterProfile;
use App\Models\Quote;
use App\Models\Rating;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Customer → inquiry → dispatched by criteria → printer offers → customer accepts → chat → done → rating. */
class InquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    private function printer(string $name, array $opts = []): User
    {
        $user = User::factory()->create(['name' => $name, 'lat' => $opts['lat'] ?? null, 'lng' => $opts['lng'] ?? null, 'city' => $opts['city'] ?? null, 'country' => 'CZ']);
        $user->setRole(User::ROLE_PRINTER, true);
        $p = PrinterProfile::create(['user_id' => $user->id, 'display_name' => $name, 'slug' => PrinterProfile::makeSlug($name), 'contact_email' => strtolower($name).'@example.com', 'visible' => true, 'capacity' => $opts['capacity'] ?? 'open']);
        PricingProfile::create(['printer_profile_id' => $p->id, 'is_default' => true, 'hourly_rate' => $opts['hourly'] ?? 100, 'price_per_gram' => 5, 'setup_fee' => 50, 'lead_time_days' => 3]);
        foreach ($opts['materials'] ?? ['PLA'] as $m) {
            PrinterMaterial::create(['printer_profile_id' => $p->id, 'material_code' => $m]);
        }
        if (isset($opts['bed'])) {
            PrinterMachine::create(['printer_profile_id' => $p->id, 'name' => 'M', 'bed_x' => $opts['bed'], 'bed_y' => $opts['bed'], 'bed_z' => $opts['bed']]);
        }

        return $user->fresh();
    }

    private function calculation(): string
    {
        Storage::fake('models');
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');

        return $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quantity' => 2])->json('calculation.token');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Nominatim: Brno for postcode 60200, Prague for 11000
        Http::fake([
            'nominatim.openstreetmap.org/*' => function ($request) {
                $zip = $request['postalcode'] ?? '';
                if (str_starts_with($zip, '602')) {
                    return Http::response([['lat' => '49.1951', 'lon' => '16.6068', 'display_name' => 'Brno']]);
                }

                return Http::response([['lat' => '50.0875', 'lon' => '14.4213', 'display_name' => 'Praha']]);
            },
        ]);
    }

    public function test_guest_inquiry_needs_verification_then_dispatches_nearest_matching_printers(): void
    {
        $brno = $this->printer('Brno', ['lat' => 49.19, 'lng' => 16.61, 'city' => 'Brno']);
        $praha = $this->printer('Praha', ['lat' => 50.08, 'lng' => 14.42, 'city' => 'Praha']);
        $this->printer('Petg only', ['lat' => 49.2, 'lng' => 16.6, 'materials' => ['PETG']]);
        $this->printer('Tiny bed', ['lat' => 49.2, 'lng' => 16.6, 'bed' => 10]);
        $this->printer('Paused', ['lat' => 49.2, 'lng' => 16.6, 'capacity' => 'paused']);
        $token = $this->calculation();

        $r = $this->postJson('/api/inquiries', ['calculation' => $token, 'email' => 'zak@example.com', 'name' => 'Zákazník', 'zip' => '602 00', 'city' => 'Brno']);
        $r->assertCreated()->assertJsonPath('inquiry.needs_verification', true);
        $inquiry = Inquiry::firstOrFail();
        $this->assertSame('pending', $inquiry->status);
        $this->assertEqualsWithDelta(49.1951, $inquiry->lat, 0.001);
        $this->assertSame(0, InquiryDispatch::count());
        Mail::assertSent(CustomerInquiryVerify::class, fn ($m) => $m->hasTo('zak@example.com'));

        // wrong code does nothing, right code dispatches
        $this->get(route('inquiry.verify', [$inquiry, 'nope']));
        $this->assertSame('pending', $inquiry->fresh()->status);
        $this->get(route('inquiry.verify', [$inquiry, $inquiry->verification_code]))->assertRedirect(route('inquiry.show', $inquiry));
        $inquiry->refresh();
        $this->assertSame('open', $inquiry->status);

        $dispatches = InquiryDispatch::orderBy('rank')->get();
        $this->assertSame([$brno->printerProfile->id, $praha->printerProfile->id], $dispatches->pluck('printer_profile_id')->all());
        $this->assertLessThan(5, $dispatches[0]->distance_km);
        $this->assertGreaterThan(150, $dispatches[1]->distance_km);
        $this->assertGreaterThan(0, $dispatches[0]->auto_price);
        Mail::assertQueued(PrinterNewInquiry::class, 2);

        $this->get(route('inquiry.show', $inquiry))->assertOk()->assertSee('cube.stl');
    }

    public function test_offer_accept_chat_done_and_rating(): void
    {
        $a = $this->printer('Alfa', ['lat' => 49.19, 'lng' => 16.61]);
        $b = $this->printer('Beta', ['lat' => 49.2, 'lng' => 16.6]);
        $customer = User::factory()->create();
        $token = $this->actingAs($customer)->calculation();
        $this->actingAs($customer)->postJson('/api/inquiries', ['calculation' => $token, 'zip' => '60200'])->assertCreated()->assertJsonPath('inquiry.needs_verification', false);
        $inquiry = Inquiry::firstOrFail();
        $this->assertSame('open', $inquiry->status);
        $this->assertSame(2, InquiryDispatch::count());

        // printer A sees the inquiry, sends an offer with an adjusted price
        $this->actingAs($a)->get(route('printer.inquiries'))->assertOk()->assertSee('cube.stl');
        $this->actingAs($a)->get(route('printer.inquiries.show', $inquiry))->assertOk();
        $this->actingAs($a)->post(route('printer.inquiries.offer', $inquiry), ['total' => 333, 'lead_time_days' => 4, 'note' => 'Můžu v černé.'])->assertRedirect();
        $this->actingAs($b)->post(route('printer.inquiries.offer', $inquiry), ['total' => 400, 'lead_time_days' => 2]);
        $inquiry->refresh();
        $this->assertSame('offered', $inquiry->status);
        $this->assertSame(2, Quote::where('inquiry_id', $inquiry->id)->count());
        $offerA = Quote::where('inquiry_id', $inquiry->id)->where('printer_profile_id', $a->printerProfile->id)->firstOrFail();
        $this->assertSame(333.0, $offerA->total);
        $this->assertSame('sent', $offerA->status);
        $this->assertSame(333.0, Quote::sumLines($offerA->lines)); // lines always add up to the total
        Mail::assertQueued(CustomerNewOffer::class, 2);
        $thread = Thread::where('inquiry_id', $inquiry->id)->where('printer_profile_id', $a->printerProfile->id)->firstOrFail();
        $this->assertSame(2, $thread->messages()->count()); // system + note

        // customer page lists both offers and the chat; chat works with the inquiry token (no login)
        $this->flushSession();
        $this->get(route('inquiry.show', $inquiry))->assertOk()->assertSee('333')->assertSee('400')->assertSee('Můžu v černé.');
        $this->getJson(route('api.threads.messages', $thread).'?inquiry='.$inquiry->token)->assertOk()->assertJsonPath('side', 'customer')->assertJsonCount(2, 'messages');
        $this->getJson(route('api.threads.messages', $thread))->assertForbidden();
        $this->post(route('api.threads.post', $thread), ['inquiry' => $inquiry->token, 'body' => 'Bílá by šla?'], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($a)->getJson(route('api.threads.messages', $thread).'?since=0')->assertOk()->assertJsonPath('side', 'printer')->assertJsonCount(3, 'messages');
        $this->actingAs($a)->post(route('api.threads.post', $thread), ['body' => 'Jasně.', 'file' => UploadedFile::fake()->image('vzorek.jpg')], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message.attachment.name', 'vzorek.jpg');
        $this->actingAs($b)->getJson(route('api.threads.messages', $thread))->assertForbidden(); // other printer cannot read A's thread

        // accept A → B loses, threads get system messages, mails go out
        $this->flushSession();
        $this->post(route('inquiry.accept', [$inquiry, $offerA]))->assertRedirect();
        $inquiry->refresh();
        $this->assertSame('accepted', $inquiry->status);
        $this->assertSame($offerA->id, $inquiry->accepted_quote_id);
        $this->assertSame('declined', Quote::where('printer_profile_id', $b->printerProfile->id)->first()->status);
        Mail::assertQueued(PrinterOfferAccepted::class, 1);
        Mail::assertQueued(PrinterOfferLost::class, 1);
        $this->actingAs($a)->get(route('printer.inquiries.show', $inquiry))->assertOk()->assertSee($customer->email);

        // done + rating
        $this->flushSession();
        $this->post(route('inquiry.done', $inquiry))->assertRedirect();
        $this->post(route('inquiry.rate', $inquiry), ['score' => 5, 'comment' => 'Super'])->assertRedirect();
        $this->assertSame('done', $inquiry->fresh()->status);
        $this->assertSame(1, Rating::where('to_user_id', $a->id)->count());
        $this->assertSame(5.0, (float) $a->fresh()->rating_avg);
    }

    public function test_decline_and_cancel(): void
    {
        $a = $this->printer('Alfa', ['lat' => 49.19, 'lng' => 16.61]);
        $customer = User::factory()->create();
        $token = $this->actingAs($customer)->calculation();
        $this->actingAs($customer)->postJson('/api/inquiries', ['calculation' => $token, 'zip' => '60200'])->assertCreated();
        $inquiry = Inquiry::firstOrFail();
        $this->actingAs($a)->post(route('printer.inquiries.decline', $inquiry), ['reason' => 'plno'])->assertRedirect(route('printer.inquiries'));
        $this->assertNotNull(InquiryDispatch::first()->declined_at);
        $this->flushSession();
        $this->post(route('inquiry.cancel', $inquiry))->assertRedirect();
        $this->assertSame('cancelled', $inquiry->fresh()->status);
    }
}
