<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmGate;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\FarmState;
use App\Mail\FarmAdminAlert;
use App\Mail\FarmGuestOrder;
use App\Mail\FarmOrderStatus;
use App\Models\FarmAgent;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use App\Models\Payment;
use App\Models\User;
use App\Support\LandingPage;
use App\Support\NextStep;
use App\Support\Schema;
use Carbon\Carbon;
use Database\Seeders\FarmSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The state of the whole service (docs/AL.md §23, docs/prompts/stav-farmy-odstavka.md): open / paused / closed.
 * Who may order, what the pages say, a paid order waiting through a pause, the admin printing anyway, the mail, and
 * how the old switches map to the state. Fake slicer, fake gateway, sync queue.
 */
class FarmStateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['features.marketplace' => false, 'farm.enabled' => true, 'farm.open' => true, 'farm.public' => true, 'farm.state' => 'open']);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        $this->admin = User::factory()->create(['email' => 'owner@example.com']);
        $this->admin->setRole(User::ROLE_ADMIN, true);
        $this->admin = $this->admin->fresh();
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Europe/Prague'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pause(?string $until = '2026-11-20', array $note = []): void
    {
        config(['farm.open' => true, 'farm.state' => 'paused', 'farm.pause_until' => (string) $until, 'farm.pause_note' => $note]);
    }

    private function close(): void
    {
        config(['farm.open' => false, 'farm.state' => 'closed']);
    }

    private function open(): void
    {
        config(['farm.open' => true, 'farm.state' => 'open']);
    }

    private function order(User $who): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_state_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($who)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($who)->postJson('/farm/orders', ['file' => $uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function credit(User $who, int $amount = 1000): User
    {
        $this->actingAs($who)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();

        return $who->fresh();
    }

    private function pay(User $who, FarmOrder $order)
    {
        $state = $this->actingAs($who)->getJson("/farm/orders/{$order->token}/status")->json();
        $color = collect($state['colors'])->firstWhere('slot', $state['slot']) ?? collect($state['colors'])->firstWhere('sliced', true) ?? $state['colors'][0];

        return $this->actingAs($who)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $color['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $color['total']]);
    }

    /** @return array{0: FarmPrinter, 1: array<string, string>} the seeded printer driven by an agent, online, its plate declared clear */
    private function agentPrinter(): array
    {
        [$agent, $token] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id]);

        return [$printer, ['Authorization' => 'Bearer '.$token]];
    }

    private function sync(array $headers)
    {
        return $this->postJson('/api/agent/sync', ['version' => 'test', 'printers' => [['key' => 'kobra-s1-01', 'state' => 'idle', 'telemetry' => ['extruder' => 25, 'bed' => 25], 'job' => null]]], $headers);
    }

    private function settingsForm(): array
    {
        $settings = app(FarmSettings::class)->all();

        return ['topup_amounts' => implode(', ', $settings['topup_amounts']), 'qualities' => json_encode($settings['qualities']), 'strengths' => json_encode($settings['strengths']), 'delivery_modes' => $settings['delivery_modes']]
            + array_intersect_key($settings, array_flip(['hourly_rate', 'vat_percent', 'rounding', 'max_upload_mb', 'daily_slices_per_user', 'min_model_mm', 'bed_margin_mm', 'fixed_fee', 'min_price', 'topup_min', 'topup_max', 'generation_price', 'changeover_minutes', 'offline_after_seconds', 'terms_version', 'admin_email', 'shorts_publish_hour']));
    }

    /** What a page says to search engines: these never follow the state. */
    private function seoOf(string $html): array
    {
        preg_match('/<title>(.*?)<\/title>/s', $html, $title);
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $description);
        preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $h1);

        return [$title[1] ?? null, $description[1] ?? null, trim(strip_tags($h1[1] ?? ''))];
    }

    public function test_the_old_switches_map_to_the_three_states(): void
    {
        $settings = app(FarmSettings::class);
        // a database that never saw the state: open or closed by `farm_open` alone
        $this->assertNull($settings->get('farm_state'));
        $this->assertSame(FarmState::OPEN, FarmState::fromSettings($settings));
        $settings->set('farm_open', false);
        $this->assertSame(FarmState::CLOSED, FarmState::fromSettings($settings));
        // paused is kept in `farm_state`; `farm_open` off closes whatever it says
        $settings->set('farm_state', 'paused');
        $this->assertSame(FarmState::CLOSED, FarmState::fromSettings($settings));
        $settings->set('farm_open', true);
        $this->assertSame(FarmState::PAUSED, FarmState::fromSettings($settings));
        $settings->set('farm_state', 'open');
        $this->assertSame(FarmState::OPEN, FarmState::fromSettings($settings));

        // the config the pages read: `farm.open` off is closed, `farm.state` adds the pause
        $this->close();
        $this->assertTrue(FarmState::isClosed());
        $this->pause();
        $this->assertTrue(FarmState::isPaused());
        $this->open();
        $this->assertTrue(FarmState::isOpen());
    }

    public function test_a_queue_worker_reads_the_state_again_before_every_job(): void
    {
        // the admin pauses the farm while a worker is running: the worker's next job sees it, the sync queue keeps the request's own
        app(FarmSettings::class)->set('farm_state', 'paused');
        app(FarmSettings::class)->set('farm_pause_until', '2026-11-20');
        $this->app->forgetScopedInstances();
        $job = $this->createMock(Job::class);
        event(new JobProcessing('sync', $job));
        $this->assertTrue(FarmState::isOpen());
        event(new JobProcessing('database', $job));
        $this->assertTrue(FarmState::isPaused());
        $this->assertSame('2026-11-20', FarmState::pauseUntil()?->toDateString());
        app(FarmSettings::class)->set('farm_open', false);
        $this->app->forgetScopedInstances();
        event(new JobProcessing('database', $job));
        $this->assertTrue(FarmState::isClosed());
        $this->assertFalse(FarmGate::open($this->user));
    }

    public function test_the_admin_sets_the_state_in_one_choice_and_farm_open_follows_it(): void
    {
        $form = $this->settingsForm();
        $page = $this->actingAs($this->admin)->get('/admin/farm/settings')->assertOk();
        $page->assertSee('id="farm-state"', false)->assertSee('name="farm_state" value="open" checked', false)
            ->assertSee('name="farm_state" value="paused"', false)->assertSee('name="farm_state" value="closed"', false)
            ->assertSee('name="farm_pause_until"', false)->assertSee('name="farm_pause_note[en]"', false)
            ->assertSee('Při odstávce nebo zavření zvažte pozastavení reklamy.')
            ->assertDontSee('name="farm_open"', false)
            // the other old switches stay where they were
            ->assertSee('name="farm_public"', false)->assertSee('name="require_approval"', false);

        $this->actingAs($this->admin)->post('/admin/farm/settings', $form + ['farm_state' => 'paused', 'farm_pause_until' => '2026-11-20', 'farm_pause_note' => ['cs' => 'Měníme trysky.', 'en' => '', 'es' => ''], 'farm_public' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $settings = app(FarmSettings::class);
        $this->assertSame('paused', $settings->get('farm_state'));
        $this->assertTrue($settings->get('farm_open'), 'a paused farm still takes orders');
        $this->assertSame('2026-11-20', $settings->get('farm_pause_until'));
        $this->assertSame('Měníme trysky.', $settings->get('farm_pause_note')['cs']);
        $this->assertSame(FarmState::PAUSED, FarmState::fromSettings($settings));

        $this->actingAs($this->admin)->post('/admin/farm/settings', $form + ['farm_state' => 'closed', 'farm_public' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $this->assertFalse(app(FarmSettings::class)->get('farm_open'), 'closed is the old switch off');
        $this->assertSame(FarmState::CLOSED, FarmState::fromSettings(app(FarmSettings::class)));

        $this->actingAs($this->admin)->post('/admin/farm/settings', $form + ['farm_state' => 'open', 'farm_public' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $this->assertTrue(app(FarmSettings::class)->get('farm_open'));
        $this->assertSame(FarmState::OPEN, FarmState::fromSettings(app(FarmSettings::class)));

        // a form of the old shape (the checkbox alone): on = open, off = closed, as before
        $this->actingAs($this->admin)->post('/admin/farm/settings', $form)->assertRedirect()->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $this->assertSame(FarmState::CLOSED, FarmState::fromSettings(app(FarmSettings::class)));
        $this->actingAs($this->admin)->post('/admin/farm/settings', $form + ['farm_open' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $this->assertSame(FarmState::OPEN, FarmState::fromSettings(app(FarmSettings::class)));

        $this->actingAs($this->admin)->post('/admin/farm/settings', $form + ['farm_state' => 'holiday'])->assertSessionHasErrors('farm_state');
    }

    public function test_the_state_is_loud_on_every_page_of_the_farms_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee('data-farm-state="open"', false)->assertDontSee('ODSTÁVKU');
        $this->pause();
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee('data-farm-state="paused"', false)->assertSee('Farma má ODSTÁVKU.')
            ->assertSee('tisk začne nejdřív 20. 11. 2026')->assertSee('zvažte pozastavení reklamy');
        $this->actingAs($this->admin)->get('/admin/farm/orders')->assertOk()->assertSee('data-farm-state="paused"', false);
        // a date that has passed is said so: the customers no longer see it
        $this->pause('2026-11-01');
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee('Datum konce odstávky už uplynulo');
        $this->close();
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee('data-farm-state="closed"', false)->assertSee('Farma je ZAVŘENÁ.');
    }

    public function test_who_may_order_in_each_state(): void
    {
        $this->assertTrue(FarmGate::open($this->user));
        $this->assertTrue(FarmGate::offered(null));

        $this->pause();
        $this->assertTrue(FarmGate::open($this->user), 'a paused farm takes orders');
        $this->assertTrue(FarmGate::offered(null));
        $this->assertTrue(FarmGate::open($this->admin));
        $this->assertSame(NextStep::FARM, NextStep::mode());

        $this->close();
        $this->assertFalse(FarmGate::open($this->user));
        $this->assertFalse(FarmGate::offered(null));
        $this->assertTrue(FarmGate::offered($this->admin), 'the admin orders in every state');
        $this->assertSame(NextStep::DOWNLOAD, NextStep::mode());
        $path = sys_get_temp_dir().'/mp_state_closed_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->assertStatus(422)->assertJsonPath('error', 'closed');
        $this->order($this->admin);
    }

    public function test_the_pause_sentence_names_the_day_only_while_it_is_ahead_and_the_note_only_in_its_language(): void
    {
        $this->assertNull(FarmState::pauseLine());
        $this->pause('2026-11-20', ['cs' => 'Měníme trysky.', 'en' => '', 'es' => '']);
        $this->assertSame('Farma má teď odstávku. Tisk začne nejdřív 20. 11. 2026. Měníme trysky.', FarmState::pauseLine('cs'));
        $this->assertSame('The farm is paused right now. Printing starts on 20 Nov 2026 at the earliest.', FarmState::pauseLine('en'));
        $this->assertStringContainsString('La granja está en pausa.', (string) FarmState::pauseLine('es'));
        $this->assertStringNotContainsString('trysky', (string) FarmState::pauseLine('es'));
        // today still counts; yesterday does not, and neither does no date at all
        $this->pause('2026-11-13');
        $this->assertStringContainsString('13. 11. 2026', (string) FarmState::pauseLine('cs'));
        $this->pause('2026-11-12');
        $this->assertSame('Farma má teď odstávku. Tisk začne, jakmile farmu znovu spustíme.', FarmState::pauseLine('cs'));
        $this->pause('');
        $this->assertSame('Farma má teď odstávku. Tisk začne, jakmile farmu znovu spustíme.', FarmState::pauseLine('cs'));
        $this->close();
        $this->assertNull(FarmState::pauseLine());
    }

    public function test_a_promise_by_a_day_holds_only_while_the_pause_ends_in_time(): void
    {
        $christmas = Carbon::parse('2026-12-15');
        $this->assertTrue(FarmState::allowsPromiseBy($christmas));
        $this->pause('2026-11-20');
        $this->assertTrue(FarmState::allowsPromiseBy($christmas));
        $this->pause('2026-12-15');
        $this->assertTrue(FarmState::allowsPromiseBy($christmas));
        $this->pause('2026-12-16');
        $this->assertFalse(FarmState::allowsPromiseBy($christmas), 'the pause ends after the last day of the promise');
        $this->pause('');
        $this->assertFalse(FarmState::allowsPromiseBy($christmas), 'nobody knows when the pause ends');
        $this->close();
        $this->assertFalse(FarmState::allowsPromiseBy($christmas));
    }

    public function test_the_calculator_keeps_the_button_and_the_price_in_a_pause_and_drops_both_when_closed(): void
    {
        $configOf = function (string $html): array {
            $this->assertSame(1, preg_match('/window\.MP_CONFIG_URL = "([^"]+)"/', $html, $m));

            return (array) $this->get((string) json_decode('"'.$m[1].'"'))->assertOk()->json('config');
        };
        $open = (string) $this->get('/')->assertOk()->assertSee('id="cta-farm"', false)->assertDontSee('data-farm-pause', false)->getContent();
        $this->assertContains('farm', array_column($configOf($open)['orientation_profiles'], 'key'), 'the farm prices the calculator');

        $this->pause();
        $paused = (string) $this->get('/')->assertOk()->assertSee('id="cta-farm"', false)->assertSee('data-farm-pause', false)
            ->assertSee('Farma má teď odstávku. Tisk začne nejdřív 20. 11. 2026.')->assertSee('Objednat a zaplatit můžete už teď')->getContent();
        $this->assertContains('farm', array_column($configOf($paused)['orientation_profiles'], 'key'), 'the prices stay in a pause');
        $this->get('/en')->assertOk()->assertSee('Printing starts on 20 Nov 2026 at the earliest.');
        $this->pause('');
        $this->get('/')->assertOk()->assertSee('Tisk začne, jakmile farmu znovu spustíme.');

        $this->close();
        $closed = (string) $this->get('/')->assertOk()->assertDontSee('id="cta-farm"', false)->assertDontSee('data-farm-pause', false)->assertDontSee('id="calc-trust"', false)->getContent();
        $this->assertNotContains('farm', array_column($configOf($closed)['orientation_profiles'], 'key'), 'no price of a print for a visitor of a closed farm');
        // the admin tries the farm out while it is closed: the button and the price are there
        $admin = (string) $this->actingAs($this->admin)->get('/')->assertOk()->assertSee('id="cta-farm"', false)->getContent();
        $this->assertContains('farm', array_column($configOf($admin)['orientation_profiles'], 'key'));

        // what the page says to search engines is the same in every state
        $this->assertSame($this->seoOf($open), $this->seoOf($paused));
        $this->assertSame($this->seoOf($open), $this->seoOf($closed));
    }

    public function test_a_tool_page_says_the_pause_where_it_promises_a_print_and_nothing_of_printing_when_closed(): void
    {
        $open = (string) $this->get('/tools/qr')->assertOk()->assertDontSee('data-farm-pause', false)->getContent();
        $this->assertStringContainsString(e(__('param.go.hint.farm')), $open);

        $this->pause();
        $paused = (string) $this->get('/tools/qr')->assertOk()->assertSee('data-farm-pause', false)->assertSee('Tisk začne nejdřív 20. 11. 2026.')->getContent();
        $this->assertStringContainsString(e(__('param.go.hint.farm')), $paused, 'the way on stays');
        // "Before you order": the first question says when printing starts, on the page and in its FAQPage data
        $items = LandingPage::order('qr');
        $this->assertSame('Kdy začne tisk?', $items[0]['q']);
        $this->assertStringContainsString('Tisk začne nejdřív 20. 11. 2026.', $items[0]['a']);
        $this->assertGreaterThanOrEqual(2, substr_count($paused, 'Kdy začne tisk?'));

        $this->close();
        $closed = (string) $this->get('/tools/qr')->assertOk()->assertDontSee('data-farm-pause', false)->assertDontSee('Kdy začne tisk?')->getContent();
        $this->assertStringNotContainsString(e(__('param.go.hint.farm')), $closed);
        $this->assertStringContainsString(e(__('param.go.hint.closed')), $closed, 'no price of a print is promised');
        $this->assertStringContainsString('<span id="tool-go-label">Spočítat dobu tisku a materiál</span>', $closed);
        $this->assertStringContainsString('<span id="tool-go-label">Spočítat cenu a možnosti tisku</span>', $paused);
        $this->assertStringNotContainsString(e(__('landing.qr.order.1.q')), $closed, 'the questions about printing with us are gone');
        $this->assertStringContainsString(e(__('footer.promise.closed')), $closed);

        $this->assertSame($this->seoOf($open), $this->seoOf($paused));
        $this->assertSame($this->seoOf($open), $this->seoOf($closed));
    }

    public function test_the_entry_pages_follow_the_state_and_keep_their_titles(): void
    {
        foreach (['/3d-tisk-praha', '/gifts'] as $path) {
            $open = (string) $this->get($path)->assertOk()->assertDontSee('data-farm-pause', false)->assertDontSee('data-farm-closed', false)->getContent();
            $this->pause();
            $paused = (string) $this->get($path)->assertOk()->assertSee('data-farm-pause', false)->assertSee('Farma má teď odstávku.')->getContent();
            $this->close();
            $closed = (string) $this->get($path)->assertOk()->assertDontSee('data-farm-pause', false)->getContent();
            $this->assertSame($this->seoOf($open), $this->seoOf($paused), $path);
            $this->assertSame($this->seoOf($open), $this->seoOf($closed), $path);
            $this->open();
        }
        $this->close();
        $this->get('/3d-tisk-praha')->assertOk()->assertSee('data-farm-closed', false)->assertSee('Farma teď nové zakázky nepřijímá.');
        $this->get('/gifts')->assertOk()->assertSee(__('gifts.after.closed'));
    }

    public function test_structured_data_of_a_print_we_sell_follows_the_state(): void
    {
        $offer = fn () => Schema::product('Vase', null, [], 'https://matplace.com/x', [99.0, 129.0], 'CZK')['offers']['availability'];
        $this->assertSame('https://schema.org/InStock', $offer());
        $this->pause();
        $this->assertSame('https://schema.org/BackOrder', $offer());
        $this->close();
        $this->assertSame('https://schema.org/OutOfStock', $offer());
    }

    public function test_an_order_paid_during_a_pause_waits_in_the_queue_and_prints_once_the_farm_is_open(): void
    {
        config(['farm.settings.admin_email' => 'farm@example.com']);
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth)->assertOk();
        $this->actingAs($this->admin)->post("/admin/farm/printers/{$printer->id}/bed", ['clear' => 1])->assertRedirect();
        $this->assertTrue($printer->refresh()->readyForAutoStart(), 'the machine is free and its plate clear');

        $this->pause();
        $order = $this->order($this->user);
        $this->user = $this->credit($this->user);
        // before the payment the page says the pause, and nothing "starts now"
        $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->assertSee('data-farm-pause', false)->assertSee('Tisk začne nejdřív 20. 11. 2026.');
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->assertSame('Farma má teď odstávku. Tisk začne nejdřív 20. 11. 2026.', $state['pause']);
        $this->assertNotContains(true, array_column($state['colors'], 'starts_now'));

        $this->pay($this->user, $order)->assertOk()->assertJsonPath('status', 'queued');
        $this->assertSame(FarmOrder::STATUS_QUEUED, $order->refresh()->status, 'paid and in the queue, as always');
        $this->assertSame(0, FarmPrintJob::count(), 'paying starts nothing');
        $this->sync($auth)->assertOk()->assertJsonCount(0, 'commands');
        $this->sync($auth)->assertOk()->assertJsonCount(0, 'commands');
        $this->assertSame(0, FarmPrintJob::count());
        $this->assertTrue($printer->refresh()->bed_clear, 'the confirmation of the plate is not used up');

        // the page of the paid order and the mail say the same sentence
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->assertSame('pause', $state['queue']['blocked']);
        $this->assertStringContainsString('20. 11. 2026', $state['pause']);
        Mail::assertQueued(FarmOrderStatus::class, function (FarmOrderStatus $mail) use ($order) {
            if ($mail->order->id !== $order->id || $mail->status !== FarmOrder::STATUS_QUEUED) {
                return false;
            }
            $this->assertSame('Zakázka '.$order->number.' je přijatá, tisk začne po odstávce', $mail->envelope()->subject);
            $text = $mail->render();
            $this->assertStringContainsString('Tisk začne nejdřív 20. 11. 2026.', $text);
            $this->assertStringContainsString('čeká ve frontě', $text);

            return true;
        });

        // the operator's "paid" mail says why nothing starts, and the guest's "order received" mail says the pause too
        Mail::assertQueued(FarmAdminAlert::class, fn (FarmAdminAlert $mail) => $mail->kind === 'paid' && in_array('Fronta: farma má odstávku, zakázka čeká a spustí se, až farmu otevřete', $mail->lines, true));
        $guestMail = (new FarmGuestOrder($order->refresh(), false))->render();
        $this->assertStringContainsString('čeká ve frontě', $guestMail);
        $this->assertStringContainsString('Tisk začne nejdřív 20. 11. 2026.', $guestMail);
        $this->assertStringNotContainsString('jde k tiskárně', $guestMail);

        // the admin opens the farm: the queue goes on the usual way, with the agent's next report
        $this->open();
        $cmd = $this->sync($auth)->assertOk()->assertJsonCount(1, 'commands')->json('commands.0');
        $this->assertSame('start', $cmd['type']);
        $this->assertSame($order->id, FarmPrintJob::findOrFail($cmd['job_id'])->farm_order_id);
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->assertNull($state['pause']);
        $this->assertNotSame('pause', $state['queue']['blocked'] ?? null);
    }

    public function test_an_order_paid_while_open_is_told_about_the_queue_as_always(): void
    {
        $order = $this->order($this->user);
        $this->user = $this->credit($this->user);
        $this->pay($this->user, $order)->assertOk()->assertJsonPath('status', 'queued');
        $order->refresh();
        Mail::assertQueued(FarmOrderStatus::class, function (FarmOrderStatus $mail) use ($order) {
            return $mail->order->id === $order->id && $mail->envelope()->subject === 'Zakázka '.$order->number.' je ve frontě'
                && ! str_contains($mail->render(), 'odstávku');
        });
    }

    public function test_the_admins_trial_order_prints_during_a_pause_past_the_waiting_customers(): void
    {
        [$printer, $auth] = $this->agentPrinter();
        $this->sync($auth)->assertOk();
        $this->pause();

        // a customer first, the admin second: the customer's order waits, the admin's starts
        $customers = $this->order($this->user);
        $this->user = $this->credit($this->user);
        $this->pay($this->user, $customers)->assertOk()->assertJsonPath('status', 'queued');
        $own = $this->order($this->admin);
        $this->admin = $this->credit($this->admin);
        $this->actingAs($this->admin)->get("/farm/orders/{$own->token}")->assertOk()->assertDontSee('data-farm-pause', false);
        $this->pay($this->admin, $own)->assertOk()->assertJsonPath('status', 'queued');
        $this->assertFalse(FarmState::holds($own->refresh()));
        $this->assertTrue(FarmState::holds($customers->refresh()));

        $this->actingAs($this->admin)->post("/admin/farm/printers/{$printer->id}/bed", ['clear' => 1])->assertRedirect();
        $job = FarmPrintJob::firstOrFail();
        $this->assertSame($own->id, $job->farm_order_id, 'the admin prints in every state');
        $this->assertSame(1, FarmPrintJob::count());
        $this->assertSame(FarmOrder::STATUS_QUEUED, $customers->refresh()->status);
        // the admin's mail has the usual words
        Mail::assertQueued(FarmOrderStatus::class, fn (FarmOrderStatus $mail) => $mail->order->id === $own->id && $mail->envelope()->subject === 'Zakázka '.$own->refresh()->number.' je ve frontě');
        // the banner counts who waits
        $this->actingAs($this->admin)->get('/admin/farm')->assertOk()->assertSee('ve frontě čeká 1');
    }
}
