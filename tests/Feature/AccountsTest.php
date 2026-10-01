<?php

namespace Tests\Feature;

use App\Domain\Farm\Wallet;
use App\Models\AnonymousSession;
use App\Models\Calculation;
use App\Models\CreditTransaction;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\OauthIdentity;
use App\Models\Payment;
use App\Models\PrinterProfile;
use App\Models\User;
use App\Notifications\AccountMail;
use App\Notifications\ConfirmAccountDeletion;
use App\Notifications\ConfirmNewEmail;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * One account = a customer. Anonymous work is claimed on registration, the e-mail is verified before the first
 * order, the e-mail changes only after the new address confirms, deleting scrubs the person and keeps the books.
 */
class AccountsTest extends TestCase
{
    use RefreshDatabase;

    private function model(User $user, array $attributes = []): ModelFile
    {
        return ModelFile::create($attributes + [
            'uuid' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'original_name' => 'cube.stl', 'ext' => 'stl', 'size_bytes' => 684,
            'sha256' => str_repeat('0', 64), 'storage_path' => 'files/x/original.stl', 'stl_path' => 'files/x/model.stl', 'status' => ModelFile::STATUS_READY,
        ]);
    }

    private function order(User $user, ModelFile $file, string $status, float $price = 150): FarmOrder
    {
        $material = FarmMaterial::firstOrCreate(['code' => 'PLA'], ['name' => 'PLA', 'filament_profile' => 'filament_pla.json', 'price_per_gram' => 1.2]);

        return FarmOrder::create(['token' => Str::random(32), 'user_id' => $user->id, 'model_file_id' => $file->id, 'status' => $status, 'farm_material_id' => $material->id, 'price_total' => $price]);
    }

    private function credit(User $user, float $amount): void
    {
        app(Wallet::class)->topUp(Payment::create(['user_id' => $user->id, 'gateway' => 'fake', 'gateway_ref' => 'cs_'.Str::random(6), 'amount' => $amount, 'status' => Payment::STATUS_PAID]));
    }

    /** The link a notification carries, rendered in the receiver's language. */
    private function linkOf(object $notification, object $notifiable): string
    {
        $locale = $notification->locale ?? (method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : 'cs');
        $previous = app()->getLocale();
        app()->setLocale($locale);
        $url = $notification->toMail($notifiable)->actionUrl;
        app()->setLocale($previous);

        return $url;
    }

    private function google(string $id, string $email, string $name = 'Roman'): void
    {
        $remote = (new SocialiteUser)->map(['id' => $id, 'email' => $email, 'name' => $name]);
        // a fresh factory every time: a later call must not be answered by an earlier Google account
        $factory = \Mockery::mock(Factory::class);
        $factory->shouldReceive('driver')->with('google')->andReturn(\Mockery::mock(['user' => $remote, 'redirect' => redirect('https://accounts.google.com/o/oauth2/auth')]));
        Socialite::swap($factory);
    }

    // ── registration and verification ────────────────────────────────────────

    public function test_registration_claims_anonymous_work_and_sends_the_verification_mail(): void
    {
        Notification::fake();
        Storage::fake('models');
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        // The test client keeps no cookies between requests and JSON requests send none without withCredentials():
        // carry the anonymous session cookie by hand, as a browser would.
        $this->withCredentials();
        $uuid = $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $session = AnonymousSession::findOrFail(ModelFile::first()->anonymous_session_id);
        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $session->token)->postJson('/api/calculations', ['file' => $uuid])->assertCreated();
        $this->assertNull(Calculation::first()->owner_user_id);

