<?php

namespace Tests\Feature;

use App\Domain\Tools\Artwork;
use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The composer of layers (engines/python/compose_kind.py): texts, pictures (of the library or the visitor's own) and
 * shapes laid one on another, each in its filament. Its builder, its server side and its page; dragging a layer in the
 * preview lives in the browser alone (Viewer.setFrame).
 */
class ComposeToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
    }

    private function preview(array $params, string $part = 'all')
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => 'compose', 'params' => $params, 'part' => $part, 'pieces' => true]);
    }

    /** @return array<string, mixed> */
    private function meta(array $params, string $part = 'all'): array
    {
        return json_decode((string) $this->preview($params, $part)->assertOk()->headers->get('X-Model-Meta'), true);
    }

    public function test_layers_lie_one_on_another_each_in_its_filament(): void
    {
        $cloud = ['kind' => 'shape', 'shape' => 'cloud', 'x' => 0, 'y' => 0, 'w' => 100, 'code' => 'white'];
        $name = ['kind' => 'text', 'text' => 'Ela', 'typeface' => 'script', 'x' => 5, 'y' => -4, 'w' => 50, 'code' => 'blue'];
        $star = ['kind' => 'art', 'art' => 'lib:hearts-stars/star', 'x' => -30, 'y' => 8, 'w' => 18, 'turn' => 15, 'code' => 'yellow'];
        $m = $this->meta(['thickness' => 3, 'step' => 0.8, 'layers' => [$cloud, $name, $star]]);
        $n = $m['notes'];
        // the cloud is the plate, the name a step higher, the star another step: three parts, three filaments, two changes
        $this->assertEqualsWithDelta(100, $m['bbox']['x'], 0.1);
        $this->assertEqualsWithDelta(3 + 2 * 0.8, $m['bbox']['z'], 0.01);
        $this->assertSame(['layer_1', 'layer_2', 'layer_3'], $n['parts']);
        $this->assertSame(array_column($m['parts'], 'name'), $n['parts']);
        $this->assertSame(3, $n['filaments']);
        $this->assertFalse($n['multi_material']);
        $this->assertSame([[3.0, 'blue'], [3.8, 'yellow']], array_map(fn ($c) => [(float) $c['z'], $c['code']], $n['color_changes']));
        $this->assertArrayNotHasKey('color_change_mm', $n);
        // every layer says where it lies, for the page to pick it and move it
        $this->assertCount(3, $n['layers']);
        $this->assertEqualsWithDelta(50, $n['layers'][1]['box'][2] - $n['layers'][1]['box'][0], 0.2);
        $this->assertEqualsWithDelta(($n['layers'][0]['box'][0] + $n['layers'][0]['box'][2]) / 2 + 5, ($n['layers'][1]['box'][0] + $n['layers'][1]['box'][2]) / 2, 0.3);
        // under a layer the ones below are filled up: the star's band is the star alone, the name's band the name and the star
        $top = $this->meta(['layers' => [$cloud, $name, $star]], 'layer_3');
        $mid = $this->meta(['layers' => [$cloud, $name, $star]], 'layer_2');
        $alone = $this->meta(['layers' => [$cloud, $name]], 'layer_2');
        $this->assertEqualsWithDelta($alone['volume_mm3'] + $top['volume_mm3'], $mid['volume_mm3'], 0.35 * $top['volume_mm3']);
        $this->assertEqualsWithDelta(0.8, $top['bbox']['z'], 0.01);
        // two layers in one filament are no change; a hidden layer is not there; another order is another model
        $same = $this->meta(['layers' => [$cloud, ['code' => 'white'] + $name]])['notes'];
        $this->assertSame(1, $same['filaments']);
        $this->assertSame([], $same['color_changes']);
        $this->assertSame(['layer_1', 'layer_2'], $this->meta(['layers' => [$cloud, $name, ['hidden' => true] + $star]])['notes']['parts']);
        $swapped = $this->meta(['layers' => [$cloud, $star, $name]])['notes'];
        $this->assertSame('yellow', $swapped['color_changes'][0]['code']);
        // one change only is what every slicer project and the farm have always known
        $this->assertSame(3.0, (float) $this->meta(['layers' => [$cloud, $name]])['notes']['color_change_mm']);
    }

    public function test_what_lies_apart_is_tied_and_a_base_is_added(): void
    {
        $two = [['kind' => 'shape', 'shape' => 'circle', 'x' => 0, 'y' => 0, 'w' => 30, 'code' => 'red'], ['kind' => 'shape', 'shape' => 'rect', 'x' => 50, 'y' => 0, 'w' => 30, 'code' => 'red']];
        $m = $this->meta(['layers' => $two]);
        $this->assertContains('pieces_tied', $m['notes']['warnings']);
        $this->assertGreaterThanOrEqual(1, $m['notes']['links']);
        $this->assertEqualsWithDelta(80, $m['bbox']['x'], 0.5);
        // a turned layer takes another box; the sticks of a cake topper reach down by their length; an eyelet sits on top
        $flat = $this->meta(['layers' => [['kind' => 'shape', 'shape' => 'rect', 'w' => 60, 'code' => 'red']]]);
        $turned = $this->meta(['layers' => [['kind' => 'shape', 'shape' => 'rect', 'w' => 60, 'turn' => 90, 'code' => 'red']]]);
        $this->assertEqualsWithDelta($flat['bbox']['x'], $turned['bbox']['y'], 0.1);
        $topper = $this->meta(['base' => 'sticks', 'spike' => 60, 'layers' => [['kind' => 'text', 'text' => '2', 'typeface' => 'archivo', 'w' => 60, 'code' => 'red']]]);
        $bare = $this->meta(['layers' => [['kind' => 'text', 'text' => '2', 'typeface' => 'archivo', 'w' => 60, 'code' => 'red']]]);
        $this->assertEqualsWithDelta($bare['bbox']['y'] + 60, $topper['bbox']['y'], 0.5);
        $hung = $this->meta(['base' => 'eyelet', 'eye_hole' => 4, 'layers' => [['kind' => 'shape', 'shape' => 'heart', 'w' => 50, 'code' => 'red']]]);
        $this->assertSame(4, (int) $hung['notes']['eyelet'][2]);
        $this->assertGreaterThan($this->meta(['layers' => [['kind' => 'shape', 'shape' => 'heart', 'w' => 50, 'code' => 'red']]])['volume_mm3'], $hung['volume_mm3']);
    }

    public function test_a_composition_is_checked_and_kept_with_its_filaments(): void
    {
        $said = fn ($response) => $response->assertStatus(422)->json('errors');
        // nothing to build, too much to build, a layer that is nothing we know, a picture that is not of the library
        $this->assertArrayHasKey('params.layers', $said($this->preview([])));
        $this->assertArrayHasKey('params.layers', $said($this->preview(['layers' => array_fill(0, 13, ['kind' => 'shape', 'shape' => 'circle'])])));
        $this->assertArrayHasKey('params.layers.0.kind', $said($this->preview(['layers' => [['kind' => 'photo']]])));
        $this->assertArrayHasKey('params.layers.0.art', $said($this->preview(['layers' => [['kind' => 'art', 'art' => '../../etc/passwd']]])));
        $this->assertArrayHasKey('params.layers.0.w', $said($this->preview(['layers' => [['kind' => 'shape', 'shape' => 'circle', 'w' => 900]]])));
        $this->assertSame(__('param.error.no_layers'), $said($this->preview(['layers' => [['kind' => 'shape', 'shape' => 'circle', 'hidden' => true]]]))['params'][0]);
        // a design remembers its layers, the filament of every part and where the print changes it
        $layers = [['kind' => 'shape', 'shape' => 'heart', 'w' => 80, 'code' => 'red'], ['kind' => 'text', 'text' => 'Ema', 'typeface' => 'lobster', 'w' => 44, 'code' => 'white']];
        $created = $this->postJson('/api/tools/param', ['kind' => 'compose', 'params' => ['layers' => $layers]])->assertCreated();
        $this->assertSame('compose', $created->json('file.kind'));
        $this->assertSame('compose', $created->json('file.tool.kind'));      // the page can open it again
        $kept = ModelFile::where('uuid', $created->json('file.uuid'))->firstOrFail()->tool_params;
        $this->assertSame(['heart', 'Ema'], [$kept['layers'][0]['shape'], $kept['layers'][1]['text']]);
        $this->assertSame(['layer_1', 'layer_2'], $kept['parts']);
        $this->assertSame('red', $kept['part_colors']['layer_1']['code']);
        $this->assertSame('white', $kept['color_changes'][0]['code']);
        $this->assertFalse($kept['multi_material']);
    }

    public function test_a_picture_of_ones_own_is_a_layer_and_stays_with_the_design(): void
    {
        $ann = User::factory()->create();
        $flag = UploadedFile::fake()->createWithContent('vlajka.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 20"><path d="M0 0L30 10L0 20Z"/></svg>');
        $id = $this->actingAs($ann)->post('/api/tools/artwork', ['file' => $flag], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
        try {
            $layers = [['kind' => 'shape', 'shape' => 'circle', 'w' => 60, 'code' => 'white'], ['kind' => 'art', 'art' => $id, 'art_name' => 'vlajka.svg', 'w' => 30, 'code' => 'red']];
            $box = $this->meta(['layers' => $layers])['notes']['layers'][1]['box'];
            $this->assertEqualsWithDelta([30, 20], [$box[2] - $box[0], $box[3] - $box[1]], 0.2);        // the flag as it was drawn, 30 wide
            // the design keeps a copy of its own: the upload may go after its thirty days
            $created = $this->actingAs($ann)->postJson('/api/tools/param', ['kind' => 'compose', 'params' => ['layers' => $layers]])->assertCreated();
            $uuid = $created->json('file.uuid');
            $kept = ModelFile::where('uuid', $uuid)->firstOrFail()->tool_params['layers'];
            $this->assertSame(['file:'.$uuid.':1', 'vlajka.svg'], [$kept[1]['art'], $kept[1]['art_name']]);
            File::deleteDirectory(storage_path('app/artwork/u'.$ann->id));
            $this->assertNull(Artwork::path($id));
            $this->assertNotNull(Artwork::path($kept[1]['art']));
            $this->assertSame(['layer_1', 'layer_2'], $this->meta(['layers' => $kept])['notes']['parts']);
            // a layer that points at a picture nobody has is said so, not built without it
            $gone = $this->preview(['layers' => [['kind' => 'art', 'art' => 'file:'.$uuid.':7']]])->assertStatus(422)->json('errors');
            $this->assertSame(__('param.error.artwork_gone'), $gone['params'][0]);
        } finally {
            File::deleteDirectory(storage_path('app/artwork/u'.$ann->id));
        }
    }

    public function test_the_page_lists_the_layers_and_opens_with_a_composition(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            app()->setLocale($locale);
            $html = $this->get($prefix.'/tools/compose')->assertOk()->assertSee(__('tools.compose.title'))->assertSee(__('param.compose.layers'))->getContent();
            // the list, the three ways to add a layer, the fields of a layer, a typeface for every face of the registry
            foreach (['id="compose-layers"', 'data-add-layer="text"', 'data-add-layer="art"', 'data-add-layer="shape"', 'id="compose-edit"', 'data-layer-slide="turn"', 'preset: "cloud"'] as $piece) {
                $this->assertStringContainsString($piece, $html, $piece);
            }
            $this->assertSame(count(ParametricGenerator::FONTS), preg_match_all('/<option value="[a-z_]+">/', explode('id="compose-shape"', explode('id="compose-font"', $html)[1])[0]));
            $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $locale);
        }
        app()->setLocale('cs');
        // every composition the page offers to start from is one the server takes as it is, colours named and all
        foreach (ParametricGenerator::PRESETS['compose'] as $name => $preset) {
            $m = $this->meta($preset);
            $this->assertSame(count($preset['layers']), count($m['notes']['layers']), $name);
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('param.preset.'.$name, __('param.preset.'.$name, [], $locale));
            }
        }
        $this->assertSame(60, (int) $this->meta(ParametricGenerator::PRESETS['compose']['topper'])['notes']['sticks']);
    }

    public function test_the_forms_of_its_products_show_the_way_to_the_composer(): void
    {
        // topper, ornament and the big letter stay forms; each offers the same product as a composition
        $pages = ['topper' => '/tools/cake-topper', 'nameplate' => '/tools/nameplate', 'keychain' => '/tools/keychain', 'ornament' => '/tools/ornament', 'name_letter' => '/tools/name-letter'];
        $this->assertSame(array_keys($pages), array_keys(ParametricGenerator::COMPOSED));
        foreach ($pages as $tool => $page) {
            $preset = ParametricGenerator::COMPOSED[$tool];
            $this->assertArrayHasKey($preset, ParametricGenerator::PRESETS['compose'], $tool);
            if (in_array($tool, ['nameplate', 'keychain'], true)) {
                continue;
            }
            $this->get($page)->assertOk()->assertSee('/tools/compose?preset='.$preset, false)->assertSee(__('param.compose.open'));
        }
        // a tool the composer has nothing for says nothing of it
        $this->get('/tools/magnet')->assertOk()->assertDontSee('/tools/compose?preset=', false);
    }

    public function test_the_cards_of_the_nameplate_and_the_key_ring_open_the_composer(): void
    {
        foreach (['nameplate' => ['plate', 'sign'], 'keychain' => ['keyring', 'keychain']] as $tool => [$preset, $form]) {
            foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
                app()->setLocale($locale);
                $address = $prefix.'/tools/'.$tool;
                // the address is the composer with the product in it, under the tool's own title and texts
                $html = $this->get($address)->assertOk()->assertSee(__('tools.'.$tool.'.title'))->assertSee(__('param.'.$tool.'.lead'))->getContent();
                foreach (['kind: "compose"', 'preset: "'.$preset.'"', 'sample: null', 'id="compose-layers"', $address.'?form=1'] as $piece) {
                    $this->assertStringContainsString($piece, $html, $tool.' '.$piece);
                }
                $this->assertStringContainsString(e(__('param.compose.form.'.$tool)), $html);
                $this->assertDoesNotMatchRegularExpression('/>\s*(param|tools|toolpage)\.[a-z_.]+\s*</', $html, $tool.' '.$locale);
                // the quick form it used to be is still there for who asks, and shows the way back
                $quick = $this->get($address.'?form=1')->assertOk()->assertSee(__('param.'.$tool.'.lead_form'))->assertSee(__('param.compose.open'))->getContent();
                $this->assertStringContainsString('kind: "'.$form.'"', $quick);
                $this->assertStringNotContainsString('id="compose-layers"', $quick);
            }
            app()->setLocale('cs');
            $this->assertSame('compose', config('tools.'.$tool.'.card.kind'));
            $this->assertSame($preset, config('tools.'.$tool.'.card.preset'));
        }
        // a key ring made with the form opens in the form again; one made in the composer goes to the composer's own address
        $old = $this->postJson('/api/tools/param', ['kind' => 'keychain', 'params' => ['line1' => 'Jana']])->assertCreated()->json('file.uuid');
        $this->assertStringContainsString('kind: "keychain"', $this->get('/tools/keychain?from='.$old)->assertOk()->getContent());
        $new = $this->postJson('/api/tools/param', ['kind' => 'compose', 'params' => ParametricGenerator::PRESETS['compose']['keyring']])->assertCreated()->json('file.uuid');
        $this->assertStringContainsString('/tools/compose?from='.$new, (string) ModelFile::where('uuid', $new)->firstOrFail()->toolUrl());
        $this->assertStringContainsString('/tools/keychain?from='.$old, (string) ModelFile::where('uuid', $old)->firstOrFail()->toolUrl());
    }
}
