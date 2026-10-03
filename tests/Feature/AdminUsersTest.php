<?php

namespace Tests\Feature;

use App\Domain\Farm\Wallet;
use App\Models\DesignerProfile;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\ResetPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** /admin/users: finding people, their roles, credit and prints, a password link, deleting an account. */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('models');
        $this->admin = User::factory()->create(['name' => 'Roman']);
        UserRole::create(['user_id' => $this->admin->id, 'role' => 'admin']);
    }

    public function test_admins_find_people_and_see_what_they_did(): void
    {
        $jana = User::factory()->create(['name' => 'Jana Nováková', 'email' => 'jana@example.com', 'phone' => '+420 777 123 456', 'country' => 'CZ']);
        $pedro = User::factory()->create(['name' => 'Pedro', 'email' => 'pedro@example.com', 'country' => 'ES']);
        app(Wallet::class)->adjust($jana, 250, 'dárek', $this->admin->id);

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin/users')->assertRedirect(route('account'));
        $this->get('/admin/users')->assertRedirect();

        $page = $this->actingAs($this->admin)->get('/admin/users')->assertOk();
        $page->assertSee('Jana Nováková')->assertSee('Pedro')->assertSee('jana@example.com')->assertSee('250,00');
        $this->actingAs($this->admin)->get('/admin/users?q=777+123')->assertOk()->assertSee('Jana Nováková')->assertDontSee('Pedro');
        $this->actingAs($this->admin)->get('/admin/users?q=pedro@')->assertOk()->assertSee('Pedro')->assertDontSee('Jana Nováková');
        $this->actingAs($this->admin)->get('/admin/users?role=admin')->assertOk()->assertSee('Roman')->assertDontSee('Pedro');

        $detail = $this->actingAs($this->admin)->get('/admin/users/'.$jana->id)->assertOk();
        $detail->assertSee('Jana Nováková')->assertSee('+420 777 123 456')->assertSee('dárek')->assertSee("250\u{00A0}Kč")->assertSee('Poslat odkaz na nové heslo')->assertSee('Smazat účet');
        // the admin's own page has no delete form and no way to drop their own admin role
        $this->actingAs($this->admin)->get('/admin/users/'.$this->admin->id)->assertOk()->assertDontSee('Smazat účet');
    }

    public function test_roles_a_password_link_and_deleting_an_account(): void
    {
        Notification::fake();
        $jana = User::factory()->create(['name' => 'Jana Nováková', 'email' => 'jana@example.com']);

        // designer on: the profile comes with the role, as when she switches it on herself
        $this->actingAs($this->admin)->from('/admin/users/'.$jana->id)->post('/admin/users/'.$jana->id.'/role', ['role' => 'designer', 'enabled' => 1])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue($jana->fresh()->isDesigner());
        $profile = DesignerProfile::where('user_id', $jana->id)->firstOrFail();
        $profile->update(['visible' => true]);
        $this->actingAs($this->admin)->get('/admin/users/'.$jana->id)->assertOk()->assertSee('/d/'.$profile->slug);
        // off: the role goes, the profile hides
        $this->actingAs($this->admin)->post('/admin/users/'.$jana->id.'/role', ['role' => 'designer', 'enabled' => 0])->assertSessionHas('status');
        $this->assertFalse($jana->fresh()->isDesigner());
        $this->assertFalse((bool) $profile->fresh()->getAttribute('visible'));
        // an unverified e-mail cannot become a designer
        $nobody = User::factory()->unverified()->create();
        $this->actingAs($this->admin)->post('/admin/users/'.$nobody->id.'/role', ['role' => 'designer', 'enabled' => 1])->assertSessionHas('error');

        // admin: given and taken, but never from oneself
        $this->actingAs($this->admin)->post('/admin/users/'.$jana->id.'/role', ['role' => 'admin', 'enabled' => 1])->assertSessionHas('status');
        $this->assertTrue($jana->fresh()->isAdmin());
        $this->actingAs($this->admin)->post('/admin/users/'.$this->admin->id.'/role', ['role' => 'admin', 'enabled' => 0])->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->isAdmin());
        // an admin cannot be deleted while the role is on
        $this->actingAs($this->admin)->post('/admin/users/'.$jana->id.'/erase', ['confirm' => 'jana@example.com'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post('/admin/users/'.$jana->id.'/role', ['role' => 'admin', 'enabled' => 0])->assertSessionHas('status');

        // the password link: the same notification as "forgotten password"
        $this->actingAs($this->admin)->post('/admin/users/'.$jana->id.'/reset-link')->assertSessionHas('status');
        Notification::assertSentTo($jana, ResetPasswordLink::class);

        // deleting: only with the e-mail written out, never oneself; afterwards anonymised and listed among the deleted
        $this->actingAs($this->admin)->post('/admin/users/'.$jana->id.'/erase', ['confirm' => 'wrong'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post('/admin/users/'.$this->admin->id.'/erase', ['confirm' => $this->admin->email])->assertSessionHas('error');
        $this->actingAs($this->admin)->post('/admin/users/'.$jana->id.'/erase', ['confirm' => 'jana@example.com'])->assertRedirect('/admin/users/'.$jana->id)->assertSessionHas('status');
        $jana->refresh();
        $this->assertTrue($jana->isAnonymized());
        $this->assertStringNotContainsString('jana', $jana->email);
        $this->actingAs($this->admin)->get('/admin/users')->assertOk()->assertDontSee('jana@example.com');
        $this->actingAs($this->admin)->get('/admin/users?deleted=1')->assertOk()->assertSee('deleted-'.$jana->id);
        $this->actingAs($this->admin)->get('/admin/users/'.$jana->id)->assertOk()->assertSee('Smazaný (anonymizovaný) účet');
    }
}
