<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderService;
use App\Domain\Tools\ParametricGenerator;
use App\Domain\Tools\ToolVisibility;
use App\Models\ModelFile;
use App\Models\ToolFlag;
use App\Models\User;
use App\Support\CalculatorTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Which tools the home page shows: config/home.php unless the admin decided at /admin/tools (docs/AN.md §21), and the
 * names of a design's parts in the calculation's download panel.
 */
class ToolHomeSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['name' => 'Roman']);
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    /** The switch as the row of the admin's page sends it. */
    private function save(User $admin, string $tool, bool $home, bool $public = true)
    {
        return $this->actingAs($admin)->postJson(route('admin.tools.update', $tool), ['public' => $public ? '1' : '0', 'home' => $home ? '1' : '0'])->assertOk();
    }

    /** @return list<string> the tools with a card in the home page's section of tools, in order */
    private function cards(): array
    {
        $html = $this->get('/')->assertOk()->getContent();
        $from = (int) strpos($html, 'id="home-tools"');
        $section = substr($html, $from, (int) strpos($html, '</section>', $from) - $from);
        $tools = [];
        foreach ((array) config('tools') as $key => $tool) {
            if (Route::has((string) $tool['route']) && ($at = strpos($section, 'href="'.route($tool['route']).'"')) !== false) {
                $tools[$at] = (string) $key;
            }
        }
        ksort($tools);

        return array_values($tools);
    }

    public function test_the_switch_is_saved_and_the_home_page_follows_it(): void
    {
        $admin = $this->admin();
        config(['home.tools' => ['qr', 'relief'], 'home.tools_shown' => 8]);
        $this->assertNotContains('box', config('home.tools'));
        $this->assertSame(['qr', 'relief'], $this->cards());

        // on: a tool the admin chose goes before those of the config
        $res = $this->save($admin, 'box', true)->assertJson(['tool' => 'box', 'home' => true]);
        $this->assertTrue(ToolFlag::where('tool', 'box')->value('home'));
        $this->assertStringContainsString('3', $res->json('home_note'));
        $this->assertStringContainsString('Roman', $res->json('changed'));
        $this->assertSame(['box', 'qr', 'relief'], ToolVisibility::onHome());
        $this->assertSame(['box', 'qr', 'relief'], $this->cards());

        // off: a tool the config lists leaves
        $this->save($admin, 'qr', false)->assertJson(['tool' => 'qr', 'home' => false]);
        $this->assertSame(['box', 'relief'], $this->cards());
    }

    public function test_a_tool_switched_on_is_on_the_page_with_a_full_config_and_its_row_says_it_goes_first(): void
    {
        $admin = $this->admin();
        $this->assertNotContains('box', config('home.tools'));
        $this->assertGreaterThanOrEqual(13, count(config('home.tools')));
        $this->assertSame(8, config('home.tools_shown'));
        $this->assertSame(array_slice(ToolVisibility::onHome(), 0, 8), array_slice($this->cards(), 0, 8));
        $eighth = ToolVisibility::onHome()[7];

        $res = $this->save($admin, 'box', true);
        $this->assertSame('box', $this->cards()[0]);
        $this->assertCount(8, $this->cards());
        $this->assertNotContains($eighth, $this->cards(), 'the last of the config gives way');
        $this->assertSame(__('tools.admin.home.first'), $res->json('home_hints.box'));
        // the row the new one pushed over the limit hears of it without a reload
        $this->assertSame(__('tools.admin.home.beyond', ['at' => 9, 'max' => 8]), $res->json('home_hints.'.$eighth));
        $this->assertStringContainsString(e(__('tools.admin.home.first')), $this->actingAs($admin)->get('/admin/tools')->assertOk()->getContent());

        // two chosen: config order first, then the catalogue; a tool the config lists and the admin only left alone has no such note
        config(['home.tools' => ['qr', 'relief']]);
        $this->save($admin, 'relief', false);
        $this->save($admin, 'relief', true);
        $this->assertSame(['relief', 'box', 'qr'], ToolVisibility::onHome());
        $this->assertSame('', $this->save($admin, 'qr', true)->json('home_hints.qr'));
    }

    public function test_a_row_saved_for_something_else_leaves_the_config_deciding(): void
    {
        $admin = $this->admin();
        config(['home.tools' => ['qr']]);
        // the row sends its home box as it stands, ticked for the listed tool and not for the other
        $this->save($admin, 'qr', true)->assertJson(['home' => null]);
        $this->save($admin, 'box', false)->assertJson(['home' => null]);
        $this->assertNull(ToolFlag::where('tool', 'qr')->value('home'));
        // an older client without the box
        $this->actingAs($admin)->postJson(route('admin.tools.update', 'qr'), ['public' => '1'])->assertOk()->assertJson(['home' => null]);

        // what the admin decided stays when the row is saved again
        $this->save($admin, 'qr', false)->assertJson(['home' => false]);
        $this->save($admin, 'qr', false)->assertJson(['home' => false]);
        $this->save($admin, 'qr', true)->assertJson(['home' => true]);
    }

    public function test_a_hidden_tool_stays_off_the_home_page_and_the_row_says_why(): void
    {
        $admin = $this->admin();
        config(['home.tools' => ['qr']]);
        $res = $this->save($admin, 'box', true, false)->assertJson(['home' => true, 'listed' => false]);
        $this->assertSame(__('tools.admin.home.hint'), $res->json('home_hints.box'));
        $this->assertSame(['qr'], $this->cards());
        $this->assertStringContainsString(e(__('tools.admin.home.hint')), $this->actingAs($admin)->get('/admin/tools')->assertOk()->getContent());
    }

    public function test_the_cache_is_cleared_by_a_save(): void
    {
        $admin = $this->admin();
        config(['home.tools' => ['qr']]);
        $this->assertSame(['qr'], ToolVisibility::onHome());
        $this->assertTrue(Cache::has(ToolVisibility::CACHE_KEY));
        $this->save($admin, 'qr', false);
        $this->assertSame([], ToolVisibility::onHome());
    }

    public function test_the_admin_sees_the_count_the_limit_and_a_filter_of_the_home_cards(): void
    {
        $admin = $this->admin();
        config(['home.tools' => ['relief', 'qr', 'organizer'], 'home.tools_shown' => 2]);
        $page = $this->actingAs($admin)->get('/admin/tools')->assertOk()
            ->assertSee(__('tools.admin.col.home'))
            ->assertSee(__('tools.admin.home.count', ['n' => 3, 'max' => 2]))
            ->assertSee(__('tools.admin.home.over', ['max' => 2]))
            ->assertSee(__('tools.admin.home.beyond', ['at' => 3, 'max' => 2]));
        $this->assertMatchesRegularExpression('/name="home" value="1"[^>]*checked/', $page->getContent());

        // the filter lists the cards in the order of the home page
        $rows = $this->actingAs($admin)->get('/admin/tools?show=home')->assertOk()->assertSee(__('tools.admin.filter.home'))->getContent();
        preg_match_all('/data-tool="([a-z_]+)"/', $rows, $found);
        $this->assertSame(['relief', 'qr', 'organizer'], $found[1]);

        // over the limit the texts say so; under it they do not
        config(['home.tools_shown' => 8]);
        $this->get('/admin/tools')->assertOk()->assertDontSee(__('tools.admin.home.over', ['max' => 8]));
        $this->assertSame(3, count(ToolVisibility::onHome()));
    }

    public function test_the_calculation_gets_the_part_names_of_the_tools_pages(): void
    {
        // what parts.ts reads for a charm and a box, in every language
        foreach (['cs', 'en', 'es'] as $locale) {
            app()->setLocale($locale);
            $i18n = CalculatorTexts::all(120);
            foreach (['shape.part.body', 'shape.part.rim', 'shape.part.color', 'shape.part.icing', 'param.part.body.medallion', 'param.part.color_1.medallion', 'param.part.piece', 'param.part.body', 'param.part.body.box', 'param.part.lid', 'param.part.post.hair_tie', 'edit.split.part.piece', 'edit.art.part.plate', 'edit.flexi_cut.part.segment'] as $key) {
                $this->assertArrayHasKey($key, $i18n, "{$locale} {$key}");
            }
            $this->assertNotSame($i18n['param.part.body.box'], $i18n['param.part.body'], "{$locale}: the generic word is no box");
        }
        app()->setLocale('cs');
        $i18n = CalculatorTexts::all(120);
        $this->assertSame(['Podklad', 'Barva :n', 'Jen krabička', 'Jen tělo'], [$i18n['shape.part.body'], $i18n['shape.part.color'], $i18n['param.part.body.box'], $i18n['param.part.body']]);
        $this->assertSame('Celý model (STL)', __('download.stl'));

        // every tool of a picture in colours can say so to the panel, through its page's route
        foreach (array_keys(ParametricGenerator::FAMILY) as $kind) {
            $this->assertTrue(Route::has('tools.'.$kind), $kind);
        }

        // an order by parts says the same for a charm and a box
        $charm = new ModelFile(['origin' => 'tool', 'origin_ref' => 'charm']);
        $this->assertSame(['Podklad', 'Barva 1', 'Barva 3', 'Lem'], array_map(fn ($p) => OrderService::partLabel($charm, $p), ['body', 'color_1', 'color_3', 'rim']));
        $this->assertSame('Jen krabička', OrderService::partLabel(new ModelFile(['origin' => 'tool', 'origin_ref' => 'box']), 'body'));
    }

    public function test_a_part_has_one_name_on_the_page_of_its_tool_and_on_the_order(): void
    {
        foreach (['cs', 'en', 'es'] as $locale) {
            app()->setLocale($locale);
            $medal = new ModelFile(['origin' => 'tool', 'origin_ref' => 'medallion']);
            $this->assertSame([__('param.part.body.medallion'), __('param.part.color_1.medallion')], array_map(fn ($p) => OrderService::partLabel($medal, $p), ['body', 'color_1']));
            // what the tool's page reads first (parts.ts), the same text
            $this->assertSame(['param.part.body.medallion' => __('param.part.body.medallion'), 'param.part.color_1.medallion' => __('param.part.color_1.medallion')], CalculatorTexts::partNamesOf('medallion'));
            // the medal's page has no other word left that could go its own way
            $this->assertArrayNotHasKey('shape.part.body.medallion', CalculatorTexts::all(120), $locale);
            $this->assertSame(str_replace(':n', '2', __('param.part.piece')), OrderService::partLabel(new ModelFile(['origin' => 'tool', 'origin_ref' => 'puzzle']), 'piece_2'), $locale);
        }
        app()->setLocale('cs');
        $medal = new ModelFile(['origin' => 'tool', 'origin_ref' => 'medallion']);
        $this->assertSame(['Medaile a řetěz', 'Číslo a lem'], array_map(fn ($p) => OrderService::partLabel($medal, $p), ['body', 'color_1']));
        $this->assertSame('Díl 2', OrderService::partLabel(new ModelFile(['origin' => 'tool', 'origin_ref' => 'puzzle']), 'piece_2'));

        // the medal's page hands them over to its script
        $this->get(route('tools.medallion'))->assertOk()->assertSee('param.part.body.medallion', false)->assertSee('param.part.color_1.medallion', false);
        // and a tool no list by hand ever named
        $this->get('/tools/cookie-cutter')->assertOk()->assertSee('param.part.body.cutter', false);
    }

    public function test_a_design_tells_its_family_to_the_calculation(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $this->assertNull($this->postJson('/api/tools/param', ['kind' => 'box', 'params' => []])->assertCreated()->json('file.tool.family'));
        $this->assertSame('shape', $this->postJson('/api/tools/param', ['kind' => 'medallion', 'params' => ['line1' => '1']])->assertCreated()->json('file.tool.family'));
    }
}
