<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Generation\GenerationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The daily AI limits are set at /admin/ai and take effect at once (Roman, 18 Oct 2026: a second phone on the house
 * Wi-Fi hit the guest's one generation a day, and the number lived only in .env).
 */
class AdminAiLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_sets_the_daily_ai_limits_on_the_ai_page_and_they_hold(): void
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $guestDefault = (int) config('ai.daily_limits.generate_guest');
        $this->assertSame($guestDefault, app(GenerationService::class)->limitFor(null));

        $this->actingAs($admin)->get('/admin/ai')->assertOk()->assertSee('Denní limity')->assertSee('name="generate_guest"', false)->assertSee('výchozí '.$guestDefault);

        $all = ['generate_guest' => $guestDefault + 1, 'generate_user' => 5, 'generate_printer' => 15, 'generate_global' => 100, 'describe' => 20, 'advise' => 10, 'advise_global' => 300, 'design' => 20, 'design_global' => 500];
        $this->actingAs($admin)->post('/admin/ai/limits', $all)->assertRedirect('/admin/ai')->assertSessionHas('status');
        $this->assertSame($guestDefault + 1, config('ai.daily_limits.generate_guest'));
        $this->assertSame($guestDefault + 1, app(GenerationService::class)->limitFor(null));
        $this->assertSame(5, app(GenerationService::class)->limitFor(User::factory()->create()));
        $this->actingAs($admin)->get('/admin/ai')->assertOk()->assertSee('nastaveno v administraci');

        // the next request boots from the table again (AppServiceProvider → FarmSettings::applyAiLimits)
        config(['ai.daily_limits.generate_guest' => $guestDefault]);
        app(FarmSettings::class)->applyAiLimits();
        $this->assertSame($guestDefault + 1, config('ai.daily_limits.generate_guest'));

        // nonsense is refused, a guest has no page here
        $this->actingAs($admin)->post('/admin/ai/limits', ['generate_guest' => -1] + $all)->assertSessionHasErrors('generate_guest');
        $this->assertSame($guestDefault + 1, config('ai.daily_limits.generate_guest'));
        auth()->logout();
        $this->get('/admin/ai')->assertRedirect();
    }
}
