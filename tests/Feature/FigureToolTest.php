<?php

namespace Tests\Feature;

use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $this->get('/tools/figure?lang=es')->assertOk()->assertSee('Busto');
        $this->get('/tools/figure?lang=en')->assertOk()->assertSee('Bust');
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
        $this->post('/api/generate', ['image' => \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 600, 600), 'kind' => 'bust', 'consent' => 1, 'pedestal' => 'pyramid'], ['Accept' => 'application/json'])->assertStatus(422);

        // the plinth with a name is real geometry: one closed body, taller than the plain base, letters raised on the front
        $python = app(\App\Engines\Repair\PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $dir = sys_get_temp_dir().'/mp_ped_'.uniqid();
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
        \Tests\Support\MeshFixtures::cubeStl($dir.'/in.stl', 1);
        $n = app(\App\Domain\Generation\ModelNormalizer::class);
        config(['engines.repair' => 'trimesh']);
        $check = app(\App\Engines\Contracts\MeshRepair::class);
        $n->toPrintableStl($dir.'/in.stl', $dir.'/round.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'round']);
        $n->toPrintableStl($dir.'/in.stl', $dir.'/plaque.stl', 80, false, ['clean', 'pedestal', 'solid'], ['pedestal' => 'plaque', 'name' => 'Babička Věra', 'dedication' => 'k 80. narozeninám']);
        $round = $check->check($dir.'/round.stl');
        $plaque = $check->check($dir.'/plaque.stl');
        $this->assertTrue($plaque->watertight);
        $this->assertSame(1, $plaque->shells);
        $this->assertGreaterThan($round->triangles + 500, $plaque->triangles);      // the letters are in the mesh, not just in a picture
        $this->assertEqualsWithDelta(80, $plaque->bbox->max(), 0.1);               // still the size the customer asked for
        \Illuminate\Support\Facades\File::deleteDirectory($dir);
    }

    public function test_base_of_a_generated_bust_can_be_changed_without_a_new_generation(): void
    {
        if (! app(\App\Engines\Repair\PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
        $this->moderation(true);
        $r = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'pedestal' => 'round'], ['Accept' => 'application/json'])->assertCreated();
        $uuid = $r->json('generation.file.uuid');
        $r->assertJsonPath('generation.file.generation.pedestal.type', 'round');
        Storage::disk('models')->assertExists('files/'.$uuid.'/source.stl');      // the figure alone is kept for later changes

        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'pyramid'])->assertStatus(422);
        $p = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'plaque', 'name' => 'Věra', 'front' => 'right'])->assertCreated();
        $p->assertJsonPath('file.generation.pedestal.type', 'plaque')->assertJsonPath('file.generation.pedestal.name', 'Věra');
        $this->assertNotSame($uuid, $p->json('file.uuid'));
        $this->assertSame(1, GenerationRequest::count());                         // no new generation, no credits
        $new = ModelFile::where('uuid', $p->json('file.uuid'))->firstOrFail();
        $this->assertTrue($new->isReady());
        $this->assertGreaterThan(ModelFile::where('uuid', $uuid)->firstOrFail()->triangles + 500, $new->triangles);   // the raised name is in the mesh
        Storage::disk('models')->assertExists('files/'.$new->uuid.'/source.stl');

        // an uploaded model has no base to change
        $plain = ModelFile::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'original_name' => 'a.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'storage_path' => 'files/x/original.stl', 'origin' => 'upload', 'status' => ModelFile::STATUS_UPLOADED]);
        $this->postJson('/api/files/'.$plain->uuid.'/pedestal', ['type' => 'round'])->assertNotFound();
    }
}
