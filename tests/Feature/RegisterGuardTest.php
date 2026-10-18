<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use App\Support\RegisterGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The registration against robots that fill in the real form (docs/AH.md §10): limits by address, the time the form
 * took, the verification mail, Turnstile behind its keys, and pruning the accounts nobody confirmed.
 */
class RegisterGuardTest extends TestCase
{
    use RefreshDatabase;

    /** One registration from an address, as a browser sends it, by a visitor who is signed out (the last one signed in). */
    private function register(string $email, string $ip = '31.56.204.42', array $extra = []): TestResponse
    {
        Auth::logout();
        $this->flushSession();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/register', ['name' => 'Test', 'email' => $email, 'password' => 'tajneheslo1', 'terms' => 1] + $extra + $this->registerStamp());
    }

    public function test_an_address_makes_three_accounts_an_hour_and_five_a_day(): void
    {
        foreach ([1, 2, 3] as $n) {
            $this->register("bot{$n}@gmail.com")->assertRedirect(route('account'));
        }
        // the fourth within the hour: a page that says why, the form still filled in, no account
        $fourth = $this->register('bot4@gmail.com')->assertStatus(429);
        $fourth->assertSee(__('auth.guard.limited'))->assertSee('value="bot4@gmail.com"', false)->assertSee('name="password"', false);
        $this->assertSame(3, User::count());
        // another address is not affected
        $this->register('human@example.com', '89.24.10.7')->assertRedirect(route('account'));

        // an hour later two more, then the day is full
        $this->travel(61)->minutes();
        $this->register('bot5@gmail.com')->assertRedirect(route('account'));
        $this->register('bot6@gmail.com')->assertRedirect(route('account'));
        $this->register('bot7@gmail.com')->assertStatus(429);
        $this->assertSame(6, User::count());
    }

    public function test_the_form_must_have_been_open_for_four_seconds_and_at_most_two_hours(): void
    {
        $form = (string) $this->get('/register')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input type="hidden" name="t" value="[^"]{20,}">/', $form, 'the form carries its signed time');

