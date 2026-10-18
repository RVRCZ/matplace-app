<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\ModelFile;
use App\Models\OauthIdentity;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\VerifyEmailAddress;
use App\Support\RegisterGuard;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\MeshFixtures;
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

    /** @return array<string, User> the accounts of the prune tests: two robots and everything that must stay */
    private function accounts(): array
    {
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $old = now()->subDays(8);
        $make = fn (string $email, bool $verified = false, $at = null) => tap(User::factory()->unverified()->create(['email' => $email, 'name' => 'Test']), function (User $u) use ($verified, $at, $old) {
            $u->forceFill(['created_at' => $at ?? $old, 'email_verified_at' => $verified ? $old : null])->save();
            $u->setRole(User::ROLE_CUSTOMER, true);
        });
        $a = [
            'robot1' => $make('robot1@gmail.com'), 'robot2' => $make('robot2@gmail.com'),
            'fresh' => $make('fresh@gmail.com', false, now()->subDays(2)),          // not seven days old yet
            'verified' => $make('verified@example.com', true),
            'credit' => $make('credit@example.com'), 'model' => $make('model@example.com'), 'google' => $make('google@example.com'),
            'order' => $make('order@example.com'), 'designer' => $make('designer@example.com'),
        ];
        CreditTransaction::create(['user_id' => $a['credit']->id, 'type' => CreditTransaction::TYPE_TOPUP, 'amount' => 100, 'currency' => 'CZK']);
        ModelFile::create(['uuid' => (string) Str::uuid(), 'owner_user_id' => $a['model']->id, 'original_name' => 'a.stl', 'ext' => 'stl', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64), 'storage_path' => 'x', 'status' => ModelFile::STATUS_READY]);
        OauthIdentity::create(['user_id' => $a['google']->id, 'provider' => 'google', 'provider_id' => 'g-1']);
        $a['designer']->setRole(User::ROLE_DESIGNER, true);
        // an order the person never paid still keeps the account
        $path = sys_get_temp_dir().'/mp_prune_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $a['order']->forceFill(['email_verified_at' => now()])->save();          // ordering needs a confirmed e-mail
        $uuid = $this->actingAs($a['order'])->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->json('file.uuid');
        $this->actingAs($a['order'])->postJson('/farm/orders', ['file' => $uuid])->assertCreated();
        $a['order']->forceFill(['email_verified_at' => null])->save();
        ModelFile::where('uuid', $uuid)->update(['owner_user_id' => null]);       // the order alone, not the model, keeps it
        Auth::logout();

        return $a;
    }

    public function test_the_command_lists_and_deletes_only_unconfirmed_accounts_nobody_used(): void
    {
        $a = $this->accounts();
        $this->artisan('users:prune-unverified', ['--days' => 7, '--dry-run' => true])
            ->expectsOutputToContain('robot1@gmail.com')->expectsOutputToContain('2 unconfirmed accounts')->expectsOutputToContain('Nothing deleted (--dry-run).')
            ->doesntExpectOutputToContain('credit@example.com')->assertSuccessful();
        // without --force nothing goes either; --dry-run wins over --force
        $this->artisan('users:prune-unverified')->expectsOutputToContain('add --force')->assertSuccessful();
        $this->artisan('users:prune-unverified', ['--dry-run' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(9, User::count());

        $this->artisan('users:prune-unverified', ['--force' => true])->expectsOutputToContain('Deleted 2 accounts.')->assertSuccessful();
        $this->assertSame(['credit', 'designer', 'fresh', 'google', 'model', 'order', 'verified'], collect($a)->filter(fn ($u) => User::whereKey($u->id)->exists())->keys()->sort()->values()->all());
        $this->assertSame(0, UserRole::whereIn('user_id', [$a['robot1']->id, $a['robot2']->id])->count());
        // --days counts: two days old goes with --days=1
        $this->artisan('users:prune-unverified', ['--days' => 1, '--force' => true])->expectsOutputToContain('Deleted 1 accounts.');
        $this->assertFalse(User::whereKey($a['fresh']->id)->exists());
    }

    public function test_the_admin_sees_how_many_and_deletes_them_with_a_confirmation(): void
    {
        $a = $this->accounts();
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $page = $this->actingAs($admin)->get('/admin/users?unverified=1')->assertOk();
        $page->assertSee('Smazat neověřené (2)')->assertSee('id="users-prune"', false)->assertSee('neověřený')->assertDontSee('admin-'.$admin->email);
        // a customer cannot: the admin gate sends them away and nothing is deleted (the count below)
        $this->actingAs($a['verified'])->post('/admin/users/prune-unverified', ['confirm' => 1])->assertRedirect();
        // without the confirmation nothing happens
        $this->actingAs($admin)->post('/admin/users/prune-unverified')->assertSessionHasErrors('confirm');
        $this->assertSame(10, User::count());
        $this->actingAs($admin)->post('/admin/users/prune-unverified', ['confirm' => 1])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(8, User::count());
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Smazat neověřené (0)');
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
