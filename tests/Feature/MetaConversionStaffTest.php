<?php

namespace Tests\Feature;

use App\Domain\Social\SocialPublisher;
use App\Engines\Social\MetaClient;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Consent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

/** A conversion goes to Meta for a visitor who allowed marketing cookies, never for the staff's own orders. */
class MetaConversionStaffTest extends TestCase
{
    use RefreshDatabase;

    private function send(?User $user, string $consent = 'a1m1'): bool
    {
        $request = Request::create('/farm/orders/1/pay', 'POST', [], [Consent::COOKIE => $consent]);
        // binding the request resets its user resolver to the guard's, so ours comes after
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $user);

        return app(SocialPublisher::class)->conversion('order_paid', 'order-1', $request, $user?->email, ['value' => 359.0, 'currency' => 'CZK']);
    }

    public function test_a_customers_paid_order_is_reported_and_an_admins_is_not(): void
    {
        $meta = Mockery::mock(MetaClient::class);
        $meta->shouldReceive('sendConversion')->once()->withArgs(fn (string $event, string $id) => $event === 'Purchase' && $id === 'order-1')->andReturn(true);
        $this->app->instance(MetaClient::class, $meta);

        $admin = User::factory()->create();
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin']);

        $this->assertFalse($this->send($admin->fresh()), 'admin');
        $this->assertFalse($this->send(User::factory()->create(), 'a1m0'), 'no consent');
        $this->assertTrue($this->send(User::factory()->create()), 'customer');
    }
}
