<?php

namespace Tests\Feature;

use App\Models\AnonymousSession;
use App\Models\Calculation;
use App\Models\ModelFile;
use App\Models\PrinterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** One account with role switches; anonymous work is claimed on registration. */
class AccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_claims_anonymous_calculation_and_enables_printer_role(): void
    {
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
        $this->assertSame($session->id, Calculation::first()->anonymous_session_id);

        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $session->token)
            ->post('/registrace', ['name' => 'Roman', 'email' => 'roman@example.com', 'password' => 'secret123', 'terms' => 1, 'role' => 'printer'])
            ->assertRedirect(route('account.roles.enable', 'printer'));
        $user = User::where('email', 'roman@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame($user->id, Calculation::first()->owner_user_id);
        $this->assertSame($user->id, ModelFile::first()->owner_user_id);
        $this->assertSame($user->id, $session->fresh()->claimed_by_user_id);

        $this->actingAs($user)->post(route('account.roles.enable', 'printer'))->assertRedirect(route('printer.profile'));
        $user->refresh();
        $this->assertTrue($user->isPrinter());
        $this->assertNotNull($user->printerProfile);
        $this->assertNotNull($user->printerProfile->defaultPricing());
    }

    public function test_printer_area_requires_role(): void
    {
        $this->get('/tiskar')->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get('/tiskar')->assertRedirect(route('account'));
    }

    public function test_login_and_role_switch_off_keeps_data(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->post('/prihlaseni', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect(route('account'));
        $this->actingAs($user)->post(route('account.roles.enable', 'printer'));
        $this->actingAs($user)->post(route('account.roles.disable', 'printer'))->assertRedirect(route('account'));
        $this->assertFalse($user->fresh()->isPrinter());
        $this->assertSame(1, PrinterProfile::count());
    }
}
