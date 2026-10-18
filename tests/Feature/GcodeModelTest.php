<?php

namespace Tests\Feature;

use App\Domain\Tools\ModelEditor;
use App\Engines\Contracts\MeshRepair;
use App\Engines\Mesh\StlFile;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GcodeFixtures;
use Tests\TestCase;

/**
 * A model out of a G-code file (docs/AL.md): the walls of every layer become a watertight solid, holes stay holes,
 * supports and the skirt leave nothing, colours side by side become a part per colour (and a 3MF of them), colours
 * by height one body with a spool change, the headers of four slicers are read, a file that is not G-code is refused.
 */
class GcodeModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        if (! app(ModelEditor::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
    }

    /** The G-code uploaded to the tool; the queue is synchronous, so the answer is the finished model. */
    private function made(string $gcode, string $name = 'thing.gcode'): array
    {
        $path = sys_get_temp_dir().'/mp_gcode_'.uniqid().'.gcode';
        file_put_contents($path, $gcode);
        $uuid = $this->postJson('/api/tools/gcode', ['file' => new UploadedFile($path, $name, 'text/plain', null, true)])->assertCreated()->json('file.uuid');
        @unlink($path);

        return $this->getJson('/api/files/'.$uuid)->assertOk()->json('file');
    }

    private function stlOf(array $file): string
    {
        return Storage::disk('models')->path((string) ModelFile::where('uuid', $file['uuid'])->firstOrFail()->stl_path);
    }

    public function test_a_cube_with_walls_infill_skirt_and_a_support_becomes_a_watertight_cube(): void
    {
        $file = $this->made(GcodeFixtures::make('cube'));
        $this->assertSame('ready', $file['status'], (string) $file['error']);
        $this->assertSame('gcode', $file['kind']);
        $this->assertEqualsWithDelta(8000, $file['volume_mm3'], 400);                       // 20 × 20 × 20 within 5 %
        $this->assertEqualsWithDelta(20, $file['bbox']['x'], 0.05);
        $this->assertEqualsWithDelta(20, $file['bbox']['y'], 0.05);
        $this->assertEqualsWithDelta(20, $file['bbox']['z'], 0.05);                          // nothing of the skirt or the support block beside it
        $this->assertTrue(app(MeshRepair::class)->check($this->stlOf($file))->watertight);
        $this->assertSame(['body'], $file['parts']);
        $r = $file['edit'];
        $this->assertSame('one', $r['colors_mode']);
        $this->assertSame('OrcaSlicer 2.3.0', $r['slicer']);
        $this->assertSame('Anycubic Kobra S1', $r['printer']);
        $this->assertSame('0.20mm Standard', $r['profile']);
        $this->assertSame(3723, $r['time_s']);
        $this->assertEqualsWithDelta(9.87, $r['filament_g'], 0.001);
        $this->assertEqualsWithDelta(1.23, $r['filament_m'], 0.01);
        $this->assertSame(100, $r['layers']);
        $this->assertEqualsWithDelta(0.2, $r['layer_height'], 0.001);
        $this->assertSame('#FF8000', $r['colors'][0]['hex']);
        $this->assertSame([], $r['warnings']);
        $this->assertNotNull($file['tool']);                                                 // the page reopens it as a result
        $this->assertStringContainsString('/tools/gcode-model?from=', $file['tool']['url']);
    }

    public function test_a_hole_stays_a_hole(): void
    {
        $file = $this->made(GcodeFixtures::make('hole'));
        $this->assertSame('ready', $file['status'], (string) $file['error']);
        $this->assertEqualsWithDelta(8000 - 8 * 8 * 20, $file['volume_mm3'], 340);
        $this->assertTrue(app(MeshRepair::class)->check($this->stlOf($file))->watertight);
    }

    public function test_two_colours_side_by_side_become_a_part_per_colour_and_a_3mf_of_them(): void
    {
        $file = $this->made(GcodeFixtures::make('inlay', 'bambu'));
        $this->assertSame('ready', $file['status'], (string) $file['error']);
        $this->assertSame(['color_1', 'color_2'], $file['parts']);
        $r = $file['edit'];
        $this->assertSame('parts', $r['colors_mode']);
        $this->assertSame(['#000000', '#FFFFFF'], array_column($r['colors'], 'hex'));
        $this->assertSame(['color_1', 'color_2'], array_column($r['colors'], 'part'));
        $this->assertEqualsWithDelta(8000, $file['volume_mm3'], 400);
        $model = ModelFile::where('uuid', $file['uuid'])->firstOrFail();
        $this->assertSame(['code' => '#FFFFFF', 'hex' => '#FFFFFF'], $model->tool_params['part_colors']['color_2']);
        $this->assertSame([], $model->colorChanges());                                        // side by side: no change by height
        // the letter alone: 6 × 6 mm through the upper half
        $letter = Storage::disk('models')->path('files/'.$file['uuid'].'/parts/color_2.stl');
        $this->assertFileExists($letter);
        $this->assertEqualsWithDelta(6 * 6 * 10, StlFile::stats($letter)->volumeMm3, 25);
        $this->get('/api/tools/edit/'.$file['uuid'].'/color_2.stl')->assertOk();
        // the plain 3MF with an object per colour, for the visitor's own slicer
        $zip = new \ZipArchive;
        $res = $this->get('/api/tools/edit/'.$file['uuid'].'/colors.3mf')->assertOk();
        $this->assertTrue($zip->open($res->baseResponse->getFile()->getPathname()) === true);
        $xml = (string) $zip->getFromName('3D/3dmodel.model');
        $this->assertSame(2, substr_count($xml, '<object '));
        $this->assertStringContainsString('displaycolor="#FFFFFFFF"', $xml);
        $this->assertStringContainsString('name="color_1"', $xml);
        $zip->close();
    }

    public function test_two_colours_by_height_become_one_body_with_a_spool_change(): void
    {
        $file = $this->made(GcodeFixtures::make('height', 'anycubic'));
        $this->assertSame('ready', $file['status'], (string) $file['error']);
        $this->assertSame(['body'], $file['parts']);
        $r = $file['edit'];
        $this->assertSame('height', $r['colors_mode']);
        $this->assertEqualsWithDelta(8000, $file['volume_mm3'], 400);
        $model = ModelFile::where('uuid', $file['uuid'])->firstOrFail();
        $changes = $model->colorChanges();
        $this->assertCount(1, $changes);
        $this->assertEqualsWithDelta(10.0, $changes[0]['z'], 0.01);
        $this->assertSame('#0047BB', $changes[0]['hex']);                                      // the second tool's filament of the Anycubic header
        $this->assertSame(['#212721', '#0047BB'], array_column($r['colors'], 'hex'));
        $this->get('/api/tools/edit/'.$file['uuid'].'/colors.3mf')->assertNotFound();           // one body: no 3MF by colours
    }

    public function test_the_headers_of_the_slicers_are_read(): void
    {
        $prusa = $this->made(GcodeFixtures::make('cube', 'prusa', 10))['edit'];
        $this->assertSame(['prusa', 'PrusaSlicer 2.8.1', 'MK4S', 2710, 4.47, '#FF8000'], [$prusa['flavour'], $prusa['slicer'], $prusa['printer'], $prusa['time_s'], $prusa['filament_g'], $prusa['colors'][0]['hex']]);
        $this->assertEqualsWithDelta(0.45, $prusa['line_width'], 0.001);                       // external_perimeter_extrusion_width
        $bambu = $this->made(GcodeFixtures::make('cube', 'bambu', 10))['edit'];
        $this->assertSame(['bambu', 'BambuStudio 01.09.00.70', 'Bambu Lab X1 Carbon', 6002, 7.5], [$bambu['flavour'], $bambu['slicer'], $bambu['printer'], $bambu['time_s'], $bambu['filament_g']]);   // the total estimated time
        $any = $this->made(GcodeFixtures::make('cube', 'anycubic', 10))['edit'];
        $this->assertSame(['anycubic', 'AnycubicSlicerNext 1.3.7.1', 'Anycubic Kobra 3 Max', 5846, 25.91, '#212721', 20.23], [$any['flavour'], $any['slicer'], $any['printer'], $any['time_s'], $any['filament_g'], $any['colors'][0]['hex'], $any['colors'][0]['grams']]);
    }

    public function test_a_file_that_is_not_gcode_is_refused_with_a_reason_the_page_can_say(): void
    {
        $file = $this->made("solid foo\nfacet normal 0 0 1\nendsolid\n", 'foo.gcode');
        $this->assertSame('failed', $file['status']);
        $this->assertSame('not_gcode', $file['error']);
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertNotSame('edit.gcode.error.not_gcode', __('edit.gcode.error.not_gcode', [], $locale));
            $this->assertNotSame('edit.gcode.error.no_walls', __('edit.gcode.error.no_walls', [], $locale));
        }
        // a print with travels but no walls (a support-only file) says so too
        $walls = GcodeFixtures::make('cube', 'orca', 3);
        $noWalls = $this->made(str_replace(';TYPE:Outer wall', ';TYPE:Support', $walls), 'support.gcode');
        $this->assertSame(['failed', 'no_walls'], [$noWalls['status'], $noWalls['error']]);
        // the wrong kind of file does not even start
        $path = sys_get_temp_dir().'/mp_gcode_'.uniqid().'.stl';
        file_put_contents($path, 'solid x');
        $this->postJson('/api/tools/gcode', ['file' => new UploadedFile($path, 'x.stl', 'model/stl', null, true)])->assertStatus(422);
        @unlink($path);
    }

    public function test_the_page_takes_gcode_alone_and_is_in_the_catalogue(): void
    {
        foreach (['cs' => '/tools/gcode-model', 'en' => '/en/tools/gcode-model', 'es' => '/es/tools/gcode-model'] as $lang => $path) {
            app()->setLocale($lang);
            $page = $this->get($path)->assertOk()->assertSee(__('tools.gcode.title'))->assertSee(__('edit.gcode.lead'))->assertSee('data-module="edit"', false)->assertSee('accept=".gcode"', false)
                ->assertSee('\/api\/tools\/gcode', false)->assertSee('GCODE · ', false)->assertSee(__('edit.gcode.settings'));
            $this->assertDoesNotMatchRegularExpression('/>\s*(edit|tools|toolpage|param)\.[a-z_.]+\s*</', $page->getContent(), $lang);
        }
        app()->setLocale('cs');
        $this->get('/tools')->assertOk()->assertSee(route('tools.gcode'));
        $this->assertStringContainsString('150 MB', (string) $this->get('/tools/gcode-model')->getContent());
    }
}
