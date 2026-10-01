<?php

namespace Tests\Feature;

use App\Domain\Farm\CarrierBook;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\Shipping;
use App\Domain\Farm\Wallet;
use App\Engines\Shipping\FakeCarrier;
use App\Engines\Shipping\Parcel;
use App\Mail\FarmOrderStatus;
use App\Models\CreditTransaction;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Currency;
use App\Support\CurrencyMismatch;
use App\Support\Money;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Step D: crowns for Czechia and euros for everybody else, and parcels by Packeta to a pickup point or to the door.
 * Fake carrier, fake gateway, fake slicer; nothing leaves the machine.
 */
class ShippingCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const NB = "\u{00A0}";

    /** The currency the layout hands to the scripts of the page (window.MP_MONEY). */
    private static function shown(string $currency): string
    {
        // Js::from() writes the quotes of the JSON as escapes: a backslash, "u0022"
        $quote = '\\'.'u0022';

        return "MP_MONEY = JSON.parse('{{$quote}currency{$quote}:{$quote}{$currency}{$quote}";
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        FakeCarrier::reset();
        $this->seed(FarmSeeder::class);
        // the weekly download of who carries where (the fake knows Spain, France, Germany…)
        $this->artisan('matplace:packeta-carriers')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        // other tests run without a downloaded list: only the carriers written in the config
        @unlink(CarrierBook::path());
        parent::tearDown();
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email' => 'zakaznik'.uniqid().'@example.com']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin']);

        return $admin;
    }

    private function order(User $user, string $prefix = ''): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_ship_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($user)->postJson($prefix.'/farm/orders', ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function topUp(User $user, int $amount, string $prefix = ''): Payment
    {
        $this->actingAs($user)->post($prefix.'/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();

        return $payment->refresh();
    }

    private function finish(FarmOrder $order): FarmOrder
    {
        $flow = app(OrderFlow::class);
        $flow->move($order->refresh(), FarmOrder::STATUS_PRINTING, 'admin');
        $flow->move($order, FarmOrder::STATUS_DONE, 'admin');

        return $order->refresh();
    }

    public function test_money_converts_at_the_fixed_rate_and_rounds_euros_up_to_ten_cents(): void
    {
        $this->assertSame(25.0, Money::rate());
        $this->assertSame([10.0, 'EUR'], [Money::czk(250)->to('EUR')->amount, Money::czk(250)->to('EUR')->currency]);
        $this->assertSame(10.1, Money::czk(251)->to('EUR')->amount, 'never below the crown price');
        $this->assertSame(0.6, Money::czk(15)->to('EUR')->amount);
        $this->assertSame(4.0, Money::czk(99)->to('EUR')->amount);
        $this->assertSame(-10.1, Money::czk(-251)->to('EUR')->amount, 'a refund mirrors the charge');
        $this->assertSame(250.0, Money::eur(9.99)->to('CZK')->amount, 'euros to crowns: up to a whole crown');
        $this->assertSame(1.0, Money::czk(25)->exactly('EUR')->amount);
        $this->assertSame(0.68, Money::czk(17)->exactly('EUR')->amount, 'the plain rate, to the cent');
        $this->assertSame(99.0, Money::czk(99)->to('CZK')->amount);

        config(['farm.eur_rate' => 24.5]);
        $this->assertSame(4.1, Money::czk(99)->to('EUR')->amount);

        $this->assertSame(350.5, Money::czk(100.25)->plus(Money::czk(250.25))->amount);
        $this->assertTrue(Money::eur(5)->covers(Money::eur(5)));
        $this->assertFalse(Money::eur(4.99)->covers(Money::eur(5)));
        $this->expectException(CurrencyMismatch::class);
        Money::czk(1)->plus(Money::eur(1));
    }

    public function test_money_is_written_the_way_each_language_writes_it(): void
    {
        $nb = self::NB;
        $this->assertSame("1{$nb}250{$nb}Kč", Money::czk(1250)->format('cs'));
        $this->assertSame("49,90{$nb}€", Money::eur(49.9)->format('cs'));
        $this->assertSame("1,250{$nb}Kč", Money::czk(1250)->format('en'));
        $this->assertSame('€49.90', Money::eur(49.9)->format('en'));
        $this->assertSame("1.250{$nb}Kč", Money::czk(1250)->format('es'));
        $this->assertSame("49,90{$nb}€", Money::eur(49.9)->format('es'));
        // crowns show hellers only when there are some; euros always show cents
        $this->assertSame("123,45{$nb}Kč", Money::czk(123.45)->format('cs'));
        $this->assertSame("5,00{$nb}€", Money::eur(5)->format('cs'));
        $this->assertSame("\u{2212}25{$nb}Kč", Money::czk(-25)->format('cs'));
        $this->assertSame("+25{$nb}Kč", Money::czk(25)->format('cs', true));
        $this->assertSame(['amount' => 5.9, 'currency' => 'EUR', 'text' => "5,90{$nb}€"], Money::eur(5.9)->jsonSerialize());

        // the Blade directive: a Money as it is, a bare number as a price in crowns shown in the visitor's currency
        $this->assertSame("99{$nb}Kč", Blade::render('@money(99)'));
        $this->assertSame("5,90{$nb}€", Blade::render('@money(5.9, "EUR")'));
        $this->assertSame("1{$nb}250{$nb}Kč", Blade::render('@money($total)', ['total' => Money::czk(1250)]));
        app()->setLocale('en');
        $this->assertSame('€4.00', Blade::render('@money(99)'), 'English page, nobody signed in: euros');
    }

    public function test_the_currency_follows_the_account_then_the_switch_then_the_country_then_the_language(): void
    {
        // nobody signed in: the language of the address decides
        $this->get('/')->assertOk()->assertSee(self::shown('CZK'), false)->assertSee('name="currency" value="EUR"', false);
        $this->get('/en')->assertOk()->assertSee(self::shown('EUR'), false);
        $this->get('/es')->assertOk()->assertSee(self::shown('EUR'), false);

        // the switch in the header wins over the language
        $this->from('/')->post('/currency', ['currency' => 'EUR'])->assertRedirect('/')->assertCookie(Currency::COOKIE, 'EUR');
        $this->withCookie(Currency::COOKIE, 'EUR')->get('/')->assertSee(self::shown('EUR'), false);
        $this->withCookie(Currency::COOKIE, 'CZK')->get('/en')->assertSee(self::shown('CZK'), false);
        $this->post('/currency', ['currency' => 'USD'])->assertSessionHasErrors('currency');

        // the country things are delivered to, when the account knows it, comes before the language (a browser without the switch's cookie)
        $this->defaultCookies = [];
        $spaniard = $this->customer(['country' => 'ES']);
        $this->actingAs($spaniard)->get('/account/credit')->assertOk()->assertSee(self::shown('EUR'), false)->assertSee('10,00'.self::NB.'€');
        $this->assertSame('EUR', Currency::current($spaniard));
        $this->assertSame('CZK', Currency::current($this->customer(['country' => 'CZ'])));

        // an account money has moved on keeps its currency whatever the page, the switch or the country say
        $czech = $this->customer(['country' => 'ES']);
        CreditTransaction::create(['user_id' => $czech->id, 'type' => 'adjust', 'amount' => 100, 'currency' => 'CZK']);
        $this->assertSame('CZK', $czech->fresh()->currency);
        $this->actingAs($czech->fresh())->withCookie(Currency::COOKIE, 'EUR')->get('/en/account/credit')->assertOk()
            ->assertSee(self::shown('CZK'), false)->assertSee('100'.self::NB.'Kč')->assertDontSee('name="currency" value="EUR"', false);
    }

    public function test_the_first_payment_fixes_the_currency_of_the_account(): void
    {
        $user = $this->customer(['country' => 'DE']);
        $this->assertNull($user->currency);
        $page = $this->actingAs($user)->get('/en/account/credit')->assertOk();
        $page->assertSee('€10.00')->assertSee('€50.00')->assertSee(__('farm.credit.currency_note', ['currency' => '€'], 'en'))->assertSee('min="5"', false);

        // the smallest top-up in euros is 5
        $this->actingAs($user)->post('/en/account/credit', ['amount' => 4])->assertSessionHasErrors('amount');
        $payment = $this->topUp($user, 20, '/en');
        $this->assertSame(['EUR', 20.0, Payment::STATUS_PAID], [$payment->currency, $payment->amount, $payment->status]);
        $user->refresh();
        $this->assertSame('EUR', $user->currency);
        $balance = app(Wallet::class)->balance($user);
        $this->assertSame([20.0, 'EUR'], [$balance->amount, $balance->currency]);

        // from now on everything is in euros, in any language; the switch and the note are gone
        $this->actingAs($user)->get('/account/credit')->assertOk()->assertSee('20,00'.self::NB.'€')
            ->assertDontSee('name="currency" value="CZK"', false)->assertDontSee(__('farm.credit.currency_note', ['currency' => '€']));

        // a line in another currency never reaches the ledger
        try {
            CreditTransaction::create(['user_id' => $user->id, 'type' => 'adjust', 'amount' => 100, 'currency' => 'CZK']);
            $this->fail('A crown line was written to a euro account.');
        } catch (CurrencyMismatch) {
            $this->assertSame(1, CreditTransaction::where('user_id', $user->id)->count());
        }

        // a second checkout that was opened in crowns and paid later: the money is not credited, the admin is told
        $stray = Payment::create(['user_id' => $user->id, 'gateway' => 'fake', 'gateway_ref' => 'fake_stray', 'amount' => 500, 'currency' => 'CZK', 'status' => Payment::STATUS_PENDING]);
        $this->postJson('/webhooks/payments/fake', ['ref' => $stray->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->assertSame(20.0, app(Wallet::class)->balance($user)->amount);

        // only an admin changes the currency, and only of an account that holds nothing
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/farm/credit/currency', ['user_id' => $user->id, 'currency' => 'CZK'])->assertSessionHas('error');
        $this->assertSame('EUR', $user->fresh()->currency);
        app(Wallet::class)->adjust($user, -20, 'spent', $admin->id);
        $this->actingAs($admin)->post('/admin/farm/credit/currency', ['user_id' => $user->id, 'currency' => 'CZK'])->assertSessionHas('status');
        $this->assertSame('CZK', $user->fresh()->currency);
    }

    public function test_a_parcel_costs_by_country_kind_and_weight_and_goes_only_where_somebody_carries_it(): void
    {
        $shipping = app(Shipping::class);
        $price = fn (string $delivery, string $country, int $grams, string $currency) => $shipping->price($delivery, $country, $grams, $currency)?->amount;

        $this->assertSame(0.0, $price(Shipping::PICKUP, 'CZ', 500, 'CZK'));
        $this->assertSame([99.0, 149.0], [$price(Shipping::POINT, 'CZ', 500, 'CZK'), $price(Shipping::HOME, 'CZ', 500, 'CZK')]);
        $this->assertSame([5.9, 6.9], [$price(Shipping::POINT, 'SK', 500, 'EUR'), $price(Shipping::HOME, 'SK', 500, 'EUR')]);
        $this->assertSame([9.9, 11.9], [$price(Shipping::POINT, 'ES', 500, 'EUR'), $price(Shipping::HOME, 'ES', 500, 'EUR')]);
        // an account in crowns pays the crown column wherever the parcel goes
        $this->assertSame(249.0, $price(Shipping::POINT, 'ES', 500, 'CZK'));

        // weight bands: up to 2 kg the base, up to 5 kg a surcharge, heavier only inside Czechia
        $this->assertSame(9.9, $price(Shipping::POINT, 'ES', 2000, 'EUR'));
        $this->assertSame(11.9, $price(Shipping::POINT, 'ES', 2050, 'EUR'));
        $this->assertNull($price(Shipping::POINT, 'ES', 5050, 'EUR'));
        $this->assertSame([149.0, 199.0], [$price(Shipping::POINT, 'CZ', 5000, 'CZK'), $price(Shipping::POINT, 'CZ', 5050, 'CZK')]);
        $this->assertNull($price(Shipping::HOME, 'CZ', 15050, 'CZK'));

        // Austria has no pickup points with us, the USA are outside the EU, nobody is known to carry to the Netherlands
        $this->assertSame([null, 11.9], [$price(Shipping::POINT, 'AT', 500, 'EUR'), $price(Shipping::HOME, 'AT', 500, 'EUR')]);
        $this->assertNull($price(Shipping::HOME, 'US', 500, 'EUR'));
        $this->assertNull($price(Shipping::HOME, 'NL', 500, 'EUR'));
        // a carrier written into the config opens the country
        config(['farm.packeta_home_carriers' => config('farm.packeta_home_carriers') + ['NL' => 777]]);
        $this->assertSame(22.9, $price(Shipping::HOME, 'NL', 500, 'EUR'));

        // who carries: what the config names wins, else the preferred name in the downloaded list
        $book = app(CarrierBook::class);
        $this->assertSame([106, 9001, 9011], [$book->home('CZ'), $book->home('ES'), $book->home('FR')], 'Spain: Correos, not MRW, to the door');
        $this->assertSame([[9003], [9012], []], [$book->points('ES'), $book->points('FR'), $book->points('NL')], 'Spain: MRW pickup points');
        $this->assertSame([['country' => 'cz'], ['country' => 'cz', 'group' => 'zbox']], $shipping->vendors('CZ'));
        $this->assertSame([['carrierId' => '9003']], $shipping->vendors('ES'));
        $this->artisan('matplace:packeta-carriers --suggest')->expectsOutputToContain("'ES' => 9001")->assertSuccessful();

        // the destination VAT applies once config says so (OSS); until then the farm's own rate everywhere
        $this->assertSame(21.0, $shipping->vatPercent('ES'));
        config(['farm.vat_rate.EU2' => 19.0, 'farm.vat_rate.ES' => 21.5]);
        $this->assertSame([21.5, 19.0, 21.0], [$shipping->vatPercent('ES'), $shipping->vatPercent('DE'), $shipping->vatPercent('CZ')]);
    }

    public function test_a_parcel_is_addressed_from_the_order_alone(): void
    {
        $this->assertSame(['Jan Amos', 'Komenský'], Parcel::splitName('Jan Amos Komenský'));
        $this->assertSame(['Cher', 'Cher'], Parcel::splitName('Cher'));
        $this->assertSame(['Dlouhá', '12/3a'], Parcel::splitStreet('Dlouhá 12/3a'));
        $this->assertSame(['Calle Mayor', '5'], Parcel::splitStreet('Calle Mayor, 5'));
        $this->assertSame(['Rue de la Paix', '12'], Parcel::splitStreet('12 Rue de la Paix'));
        $this->assertSame(['Náves', '-'], Parcel::splitStreet('Náves'));
    }

    public function test_delivery_is_chosen_before_paying_and_the_pickup_point_must_lie_in_the_chosen_country(): void
    {
        $user = $this->customer(['country' => 'CZ', 'phone' => '+420 777 000 111', 'pickup_point' => ['id' => '95', 'name' => 'Z-BOX Plzeň, Dlouhá 1', 'carrier_id' => null, 'country' => 'CZ']]);
        $this->topUp($user, 1000);
        $order = $this->order($user);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $slot = $state['colors'][0]['slot'];
        $print = (float) $state['colors'][0]['total'];

        // the page is told what is on offer: the weight of the parcel and, per country, the price of each kind
        $this->assertSame('CZK', $state['currency']);
        $this->assertSame(['packeta_point', 'packeta_home', 'pickup'], $state['shipping']['modes']);
        $this->assertSame(0, $state['shipping']['weight_g'] % 50);
        $this->assertGreaterThanOrEqual(100, $state['shipping']['weight_g'], 'the print plus the box');
        $this->assertSame(['name' => 'Česko', 'point' => 99, 'home' => 149], array_intersect_key($state['shipping']['countries']['CZ'], array_flip(['name', 'point', 'home'])));
        $this->assertSame([null, 299], [$state['shipping']['countries']['AT']['point'], $state['shipping']['countries']['AT']['home']]);
        $this->assertArrayNotHasKey('US', $state['shipping']['countries']);
        $this->assertArrayNotHasKey('NL', $state['shipping']['countries'], 'nobody carries there yet');
        // the order page carries the picker and the favourite point of the profile
        $this->actingAs($user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee('data-pickup-box', false)->assertSee('Z-BOX Plzeň, Dlouhá 1')->assertSee(__('farm.delivery.packeta_home'));

        // the price with a parcel is asked from the server
        $pay = fn (array $over) => $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", $over + ['slot' => $slot, 'terms' => true, 'expected_total' => $print + 99]);
        $quote = $this->actingAs($user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $slot, 'delivery' => 'packeta_point', 'country' => 'CZ'])->assertOk()->json();
        $this->assertSame([$print + 99, 99, 'CZK'], [(float) $quote['total'], $quote['price']['shipping'], $quote['currency']]);
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $slot, 'delivery' => 'packeta_point', 'country' => 'AT'])->assertStatus(422)->assertJsonPath('error', 'delivery_country');

        // a point of another country, an unknown carrier, no point at all, a point the carrier does not know: refused, nothing charged
        $point = ['id' => '95', 'name' => 'Z-BOX Plzeň, Dlouhá 1', 'carrier_id' => '', 'country' => 'CZ'];
        $pay(['delivery' => 'packeta_point', 'address' => ['name' => 'Roman Vzor', 'country' => 'SK', 'point' => $point], 'expected_total' => $print + 149])->assertStatus(422)->assertJsonPath('error', 'delivery_point');
        $pay(['delivery' => 'packeta_point', 'address' => ['name' => 'Roman Vzor', 'country' => 'CZ', 'point' => ['carrier_id' => '9003'] + $point]])->assertStatus(422)->assertJsonPath('error', 'delivery_point');
        $pay(['delivery' => 'packeta_point', 'address' => ['name' => 'Roman Vzor', 'country' => 'CZ']])->assertStatus(422)->assertJsonPath('error', 'delivery_point');
        FakeCarrier::$unknownPoints = ['95'];
        $pay(['delivery' => 'packeta_point', 'address' => ['name' => 'Roman Vzor', 'country' => 'CZ', 'point' => $point]])->assertStatus(422)->assertJsonPath('error', 'delivery_point');
        FakeCarrier::$unknownPoints = [];
        // to the door: the whole address and a phone, in a country we deliver to
        $pay(['delivery' => 'packeta_home', 'address' => ['name' => 'Roman Vzor', 'country' => 'CZ', 'street' => 'Dlouhá 1', 'city' => 'Plzeň', 'zip' => '301 00'], 'expected_total' => $print + 149])->assertStatus(422)->assertJsonPath('error', 'delivery_address');
        $pay(['delivery' => 'packeta_home', 'address' => ['name' => 'Roman Vzor', 'phone' => '777', 'country' => 'US', 'street' => '1 Main St', 'city' => 'Springfield', 'zip' => '12345']])->assertStatus(422)->assertJsonPath('error', 'delivery_country');
        // the price the customer saw was without the parcel: never charged silently
        $pay(['delivery' => 'packeta_point', 'address' => ['name' => 'Roman Vzor', 'country' => 'CZ', 'point' => $point], 'expected_total' => $print])->assertStatus(422)->assertJsonPath('error', 'price_changed');
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->fresh()->status);
        $this->assertSame(1000.0, app(Wallet::class)->balance($user)->amount);

        // the right point: paid with the parcel in the price
        $pay(['delivery' => 'packeta_point', 'address' => ['name' => 'Roman Vzor', 'phone' => '+420 777 000 111', 'country' => 'CZ', 'point' => $point]])->assertOk()->assertJsonPath('destination', 'Z-BOX Plzeň, Dlouhá 1, Česko');
        $order->refresh();
        $this->assertSame(['packeta_point', 99.0, $print + 99, 'CZK'], [$order->delivery, $order->shipping_price, $order->price_total, $order->currency]);
        $this->assertSame(['name' => 'Roman Vzor', 'phone' => '+420 777 000 111', 'country' => 'CZ', 'pickup_point_id' => '95', 'pickup_point_name' => 'Z-BOX Plzeň, Dlouhá 1', 'carrier_id' => null], $order->shipping_address);
        $this->assertSame(1000.0 - $print - 99, app(Wallet::class)->balance($user)->amount);

        // the admin can switch parcels off: only pickup in person is left
        app(FarmSettings::class)->set('delivery_modes', ['pickup']);
        $second = $this->order($user);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$second->token}/status")->json();
        $this->assertSame([], $state['shipping']['countries']);
        $this->actingAs($user)->postJson("/farm/orders/{$second->token}/pay", ['slot' => $slot, 'terms' => true, 'delivery' => 'packeta_point', 'address' => ['name' => 'Roman Vzor', 'country' => 'CZ', 'point' => $point], 'expected_total' => $print + 99])
            ->assertStatus(422)->assertJsonPath('error', 'delivery');
    }

    public function test_pickup_in_person_is_not_offered_unless_the_admin_switches_it_on(): void
    {
        // the site as it starts (the test suite itself runs with pickup switched on, see Tests\TestCase)
        $default = (require config_path('farm.php'))['settings']['delivery_modes'];
        $this->assertSame(['packeta_point', 'packeta_home'], $default);
        config(['farm.settings.delivery_modes' => $default]);

        $user = $this->customer(['country' => 'CZ', 'phone' => '+420 777 000 111']);
        $this->topUp($user, 1000);
        $order = $this->order($user);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $slot = $state['colors'][0]['slot'];
        $print = (float) $state['colors'][0]['total'];
        $this->assertSame(['packeta_point', 'packeta_home'], $state['shipping']['modes']);
        $this->assertFalse(app(Shipping::class)->pickup());

        // neither a price nor an order with pickup: the server says so, whatever the page sent
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/quote", ['slot' => $slot, 'delivery' => 'pickup'])->assertStatus(422);
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $slot, 'terms' => true, 'delivery' => 'pickup', 'expected_total' => $print])
            ->assertStatus(422)->assertJsonPath('error', 'delivery')->assertJsonPath('message', __('farm.refuse.delivery'));
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->fresh()->status);
        $this->assertSame(1000.0, app(Wallet::class)->balance($user)->amount);

        // a parcel goes through, and its finished print is "being packed", not "waiting for handover"
        $point = ['id' => '95', 'name' => 'Z-BOX Plzeň, Dlouhá 1', 'carrier_id' => null, 'country' => 'CZ'];
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $slot, 'terms' => true, 'delivery' => 'packeta_point',
            'address' => ['name' => 'Roman Vzor', 'country' => 'CZ', 'point' => $point], 'expected_total' => $print + 99])->assertOk();
        $done = $this->finish($order);
        $this->assertSame(__('farm.status.done_parcel'), $done->statusText());
        $this->assertSame('Hotovo, chystáme k odeslání', $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->json('status_text'));
        $mail = new FarmOrderStatus($done, FarmOrder::STATUS_DONE);
        $mail->assertSeeInText('Zabalíme ho a předáme dopravci');
        $mail->assertDontSeeInText('čeká na převzetí');
        // … and once the carrier has it, the mail says so in its text, not only in its subject
        $done->forceFill(['tracking_url' => 'https://tracking.packeta.com/cs/?id=Z123', 'packeta_barcode' => 'Z123']);
        (new FarmOrderStatus($done, FarmOrder::STATUS_HANDED_OVER))->assertSeeInText('jsme předali dopravci')->assertSeeInText('Z123');
        $done->refresh();

        // a print too big for a parcel cannot be ordered at all, and the refusal says why
        $big = $this->order($user);
        $big->forceFill(['check' => ['piece_dims' => ['x' => 800, 'y' => 100, 'z' => 100]] + (array) $big->check])->save();
        $state = $this->actingAs($user)->getJson("/farm/orders/{$big->token}/status")->json();
        $this->assertSame([true, []], [$state['shipping']['too_big'], $state['shipping']['countries']]);
        $this->actingAs($user)->postJson("/farm/orders/{$big->token}/pay", ['slot' => $state['colors'][0]['slot'], 'terms' => true, 'delivery' => 'packeta_home',
            'address' => ['name' => 'Roman Vzor', 'phone' => '777', 'country' => 'CZ', 'street' => 'Dlouhá 1', 'city' => 'Plzeň', 'zip' => '30100'], 'expected_total' => 1])
            ->assertStatus(422)->assertJsonPath('error', 'delivery_too_big')->assertJsonPath('message', __('farm.refuse.delivery_too_big'));

        // the admin ticks pickup in the farm's settings once there is a place: it is offered again and free
        app(FarmSettings::class)->set('delivery_modes', ['packeta_point', 'pickup']);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$big->token}/status")->json();
        $this->assertSame(['packeta_point', 'pickup'], $state['shipping']['modes']);
        $this->actingAs($user->fresh())->postJson("/farm/orders/{$big->token}/pay", ['slot' => $state['colors'][0]['slot'], 'terms' => true, 'delivery' => 'pickup', 'expected_total' => $state['colors'][0]['total']])->assertOk();
        $this->assertSame(__('farm.status.done'), $this->finish($big)->statusText());

        // the stored choice of an admin from before loses pickup with the migration, and never ends up empty
        $migration = require database_path('migrations/2026_10_08_100000_no_pickup_in_person.php');
        app(FarmSettings::class)->set('delivery_modes', ['pickup', 'packeta_home']);
        $migration->up();
        $this->assertSame(['packeta_home'], (new FarmSettings)->get('delivery_modes'));
        app(FarmSettings::class)->set('delivery_modes', ['pickup']);
        $migration->up();
        $this->assertSame(['packeta_point', 'packeta_home'], (new FarmSettings)->get('delivery_modes'));

        // the public pages do not promise what the farm does not do
        foreach (['', '/en', '/es'] as $prefix) {
            foreach (['/about', '/contact', '/faq', '/business-terms', '/tools/box'] as $page) {
                $html = $this->get($prefix.$page)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/vyzvednete|osobní odběr je zdarma|pick it up in person|collect the finished|recoge en persona|recogida en persona es gratuita/iu', $html, $prefix.$page);
            }
        }
    }

    public function test_a_spanish_account_pays_in_euros_and_gets_the_parcel_to_a_pickup_point_in_spain(): void
    {
        $user = $this->customer(['name' => 'María García', 'country' => 'ES', 'locale' => 'es', 'phone' => '+34 600 000 000']);
        $order = $this->order($user, '/es');
        $this->assertSame('EUR', $order->currency, 'priced in euros from the start');
        $state = $this->actingAs($user)->getJson("/es/farm/orders/{$order->token}/status")->assertOk()->json();
        $slot = $state['colors'][0]['slot'];
        $print = (float) $state['colors'][0]['total'];
        $this->assertSame('EUR', $state['currency']);
        $this->assertSame('EUR', $state['colors'][0]['price']['currency']);
        // euros rounded up to ten cents, from the crown price at 25 Kč per euro
        $czk = (float) $state['colors'][0]['price']['czk']['print_total'];
        $this->assertSame(Money::czk($czk)->to('EUR')->amount, $print);
        $this->assertSame(round($print * 10), $print * 10);
        $this->assertSame(['point' => 9.9, 'home' => 11.9], array_intersect_key($state['shipping']['countries']['ES'], array_flip(['point', 'home'])));
        $this->assertSame([['carrierId' => '9003']], $state['shipping']['countries']['ES']['vendors']);

        // no credit yet: the answer names what is missing in euros and leads to a top-up in euros
        $point = ['id' => 'MRW-0412', 'name' => 'MRW Valencia Centro, Calle Mayor 5, 46001 Valencia', 'carrier_id' => '9003', 'country' => 'ES'];
        $body = ['slot' => $slot, 'terms' => true, 'delivery' => 'packeta_point', 'address' => ['name' => 'María García', 'phone' => '+34 600 000 000', 'country' => 'ES', 'point' => $point], 'expected_total' => round($print + 9.9, 2)];
        $short = $this->actingAs($user)->postJson("/es/farm/orders/{$order->token}/pay", $body)->assertStatus(402)->assertJsonPath('error', 'credit')->json();
        $this->assertStringContainsString('€', $short['message']);
        $this->assertStringContainsString('/es/account/credit', $short['topup_url']);
        $this->topUp($user, 50, '/es');
        $this->assertSame('EUR', $user->fresh()->currency);

        $this->actingAs($user->fresh())->postJson("/es/farm/orders/{$order->token}/pay", $body)->assertOk();
        $order->refresh();
        $this->assertSame(['packeta_point', 'EUR', 9.9, round($print + 9.9, 2)], [$order->delivery, $order->currency, $order->shipping_price, $order->price_total]);
        $this->assertSame(round(50 - $print - 9.9, 2), app(Wallet::class)->balance($user->fresh())->amount);
        $this->actingAs($user->fresh())->get('/es/account/orders')->assertOk()->assertSee(Money::eur($order->price_total)->format('es'));

        // printed: the operator creates the parcel with one button
        $this->finish($order);
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}")->assertOk()->assertSee(__('farm.admin.ship'))->assertSee('MRW Valencia Centro');
        Mail::fake();

        // the carrier refuses: the operator reads why, nothing changes, nobody is mailed
        FakeCarrier::$refuse = 'PacketAttributesFault: weight is too big';
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/ship")->assertSessionHas('error', __('farm.admin.ship_failed', ['reason' => 'PacketAttributesFault: weight is too big']));
        $order->refresh();
        $this->assertSame([FarmOrder::STATUS_DONE, null, null], [$order->status, $order->packeta_packet_id, $order->shipped_at]);
        Mail::assertNothingQueued();
        $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}/label.pdf")->assertNotFound();

        // accepted: the order is handed over, the parcel's numbers are kept, the customer gets the link in Spanish
        FakeCarrier::$refuse = null;
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/ship")->assertSessionHas('status');
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_HANDED_OVER, $order->status);
        $this->assertNotNull($order->shipped_at);
        $this->assertSame('Z'.$order->packeta_packet_id, $order->packeta_barcode);
        $this->assertSame('https://tracking.packeta.com/es/?id='.$order->packeta_barcode, $order->tracking_url);

        $parcel = FakeCarrier::$parcels[0];
        $this->assertSame([9003, 'MRW-0412', 'María', 'García', 'ES', true, 'EUR'], [$parcel->addressId, $parcel->carrierPickupPoint, $parcel->name, $parcel->surname, $parcel->country, $parcel->external, $parcel->currency]);
        $this->assertSame(app(Shipping::class)->parcelGrams($order) / 1000, $parcel->weightKg, 'the weight always goes with a parcel abroad');
        $this->assertSame($print, $parcel->value, 'insured for the print, not for the delivery');
        $this->assertNull($parcel->street);

        Mail::assertQueued(FarmOrderStatus::class, function (FarmOrderStatus $mail) use ($order, $user) {
            $html = $mail->locale('es')->render();

            return $mail->hasTo($user->email) && $mail->status === FarmOrder::STATUS_HANDED_OVER
                && str_contains($html, 'https://tracking.packeta.com/es/?id='.$order->packeta_barcode)
                && str_contains($html, __('farm.mail.shipped.track', [], 'es'));
        });
        // asked again (a double click): no second parcel
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/ship")->assertSessionHas('error');
        $this->assertCount(1, FakeCarrier::$parcels);

        // the label and what the customer sees
        $label = $this->actingAs($admin)->get("/admin/farm/orders/{$order->token}/label.pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $label->getContent());
        $this->assertStringContainsString('(carrier)', $label->getContent(), 'a partner carrier prints its own label');
        $this->actingAs($user->fresh())->getJson("/es/farm/orders/{$order->token}/status")->assertOk()
            ->assertJsonPath('tracking_url', $order->tracking_url)->assertJsonPath('destination', 'MRW Valencia Centro, Calle Mayor 5, 46001 Valencia, España');
    }

    public function test_home_delivery_in_czechia_sends_the_address_and_an_order_picked_up_in_person_needs_no_parcel(): void
    {
        $user = $this->customer(['name' => 'Roman Vzor']);
        $this->topUp($user, 1000);
        $admin = $this->admin();

        $home = $this->order($user);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$home->token}/status")->json();
        $print = (float) $state['colors'][0]['total'];
        $this->actingAs($user)->postJson("/farm/orders/{$home->token}/pay", ['slot' => $state['colors'][0]['slot'], 'terms' => true, 'delivery' => 'packeta_home', 'expected_total' => $print + 149,
            'address' => ['name' => 'Roman Vzor', 'phone' => '+420 777 000 111', 'country' => 'CZ', 'street' => 'Dlouhá 12/3a', 'city' => 'Plzeň', 'zip' => '301 00']])->assertOk();
        $this->finish($home);
        $this->actingAs($admin)->post("/admin/farm/orders/{$home->token}/ship")->assertSessionHas('status');
        $parcel = FakeCarrier::$parcels[0];
        $this->assertSame([106, null, 'Dlouhá', '12/3a', 'Plzeň', '301 00', false], [$parcel->addressId, $parcel->carrierPickupPoint, $parcel->street, $parcel->houseNumber, $parcel->city, $parcel->zip, $parcel->external]);
        $this->assertSame('https://tracking.packeta.com/cs/?id='.$home->fresh()->packeta_barcode, $home->fresh()->tracking_url);

        // pickup in person: free, no parcel button, handed over the old way, the plain mail
        $pickup = $this->order($user);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$pickup->token}/status")->json();
        $this->actingAs($user)->postJson("/farm/orders/{$pickup->token}/pay", ['slot' => $state['colors'][0]['slot'], 'terms' => true, 'delivery' => 'pickup', 'expected_total' => $state['colors'][0]['total']])->assertOk();
        $this->assertSame([0.0, null], [$pickup->fresh()->shipping_price, $pickup->fresh()->shipping_address]);
        $this->finish($pickup);
        $this->actingAs($admin)->get("/admin/farm/orders/{$pickup->token}")->assertOk()->assertDontSee(__('farm.admin.ship'));
        $this->actingAs($admin)->post("/admin/farm/orders/{$pickup->token}/ship")->assertSessionHas('error');
        $this->assertCount(1, FakeCarrier::$parcels);
        Mail::fake();
        $this->actingAs($admin)->post("/admin/farm/orders/{$pickup->token}/status", ['to' => FarmOrder::STATUS_HANDED_OVER])->assertSessionHas('status');
        Mail::assertQueued(FarmOrderStatus::class, fn (FarmOrderStatus $mail) => ! str_contains($mail->render(), 'tracking.packeta.com'));
    }

    public function test_an_unpaid_order_follows_the_currency_its_customer_switches_to(): void
    {
        $user = $this->customer(['country' => 'CZ']);
        $order = $this->order($user);
        $this->assertSame('CZK', $order->currency);
        $czk = (float) $order->fresh()->price_total;

        $state = $this->actingAs($user)->withCredentials()->withCookie(Currency::COOKIE, 'EUR')->getJson("/farm/orders/{$order->token}/status")->json();
        $this->assertSame(['EUR', Money::czk($czk)->to('EUR')->amount], [$state['currency'], (float) $state['total']]);
        $this->assertSame('EUR', $order->fresh()->currency);
        // the lines of the breakdown still add up to the print price
        $p = $state['colors'][0]['price'];
        $this->assertEqualsWithDelta($p['print_total'], $p['net'] + $p['vat'], 0.001);

        // topping up in crowns after all: the order goes back to crowns and is paid in them
        $this->withCookie(Currency::COOKIE, 'CZK');
        $this->topUp($user, 1000);
        $state = $this->actingAs($user->fresh())->withCredentials()->getJson("/farm/orders/{$order->token}/status")->json();
        $this->assertSame(['CZK', $czk], [$state['currency'], (float) $state['total']]);
        $this->actingAs($user->fresh())->postJson("/farm/orders/{$order->token}/pay", ['slot' => $state['colors'][0]['slot'], 'terms' => true, 'delivery' => 'pickup', 'expected_total' => $state['colors'][0]['total']])->assertOk();
        // paid: the switch no longer touches it
        $this->assertSame('CZK', $this->actingAs($user->fresh())->withCredentials()->withCookie(Currency::COOKIE, 'EUR')->getJson("/farm/orders/{$order->token}/status")->json('currency'));
    }
}
