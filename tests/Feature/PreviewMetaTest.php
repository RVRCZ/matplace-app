<?php

namespace Tests\Feature;

use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ParametricGenerator;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header X-Model-Meta of a preview stays under 4 kB, whatever the model: a web server answers 502 when the headers
 * of a response do not fit its buffer (matplace.com, 10 Oct 2026: every layered picture, its guide alone is 9 kB).
 * What does not fit waits in the cache and the page fetches it.
 */
class PreviewMetaTest extends TestCase
{
    use RefreshDatabase;

    /** the header value alone: nginx fits all the headers of an answer into 4 kB, and the cookies and the rest take 1.6 kB of it */
    private const MOST = 2048;

    public function test_a_small_meta_is_the_header_and_a_heavy_one_leaves_its_biggest_notes_beside_it(): void
    {
        $small = ['bbox' => ['x' => 1, 'y' => 2, 'z' => 3], 'notes' => ['outer' => [1, 2, 3], 'warnings' => []], 'parts' => [['name' => 'body', 'tris' => [0, 12]]]];
        $this->assertSame(json_encode($small), PreviewMeta::header($small));
        $this->assertSame($small, PreviewMeta::whole(PreviewMeta::header($small)));

        $heavy = $small;
        $heavy['notes']['guide'] = array_fill(0, 5, ['part' => 'plate_1', 'svg' => str_repeat('M0 0L10 10', 180)]);
        $heavy['notes']['outline'] = array_fill(0, 400, [12.345, 67.891]);
        $heavy['parts'] = array_map(fn ($i) => ['name' => 'piece_'.$i, 'tris' => [$i * 100, $i * 100 + 99], 'bbox' => [0, 0, 0, 10, 10, 10]], range(1, 80));
        $header = PreviewMeta::header($heavy);
        $this->assertLessThanOrEqual(PreviewMeta::LIMIT, strlen($header));
        $sent = json_decode($header, true);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $sent['more']);
        // the light notes stay where the page reads them first
        $this->assertSame([[1, 2, 3], []], [$sent['notes']['outer'], $sent['notes']['warnings']]);
        $this->assertArrayNotHasKey('guide', $sent['notes']);
        // and nothing is lost: the page puts the rest back
        $this->assertEquals($heavy, PreviewMeta::whole($header));
        $rest = $this->getJson('/api/tools/preview/'.$sent['more'].'/meta')->assertOk()->json();
        $this->assertSame($heavy['notes']['guide'], $rest['notes']['guide']);
        $this->assertSame($heavy['parts'], $rest['parts']);
        $this->getJson('/api/tools/preview/'.str_repeat('x', 32).'/meta')->assertNotFound();
        $this->getJson('/api/tools/preview/..%2F..%2Fenv/meta')->assertNotFound();
        $this->assertNull(PreviewMeta::rest('preview_meta'));
    }

    public function test_the_header_of_a_layered_picture_fits_and_its_guide_comes_with_one_more_request(): void
    {
        if (! app(ArtGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $r = $this->postJson('/api/tools/art/preview', ['params' => ['artwork' => 'lib:colour/snowman', 'mode' => 'layered', 'frame' => 'round', 'width' => 160, 'colors_n' => 5], 'view' => 'use'])->assertOk();
        $header = (string) $r->headers->get('X-Model-Meta');
        $this->assertLessThanOrEqual(self::MOST, strlen($header));
        $sent = json_decode($header, true);
        $this->assertArrayNotHasKey('guide', $sent['notes']);
        $rest = $this->getJson('/api/tools/preview/'.$sent['more'].'/meta')->assertOk()->json();
        $this->assertGreaterThanOrEqual(4, count($rest['notes']['guide']));
        $this->assertNotEmpty($rest['notes']['guide'][0]['svg']);
        // what the page needs at once is in the header: the size, the pieces, the colours
        $this->assertSame('layered', $sent['notes']['mode']);
        $this->assertNotEmpty($sent['notes']['paint']);
        $whole = PreviewMeta::whole($header);
        $this->assertCount(count($rest['notes']['guide']), $whole['notes']['guide']);
        $this->assertNotEmpty($whole['parts']);
    }

    public function test_the_header_of_every_other_preview_fits_too(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $header = fn (string $kind, array $params) => (string) $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'view' => 'use', 'pieces' => true])->assertOk()->headers->get('X-Model-Meta');
        $previews = [
            'a picture in eight colours' => ['ornament', ['artwork' => 'lib:colour/gingerbread-man', 'width' => 90, 'colors_n' => 8]],
            'a papel picado portrait' => ['papel', ['artwork' => 'lib:holidays/sugar-skull', 'treatment' => 'portrait']],
            'a medallion with its chain' => ['medallion', ['artwork' => 'lib:colour/smiling-star']],
            'a drawer full of bins' => ['modular', ['inner_w' => 300, 'inner_d' => 200, 'cols' => 6, 'rows' => 4, 'bins' => array_map(fn ($i) => ['x' => $i % 6, 'y' => intdiv($i, 6), 'w' => 1, 'h' => 1, 'color' => '#'.str_pad(dechex(0x101010 * ($i % 14 + 1)), 6, '0', STR_PAD_LEFT)], range(0, 23))]],
        ];
        foreach ($previews as $what => [$kind, $params]) {
            $sent = $header($kind, $params);
            $this->assertLessThan(self::MOST, strlen($sent), $what);
            $whole = PreviewMeta::whole($sent);
            $this->assertNotEmpty($whole['parts'], $what);
            $this->assertArrayNotHasKey('more', $whole, $what);
        }
        // the bins of a full drawer are many regions: they come back whole, whichever way they travelled
        $drawer = PreviewMeta::whole($header(...$previews['a drawer full of bins']));
        $this->assertCount(24, $drawer['notes']['regions']);
    }
}
