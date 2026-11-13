<?php

namespace Tests\Feature;

use App\Domain\Files\PaintedColors;
use App\Domain\Tools\ModelPainter;
use App\Domain\Tools\ToolVisibility;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\ToolFlag;
use App\Models\User;
use App\Support\ToolSeo;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The tool "Vybarvit model" (docs/AS.md): what the visitor painted in the browser comes as one record over the model's
 * STL (a state per triangle, the triangles the brush refined, the colours by height) and leaves as a painted 3MF that
 * the site treats as any uploaded painted 3MF: its colours read back, two prices, an order by colours on one plate.
 * The model is tests/fixtures/cube10.stl: a cube of 10 mm, twelve triangles, the bottom first, then the top.
 */
final class PaintToolTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create();
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python with trimesh is not installed.');
        }
    }

    private function cube(): array
    {
        $path = sys_get_temp_dir().'/mp_paint_'.uniqid().'.stl';
        copy(base_path('tests/fixtures/cube10.stl'), $path);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'kostka.stl', null, null, true)])->assertCreated()->json('file');
    }

    private function bytes(string $data, string $name): UploadedFile
    {
        $path = sys_get_temp_dir().'/mp_paint_'.uniqid().'_'.$name;
        file_put_contents($path, $data);

        return new UploadedFile($path, $name, 'application/octet-stream', null, true);
    }

    /**
     * @param  list<int>  $states  one per triangle of the source
     * @param  list<list<float>>|null  $added  the refined triangles, nine numbers each
     * @param  list<int>  $addedStates
     */
    private function paint(string $uuid, array $record, array $states, ?array $added = null, array $addedStates = [])
    {
        $post = ['record' => json_encode($record + ['v' => 1]), 'states' => $this->bytes(pack('C*', ...$states), 'states.bin')];
        if ($added !== null) {
            $post['added'] = $this->bytes(pack('g*', ...array_merge(...$added)), 'added.bin');
            $post['added_states'] = $this->bytes(pack('C*', ...$addedStates), 'added-states.bin');
        }

        return $this->actingAs($this->user)->post('/api/files/'.$uuid.'/paint', $post, ['Accept' => 'application/json']);
    }

    public function test_a_fill_and_a_height_come_out_as_a_painted_3mf_with_its_colours_read_back(): void
    {
        $src = $this->cube();
        $this->assertSame('ready', $src['status']);
        $this->assertNotNull($src['paint_url'], 'any ready model can be coloured');
        $this->assertStringContainsString('/tools/paint?from='.$src['uuid'], $src['paint_url']);
        $this->assertNull($src['paint']);

        // the bottom red (a fill of one face), everything from 6 mm up blue, the rest stays the model's own white
        $r = $this->paint($src['uuid'], ['triangles' => 12, 'palette' => ['#ffffff', '#d62828', '#1f6fd6'], 'heights' => [['z' => 6, 'color' => 2]]], [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])->assertCreated();
        $f = $this->getJson('/api/files/'.$r->json('file.uuid'))->assertOk()->json('file');
        $this->assertSame('ready', $f['status'], (string) $f['error']);
        $this->assertSame(['paint', '3mf', 'kostka-barvy.3mf'], [$f['kind'], $f['ext'], $f['name']]);

        // the colours by area: blue the top and 4 mm of every side, white 6 mm of every side, red the bottom
        $this->assertSame(['#1F6FD6', '#FFFFFF', '#D62828'], array_column($f['colors'], 'hex'));
        $this->assertEqualsWithDelta(260 / 600, $f['colors'][0]['share'], 0.002);
        $this->assertEqualsWithDelta(240 / 600, $f['colors'][1]['share'], 0.002);
        $this->assertEqualsWithDelta(100 / 600, $f['colors'][2]['share'], 0.002);
        $this->assertSame(['count' => 3, 'merged' => 0, 'too_many' => false, 'status' => 'done'], $f['colors_info']);
        // the sides were cut at 6 mm: 8 side triangles became 24, the preview's ranges cover the STL as it is served
        $file = ModelFile::where('uuid', $f['uuid'])->firstOrFail();
        $this->assertSame(28, $file->triangles);
        $this->assertSame(28, StlFile::binaryCount($file->absoluteStlPath()));
        $this->assertSame([0, 28], [$f['groups'][0]['from'], $f['groups'][2]['to']]);
        $this->assertEqualsWithDelta(1000, $file->volume_mm3, 0.5, 'the same cube');
        $this->assertSame(['x' => 10.0, 'y' => 10.0, 'z' => 10.0], array_map('floatval', $file->bbox));

        // the record is kept with the file, with what the engine made of it
        $this->assertSame($src['uuid'], $f['paint']['source']);
        $this->assertSame(['#ffffff', '#d62828', '#1f6fd6'], $f['paint']['palette']);
        $this->assertSame([['z' => 6, 'color' => 2]], $f['paint']['heights']);
        $this->assertSame(8, $f['paint']['report']['cut']);
        $this->assertFalse($f['paint']['lost']);
        $this->assertSame(12, strlen((string) Storage::disk('models')->get('files/'.$f['uuid'].'/paint/states.bin')));
        $this->assertTrue(PaintedColors::painted($file), 'a painted upload for everything that follows');
        $this->assertSame($src['uuid'], $file->origin_ref);
        // it can be coloured again (its own STL, ordered by colour, is the model then), and downloaded as it is
        $this->assertStringContainsString('from='.$f['uuid'], $f['paint_url']);
        $zip = new \ZipArchive;
        $path = sys_get_temp_dir().'/mp_paint_dl_'.uniqid().'.3mf';
        file_put_contents($path, $this->get('/api/tools/paint/'.$f['uuid'].'/colors.3mf')->assertOk()->streamedContent());
        $this->assertTrue($zip->open($path));
        $this->assertSame(['#FFFFFF', '#D62828', '#1F6FD6'], json_decode((string) $zip->getFromName('Metadata/project_settings.config'), true)['filament_colour']);
        $model = (string) $zip->getFromName('3D/3dmodel.model');
        $zip->close();
        // the largest colour (blue, filament 3) is the object's own; red (filament 2) and white (filament 1) are painted
        $this->assertSame(28, substr_count($model, '<triangle '));
        $this->assertSame(2, substr_count($model, 'paint_color="8"'));
        $this->assertSame(12, substr_count($model, 'paint_color="4"'));
        $this->assertSame(404, $this->get('/api/tools/paint/'.$src['uuid'].'/colors.3mf')->status(), 'only a model coloured here');

        // the calculator: one price in one colour, one in colours
        $token = $this->postJson('/api/calculations', ['file' => $f['uuid'], 'material' => 'PLA'])->assertCreated()->json('calculation.token');
        $calc = $this->getJson('/api/calculations/'.$token)->assertOk()->json('calculation');
        $this->assertSame(3, $calc['colors']['count']);
        $this->assertGreaterThanOrEqual($calc['colors']['one'], $calc['colors']['multi']);
    }

    public function test_a_small_colour_painted_on_purpose_stays_and_a_speck_under_the_nozzle_joins_its_surroundings(): void
    {
        $src = $this->cube();
        // the first bottom triangle is replaced by three triangles around a point close to its first edge: one of them
        // is a sliver of 0.2 mm² painted red (a speck); the second bottom triangle is blue, the top's first one green
        [$a, $b, $c] = $this->triangle(Storage::disk('models')->path('files/'.$src['uuid'].'/model.stl'), 0);
        $mid = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2, ($a[2] + $b[2]) / 2];
        // a point 0.4 % of the way from the middle of the first edge to the third corner: the sliver is 0.4 % of 50 mm²
        $d = array_map(fn ($i) => $mid[$i] + ($c[$i] - $mid[$i]) * 0.004, [0, 1, 2]);
        $added = [[...$a, ...$b, ...$d], [...$b, ...$c, ...$d], [...$c, ...$a, ...$d]];
        $r = $this->paint($src['uuid'], ['triangles' => 12, 'palette' => ['#ffffff', '#d62828', '#1f6fd6', '#2e9e4f']], [255, 2, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0], $added, [1, 0, 0])->assertCreated();
        $f = $this->getJson('/api/files/'.$r->json('file.uuid'))->assertOk()->json('file');
        $this->assertSame('ready', $f['status'], (string) $f['error']);
        $this->assertSame(['count' => 1, 'mm2' => $f['paint']['report']['specks']['mm2']], $f['paint']['report']['specks']);
        $this->assertLessThan(0.5, $f['paint']['report']['specks']['mm2']);
        $this->assertSame(3, $f['paint']['report']['refined']);
        // white, blue and green print; the red speck is gone, and nothing was merged for being a small share
        $this->assertEqualsCanonicalizing(['#FFFFFF', '#1F6FD6', '#2E9E4F'], array_column($f['colors'], 'hex'));
        $this->assertSame([false, false, false], array_column($f['colors'], 'merged'));
        $this->assertSame(14, ModelFile::where('uuid', $f['uuid'])->firstOrFail()->triangles);

        // the same rule on a big model: one triangle of 2 400 (0.04 % of the area, 2 mm²) keeps its colour, where a file
        // painted in a slicer would have it merged under one per cent
        $path = sys_get_temp_dir().'/mp_paint_'.uniqid().'.3mf';
        copy(base_path('tests/fixtures/painted-two-bodies.3mf'), $path);
        $big = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'two.3mf', null, null, true)])->assertCreated()->json('file');
        $states = array_fill(0, 2400, 0);
        $states[5] = 1;
        $r = $this->paint($big['uuid'], ['triangles' => 2400, 'palette' => ['#ffffff', '#d62828']], $states)->assertCreated();
        $f = $this->getJson('/api/files/'.$r->json('file.uuid'))->assertOk()->json('file');
        $this->assertSame(['#FFFFFF', '#D62828'], array_column($f['colors'], 'hex'));
        $this->assertSame([false, false], array_column($f['colors'], 'merged'));
        $this->assertLessThanOrEqual(0.001, $f['colors'][1]['share']);
    }

    /** @return array{0: list<float>, 1: list<float>, 2: list<float>} the corners of a triangle of a binary STL */
    private function triangle(string $stl, int $index): array
    {
        $raw = (string) file_get_contents($stl, false, null, 84 + 50 * $index + 12, 36);
        $v = array_values((array) unpack('g9', $raw));

        return [array_slice($v, 0, 3), array_slice($v, 3, 3), array_slice($v, 6, 3)];
    }

    public function test_a_record_that_does_not_fit_the_model_is_refused_and_a_broken_one_fails_with_its_reason(): void
    {
        $src = $this->cube();
        $pal = ['#ffffff', '#d62828'];
        $states = [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        // another model's record, a version of tomorrow, a palette of one, more colours than any machine holds, a colour that is not one
        $this->assertSame('source_changed', $this->paint($src['uuid'], ['triangles' => 14, 'palette' => $pal], $states)->assertStatus(422)->json('reason'));
        $this->assertSame('bad_record', $this->paint($src['uuid'], ['v' => 2, 'triangles' => 12, 'palette' => $pal], $states)->assertStatus(422)->json('reason'));
        $this->assertSame('bad_record', $this->paint($src['uuid'], ['triangles' => 12, 'palette' => ['#ffffff']], $states)->assertStatus(422)->json('reason'));
        $many = array_map(fn ($i) => sprintf('#%06x', $i * 1000), range(1, ModelPainter::maxColors() + 1));
        $this->assertSame('too_many_colors', $this->paint($src['uuid'], ['triangles' => 12, 'palette' => $many], $states)->assertStatus(422)->json('reason'));
        $this->assertSame('bad_record', $this->paint($src['uuid'], ['triangles' => 12, 'palette' => ['#ffffff', 'red']], $states)->assertStatus(422)->json('reason'));
        // states for another number of triangles, a band in a colour the palette does not have
        $this->assertSame('bad_record', $this->paint($src['uuid'], ['triangles' => 12, 'palette' => $pal], [1, 1, 0])->assertStatus(422)->json('reason'));
        $this->assertSame('bad_record', $this->paint($src['uuid'], ['triangles' => 12, 'palette' => $pal, 'heights' => [['z' => 5, 'color' => 4]]], $states)->assertStatus(422)->json('reason'));
        $this->assertSame(0, ModelFile::where('origin', 'paint')->count(), 'nothing was made');

        // nothing painted at all: the engine says so and the file fails with the reason, the source stays as it was
        $r = $this->paint($src['uuid'], ['triangles' => 12, 'palette' => $pal], array_fill(0, 12, 0))->assertCreated();
        $f = $this->getJson('/api/files/'.$r->json('file.uuid'))->assertOk()->json('file');
        $this->assertSame(['failed', 'one_color'], [$f['status'], $f['error']]);
        // triangles that are not the model's surface (a refined triangle moved 5 mm up): refused by the area and the box
        [$a, $b, $c] = $this->triangle(Storage::disk('models')->path('files/'.$src['uuid'].'/model.stl'), 0);
        $up = fn (array $p) => [$p[0], $p[1], $p[2] + 25];
        $r = $this->paint($src['uuid'], ['triangles' => 12, 'palette' => $pal], [255, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], [[...$up($a), ...$up($b), ...$up($c)]], [1])->assertCreated();
        $f = $this->getJson('/api/files/'.$r->json('file.uuid'))->assertOk()->json('file');
        $this->assertSame(['failed', 'mesh_changed'], [$f['status'], $f['error']]);
        $this->assertSame('ready', $this->getJson('/api/files/'.$src['uuid'])->json('file.status'));
    }

    public function test_an_order_of_a_coloured_model_prints_by_colours_on_one_plate_like_a_painted_upload(): void
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        [$runner] = FarmAgent::issue('test agent');
        $s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $runner->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE]);
        $pla = FarmColor::findOrFail($s1->slots()->whereNotNull('farm_color_id')->firstOrFail()->farm_color_id)->farm_material_id;
        $free = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->get();
        $spool = function (FarmPrinterSlot $slot, string $hex, string $name) use ($pla) {
            $color = FarmColor::create(['farm_material_id' => $pla, 'name' => $name, 'hex' => $hex, 'enabled' => true]);
            $slot->update(['farm_color_id' => $color->id, 'remaining_g' => 800, 'enabled' => true]);

            return $color;
        };
        $red = $spool($free[0], '#D62828', 'Červená');
        $blue = $spool($free[1], '#1F6FD6', 'Modrá');

        $src = $this->cube();
        // the top half blue by height, the bottom face red: two colours that meet in no layer but the first
        $r = $this->paint($src['uuid'], ['triangles' => 12, 'palette' => ['#d62828', '#1f6fd6'], 'heights' => [['z' => 5, 'color' => 1]]], array_fill(0, 12, 0))->assertCreated();
        $uuid = $r->json('file.uuid');
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid, 'colors' => 'painted'])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertTrue($order->isByParts(), 'coloured: by colour from the start');
        $this->assertSame(['color_1', 'color_2'], array_column($order->partPlates(), 'part'));
        $this->assertEqualsCanonicalizing([$red->id, $blue->id], array_map('intval', array_column($order->partPlates(), 'color_id')));
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame('painted', $order->timings['one_plate_project'] ?? null, 'the painted file itself goes to the slicer');
        $this->assertCount(2, (array) ($order->slice_params['filaments'] ?? []));
        // in one colour on request, as with any painted file
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid, 'colors' => 'one'])->assertCreated();
        $this->assertFalse(FarmOrder::where('token', basename($r->json('url')))->firstOrFail()->isByParts());
    }

    public function test_the_page_opens_in_three_languages_with_the_farms_colours_and_is_in_the_catalogue(): void
    {
        foreach (['cs' => '/tools/paint', 'en' => '/en/tools/paint', 'es' => '/es/tools/paint'] as $lang => $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            app()->setLocale($lang);
            $this->assertStringContainsString('id="tool-page" data-tool="paint" data-module="paint"', $html);
            $this->assertStringContainsString('window.MP_PAINT', $html);
            $this->assertStringContainsString(e(__('tools.paint.title')), $html);
            $this->assertStringContainsString(e(ToolSeo::texts('paint', $lang)['h1']), $html);
            foreach (['paint.mode.fill', 'paint.mode.brush', 'paint.mode.erase', 'paint.mode.turn', 'paint.step.model', 'paint.step.colors', 'paint.step.height', 'paint.honest.title'] as $key) {
                $this->assertNotSame($key, __($key), $lang);
                $this->assertStringContainsString(e(__($key)), $html, $key.' '.$lang);
            }
            // every text the script is handed is a text, never a key
            // (Js::from writes JSON.parse('…') with the JSON as a JavaScript string: read as a string first, then as JSON)
            preg_match('/window\.MP_PAINT = JSON\.parse\(\'(.*?)\'\);\s*<\/script>/s', $html, $m);
            $cfg = json_decode((string) json_decode('"'.($m[1] ?? '').'"'), true);
            $this->assertIsArray($cfg, $lang);
            foreach ($cfg['i18n'] as $key => $text) {
                $this->assertNotSame($key, $text, $lang);
                $this->assertStringNotContainsString('paint.', (string) $text, $key.' '.$lang);
            }
            $this->assertSame(ModelPainter::maxColors(), $cfg['limits']['max_colors']);
            $this->assertNotEmpty($cfg['tiles']);
            foreach ($cfg['tiles'] as $tile) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $tile['hex']);
                $this->assertNotSame('', $tile['name']);
            }
        }
        // the farm's catalogue is where the tiles come from: a spool's colour is one of them, once
        app()->setLocale('cs');
        $spool = FarmColor::where('enabled', true)->whereNotNull('hex')->whereNotNull('code')->firstOrFail();
        $tiles = ModelPainter::tiles(app(\App\Domain\Farm\Palette::class));
        $this->assertContains(strtolower($spool->hex), array_column($tiles, 'hex'));
        $this->assertSame(count($tiles), count(array_unique(array_column($tiles, 'hex'))));

        $this->get('/tools')->assertOk()->assertSee(__('tools.paint.title'))->assertSee(route('tools.paint'), false);
        $this->get('/tools/paint?from=00000000-0000-0000-0000-000000000000')->assertOk();

        // switched off at /admin/tools: the page, the API and the offer in a file's description are gone
        $src = $this->cube();
        ToolFlag::updateOrCreate(['tool' => 'paint'], ['public' => false]);
        ToolVisibility::forget();
        $this->get('/tools/paint')->assertNotFound();
        $this->assertNull($this->getJson('/api/files/'.$src['uuid'])->json('file.paint_url'));
        $this->paint($src['uuid'], ['triangles' => 12, 'palette' => ['#ffffff', '#d62828']], [1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])->assertNotFound();
    }
}
