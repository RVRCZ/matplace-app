<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** The admin tries the tools all day: no daily count on photo descriptions, generations, print advice, uploads or calculations. */
class AdminLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('local');
        // every daily count at zero: a customer is refused at once, the admin never
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.describe' => 0, 'ai.daily_limits.advise' => 0,
            'ai.daily_limits.generate_user' => 0, 'ai.daily_limits.generate_global' => 0]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->create(['role' => User::ROLE_ADMIN]);
        $this->user = User::factory()->create();
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                'name' => 'Drak', 'name_en' => 'Dragon', 'category' => 'toy', 'queries' => ['dragon'], 'bbox_mm' => ['x' => 60, 'y' => 40, 'z' => 25],
                'size_known' => false, 'material' => 'PLA', 'printable' => true, 'notes' => '',
                'summary' => 'Kostka se vytiskne bez potíží.', 'items' => [],
            ])]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]]),
            'api.printables.com/*' => Http::response(['data' => ['searchPrints2' => ['items' => []]]]),
            'makerworld.com/*' => Http::response('', 403),
        ]);
    }

    public function test_the_admin_is_not_counted_where_a_customer_is(): void
    {
        $photo = fn () => ['image' => UploadedFile::fake()->image('drak.jpg', 400, 300)];
        $this->actingAs($this->user)->post('/api/describe', $photo(), ['Accept' => 'application/json'])->assertStatus(429)->assertJsonPath('error', 'daily_limit');
        $this->actingAs($this->admin)->post('/api/describe', $photo(), ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($this->user)->postJson('/api/generate', ['prompt' => 'small vase'])->assertStatus(429);
        $this->actingAs($this->admin)->postJson('/api/generate', ['prompt' => 'small vase'])->assertCreated();   // even past the global cap

        $path = sys_get_temp_dir().'/mp_admin_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->admin)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $this->actingAs($this->user)->postJson("/api/files/{$uuid}/advice")->assertStatus(429)->assertJsonPath('error', 'daily_limit');
        $this->actingAs($this->admin)->postJson("/api/files/{$uuid}/advice")->assertStatus(202);

        // bursts of uploads and calculations are rate-limited by address, not for the admin
        foreach (['uploads', 'calculations'] as $name) {
            $limiter = RateLimiter::limiter($name);
            $this->assertInstanceOf(Unlimited::class, $limiter(Request::create('/api/'.$name, 'POST')->setUserResolver(fn () => $this->admin)), $name);
            $this->assertNotInstanceOf(Unlimited::class, $limiter(Request::create('/api/'.$name, 'POST')->setUserResolver(fn () => $this->user)), $name);
        }
    }
}
