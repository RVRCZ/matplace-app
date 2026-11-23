<?php

namespace Tests\Feature;

use App\Models\GenerationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The generator's coloured GLB is shown while the printable file is still being made (23 Nov 2026). */
class GenerationPreviewGlbTest extends TestCase
{
    use RefreshDatabase;

    private string $glb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glb = sys_get_temp_dir().'/preview_'.uniqid().'.glb';
        file_put_contents($this->glb, 'glTF-fake');
    }

    protected function tearDown(): void
    {
        @unlink($this->glb);
        parent::tearDown();
    }

    private function req(string $status, ?string $path): GenerationRequest
    {
        return GenerationRequest::create(['token' => 'prevtoken01', 'type' => 'image', 'status' => $status, 'engine' => 'tripo', 'prompt' => 'x', 'progress' => 99,
            'description' => $path === null ? ['kind' => 'bust'] : ['kind' => 'bust', 'preview_glb' => $path]]);
    }

    public function test_describe_offers_the_preview_only_while_the_file_exists_and_the_model_is_not_done(): void
    {
        $this->req('running', $this->glb);
        $this->getJson('/api/generate/prevtoken01')->assertOk()->assertJsonPath('generation.preview_glb_url', url('/api/generate/prevtoken01/preview.glb'));
        $this->get('/api/generate/prevtoken01/preview.glb')->assertOk()->assertHeader('Content-Type', 'model/gltf-binary');

        unlink($this->glb);
        $this->getJson('/api/generate/prevtoken01')->assertOk()->assertJsonPath('generation.preview_glb_url', null);
        $this->get('/api/generate/prevtoken01/preview.glb')->assertNotFound();
    }

    public function test_no_preview_without_a_path_after_done_and_for_a_wrong_token(): void
    {
        $this->req('running', null);
        $this->getJson('/api/generate/prevtoken01')->assertJsonPath('generation.preview_glb_url', null);
        $this->get('/api/generate/prevtoken01/preview.glb')->assertNotFound();

        GenerationRequest::where('token', 'prevtoken01')->update(['status' => 'done', 'description' => json_encode(['preview_glb' => $this->glb])]);
        $this->getJson('/api/generate/prevtoken01')->assertJsonPath('generation.preview_glb_url', null);
        $this->get('/api/generate/prevtoken01/preview.glb')->assertNotFound();
        $this->get('/api/generate/nosuchtoken1/preview.glb')->assertNotFound();
    }
}
