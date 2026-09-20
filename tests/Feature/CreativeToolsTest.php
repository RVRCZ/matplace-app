<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Vase / pot, logo to 3D, stamp, QR sign: shared text + SVG + picture input, honest errors, parts, reopening a design. */
class CreativeToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
    }

    private function meta($response): array
    {
        return json_decode((string) $response->headers->get('X-Model-Meta'), true);
    }

    private function preview(string $kind, array $params, string $part = 'all')
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params, 'part' => $part]);
    }

    private function svg(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">'.$body.'</svg>');
    }

    public function test_pages_render(): void
    {
        foreach (['vase', 'logo', 'stamp', 'qr'] as $slug) {
            foreach (['cs', 'en', 'es'] as $lang) {
                $this->get('/tools/'.$slug.'?lang='.$lang)->assertOk();
            }
        }
    }

    public function test_vase_follows_its_dimensions_and_a_pot_gets_drainage_and_a_saucer(): void
    {
        $v = $this->meta($this->preview('vase', ['height' => 150, 'top_d' => 80, 'bottom_d' => 80, 'profile' => 'cone', 'style' => 'smooth', 'purpose' => 'vase'])->assertOk());
        $this->assertEqualsWithDelta(150, $v['bbox']['z'], 0.01);
        $this->assertEqualsWithDelta(80, $v['bbox']['x'], 0.3);
        // a hollow cylinder: wall + floor, nowhere near a solid one
        $solid = M_PI * 40 * 40 * 150;
        $this->assertLessThan($solid * 0.12, $v['volume_mm3']);
        $this->assertGreaterThan(M_PI * 40 * 40 * 1.6, $v['volume_mm3']);

        $twist = $this->meta($this->preview('vase', ['style' => 'twist', 'profile' => 'belly', 'purpose' => 'vase'])->assertOk());
        $this->assertGreaterThan(90, $twist['bbox']['x']);                        // the belly is wider than the rim

        $pot = $this->meta($this->preview('vase', ['purpose' => 'pot', 'bottom_d' => 80, 'drainage' => true, 'saucer' => true])->assertOk());
        $this->assertSame(5, $pot['notes']['drainage_holes']);
        $saucer = $this->meta($this->preview('vase', ['purpose' => 'pot', 'bottom_d' => 80, 'saucer' => true], 'saucer')->assertOk());
        $this->assertEqualsWithDelta($pot['notes']['saucer_d'], $saucer['bbox']['x'], 0.5);
        $this->assertGreaterThan(80 + 15, $saucer['bbox']['x']);                   // the pot fits in with room to spare

        $this->preview('vase', ['profile' => 'pyramid'])->assertStatus(422);
    }

    public function test_logo_from_text_svg_and_picture_with_honest_refusals(): void
    {
        $text = $this->meta($this->preview('logo', ['line1' => 'Žluťoučký', 'line2' => 'kůň', 'width' => 90, 'mode' => 'relief', 'margin' => 5, 'plate' => 2, 'thickness' => 2])->assertOk());
        $this->assertEqualsWithDelta(100, $text['bbox']['x'], 0.1);                // motif 90 + 2 × margin
        $this->assertEqualsWithDelta(4, $text['bbox']['z'], 0.05);
        $this->assertSame([], $text['notes']['missing_chars']);                   // Czech diacritics are in the font

        $cut = $this->meta($this->preview('logo', ['line1' => 'AB', 'mode' => 'cutout', 'width' => 60, 'thickness' => 3])->assertOk());
        $this->assertEqualsWithDelta(60, $cut['bbox']['x'], 0.1);
        $this->assertSame(2, $cut['notes']['pieces']);
        $this->assertContains('separate_pieces', $cut['notes']['warnings']);

        // SVG: fills are used, bare outlines are reported
        $id = $this->post('/api/tools/artwork', ['file' => $this->svg('<rect x="10" y="10" width="80" height="30" fill="#000"/><path d="M0 0 L100 0" stroke="#000" fill="none"/>')], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
        $svg = $this->meta($this->preview('logo', ['artwork' => $id, 'width' => 80, 'margin' => 0, 'shape' => 'rect'])->assertOk());
        $this->assertEqualsWithDelta(80, $svg['bbox']['x'], 0.1);
        $this->assertEqualsWithDelta(30, $svg['bbox']['y'], 0.1);                  // proportions of the drawing are kept
        $this->assertContains('outlines_ignored', $svg['notes']['warnings']);

        $outline = $this->post('/api/tools/artwork', ['file' => $this->svg('<path d="M0 0 L100 50" stroke="#000" fill="none"/>')], ['Accept' => 'application/json'])->json('artwork');
        $r = $this->preview('logo', ['artwork' => $outline])->assertStatus(422);
        $this->assertSame(__('param.error.svg_outlines_only'), $r->json('errors.params.0'));

        $bomb = $this->post('/api/tools/artwork', ['file' => UploadedFile::fake()->createWithContent('x.svg', '<!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10" fill="#000"/></svg>')], ['Accept' => 'application/json'])->json('artwork');
        $this->assertSame(__('param.error.svg_unsafe'), $this->preview('logo', ['artwork' => $bomb])->assertStatus(422)->json('errors.params.0'));

        // a blank picture is refused with an explanation; wrong file types never get in
        $blank = $this->post('/api/tools/artwork', ['file' => UploadedFile::fake()->image('white.png', 200, 200)], ['Accept' => 'application/json'])->json('artwork');
        $this->assertStringContainsString('%', $this->preview('logo', ['artwork' => $blank])->assertStatus(422)->json('errors.params.0'));
        $this->post('/api/tools/artwork', ['file' => UploadedFile::fake()->create('x.exe', 5)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->preview('logo', ['artwork' => '00000000-0000-0000-0000-000000000000'])->assertStatus(422);
        $this->preview('logo', ['artwork' => '../../etc/passwd'])->assertStatus(422);
        $this->preview('logo', ['line1' => '', 'line2' => ''])->assertStatus(422);
    }

    public function test_stamp_is_mirrored_and_shows_its_imprint(): void
    {
        $p = ['line1' => 'L', 'width' => 30, 'relief' => 2, 'plate' => 3, 'handle' => 'knob', 'mode' => 'raised'];
        $body = $this->preview('stamp', $p, 'body')->assertOk();
        $imprint = $this->preview('stamp', $p, 'imprint')->assertOk();
        $this->assertEqualsWithDelta(5, $this->meta($body)['bbox']['z'], 0.05);
        $this->assertContains('glue', $this->meta($body)['notes']['needs']);       // non-printed things are named

        // the letter L has its stem on the left; on the stamp it must be on the right
        $side = function ($response, float $zMin): float {
            $m = \App\Engines\Mesh\StlFile::triangles($response->baseResponse->getFile()->getPathname());
            $sum = 0; $n = 0; $xs = [];
            foreach ($m as [$a, $b, $c]) {
                foreach ([$a, $b, $c] as $v) {
                    $xs[] = $v[0];
                    if ($v[2] > $zMin && $v[1] > 20) {           // raised motif, upper part of the letter (only the stem)
                        $sum += $v[0]; $n++;
                    }
                }
            }

            return $sum / max(1, $n) - (min($xs) + max($xs)) / 2;
        };
        $this->assertLessThan(-3, $side($imprint, 1.0));         // imprint reads normally: stem left of centre
        $this->assertGreaterThan(3, $side($body, 4.0));          // stamp: mirrored

        $all = $this->meta($this->preview('stamp', $p)->assertOk());
        $this->assertGreaterThan($this->meta($body)['bbox']['x'] + 20, $all['bbox']['x']);   // the handle lies beside it
        $rec = $this->meta($this->preview('stamp', ['mode' => 'recessed', 'handle' => 'none'] + $p, 'body')->assertOk());
        $this->assertEqualsWithDelta(5, $rec['bbox']['z'], 0.05);
    }

    public function test_qr_sign_is_verified_keeps_its_quiet_zone_and_refuses_unreadable_sizes(): void
    {
        $q = $this->meta($this->preview('qr', ['url' => 'https://matplace.com', 'size' => 70, 'label' => 'matplace.com'])->assertOk());
        $this->assertTrue($q['notes']['verified']);
        $this->assertEqualsWithDelta(4 * $q['notes']['module_mm'], $q['notes']['quiet_zone_mm'], 0.06);
        $this->assertEqualsWithDelta(70, $q['bbox']['x'], 0.01);
        $this->assertGreaterThan(70 + 5, $q['bbox']['y']);                                   // caption under the code

        $dense = $this->preview('qr', ['url' => 'https://matplace.com/'.str_repeat('a', 200), 'size' => 40])->assertStatus(422);
        $this->assertMatchesRegularExpression('/\d{2,3} mm/', $dense->json('errors.params.0')); // says which size would work
        $this->preview('qr', ['url' => 'x'])->assertStatus(422);
        $this->preview('qr', ['size' => 70])->assertStatus(422);                              // the link is required

        $stand = $this->meta($this->preview('qr', ['url' => 'https://matplace.com', 'stand' => true], 'stand')->assertOk());
        $this->assertGreaterThan(30, $stand['bbox']['x']);
    }

    public function test_created_design_is_stored_with_its_artwork_and_can_be_reopened(): void
    {
        Storage::fake('models');
        config(['engines.repair' => 'trimesh']);
        $id = $this->post('/api/tools/artwork', ['file' => $this->svg('<circle cx="50" cy="25" r="20" fill="#000"/>')], ['Accept' => 'application/json'])->json('artwork');
        $r = $this->postJson('/api/tools/param', ['kind' => 'logo', 'params' => ['artwork' => $id, 'width' => 50, 'shape' => 'circle']])->assertCreated();
        $r->assertJsonPath('file.kind', 'logo')->assertJsonPath('file.tool.kind', 'logo')->assertJsonPath('file.tool.params.width', 50);
        $file = ModelFile::where('uuid', $r->json('file.uuid'))->firstOrFail();
        $this->assertSame('file:'.$file->uuid, $file->tool_params['artwork']);              // the artwork moved in with the model
        $this->assertNotNull(ParametricGenerator::artworkPath($file->tool_params['artwork']));
        $this->assertStringContainsString('from='.$file->uuid, $r->json('file.tool.url'));

        // reopened: the stored parameters build the same thing again, even after the temporary upload is gone
        @unlink(ParametricGenerator::artworkPath($id));
        $again = $this->meta($this->preview('logo', $file->tool_params)->assertOk());
        $this->assertEqualsWithDelta($file->bbox['x'], $again['bbox']['x'], 0.05);
        $this->get('/tools/logo?from='.$file->uuid)->assertOk();

        $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PLA'])->assertCreated()->assertJsonPath('calculation.status', 'done');
    }
}
