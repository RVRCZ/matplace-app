<?php

namespace Tests\Feature;

use App\Domain\Generation\ModelNormalizer;
use App\Domain\Generation\PedestalChanger;
use App\Engines\Contracts\MeshRepair;
use App\Engines\Generator\FakeGenerator;
use App\Engines\Repair\PythonTool;
use App\Jobs\GenerateModel;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The pet figurine together with its owner (15 Nov 2026): the choice on the page, the photo check for one animal and one
 * person, the cleaning wording, a base that carries both, the consent of a photographed person. Nothing is paid for:
 * the generator is the fake one and the vision is Http::fake.
 */
class PetWithOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 20, 'ai.clean_photo' => 'always']);
        Storage::fake('models');
        Storage::fake('local');
        FakeGenerator::$restyled = [];
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

    public function test_the_page_offers_the_choice_in_three_languages_with_the_consent_of_a_person(): void
    {
        foreach (['cs' => '/tools/pet-figurine', 'en' => '/en/tools/pet-figurine', 'es' => '/es/tools/pet-figurine'] as $lang => $path) {
            app()->setLocale($lang);
            $page = $this->get($path)->assertOk();
            // two radios in the panel (the animal alone first), the same two in the dialog of the viewer, outside the form
            $page->assertSee('name="with_person" value="0" checked', false)->assertSee('name="with_person" value="1"', false)
                ->assertSee('id="figure-drop-owner-0"', false)->assertSee('id="figure-drop-owner-1"', false);
            foreach (['pet.owner.label', 'pet.owner.alone', 'pet.owner.with', 'pet.owner.alone.hint', 'pet.owner.with.hint', 'pet.owner.style', 'pet.owner.size',
                'pet.consent.owner', 'pet.privacy.owner', 'pet.consent', 'pet.privacy'] as $key) {
                $this->assertNotSame($key, __($key), $lang.' '.$key);
                $page->assertSee(__($key));
            }
            // the words the script says for a refused picture and for a thin leash
            foreach (['rejected.owner.no_person', 'rejected.owner.several_people', 'rejected.owner.cropped', 'thin.owner'] as $key) {
                $this->assertNotSame('pet.'.$key, __('pet.'.$key), $lang.' '.$key);
                $page->assertSee('"figure.'.$key.'"', false);
            }
            $this->assertDoesNotMatchRegularExpression('/>\s*(pet|figure|tools|toolpage)\.[a-z_.]+\s*</', $page->getContent(), $lang);
            // the consent of a person is the bust's kind: "the photo shows me or I have the person's consent"
            $this->assertNotSame(__('pet.consent'), __('pet.consent.owner'));
        }
        // the bust page has no such choice
        $this->get('/tools/figure')->assertOk()->assertDontSee('with_person', false);
    }

    public function test_the_choice_is_a_field_kept_in_the_request_and_put_back_by_the_address(): void
    {
        $this->python();
        $this->vision(true);
        // without it the pet is what it always was
        $this->make()->assertCreated();
        $alone = GenerationRequest::firstOrFail();
        $this->assertArrayNotHasKey('with_person', $alone->description);
        $this->assertArrayNotHasKey('consent_kind', $alone->description);
        $this->assertArrayNotHasKey('with_person', $this->getJson('/api/generate/'.$alone->token)->json('generation.options'));
        $this->assertFalse($this->getJson('/api/generate/'.$alone->token)->json('generation.file.generation.pet.with_person'));

        $this->make(['with_person' => 'sometimes'])->assertStatus(422);
        // the consent is asked as for a figure, and for a person it is the consent of the person
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('rex.jpg'), 'kind' => 'pet', 'with_person' => '1'], ['Accept' => 'application/json'])->assertStatus(422);

        $r = $this->make(['with_person' => '1', 'target_mm' => 150, 'pedestal' => 'plaque', 'pedestal_name' => 'Rex'])->assertCreated()->assertJsonPath('generation.status', 'done');
        $req = GenerationRequest::latest('id')->firstOrFail();
        $this->assertTrue($req->description['with_person']);
        $this->assertSame('person', $req->description['consent_kind']);
        $this->assertNotEmpty($req->description['consent_at']);
        // the page opened by the address gets the choice back, and the file says it has a person on it
        $again = $this->getJson('/api/generate/'.$req->token)->assertOk();
        $this->assertSame('1', $again->json('generation.options.with_person'));
        $this->assertTrue($r->json('generation.file.generation.pet.with_person'));
        $this->get('/tools/pet-figurine?generation='.$req->token)->assertOk();
        // the photo is gone as for every pet
        $this->assertNull($req->image_path);
        $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'));
        // the price is the pet's: the cleaning picture (5 credits) and the model, nothing added for the person
        $this->assertSame(5, $req->cost_cents);
    }

    public function test_only_the_realistic_look_is_made_with_a_person(): void
    {
        $this->vision(true);
        $this->make(['with_person' => '1', 'style' => 'miniature', 'roughness' => 3, 'pedestal' => 'plaque'])->assertCreated();
        $req = GenerationRequest::firstOrFail();
        $this->assertSame('realistic', $req->description['style']);
        $this->assertArrayNotHasKey('roughness', $req->description);
        $this->assertSame('plaque', $req->description['pedestal']);   // the base the customer picked: no disc forced on
        $this->make(['with_person' => '1', 'style' => 'cartoon'])->assertCreated();
        $this->assertSame('realistic', GenerationRequest::latest('id')->firstOrFail()->description['style']);
        // the animal alone keeps all three
        $this->make(['style' => 'miniature'])->assertCreated();
        $this->assertSame('miniature', GenerationRequest::latest('id')->firstOrFail()->description['style']);
    }

    public function test_the_photo_check_asks_for_one_animal_and_one_person(): void
    {
        $this->vision(true);
        $this->make(['with_person' => '1'])->assertCreated();
        $system = Http::recorded()[0][0]['system'];
        $this->assertStringContainsString('PET TOGETHER WITH ITS OWNER', $system);
        foreach (['no_animal', 'no_person', 'several', 'several_people', 'cropped'] as $reason) {
            $this->assertStringContainsString('"'.$reason.'"', $system);
        }
        // the content rules stay
        $this->assertStringContainsString('nudity or sexual content', $system);
        // the animal alone is asked what it was today: a person is no reason to refuse
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'pet', 'reason' => ''])]]])]);
        $this->make()->assertCreated();
        $alone = Http::recorded()[0][0]['system'];
        $this->assertStringContainsString('figurine of a PET.', $alone);
        $this->assertStringNotContainsString('OWNER', $alone);
        $this->assertStringNotContainsString('no_person', $alone);

        foreach (['cs', 'en', 'es'] as $lang) {
            foreach (['pet.rejected.owner.no_person', 'pet.rejected.owner.several_people', 'pet.rejected.owner.cropped', 'pet.rejected.no_animal', 'pet.rejected.several'] as $key) {
                $this->assertNotSame($key, trans($key, [], $lang), $lang.' '.$key);
            }
            // the refusal for a missing person offers the first option, by its name
            $this->assertStringContainsString(trans('pet.owner.alone', [], $lang), trans('pet.rejected.owner.no_person', [], $lang));
        }
    }

    public function test_every_refusal_comes_back_and_leaves_nothing_behind(): void
    {
        // every reason comes back to the page, which has a sentence for each (the ones of the pet where they fit)
        $reasons = ['no_animal', 'no_person', 'several', 'several_people', 'cropped'];
        $sequence = Http::sequence();
        foreach ($reasons as $reason) {
            $sequence->push(['content' => [['type' => 'text', 'text' => json_encode(['ok' => false, 'subject' => 'pet', 'reason' => $reason])]]]);
        }
        Http::fake(['api.anthropic.com/*' => $sequence]);
        foreach ($reasons as $reason) {
            $this->make(['with_person' => '1'])->assertStatus(422)->assertJsonPath('error', 'photo_rejected')->assertJsonPath('reason', $reason);
            $this->assertSame(0, GenerationRequest::count());
            $this->assertSame([], Storage::disk('local')->allFiles('photos/figures'), $reason);
        }
    }

    public function test_the_cleaning_of_the_photo_keeps_both_and_names_nothing_they_wear(): void
    {
        $this->vision(true);
        $this->make(['with_person' => '1'])->assertCreated()->assertJsonPath('generation.status', 'done');
        $this->assertSame([GenerateModel::CLEAN_PET_OWNER_PROMPT], FakeGenerator::$restyled);
        $this->assertSame(['realistic', 'model'], [GenerationRequest::firstOrFail()->description['style'], GenerationRequest::firstOrFail()->description['stage']]);
        // the animal alone and a bust are cleaned by their own words
        FakeGenerator::$restyled = [];
        $this->make()->assertCreated();
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame([GenerateModel::CLEAN_PROMPT, GenerateModel::CLEAN_BUST_PROMPT], FakeGenerator::$restyled);
        // more sides of the photo: no cleaning, as for every pet
        FakeGenerator::$restyled = [];
        $this->make(['with_person' => '1', 'image_left' => UploadedFile::fake()->image('left.jpg')])->assertCreated();
        $this->assertSame([], FakeGenerator::$restyled);

        $p = GenerateModel::CLEAN_PET_OWNER_PROMPT;
        // what must stay is said before what goes, as in the lessons of the bust's wording
        $this->assertLessThan(strpos($p, 'Remove'), strpos($p, 'keep the face'));
        $this->assertLessThan(strpos($p, 'Remove'), strpos($p, 'precisely as they are'));
        foreach (['same person', 'same animal', 'expression', 'age', 'hair', 'eyes exactly as they are', 'breed', 'coat pattern', 'pose', 'whole', 'leash', 'same position', 'Plain white'] as $needle) {
            $this->assertStringContainsStringIgnoringCase($needle, str_replace('exactly this same', 'same', $p), $needle);
        }
        foreach (['background', 'other person', 'furniture', 'objects'] as $needle) {
            $this->assertStringContainsString($needle, $p);
        }
        // nothing the person wears is named (an earlier wording drew glasses on a woman who had none)
        foreach (['glasses', 'hat', 'earring', 'jacket', 'shirt', 'beard'] as $worn) {
            $this->assertDoesNotMatchRegularExpression('/'.$worn.'/i', $p, $worn);
        }
    }

    public function test_one_base_carries_the_person_and_the_animal_and_the_bust_cut_never_runs(): void
    {
        $this->python();
        $dir = sys_get_temp_dir().'/mp_owner_'.uniqid();
        File::ensureDirectoryExists($dir);
        MeshFixtures::personAndDogStl($dir.'/in.stl');
        $n = app(ModelNormalizer::class);
        config(['engines.repair' => 'trimesh']);
        $check = app(MeshRepair::class);

        // what GenerateModel asks for a pet with its owner: the pet's options, no `cut`, no `front`, no `tidy`
        $extras = ['pet' => true, 'person' => true, 'style' => 'realistic', 'pedestal' => 'oval', 'name' => 'Rex', 'source_out' => $dir.'/source.stl'];
        $n->toPrintableStl($dir.'/in.stl', $dir.'/out.stl', 100, false, ['clean', 'pedestal', 'solid'], $extras);
        $r = $n->report;
        $this->assertTrue($r['with_person']);
        $this->assertSame('oval', $r['pedestal']);
        $this->assertArrayNotHasKey('bust_cut', $r);
        $this->assertArrayNotHasKey('stand_error', $r);
        $solid = $check->check($dir.'/out.stl');
        $this->assertTrue($solid->watertight);
        $this->assertSame(1, $solid->shells, 'the two bodies and the base are one piece');
        $this->assertEqualsWithDelta(100.0, max($solid->bbox->x, $solid->bbox->y, $solid->bbox->z), 0.5);
        // the base reaches from the person's feet to the far paws: 70 mm of ground plus the margins, and the name strip
        $scale = $r['figure_scale'];
        $this->assertGreaterThan(70 * $scale, $r['base_mm'][0] * $scale);
        $this->assertGreaterThan(70.0, $r['base_mm'][0]);
        // the figure itself is whole: the person is as tall as it was (the base is thin under the feet)
        $this->assertGreaterThan(70 * $scale, $solid->bbox->z);
        $this->assertEqualsWithDelta(1, $r['engraved_lines'], 0);

        // the same figure on the plinth, and without a base: both come out whole too
        foreach (['plaque', 'none'] as $kind) {
            $n->toPrintableStl($dir.'/source.stl', $dir.'/'.$kind.'.stl', 100, false, ['pedestal', 'solid'], ['pet' => true, 'person' => true, 'pedestal' => $kind, 'name' => 'Rex']);
            $this->assertSame($kind, $n->report['pedestal']);
            $this->assertTrue($check->check($dir.'/'.$kind.'.stl')->watertight, $kind);
        }
        File::deleteDirectory($dir);
    }

    public function test_the_job_passes_the_person_to_the_normaliser_and_changing_the_base_keeps_it(): void
    {
        $this->python();
        $this->vision(true);
        $r = $this->make(['with_person' => '1', 'pedestal' => 'oval', 'pedestal_name' => 'Rex', 'target_mm' => 120])->assertCreated();
        $req = GenerationRequest::latest('id')->firstOrFail();
        // the cube of the fake generator is a "figure": the report says a person was meant and nothing of a bust ran
        $this->assertSame('oval', $req->description['pedestal']);
        $this->assertArrayNotHasKey('bust_cut', $req->description);
        $file = ModelFile::findOrFail($req->result_model_file_id);
        $new = app(PedestalChanger::class)->changePet($file, ['type' => 'plaque', 'name' => 'Rex', 'dedication' => '', 'name_side' => 'auto']);
        $this->assertSame('plaque', $new->tool_params['pedestal']);
        $this->assertTrue(app(PedestalChanger::class)->petState($new)['with_person']);
        $this->assertNotNull($r->json('generation.file.generation.pet.safe_mm'));
    }
}
