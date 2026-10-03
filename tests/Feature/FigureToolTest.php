<?php

namespace Tests\Feature;

use App\Domain\Generation\ModelNormalizer;
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
        $this->assertGreaterThan(ModelFile::where('uuid', $uuid)->firstOrFail()->triangles + 500, $new->triangles);   // the raised name is in the mesh
        Storage::disk('models')->assertExists('files/'.$new->uuid.'/source.stl');

        // an uploaded model has no base to change
        $plain = ModelFile::create(['uuid' => (string) Str::uuid(), 'original_name' => 'a.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'storage_path' => 'files/x/original.stl', 'origin' => 'upload', 'status' => ModelFile::STATUS_UPLOADED]);
        $this->postJson('/api/files/'.$plain->uuid.'/pedestal', ['type' => 'round'])->assertNotFound();
    }
}
