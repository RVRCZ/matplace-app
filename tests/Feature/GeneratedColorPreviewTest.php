<?php

namespace Tests\Feature;

use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The coloured preview of a generated model (22 Nov 2026): raw.glb next to the STL is served at /api/files/{uuid}/model.glb
 * and offered as glb_url in the file's JSON, up to a size limit. Printing and price stay on the STL.
 */
class GeneratedColorPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function file(?string $glb = null): ModelFile
    {
        Storage::fake('models');
        $f = ModelFile::factory()->create(['origin' => 'generated']);
        $f->forceFill(['stl_path' => $f->dir().'/model.stl'])->save();
        Storage::disk('models')->put($f->stl_path, 'solid x');
        if ($glb !== null) {
            Storage::disk('models')->put($f->dir().'/raw.glb', $glb);
        }

        return $f->refresh();
    }

    public function test_glb_is_served_with_its_content_type(): void
    {
        $f = $this->file('glTF-bytes');
        $res = $this->get(route('api.files.glb', $f->uuid));
        $res->assertOk();
        $this->assertSame('model/gltf-binary', $res->headers->get('Content-Type'));
        $this->assertSame('10', $res->headers->get('Content-Length'));
    }

    public function test_no_raw_glb_is_a_404(): void
    {
        $f = $this->file();
        $this->get(route('api.files.glb', $f->uuid))->assertNotFound();
    }

    public function test_a_file_that_is_not_ready_has_no_glb(): void
    {
        $f = $this->file('glTF');
        $f->forceFill(['status' => ModelFile::STATUS_FAILED])->save();
        $this->get(route('api.files.glb', $f->uuid))->assertNotFound();
    }

    public function test_payload_carries_glb_url_only_when_there_is_one(): void
    {
        $with = $this->file('glTF');
        $this->getJson(route('api.files.show', $with->uuid))->assertOk()
            ->assertJsonPath('file.glb_url', route('api.files.glb', $with->uuid))
            ->assertJsonPath('file.stl_url', route('api.files.stl', $with->uuid));

        $without = $this->file();
        $this->getJson(route('api.files.show', $without->uuid))->assertOk()->assertJsonPath('file.glb_url', null);
    }

    public function test_payload_hides_a_glb_over_the_limit(): void
    {
        config(['ai.glb_preview_max_bytes' => 8]);
        $f = $this->file('0123456789');
        $this->getJson(route('api.files.show', $f->uuid))->assertOk()->assertJsonPath('file.glb_url', null);

        config(['ai.glb_preview_max_bytes' => 10]);
        $this->getJson(route('api.files.show', $f->uuid))->assertOk()->assertJsonPath('file.glb_url', route('api.files.glb', $f->uuid));
    }
}
