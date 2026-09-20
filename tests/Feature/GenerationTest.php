<?php

namespace Tests\Feature;

use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Generator\TripoGenerator;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Photo/text → rough model: quotas, cache of identical inputs, result enters the normal file pipeline; Tripo V3 adapter. */
class GenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 1, 'ai.daily_limits.generate_user' => 2]);
        Storage::fake('models');
        Storage::fake('local');
    }

    private function describeToken(): string
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                'name' => 'Drak', 'name_en' => 'Dragon keychain', 'category' => 'toy', 'queries' => ['dragon keychain'],
                'bbox_mm' => ['x' => 60, 'y' => 40, 'z' => 25], 'size_known' => false, 'material' => 'PLA', 'printable' => true, 'notes' => '',
            ])]]]),
            'api.printables.com/*' => Http::response(['data' => ['searchPrints2' => ['items' => []]]]),
            'makerworld.com/*' => Http::response('', 403),
        ]);

        return $this->post('/api/describe', ['image' => UploadedFile::fake()->image('drak.jpg', 400, 300)], ['Accept' => 'application/json'])->json('token');
    }

    public function test_generate_from_photo_creates_model_file_scaled_to_size(): void
    {
        $token = $this->describeToken();
        $r = $this->postJson('/api/generate', ['describe' => $token, 'target_mm' => 60]);
        $r->assertCreated();
        $this->assertSame('done', $r->json('generation.status'));        // sync queue + fake generator finish at once
        $file = ModelFile::where('uuid', $r->json('generation.file.uuid'))->firstOrFail();
        $this->assertSame('generated', $file->origin);
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $this->assertEqualsWithDelta(60.0, max($file->bbox['x'], $file->bbox['y'], $file->bbox['z']), 0.5); // unit cube → 60 mm
        $this->getJson('/api/generate/'.$r->json('generation.token'))->assertOk()->assertJsonPath('generation.status', 'done');

        // the generated file prices like any upload
        $this->postJson('/api/calculations', ['file' => $file->uuid, 'material' => 'PLA'])->assertCreated()->assertJsonPath('calculation.status', 'done');
    }

    public function test_guest_limit_then_account_limit_and_cache(): void
    {
        $token = $this->describeToken();
        $this->postJson('/api/generate', ['describe' => $token])->assertCreated();
        $this->postJson('/api/generate', ['prompt' => 'small vase'])->assertStatus(429)->assertJsonPath('error', 'daily_limit');

        $user = User::factory()->create();
        $a = $this->actingAs($user)->postJson('/api/generate', ['prompt' => 'small vase', 'target_mm' => 100]);
        $a->assertCreated();
        // identical prompt + size → served from cache, costs nothing, same file
        $b = $this->actingAs($user)->postJson('/api/generate', ['prompt' => 'Small Vase', 'target_mm' => 100]);
        $b->assertCreated();
        $this->assertSame($a->json('generation.file.uuid'), $b->json('generation.file.uuid'));
        $this->assertSame(0, GenerationRequest::latest('id')->first()->cost_cents);
    }

    public function test_disabled_generator_returns_503(): void
    {
        config(['engines.generator' => 'null']);
        $this->app->forgetInstance(\App\Engines\Contracts\ModelGenerator::class);
        $this->app->forgetInstance(\App\Domain\Generation\GenerationService::class);
        $this->postJson('/api/generate', ['prompt' => 'vase'])->assertStatus(503);
    }

    public function test_tripo_v3_adapter_requests_geometry_only_and_downloads_result(): void
    {
        Http::fake([
            'openapi.tripo3d.ai/v3/files' => Http::response(['code' => 0, 'data' => ['file_token' => 'file_abc']]),
            'openapi.tripo3d.ai/v3/generation/image-to-model' => Http::response(['code' => 0, 'data' => ['task_id' => 'task-1', 'status' => 'queued']]),
            'openapi.tripo3d.ai/v3/tasks/task-1' => Http::sequence()
                ->push(['code' => 0, 'data' => ['status' => 'running', 'progress' => 42]])
                ->push(['code' => 0, 'data' => ['status' => 'success', 'progress' => 100, 'output' => ['model_url' => 'https://tripo-data.example/model.glb?sig=1']]]),
            'tripo-data.example/*' => Http::response(str_repeat('G', 500)),
        ]);
        $img = sys_get_temp_dir().'/mp_tripo_'.uniqid().'.jpg';
        imagejpeg(imagecreatetruecolor(8, 8), $img);

        $g = new TripoGenerator(['api_key' => 'tsk_test', 'base_url' => 'https://openapi.tripo3d.ai', 'model' => 'v3.1-20260211', 'face_limit' => 200000, 'work_dir' => sys_get_temp_dir().'/mp_tripo_out']);
        $h = $g->fromImage($img, null, new GenerationOptions);
        $this->assertSame('task-1', $h->externalId);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v3/generation/image-to-model')
            && $req['texture'] === false && $req['pbr'] === false && $req['face_limit'] === 200000
            && $req['model'] === 'v3.1-20260211' && $req['file'] === ['type' => 'jpg', 'file_token' => 'file_abc']
            && $req->hasHeader('Authorization', 'Bearer tsk_test'));

        $s1 = $g->poll($h);
        $this->assertSame(GenerationStatus::RUNNING, $s1->state);
        $this->assertSame(42, $s1->progress);
        $s2 = $g->poll($h);
        $this->assertSame(GenerationStatus::DONE, $s2->state);
        $this->assertFileExists($s2->meshPath);
        $this->assertStringEndsWith('.glb', $s2->meshPath);
        @unlink($s2->meshPath);
    }
}
