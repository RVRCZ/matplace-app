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

    public function test_generated_model_can_be_changed_in_words_but_not_a_personal_photo(): void
    {
        $user = User::factory()->create();
        $a = $this->actingAs($user)->postJson('/api/generate', ['prompt' => 'small vase', 'target_mm' => 90]);
        $a->assertCreated()->assertJsonPath('generation.file.kind', 'generated')->assertJsonPath('generation.file.generation.refinable', true);

        $this->actingAs($user)->postJson('/api/generate/'.$a->json('generation.token').'/refine', ['instruction' => ''])->assertStatus(422);
        $b = $this->actingAs($user)->postJson('/api/generate/'.$a->json('generation.token').'/refine', ['instruction' => 'wider neck']);
        $b->assertCreated();
        $new = GenerationRequest::where('token', $b->json('generation.token'))->firstOrFail();
        $this->assertSame('small vase. wider neck', $new->prompt);
        $this->assertSame(90, $new->target_mm);
        $this->assertSame(GenerationRequest::where('token', $a->json('generation.token'))->value('id'), $new->source_request_id);

        // a bust from a personal photo has no describable subject
        $photo = GenerationRequest::create(['token' => 'phototoken01', 'type' => 'image', 'status' => 'done', 'engine' => 'fake', 'prompt' => 'bust', 'description' => ['kind' => 'bust', 'delete_photo' => true]]);
        $this->actingAs($user)->postJson('/api/generate/'.$photo->token.'/refine', ['instruction' => 'add a hat'])->assertStatus(422)->assertJsonPath('error', 'not_refinable');
    }

    public function test_printers_get_a_higher_daily_limit(): void
    {
        config(['ai.daily_limits.generate_user' => 1, 'ai.daily_limits.generate_printer' => 3]);
        $printer = User::factory()->create();
        $printer->roles()->create(['role' => User::ROLE_PRINTER]);
        foreach (['vase one', 'vase two', 'vase three'] as $prompt) {
            $this->actingAs($printer)->postJson('/api/generate', ['prompt' => $prompt])->assertCreated();
        }
        $this->actingAs($printer)->postJson('/api/generate', ['prompt' => 'vase four'])->assertStatus(429)->assertJsonPath('limit', 3);
    }

    public function test_beyond_the_free_quota_a_signed_in_customer_pays_a_generation_from_credit(): void
    {
        config(['ai.daily_limits.generate_user' => 1]);
        app(\App\Domain\Farm\FarmSettings::class)->set('generation_price', 15);
        $user = User::factory()->create();
        $wallet = app(\App\Domain\Farm\Wallet::class);
        $wallet->adjust($user, 20, 'test', $user->id);

        $this->actingAs($user)->postJson('/api/generate', ['prompt' => 'vase one'])->assertCreated();      // free
        $this->assertSame(20.0, $wallet->balance($user));
        $this->actingAs($user)->postJson('/api/generate', ['prompt' => 'vase two'])->assertCreated();      // 15 from credit
        $this->assertSame(5.0, $wallet->balance($user));
        $this->assertSame(15.0, (float) GenerationRequest::latest('id')->first()->paid_credit);
        $r = $this->actingAs($user)->postJson('/api/generate', ['prompt' => 'vase three'])->assertStatus(429); // 5 < 15
        $r->assertJsonPath('error', 'credit')->assertJsonPath('missing', 10);
        $this->assertStringContainsString('/account/credit', $r->json('topup_url'));
    }

    public function test_a_guest_never_pays_the_quota_stays_hard(): void
    {
        app(\App\Domain\Farm\FarmSettings::class)->set('generation_price', 15);
        $this->postJson('/api/generate', ['prompt' => 'vase g1'])->assertCreated();
        $this->postJson('/api/generate', ['prompt' => 'vase g2'])->assertStatus(429)->assertJsonPath('error', 'daily_limit');
    }

    public function test_disabled_generator_returns_503(): void
    {
        config(['engines.generator' => 'null']);
        $this->app->forgetInstance(\App\Engines\Contracts\ModelGenerator::class);
        $this->app->forgetInstance(\App\Domain\Generation\GenerationService::class);
        $this->postJson('/api/generate', ['prompt' => 'vase'])->assertStatus(503);
    }

    public function test_more_sides_of_a_photo_are_moderated_stored_sent_together_and_deleted(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"ok":true,"subject":"person"}']]])]);
        $r = $this->post('/api/generate', [
            'image' => UploadedFile::fake()->image('front.jpg', 300, 400), 'image_back' => UploadedFile::fake()->image('back.jpg', 300, 400),
            'kind' => 'bust', 'consent' => '1', 'target_mm' => 60,
        ], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('generation.status', 'done');
        $req = GenerationRequest::where('token', $r->json('generation.token'))->firstOrFail();
        // the fake generator charges 10 per view it was given; the photos are gone once the run is over
        $this->assertSame(20, $req->cost_cents);
        $this->assertNull($req->image_path);
        $this->assertNull($req->views);
        $this->assertCount(0, Storage::disk('local')->files('photos/figures'));
    }

    public function test_a_rejected_side_photo_rejects_the_request_and_names_the_view(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => '{"ok":true,"subject":"person"}']]])
            ->push(['content' => [['type' => 'text', 'text' => '{"ok":false,"subject":"person","reason":"minor"}']]])]);
        $this->post('/api/generate', [
            'image' => UploadedFile::fake()->image('front.jpg', 300, 400), 'image_left' => UploadedFile::fake()->image('left.jpg', 300, 400),
            'kind' => 'bust', 'consent' => '1',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('error', 'photo_rejected')->assertJsonPath('view', 'left');
        $this->assertCount(0, Storage::disk('local')->files('photos/figures'));
        $this->assertSame(0, GenerationRequest::count());
    }

    public function test_tripo_v3_multiview_sends_the_four_slots_in_order_with_empty_ones_for_missing_sides(): void
    {
        Http::fake([
            'openapi.tripo3d.ai/v3/files' => Http::sequence()->push(['code' => 0, 'data' => ['file_token' => 'tok_front']])->push(['code' => 0, 'data' => ['file_token' => 'tok_back']]),
            'openapi.tripo3d.ai/v3/generation/multiview-to-model' => Http::response(['code' => 0, 'data' => ['task_id' => 'task-mv', 'status' => 'queued']]),
        ]);
        $front = sys_get_temp_dir().'/mp_mv_front_'.uniqid().'.jpg';
        $back = sys_get_temp_dir().'/mp_mv_back_'.uniqid().'.png';
        imagejpeg(imagecreatetruecolor(8, 8), $front);
        imagepng(imagecreatetruecolor(8, 8), $back);

        $g = new TripoGenerator(['api_key' => 'tsk_test', 'base_url' => 'https://openapi.tripo3d.ai', 'model' => 'v3.1-20260211', 'face_limit' => 0, 'geometry_quality' => 'detailed', 'work_dir' => sys_get_temp_dir().'/mp_tripo_out']);
        $h = $g->fromImages(['back' => $back, 'front' => $front], null, new GenerationOptions);
        $this->assertSame('task-mv', $h->externalId);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v3/generation/multiview-to-model')
            && json_encode($req['files']) === '[{"type":"jpg","file_token":"tok_front"},{},{"type":"png","file_token":"tok_back"},{}]'
            && $req['texture'] === false && $req['geometry_quality'] === 'detailed');
        @unlink($front);
        @unlink($back);
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

        $g = new TripoGenerator(['api_key' => 'tsk_test', 'base_url' => 'https://openapi.tripo3d.ai', 'model' => 'v3.1-20260211', 'face_limit' => 0, 'geometry_quality' => 'detailed', 'work_dir' => sys_get_temp_dir().'/mp_tripo_out']);
        $h = $g->fromImage($img, null, new GenerationOptions);
        $this->assertSame('task-1', $h->externalId);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v3/generation/image-to-model')
            && $req['texture'] === false && $req['pbr'] === false
            // no UV islands (they tear the mesh into open patches), full detail, no server-side face limit
            && $req['export_uv'] === false && $req['geometry_quality'] === 'detailed' && ! isset($req['face_limit'])
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
