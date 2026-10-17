<?php

namespace Tests\Feature;

use App\Domain\Stats\Funnel;
use App\Domain\Stats\Humans;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Models\User;
use App\Support\Track;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The measurement of the audit (docs/AUDIT.md bod 12, docs/AJ.md): the steps from a model to a paid order are each
 * written once, the payment only when the bank confirmed it, our own test runs not at all, and no event keeps more
 * than numbers and codes. Fake slicer, fake gateway, sync queue.
 */
class AuditEventsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        Humans::forget();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->withCredentials();   // a browser sends its cookies with fetch() too
    }

    /** Open a page as a browser and keep its anonymous-session cookie for the next requests. */
    private function open(string $path): AnonymousSession
    {
        $token = $this->get($path, ['User-Agent' => self::BROWSER])->assertOk()->getCookie(AnonymousSession::COOKIE, false)?->getValue();
        if ($token) {
            $this->withUnencryptedCookie(AnonymousSession::COOKIE, $token);
        }

        return AnonymousSession::where('token', $token ?? $this->unencryptedCookies[AnonymousSession::COOKIE])->firstOrFail();
    }

    private function cube(): UploadedFile
    {
        $path = sys_get_temp_dir().'/mp_audit_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20.0);

        return new UploadedFile($path, 'grandmas-secret-name.stl', null, null, true);
    }

    /** @return list<string> the types of all events, in order */
    private function types(): array
    {
        return Event::orderBy('id')->pluck('type')->all();
    }

    public function test_the_way_to_an_order_paid_by_card_is_measured_step_by_step_and_the_payment_only_by_the_bank(): void
    {
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $anon = $this->open('/tools/vase');
        $ua = ['User-Agent' => self::BROWSER];

        // the model: uploaded and ready (the sync queue processed it), told once however often the browser asks
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => $this->cube()], $ua)->assertCreated()->json('file.uuid');
        $this->getJson("/api/files/{$uuid}", $ua)->assertOk();
        $this->getJson("/api/files/{$uuid}", $ua)->assertOk();
        $this->assertSame(1, Event::where('type', 'model_ready')->count());
        $this->assertSame(['kind' => 'upload'], Event::where('type', 'model_ready')->first()->meta);

        // the exact price, once
        $token = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'], $ua)->assertCreated()->json('calculation.token');
        $this->getJson("/api/calculations/{$token}", $ua)->assertOk();
        $this->assertSame(1, Event::where('type', 'price_shown')->count());
        $this->assertSame('exact', Event::where('type', 'price_shown')->first()->meta['kind']);

        // somebody else opening the shared calculation adds nothing
        $this->app['auth']->forgetGuards();
        $this->unencryptedCookies = [];
        $this->getJson("/api/calculations/{$token}", $ua)->assertOk();
        $this->assertSame(1, Event::where('type', 'price_shown')->count());
        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $anon->token);

        // the order: created, the customer goes to pay by card; coming back from the gateway pays nothing
        $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid], $ua)->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $state = $this->getJson("/farm/orders/{$order->token}/status", $ua)->assertOk()->json();
        $this->postJson("/farm/orders/{$order->token}/checkout", ['slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total'], 'note' => 'na poličku k babičce'], $ua)
            ->assertOk()->assertJsonStructure(['checkout_url']);
        $this->get("/farm/orders/{$order->token}?paid=".Payment::latest('id')->value('id'), $ua)->assertOk();
        $this->assertEquals(['kind' => 'card', 'order' => $order->id], Event::where('type', 'checkout_started')->first()->meta);
        $this->assertSame(0, Event::where('type', 'order_paid')->count(), 'the return from the gateway is no payment');

        // the bank confirms (a request of the gateway, no visitor's cookie), twice: one paid order, in the customer's visit
        $this->app['auth']->forgetGuards();
        $this->unencryptedCookies = [];
        $payment = Payment::latest('id')->firstOrFail();
        foreach ([1, 2] as $_) {
            $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        }
        $paid = Event::where('type', 'order_paid')->get();
        $this->assertCount(1, $paid);
        $this->assertSame($anon->id, $paid[0]->session_id);
        $this->assertSame($user->id, $paid[0]->user_id);
        $this->assertSame('card', $paid[0]->meta['kind']);
        $this->assertSame($order->id, $paid[0]->meta['order']);

        // the audit's way through, all of it one visitor on a computer
        $stats = app(Funnel::class)->compute(30);
        $this->assertSame([1, 1, 1, 1, 1, 1], array_column($stats['paths']['order'], 'sessions'));
        $this->assertSame(1, $stats['audit']['splits']['device']['desktop']['paid']);
        $this->assertSame(['n' => 1, 'k' => 1, 'rate' => 100.0], $stats['audit']['shares']['paid_of_started']);
        $this->assertSame(1, $stats['audit']['splits']['entry']['file']['sessions']);

        // nothing but numbers and codes: no file name, no note, no e-mail
        foreach (Event::all() as $event) {
            $meta = (array) $event->meta;
            $this->assertSame([], array_diff(array_keys($meta), Track::META), "event {$event->type} has a key outside Track::META");
            $this->assertSame($meta, Track::clean($meta), "event {$event->type} keeps something that is not a number or a code");
        }
        $all = json_encode(Event::all()->toArray());
        foreach (['grandmas-secret-name', 'babičce', 'customer@example.com'] as $private) {
            $this->assertStringNotContainsString($private, $all);
        }
    }

    public function test_an_order_paid_from_credit_is_measured_as_credit(): void
    {
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        $this->open('/tools/vase');
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => $this->cube()])->assertCreated()->json('file.uuid');
        $this->post('/account/credit', ['amount' => 1000])->assertRedirect();
        $this->postJson('/webhooks/payments/fake', ['ref' => Payment::latest('id')->value('gateway_ref'), 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
        $order = FarmOrder::where('token', basename($this->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url')))->firstOrFail();
        $state = $this->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->postJson("/farm/orders/{$order->token}/pay", ['slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total']])->assertOk();

        $this->assertSame('credit', Event::where('type', 'checkout_started')->first()->meta['kind']);
        $this->assertSame('credit', Event::where('type', 'order_paid')->first()->meta['kind']);
    }

    public function test_our_test_runs_write_nothing_by_cookie_or_by_header(): void
    {
        // a script with the cookie: a page, its call home, an upload
        $this->withUnencryptedCookie(Track::TEST_COOKIE, '1');
        $anon = $this->open('/tools/vase');
        $this->post('/api/seen', ['path' => '/tools/vase'], ['User-Agent' => self::BROWSER])->assertNoContent();
        $this->postJson('/api/uploads', ['file' => $this->cube()], ['User-Agent' => self::BROWSER])->assertCreated();
        $this->assertSame([], $this->types());
        $this->assertTrue($anon->fresh()->staff, 'the browser is remembered like the staff\'s');

        // a fresh browser with the header only
        $this->unencryptedCookies = [];
        $this->get('/tools/box', ['User-Agent' => self::BROWSER, Track::TEST_HEADER => 'review'])->assertOk();
        $this->assertSame([], $this->types());

        // a browser that was counted, then says it is a test: what it did leaves the numbers
        $this->unencryptedCookies = [];
        $this->flushSession();
        $this->app['session']->flush();
        $this->open('/tools/vase');
        $this->post('/api/seen', ['path' => '/tools/vase'], ['User-Agent' => self::BROWSER])->assertNoContent();
        $this->assertSame(1, app(Funnel::class)->compute(30)['sessions']);
        $this->withUnencryptedCookie(Track::TEST_COOKIE, '1');
        $this->get('/tools/box', ['User-Agent' => self::BROWSER])->assertOk();
        $this->assertSame(0, app(Funnel::class)->compute(30)['sessions']);
        $this->assertSame(0, Event::people()->count());
    }

    public function test_an_event_keeps_numbers_and_codes_only(): void
    {
        $this->assertSame(
            ['kind' => 'stl', 'value' => 12.5, 'order' => 7, 'path' => '/tools/vase', 'js' => true],
            Track::clean(['kind' => 'stl', 'value' => 12.5, 'order' => 7, 'path' => '/tools/vase', 'js' => true,
                'note' => 'k narozeninám', 'email' => 'a@b.cz', 'printer' => 'Ahoj <b>světe</b>', 'tool' => ['x'], 'prompt' => 'a cat'])
        );
    }

    public function test_the_audit_splits_visitors_by_device_newness_and_way_in_with_their_sample_size(): void
    {
        $at = now()->subHour();
        $make = function (string $agent, array $events, ?\DateTimeInterface $earlier = null) use ($at): AnonymousSession {
            $anon = AnonymousSession::start('127.0.0.1', $agent);
            if ($earlier) {
                Event::create(['session_id' => $anon->id, 'type' => Event::VISIT, 'meta' => ['js' => true]])->forceFill(['created_at' => $earlier])->save();
            }
            Event::create(['session_id' => $anon->id, 'type' => Event::VISIT, 'meta' => ['js' => true]])->forceFill(['created_at' => $at])->save();
            foreach ($events as [$type, $meta]) {
                Event::create(['session_id' => $anon->id, 'type' => $type, 'subject_type' => $type === 'view' ? 'tool' : null, 'meta' => $meta ?: null])->forceFill(['created_at' => $at])->save();
            }

            return $anon;
        };
        // a phone: own file, price, an order started and left
        $make(self::PHONE, [['upload', ['kind' => 'stl']], ['model_ready', ['kind' => 'upload']], ['calculation', []], ['price_shown', ['kind' => 'exact']], ['order_created', ['order' => 1]]]);
        // a computer that was here before: a text, the generation failed
        $make(self::BROWSER, [['generate', ['kind' => 'text']], ['failed', ['step' => 'generate', 'kind' => 'text']]], now()->subDays(40));
        // a computer: a tool, its download failed once and then went through
        $make(self::BROWSER, [['view', ['tool' => 'vase']], ['failed', ['step' => 'download', 'code' => 422]], ['download', ['kind' => '3mf', 'tool' => 'vase']]]);

        $a = app(Funnel::class)->compute(30)['audit'];
        $this->assertSame(1, $a['splits']['device']['mobile']['sessions']);
        $this->assertSame(2, $a['splits']['device']['desktop']['sessions']);
        $this->assertSame(1, $a['splits']['device']['mobile']['started']);
        $this->assertSame(1, $a['splits']['visitor']['returning']['sessions']);
        $this->assertSame(2, $a['splits']['visitor']['new']['sessions']);
        $this->assertSame([1, 1, 1], [$a['splits']['entry']['file']['sessions'], $a['splits']['entry']['text']['sessions'], $a['splits']['entry']['tool']['sessions']]);
        $this->assertSame(2, $a['splits']['device']['desktop']['failed']);

        $this->assertSame(['n' => 3, 'k' => 1, 'rate' => 33.3], $a['shares']['price']);
        $this->assertSame(['n' => 1, 'k' => 0, 'rate' => 0.0], $a['shares']['paid_of_started']);
        $this->assertSame(['n' => 1, 'k' => 1, 'rate' => 100.0], $a['shares']['download']);
        $this->assertSame(['n' => 2, 'k' => 1, 'rate' => 50.0], $a['errors']['model']);
        $this->assertSame(['n' => 1, 'k' => 1, 'rate' => 100.0], $a['errors']['download']);
        $this->assertSame(['n' => 1, 'k' => 0, 'rate' => 0.0], $a['errors']['calculation']);

        $this->actingAs(tap(User::factory()->create())->setRole(User::ROLE_ADMIN, true))->get('/admin/stats')->assertOk()
            ->assertSee('Měření podle auditu')->assertSee('n = 3');
    }
}
