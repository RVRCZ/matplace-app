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
}
