<?php

namespace Tests\Feature;

use App\Domain\Tools\ArtGenerator;
use App\Engines\Project\ColorChange;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Filament art: a picture as a layered picture of plates in a frame (every colour a plate, spacers, a guide back to
 * front) or as one print with the colours as steps (filament changes by height). The page, the preview, the design.
 */
class FilamentArtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ArtGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    private function meta($response): array
    {
        return json_decode((string) $response->headers->get('X-Model-Meta'), true);
    }

    private function preview(array $params, string $view = 'use')
    {
        return $this->postJson('/api/tools/art/preview', ['params' => $params, 'view' => $view]);
    }

    public function test_the_page_renders_in_three_languages_and_is_in_the_catalogue(): void
    {
        foreach (['cs' => '/tools/filament-art', 'en' => '/en/tools/filament-art', 'es' => '/es/tools/filament-art'] as $lang => $path) {
            app()->setLocale($lang);
            $page = $this->get($path)->assertOk()->assertSee(__('tools.filament_art.title'))->assertSee(__('edit.art.lead'))->assertSee('data-module="art"', false)->assertSee('window.MP_ART', false);
            $this->assertDoesNotMatchRegularExpression('/>\s*(edit|tools|toolpage|param)\.[a-z_.]+\s*</', $page->getContent(), $lang);
        }
        app()->setLocale('cs');
        $this->get('/tools')->assertOk()->assertSee(route('tools.filament_art'));
        $this->assertNotNull(ArtGenerator::SAMPLE);
    }

    public function test_a_layered_picture_is_one_plate_per_colour_a_frame_and_a_guide(): void
    {
        $r = $this->preview(['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'round', 'width' => 160, 'colors_n' => 5, 'plate' => 2, 'gap' => 3])->assertOk();
        $this->assertSame('model/stl', $r->headers->get('Content-Type'));
        $m = $this->meta($r);
        $n = $m['notes'];
        $this->assertSame('layered', $n['mode']);
        $parts = array_column($m['parts'], 'name');
        $this->assertSame('frame', $parts[0]);
        $plates = array_values(array_filter($parts, fn ($p) => str_starts_with($p, 'plate_')));
        $this->assertGreaterThanOrEqual(3, count($plates));
        $this->assertSame(count($plates), $n['plates']);
        // the plates stack back to front: each one a step deeper (plate + gap), the picture's outer size is the frame's window
        $guide = $n['guide'];
        $this->assertCount(count($plates) + 1, $guide);                                      // the plates, and the back plate a cut-out motif stands on
        $this->assertSame('body', $guide[0]['part']);
        $this->assertContains('body', $parts);
        foreach ($guide as $i => $g) {
            $this->assertEqualsWithDelta($i * 5.0, $g['z'], 0.01, 'plate '.$i);
            $this->assertNotEmpty($g['svg']);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $g['hex']);
        }
        $this->assertEquals([160, 160], array_slice($n['outer'], 0, 2));
        $this->assertEquals([180, 180], $n['frame_outer']);                                 // 160 + 2 × 10 mm of frame
        // the back plate carries the whole silhouette: it is the biggest; the front one is the smallest
        $areas = array_column($n['colors'], 'area_mm2');
        $this->assertGreaterThan(end($areas), $areas[0]);
        // every piece is painted in a filament of the palette; the frame the darkest one
        $this->assertCount(count($parts), $n['paint']);
        $this->assertArrayHasKey('frame', $n['paint']);
        $this->assertSame([], $n['color_changes']);
        // the triangle ranges follow each other and cover the file
        $stl = file_get_contents($r->baseResponse->getFile()->getPathname());
        $triangles = unpack('V', substr($stl, 80, 4))[1];
        $this->assertSame($m['triangles'], $triangles);
        $at = 0;
        foreach ($m['parts'] as $part) {
            $this->assertSame($at, $part['tris'][0]);
            $at = $part['tris'][1];
        }
        $this->assertSame($triangles, $at);

        // laid out for printing: the pieces side by side on the bed, nothing stacked, the frame rim up
        $print = $this->meta($this->preview(['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'round', 'width' => 160, 'colors_n' => 5], 'print')->assertOk());
        $this->assertEqualsWithDelta($print['notes']['frame_depth'], $print['bbox']['z'], 0.1);   // the frame is the tallest piece: its board, the plates and a hair of room
        $this->assertEqualsWithDelta(2 + 22 + 1, $print['notes']['frame_depth'], 0.1);           // 5 plates of 2 mm with 3 mm gaps = 22 mm of depth
        $this->assertGreaterThan($m['bbox']['y'] + 100, $print['bbox']['y']);
        foreach ($print['notes']['each'] as $size) {
            $this->assertLessThanOrEqual(240, max($size[0], $size[1]));
        }
    }

    public function test_one_print_stacks_the_colours_and_changes_filament_by_height(): void
    {
        $m = $this->meta($this->preview(['artwork' => 'lib:colour/gingerbread-man', 'mode' => 'stack', 'shape' => 'rect', 'width' => 120, 'height' => 140, 'margin' => 6, 'colors_n' => 4, 'base' => 1.2, 'step' => 0.4])->assertOk());
        $n = $m['notes'];
        $this->assertSame('stack', $n['mode']);
        $this->assertEquals([120, 140], [$m['bbox']['x'], $m['bbox']['y']]);             // the plate is as big as asked
        $parts = array_column($m['parts'], 'name');
        $this->assertSame('body', $parts[0]);
        $colors = array_values(array_filter($parts, fn ($p) => str_starts_with($p, 'color_')));
        $this->assertCount(3, $colors);
        $this->assertEqualsWithDelta(1.2 + 3 * 0.4, $m['bbox']['z'], 0.01);
        // a change of filament at the top of the base and at every step above it, bottom to top
        $zs = array_column($n['color_changes'], 'z');
        $this->assertSame($zs, array_values(array_unique($zs)));
        $this->assertSame($zs, (function (array $a) { sort($a); return $a; })($zs));
        $this->assertLessThanOrEqual(3, count($zs));
        $this->assertGreaterThanOrEqual(1, count($zs));
        foreach ($n['color_changes'] as $c) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $c['hex']);
            $this->assertEqualsWithDelta(0, fmod(round($c['z'] / 0.2, 3), 1), 1e-6, 'a change ends on a 0.2 mm layer');
        }
        $this->assertFalse($n['multi_material']);
    }

    public function test_the_visitor_can_give_a_plate_another_spool_and_merge_colours(): void
    {
        $base = ['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'none', 'width' => 150, 'colors_n' => 5];
        $plain = $this->meta($this->preview($base)->assertOk())['notes'];
        $first = $plain['colors'][0];
        $other = collect(app(\App\Domain\Farm\Palette::class)->all())->first(fn ($c) => $c['code'] !== $first['code'])['code'];
        $own = $this->meta($this->preview($base + ['part_colors' => [$first['part'] => ['code' => $other]]])->assertOk())['notes'];
        $this->assertSame($other, $own['colors'][0]['code']);
        $this->assertSame($own['colors'][0]['hex'], $own['paint'][$first['part']]);
        // two colours merged: one plate fewer
        $merged = $this->meta($this->preview($base + ['merge' => [[$plain['colors'][1]['index'], $plain['colors'][0]['index']]]])->assertOk())['notes'];
        $this->assertSame(count($plain['colors']) - 1, count($merged['colors']));
    }

    public function test_bad_input_is_refused_with_a_sentence(): void
    {
        $this->preview(['artwork' => 'lib:colour/snowman', 'width' => 9999])->assertStatus(422);
        $this->preview(['width' => 100])->assertStatus(422);
        $this->preview(['artwork' => 'lib:nothing/here', 'width' => 100])->assertStatus(422)->assertJsonPath('errors.params.0', __('param.error.artwork_gone'));
        $this->preview(['artwork' => 'lib:colour/snowman', 'mode' => 'sideways'])->assertStatus(422);
    }

    public function test_a_design_is_a_model_file_with_its_plates_beside_it_and_a_project_with_colour_changes(): void
    {
        Storage::fake('models');
        $r = $this->postJson('/api/tools/art', ['params' => ['artwork' => 'lib:colour/gingerbread-man', 'mode' => 'stack', 'shape' => 'rect', 'width' => 100, 'height' => 110, 'colors_n' => 3]])->assertCreated();
        $r->assertJsonPath('file.kind', 'filament_art')->assertJsonPath('file.status', 'ready');
        $uuid = $r->json('file.uuid');
        $parts = $r->json('file.parts');
        $this->assertContains('body', $parts);
        $this->assertContains('color_1', $parts);
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertTrue($file->builtForPrinting());                                     // the farm must not turn it
        $this->assertNotEmpty($file->colorChanges());
        $this->assertSame('file:'.$uuid, $file->tool_params['artwork']);
        $this->assertNotEmpty($file->tool_params['part_colors']['body']['code']);
        // every piece as its own file, handed out by its name
        foreach ($parts as $part) {
            $this->get('/api/tools/edit/'.$uuid.'/'.$part.'.stl')->assertOk()->assertHeader('Content-Type', 'model/stl');
        }
        $this->get('/api/tools/edit/'.$uuid.'/nothing.stl')->assertNotFound();
        // the design opens again on its page with its settings
        $this->get('/tools/filament-art?from='.$uuid)->assertOk()->assertSee($uuid, false);
        $tool = $this->getJson('/api/files/'.$uuid)->assertOk()->json('file.tool');
        $this->assertSame('filament_art', $tool['kind']);
        $this->assertSame('stack', $tool['params']['mode']);

        // a layered picture keeps its guide beside the model
        $layered = $this->postJson('/api/tools/art', ['params' => ['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'square', 'width' => 120, 'colors_n' => 4]])->assertCreated();
        $guide = $this->getJson('/api/tools/edit/'.$layered->json('file.uuid').'/guide')->assertOk()->json('guide');
        $this->assertGreaterThanOrEqual(3, count($guide));
        $this->assertNotEmpty($guide[0]['svg']);
        $this->getJson('/api/tools/edit/'.$uuid.'/guide')->assertNotFound();           // one print has no guide of plates
        $this->assertSame([], ModelFile::where('uuid', $layered->json('file.uuid'))->firstOrFail()->colorChanges());

        // the zip: every plate as its own STL
        $zip = $this->postJson('/api/tools/art/zip', ['params' => ['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'none', 'width' => 100, 'colors_n' => 3]])->assertOk();
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($zip->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $names[] = $archive->getNameIndex($i);
        }
        $archive->close();
        $this->assertContains('filament-art.stl', $names);
        $this->assertContains('filament-art-plate_1.stl', $names);
        $this->assertGreaterThanOrEqual(3, count($names));
    }

    public function test_filament_changes_land_on_whole_layers_of_the_project(): void
    {
        $this->assertSame(1.4, ColorChange::layerAbove(1.2, 0.2, 0.2));
        $this->assertSame(1.8, ColorChange::layerAbove(1.6, 0.2, 0.2));
    }
}
