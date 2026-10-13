<?php

namespace Tests\Feature;

use App\Engines\Contracts\ImageRestyler;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Exceptions\GenerationException;
use App\Engines\Generator\MeshyGenerator;
use App\Models\AiCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Meshy adapter behind ModelGenerator, as its documentation describes the API (docs.meshy.ai, 14 Oct 2026):
 * what is sent, how a task's state is read, what a failure looks like. The service itself answers nothing here.
 */
class MeshyGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/mp_meshy_'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function meshy(array $over = []): MeshyGenerator
    {
        return new MeshyGenerator($over + ['api_key' => 'msy_test', 'base_url' => 'https://api.meshy.ai', 'model' => 'meshy-7.1', 'geometry_quality' => 'detailed', 'detail_resolution' => '2k', 'credit_cents' => 2, 'work_dir' => $this->dir]);
    }

    private function photo(string $name = 'face.jpg'): string
    {
        $path = $this->dir.'/'.$name;
        $img = imagecreatetruecolor(16, 16);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 170, 150));
        str_ends_with($name, '.png') ? imagepng($img, $path) : imagejpeg($img, $path);
        imagedestroy($img);

        return $path;
    }

    public function test_a_photo_goes_in_the_request_and_asks_for_geometry_only(): void
    {
        Http::fake(['api.meshy.ai/openapi/v1/image-to-3d' => Http::response(['result' => '018a210d-8ba4-705c-b111-1f1776f7f578'], 202)]);
        $handle = $this->meshy()->fromImage($this->photo(), null, new GenerationOptions);

        $this->assertSame('meshy', $handle->engine);
        $this->assertSame('image-to-3d:018a210d-8ba4-705c-b111-1f1776f7f578', $handle->externalId);
        // 20 credits and 5 for the finer geometry, two cents a credit: what the request keeps as its cost
        $this->assertSame(['credits' => 50, 'meshy_credits' => 25, 'model' => 'meshy-7.1'], $handle->meta);
        Http::assertSent(function (Request $r) {
            $b = $r->data();

            return $r->method() === 'POST' && $r->url() === 'https://api.meshy.ai/openapi/v1/image-to-3d' && $r->hasHeader('Authorization', 'Bearer msy_test')
                && str_starts_with($b['image_url'], 'data:image/jpeg;base64,/9j/') && $b['ai_model'] === 'meshy-7.1'
                && $b['should_texture'] === false && $b['should_remesh'] === false && $b['geometry_resolution'] === '2k' && $b['target_formats'] === ['glb'];
        });
        $this->assertSame('meshy-fine', AiCall::where('kind', 'generate')->sole()->engine);

        // standard geometry when the request asks for it (a miniature is smoothed anyway): 20 credits
        $plain = $this->meshy()->fromImage($this->photo('pet.png'), null, new GenerationOptions(geometryQuality: 'standard'));
        $this->assertSame(40, $plain->meta['credits']);
        Http::assertSent(fn (Request $r) => str_starts_with((string) ($r->data()['image_url'] ?? ''), 'data:image/png;base64,') && $r->data()['geometry_resolution'] === 'standard');
        $this->assertSame(1, AiCall::where('engine', 'meshy')->count());
        // the small model knows no finer geometry and costs 5 credits
        $lite = $this->meshy(['model' => 'meshy-6-lite']);
        $this->assertSame(10, $lite->estimatedCostCents());
        $this->assertSame(50, $this->meshy()->estimatedCostCents());
    }

    public function test_several_sides_go_in_their_order_and_one_side_is_a_single_photo(): void
    {
        Http::fake([
            'api.meshy.ai/openapi/v1/multi-image-to-3d' => Http::response(['result' => 'task-mv']),
            'api.meshy.ai/openapi/v1/image-to-3d' => Http::response(['result' => 'task-one']),
        ]);
        $front = $this->photo('front.jpg');
        $back = $this->photo('back.png');
        $right = $this->photo('right.jpg');

        $handle = $this->meshy(['detail_resolution' => '4k'])->fromImages(['right' => $right, 'back' => $back, 'front' => $front, 'top' => $front], null, new GenerationOptions);
        $this->assertSame('multi-image-to-3d:task-mv', $handle->externalId);
        Http::assertSent(function (Request $r) {
            $urls = $r->data()['image_urls'] ?? null;

            // front, back, right (no left was given, "top" is no side); several pictures take the finer geometry to 2k only
            return is_array($urls) && count($urls) === 3 && str_starts_with($urls[0], 'data:image/jpeg') && str_starts_with($urls[1], 'data:image/png') && str_starts_with($urls[2], 'data:image/jpeg')
                && $r->data()['geometry_resolution'] === '2k' && $r->data()['should_texture'] === false;
        });

        $this->assertSame('image-to-3d:task-one', $this->meshy()->fromImages(['front' => $front], null, new GenerationOptions)->externalId);
        $this->expectException(GenerationException::class);
        $this->meshy()->fromImages(['left' => $front], null, new GenerationOptions);
    }

    public function test_a_text_asks_for_the_preview_which_is_the_mesh_without_a_texture(): void
    {
        Http::fake(['api.meshy.ai/openapi/v2/text-to-3d' => Http::response(['result' => 'task-text'])]);
        $handle = $this->meshy()->fromText(str_repeat('a small owl on a branch ', 60), new GenerationOptions(geometryQuality: 'standard'));

        $this->assertSame('text-to-3d:task-text', $handle->externalId);
        Http::assertSent(function (Request $r) {
            $b = $r->data();

            return $b['mode'] === 'preview' && mb_strlen($b['prompt']) === 800 && $b['ai_model'] === 'meshy-7.1' && ! array_key_exists('should_texture', $b) && $b['target_formats'] === ['glb'];
        });
        $this->expectException(GenerationException::class);
        $this->meshy()->fromText('ab', new GenerationOptions);
    }

    public function test_a_task_is_asked_for_at_the_address_of_its_kind_until_its_model_is_downloaded(): void
    {
        Http::fake([
            'api.meshy.ai/openapi/v1/image-to-3d/task-1' => Http::sequence()
                ->push(['id' => 'task-1', 'status' => 'PENDING', 'progress' => 0])
                ->push(['id' => 'task-1', 'status' => 'IN_PROGRESS', 'progress' => 100])
                ->push(['id' => 'task-1', 'status' => 'SUCCEEDED', 'progress' => 100, 'consumed_credits' => 25, 'model_urls' => ['glb' => 'https://assets.meshy.example/task-1/model.glb?Expires=1']]),
            'assets.meshy.example/*' => Http::response(str_repeat('G', 500)),
            'api.meshy.ai/openapi/v2/text-to-3d/task-t' => Http::response(['status' => 'IN_PROGRESS', 'progress' => 37]),
        ]);
        $meshy = $this->meshy();
        $handle = new GenerationHandle('meshy', 'image-to-3d:task-1');      // as the job rebuilds it: the engine and the id, nothing else

        $this->assertSame(GenerationStatus::QUEUED, $meshy->poll($handle)->state);
        $running = $meshy->poll($handle);
        $this->assertSame([GenerationStatus::RUNNING, 99], [$running->state, $running->progress]);      // never "done" before the file is here
        $done = $meshy->poll($handle);
        $this->assertSame(GenerationStatus::DONE, $done->state);
        $this->assertStringEndsWith('.glb', (string) $done->meshPath);
        $this->assertSame(500, filesize((string) $done->meshPath));

        $text = $meshy->poll(new GenerationHandle('meshy', 'text-to-3d:task-t'));
        $this->assertSame([GenerationStatus::RUNNING, 37], [$text->state, $text->progress]);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://api.meshy.ai/openapi/v2/text-to-3d/task-t' && $r->hasHeader('Authorization', 'Bearer msy_test'));
    }

    public function test_failures_are_told_apart_what_passes_is_asked_again_what_does_not_ends_the_request(): void
    {
        Http::fake([
            'api.meshy.ai/openapi/v1/image-to-3d/failed' => Http::response(['status' => 'FAILED', 'progress' => 40, 'task_error' => ['type' => 'invalid_request', 'message' => 'The image contains no object.']]),
            'api.meshy.ai/openapi/v1/image-to-3d/canceled' => Http::response(['status' => 'CANCELED']),
            'api.meshy.ai/openapi/v1/image-to-3d/empty' => Http::response(['status' => 'SUCCEEDED', 'model_urls' => ['fbx' => 'https://assets.meshy.example/x.fbx']]),
            'api.meshy.ai/openapi/v1/image-to-3d/busy' => Http::response(['message' => 'Too Many Requests'], 429),
            'api.meshy.ai/openapi/v1/image-to-3d/down' => Http::response('', 503),
            'api.meshy.ai/openapi/v1/image-to-3d/broke' => Http::response(['message' => 'Insufficient funds'], 402),
            'api.meshy.ai/openapi/v1/image-to-3d/gone' => Http::response(['message' => 'Not found'], 404),
            'api.meshy.ai/openapi/v1/image-to-3d/half' => Http::response(['status' => 'SUCCEEDED', 'model_urls' => ['glb' => 'https://assets.meshy.example/half.glb']]),
            'assets.meshy.example/half.glb' => Http::response('', 500),
        ]);
        $state = fn (string $id) => $this->meshy()->poll(new GenerationHandle('meshy', $id));

        $this->assertSame([GenerationStatus::FAILED, 'meshy_failed: The image contains no object.'], [$state('image-to-3d:failed')->state, $state('image-to-3d:failed')->error]);
        $this->assertSame([GenerationStatus::FAILED, 'meshy_canceled'], [$state('image-to-3d:canceled')->state, $state('image-to-3d:canceled')->error]);
        $this->assertSame([GenerationStatus::FAILED, 'meshy_no_model_url'], [$state('image-to-3d:empty')->state, $state('image-to-3d:empty')->error]);
        $this->assertSame(GenerationStatus::RUNNING, $state('image-to-3d:busy')->state);
        $this->assertSame(GenerationStatus::RUNNING, $state('image-to-3d:down')->state);
        $this->assertSame([GenerationStatus::FAILED, 'meshy_http_402'], [$state('image-to-3d:broke')->state, $state('image-to-3d:broke')->error]);
        $this->assertSame([GenerationStatus::FAILED, 'meshy_http_404'], [$state('image-to-3d:gone')->state, $state('image-to-3d:gone')->error]);
        // the model was ready but its download did not come: asked again, no file is left behind
        $half = $state('image-to-3d:half');
        $this->assertSame([GenerationStatus::RUNNING, 'download_retry'], [$half->state, $half->error]);
        $this->assertSame([], glob($this->dir.'/*.glb'));
        // an id that names no kind of task (a request of another engine) is no task of Meshy
        $this->assertSame('meshy_bad_task_id', $state('task-1')->error);
        $this->assertSame('meshy_bad_task_id', $state('figure:task-1')->error);
    }

    public function test_a_refused_task_and_a_missing_key_are_said_in_words(): void
    {
        Http::fake(['api.meshy.ai/openapi/v1/image-to-3d' => Http::response(['message' => 'Insufficient credits to perform this task'], 402)]);
        try {
            $this->meshy()->fromImage($this->photo(), null, new GenerationOptions);
            $this->fail('a refused task gave a handle');
        } catch (GenerationException $e) {
            $this->assertSame('Meshy task failed: Insufficient credits to perform this task', $e->getMessage());
        }
        $this->assertSame(0, AiCall::count());      // nothing was started, nothing is booked

        foreach (['notes.gif' => 'Unsupported image type', 'missing.jpg' => 'Image not found'] as $name => $why) {
            if ($name === 'notes.gif') {
                file_put_contents($this->dir.'/'.$name, 'GIF89a');
            }
            try {
                $this->meshy()->fromImage($this->dir.'/'.$name, null, new GenerationOptions);
                $this->fail($name);
            } catch (GenerationException $e) {
                $this->assertStringContainsString($why, $e->getMessage());
            }
        }
        $this->expectExceptionMessage('MESHY_API_KEY is not set.');
        $this->meshy(['api_key' => ''])->fromImage($this->photo(), null, new GenerationOptions);
    }

    public function test_the_switch_names_the_generator_and_the_trial_command_makes_a_printable_file_per_photo_and_engine(): void
    {
        config(['engines.generator' => 'meshy', 'ai.meshy.api_key' => 'msy_test']);
        $this->app->forgetInstance(ModelGenerator::class);
        $bound = app(ModelGenerator::class);
        $this->assertInstanceOf(MeshyGenerator::class, $bound);
        $this->assertSame('meshy', $bound->name());
        $this->assertNotInstanceOf(ImageRestyler::class, $bound);      // no redrawn picture: a cartoon pet figurine is not offered with it

        // the trial with the generator of the tests: one STL and the raw mesh per photo, and a report
        $out = $this->dir.'/trial';
        $this->artisan('matplace:generator-trial', ['photos' => [$this->photo('face.jpg'), $this->photo('dog.png')], '--engines' => 'fake', '--kind' => 'plain', '--size' => 40, '--out' => $out, '--yes' => true])->assertSuccessful();
        foreach (['face-fake.stl', 'face-fake-raw.stl', 'dog-fake.stl', 'dog-fake-raw.stl', 'report.json'] as $file) {
            $this->assertFileExists($out.'/'.$file);
        }
        $report = json_decode((string) file_get_contents($out.'/report.json'), true);
        $this->assertSame(['face.jpg', 'dog.png'], array_column($report['rows'], 'photo'));
        $this->assertSame([true, true], array_column($report['rows'], 'ok'));
        $this->assertSame('detailed', $report['quality']);

        // a photo that is not there, a generator that is not one: nothing is spent
        $this->artisan('matplace:generator-trial', ['photos' => [$this->dir.'/nobody.jpg'], '--engines' => 'fake', '--yes' => true])->assertFailed();
        $this->artisan('matplace:generator-trial', ['photos' => [$this->photo()], '--engines' => 'luma', '--yes' => true])->assertFailed();
        // without --yes the command asks before it sends anything
        $this->artisan('matplace:generator-trial', ['photos' => [$this->photo()], '--engines' => 'fake', '--out' => $this->dir.'/asked'])
            ->expectsConfirmation('This spends credits at fake. Go on?', 'no')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($this->dir.'/asked');
    }
}
