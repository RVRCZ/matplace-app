<?php

namespace Tests\Feature;

use App\Domain\Farm\Palette;
use App\Domain\Files\PaintedColors;
use App\Domain\Tools\ModelPainter;
use App\Domain\Tools\ToolVisibility;
use App\Engines\Contracts\Slicer;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
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

    /**
     * The Kobra S1 run by an agent (it changes spools), with a spool of each colour in its free positions.
     *
     * @param  array<string, string>  $colors  hex => name
     * @return list<FarmColor>
     */
    private function spools(array $colors): array
    {
        $s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        [$runner] = FarmAgent::issue('test agent');
        $s1->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $runner->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE]);
        $pla = FarmColor::findOrFail($s1->slots()->whereNotNull('farm_color_id')->firstOrFail()->farm_color_id)->farm_material_id;
        $free = $s1->slots()->whereNull('farm_color_id')->orderBy('slot')->get()->values();
        $out = [];
        foreach (array_keys($colors) as $i => $hex) {
            $color = FarmColor::create(['farm_material_id' => $pla, 'name' => $colors[$hex], 'hex' => $hex, 'enabled' => true]);
            $free[$i]->update(['farm_color_id' => $color->id, 'remaining_g' => 800, 'enabled' => true]);
            $out[] = $color;
        }

        return $out;
    }

    /**
     * The record of a stroke drawn in a real browser (scripts/paint_walk.mjs, Chrome, 13 Nov 2026): on the cube a tap
     * filled one side black, a stroke of the brush of 1 mm in red went across two sides, the brush refined every one of
     * the twelve triangles (2 846 pieces). tests/fixtures/paint/cube-stroke-*.bin are the three files the page sent.
     *
     * @param  array<string, mixed>  $record
     */
    private function stroke(string $uuid, array $record = [])
    {
        $file = fn (string $name) => new UploadedFile(base_path('tests/fixtures/paint/cube-stroke-'.$name.'.bin'), $name.'.bin', 'application/octet-stream', null, true);

        return $this->actingAs($this->user)->post('/api/files/'.$uuid.'/paint', [
            'record' => json_encode($record + ['v' => 1, 'triangles' => 12, 'palette' => ['#f4f4f2', '#1b1b1d', '#d1232a']]),
            'states' => $file('states'), 'added' => $file('added'), 'added_states' => $file('added-states'),
        ], ['Accept' => 'application/json']);
    }

    public function test_a_stroke_drawn_in_a_browser_leaves_the_surface_whole_and_its_colours_on_it(): void
    {
        $src = $this->cube();
        // the stroke and the fill as they were drawn, and red from 6.3 mm up for what is not painted
        $r = $this->stroke($src['uuid'], ['heights' => [['z' => 6.3, 'color' => 2]]])->assertCreated();
        $f = $this->getJson('/api/files/'.$r->json('file.uuid'))->assertOk()->json('file');
        $this->assertSame('ready', $f['status'], (string) $f['error']);
        $file = ModelFile::where('uuid', $f['uuid'])->firstOrFail();
        // the brush split edges, both triangles of an edge at once: the mesh is the same closed cube, no hole, no T-junction
        $this->assertTrue((bool) ($file->mesh_report['watertight'] ?? false), json_encode($file->mesh_report));
        $this->assertEqualsWithDelta(1000, $file->volume_mm3, 0.5);
        $this->assertEqualsWithDelta(600, $file->area_mm2, 0.5);
        $this->assertSame(['x' => 10.0, 'y' => 10.0, 'z' => 10.0], array_map('floatval', $file->bbox));
        $this->assertNotContains('not_watertight', $f['issues']);
        $report = $f['paint']['report'];
        $this->assertSame(2846, $report['refined']);
        $this->assertGreaterThan(0, $report['cut'], 'the height at 6.3 mm cut the triangles it crosses');
        $this->assertSame(2846 + 2 * $report['cut'], $report['triangles'], 'a triangle cut at a height becomes three');
        $this->assertSame(0, $report['specks']['count'], 'the stroke is one clean band, nothing under the nozzle\'s width');
        // red: the top, 3.7 mm of three sides and of the black side's rest, and the stroke; black: its side below 6.3 mm
        // less the stroke; the model's own colour the rest
        $share = array_column($f['colors'], 'share', 'hex');
        $this->assertEqualsCanonicalizing(['#D1232A', '#F4F4F2', '#1B1B1D'], array_keys($share));
        $this->assertEqualsWithDelta(1.0, array_sum($share), 0.002);
        $this->assertGreaterThan((100 + 3 * 37) / 600, $share['#D1232A'], 'more red than the height alone gives: the stroke');
        $this->assertLessThan((100 + 4 * 37 + 60) / 600, $share['#D1232A']);
        // the black side keeps what the stroke did not take: the height does not repaint what was painted by hand
        $this->assertGreaterThan(0.10, $share['#1B1B1D']);
        $this->assertLessThan(100 / 600, $share['#1B1B1D']);
        // the preview's STL is the refined mesh in colour order
        $this->assertSame($report['triangles'], $file->triangles);
        $this->assertSame([0, $file->triangles], [$f['groups'][0]['from'], $f['groups'][count($f['groups']) - 1]['to']]);
    }

    /**
     * On this machine with the real OrcaSlicer (ORCA_LOCAL = its orca-slicer.exe): the cube with the browser's stroke, the
     * fill and a colour by height is ordered in colours and slices on one plate of the S1 from the painted file itself,
     * three filaments, every one of them used. Skipped elsewhere; PAINT_SAMPLES (a folder) gets the numbers and the plate.
     */
    public function test_a_coloured_model_slices_on_one_plate_with_the_real_orca(): void
    {
        $exe = (string) env('ORCA_LOCAL', '');
        if ($exe === '' || ! is_file($exe)) {
            $this->markTestSkipped('ORCA_LOCAL not set: the real slice runs on Roman\'s PC only');
        }
        config(['engines.slicer' => 'orca', 'engines.orca.bin' => $exe, 'engines.orca.xvfb' => false, 'engines.orca.cache' => false,
            'engines.orca.work_dir' => sys_get_temp_dir().'/mp_orca_'.uniqid(), 'engines.orca.timeout' => 600]);
        $this->app->forgetInstance(Slicer::class);
        [$white, $black, $red] = $this->spools(['#F4F4F2' => 'Bílá', '#1B1B1D' => 'Černá', '#D1232A' => 'Červená']);
        $src = $this->cube();
        // three times the size, so the stroke is 6 mm wide and every colour has layers of its own to show in the G-code
        $r = $this->stroke($src['uuid'], ['heights' => [['z' => 6.3, 'color' => 2]]])->assertCreated();
        $uuid = $r->json('file.uuid');
        $t = microtime(true);
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid, 'colors' => 'painted', 'scale' => 3])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $seconds = round(microtime(true) - $t, 1);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings).' '.json_encode(collect((array) $order->check)->except(['mesh'])->all()));
        $this->assertTrue($order->onePlate(), json_encode($order->timings));
        $this->assertSame('painted', $order->timings['one_plate_project'] ?? null);
        $this->assertEqualsCanonicalizing([$white->id, $black->id, $red->id], array_map('intval', array_column($order->partPlates(), 'color_id')));
        $grams = (array) $order->slice_result['filament_grams'];
        $this->assertCount(3, $grams);
        foreach ($grams as $g) {
            $this->assertGreaterThan(0.05, (float) $g, 'every filament of the three prints something: '.json_encode($grams));
        }
        $dir = (string) env('PAINT_SAMPLES', '');
        if ($dir !== '' && is_dir($dir)) {
            file_put_contents($dir.'/cube-stroke-orca.json', json_encode(['order_s' => $seconds, 'grams' => $grams, 'changes' => $order->slice_result['changes'] ?? null,
                'minutes' => $order->est_minutes, 'grams_total' => $order->est_grams, 'price' => $order->price_total, 'timings' => $order->timings], JSON_PRETTY_PRINT));
            copy(Storage::disk('farm')->path($order->dir().'/plate.3mf'), $dir.'/cube-stroke-plate.3mf');
            copy(Storage::disk('farm')->path($order->gcode_path), $dir.'/cube-stroke.gcode');
        }

        // the same cube in two colours one above the other (by height alone): the cheap kind of colour, one change
        $r = $this->paint($src['uuid'], ['triangles' => 12, 'palette' => ['#f4f4f2', '#d1232a'], 'heights' => [['z' => 5.1, 'color' => 1]]], array_fill(0, 12, 0))->assertCreated();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $r->json('file.uuid'), 'colors' => 'painted', 'scale' => 3])->assertCreated();
        $stacked = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $stacked->status, (string) $stacked->error.' '.json_encode($stacked->timings));
        $this->assertTrue($stacked->onePlate(), json_encode($stacked->timings));
        $this->assertCount(2, (array) $stacked->slice_result['filament_grams']);
        $this->assertLessThanOrEqual(2, (int) ($stacked->slice_result['changes'] ?? 99), 'colours one above another change the filament once');
        $this->assertLessThan($order->est_minutes / 3, $stacked->est_minutes, 'and print in a fraction of the time of colours side by side');
        // and in one colour, for the comparison the page's note about colour changes rests on
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid, 'colors' => 'one', 'scale' => 3])->assertCreated();
        $one = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
        $this->assertSame(FarmOrder::STATUS_SLICED, $one->status, (string) $one->error);
        $this->assertLessThan($stacked->est_minutes, $one->est_minutes);
        if ($dir !== '' && is_dir($dir)) {
            file_put_contents($dir.'/cube-height-orca.json', json_encode(['grams' => $stacked->slice_result['filament_grams'], 'changes' => $stacked->slice_result['changes'] ?? null,
                'minutes' => $stacked->est_minutes, 'grams_total' => $stacked->est_grams, 'price' => $stacked->price_total,
                'one_colour' => ['minutes' => $one->est_minutes, 'grams_total' => $one->est_grams, 'price' => $one->price_total]], JSON_PRETTY_PRINT));
        }
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
        [$red, $blue] = $this->spools(['#D62828' => 'Červená', '#1F6FD6' => 'Modrá']);

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
        $tiles = ModelPainter::tiles(app(Palette::class));
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
