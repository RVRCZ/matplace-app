<?php

namespace Tests\Feature;

use App\Domain\Affiliate\Affiliates;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\Wallet;
use App\Mail\FarmAdminAlert;
use App\Models\AnonymousSession;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\Partner;
use App\Models\PartnerClick;
use App\Models\PartnerCommission;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The partner programme (docs/AT.md): the link, the cookie, the attribution of an order, the commission that is written
 * once when the order is paid (credit and card alike), its states, the registration and the partner's page, the
 * operator's pages. Fake slicer, fake gateway, sync queue; nothing leaves the machine.
 */
class AffiliateProgrammeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AnonymousSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['farm.public' => true, 'farm.settings.admin_email' => 'farm@example.com']);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        app(FarmSettings::class)->set('affiliate_enabled', true);
        // a person's browser: a page that asks for HTML (without it the request is taken for a script, Bots::name)
        $this->withHeaders(['Accept' => 'text/html,application/xhtml+xml', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36']);
    }

    // ── helpers ───────────────────────────────────────────────────────────────────────────────────────────────────

    private function partner(array $over = []): Partner
    {
        return Partner::create($over + ['name' => 'Anna', 'code' => 'ANNA', 'email' => 'anna@example.com', 'commission_percent' => 10, 'cookie_days' => 30, 'active' => true]);
    }

    private function admin(): User
    {
        $admin = User::where('email', 'admin@example.com')->first() ?? User::factory()->create(['email' => 'admin@example.com']);
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    /** An order of the signed-in customer, made with the partner's cookie (or none). */
    private function order(?User $user = null, ?string $cookie = null): FarmOrder
    {
        $user ??= $this->user;
        $this->defaultCookies = [];   // the test client keeps the cookies it was given: each order has the cookie it is given here, or none
        $path = sys_get_temp_dir().'/mp_aff_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $client = $this->actingAs($user)->withCredentials();
        $r = ($cookie ? $client->withCookie('aff', $cookie) : $client)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function credit(User $user, int $amount = 5000): void
    {
        $this->actingAs($user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $user->refresh();
    }

    private function pay(FarmOrder $order, ?User $user = null): void
    {
        $user ??= $this->user;
        $s = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $s['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $s['colors'][0]['total']])->assertOk();
    }

    private function paidOrder(?string $cookie = null, ?User $user = null): FarmOrder
    {
        $user ??= $this->user;
        $this->credit($user);
        $order = $this->order($user, $cookie);
        $this->pay($order, $user);

        return $order->refresh();
    }

    private function done(FarmOrder $order): void
    {
        $flow = app(OrderFlow::class);
        $flow->move($order->refresh(), FarmOrder::STATUS_PRINTING, 'admin');
        $flow->move($order->refresh(), FarmOrder::STATUS_DONE, 'admin');
    }

    private function net(FarmOrder $order): float
    {
        return round((float) $order->price_total / 1.21, 2);   // pickup: the print alone, Czech VAT 21 %
    }

    // ── the link ──────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_the_link_counts_the_click_sets_the_cookie_and_redirects_inside_the_site(): void
    {
        $this->partner(['cookie_days' => 7]);
        $r = $this->get('https://localhost/r/anna?to=/tools');
        $r->assertRedirect(url('/tools'));
        $cookie = collect($r->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'aff');
        $this->assertNotNull($cookie, 'the cookie of the partner');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $cookie->getExpiresTime(), 120, 'the partner\'s own days');
        $this->assertSame(1, PartnerClick::count());
        $click = PartnerClick::first();
        $this->assertSame('/tools', $click->landing);
        $this->assertStringNotContainsString('127.0.0.1', $click->visitor, 'the address is never kept');

        // the same visitor again the same day: still one click; another visitor counts
        $this->call('GET', 'https://localhost/r/ANNA');
        $this->assertSame(1, PartnerClick::count());
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])->get('/r/ANNA');
        $this->assertSame(2, PartnerClick::count());
    }

    public function test_a_robot_is_not_counted_and_gets_no_cookie(): void
    {
        $this->partner();
        $r = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/r/ANNA?to=/tools');
        $r->assertRedirect(url('/tools'));
        $this->assertSame(0, PartnerClick::count());
        $r->assertCookieMissing('aff');
    }

    public function test_only_a_path_of_this_site_is_ever_gone_to(): void
    {
        $this->partner();
        foreach (['//evil.example', 'https://evil.example/x', '/\\evil.example', 'evil.example', 'javascript:alert(1)', "/ok\r\nLocation: https://evil.example", ''] as $to) {
            $this->get('/r/ANNA?'.http_build_query(['to' => $to]))->assertRedirect(url('/'));
        }
        $this->get('/r/ANNA?to='.urlencode('/en/tools/map?x=1'))->assertRedirect(url('/en/tools/map?x=1'));
    }

    public function test_an_unknown_or_switched_off_code_and_a_switched_off_programme_only_redirect(): void
    {
        $this->partner();
        $this->partner(['code' => 'OFFLINE', 'email' => 'o@example.com', 'active' => false]);
        $this->get('/r/NOBODY?to=/tools')->assertRedirect(url('/tools'))->assertCookieMissing('aff');
        $this->get('/r/OFFLINE')->assertRedirect(url('/'))->assertCookieMissing('aff');
        app(FarmSettings::class)->set('affiliate_enabled', false);
        $this->get('/r/ANNA')->assertRedirect(url('/'))->assertCookieMissing('aff');
        $this->assertSame(0, PartnerClick::count());
        $this->get('/partner')->assertNotFound();
        $this->actingAs($this->user)->get('/account/partner')->assertNotFound();
    }

    // ── an order and its commission ───────────────────────────────────────────────────────────────────────────────

    public function test_the_order_names_the_partner_of_the_cookie_and_the_last_click_wins(): void
    {
        $anna = $this->partner();
        $bob = $this->partner(['name' => 'Bob', 'code' => 'BOB', 'email' => 'bob@example.com']);
        $this->credit($this->user);
        $this->assertSame($anna->id, $this->order($this->user, 'ANNA')->partner_id);
        $this->assertSame($bob->id, $this->order($this->user, 'BOB')->partner_id);
        $this->assertNull($this->order($this->user)->partner_id, 'no cookie, no partner');
        $this->assertNull($this->order($this->user, 'NOBODY')->partner_id);
        $bob->update(['active' => false]);
        $this->assertNull($this->order($this->user, 'BOB')->partner_id, 'a switched-off partner earns nothing new');
    }

    public function test_never_for_the_partners_own_account_nor_an_admins_order(): void
    {
        $own = User::factory()->create(['email' => 'own@example.com']);
        $this->partner(['user_id' => $own->id]);
        $this->assertNull($this->order($own, 'ANNA')->partner_id);
        $admin = $this->admin();
        $this->assertNull($this->order($admin, 'ANNA')->partner_id);
        app(FarmSettings::class)->set('affiliate_enabled', false);
        $this->assertNull($this->order($this->user, 'ANNA')->partner_id, 'a programme that is off attributes nothing');
    }

    public function test_the_commission_is_written_when_the_order_is_paid_from_credit_net_of_vat_and_its_percent_is_frozen(): void
    {
        $anna = $this->partner();
        $order = $this->order($this->user, 'ANNA');
        $this->assertSame(0, PartnerCommission::count(), 'an order that is not paid earns nothing');
        $this->credit($this->user);
        $this->pay($order);
        $order->refresh();

        $c = PartnerCommission::firstOrFail();
        $this->assertSame($anna->id, $c->partner_id);
        $this->assertSame($order->id, $c->farm_order_id);
        $this->assertEqualsWithDelta($this->net($order), $c->base_amount, 0.011);
        $this->assertSame(10.0, $c->percent);
        $this->assertEqualsWithDelta(round($c->base_amount * 0.10, 2), $c->amount, 0.001);
        $this->assertSame('CZK', $c->currency);
        $this->assertSame(PartnerCommission::STATE_PENDING, $c->state);

        // a change of the partner's percent never touches a commission that exists; paying twice writes one
        $anna->update(['commission_percent' => 25]);
        app(Affiliates::class)->onPaid($order);
        $this->assertSame(1, PartnerCommission::count());
        $this->assertSame(10.0, $c->fresh()->percent);
    }

    public function test_the_commission_follows_a_discount_and_is_payable_when_the_print_is_done(): void
    {
        $this->partner();
        app(FarmSettings::class)->set('promo_codes_enabled', true);
        PromoCode::create(['code' => 'HALF', 'kind' => 'percent', 'value' => 50, 'active' => true, 'once_per_account' => false]);
        $this->credit($this->user);
        $order = $this->order($this->user, 'ANNA');
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/promo", ['code' => 'HALF'])->assertOk();
        $this->pay($order);
        $order->refresh();
        $c = PartnerCommission::firstOrFail();
        $this->assertEqualsWithDelta($this->net($order), $c->base_amount, 0.011, 'counted from what was paid, after the discount');
        $this->assertGreaterThan(0, (float) $order->discount);

        $this->done($order);
        $this->assertSame(PartnerCommission::STATE_PAYABLE, $c->fresh()->state, 'payable once the print is finished and the money is final');
    }

    public function test_a_card_payment_by_a_guest_writes_the_same_commission_once(): void
    {
        $this->partner();
        $path = sys_get_temp_dir().'/mp_aff_g_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $this->withCredentials()->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated();
        $this->session = AnonymousSession::findOrFail(ModelFile::latest('id')->firstOrFail()->anonymous_session_id);
        $guest = fn () => $this->withCredentials()->withUnencryptedCookie(AnonymousSession::COOKIE, $this->session->token)->withCookie('aff', 'ANNA');
        $r = $guest()->postJson('/farm/orders', ['file' => ModelFile::latest('id')->first()->uuid])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertNotNull($order->partner_id);

        $s = $guest()->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $guest()->postJson("/farm/orders/{$order->token}/checkout", ['slot' => $s['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $s['colors'][0]['total'], 'email' => 'host@example.com'])->assertOk();
        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame(0, PartnerCommission::count());
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();   // the gateway says it twice
        $order->refresh();
        $this->assertSame(1, PartnerCommission::count());
        $this->assertEqualsWithDelta($this->net($order), PartnerCommission::first()->base_amount, 0.011);
    }

    public function test_a_code_that_belongs_to_a_partner_wins_over_the_cookie(): void
    {
        $anna = $this->partner();
        $bob = $this->partner(['name' => 'Bob', 'code' => 'BOB', 'email' => 'bob@example.com']);
        app(FarmSettings::class)->set('promo_codes_enabled', true);
        PromoCode::create(['code' => 'BOBS', 'kind' => 'percent', 'value' => 10, 'active' => true, 'once_per_account' => false, 'partner_id' => $bob->id]);
        $this->credit($this->user);
        $order = $this->order($this->user, 'ANNA');
        $this->assertSame($anna->id, $order->partner_id);
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/promo", ['code' => 'BOBS'])->assertOk();
        $this->pay($order);
        $this->assertSame($bob->id, $order->refresh()->partner_id);
        $this->assertSame($bob->id, PartnerCommission::firstOrFail()->partner_id);

        // …and without the cookie at all the code alone credits the partner
        $second = $this->order($this->user);
        $this->actingAs($this->user)->postJson("/farm/orders/{$second->token}/promo", ['code' => 'BOBS'])->assertOk();
        $this->assertSame(1, PartnerCommission::count());
    }

    public function test_no_commission_for_the_partners_own_order_even_with_their_own_code(): void
    {
        $own = User::factory()->create(['email' => 'own@example.com']);
        $partner = $this->partner(['user_id' => $own->id]);
        app(FarmSettings::class)->set('promo_codes_enabled', true);
        PromoCode::create(['code' => 'MINE', 'kind' => 'percent', 'value' => 10, 'active' => true, 'once_per_account' => false, 'partner_id' => $partner->id]);
        $this->credit($own);
        $order = $this->order($own);
        $this->actingAs($own)->postJson("/farm/orders/{$order->token}/promo", ['code' => 'MINE'])->assertOk();
        $this->pay($order, $own);
        $this->assertSame(0, PartnerCommission::count());
    }

    public function test_switched_off_at_payment_no_commission_is_written(): void
    {
        $this->partner();
        $this->credit($this->user);
        $order = $this->order($this->user, 'ANNA');
        app(FarmSettings::class)->set('affiliate_enabled', false);
        $this->pay($order);
        $this->assertSame(0, PartnerCommission::count());
    }

    public function test_a_failed_order_voids_the_commission_and_a_partial_refund_reduces_it_in_proportion(): void
    {
        $this->partner();
        $order = $this->paidOrder('ANNA');
        $c = PartnerCommission::firstOrFail();
        $full = $c->amount;

        // 40 % of the money goes back (the print was cancelled when 60 % was printed): the commission is 60 %
        $total = (float) $order->price_total;
        $back = round($total * 0.4, 2);
        app(Wallet::class)->giveBack($order, null, 'part', $total - $back);
        app(Affiliates::class)->sync($order);
        $this->assertEqualsWithDelta(round($c->base_amount * 0.10 * (1 - $back / $total), 2), $c->fresh()->amount, 0.02);
        $this->assertSame(PartnerCommission::STATE_PENDING, $c->fresh()->state);
        $this->assertLessThan($full, $c->fresh()->amount);

        // the rest goes back (the order fails): void
        $second = $this->order($this->user, 'ANNA');
        $this->pay($second);
        $c2 = PartnerCommission::where('farm_order_id', $second->id)->firstOrFail();
        app(OrderFlow::class)->fail($second->refresh(), 'test', 'operator');
        $this->assertSame(PartnerCommission::STATE_VOID, $c2->fresh()->state);
        $this->assertSame(0.0, $c2->fresh()->amount);
    }

    public function test_a_commission_already_paid_out_is_not_touched_by_a_later_refund(): void
    {
        $partner = $this->partner();
        $order = $this->paidOrder('ANNA');
        $this->done($order);
        $this->actingAs($this->admin())->post("/admin/affiliates/{$partner->id}/paid", ['all' => 1, 'note' => 'převod 20. 11.'])->assertRedirect();
        $c = PartnerCommission::firstOrFail();
        $this->assertSame(PartnerCommission::STATE_PAID, $c->state);
        $this->assertNotNull($c->paid_at);
        $this->assertSame('převod 20. 11.', $c->note);
        $amount = $c->amount;
        app(Wallet::class)->giveBack($order->refresh());
        app(Affiliates::class)->sync($order);
        $this->assertSame(PartnerCommission::STATE_PAID, $c->fresh()->state);
        $this->assertSame($amount, $c->fresh()->amount);
    }

    // ── registration and the partner's page ──────────────────────────────────────────────────────────────────────

    private function form(array $over = []): array
    {
        return $over + ['name' => 'Eva Nová', 'code' => 'eva', 'channels' => 'blog evanova.example', 'payout_details' => 'účet 123456789/0100', 'terms' => '1'];
    }

    public function test_the_programme_page_tells_the_true_numbers_and_the_terms_in_every_language(): void
    {
        $settings = app(FarmSettings::class);
        $settings->set('affiliate_percent', 12);
        $settings->set('affiliate_cookie_days', 45);
        $this->get('/partner')->assertOk()->assertSee('12 %', false)->assertSee('45 dní', false)->assertSee(__('affiliate.form.signin'));
        $en = $this->get('/en/partner')->assertOk();
        $en->assertSee('12 %', false)->assertSee('45 days', false)->assertSee('Terms of the programme');
        $this->get('/es/partner')->assertOk()->assertSee('Condiciones del programa');
    }

    public function test_a_verified_user_registers_once_and_the_operator_is_mailed(): void
    {
        $this->actingAs($this->user)->post('/partner', $this->form())->assertRedirect(route('account.partner'));
        $p = Partner::firstOrFail();
        $this->assertSame('EVA', $p->code);
        $this->assertSame($this->user->id, $p->user_id);
        $this->assertSame('customer@example.com', $p->email);
        $this->assertTrue($p->active, 'active at once');
        $this->assertSame(10.0, $p->commission_percent);
        $this->assertSame(30, $p->cookie_days);
        $this->assertNotNull($p->terms_accepted_at);
        $this->assertSame('2026-11', $p->terms_version);
        Mail::assertQueued(FarmAdminAlert::class, fn (FarmAdminAlert $m) => $m->kind === 'partner' && str_contains($m->subjectLine, 'EVA'));

        // one partner per account
        $this->actingAs($this->user)->post('/partner', $this->form(['code' => 'EVA2']))->assertRedirect(route('account.partner'));
        $this->assertSame(1, Partner::count());
    }

    public function test_registration_refuses_a_taken_or_bad_code_no_terms_an_unverified_account_and_a_guest(): void
    {
        $this->partner(['code' => 'EVA']);
        $this->actingAs($this->user)->post('/partner', $this->form())->assertSessionHasErrors('code');
        $this->actingAs($this->user)->post('/partner', $this->form(['code' => 'bad code!']))->assertSessionHasErrors('code');
        $this->actingAs($this->user)->post('/partner', $this->form(['code' => str_repeat('A', 21)]))->assertSessionHasErrors('code');
        $this->actingAs($this->user)->post('/partner', $this->form(['code' => 'OK_1-2', 'terms' => null]))->assertSessionHasErrors('terms');
        $this->actingAs($this->user)->post('/partner', $this->form(['code' => 'OK_1-2', 'payout_details' => '']))->assertSessionHasErrors('payout_details');
        $this->assertSame(1, Partner::count());

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->post('/partner', $this->form(['code' => 'NEWONE']));
        $this->assertSame(1, Partner::count(), 'a verified e-mail is needed');

        auth()->logout();
        $this->post('/partner', $this->form(['code' => 'NEWONE']))->assertRedirect();
        $this->assertSame(1, Partner::count());
    }

    public function test_the_operators_switch_for_the_registration_mail_is_respected(): void
    {
        app(FarmSettings::class)->set('notify_partner', false);
        $this->actingAs($this->user)->post('/partner', $this->form())->assertRedirect();
        $this->assertSame(1, Partner::count());
        Mail::assertNotQueued(FarmAdminAlert::class);
    }

    public function test_the_partners_page_shows_the_link_its_builder_the_numbers_and_the_commissions(): void
    {
        $partner = $this->partner(['user_id' => $this->user->id]);
        $this->actingAs($this->user)->get('/account/partner')->assertOk()->assertSee(url('/r/ANNA'), false);
        $this->actingAs($this->user)->get('/account/partner?to=/tools/map')->assertOk()->assertSee(url('/r/ANNA').'?to=/tools/map', false);
        $this->actingAs($this->user)->get('/account/partner?to=//evil.example')->assertOk()->assertSee('value="'.url('/r/ANNA').'"', false);

        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $order = $this->paidOrder('ANNA', $buyer);
        $this->done($order);
        PartnerClick::create(['partner_id' => $partner->id, 'day' => now()->toDateString(), 'visitor' => 'x', 'created_at' => now()]);
        $page = $this->actingAs($this->user)->get('/account/partner')->assertOk();
        $page->assertSee($order->number)->assertSee(__('affiliate.state.payable'));
        $stats = app(Affiliates::class)->stats($partner);
        $this->assertSame(1, $stats['clicks']);
        $this->assertSame(1, $stats['paid_orders']);
        $this->assertEqualsWithDelta($this->net($order), $stats['turnover']['CZK'], 0.011);
        $this->assertEqualsWithDelta(round($this->net($order) * 0.10, 2), $stats['payable']['CZK'], 0.011);
        $this->assertSame([], $stats['paid']);

        // somebody who is not a partner is sent to the programme
        $this->actingAs($buyer)->get('/account/partner')->assertRedirect(route('affiliate.program'));
    }

    // ── the operator's pages ──────────────────────────────────────────────────────────────────────────────────────

    public function test_the_admin_pages_are_the_admins_and_both_switches_start_off(): void
    {
        $this->actingAs($this->user)->get('/admin/affiliates')->assertRedirect();
        $this->actingAs($this->user)->get('/admin/promo-codes')->assertRedirect();
        $this->assertFalse((bool) config('farm.settings.affiliate_enabled'));
        $this->assertFalse((bool) config('farm.settings.promo_codes_enabled'));
        $this->assertSame(10, (int) config('farm.settings.affiliate_percent'));
        $this->assertSame(30, (int) config('farm.settings.affiliate_cookie_days'));
    }

    public function test_the_operator_adds_changes_and_switches_off_a_partner_and_marks_commissions_paid(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/affiliates', ['name' => 'Cyril', 'code' => 'cyril', 'email' => 'c@example.com', 'commission_percent' => 12.5, 'cookie_days' => 60, 'active' => 1, 'user_email' => 'customer@example.com'])->assertRedirect(route('admin.affiliates'));
        $p = Partner::where('code', 'CYRIL')->firstOrFail();
        $this->assertSame(12.5, $p->commission_percent);
        $this->assertSame(60, $p->cookie_days);
        $this->assertSame($this->user->id, $p->user_id);
        $this->actingAs($admin)->post('/admin/affiliates', ['name' => 'D', 'code' => 'CYRIL', 'email' => 'd@example.com', 'commission_percent' => 5, 'cookie_days' => 5])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/affiliates', ['name' => 'D', 'code' => 'DEE', 'email' => 'd@example.com', 'commission_percent' => 5, 'cookie_days' => 5, 'user_email' => 'customer@example.com'])->assertSessionHasErrors('user_email');

        $this->actingAs($admin)->post("/admin/affiliates/{$p->id}", ['name' => 'Cyril', 'code' => 'CYRIL', 'email' => 'c@example.com', 'commission_percent' => 15, 'cookie_days' => 60])->assertRedirect();
        $this->assertSame(15.0, $p->fresh()->commission_percent);
        $this->assertFalse($p->fresh()->active, 'no tick = switched off');
        $this->get('/r/CYRIL')->assertCookieMissing('aff');

        // payout: by id or all of the partner; the note is required
        $other = User::factory()->create(['email' => 'b2@example.com']);
        $p->refresh()->update(['active' => true, 'user_id' => null]);
        $one = $this->paidOrder('CYRIL', $other);
        $two = $this->order($other, 'CYRIL');
        $this->pay($two, $other);
        $this->done($one);
        $this->done($two->refresh());
        $ids = PartnerCommission::orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->actingAs($admin)->post("/admin/affiliates/{$p->id}/paid", ['ids' => [$ids[0]], 'note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post("/admin/affiliates/{$p->id}/paid", ['ids' => [$ids[0]], 'note' => 'poslání'])->assertRedirect();
        $this->assertSame([PartnerCommission::STATE_PAID, PartnerCommission::STATE_PAYABLE], PartnerCommission::orderBy('id')->pluck('state')->all());
        $this->actingAs($admin)->get('/admin/affiliates')->assertOk()->assertSee('Cyril')->assertSee('CYRIL');
        $this->actingAs($admin)->post("/admin/affiliates/{$p->id}/paid", ['all' => 1, 'note' => 'zbytek'])->assertRedirect();
        $this->assertSame([PartnerCommission::STATE_PAID, PartnerCommission::STATE_PAID], PartnerCommission::orderBy('id')->pluck('state')->all());
    }

    public function test_the_operator_manages_the_discount_codes(): void
    {
        $admin = $this->admin();
        $partner = $this->partner();
        $this->actingAs($admin)->post('/admin/promo-codes', ['code' => 'jaro', 'kind' => 'percent', 'value' => 15, 'min_order_czk' => 200, 'valid_until' => '2026-12-31', 'max_uses' => 50, 'once_per_account' => 1, 'active' => 1, 'partner_id' => $partner->id])->assertRedirect(route('admin.promo-codes'));
        $c = PromoCode::firstOrFail();
        $this->assertSame('JARO', $c->code);
        $this->assertSame(200.0, $c->min_order_czk);
        $this->assertTrue($c->once_per_account);
        $this->assertSame('2026-12-31 23:59:59', $c->valid_until->format('Y-m-d H:i:s'), 'the last day counts whole');
        $this->assertSame($partner->id, $c->partner_id);
        $this->actingAs($admin)->post('/admin/promo-codes', ['code' => 'JARO', 'kind' => 'fixed', 'value' => 50])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/promo-codes', ['code' => 'TOO', 'kind' => 'percent', 'value' => 150])->assertSessionHasErrors('value');
        $this->actingAs($admin)->post('/admin/promo-codes', ['code' => 'X', 'kind' => 'fixed', 'value' => 50])->assertSessionHasErrors('code');

        $this->actingAs($admin)->post("/admin/promo-codes/{$c->id}", ['code' => 'JARO', 'kind' => 'fixed', 'value' => 80])->assertRedirect();
        $c->refresh();
        $this->assertSame('fixed', $c->kind);
        $this->assertFalse($c->active, 'no tick = switched off');
        $this->actingAs($admin)->get('/admin/promo-codes')->assertOk()->assertSee('JARO');
    }

    public function test_the_two_switches_and_the_numbers_are_saved_in_the_farm_settings(): void
    {
        $admin = $this->admin();
        $settings = app(FarmSettings::class);
        $settings->set('affiliate_enabled', false);
        $page = $this->actingAs($admin)->get('/admin/farm/settings')->assertOk();
        $page->assertSee('name="affiliate_enabled"', false)->assertSee('name="promo_codes_enabled"', false)->assertSee('name="notify_partner"', false);
        $post = fn (array $over) => $this->actingAs($admin)->post('/admin/farm/settings', $over + [
            'max_upload_mb' => 50, 'daily_slices_per_user' => 5, 'min_model_mm' => 1, 'bed_margin_mm' => 5, 'hourly_rate' => 35, 'fixed_fee' => 30, 'min_price' => 99, 'vat_percent' => 21, 'rounding' => 1,
            'topup_amounts' => '100, 200', 'topup_min' => 50, 'topup_max' => 5000, 'generation_price' => 15, 'changeover_minutes' => 10, 'offline_after_seconds' => 120, 'terms_version' => '2026-10',
            'qualities' => json_encode(['standard' => ['layer_mm' => 0.2]]), 'strengths' => json_encode(['standard' => ['infill' => 15]]), 'shorts_publish_hour' => '18:00', 'farm_state' => 'open',
        ]);
        $post(['affiliate_enabled' => 1, 'promo_codes_enabled' => 1, 'affiliate_percent' => 8, 'affiliate_cookie_days' => 14])->assertRedirect();
        $this->assertTrue((bool) $settings->get('affiliate_enabled'));
        $this->assertTrue((bool) $settings->get('promo_codes_enabled'));
        $this->assertEquals(8, $settings->get('affiliate_percent'));
        $this->assertEquals(14, $settings->get('affiliate_cookie_days'));
        $post([])->assertRedirect();
        $this->assertFalse((bool) $settings->get('affiliate_enabled'));
        $this->assertFalse((bool) $settings->get('promo_codes_enabled'));
        $this->assertEquals(8, $settings->get('affiliate_percent'), 'an empty box keeps the number');
    }
}