        $send = fn (array $t) => $this->withServerVariables(['REMOTE_ADDR' => '31.57.107.185'])->from('/register')
            ->post('/register', ['name' => 'Test', 'email' => 'fast@gmail.com', 'password' => 'tajneheslo1', 'terms' => 1] + $t);
        $now = now()->getTimestamp();
        // posted a second after the page came: back to the form, filled in, no account
        $send(['t' => RegisterGuard::stamp($now - 1)])->assertRedirect('/register')->assertSessionHasErrors('t')
            ->assertSessionHasInput('email', 'fast@gmail.com')->assertSessionMissing('_old_input.password');
        $this->assertSame(__('auth.guard.retry'), session('errors')->first('t'));
        // a form left open for more than two hours, a missing and a made-up time: the same
        $send(['t' => RegisterGuard::stamp($now - 7201)])->assertSessionHasErrors('t');
        $send([])->assertSessionHasErrors('t');
        $send(['t' => base64_encode('register:'.($now - 60))])->assertSessionHasErrors('t');
        $this->assertSame(0, User::count());
        // the page shows why, and the form is filled in again
        $this->get('/register')->assertSee(__('auth.guard.retry'))->assertSee('value="fast@gmail.com"', false);
        // after five seconds it goes through
        $send(['t' => RegisterGuard::stamp($now - 5)])->assertRedirect(route('account'));
        $this->assertSame(1, User::count());
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertNotSame('auth.guard.retry', __('auth.guard.retry', [], $locale));
            $this->assertNotSame('auth.guard.limited', __('auth.guard.limited', [], $locale));
        }
    }

    public function test_only_the_first_registration_from_an_address_today_gets_the_mail_at_once(): void
    {
        Notification::fake();
        $this->register('first@example.com')->assertSessionHas('status', __('user.verify.registered', ['email' => 'first@example.com']));
        $first = User::where('email', 'first@example.com')->firstOrFail();
        Notification::assertSentTo($first, VerifyEmailAddress::class);

        // the second account from the same address the same day: made, but no mail to a stranger's inbox
        $this->register('second@gmail.com')->assertRedirect(route('account'))
            ->assertSessionHas('status', __('user.verify.registered_ask', ['email' => 'second@gmail.com']));
        $second = User::where('email', 'second@gmail.com')->firstOrFail();
        Notification::assertNotSentTo($second, VerifyEmailAddress::class);
        // another address mails at once again
        $this->register('neighbour@example.com', '89.24.10.7');
        Notification::assertSentTo(User::where('email', 'neighbour@example.com')->firstOrFail(), VerifyEmailAddress::class);

        // the person behind the second account asks for it on the account page and gets it
        $this->actingAs($second)->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($second, VerifyEmailAddress::class);

        // the next day the address mails at once again
        $this->travel(1)->days();
        $this->register('tomorrow@example.com');
        Notification::assertSentTo(User::where('email', 'tomorrow@example.com')->firstOrFail(), VerifyEmailAddress::class);
    }

    public function test_turnstile_waits_behind_its_keys_and_then_asks_cloudflare(): void
    {
        // one stub for the whole test (the first matching stub wins); what it answers changes below
        $answer = fn () => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]);
        Http::fake(['challenges.cloudflare.com/*' => function () use (&$answer) {
            return $answer();
        }]);
        // off (no keys): the form as it was, nothing asked
        $this->get('/register')->assertOk()->assertDontSee('cf-turnstile', false)->assertDontSee('challenges.cloudflare.com', false);
        $this->register('plain@example.com')->assertRedirect(route('account'));
        Http::assertNothingSent();

        // on: the widget is on the form and Cloudflare decides
        config(['services.turnstile.site_key' => '0x4AAA-site', 'services.turnstile.secret' => '0x4AAA-secret']);
        Auth::logout();
        $this->get('/en/register')->assertOk()->assertSee('class="cf-turnstile" data-sitekey="0x4AAA-site" data-language="en"', false)
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);
        $this->register('robot@gmail.com', '95.182.114.251', ['cf-turnstile-response' => 'made-up'])->assertSessionHasErrors('turnstile');
        $this->register('robot2@gmail.com', '95.182.114.251')->assertSessionHasErrors('turnstile');       // no token at all
        $this->assertSame(0, User::where('email', 'like', 'robot%')->count());
        Http::assertSent(fn ($r) => $r->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify' && $r['secret'] === '0x4AAA-secret' && $r['response'] === 'made-up' && $r['remoteip'] === '95.182.114.251');

        $answer = fn () => Http::response(['success' => true]);
        $this->register('person@example.com', '95.182.114.251', ['cf-turnstile-response' => 'real-token'])->assertRedirect(route('account'));
        // Cloudflare down: let through (the limits and the signed time still stand), written to the log
        $answer = fn () => throw new ConnectionException('timeout');
        $this->register('during-outage@example.com', '95.182.114.251', ['cf-turnstile-response' => 'any'])->assertRedirect(route('account'));
    }

    public function test_a_mistake_in_the_form_is_not_a_registration_and_ipv6_counts_by_its_64(): void
    {
        // three failed attempts (a short password) leave the address its three registrations
        foreach ([1, 2, 3] as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => '95.182.89.76'])
                ->post('/register', ['name' => 'Eva', 'email' => "eva{$n}@example.com", 'password' => 'kratke', 'terms' => 1] + $this->registerStamp())
                ->assertSessionHasErrors('password');
        }
        $this->register('eva@example.com', '95.182.89.76')->assertRedirect(route('account'));

        // one /64 is one address: the fourth account from 2a13:7c00:5:29::/64 waits, whatever the last bits are
        foreach (['2a13:7c00:5:29::1', '2a13:7c00:5:29:aa::2', '2a13:7c00:5:29:ffff::3'] as $i => $ip) {
            $this->register("v6-{$i}@gmail.com", $ip)->assertRedirect(route('account'));
        }
        $this->register('v6-4@gmail.com', '2a13:7c00:5:29:1234::9')->assertStatus(429);
        $this->register('v6-other@gmail.com', '2a13:7c00:5:30::1')->assertRedirect(route('account'));
    }
}
