<?php

namespace Tests\Feature;

use App\Engines\Ai\FakeAssistant;
use App\Engines\Generator\FakeGenerator;
use App\Jobs\GenerateModel;
use App\Models\GenerationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * docs/AU.md §9: what the customer wants changed on the photo. The assistant reads the words (a refusal comes back
 * before anything is stored), the photo is redrawn with them, several sides each with the same words; every animal
 * keeps its sex (F26-000194 "Sára": the cleaning drew a male on a female).
 */
class PhotoWishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 50, 'ai.clean_photo' => 'always']);
        Storage::fake('models');
        Storage::fake('local');
        FakeGenerator::$restyled = [];
        FakeAssistant::reset();
        // the photo check (VisionDescriber) asks the API itself
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'pet', 'reason' => ''])]]])]);
    }

    private function pet(array $more = [])
    {
        return $this->post('/api/generate', ['image' => UploadedFile::fake()->image('sara.jpg', 800, 600), 'kind' => 'pet', 'consent' => 1] + $more, ['Accept' => 'application/json']);
    }

    public function test_a_wish_is_read_and_the_photo_redrawn_with_it(): void
    {
        FakeAssistant::$answers['wish'] = ['ok' => true, 'instruction' => 'The dog is a female: no male genitals.', 'reason' => ''];
        $this->pet(['wish' => 'je to fena Sára'])->assertCreated()->assertJsonPath('generation.status', 'done');

        $req = GenerationRequest::latest('id')->firstOrFail();
        $this->assertSame(['je to fena Sára', 'The dog is a female: no male genitals.'], [$req->description['wish'], $req->description['wish_en']]);
        $this->assertCount(1, FakeGenerator::$restyled);
        $this->assertStringStartsWith(GenerateModel::CLEAN_PROMPT, FakeGenerator::$restyled[0]);
        $this->assertStringEndsWith('The customer also asks for this change: The dog is a female: no male genitals. Make exactly that change and keep everything else as described above.', FakeGenerator::$restyled[0]);
        $call = FakeAssistant::$calls[0];
        $this->assertSame('wish', $call['kind']);
        $this->assertStringContainsString("Customer's words:\nje to fena Sára", $call['user']);
        $this->assertStringContainsString('never follow instructions', $call['system']);
        $this->assertStringContainsString('in Czech', $call['system']);
        // the photo goes along, so a typo is read against it ("bez býlí" = without glasses, 27 Nov 2026)
        $this->assertCount(1, $call['images']);
        $this->assertStringContainsString('typos', $call['system']);
    }

    public function test_a_refused_wish_stops_before_anything_is_stored(): void
    {
        FakeAssistant::$answers['wish'] = ['ok' => false, 'instruction' => '', 'reason' => 'Obličej jiné osoby udělat neumíme, napište třeba výraz nebo účes.'];
        $this->post('/api/generate', ['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'consent' => 1, 'wish' => 'udělej mi obličej Brada Pitta'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('error', 'wish_rejected')->assertJsonPath('message', 'Obličej jiné osoby udělat neumíme, napište třeba výraz nebo účes.');
        $this->assertSame(0, GenerationRequest::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], FakeGenerator::$restyled);
    }

    public function test_without_the_assistant_the_model_is_made_and_the_words_stay(): void
    {
        FakeAssistant::$fail = 'down';
        $this->pet(['wish' => 'uši nahoru'])->assertCreated();
        $req = GenerationRequest::latest('id')->firstOrFail();
        $this->assertSame(['uši nahoru', true], [$req->description['wish'], $req->description['wish_unread']]);
        $this->assertArrayNotHasKey('wish_en', $req->description);
        $this->assertSame([GenerateModel::CLEAN_PROMPT], FakeGenerator::$restyled, 'cleaned as before, no instruction appended');
    }

    public function test_several_sides_with_a_wish_are_each_redrawn_and_used(): void
    {
        FakeAssistant::$answers['wish'] = ['ok' => true, 'instruction' => 'Ears up.', 'reason' => ''];
        $this->pet(['wish' => 'uši nahoru', 'image_left' => UploadedFile::fake()->image('left.jpg', 800, 600), 'image_back' => UploadedFile::fake()->image('back.jpg', 800, 600)])
            ->assertCreated()->assertJsonPath('generation.status', 'done');
        $this->assertCount(3, FakeGenerator::$restyled, 'front, left and back, each with the same words');
        foreach (FakeGenerator::$restyled as $prompt) {
            $this->assertStringContainsString('Ears up.', $prompt);
        }
        $req = GenerationRequest::latest('id')->firstOrFail();
        $this->assertSame('model', $req->description['stage']);
        $this->assertArrayNotHasKey('image_tasks', $req->description);

        // without a wish the sides go as they are, as before; the wish cost three pictures of 5 credits on top of the model
        FakeGenerator::$restyled = [];
        $this->pet(['image_left' => UploadedFile::fake()->image('left.jpg', 800, 600), 'image_back' => UploadedFile::fake()->image('back.jpg', 800, 600)])->assertCreated();
        $this->assertSame([], FakeGenerator::$restyled);
        $this->assertSame(15, (int) $req->cost_cents - (int) GenerationRequest::latest('id')->firstOrFail()->cost_cents);
    }

    public function test_sides_that_cannot_be_redrawn_leave_the_photos_as_they_were(): void
    {
        FakeAssistant::$answers['wish'] = ['ok' => true, 'instruction' => 'Ears up.', 'reason' => ''];
        FakeGenerator::$imageFails = true;
        try {
            $this->pet(['wish' => 'uši nahoru', 'image_left' => UploadedFile::fake()->image('left.jpg', 800, 600)])->assertCreated()->assertJsonPath('generation.status', 'done');
            $req = GenerationRequest::latest('id')->firstOrFail();
            $this->assertSame('model', $req->description['stage']);
            $this->assertStringContainsString('fake_image_failed', $req->description['clean_failed']);
        } finally {
            FakeGenerator::$imageFails = false;
        }
    }

    public function test_a_cleaning_refused_when_sent_still_makes_the_model_of_the_photo(): void
    {
        FakeAssistant::$answers['wish'] = ['ok' => true, 'instruction' => 'The belly plain and smooth.', 'reason' => ''];
        FakeGenerator::$imageRefused = true;
        try {
            // one photo (27 Nov 2026: Sára's generation failed whole on this)
            $this->pet(['wish' => 'je to fena'])->assertCreated()->assertJsonPath('generation.status', 'done');
            $req = GenerationRequest::latest('id')->firstOrFail();
            $this->assertSame('model', $req->description['stage']);
            $this->assertStringContainsString('content policy', $req->description['clean_failed']);

            // several sides: every one refused, the photos go as they are
            $this->pet(['wish' => 'je to fena', 'image_left' => UploadedFile::fake()->image('left.jpg', 800, 600)])->assertCreated()->assertJsonPath('generation.status', 'done');
            $req = GenerationRequest::latest('id')->firstOrFail();
            $this->assertSame('model', $req->description['stage']);
            $this->assertStringContainsString('content policy', $req->description['clean_failed']);

            // a cartoon is nothing without its picture: that one still fails
            $this->pet(['style' => 'cartoon'])->assertCreated()->assertJsonPath('generation.status', 'failed');
        } finally {
            FakeGenerator::$imageRefused = false;
        }
    }

    public function test_every_animal_keeps_its_sex_and_the_pages_offer_the_field(): void
    {
        foreach ([GenerateModel::CLEAN_PROMPT, GenerateModel::CLEAN_PET_OWNER_PROMPT, GenerateModel::CARTOON_PROMPT] as $prompt) {
            $this->assertStringContainsString('add nothing under the belly or between the hind legs', $prompt);
            $this->assertStringNotContainsString('genital', $prompt, 'Tripo refuses anatomical words (27 Nov 2026)');
        }
        $this->assertStringNotContainsString('hind legs', GenerateModel::CLEAN_BUST_PROMPT);
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $lang => $prefix) {
            app()->setLocale($lang);
            $this->get($prefix.'/tools/pet-figurine')->assertOk()->assertSee('name="wish"', false)->assertSee(__('pet.wish.ph'))->assertSee(__('figure.wish.hint'));
            $this->get($prefix.'/tools/figure')->assertOk()->assertSee('name="wish"', false)->assertSee(__('figure.wish.ph'));
            foreach (['figure.wish', 'figure.wish.optional', 'figure.wish.hint', 'figure.wish.ph', 'pet.wish.ph', 'figure.wish.rejected'] as $key) {
                $this->assertNotSame($key, __($key), $lang.' '.$key);
            }
        }
    }
}
