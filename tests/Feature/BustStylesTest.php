<?php

namespace Tests\Feature;

use App\Domain\Generation\BustStyles;
use App\Domain\Generation\PedestalChanger;
use App\Engines\Repair\PythonTool;
use App\Models\GenerationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Typy busty" at /admin/ai: the owner hides bust styles and picks the one the bust page starts with, without code
 * (Roman, 17 Nov 2026: all three classic busts are added, he tries them with ideal photos, then hides some and sets
 * the default). The bust page, the validation of a new request, the base of a request that names none and the free
 * change of base all read it; a model made with a style that was hidden later still opens and changes its base.
 */
class BustStylesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 20]);
        Storage::fake('models');
        Storage::fake('local');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'person', 'reason' => ''])]]])]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    private function bust(array $more = [])
    {
        return $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1] + $more, ['Accept' => 'application/json']);
    }

    /** @return array<string, bool> the bases of the bust page in its order, true for the one it starts with */
    private function offered(): array
    {
        preg_match_all('/<input type="radio" name="pedestal" value="(\w+)"([^>]*)>/', $this->get('/tools/figure')->assertOk()->getContent(), $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($row) => [$row[1] => str_contains($row[2], 'checked')])->all();
    }

    public function test_until_the_owner_chooses_every_style_is_shown_and_the_page_starts_as_before(): void
    {
        $styles = app(BustStyles::class);
        $this->assertSame(PedestalChanger::BUST_STYLES, $styles->shown());
        $this->assertSame(['classic', 'classic_round', 'classic_stem'], array_slice($styles->shown(), 0, 3));   // the picture's order: middle, right, left
        $this->assertSame([], $styles->hidden());
        $this->assertSame('classic', $styles->default());
        $this->assertSame(PedestalChanger::TYPES, $styles->allowed());
        $this->assertSame(['classic'], array_keys(array_filter($this->offered())));

        $this->actingAs($this->admin())->get('/admin/ai')->assertOk()->assertSee('Typy busty')->assertSee('Busta na bloku')->assertSee('name="shown[]" value="classic_stem"', false);
    }

    public function test_the_owner_hides_styles_and_sets_the_default_and_the_page_and_the_server_follow(): void
    {
        $admin = $this->admin();
        // made while the antique style was still on offer
        $old = $this->bust(['pedestal' => 'antique', 'pedestal_name' => 'Hadrianus'])->assertCreated()->json('generation.file.uuid');

        $this->actingAs($admin)->post('/admin/ai/bust-styles', ['shown' => ['socle', 'classic', 'classic_round'], 'default' => 'classic'])->assertRedirect()->assertSessionHas('status');
        $styles = app(BustStyles::class);
        $this->assertSame(['classic', 'classic_round', 'socle'], $styles->shown());                  // the page's order, not the form's
        $this->assertSame(['classic_stem', 'antique', 'cut'], $styles->hidden());
        $this->assertSame('classic', $styles->default());
        $html = $this->actingAs($admin)->get('/admin/ai')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="shown\[\]" value="classic"[^>]* checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="shown\[\]" value="antique"[^>]* checked/', $html);
        $this->assertMatchesRegularExpression('/name="default" value="classic"[^>]* checked/', $html);
        auth()->logout();

        // the bust page: the shown styles and the plain bases, starting with the owner's choice
        $this->assertSame(['classic' => true, 'classic_round' => false, 'socle' => false, 'round' => false, 'square' => false, 'hexagon' => false, 'column' => false, 'plaque' => false, 'none' => false], $this->offered());
        $page = $this->get('/tools/figure')->getContent();
        $this->assertStringContainsString(__('figure.pedestal.about.classic'), $page);
        $this->assertStringNotContainsString(__('figure.pedestal.about.antique'), $page);

        // a new bust: a hidden style is refused, a bust that names no base gets the default, a figure the round base
        $this->bust(['pedestal' => 'antique'])->assertStatus(422);
        $this->bust(['pedestal' => 'classic_round'])->assertCreated();
        $this->bust(['pedestal_name' => 'Ema'])->assertCreated();
        $made = GenerationRequest::latest('id')->firstOrFail();
        $this->assertSame(['classic', 'Ema'], [$made->description['pedestal'], $made->description['pedestal_name']]);
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'figure', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('round', GenerationRequest::latest('id')->firstOrFail()->description['pedestal']);

        // the calculator's "change the base": the hidden styles are marked, a model that has one still finds it there
        $calc = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="antique"\s+data-hidden\s*>/', $calc);
        $this->assertDoesNotMatchRegularExpression('/<option value="classic"\s+data-hidden/', $calc);
        $this->assertStringContainsString('data-named="'.implode(',', PedestalChanger::NAMED).'"', $calc);

        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        // the model made with the hidden style: it is described as before, keeps its style under another name,
        // cannot take another hidden style, and changes away to a shown one or a plain base
        $this->getJson('/api/files/'.$old)->assertOk()->assertJsonPath('file.generation.pedestal.type', 'antique')->assertJsonPath('file.generation.pedestal.name', 'Hadrianus');
        $same = $this->postJson('/api/files/'.$old.'/pedestal', ['type' => 'antique', 'name' => 'Trajanus'])->assertCreated()->assertJsonPath('file.generation.pedestal.type', 'antique')->assertJsonPath('file.generation.pedestal.name', 'Trajanus');
        $this->postJson('/api/files/'.$old.'/pedestal', ['type' => 'cut'])->assertStatus(422);
        $away = $this->postJson('/api/files/'.$same->json('file.uuid').'/pedestal', ['type' => 'classic', 'name' => 'Ema'])->assertCreated()->assertJsonPath('file.generation.pedestal.type', 'classic');
        // once changed away the hidden style cannot be chosen anew
        $this->postJson('/api/files/'.$away->json('file.uuid').'/pedestal', ['type' => 'antique'])->assertStatus(422);
        $this->postJson('/api/files/'.$away->json('file.uuid').'/pedestal', ['type' => 'round'])->assertCreated();
    }

    public function test_the_default_must_be_a_shown_style_and_nothing_shown_means_the_round_base(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/ai/bust-styles', ['shown' => ['classic'], 'default' => 'socle'])->assertSessionHasErrors('default');
        $this->actingAs($admin)->post('/admin/ai/bust-styles', ['shown' => ['pyramid'], 'default' => 'pyramid'])->assertSessionHasErrors('shown.0');
        $this->assertSame(PedestalChanger::BUST_STYLES, app(BustStyles::class)->shown());           // nothing was saved

        // shown without a default: the first shown one
        $this->actingAs($admin)->post('/admin/ai/bust-styles', ['shown' => ['antique', 'classic_stem']])->assertSessionHasNoErrors();
        $this->assertSame('classic_stem', app(BustStyles::class)->default());

        // every style hidden: the bust page offers the plain bases and starts with the round one
        $this->actingAs($admin)->post('/admin/ai/bust-styles', [])->assertSessionHasNoErrors();
        $this->assertSame([], app(BustStyles::class)->shown());
        $this->assertSame('round', app(BustStyles::class)->default());
        auth()->logout();
        $this->assertSame(['round' => true, 'square' => false, 'hexagon' => false, 'column' => false, 'plaque' => false, 'none' => false], $this->offered());
        $this->bust()->assertCreated();
        $this->assertSame('round', GenerationRequest::latest('id')->firstOrFail()->description['pedestal']);

        // a visitor has no say here
        $this->post('/admin/ai/bust-styles', ['shown' => ['classic']])->assertRedirect();
        $this->assertSame([], app(BustStyles::class)->shown());
    }
}
