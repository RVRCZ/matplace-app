<?php

namespace Tests\Feature;

use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ToolVisibility;
use App\Models\ToolFlag;
use App\Models\User;
use App\Support\Sitemaps;
use App\Support\ToolSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The admin's switch of a tool (/admin/tools): a hidden tool is in no list, sitemap or link and answers 404 to
 * everybody but an admin, who still opens and uses it. A tool held back in config/tools.php behaves the same.
 */
class ToolVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private string $sitemaps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sitemaps = sys_get_temp_dir().'/mp_sitemaps_'.uniqid();
        config(['seo.sitemap_dir' => $this->sitemaps]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sitemaps);
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['name' => 'Roman']);
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    /** The switch as the admin's page sends it. */
    private function switch(User $admin, string $tool, bool $public, ?string $note = null)
    {
        return $this->actingAs($admin)->postJson('/admin/tools/'.$tool, ['public' => $public, 'note' => $note]);
    }

    private function sitemap(): string
    {
        return (string) file_get_contents($this->sitemaps.'/sitemap-tools-cs.xml');
    }

    public function test_a_hidden_tool_leaves_the_catalogue_the_gifts_the_home_page_and_the_sitemap_and_comes_back(): void
    {
        $admin = $this->admin();
        app(Sitemaps::class)->build();
        $box = 'href="'.route('tools.box').'"';
        $ornament = 'href="'.route('tools.ornament').'"';
        $organizer = route('tools.organizer');
        $this->get('/tools')->assertOk()->assertSee($box, false);
        $this->get('/')->assertOk()->assertSee($box, false);
        $this->get('/gifts')->assertOk()->assertSee($ornament, false);
        $this->assertStringContainsString('<loc>'.$organizer.'</loc>', $this->sitemap());
        $this->assertStringContainsString('/tools/box<', $this->sitemap());
        $listed = count(ToolVisibility::listed());

        $this->switch($admin, 'box', false, 'víko nedrží')->assertOk()->assertJson(['tool' => 'box', 'public' => false, 'listed' => false]);
        $this->switch($admin, 'ornament', false)->assertOk();
        auth()->logout();

        // no restart, no waiting for the cache: the next page already goes without them
        $this->assertFalse(ToolVisibility::isPublic('box'));
        $this->assertSame($listed - 2, count(ToolVisibility::listed()));
        $this->get('/tools')->assertOk()->assertDontSee($box, false)->assertSee('href="'.$organizer.'"', false);
        $this->get('/')->assertOk()->assertDontSee($box, false);
        $this->get('/gifts')->assertOk()->assertDontSee($ornament, false)->assertSee('href="'.route('tools.gingerbread').'"', false);
        $this->get('/en/tools')->assertOk()->assertDontSee('/en/tools/box"', false);
        $this->assertStringNotContainsString('/tools/box<', $this->sitemap());
        $this->assertStringContainsString('<loc>'.$organizer.'</loc>', $this->sitemap());
        $this->assertNotContains('box', ToolSeo::tools());
        $this->get('/og/tool/box.png')->assertNotFound();

        $this->switch($admin, 'box', true)->assertOk()->assertJson(['public' => true, 'listed' => true]);
        auth()->logout();
        $this->get('/tools')->assertOk()->assertSee($box, false);
        $this->get('/')->assertOk()->assertSee($box, false);
        $this->get('/tools/box')->assertOk();
        $this->assertStringContainsString('/tools/box<', $this->sitemap());
    }

    public function test_the_page_of_a_hidden_tool_is_404_for_a_guest_and_a_user_and_open_with_a_line_for_an_admin(): void
    {
        $admin = $this->admin();
        $this->get('/tools/box')->assertOk()->assertDontSee('id="tool-hidden-bar"', false);
        $this->switch($admin, 'box', false)->assertOk();
        auth()->logout();

        foreach (['', '/en', '/es'] as $prefix) {
            $this->get($prefix.'/tools/box')->assertNotFound();
        }
        $this->actingAs(User::factory()->create())->get('/tools/box')->assertNotFound();

        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $page = $this->actingAs($admin)->get($prefix.'/tools/box')->assertOk();
            $page->assertSee('id="tool-hidden-bar"', false)->assertSee(__('tools.admin.bar', [], $locale))
                ->assertSee(route('admin.tools.index').'#tool-box', false)
                ->assertSee('<meta name="robots" content="noindex', false)
                ->assertSee('id="tool-go"', false)->assertSee('id="tool-download"', false);      // the page is whole: preview, download, print with us
            $this->assertNotSame('tools.admin.bar', __('tools.admin.bar', [], $locale));
        }
        // the line belongs to the hidden tool's page alone
        $this->actingAs($admin)->get('/tools/organizer')->assertOk()->assertDontSee('id="tool-hidden-bar"', false);
        $this->actingAs($admin)->get('/tools')->assertOk()->assertDontSee('id="tool-hidden-bar"', false);
    }

    public function test_the_api_of_a_hidden_tool_is_404_for_a_guest_and_works_for_an_admin(): void
    {
        $admin = $this->admin();
        $made = app(ParametricGenerator::class)->available() ? 201 : 503;       // without Python the generator itself says so: the gate let the request through
        $this->postJson('/api/tools/param', ['kind' => 'box', 'params' => []])->assertStatus($made);

        $this->switch($admin, 'box', false)->assertOk();
        auth()->logout();
        $this->postJson('/api/tools/param', ['kind' => 'box', 'params' => []])->assertNotFound();
        $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => []])->assertNotFound();
        $this->postJson('/api/tools/param/zip', ['kind' => 'box', 'params' => []])->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson('/api/tools/param', ['kind' => 'box', 'params' => []])->assertNotFound();
        $this->actingAs($admin)->postJson('/api/tools/param', ['kind' => 'box', 'params' => []])->assertStatus($made);
        auth()->logout();
        // the other tools go on
        $this->postJson('/api/tools/param', ['kind' => 'cap', 'params' => []])->assertStatus($made);

        // a picture in filaments has an API of its own
        $art = app(ArtGenerator::class)->available() ? 422 : 503;                // no picture given: asked for, not refused
        $this->postJson('/api/tools/art/preview', ['params' => []])->assertStatus($art);
        $this->switch($admin, 'filament_art', false)->assertOk();
        auth()->logout();
        $this->postJson('/api/tools/art/preview', ['params' => []])->assertNotFound();
        $this->postJson('/api/tools/art', ['params' => []])->assertNotFound();
        $this->actingAs($admin)->postJson('/api/tools/art/preview', ['params' => []])->assertStatus($art);
    }

    public function test_a_generator_stays_open_while_one_of_its_tools_is_public(): void
    {
        $admin = $this->admin();
        // SVG to STL is the logo tool with a preset: hiding the logo tool alone leaves the generator to it
        $this->switch($admin, 'logo', false)->assertOk();
        $this->assertTrue(ToolVisibility::canUseKind(null, 'logo'));
        $this->switch($admin, 'svg_to_stl', false)->assertOk();
        $this->assertFalse(ToolVisibility::canUseKind(null, 'logo'));
        $this->assertFalse(ToolVisibility::canUseKind(User::factory()->create(), 'logo'));
        $this->assertTrue(ToolVisibility::canUseKind($admin, 'logo'));
        // the nameplate is the composer, its quick form the sign generator
        $this->switch($admin, 'sign', false)->assertOk();
        $this->switch($admin, 'text', false)->assertOk();
        $this->assertTrue(ToolVisibility::canUseKind(null, 'sign'));
        $this->switch($admin, 'nameplate', false)->assertOk();
        $this->assertFalse(ToolVisibility::canUseKind(null, 'sign'));

        // the edits of a model file: the tool's key and the name of the edit differ for two of them
        $this->assertTrue(ToolVisibility::canUseEdit(null, 'holder'));
        $this->switch($admin, 'holder_model', false)->assertOk();
        $this->assertFalse(ToolVisibility::canUseEdit(null, 'holder'));
        $this->assertTrue(ToolVisibility::canUseEdit($admin, 'holder'));
        $this->assertTrue(ToolVisibility::canUseEdit(null, 'split'));
        // what no tool's page is built on is not the catalogue's matter
        $this->assertTrue(ToolVisibility::canUseKind(null, 'no_such_kind'));
    }

    public function test_a_tool_held_back_in_the_config_behaves_as_hidden_and_the_switch_does_not_publish_it(): void
    {
        $admin = $this->admin();
        $this->assertFalse(config('tools.straw.available'));
        $this->assertFalse(ToolVisibility::isPublic('straw'));
        $this->get('/tools/straw-topper')->assertNotFound();
        $this->postJson('/api/tools/param/preview', ['kind' => 'straw', 'params' => []])->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/tools/straw-topper')->assertNotFound();
        $this->actingAs($admin)->get('/tools/straw-topper')->assertOk()->assertSee('id="tool-hidden-bar"', false)->assertSee(__('tools.straw.title'));
        $this->assertNotSame(404, $this->actingAs($admin)->postJson('/api/tools/param/preview', ['kind' => 'straw', 'params' => []])->status());

        // "visible" in the admin is not enough: the config holds it back
        $this->switch($admin, 'straw', true)->assertOk()->assertJson(['public' => true, 'listed' => false]);
        auth()->logout();
        $this->assertFalse(ToolVisibility::isPublic('straw'));
        $this->get('/tools/straw-topper')->assertNotFound();
        $this->get('/tools')->assertOk()->assertDontSee('/tools/straw-topper', false);
    }

    public function test_the_calculator_stays_the_home_page_when_its_card_is_hidden(): void
    {
        $this->switch($this->admin(), 'calc', false)->assertOk();
        auth()->logout();
        $this->assertFalse(ToolVisibility::isPublic('calc'));
        $this->get('/')->assertOk()->assertDontSee('id="tool-hidden-bar"', false);
    }

    public function test_links_written_into_pages_follow_the_switch(): void
    {
        config(['features.marketplace' => true, 'tools.spare.available' => true]);
        $admin = $this->admin();
        $spare = 'href="'.route('tools.spare').'"';
        $composer = 'href="'.route('tools.compose', ['preset' => 'topper']).'"';
        // the spare-part tile of the home page, and "take it to the composer" under a form the composer can start from
        $this->get('/')->assertOk()->assertSee($spare, false);
        $this->get('/tools/cake-topper')->assertOk()->assertSee($composer, false)->assertSee(__('param.compose.open'));

        $this->switch($admin, 'spare', false)->assertOk();
        $this->switch($admin, 'compose', false)->assertOk();
        auth()->logout();
        $this->get('/tools/spare-part')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee($spare, false);
        $this->get('/tools/cake-topper')->assertOk()->assertDontSee($composer, false)->assertDontSee(__('param.compose.open'));
        // the nameplate's quick form leads to the composer of its own address, not to the composer's page: it stays
        $this->get('/tools/nameplate?form=1')->assertOk()->assertSee(__('param.compose.open'));
        // an admin gets to both pages, so both links are there for him
        $this->actingAs($admin)->get('/')->assertOk()->assertSee($spare, false);
        $this->actingAs($admin)->get('/tools/cake-topper')->assertOk()->assertSee($composer, false);
    }

    public function test_the_admin_page_lists_every_tool_saves_a_row_and_filters(): void
    {
        $this->get('/admin/tools')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/tools')->assertRedirect(route('account'));
        $this->actingAs(User::factory()->create())->postJson('/admin/tools/box', ['public' => false])->assertRedirect(route('account'));
        $this->assertSame(0, ToolFlag::count());

        $admin = $this->admin();
        $page = $this->actingAs($admin)->get('/admin/tools')->assertOk();
        foreach (array_keys(config('tools')) as $key) {
            $page->assertSee('id="tool-'.$key.'"', false);
        }
        $page->assertSee(__('tools.admin.title'))->assertSee(__('tools.box.title'))->assertSee(route('tools.box'), false)
            ->assertSee(__('tools.admin.col.public'))->assertSee(__('tools.admin.config.off'))->assertSee('10. 10. 2026');     // the box was verified by printing that day
        $this->assertDoesNotMatchRegularExpression('/>\s*tools\.admin\.[a-z_.]+\s*</', $page->getContent());

        $this->switch($admin, 'box', false, '  víko nedrží  ')->assertOk()->assertJsonPath('changed', fn (string $s) => str_starts_with($s, 'Roman · '));
        $flag = ToolFlag::where('tool', 'box')->firstOrFail();
        $this->assertFalse($flag->public);
        $this->assertSame('víko nedrží', $flag->note);
        $this->assertSame($admin->id, $flag->updated_by);
        // the same row again: one row a tool
        $this->switch($admin, 'box', false, null)->assertOk();
        $this->assertSame(1, ToolFlag::count());
        $this->assertNull($flag->fresh()->note);
        // a form without scripts saves too
        $this->actingAs($admin)->post('/admin/tools/box', ['public' => '0', 'note' => 'bez skriptu'])->assertRedirect()->assertSessionHas('status', __('tools.admin.saved'));
        $this->assertSame('bez skriptu', $flag->fresh()->note);

        $this->switch($admin, 'no_such_tool', false)->assertNotFound();
        $this->actingAs($admin)->postJson('/admin/tools/box', ['note' => 'x'])->assertStatus(422);

        $hidden = $this->actingAs($admin)->get('/admin/tools?show=hidden')->assertOk();
        $hidden->assertSee('id="tool-box"', false)->assertSee('id="tool-straw"', false)->assertDontSee('id="tool-organizer"', false)->assertSee('bez skriptu');
        $unverified = $this->actingAs($admin)->get('/admin/tools?show=unverified')->assertOk();
        $unverified->assertSee('id="tool-organizer"', false)->assertDontSee('id="tool-box"', false);
        foreach (['en', 'es'] as $locale) {
            foreach (array_keys(array_filter(trans('tools', [], 'cs'), fn ($v, $k) => str_starts_with((string) $k, 'admin.'), ARRAY_FILTER_USE_BOTH)) as $key) {
                $this->assertArrayHasKey($key, trans('tools', [], $locale), "{$key} missing in {$locale}");
            }
        }
    }

    public function test_the_switches_are_cached_for_a_minute_and_a_save_clears_the_cache(): void
    {
        $this->assertTrue(ToolVisibility::isPublic('box'));
        // a row written past the model (another server, a hand in the database) is seen when the cache runs out
        DB::table('tool_flags')->insert(['tool' => 'box', 'public' => false, 'created_at' => now(), 'updated_at' => now()]);
        app()->forgetScopedInstances();
        $this->assertTrue(ToolVisibility::isPublic('box'));
        $this->travel(ToolVisibility::CACHE_SECONDS + 1)->seconds();
        app()->forgetScopedInstances();
        $this->assertFalse(ToolVisibility::isPublic('box'));
        // a save through the model is seen at once
        ToolFlag::where('tool', 'box')->firstOrFail()->update(['public' => true]);
        $this->assertTrue(ToolVisibility::isPublic('box'));
    }
}
