<?php

namespace Tests\Feature;

use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** features.marketplace = false: the calculator shows the slicer's facts, no prices, no inquiries, no printer pages. */
class MarketplaceSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        config(['features.marketplace' => false, 'tools.spare.available' => false]);
    }

    public function test_calculator_has_facts_and_no_prices(): void
    {
        $r = $this->get('/');
        $r->assertOk()->assertSee('"marketplace":false', false)->assertDontSee('id="cta-make"', false)->assertDontSee('id="inquiry-panel"', false);
        $r->assertSee(__('calc.facts.layers'))->assertDontSee(route('register', ['role' => 'printer']), false);

        $path = sys_get_temp_dir().'/mp_switch_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
        $calc = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15])->assertCreated();
        // the slicer's facts are there for the facts card
        $this->assertSame(['normal', 'silent', 'sport'], array_keys($calc->json('calculation.slicer.minutes_by_mode')));
        $this->assertSame(100, $calc->json('calculation.slicer.layers'));
        $this->assertGreaterThan(0, $calc->json('calculation.slicer.meters'));
    }

    public function test_marketplace_routes_answer_404_and_the_printer_role_is_hidden(): void
    {
        $this->postJson('/api/inquiries', [])->assertNotFound();
        $this->postJson('/api/spare-parts', [])->assertNotFound();
        $this->get('/tools/spare-part')->assertNotFound();
        $this->get('/printers/id/1')->assertNotFound();
        $this->get('/i/sometoken')->assertNotFound();
        $this->get('/q/sometoken')->assertNotFound();

        $user = User::factory()->create();
        $user->setRole(User::ROLE_PRINTER, true);
        $this->actingAs($user)->get('/printer')->assertNotFound();
        $this->actingAs($user)->get('/account')->assertOk()->assertDontSee(__('account.role.printer'));
        $this->actingAs($user)->get('/tools')->assertOk()->assertDontSee(route('tools.spare'));
    }

    public function test_farm_still_works_without_the_marketplace(): void
    {
        $this->seed(\Database\Seeders\FarmSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/farm')->assertOk();
        $this->get('/')->assertOk()->assertSee('id="cta-farm"', false);
    }
}