        // ?role=printer comes from old links: it is ignored, nobody becomes a printer
        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $session->token)
            ->post('/en/register?role=printer', ['name' => 'Roman', 'email' => 'Roman@Example.com', 'password' => 'secret123', 'terms' => 1, 'role' => 'printer'])
            ->assertRedirect('/en/account')->assertSessionHas('status');
        $user = User::where('email', 'roman@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->isPrinter());
        $this->assertSame(0, PrinterProfile::count());
        $this->assertSame('en', $user->locale, 'the language of the registration page becomes the language of e-mails');
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame($user->id, Calculation::first()->owner_user_id);
        $this->assertSame($user->id, ModelFile::first()->owner_user_id);
        $this->assertSame($user->id, $session->fresh()->claimed_by_user_id);

        Notification::assertSentTo($user, VerifyEmailAddress::class, function (VerifyEmailAddress $n) use ($user) {
            $this->assertStringContainsString('/en/email/verify/'.$user->id.'/', $this->linkOf($n, $user));

            return true;
        });

        // the claimed upload is in "My models"
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('cube.stl')->assertSee('data-model="'.$uuid.'"', false);
        $this->actingAs($user)->get('/account/models?origin=upload')->assertOk()->assertSee('cube.stl');
        $this->actingAs($user)->get('/account/models?origin=generated')->assertOk()->assertDontSee('cube.stl');
    }

    public function test_the_link_from_the_mail_verifies_and_a_wrong_or_old_one_does_not(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $url = $this->linkOf(new VerifyEmailAddress, $user);

        // tampered with
        $this->get(str_replace('/email/verify/'.$user->id.'/', '/email/verify/'.($user->id + 1).'/', $url))->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        // opened in a browser where nobody is logged in: verified, then the usual login
        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('status', __('user.verify.done_login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 24 hours later the link is dead
        $late = User::factory()->unverified()->create();
        $old = $this->linkOf(new VerifyEmailAddress, $late);
        $this->travel(25)->hours();
        $this->actingAs($late)->get($old)->assertRedirect(route('account'))->assertSessionHas('error', __('user.verify.invalid'));
        $this->assertFalse($late->fresh()->hasVerifiedEmail());
    }

    public function test_an_unverified_account_cannot_order_or_top_up_and_can_ask_for_the_link_again(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $file = $this->model($user);

        $this->actingAs($user)->get('/farm')->assertOk()->assertSee('id="verify-banner"', false);
        $this->actingAs($user)->postJson('/farm/orders', ['file' => $file->uuid])->assertStatus(403)->assertJsonPath('error', 'email_unverified');
        $this->actingAs($user)->from('/farm')->post('/farm/orders', ['file' => $file->uuid])->assertRedirect('/farm')->assertSessionHas('error', __('user.verify.needed'));
        $this->actingAs($user)->from('/account/credit')->post('/account/credit', ['amount' => 200])->assertRedirect('/account/credit')->assertSessionHas('error');
        $this->assertSame(0, FarmOrder::count());
        $this->assertSame(0, Payment::count());

        // three links an hour, the fourth request is refused
        foreach ([1, 2, 3] as $i) {
            $this->actingAs($user)->from('/account')->post('/email/verification-notification')->assertRedirect('/account')->assertSessionHas('status');
        }
        $this->actingAs($user)->post('/email/verification-notification')->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmailAddress::class, 3);

        // once verified, the wall is gone (the order now fails only because the farm has no printer)
        $user->markEmailAsVerified();
        $this->actingAs($user)->get('/farm')->assertOk()->assertDontSee('id="verify-banner"', false);
        $this->assertNotSame(403, $this->actingAs($user)->postJson('/farm/orders', ['file' => $file->uuid])->status());
    }

    public function test_google_accounts_are_verified_from_the_start_and_an_unproven_password_stops_working(): void
    {
        $this->google('g-1', 'new@example.com');
        $this->get('/auth/google/callback')->assertRedirect(route('account'));
        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->hasVerifiedEmail());
        $this->post('/logout');

        // somebody registered victim@example.com with their own password and never verified it
        $squatter = User::factory()->unverified()->create(['email' => 'victim@example.com', 'password' => 'squatter-pass']);
        $this->google('g-2', 'victim@example.com');
        $this->get('/auth/google/callback')->assertRedirect(route('account'));
        $squatter->refresh();
        $this->assertTrue($squatter->hasVerifiedEmail());
        $this->assertFalse($squatter->hasPassword(), 'the password nobody proved must not open the account of the real owner');
        $this->post('/logout');
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'squatter-pass'])->assertSessionHasErrors('email');
    }

    // ── login ────────────────────────────────────────────────────────────────

    public function test_login_returns_to_the_tool_the_visitor_came_from(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect(route('account'));
        $this->post('/logout');

        $this->withHeader('Referer', url('/en/tools/sign?preset=keyring'))->get('/en/login')->assertOk();
        $this->flushHeaders();
        $this->post('/en/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect(url('/en/tools/sign?preset=keyring'));
        $this->post('/logout');

        // somebody else's site in the Referer is not a place to return to
        $this->withHeader('Referer', 'https://example.com/evil')->get('/login')->assertOk();
        $this->flushHeaders();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect(route('account'));
    }

    public function test_printer_area_stays_behind_its_role(): void
    {
        $this->get('/printer')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/printer')->assertRedirect(route('account'));
    }

    public function test_password_reset_mail_speaks_the_language_of_the_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['locale' => 'es']);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $n) use ($user) {
            app()->setLocale('es');
            $mail = $n->toMail($user);
            app()->setLocale('cs');
            $this->assertStringContainsString('Nueva contraseña', $mail->subject);
            $this->assertStringContainsString('/es/reset-password/', $mail->actionUrl);
            $this->assertTrue(Password::tokenExists($user, basename(parse_url($mail->actionUrl, PHP_URL_PATH))));

            return true;
        });
    }

    // ── the account pages ────────────────────────────────────────────────────

    public function test_the_account_shows_prints_models_and_calculations_in_this_order_and_never_mentions_printers(): void
    {
        config(['features.marketplace' => false]);
        $user = User::factory()->create();
        $file = $this->model($user, ['original_name' => 'drak.stl']);
        $this->order($user, $file, FarmOrder::STATUS_DONE, 240)->forceFill(['number' => 'F26-000077'])->save();
        Calculation::create(['token' => Str::random(12), 'model_file_id' => $file->id, 'owner_user_id' => $user->id, 'params' => ['material' => 'PLA', 'quantity' => 2], 'params_hash' => md5('x'), 'status' => 'done']);

        $page = $this->actingAs($user)->get('/account')->assertOk();
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'id="models"'), strpos($html, 'id="prints"'));
        $this->assertLessThan(strpos($html, 'id="calculations"'), strpos($html, 'id="models"'));
        $page->assertSee('F26-000077')->assertSee('240')->assertSee(__('farm.status.done'))->assertSee('drak.stl')
            ->assertSee('data-pick-printer="'.$file->uuid.'"', false)->assertSee(route('farm.start', ['file' => $file->uuid]), false);

        foreach (['/account', '/account/profile', '/account/models', '/account/orders', '/account/calculations', '/', '/tools', '/farm'] as $url) {
            $this->assertDoesNotMatchRegularExpression('/tiskař/iu', $this->actingAs($user)->get($url)->assertOk()->getContent(), $url);
        }
        $this->post('/logout');
        foreach (['/login', '/register', '/'] as $url) {
            $this->assertDoesNotMatchRegularExpression('/tiskař/iu', $this->get($url)->assertOk()->getContent(), $url);
        }
        // the three languages render
        foreach (['en', 'es'] as $locale) {
            foreach (['/account', '/account/profile', '/account/models', '/account/orders', '/account/calculations'] as $url) {
                $this->actingAs($user)->get('/'.$locale.$url)->assertOk()->assertSee('<html lang="'.$locale.'"', false);
            }
        }
    }

    public function test_the_list_of_prints_moved_to_the_account_and_filters_by_state(): void
    {
        $user = User::factory()->create();
        $this->order($user, $this->model($user, ['original_name' => 'hotovy.stl']), FarmOrder::STATUS_DONE);
        $this->order($user, $this->model($user, ['original_name' => 'rozdelany.stl']), FarmOrder::STATUS_PRINTING);

        $this->actingAs($user)->get('/farm/orders')->assertStatus(301)->assertRedirect('/account/orders');
        $this->actingAs($user)->get('/en/farm/orders')->assertStatus(301)->assertRedirect('/en/account/orders');
        $this->actingAs($user)->get('/account/orders')->assertOk()->assertSee('hotovy.stl')->assertSee('rozdelany.stl');
        $this->actingAs($user)->get('/account/orders?status=open')->assertOk()->assertSee('rozdelany.stl')->assertDontSee('hotovy.stl');
        $this->actingAs($user)->get('/account/orders?status=done')->assertOk()->assertSee('hotovy.stl')->assertDontSee('rozdelany.stl');
        $this->actingAs($user)->get('/account/orders?status=cancelled')->assertOk()->assertSee(__('user.orders.empty'));
    }

    public function test_a_model_can_be_deleted_unless_a_live_order_needs_it(): void
    {
        Storage::fake('models');
        $user = User::factory()->create();
        $free = $this->model($user, ['original_name' => 'volny.stl']);
        $printed = $this->model($user, ['original_name' => 'tisteny.stl']);
        $dropped = $this->model($user, ['original_name' => 'zruseny.stl']);
        $this->order($user, $printed, FarmOrder::STATUS_DONE);
        $this->order($user, $dropped, FarmOrder::STATUS_CANCELLED);

        $this->actingAs($user)->get('/account/models')->assertOk()->assertSee(__('user.models.locked_order'));
        $this->actingAs($user)->from('/account/models')->post("/account/models/{$printed->uuid}/delete")->assertRedirect('/account/models')->assertSessionHas('error', __('user.models.locked_order'));
        $this->assertNull($printed->fresh()->deleted_at);

        $this->actingAs($user)->from('/account/models')->post("/account/models/{$free->uuid}/delete")->assertSessionHas('status');
        $this->assertNull(ModelFile::find($free->id), 'nothing points to it: the row goes');

        // a cancelled order keeps its name: the model becomes an empty shell, invisible in the list
        $this->actingAs($user)->post("/account/models/{$dropped->uuid}/delete")->assertSessionHas('status');
        $shell = ModelFile::findOrFail($dropped->id);
        $this->assertNotNull($shell->deleted_at);
        $this->assertFalse($shell->isReady());
        $this->actingAs($user)->get('/account/models')->assertOk()->assertDontSee('zruseny.stl')->assertDontSee('volny.stl')->assertSee('tisteny.stl');

        // somebody else's model is not there to delete
        $this->actingAs(User::factory()->create())->post("/account/models/{$printed->uuid}/delete")->assertNotFound();
    }

    public function test_the_owner_s_browser_stores_a_picture_of_the_model(): void
    {
        Storage::fake('models');
        $user = User::factory()->create();
        $file = $this->model($user);
        $this->actingAs($user)->get('/account/models')->assertOk()->assertSee('data-thumb-stl=', false);

        $png = sys_get_temp_dir().'/mp_prev_'.uniqid().'.png';
        imagepng(imagecreatetruecolor(48, 36), $png);
        $shot = fn () => new UploadedFile($png, 'preview.png', 'image/png', null, true);
        $this->actingAs(User::factory()->create())->postJson("/api/files/{$file->uuid}/preview", ['image' => $shot()])->assertForbidden();
        $this->actingAs($user)->postJson("/api/files/{$file->uuid}/preview", ['image' => $shot()])->assertCreated();
        $this->assertSame('files/'.$file->uuid.'/preview.webp', $file->fresh()->preview_path);
        $this->get("/api/files/{$file->uuid}/preview.webp")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($user)->get('/account/models')->assertOk()->assertDontSee('data-thumb-stl=', false)->assertSee("/api/files/{$file->uuid}/preview.webp", false);
    }

    public function test_profile_keeps_the_delivery_address_the_pickup_point_and_the_language_of_mails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account/profile')->assertOk()->assertSee('Španělsko')->assertSee(__('user.profile.mail_language'));
        $this->actingAs($user)->post('/account/profile', [
            'name' => 'Roman', 'phone' => '+420 777 000 111', 'delivery_name' => 'Roman Vzor', 'street' => 'Dlouhá 1', 'city' => 'Plzeň', 'zip' => '301 00', 'country' => 'ES',
            'locale' => 'es', 'notify_email' => 1, 'pickup_point' => ['id' => '1234', 'name' => 'Z-BOX Plzeň, Dlouhá 1', 'carrier_id' => '', 'country' => 'cz'],
        ])->assertSessionHas('status');
        $user->refresh();
        $this->assertSame(['Roman Vzor', 'ES', 'es'], [$user->delivery_name, $user->country, $user->locale]);
        $this->assertSame(['id' => '1234', 'name' => 'Z-BOX Plzeň, Dlouhá 1', 'carrier_id' => null, 'country' => 'CZ'], $user->pickup_point);
        $this->assertSame('Roman Vzor', $user->recipientName());

        // a country we do not know is refused; an emptied point is forgotten
        $this->actingAs($user)->post('/account/profile', ['name' => 'Roman', 'country' => 'XX'])->assertSessionHasErrors('country');
        $this->actingAs($user)->post('/account/profile', ['name' => 'Roman', 'country' => 'CZ', 'pickup_point' => ['id' => '']])->assertSessionHas('status');
        $this->assertNull($user->fresh()->pickup_point);
        // the language of mails does not change the language of pages
        $this->actingAs($user)->get('/account/profile')->assertSee('<html lang="cs"', false);
    }

    // ── changing the e-mail ──────────────────────────────────────────────────

    public function test_the_e_mail_changes_only_after_the_new_address_confirms(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'secret123', 'locale' => 'en']);
        User::factory()->create(['email' => 'taken@example.com']);

        $try = fn (array $data) => $this->actingAs($user)->post('/account/email', $data);
        $try(['new_email' => 'taken@example.com', 'current_password' => 'secret123'])->assertSessionHasErrorsIn('email', ['new_email' => __('user.email.taken')]);
        $try(['new_email' => 'new@example.com', 'current_password' => 'wrong'])->assertSessionHasErrorsIn('email', ['new_email' => __('user.email.wrong_password')]);
        $try(['new_email' => 'OLD@example.com', 'current_password' => 'secret123'])->assertSessionHasErrorsIn('email', ['new_email' => __('user.email.same')]);
        $this->assertNull($user->fresh()->pending_email);
        Notification::assertNothingSent();

        $try(['new_email' => 'New@Example.com', 'current_password' => 'secret123'])->assertSessionHas('status');
        $user->refresh();
        $this->assertSame(['old@example.com', 'new@example.com'], [$user->email, $user->pending_email]);
        $this->actingAs($user)->get('/account/profile')->assertSee(__('user.email.pending', ['email' => 'new@example.com']));

        // the new address gets the link (in the account's language), the old one hears about the request
        $link = null;
        Notification::assertSentTo(new AnonymousNotifiable, ConfirmNewEmail::class, function (ConfirmNewEmail $n, array $channels, AnonymousNotifiable $to) use (&$link) {
            $this->assertSame('new@example.com', $to->routes['mail']);
            $this->assertSame('en', $n->locale);
            $link = $this->linkOf($n, $to);

            return true;
        });
        $this->assertStringContainsString('/en/account/email/confirm/', $link);
        Notification::assertSentTo($user, AccountMail::class);

        // confirmed from another browser (nobody logged in there)
        $this->post('/logout');
        $this->get($link)->assertRedirect('/en/login')->assertSessionHas('status');
        $user->refresh();
        $this->assertSame(['new@example.com', null, null], [$user->email, $user->pending_email, $user->pending_email_token]);
        $this->assertTrue($user->hasVerifiedEmail());
        // the link works once
        $this->get($link)->assertSessionHas('error', __('user.email.expired', [], 'en'));
        $this->post('/login', ['email' => 'new@example.com', 'password' => 'secret123'])->assertRedirect();
    }

    public function test_a_pending_e_mail_change_expires_can_be_cancelled_and_loses_to_whoever_took_the_address(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'secret123']);
        $ask = function (string $email) use ($user): string {
            $this->actingAs($user)->post('/account/email', ['new_email' => $email, 'current_password' => 'secret123'])->assertSessionHas('status');
            $link = '';
            Notification::assertSentTo(new AnonymousNotifiable, ConfirmNewEmail::class, function (ConfirmNewEmail $n, array $c, AnonymousNotifiable $to) use (&$link, $email) {
                if ($to->routes['mail'] === $email) {
                    $link = $this->linkOf($n, $to);
                }

                return true;
            });

            return $link;
        };

        // cancelled by the owner
        $link = $ask('a@example.com');
        $this->actingAs($user)->post('/account/email/cancel')->assertSessionHas('status');
        $this->get($link)->assertSessionHas('error');
        $this->assertSame('old@example.com', $user->fresh()->email);

        // 25 hours later
        $link = $ask('b@example.com');
        $this->travel(25)->hours();
        $this->get($link)->assertSessionHas('error');
        $this->assertSame(['old@example.com', null], [$user->fresh()->email, $user->fresh()->pending_email]);
        $this->travelBack();

        // somebody registered the address in the meantime
        $link = $ask('c@example.com');
        User::factory()->create(['email' => 'c@example.com']);
        $this->get($link)->assertSessionHas('error');
        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    // ── linked sign-ins ──────────────────────────────────────────────────────

    public function test_the_last_way_into_an_account_cannot_be_unlinked(): void
    {
        $user = User::factory()->create(['password' => null]);
        $google = OauthIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);

        $this->actingAs($user)->post("/account/logins/{$google->id}/disconnect")->assertSessionHas('error', __('user.logins.last'));
        $this->assertSame(1, $user->oauthIdentities()->count());

        // with a second sign-in the first one can go
        $facebook = OauthIdentity::create(['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => 'f-1']);
        $this->actingAs($user)->post("/account/logins/{$google->id}/disconnect")->assertSessionHas('status');
        $this->assertSame(['facebook'], $user->oauthIdentities()->pluck('provider')->all());
        $this->actingAs($user)->post("/account/logins/{$facebook->id}/disconnect")->assertSessionHas('error');

        // or with a password (set here for the first time: no current password to ask for)
        $this->actingAs($user)->post('/account/password', ['password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])->assertSessionHas('status');
        $this->actingAs($user->fresh())->post("/account/logins/{$facebook->id}/disconnect")->assertSessionHas('status');
        $this->assertSame(0, $user->oauthIdentities()->count());

        // nobody unlinks another person's sign-in
        $other = OauthIdentity::create(['user_id' => User::factory()->create()->id, 'provider' => 'google', 'provider_id' => 'g-2']);
        $this->actingAs($user)->post("/account/logins/{$other->id}/disconnect")->assertNotFound();
    }

    public function test_a_logged_in_account_links_google_unless_it_already_belongs_to_somebody_else(): void
    {
        config(['services.google.client_id' => 'test-client']);
        $user = User::factory()->create(['email' => 'me@example.com']);
        $this->actingAs($user)->get('/account/profile')->assertOk()->assertSee(route('oauth.redirect', ['google', 'link' => 1]), false);

        $this->google('g-9', 'another-address@gmail.com');
        $this->actingAs($user)->withHeader('Referer', url('/en/account/profile'))->get('/auth/google/redirect?link=1')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $this->flushHeaders();
        $this->actingAs($user)->get('/auth/google/callback')->assertRedirect(url('/en/account/profile').'#security')->assertSessionHas('status');
        $this->assertSame($user->id, OauthIdentity::where('provider_id', 'g-9')->value('user_id'));
        $this->assertSame(1, User::where('email', 'me@example.com')->count() + User::where('email', 'another-address@gmail.com')->count(), 'linking never creates an account');

        // the same Google account cannot be linked to a second matplace account
        $second = User::factory()->create();
        $this->actingAs($second)->get('/auth/google/redirect?link=1');
        $this->actingAs($second)->get('/auth/google/callback')->assertSessionHas('error', __('user.logins.taken', ['provider' => 'Google']));
        $this->assertSame(0, $second->oauthIdentities()->count());

        // without ?link=1 a logged-in person is simply sent to the account
        $this->actingAs($second)->get('/auth/google/redirect')->assertRedirect(route('account'));
    }

    public function test_changing_the_password_asks_for_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->actingAs($user)->post('/account/password', ['current_password' => 'nope', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasErrorsIn('password', ['current_password']);
        $this->actingAs($user)->post('/account/password', ['current_password' => 'secret123', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])->assertSessionHas('status');
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-secret-1'])->assertRedirect(route('account'));
    }

    // ── deleting the account ─────────────────────────────────────────────────

    public function test_deleting_scrubs_the_person_keeps_the_books_and_closes_the_door(): void
    {
        Storage::fake('models');
        Storage::fake('public');
        $user = User::factory()->create(['email' => 'gone@example.com', 'password' => 'secret123', 'phone' => '777', 'street' => 'Dlouhá 1', 'city' => 'Plzeň', 'zip' => '30100']);
        OauthIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);
        $this->credit($user, 500);
        $printed = $this->model($user, ['original_name' => 'tisteny.stl']);
        $loose = $this->model($user, ['original_name' => 'volny.stl']);
        $done = $this->order($user, $printed, FarmOrder::STATUS_HANDED_OVER);
        $done->forceFill(['shipping_address' => ['name' => 'Roman Vzor', 'street' => 'Dlouhá 1'], 'number' => 'F26-000001'])->save();
        $running = $this->order($user, $this->model($user), FarmOrder::STATUS_PRINTING);
        $running->forceFill(['shipping_address' => ['name' => 'Roman Vzor'], 'paid_at' => now()])->save();
        $unpaid = $this->order($user, $this->model($user), FarmOrder::STATUS_SLICED);

        // the credit is lost only with the owner's say-so, and the password must be right
        $this->actingAs($user)->post('/account/delete', ['understand' => 1, 'password' => 'secret123'])->assertSessionHasErrorsIn('delete', ['credit']);
        $this->actingAs($user)->post('/account/delete', ['understand' => 1, 'credit' => 1, 'password' => 'wrong'])->assertSessionHasErrorsIn('delete', ['password']);
        $this->assertFalse($user->fresh()->isAnonymized());

        $this->actingAs($user)->post('/account/delete', ['understand' => 1, 'credit' => 1, 'password' => 'secret123'])->assertRedirect(route('home'))->assertSessionHas('status', __('user.delete.done'));
        $this->assertGuest();

        $user->refresh();
        $this->assertTrue($user->isAnonymized());
        $this->assertSame(['Smazaný uživatel', 'deleted-'.$user->id.'@invalid'], [$user->name, $user->email]);
        $this->assertSame([null, null, null, null, null], [$user->phone, $user->street, $user->city, $user->password, $user->remember_token]);
        $this->assertSame(0, $user->oauthIdentities()->count());

        // the books stay: orders, the payment, every ledger line, plus one that explains the zero
        $this->assertSame(3, FarmOrder::where('user_id', $user->id)->count());
        $this->assertSame(1, Payment::where('user_id', $user->id)->count());
        $this->assertSame(0.0, app(Wallet::class)->balance($user));
        $this->assertSame(-500.0, CreditTransaction::where('user_id', $user->id)->where('type', CreditTransaction::TYPE_FORFEIT)->value('amount'));
        // a delivered parcel forgets its address, a print still on the machine keeps it until it is handed over
        $this->assertNull($done->fresh()->shipping_address);
        $this->assertSame(['name' => 'Roman Vzor'], $running->fresh()->shipping_address);
        $this->assertSame(FarmOrder::STATUS_PRINTING, $running->fresh()->status);
        $this->assertSame(FarmOrder::STATUS_CANCELLED, $unpaid->fresh()->status, 'an order nobody paid is dropped');
        // models nothing needs are gone, the printed one stays for its order
        $this->assertNull(ModelFile::find($loose->id));
        $this->assertNotNull(ModelFile::find($printed->id));

        // no way back in
        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        // the address is free for a new account
        $this->post('/register', ['name' => 'Roman', 'email' => 'gone@example.com', 'password' => 'secret123', 'terms' => 1])->assertRedirect(route('account'));
    }

    public function test_an_account_without_a_password_confirms_the_deletion_from_its_mailbox(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => null, 'locale' => 'en']);
        OauthIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);

        $this->actingAs($user)->post('/account/delete', ['understand' => 1])->assertSessionHas('status', __('user.delete.mail_sent', ['email' => $user->email]));
        $this->assertFalse($user->fresh()->isAnonymized());
        $link = '';
        Notification::assertSentTo($user, ConfirmAccountDeletion::class, function (ConfirmAccountDeletion $n) use ($user, &$link) {
            $link = $this->linkOf($n, $user);

            return true;
        });
        $this->assertStringContainsString('/en/account/delete/confirm/'.$user->id, $link);

        // opening the link deletes nothing (mail scanners open links): it shows a page with one more button
        $page = $this->get($link)->assertOk()->assertSee(__('user.delete.confirm_button', [], 'en'));
        $this->assertFalse($user->fresh()->isAnonymized());
        preg_match('/<form method="post" action="([^"]+)"/', $page->getContent(), $m);
        $action = html_entity_decode($m[1]);

        // a forged address does nothing
        $this->post(preg_replace('/signature=[0-9a-f]+/', 'signature=0', $action), ['understand' => 1])->assertSessionHas('error');
        $this->assertFalse($user->fresh()->isAnonymized());

        $this->post($action, ['understand' => 1])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue($user->fresh()->isAnonymized());
        $this->assertGuest();
        // the link is dead afterwards
        $this->get($link)->assertSessionHas('error');
    }
}
