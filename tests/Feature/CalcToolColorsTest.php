<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The calculation of a design made here keeps the colours chosen on its tool page (Roman, 29 Oct 2026, docs/AN.md §20).
 * One colour paints the whole model in the browser (painted.ts toolPaint); parts in different colours that lie in one
 * body (a medal's motif flush in it) come once more from the server, their triangles grouped by piece.
 */
final class CalcToolColorsTest extends TestCase
{
    use RefreshDatabase;

    private function python(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    public function test_a_medal_in_two_colours_hands_out_its_pieces_once_and_keeps_them_beside_the_file(): void
    {
        $this->python();
        Storage::fake('models');
        $made = $this->postJson('/api/tools/param', ['kind' => 'medallion', 'params' => ['line1' => '1', 'part_colors' => ['body' => '#d4a531', 'color_1' => '#d1232a']]])->assertCreated();
        $url = $made->json('file.tool.pieces_url');
        $this->assertNotNull($url, 'two colours in one body: the calculation asks for the pieces');
        $file = ModelFile::where('uuid', $made->json('file.uuid'))->firstOrFail();
        $this->assertStringEndsWith('/api/tools/param/'.$file->uuid.'/pieces', $url);

        $res = $this->get('/api/tools/param/'.$file->uuid.'/pieces')->assertOk()->assertHeader('Content-Type', 'model/stl');
        $pieces = json_decode((string) $res->headers->get('X-Pieces'), true);
        $this->assertSame(['body', 'color_1'], array_column($pieces, 'name'));
        // the ranges run one after another over every triangle of the STL handed out with them
        $stl = file_get_contents($res->baseResponse->getFile()->getPathname());
        $count = unpack('V', substr($stl, 80, 4))[1];
        $this->assertSame(0, $pieces[0]['tris'][0]);
        $this->assertSame($pieces[0]['tris'][1], $pieces[1]['tris'][0]);
        $this->assertSame($count, $pieces[1]['tris'][1]);
        Storage::disk('models')->assertExists([$file->dir().'/pieces.stl', $file->dir().'/pieces.json']);

        // built once: the second answer is the kept file (a changed list on disk is what comes back)
        Storage::disk('models')->put($file->dir().'/pieces.json', '[{"name":"kept","tris":[0,1]}]');
        $this->assertSame('[{"name":"kept","tris":[0,1]}]', $this->get('/api/tools/param/'.$file->uuid.'/pieces')->assertOk()->headers->get('X-Pieces'));
    }

    public function test_a_design_in_one_colour_or_without_colours_needs_no_pieces_and_an_upload_has_none(): void
    {
        $this->python();
        Storage::fake('models');
        // the copper box with its copper lid: the browser paints the whole model in that one colour
        $box = $this->postJson('/api/tools/param', ['kind' => 'box', 'params' => ['lid' => true, 'part_colors' => ['body' => '#b87333', 'lid' => '#b87333']]])->assertCreated();
        $this->assertNull($box->json('file.tool.pieces_url'));
        $this->assertSame('#b87333', $box->json('file.tool.params.part_colors.lid.hex'));
        // a box and a lid in two colours: the pieces are there for it too
        $two = $this->postJson('/api/tools/param', ['kind' => 'box', 'params' => ['lid' => true, 'part_colors' => ['body' => '#b87333', 'lid' => '#1b1b1d']]])->assertCreated();
        $this->assertNotNull($two->json('file.tool.pieces_url'));

        $upload = ModelFile::create(['uuid' => (string) Str::uuid(), 'original_name' => 'cube.stl', 'ext' => 'stl', 'size_bytes' => 84, 'sha256' => str_repeat('0', 64), 'storage_path' => 'files/x/original.stl', 'status' => ModelFile::STATUS_READY]);
        $this->get('/api/tools/param/'.$upload->uuid.'/pieces')->assertNotFound();
    }
}
