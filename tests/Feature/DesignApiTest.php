<?php

namespace Tests\Feature;

use App\Domain\Tools\ToolVisibility;
use App\Engines\Ai\FakeAssistant;
use App\Models\GenerationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** POST /api/tools/design: who may ask, how often, and what comes back when the model does not answer. */
class DesignApiTest extends TestCase
{
    use RefreshDatabase;

    private const ANSWER = ['params' => ['style' => 'wall', 'width' => 78, 'device' => 12, 'angle' => 65, 'back' => 100, 'thickness' => 4, 'radius' => 2, 'depth' => 60, 'vent' => 1.5, 'cable' => true, 'screws' => false, 'cutout' => 'none', 'pattern' => 'circles', 'cutout_size' => 55, 'rib_pitch' => 6, 'band' => 10, 'across' => 25, 'artwork' => 'keep'],
        'note' => 'Zvolil jsem kapsu na zeď na oboustrannou pásku.', 'unmet' => ['barva podle vypínače']];

    protected function setUp(): void
    {
        parent::setUp();
        FakeAssistant::reset();
        FakeAssistant::$answers['design'] = self::ANSWER;
        config(['ai.design_public' => true]);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function ask(array $more = [])
    {
        return $this->postJson('/api/tools/design', $more + ['tool' => 'phone_stand', 'text' => 'na zeď vedle vypínače, bez šroubů', 'params' => ['style' => 'desk', 'width' => 70]]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    public function test_a_wish_comes_back_as_the_values_of_the_form(): void
    {
        $this->ask()->assertOk()
            ->assertJsonPath('params.style', 'wall')->assertJsonPath('params.width', 78)->assertJsonPath('params.screws', false)
            ->assertJsonPath('note', self::ANSWER['note'])->assertJsonPath('unmet.0', 'barva podle vypínače');

        // the wish is kept with what came of it
        $kept = GenerationRequest::where('type', 'design')->sole();
        $this->assertSame('done', $kept->status);
        $this->assertSame('na zeď vedle vypínače, bez šroubů', $kept->prompt);
        $this->assertSame('phone_stand', $kept->description['tool']);
        $this->assertSame('wall', $kept->description['params']['style']);
        // and it is no 3D generation: the visitor's daily generations stay untouched
        $this->assertSame(0, GenerationRequest::usedToday('127.0.0.1', $kept->anonymous_session_id, null));
    }

    public function test_the_wish_must_be_a_short_text(): void
    {
        $this->ask(['text' => ''])->assertStatus(422);
        $this->ask(['text' => str_repeat('a', 501)])->assertStatus(422);
        $this->ask(['text' => ['a list']])->assertStatus(422);
        $this->assertSame([], FakeAssistant::$calls);
    }

    public function test_a_visitor_has_a_daily_limit_and_the_admin_has_none(): void
    {
        config(['ai.daily_limits.design' => 20]);
        for ($i = 0; $i < 20; $i++) {
            $this->ask()->assertOk();
        }
        $this->ask()->assertStatus(429)->assertJsonPath('error', 'daily_limit')->assertJsonPath('message', __('param.design.limit'));
        $this->assertCount(20, FakeAssistant::$calls);

        $this->actingAs($this->admin());
        $this->ask()->assertOk();
        $this->assertCount(21, FakeAssistant::$calls);
    }

    public function test_the_whole_site_has_a_daily_limit_too(): void
    {
        config(['ai.daily_limits.design_global' => 2]);
        $this->ask()->assertOk();
        $this->ask()->assertOk();
        $this->ask()->assertStatus(429)->assertJsonPath('error', 'daily_limit');
    }

    public function test_a_hidden_tool_and_a_tool_without_a_designer_answer_404(): void
    {
        $this->ask(['tool' => 'box'])->assertStatus(404);
        $this->ask(['tool' => 'nothing'])->assertStatus(404);
        $this->ask(['tool' => ['phone_stand']])->assertStatus(404);

        DB::table('tool_flags')->insert(['tool' => 'phone_stand', 'public' => false, 'created_at' => now(), 'updated_at' => now()]);
        ToolVisibility::forget();
        $this->ask()->assertStatus(404);
        $this->assertSame([], FakeAssistant::$calls);

        $this->actingAs($this->admin());
        $this->ask()->assertOk();
    }

    public function test_until_it_is_public_only_the_admin_may_ask(): void
    {
        config(['ai.design_public' => false]);
        $this->ask()->assertStatus(404);
        $this->actingAs(User::factory()->create());
        $this->ask()->assertStatus(404);
        $this->actingAs($this->admin());
        $this->ask()->assertOk();
    }

    public function test_a_model_that_does_not_answer_is_a_502_and_a_wrong_type_a_422(): void
    {
        FakeAssistant::$fail = 'Assistant API HTTP 529: overloaded';
        $this->ask()->assertStatus(502)->assertJsonPath('message', __('param.design.failed'))->assertJsonMissingPath('params');
        $this->assertSame('failed', GenerationRequest::where('type', 'design')->sole()->status);

        FakeAssistant::$fail = null;
        FakeAssistant::$answers['design'] = ['params' => ['style' => 'tripod'], 'note' => '', 'unmet' => []];
        $this->ask()->assertStatus(422)->assertJsonPath('errors.text.0', __('param.design.failed'));
    }

    public function test_the_field_is_on_the_phone_stand_and_on_no_other_tool(): void
    {
        $stand = (string) $this->get('/tools/phone-stand')->assertOk()->getContent();
        $this->assertStringContainsString('id="param-design" hidden', $stand);      // shown by the script: without it the page is as it was
        $this->assertStringContainsString('id="param-design-text"', $stand);
        $this->assertStringContainsString('maxlength="500"', $stand);
        $this->assertStringContainsString(__('param.design.phone_stand.lead'), $stand);
        $this->assertStringContainsString(e(__('param.design.phone_stand.example2')), $stand);
        $this->assertStringContainsString(json_encode(route('api.tools.design')), $stand);

        $box = (string) $this->get('/tools/box')->assertOk()->getContent();
        $this->assertStringNotContainsString('param-design', $box);
        $this->assertStringContainsString('design: null', $box);

        // while the wishes are being tried the visitor's page is as it was
        config(['ai.design_public' => false]);
        $this->assertStringNotContainsString('param-design', (string) $this->get('/tools/phone-stand')->assertOk()->getContent());
        $this->actingAs($this->admin());
        $this->assertStringContainsString('id="param-design-text"', (string) $this->get('/tools/phone-stand')->assertOk()->getContent());
    }
}
