<?php

namespace Tests\Feature;

use App\Domain\Generation\ModelNormalizer;
use App\Domain\Generation\PedestalChanger;
use App\Engines\Contracts\MeshRepair;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Tools → figure/bust from a personal photo: consent, moderation, photo deleted, no sharing of results; retention. */
class FigureToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 5]);
        Storage::fake('models');
        Storage::fake('local');
    }

    private function moderation(bool $ok): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => $ok, 'subject' => 'person', 'reason' => $ok ? '' : 'nudity'])]]])]);
    }

    public function test_pages_render_in_three_languages(): void
    {
        $this->get('/tools')->assertOk()->assertSee('figure');
        $this->get('/es/tools/figure')->assertOk()->assertSee('Busto');
        $this->get('/en/tools/figure')->assertOk()->assertSee('Bust');
    }

    public function test_consent_is_required(): void
    {
        $this->moderation(true);
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg'), 'kind' => 'bust'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, GenerationRequest::count());
    }

    public function test_rejected_photo_is_deleted_and_nothing_is_generated(): void
    {
        $this->moderation(false);
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('x.jpg'), 'kind' => 'figure', 'consent' => 1], ['Accept' => 'application/json']);
        $r->assertStatus(422)->assertJsonPath('error', 'photo_rejected');
        $this->assertSame(0, GenerationRequest::count());
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
    }

    public function test_refused_side_photo_is_not_used_and_the_bust_is_still_made(): void
    {
        // the front passes, the back does not
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'person', 'reason' => ''])]]])
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['ok' => false, 'subject' => 'other', 'reason' => 'nudity'])]]])]);
        Log::spy();
        $r = $this->post('/api/generate', [
            'image' => UploadedFile::fake()->image('front.jpg'), 'image_back' => UploadedFile::fake()->image('back.jpg'),
            'kind' => 'bust', 'consent' => 1,
        ], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('generation.status', 'done')->assertJsonPath('skipped_views', ['back']);
        $this->assertSame(1, GenerationRequest::count());
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
        Log::shouldHaveReceived('info')->withArgs(fn ($m, $c) => $m === 'figure photo rejected' && $c['view'] === 'back' && $c['reason'] === 'nudity')->once();
    }

    public function test_side_photo_is_judged_for_its_content_not_for_a_hidden_face(): void
    {
        $this->moderation(true);
        $this->post('/api/generate', [
            'image' => UploadedFile::fake()->image('front.jpg'), 'image_back' => UploadedFile::fake()->image('back.jpg'),
            'kind' => 'bust', 'consent' => 1,
        ], ['Accept' => 'application/json'])->assertCreated();
        $systems = collect(Http::recorded())->map(fn ($pair) => $pair[0]['system'])->values();
        $this->assertCount(2, $systems);
        $this->assertStringNotContainsString('additional view', $systems[0]);
        $this->assertStringContainsString('from the back', $systems[1]);
        $this->assertStringContainsString('Do NOT set ok=false because the face', $systems[1]);
    }

    public function test_every_photo_of_the_form_can_be_removed(): void
    {
        $html = $this->get('/tools/figure')->assertOk()->getContent();
        foreach (['front', 'left', 'back', 'right'] as $view) {
            $this->assertStringContainsString('data-view-remove="'.$view.'"', $html);
        }
        $this->assertStringContainsString('id="figure-submit"', $html);
        // more sides are on the screen at once, with the word that they help
        $this->assertStringNotContainsString('<details id="figure-views"', $html);
        $this->assertStringContainsString(__('figure.views.better'), $html);
        // and the visitor is told how to take the photos
        $this->assertStringContainsString(__('figure.howto.frame'), $html);
    }

    public function test_bust_is_generated_photo_forgotten_and_results_not_shared(): void
    {
        $this->moderation(true);
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'target_mm' => 120], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('generation.status', 'done');
        $req = GenerationRequest::firstOrFail();
        $this->assertNull($req->image_path);                                   // photo forgotten
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
        $this->assertSame('bust', $req->description['kind']);
        $this->assertNotEmpty($req->description['consent_at']);
        $file = ModelFile::findOrFail($req->result_model_file_id);
        $this->assertSame('bust.stl', $file->original_name);
        $this->assertEqualsWithDelta(120.0, max($file->bbox['x'], $file->bbox['y'], $file->bbox['z']), 0.5);

        // same picture again → a new generation, never somebody else's cached result
        $r2 = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'target_mm' => 120], ['Accept' => 'application/json']);
        $this->assertNotSame($r->json('generation.file.uuid'), $r2->json('generation.file.uuid'));

        $this->get('/?open='.$r->json('generation.file.uuid'))->assertOk();
    }

    public function test_the_finished_generation_holds_the_front_photo_beside_the_model_and_forgets_the_side_views(): void
    {
        $this->moderation(true);
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('front.jpg', 600, 800), 'image_left' => UploadedFile::fake()->image('left.jpg', 600, 800),
            'kind' => 'bust', 'consent' => 1], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('generation.status', 'done');
        $req = GenerationRequest::firstOrFail();
        $this->assertNull($req->image_path);
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'), 'the generation forgot the front and the side view');
        $file = ModelFile::findOrFail($req->result_model_file_id);
        $held = array_values(array_filter(Storage::disk(ModelFile::DISK)->allFiles($file->dir()), fn ($f) => str_contains($f, 'source_hold.')));
        $this->assertCount(1, $held, 'the front photo alone waits beside the model for a day');
    }

    public function test_prune_removes_old_unclaimed_files_but_keeps_recent_ones(): void
    {
        $this->moderation(true);
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('a.jpg'), 'kind' => 'figure', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated();
        $old = ModelFile::firstOrFail();
        $old->forceFill(['created_at' => now()->subDays(45)])->save();
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('b.jpg'), 'kind' => 'figure', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated();

        $this->artisan('matplace:prune')->assertSuccessful();
        $this->assertSame(1, ModelFile::count());
        $this->assertNull(ModelFile::find($old->id));
    }

    public function test_pedestal_choice_and_name_travel_to_the_model(): void
    {
        $this->get('/tools/figure')->assertOk()->assertSee(__('figure.pedestal.plaque'));
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('a.jpg', 600, 600), 'kind' => 'bust', 'consent' => 1, 'pedestal' => 'pyramid'], ['Accept' => 'application/json'])->assertStatus(422);

        // the plinth with a name is real geometry: one closed body, taller than the plain base, letters raised on the front
        $python = app(PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $dir = sys_get_temp_dir().'/mp_ped_'.uniqid();
        File::ensureDirectoryExists($dir);
        MeshFixtures::cubeStl($dir.'/in.stl', 1);
        $n = app(ModelNormalizer::class);
        config(['engines.repair' => 'trimesh']);
        $check = app(MeshRepair::class);
        $n->toPrintableStl($dir.'/in.stl', $dir.'/round.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'round']);
        $n->toPrintableStl($dir.'/in.stl', $dir.'/plaque.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'plaque', 'name' => 'Babička Věra', 'dedication' => 'k 80. narozeninám']);
        $round = $check->check($dir.'/round.stl');
        $plaque = $check->check($dir.'/plaque.stl');
        $this->assertTrue($plaque->watertight);
        $this->assertSame(1, $plaque->shells);
        $this->assertGreaterThan($round->triangles + 500, $plaque->triangles);      // the letters are in the mesh, not just in a picture
        $this->assertEqualsWithDelta(80, $plaque->bbox->max(), 0.1);               // still the size the customer asked for

        // the turned foot of a classic bust: one closed body, the name bent round its band, narrower than it is tall
        $this->get('/tools/figure')->assertSee(__('figure.pedestal.socle'));
        $n->toPrintableStl($dir.'/in.stl', $dir.'/socle.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'socle']);
        $n->toPrintableStl($dir.'/in.stl', $dir.'/socle_name.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'socle', 'name' => 'Lucian']);
        $socle = $check->check($dir.'/socle.stl');
        $named = $check->check($dir.'/socle_name.stl');
        foreach ([$socle, $named] as $s) {
            $this->assertTrue($s->watertight);
            $this->assertSame(1, $s->shells);
            $this->assertEqualsWithDelta(80, $s->bbox->z, 0.1);
        }
        $this->assertGreaterThan($socle->triangles + 500, $named->triangles);

        // the antique bust stands on a tablet with the name; the sculptor's cut has no base and carries no name
        $this->get('/tools/figure')->assertSee(__('figure.pedestal.antique'))->assertSee(__('figure.pedestal.cut'));
        $n->toPrintableStl($dir.'/in.stl', $dir.'/antique.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'antique', 'name' => 'Hadrianus']);
        $n->toPrintableStl($dir.'/in.stl', $dir.'/cut.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'cut']);
        foreach (['antique', 'cut'] as $style) {
            $made = $check->check($dir.'/'.$style.'.stl');
            $this->assertTrue($made->watertight, $style);
            $this->assertSame(1, $made->shells, $style);
            $this->assertEqualsWithDelta(80, $made->bbox->max(), 0.1, $style);
        }
        $this->assertGreaterThan($socle->triangles, $check->check($dir.'/antique.stl')->triangles);
        File::deleteDirectory($dir);
    }

    public function test_torn_bottom_gets_a_flat_cut_and_the_socle_collar_stays_inside_the_chest(): void
    {
        $python = app(PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $dir = sys_get_temp_dir().'/mp_torn_'.uniqid();
        File::ensureDirectoryExists($dir);
        MeshFixtures::tornBustStl($dir.'/in.stl');
        $r = $python->run(['normalize', $dir.'/in.stl', $dir.'/socle.stl', '80', '0', 'clean,pedestal,solid', json_encode(['pedestal' => 'socle'])]);
        $this->assertTrue($r['ok'] ?? false, json_encode($r));
        $this->assertSame('socle', $r['pedestal']);
        $this->assertTrue($r['watertight']);
        $this->assertSame(1, $r['shells']);
        $this->assertGreaterThan(1.5, $r['cut_raised']);                         // the torn millimetres are cut away
        // nothing of the foot shows above the cut: what is outside the chest is exactly what is below the cut
        $this->assertEqualsWithDelta($r['foot_below_cut_mm3'], $r['foot_outside_mm3'], 1.0 + 0.01 * $r['foot_below_cut_mm3']);
        $this->assertGreaterThan($r['cut_z'] + 3, $r['collar_top_z']);           // the collar really lies inside

        [$fx, $fy, $zc, $rc, $rf] = [$r['foot_x'], $r['foot_y'], $r['cut_z'], $r['collar_r'], $r['foot_r']];
        $flat = $below = 0;
        foreach (StlFile::triangles($dir.'/socle.stl') as [$a, $b, $c]) {
            $cz = ($a[2] + $b[2] + $c[2]) / 3;
            $d = hypot(($a[0] + $b[0] + $c[0]) / 3 - $fx, ($a[1] + $b[1] + $c[1]) / 3 - $fy);
            if ($cz < $zc - 0.2) {
                // below the cut there is only the foot: no fringe of the chest, nothing of the foot sticking out sideways
                $this->assertLessThanOrEqual($rf + 0.3, $d, sprintf('triangle at z=%.2f, %.1f mm from the foot', $cz, $d));
                $below++;
            } elseif ($cz < $zc + 3 && $d <= $rc + 2.0) {
                // the chest's underside round the foot is one flat plane
                $n = [($b[1] - $a[1]) * ($c[2] - $a[2]) - ($b[2] - $a[2]) * ($c[1] - $a[1]), ($b[2] - $a[2]) * ($c[0] - $a[0]) - ($b[0] - $a[0]) * ($c[2] - $a[2]), ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0])];
                $len = sqrt($n[0] ** 2 + $n[1] ** 2 + $n[2] ** 2);
                if ($len > 1e-9 && $n[2] / $len < -0.9) {
                    $this->assertEqualsWithDelta($zc, $cz, 0.2, 'the cut face is not flat');
                    $flat++;
                }
            }
        }
        $this->assertGreaterThan(100, $below);
        $this->assertGreaterThan(10, $flat);

        // the same body on the antique socle and on the plain base: closed, one piece, the torn edge gone too
        foreach (['antique', 'round'] as $kind) {
            $s = $python->run(['normalize', $dir.'/in.stl', $dir.'/'.$kind.'.stl', '80', '0', 'clean,pedestal,solid', json_encode(['pedestal' => $kind])]);
            $this->assertTrue($s['ok'] ?? false, $kind);
            $this->assertSame($kind, $s['pedestal'], $kind);
            $this->assertTrue($s['watertight'], $kind);
            $this->assertSame(1, $s['shells'], $kind);
            $this->assertGreaterThan(1.5, $s['cut_raised'], $kind);
            if ($kind === 'antique') {
                $this->assertEqualsWithDelta($s['foot_below_cut_mm3'], $s['foot_outside_mm3'], 1.0 + 0.01 * $s['foot_below_cut_mm3']);
            }
        }
        File::deleteDirectory($dir);
    }

    /**
     * The three classic busts (the owner's pictures of 17 Nov 2026): the arms are cut away by two flat faces that lean
     * in going down, and the chest stands on a rounded-square block, on a round disc, or is rounded to a stem on a
     * round foot. The name is raised on the foot only.
     */
    public function test_classic_busts_have_flat_side_faces_and_a_foot_sized_from_the_chest(): void
    {
        $python = app(PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $dir = sys_get_temp_dir().'/mp_classic_'.uniqid();
        File::ensureDirectoryExists($dir);
        MeshFixtures::tornBustStl($dir.'/in.stl');
        $font = base_path('engines/fonts/Montserrat-wght.ttf');
        $triangles = [];
        foreach (['classic', 'classic_round', 'classic_stem'] as $kind) {
            foreach (['bare' => [], 'named' => ['name' => 'Lucian', 'font' => $font]] as $variant => $name) {
                $out = $dir.'/'.$kind.'-'.$variant.'.stl';
                $r = $python->run(['normalize', $dir.'/in.stl', $out, '80', '0', 'clean,pedestal,solid', json_encode(['pedestal' => $kind] + $name)]);
                $tag = $kind.' '.$variant;
                $this->assertTrue($r['ok'] ?? false, $tag.' '.json_encode($r));
                $this->assertSame($kind, $r['pedestal'], $tag.' '.json_encode($r));          // no fallback to the plain round base
                $this->assertTrue($r['watertight'], $tag);
                $this->assertSame(1, $r['shells'], $tag);
                $this->assertEqualsWithDelta(80, max($r['bbox']['x'], $r['bbox']['y'], $r['bbox']['z']), 0.1, $tag);
                $this->assertSame($variant === 'named' ? 1 : null, $r['engraved_lines'] ?? null, $tag);
                $triangles[$kind][$variant] = $r['triangles'];

                [$zc, $xl, $xr, $fx, $fy] = [$r['cut_z'], $r['side_left_x'], $r['side_right_x'], $r['foot_x'], $r['foot_y']];
                $lean = deg2rad($r['side_lean']);
                $this->assertGreaterThan(5, $r['side_lean'], $tag);
                $this->assertLessThan(30, $r['side_lean'], $tag);                             // an overhang that prints without supports
                $onFace = [-1 => 0.0, 1 => 0.0];
                $letters = 0;
                // the worst of every measure over all corners of the mesh (asserted once below, not per corner)
                $past = $wide = $back = $front = $letterX = $round = 0.0;
                foreach (StlFile::triangles($out) as [$a, $b, $c]) {
                    $n = [($b[1] - $a[1]) * ($c[2] - $a[2]) - ($b[2] - $a[2]) * ($c[1] - $a[1]), ($b[2] - $a[2]) * ($c[0] - $a[0]) - ($b[0] - $a[0]) * ($c[2] - $a[2]), ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0])];
                    $len = sqrt($n[0] ** 2 + $n[1] ** 2 + $n[2] ** 2);
                    foreach ([$a, $b, $c] as $p) {
                        if ($p[2] > $zc + 0.05) {
                            // the chest: how far anything reaches past the two faces (the arms are gone)
                            $reach = tan($lean) * ($p[2] - $zc);
                            $past = max($past, $p[0] - ($xr + $reach), ($xl - $reach) - $p[0]);
                        } elseif ($kind === 'classic') {
                            // the block: a rounded square round the chest's underside; only the name stands out, and only in front
                            $wide = max($wide, abs($p[0] - $fx) - $r['block_w_mm'] / 2);
                            $back = max($back, $p[1] - ($fy + $r['block_d_mm'] / 2));
                            $out_of_front = ($fy - $r['block_d_mm'] / 2) - $p[1];
                            $front = max($front, $out_of_front);
                            if ($out_of_front > 0.2) {
                                // a letter: on the flat of the front face, away from the rounded corners
                                $letterX = max($letterX, abs($p[0] - $fx));
                                $letters++;
                            }
                        } elseif ($kind === 'classic_stem' && $p[2] > $zc - 0.05) {
                            // the flat underside of the chest itself: wider than the stem since the rounding went (18 Nov 2026)
                            continue;
                        } else {
                            // the round feet: a disc that covers the underside, or a foot under a stem; the letters stand out of the rim
                            $round = max($round, hypot($p[0] - $fx, $p[1] - $fy) - $r['foot_r']);
                        }
                    }
                    if ($len < 1e-9 || min($a[2], $b[2], $c[2]) < $zc + 0.05) {
                        continue;
                    }
                    foreach ([-1, 1] as $side) {
                        // a flat side face: its normal points out and a little down, its triangles lie in one plane
                        if (($n[0] * $side * cos($lean) - $n[2] * sin($lean)) / $len > 0.9995) {
                            foreach ([$a, $b, $c] as $p) {
                                $plane = ($side > 0 ? $xr : $xl) + $side * tan($lean) * ($p[2] - $zc);
                                if (abs($p[0] - $plane) < 0.05) {
                                    continue;
                                }

                                continue 2;                                                   // a facet of the relief that happens to look the same way
                            }
                            $onFace[$side] += $len / 2;
                        }
                    }
                }
                // both faces are there, each a real plane of the chest's side (the torn bust is 80 mm tall)
                $this->assertGreaterThan(150.0, $onFace[-1], $tag.': the left side face');
                $this->assertGreaterThan(150.0, $onFace[1], $tag.': the right side face');
                $this->assertLessThanOrEqual(0.05, $past, $tag.': something reaches past a side face');
                $letter = $variant === 'named' ? 1.4 : 0.05;                                  // how far the raised name may stand out
                if ($kind === 'classic') {
                    $this->assertLessThanOrEqual(0.05, $wide, $tag.': the block is wider than it says');
                    $this->assertLessThanOrEqual(0.05, $back, $tag.': something stands out of the back of the block');
                    $this->assertLessThanOrEqual($letter, $front, $tag.': the front of the block');
                    $this->assertLessThan(0.36 * $r['block_w_mm'], $letterX, $tag.': the name runs to the corner');
                } else {
                    $this->assertLessThanOrEqual($letter, $round, $tag.': the foot is wider than it says');
                }

                if ($kind === 'classic') {
                    // a little wider and deeper than the chest above it, never a tray; a tenth of the whole height
                    $this->assertGreaterThan($r['chest_w_mm'] + 2.0, $r['block_w_mm'], $tag);
                    $this->assertGreaterThan($r['chest_d_mm'] + 2.0, $r['block_d_mm'], $tag);
                    $this->assertLessThan(1.3 * $r['chest_w_mm'], $r['block_w_mm'], $tag);
                    $this->assertLessThan(1.3 * $r['chest_d_mm'], $r['block_d_mm'], $tag);
                    $this->assertEqualsWithDelta(0.1 * $r['bbox']['z'], $r['block_h_mm'], 0.02 * $r['bbox']['z'], $tag);
                    $this->assertEqualsWithDelta($zc, $r['block_h_mm'], 0.01, $tag);          // the block's top is the plane of the chest's flat cut
                    $this->assertSame($variant === 'named', $letters > 0, $tag);
                } elseif ($kind === 'classic_round') {
                    $this->assertGreaterThanOrEqual(0.5 * hypot($r['chest_w_mm'], $r['chest_d_mm']) * 0.9, $r['foot_r'], $tag);   // the disc covers the underside
                    $this->assertLessThan(0.13 * $r['bbox']['z'], $zc, $tag);                 // and is low
                } else {
                    $this->assertLessThan(0.5 * $r['chest_w_mm'], $r['foot_r'], $tag);       // the foot under the stem is narrower than the chest
                }
            }
            $this->assertGreaterThan($triangles[$kind]['bare'] + 200, $triangles[$kind]['named'], $kind.': the letters are in the mesh');
        }
        File::deleteDirectory($dir);
    }

    public function test_bust_page_offers_the_classic_styles_first_and_hides_them_for_a_figure(): void
    {
        foreach (['' => ['Busta na bloku', 'Busta na disku', 'Busta na noze'], '/en' => ['Bust on a block', 'Bust on a disc', 'Bust on a stem'], '/es' => ['Busto sobre bloque', 'Busto sobre disco', 'Busto sobre pie']] as $prefix => $names) {
            $html = $this->get($prefix.'/tools/figure')->assertOk()->assertSeeInOrder($names)->getContent();
            preg_match_all('/<label[^>]*?( data-bust-style)?\s*>\s*<input type="radio" name="pedestal" value="(\w+)"([^>]*)>/', $html, $m, PREG_SET_ORDER);
            // the three classic styles first, in the order of the owner's picture; then the older styles; then the plain bases
            $this->assertSame(['classic', 'classic_round', 'classic_stem', 'socle', 'antique', 'cut', 'round', 'square', 'hexagon', 'column', 'plaque', 'none'], array_column($m, 2), $prefix);
            foreach ($m as [, $bustOnly, $value, $rest]) {
                // what the page hides for a whole figure carries the mark its script goes by
                $this->assertSame(in_array($value, PedestalChanger::BUST_STYLES, true), $bustOnly !== '', $value);
                // the page starts with the turned foot until the owner chooses otherwise (BustStylesTest)
                $this->assertSame($value === 'socle', str_contains($rest, 'checked'), $value);
            }
            // a line about each style, the name field for the styles that carry a name
            $this->assertStringContainsString(__('figure.pedestal.about.classic', [], ltrim($prefix, '/') ?: 'cs'), $html);
        }
        foreach (['classic', 'classic_round', 'classic_stem'] as $style) {
            $this->assertContains($style, PedestalChanger::NAMED);
            $this->assertNotContains($style, PedestalChanger::DEDICATED);
            foreach (['cs', 'en', 'es'] as $locale) {
                foreach (['figure.pedestal.'.$style, 'figure.pedestal.about.'.$style] as $key) {
                    $this->assertNotSame($key, __($key, [], $locale), $key.' '.$locale);
                }
            }
        }
    }

    public function test_a_bust_changes_to_a_classic_style_and_back_without_a_new_generation(): void
    {
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $this->moderation(true);
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'pedestal' => 'classic', 'pedestal_name' => 'Ema', 'pedestal_dedication' => 'x'], ['Accept' => 'application/json'])->assertCreated();
        $uuid = $r->json('generation.file.uuid');
        // made with the classic block and its name; the block has no room for a dedication
        $r->assertJsonPath('generation.file.generation.pedestal.type', 'classic')->assertJsonPath('generation.file.generation.pedestal.name', 'Ema');
        $this->assertSame(['classic', 'Ema', null], [GenerationRequest::firstOrFail()->description['pedestal'], GenerationRequest::firstOrFail()->description['pedestal_name'], GenerationRequest::firstOrFail()->description['pedestal_dedication'] ?? null]);

        // away from it …
        $round = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'round'])->assertCreated()->assertJsonPath('file.generation.pedestal.type', 'round');
        // … and back to it, and to the other two, from the changed file: with a name and without
        $files = [];
        foreach (['classic', 'classic_round', 'classic_stem'] as $style) {
            foreach (['Lucian', ''] as $name) {
                $p = $this->postJson('/api/files/'.$round->json('file.uuid').'/pedestal', ['type' => $style, 'name' => $name, 'dedication' => 'x'])->assertCreated()
                    ->assertJsonPath('file.generation.pedestal.type', $style)->assertJsonPath('file.generation.pedestal.name', $name)->assertJsonPath('file.generation.pedestal.dedication', '');
                $files[$style][$name] = ModelFile::where('uuid', $p->json('file.uuid'))->firstOrFail();
                $this->assertTrue($files[$style][$name]->isReady(), $style);
                Storage::disk('models')->assertExists('files/'.$files[$style][$name]->uuid.'/source.stl');
            }
            // the name is in the mesh, so the style was really built (a fallback to the round base carries no name)
            $this->assertGreaterThan($files[$style]['']->triangles + 200, $files[$style]['Lucian']->triangles, $style);
        }
        $this->assertSame(1, GenerationRequest::count());                         // no new generation, no credits
    }

    public function test_base_of_a_generated_bust_can_be_changed_without_a_new_generation(): void
    {
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $this->moderation(true);
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'pedestal' => 'round'], ['Accept' => 'application/json'])->assertCreated();
        $uuid = $r->json('generation.file.uuid');
        $r->assertJsonPath('generation.file.generation.pedestal.type', 'round');
        Storage::disk('models')->assertExists('files/'.$uuid.'/source.stl');      // the figure alone is kept for later changes

        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'pyramid'])->assertStatus(422);
        $p = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'plaque', 'name' => 'Věra', 'front' => 'right', 'sink' => 20])->assertCreated();
        $p->assertJsonPath('file.generation.pedestal.sink', 20);
        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'round', 'sink' => 55])->assertStatus(422);
        $p->assertJsonPath('file.generation.pedestal.type', 'plaque')->assertJsonPath('file.generation.pedestal.name', 'Věra');
        $this->assertNotSame($uuid, $p->json('file.uuid'));
        // the classic socle keeps the name and has no room for a dedication
        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'socle', 'name' => 'Lucian', 'dedication' => 'x'])->assertCreated()
            ->assertJsonPath('file.generation.pedestal.type', 'socle')->assertJsonPath('file.generation.pedestal.name', 'Lucian')->assertJsonPath('file.generation.pedestal.dedication', '');
        $this->assertSame(1, GenerationRequest::count());                         // no new generation, no credits
        $new = ModelFile::where('uuid', $p->json('file.uuid'))->firstOrFail();
        $this->assertTrue($new->isReady());
        // the raised name is in the mesh: the same plinth without a name has fewer triangles (the round base is no
        // measure any more: it is a turned piece with its own curves now)
        $bare = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'plaque', 'front' => 'right', 'sink' => 20])->assertCreated();
        $this->assertGreaterThan(ModelFile::where('uuid', $bare->json('file.uuid'))->firstOrFail()->triangles + 500, $new->triangles);
        Storage::disk('models')->assertExists('files/'.$new->uuid.'/source.stl');

        // an uploaded model has no base to change
        $plain = ModelFile::create(['uuid' => (string) Str::uuid(), 'original_name' => 'a.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'storage_path' => 'files/x/original.stl', 'origin' => 'upload', 'status' => ModelFile::STATUS_UPLOADED]);
        $this->postJson('/api/files/'.$plain->uuid.'/pedestal', ['type' => 'round'])->assertNotFound();
    }
}
