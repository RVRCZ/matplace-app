<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The registration against robots that fill in the real form (docs/AH.md §10): limits by address, the time the form
 * took, the verification mail, Turnstile behind its keys, and pruning the accounts nobody confirmed.
 */
class RegisterGuardTest extends TestCase
{
    use RefreshDatabase;

    /** One registration from an address, as a browser sends it; signed out afterwards so the next one can come. */
    private function register(string $email, string $ip = '31.56.204.42', array $extra = []): TestResponse
    {
        $r = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/register', ['name' => 'Test', 'email' => $email, 'password' => 'tajneheslo1', 'terms' => 1] + $extra + $this->registerStamp());
        Auth::logout();
        $this->flushSession();

        return $r;
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
