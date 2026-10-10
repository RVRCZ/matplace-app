<?php

namespace Tests\Feature;

use App\Domain\Generation\ModelNormalizer;
use App\Domain\Generation\PedestalChanger;
use App\Engines\Contracts\ImageRestyler;
use App\Engines\Contracts\MeshRepair;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Generator\TripoGenerator;
use App\Engines\Repair\PythonTool;
use App\Jobs\GenerateModel;
use App\Models\AiCall;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\AiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Tools → a pet figurine from a photo (/tools/pet-figurine): the figure tool with the looks and the bases of an animal.
 * One whole animal and no person in the photo, three styles (the cartoon one is two tasks of the generator), a base
 * that follows four paws and carries the name, the thinnest place measured, another base without a new generation.
 */
class PetFigurineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 20]);
        Storage::fake('models');
        Storage::fake('local');
    }

    private function vision(bool $ok, string $reason = ''): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => $ok, 'subject' => 'pet', 'reason' => $reason])]]])]);
    }

    private function make(array $more = [])
    {
        return $this->post('/api/generate', ['image' => UploadedFile::fake()->image('rex.jpg', 800, 600), 'kind' => 'pet', 'consent' => 1] + $more, ['Accept' => 'application/json']);
    }

    private function python(): void
    {
        if (! app(PythonTool::class)->available()) {
            $this->markTestSkipped('Python is not installed.');
        }
    }

    public function test_the_page_shows_its_steps_tips_and_styles_in_three_languages(): void
    {
        foreach (['cs' => '/tools/pet-figurine', 'en' => '/en/tools/pet-figurine', 'es' => '/es/tools/pet-figurine'] as $lang => $path) {
            app()->setLocale($lang);
            $page = $this->get($path)->assertOk();
            $page->assertSee(__('tools.pet.title'))->assertSee(__('pet.lead'))->assertSee('data-module="figure"', false)->assertSee('name="kind" value="pet"', false);
            foreach (['toolpage.section.photo', 'toolpage.section.style', 'toolpage.section.base', 'pet.howto.body', 'pet.howto.alone', 'pet.style.realistic', 'pet.style.miniature', 'pet.style.cartoon',
                'pet.roughness.hint', 'pet.base.oval', 'pet.base.round', 'pet.base.plaque', 'pet.name', 'pet.consent', 'pet.privacy', 'pet.submit', 'pet.wait', 'pet.rebase'] as $key) {
                $this->assertNotSame($key, __($key), $lang.' '.$key);
                $page->assertSee(__($key));
            }
            // no key is shown instead of a text, and the script gets the pet's own words under the keys it asks for
            $this->assertDoesNotMatchRegularExpression('/>\s*(pet|figure|tools|toolpage)\.[a-z_.]+\s*</', $page->getContent(), $lang);
            $page->assertSee('"figure.thin"', false)->assertSee('"figure.rejected.person"', false)->assertSee('"figure.back"', false);
            // every photo can be taken away again, as on the figure's page
            foreach (['front', 'left', 'back', 'right'] as $view) {
                $page->assertSee('data-view-remove="'.$view.'"', false);
            }
        }
        app()->setLocale('cs');
        // the styles of a pet are not the bust's finishes, and a pet always stands on a base
        $html = $this->get('/tools/pet-figurine')->getContent();
        $this->assertStringNotContainsString('value="socle"', $html);
        $this->assertStringNotContainsString('value="none"', $html);
        $this->assertStringContainsString('placeholder="'.__('pet.name_ph').'"', $html);
        // the catalogue and the search know it
        $this->get('/tools')->assertOk()->assertSee(route('tools.pet'), false)->assertSee(__('tools.pet.title'));
        foreach (['cs', 'en', 'es'] as $lang) {
            $this->assertNotEmpty(trans('tools.words.pet', [], $lang));
            $this->assertCount(6, trans('tools_seo/pet.faq', [], $lang), $lang);
        }
        // a generator that cannot redraw a photo offers no cartoon, instead of a button that would answer with the realistic one
        config(['engines.generator' => 'null']);
        app()->forgetInstance(ModelGenerator::class);
        $this->get('/tools/pet-figurine')->assertOk()->assertSee(__('figure.unavailable'))->assertDontSee('value="cartoon"', false);
    }

    public function test_a_pet_is_generated_named_and_its_photo_forgotten(): void
    {
        $this->python();
        $this->vision(true);
        // the consent is asked as for a figure
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('rex.jpg'), 'kind' => 'pet'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->make(['style' => 'clay'])->assertStatus(422);
        $this->assertSame(0, GenerationRequest::count());

        $r = $this->make(['target_mm' => 100, 'pedestal' => 'oval', 'pedestal_name' => 'Rex'])->assertCreated()->assertJsonPath('generation.status', 'done');
        $req = GenerationRequest::firstOrFail();
        $this->assertSame(['pet', 'pet figurine', 'realistic', 'oval', 'Rex'], [$req->description['kind'], $req->description['name_en'], $req->description['style'], $req->description['pedestal'], $req->description['pedestal_name']]);
        $this->assertNotEmpty($req->description['consent_at']);
        $this->assertNull($req->image_path);
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
        // the name is geometry on the base, and the figure is as big as asked
        $this->assertSame(1, $req->description['engraved_lines']);
        $file = ModelFile::findOrFail($req->result_model_file_id);
        $this->assertSame('pet-figurine.stl', $file->original_name);
        $this->assertEqualsWithDelta(100.0, max($file->bbox['x'], $file->bbox['y'], $file->bbox['z']), 0.5);
        // what the page shows: the look, the base, the thinnest place and the limit it is held against
        $pet = $r->json('generation.file.generation.pet');
        $this->assertSame(['realistic', 'oval', 'Rex', PedestalChanger::PET_SAFE_MM], [$pet['style'], $pet['type'], $pet['name'], $pet['safe_mm']]);
        $this->assertGreaterThan(0, $pet['thinnest_mm']);
        // the calculator's base changer is the bust's: it is not offered for a pet
        $this->assertNull($r->json('generation.file.generation.pedestal'));

        // the page opened by the address of the figurine gets the same choices back
        $again = $this->getJson('/api/generate/'.$req->token)->assertOk();
        $this->assertSame(['kind' => 'pet', 'style' => 'realistic', 'pedestal' => 'oval', 'pedestal_name' => 'Rex'], $again->json('generation.options'));
        $this->assertSame(100, $again->json('generation.target_mm'));
        $this->get('/tools/pet-figurine?generation='.$req->token)->assertOk()->assertSee('id="figure-form"', false);
        $this->get('/?open='.$r->json('generation.file.uuid'))->assertOk();

        // a miniature stands on its disc whatever base was sent, and a base of a bust is no base of a pet
        $this->make(['style' => 'miniature', 'pedestal' => 'plaque', 'roughness' => 3])->assertCreated();
        $mini = GenerationRequest::latest('id')->firstOrFail();
        $this->assertSame(['miniature', 'round', 3], [$mini->description['style'], $mini->description['pedestal'], $mini->description['roughness']]);
        $this->make(['pedestal' => 'socle'])->assertCreated();
        $this->assertSame('oval', GenerationRequest::latest('id')->firstOrFail()->description['pedestal']);
        // a figure keeps the bases it had
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg'), 'kind' => 'figure', 'consent' => 1, 'pedestal' => 'oval'], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('round', GenerationRequest::latest('id')->firstOrFail()->description['pedestal']);
    }

    public function test_the_photo_has_to_show_one_whole_animal_and_no_person(): void
    {
        // the first photo is refused, the second one (a bust) passes
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['ok' => false, 'subject' => 'person', 'reason' => 'person'])]]])
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'person', 'reason' => ''])]]])]);
        $this->make()->assertStatus(422)->assertJsonPath('error', 'photo_rejected')->assertJsonPath('reason', 'person');
        $this->assertSame(0, GenerationRequest::count());
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
        $system = Http::recorded()[0][0]['system'];
        $this->assertStringContainsString('figurine of a PET', $system);
        foreach (['no_animal', 'person', 'several', 'cropped'] as $reason) {
            $this->assertStringContainsString('"'.$reason.'"', $system);
            $this->assertNotSame('pet.rejected.'.$reason, __('pet.rejected.'.$reason));
        }
        // a figure or a bust is asked what it always was
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg'), 'kind' => 'bust', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated();
        $this->assertStringNotContainsString('PET', collect(Http::recorded())->last()[0]['system']);
    }

    public function test_the_cartoon_is_the_photo_redrawn_and_then_a_model_of_the_picture(): void
    {
        $this->python();
        $this->vision(true);
        $this->make(['style' => 'cartoon', 'pedestal' => 'plaque', 'pedestal_name' => 'Micka', 'pedestal_dedication' => '2014–2026'])->assertCreated()->assertJsonPath('generation.status', 'done');
        $req = GenerationRequest::firstOrFail();
        // two tasks: the picture (5 credits) and the model in standard geometry (20), not the detailed one of a realistic figure
        $this->assertSame(['cartoon', 'model', 25], [$req->description['style'], $req->description['stage'], $req->cost_cents]);
        // the photo and the redrawn picture are both gone
        $this->assertNull($req->image_path);
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
        $this->assertSame(['plaque', 2], [$req->description['pedestal'], $req->description['engraved_lines']]);
        // a miniature is made in standard geometry too, a realistic one as the generator is set up
        $this->make(['style' => 'miniature'])->assertCreated();
        $this->assertSame(20, GenerationRequest::latest('id')->firstOrFail()->cost_cents);
        $this->make()->assertCreated();
        $this->assertSame(0, GenerationRequest::latest('id')->firstOrFail()->cost_cents);
        // the sides are of no use to a cartoon: the model is made of the one redrawn picture
        $this->make(['style' => 'cartoon', 'image_left' => UploadedFile::fake()->image('left.jpg')])->assertCreated();
        $this->assertSame(25, GenerationRequest::latest('id')->firstOrFail()->cost_cents);
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
    }

    public function test_the_generator_redraws_the_photo_and_makes_the_cheaper_geometry_when_asked(): void
    {
        Http::fake([
            'openapi.tripo3d.ai/v3/files' => Http::response(['code' => 0, 'data' => ['file_token' => 'file_pet']]),
            'openapi.tripo3d.ai/v3/generation/image-to-image' => Http::response(['code' => 0, 'data' => ['task_id' => 'task-img', 'status' => 'queued']]),
            'openapi.tripo3d.ai/v3/generation/image-to-model' => Http::response(['code' => 0, 'data' => ['task_id' => 'task-model', 'status' => 'queued']]),
            'openapi.tripo3d.ai/v3/tasks/task-img' => Http::sequence()
                ->push(['code' => 0, 'data' => ['status' => 'running', 'progress' => 40]])
                ->push(['code' => 0, 'data' => ['status' => 'success', 'progress' => 100, 'output' => ['generated_image_url' => 'https://tripo-data.example/cartoon.png?sig=1']]])
                ->push(['code' => 0, 'data' => ['status' => 'failed', 'progress' => 10]]),
            'tripo-data.example/*' => Http::response(str_repeat('P', 500)),
        ]);
        $photo = sys_get_temp_dir().'/mp_pet_'.uniqid().'.jpg';
        imagejpeg(imagecreatetruecolor(8, 8), $photo);
        $tripo = new TripoGenerator(['api_key' => 'tsk_test', 'base_url' => 'https://openapi.tripo3d.ai', 'model' => 'v3.1-20260211', 'face_limit' => 0, 'geometry_quality' => 'detailed', 'image_autofix' => true, 'work_dir' => sys_get_temp_dir().'/mp_tripo_pet']);
        $this->assertInstanceOf(ImageRestyler::class, $tripo);

        // the picture: Tripo's own image model, the prompt of the cartoon figure, five credits
        $drawn = $tripo->restyle($photo, GenerateModel::CARTOON_PROMPT);
        $this->assertSame(['task-img', 5], [$drawn->externalId, $drawn->meta['credits']]);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v3/generation/image-to-image') && $req['input'] === 'file_pet' && $req['model'] === 'seedream_v5'
            && str_contains($req['prompt'], 'thick sturdy legs') && ! isset($req['texture']));
        $this->assertSame(GenerationStatus::RUNNING, $tripo->pollImage($drawn)->state);
        $done = $tripo->pollImage($drawn);
        $this->assertSame(GenerationStatus::DONE, $done->state);
        $this->assertStringEndsWith('.png', (string) $done->previewPath);
        $this->assertSame(500, filesize((string) $done->previewPath));
        @unlink((string) $done->previewPath);
        $failed = $tripo->pollImage($drawn);
        $this->assertSame([GenerationStatus::FAILED, 'tripo_image_failed'], [$failed->state, $failed->error]);

        // the model: standard geometry when the figure is smoothed anyway (20 credits), else as the generator is set up (40);
        // no `style` is ever sent: V3 has none for a model, it only costs five credits more
        $plain = $tripo->fromImage($photo, null, new GenerationOptions(geometryQuality: 'standard'));
        $this->assertSame(20, $plain->meta['credits']);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/v3/generation/image-to-model') && $req['geometry_quality'] === 'standard' && $req['enable_image_autofix'] === true && ! isset($req['style']));
        $fine = $tripo->fromImage($photo, null, new GenerationOptions);
        $this->assertSame(40, $fine->meta['credits']);
        // every task is recorded at its own price: 0.05, 0.20 and 0.40 dollars
        config(['ai.prices.usd_czk' => 20]);
        $this->assertSame(['tripo-image', 'tripo-standard', 'tripo'], AiCall::orderBy('id')->pluck('engine')->all());
        $this->assertSame([1.0, 4.0, 8.0], [AiUsage::cost('tripo-image', 0, 0), AiUsage::cost('tripo-standard', 0, 0), AiUsage::cost('tripo', 0, 0)]);
        @unlink($photo);
    }

    public function test_the_miniature_makes_thin_legs_printable_and_the_thinnest_place_is_told(): void
    {
        $this->python();
        $dir = sys_get_temp_dir().'/mp_pet_'.uniqid();
        File::ensureDirectoryExists($dir);
        // an "animal" on four legs 1.4 mm thick: what breaks off a print
        MeshFixtures::tableStl($dir.'/in.stl', 1.4);
        $n = app(ModelNormalizer::class);
        config(['engines.repair' => 'trimesh']);
        $check = app(MeshRepair::class);
        $font = ['pet' => true, 'pedestal' => 'round', 'name' => 'Rex'];

        $n->toPrintableStl($dir.'/in.stl', $dir.'/real.stl', 80, false, ['clean', 'pedestal', 'solid'], $font + ['style' => 'realistic']);
        $real = $n->report;
        $this->assertLessThan(PedestalChanger::PET_SAFE_MM, $real['thinnest_mm'], 'legs of 1.4 mm are told as they are');
        $this->assertGreaterThan(1.0, $real['thinnest_mm']);

        $started = microtime(true);
        $n->toPrintableStl($dir.'/in.stl', $dir.'/mini.stl', 80, false, ['clean', 'pedestal', 'solid'], $font + ['style' => 'miniature', 'roughness' => 2, 'source_out' => $dir.'/source.stl']);
        $mini = $n->report;
        $this->assertLessThan(20, microtime(true) - $started, 'the miniature of an 80 mm figure takes seconds');
        $this->assertTrue($mini['miniature']);
        $this->assertGreaterThanOrEqual(PedestalChanger::PET_SAFE_MM, $mini['thinnest_mm']);
        $this->assertGreaterThanOrEqual(0.8, $mini['grown_mm']);
        $this->assertSame($real['thinnest_mm'], $mini['thinnest_before_mm']);
        // one closed body on a disc no smaller than 40 mm, the name raised on it
        $this->assertSame(['round', 1], [$mini['pedestal'], $mini['engraved_lines']]);
        $this->assertGreaterThanOrEqual(40, $mini['base_mm'][0]);
        $solid = $check->check($dir.'/mini.stl');
        $this->assertTrue($solid->watertight);
        $this->assertSame(1, $solid->shells);
        $this->assertEqualsWithDelta(80.0, max($solid->bbox->x, $solid->bbox->y, $solid->bbox->z), 0.5);
        // more clay, thicker legs
        $n->toPrintableStl($dir.'/in.stl', $dir.'/mini3.stl', 80, false, ['clean', 'pedestal', 'solid'], $font + ['style' => 'miniature', 'roughness' => 3]);
        $this->assertGreaterThan($mini['grown_mm'], $n->report['grown_mm']);

        // another base under the figure that was kept (grown once, not twice): the oval follows the paws, the plinth is taller
        $n->toPrintableStl($dir.'/source.stl', $dir.'/oval.stl', 80, false, ['pedestal', 'solid'], ['pet' => true, 'pedestal' => 'oval']);
        $oval = $n->report;
        $this->assertSame('oval', $oval['pedestal']);
        $this->assertArrayNotHasKey('miniature', $oval);
        $this->assertEqualsWithDelta($mini['thinnest_mm'], $oval['thinnest_mm'], 0.6);
        $this->assertLessThan($mini['base_mm'][1], $oval['base_mm'][1], 'an oval base is narrower than the disc round the same paws');
        $n->toPrintableStl($dir.'/source.stl', $dir.'/plaque.stl', 80, false, ['pedestal', 'solid'], ['pet' => true, 'pedestal' => 'plaque', 'name' => 'Rex', 'dedication' => '2014–2026']);
        $this->assertSame(['plaque', 2], [$n->report['pedestal'], $n->report['engraved_lines']]);
        $this->assertGreaterThan($oval['pedestal_height'], $n->report['pedestal_height']);
        $this->assertTrue($check->check($dir.'/plaque.stl')->watertight);
        File::deleteDirectory($dir);
    }

    public function test_the_base_and_the_name_change_without_a_new_generation(): void
    {
        $this->python();
        $this->vision(true);
        $uuid = $this->make(['pedestal' => 'oval', 'pedestal_name' => 'Rex'])->assertCreated()->json('generation.file.uuid');
        $this->assertSame(1, GenerationRequest::count());
        // a base of a bust is refused, a base of a pet makes a new file of the same generation
        $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'socle'])->assertStatus(422);
        $new = $this->postJson('/api/files/'.$uuid.'/pedestal', ['type' => 'plaque', 'name' => 'Bety', 'dedication' => '2020'])->assertCreated();
        $this->assertNotSame($uuid, $new->json('file.uuid'));
        $this->assertSame(1, GenerationRequest::count());
        $pet = $new->json('file.generation.pet');
        $this->assertSame(['realistic', 'plaque', 'Bety', '2020'], [$pet['style'], $pet['type'], $pet['name'], $pet['dedication']]);
        $this->assertGreaterThan(0, $pet['thinnest_mm']);
        $file = ModelFile::where('uuid', $new->json('file.uuid'))->firstOrFail();
        $this->assertSame('plaque', $file->tool_params['pedestal']);
        // and again from the new file: the figure is kept beside every one of them
        $this->postJson('/api/files/'.$new->json('file.uuid').'/pedestal', ['type' => 'round', 'name' => ''])->assertCreated()->assertJsonPath('file.generation.pet.type', 'round')->assertJsonPath('file.generation.pet.name', '');
        // a figure is still changed the way it was, and takes no base of a pet
        $figure = $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg'), 'kind' => 'figure', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated()->json('generation.file.uuid');
        $this->postJson('/api/files/'.$figure.'/pedestal', ['type' => 'oval'])->assertStatus(422);
        $this->postJson('/api/files/'.$figure.'/pedestal', ['type' => 'square'])->assertCreated()->assertJsonPath('file.generation.pedestal.type', 'square')->assertJsonPath('file.generation.pet', null);
    }

    public function test_a_hidden_tool_makes_nothing_for_a_visitor(): void
    {
        $this->vision(true);
        config(['tools.pet.available' => false]);
        $this->get('/tools/pet-figurine')->assertNotFound();
        $this->make()->assertNotFound();
        $this->assertSame(0, GenerationRequest::count());
        // the figure beside it is not touched by the switch
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg'), 'kind' => 'figure', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated();
        // an admin tries the hidden tool out
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get('/tools/pet-figurine')->assertOk();
        $this->actingAs($admin)->post('/api/generate', ['image' => UploadedFile::fake()->image('rex.jpg'), 'kind' => 'pet', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated();
    }
}
