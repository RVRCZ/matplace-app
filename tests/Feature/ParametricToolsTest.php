<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\Mesh\StlFile;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Organizer, box, phone stand, cable holder: real solids with the requested dimensions, server-side limits, flow to price. */
class ParametricToolsTest extends TestCase
{
    use RefreshDatabase;

    private function needsPython(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    private function meta($response): array
    {
        return json_decode((string) $response->headers->get('X-Model-Meta'), true);
    }

    public function test_pages_render_in_all_languages(): void
    {
        foreach (['organizer', 'box', 'phone-stand', 'cable-holder'] as $slug) {
            $this->get('/tools/'.$slug)->assertOk();
            $this->get('/tools/'.$slug.'?lang=en')->assertOk();
            $this->get('/tools/'.$slug.'?lang=es')->assertOk();
        }
    }

    public function test_limits_are_enforced_on_the_server(): void
    {
        $this->postJson('/api/tools/param/preview', ['kind' => 'rocket'])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'organizer', 'params' => ['width' => -5]])->assertStatus(422)->assertJsonValidationErrors('params.width');
        $this->postJson('/api/tools/param/preview', ['kind' => 'organizer', 'params' => ['width' => 5000]])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'organizer', 'params' => ['rows' => 2.5]])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['holes' => array_fill(0, 9, ['wall' => 'front', 'shape' => 'circle', 'w' => 4, 'x' => 10, 'z' => 10])]])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => ['holes' => [['wall' => 'roof', 'shape' => 'circle', 'w' => 4, 'x' => 10, 'z' => 10]]]])->assertStatus(422);
    }

    public function test_organizer_has_exact_outer_size_and_explains_impossible_grids(): void
    {
        $this->needsPython();
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'organizer', 'params' => ['width' => 180, 'depth' => 90, 'height' => 35, 'rows' => 2, 'cols' => 3, 'wall' => 1.6, 'floor' => 1.2]]);
        $r->assertOk();
        $m = $this->meta($r);
        $this->assertEqualsWithDelta(180, $m['bbox']['x'], 0.01);
        $this->assertEqualsWithDelta(90, $m['bbox']['y'], 0.01);
        $this->assertEqualsWithDelta(35, $m['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta((180 - 4 * 1.6) / 3, $m['notes']['cell'][0], 0.06);
        // far less plastic than a solid block, more than the floor alone
        $this->assertLessThan(180 * 90 * 35 * 0.35, $m['volume_mm3']);
        $this->assertGreaterThan(180 * 90 * 1.2, $m['volume_mm3']);

        $bad = $this->postJson('/api/tools/param/preview', ['kind' => 'organizer', 'params' => ['width' => 40, 'depth' => 40, 'height' => 20, 'rows' => 8, 'cols' => 8]]);
        $bad->assertStatus(422)->assertJsonValidationErrors('params');
        $this->assertStringContainsString('3.2', $bad->json('errors.params.0'));   // says how small the cells would be
    }

    public function test_box_outer_size_lid_parts_and_openings(): void
    {
        $this->needsPython();
        $p = ['inner_w' => 80, 'inner_d' => 50, 'inner_h' => 30, 'wall' => 2, 'floor' => 1.6, 'lid' => true, 'clearance' => 0.25];
        $body = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => $p, 'part' => 'body'])->assertOk());
        $this->assertEqualsWithDelta(84, $body['bbox']['x'], 0.01);              // inner + 2 × wall
        $this->assertEqualsWithDelta(54, $body['bbox']['y'], 0.01);
        $this->assertEqualsWithDelta(31.6, $body['bbox']['z'], 0.01);            // inner + floor
        $this->assertEqualsWithDelta(33.2, $body['notes']['outer'][2], 0.01);             // with the lid plate on top

        $lid = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => $p, 'part' => 'lid'])->assertOk());
        $this->assertEqualsWithDelta(84, $lid['bbox']['x'], 0.01);
        $this->assertEqualsWithDelta(1.6 + 6, $lid['bbox']['z'], 0.01);          // plate + lip

        $plain = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => $p, 'part' => 'body'])->assertOk())['volume_mm3'];
        $holed = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'box', 'part' => 'body', 'params' => $p + ['holes' => [['wall' => 'front', 'shape' => 'circle', 'w' => 10, 'x' => 40, 'z' => 10]]]])->assertOk())['volume_mm3'];
        $this->assertEqualsWithDelta(M_PI * 25 * 2, $plain - $holed, 1.5);        // exactly one 10 mm disc of a 2 mm wall is gone

        // overlapping openings, an opening in the lid lip zone, an opening outside the wall
        $two = [['wall' => 'front', 'shape' => 'circle', 'w' => 10, 'x' => 40, 'z' => 10], ['wall' => 'front', 'shape' => 'rect', 'w' => 10, 'h' => 6, 'x' => 45, 'z' => 10]];
        $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => $p + ['holes' => $two]])->assertStatus(422)->assertJsonValidationErrors('params');
        $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => $p + ['holes' => [['wall' => 'left', 'shape' => 'circle', 'w' => 8, 'x' => 25, 'z' => 27]]]])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'box', 'params' => $p + ['holes' => [['wall' => 'right', 'shape' => 'circle', 'w' => 8, 'x' => 49, 'z' => 10]]]])->assertStatus(422);
    }

    public function test_created_model_flows_into_price_and_offers_separate_parts(): void
    {
        $this->needsPython();
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);

        $r = $this->postJson('/api/tools/param', ['kind' => 'box', 'params' => ['inner_w' => 60, 'inner_d' => 40, 'inner_h' => 25, 'lid' => true]]);
        $r->assertCreated()->assertJsonPath('file.kind', 'box')->assertJsonPath('file.parts', ['body', 'lid'])->assertJsonPath('file.hints.supports', false);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $this->assertSame(60, $file->tool_params['inner_w']);
        $this->assertNotContains('multiple_shells', $r->json('file.issues') ?? []);       // two bodies by design
        $this->assertEqualsWithDelta(64 + 8 + 64, $file->bbox['x'], 0.05);                // box + gap + lid on one plate

        $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PETG', 'quantity' => 2])->assertCreated()->assertJsonPath('calculation.status', 'done');

        $lid = $this->get('/api/tools/param/'.$file->uuid.'/lid.stl')->assertOk();
        $stats = StlFile::stats($lid->baseResponse->getFile()->getPathname());
        $this->assertEqualsWithDelta(64, $stats->bbox->x, 0.05);
        $this->get('/api/tools/param/'.$file->uuid.'/roof.stl')->assertNotFound();
    }

    public function test_modular_bins_fill_the_drawer_exactly_with_rounded_corners_colours_and_a_bill_of_parts(): void
    {
        $this->needsPython();
        $bins = [
            ['x' => 0, 'y' => 1, 'w' => 4, 'h' => 1, 'color' => 'blue'], ['x' => 4, 'y' => 0, 'w' => 2, 'h' => 2, 'color' => 'orange'],
            ['x' => 0, 'y' => 0, 'w' => 2, 'h' => 1, 'color' => 'white'], ['x' => 2, 'y' => 0, 'w' => 2, 'h' => 1, 'color' => 'white'],
        ];
        $p = ['inner_w' => 300, 'inner_d' => 120, 'height' => 40, 'cols' => 6, 'rows' => 2, 'radius' => 8, 'gap' => 0.6, 'wall' => 1.6, 'floor' => 1.2, 'bins' => $bins];
        $set = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => $p, 'view' => 'use'])->assertOk());
        $this->assertSame([50.0, 60.0], array_map('floatval', $set['notes']['unit']));
        $this->assertEqualsWithDelta(300 - 0.6, $set['bbox']['x'], 0.01);                 // the set fills the drawer, less half a gap on each side
        $this->assertEqualsWithDelta(120 - 0.6, $set['bbox']['y'], 0.01);
        $this->assertSame(0, $set['notes']['free_cells']);
        $this->assertCount(4, $set['notes']['regions']);                                   // one colour region per bin, for the preview
        $bom = collect($set['notes']['bom'])->keyBy(fn ($b) => $b['size'].$b['color']);
        $this->assertSame(2, $bom['2x1white']['count']);                                   // identical bins are one file printed twice
        $this->assertEqualsWithDelta(99.4, $bom['2x1white']['w_mm'], 0.01);

        // rounded corners really remove material, inside and out, and keep the wall constant
        $round = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => $p, 'part' => 'bin_2x1'])->assertOk());
        $square = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => ['radius' => 0] + $p, 'part' => 'bin_2x1'])->assertOk());
        $this->assertEqualsWithDelta(99.4, $round['bbox']['x'], 0.01);
        $this->assertEqualsWithDelta(59.4, $round['bbox']['y'], 0.01);
        $this->assertLessThan($square['volume_mm3'], $round['volume_mm3']);
        $this->assertGreaterThan($square['volume_mm3'] * 0.9, $round['volume_mm3']);

        // tray: bigger than the inside, its own part, and the print layout keeps the bins beside it
        $tray = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => ['tray' => true] + $p, 'part' => 'tray'])->assertOk());
        $this->assertEqualsWithDelta(300 + 4 + 0.6, $tray['bbox']['x'], 0.01);
        $plate = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => ['tray' => true] + $p])->assertOk());
        $this->assertGreaterThan(600, $plate['bbox']['x']);

        // impossible layouts are refused with a reason
        $over = $p;
        $over['bins'][] = ['x' => 1, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => 'red'];
        $this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => $over])->assertStatus(422)->assertJsonValidationErrors('params');
        $out = $p;
        $out['bins'] = [['x' => 5, 'y' => 0, 'w' => 2, 'h' => 1]];
        $this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => $out])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => ['cols' => 12, 'inner_w' => 100] + $p])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => ['bins' => []] + $p])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'modular', 'params' => ['bins' => [['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'color' => 'pink']]] + $p])->assertStatus(422);

        // created set: parts per bin size, stored layout, price
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);
        $r = $this->postJson('/api/tools/param', ['kind' => 'modular', 'params' => $p])->assertCreated();
        $r->assertJsonPath('file.kind', 'modular')->assertJsonPath('file.parts', ['bin_4x1', 'bin_2x2', 'bin_2x1']);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame('orange', $file->tool_params['bins'][1]['color']);
        $this->assertNotContains('multiple_shells', $r->json('file.issues') ?? []);
        $this->get('/api/tools/param/'.$file->uuid.'/bin_2x2.stl')->assertOk();
        $this->getJson('/api/tools/param/'.$file->uuid.'/bin_9x9.stl')->assertNotFound();    // no such bin in this layout
        $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PLA'])->assertCreated()->assertJsonPath('calculation.status', 'done');
        foreach (['cs', 'en', 'es'] as $lang) {
            $this->get('/tools/modular-organizer?lang='.$lang)->assertOk();
        }
    }

    public function test_stand_and_cable_holder_follow_their_numbers(): void
    {
        $this->needsPython();
        $a = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => ['width' => 70, 'device' => 12]])->assertOk());
        $this->assertEqualsWithDelta(70, $a['bbox']['z'], 0.01);                           // print orientation: lying on its side
        $use = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'phone_stand', 'params' => ['width' => 70], 'view' => 'use'])->assertOk());
        $this->assertEqualsWithDelta(70, $use['bbox']['y'], 0.01);                         // preview: standing on the desk
        $this->assertEqualsWithDelta($a['volume_mm3'], $use['volume_mm3'], 0.5);

        $c3 = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'cable_holder', 'params' => ['count' => 3, 'cable' => 6]])->assertOk());
        $c5 = $this->meta($this->postJson('/api/tools/param/preview', ['kind' => 'cable_holder', 'params' => ['count' => 5, 'cable' => 6]])->assertOk());
        $this->assertEqualsWithDelta(2 * (6.6 + 3), $c5['bbox']['x'] - $c3['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(6.6, $c3['notes']['slot'], 0.01);
    }
}
