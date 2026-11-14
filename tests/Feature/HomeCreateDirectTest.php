<?php

namespace Tests\Feature;

use App\Domain\Farm\Wallet;
use App\Domain\Generation\GenerationService;
use App\Domain\Intake\IntakeDesign;
use App\Domain\Intake\IntakePrefill;
use App\Domain\Intake\IntakeRouter;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Ai\Assistant;
use App\Engines\Ai\ClaudeAssistant;
use App\Engines\Ai\FakeAssistant;
use App\Engines\Contracts\ModelGenerator;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\GenerationRequest;
use App\Models\Intake;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * One press of "Vytvořit" ends on a design (docs/AN.md §24). A tool the assistant is sure of opens right away with the
 * wish in its form (no card in between); a tool it is not sure of opens too, with a line saying so, while the free
 * design is off; a wish without a tool gets a free design from the model generator when that is switched on, inside
 * the counts (ten free without an account, ten more with one, then the price or nothing), else one honest card with a
 * request to a designer as the visitor's own choice. Nothing real is called: the assistant and the generator are fakes.
 */
class HomeCreateDirectTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        FakeAssistant::reset();
        $this->withoutMiddleware(ThrottleRequests::class);
        // a person's browser that reads Czech: no robot, no first-visit hop to /en
        $this->withHeaders(['User-Agent' => self::PHONE, 'Accept-Language' => 'cs']);
        config(['home.intake.spam.require_stamp' => false, 'engines.generator' => 'fake', 'farm.settings.intake_reply_hours' => 24]);
    }

    private function answer(string $route, ?string $tool = null, float $confidence = 0.9, array $params = [], array $more = []): array
    {
        return $more + [
            'route' => $route, 'tool_key' => $tool,
            'params' => $params + ['size_mm' => null, 'colors' => [], 'copies' => null, 'text' => null, 'place' => null, 'kind' => null, 'decor' => null, 'text2' => null, 'number' => null, 'shape' => null],
            'summary' => ['cs' => 'Věta asistenta.', 'en' => 'The assistant\'s sentence.', 'es' => 'La frase del asistente.'],
            'questions' => [], 'alternatives' => [], 'reason' => 'test', 'prompt_en' => '', 'photo' => 'none', 'confidence' => $confidence,
        ];
    }

    private function send(array $data): TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::PHONE])->post('/intake', $data, ['Accept' => 'application/json']);
    }

    private function photo(): UploadedFile
    {
        return new UploadedFile(base_path('tests/fixtures/intake/photo.jpg'), 'photo.jpg', 'image/jpeg', null, true);
    }

    private function shared(string $html): array
    {
        $this->assertSame(1, preg_match('/window\.MP_TOOL = JSON\.parse\(\'(.*?)\'\);|window\.MP_TOOL = (\{.*?\});<\/script>/s', $html, $m), 'the page hands its scripts the shared config');
        $json = isset($m[2]) && $m[2] !== '' ? $m[2] : json_decode('"'.$m[1].'"');

        return (array) json_decode((string) $json, true);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin->fresh();
    }

    private function designOn(array $more = []): void
    {
        IntakeDesign::save($more + ['enabled' => true, 'free_guest' => 10, 'free_user' => 10, 'price' => null, 'per_day' => 100, 'direct_confidence' => 0.6]);
    }

    public function test_a_clear_tool_opens_right_away_with_the_wish_in_its_form(): void
    {
        FakeAssistant::$answers['intake'] = $this->answer('tool', 'sign', 0.92, ['text' => 'Novákovi', 'size_mm' => 20, 'colors' => ['red', 'white']]);
        $r = $this->send(['text' => 'Červená cedulka na dveře s bílým nápisem Novákovi, písmo 2 cm, 2 kusy.'])->assertCreated();
        $token = $r->json('token');
        $r->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'sign')->assertJsonPath('unsure', false)->assertJsonPath('again', false);
        $this->assertSame(url('/tools/sign').'?intake='.$token, $r->json('go'), 'the tool opens by itself: no card with two buttons');
        $this->assertSame(2, $r->json('params.copies'), 'the words fill in what the model left out');

        // the tool's page: the form's own values, the line with what was understood, the way back
        $html = (string) $this->get('/tools/sign?intake='.$token)->assertOk()->getContent();
        $intake = $this->shared($html)['intake'];
        $this->assertEquals(['_qty' => '2', 'text_height' => 20, 'line1' => 'Novákovi', 'two_color' => true, 'part_colors' => ['plate' => '#d62828', 'text' => '#ffffff']], $intake['values'], 'the colours are the colours of the colour window (docs/U.md)');
        $this->assertSame(['text „Novákovi“', 'výška písma 20 mm', 'barvy: '.__('toolpage.color.basic.red').', '.__('toolpage.color.basic.white'), 'počet kusů: 2'], $intake['understood']);
        $this->assertFalse($intake['unsure']);
        $this->assertSame(url('/').'?wish='.$token, $intake['back']);
        $this->assertSame(__('router.got.back'), $intake['words']['back']);
        $this->assertSame('Chtěl jsem něco jiného', __('router.got.back', [], 'cs'));
        $this->assertSame(1, Event::where('type', 'intake_tool_opened')->count());

        // in English the line is English
        $en = $this->shared((string) $this->get('/en/tools/sign?intake='.$token)->assertOk()->getContent())['intake'];
        $this->assertSame('the text “Novákovi”', $en['understood'][0]);
        $this->assertSame('I wanted something else', $en['words']['back']);
    }

    public function test_a_photo_goes_to_the_tool_as_its_input_or_as_a_decoration(): void
    {
        FakeAssistant::$answers['intake'] = $this->answer('tool', 'coaster', 0.9, ['size_mm' => 100], ['photo' => 'picture']);
        $r = $this->send(['text' => 'Podtácek s naší fotkou, 10 cm', 'photo' => $this->photo()])->assertCreated();
        $this->assertSame(url('/tools/coaster').'?intake='.$r->json('token'), $r->json('go'));
        $intake = $this->shared((string) $this->get('/tools/coaster?intake='.$r->json('token'))->assertOk()->getContent())['intake'];
        $this->assertSame(url('/intake/'.$r->json('token').'/file'), $intake['file']);
        $this->assertContains(__('router.got.photo'), $intake['understood']);
        $this->assertSame(100, $intake['values']['width']);

        FakeAssistant::$answers['intake'] = $this->answer('tool', 'medallion', 0.9, ['decor' => 'photo', 'text' => 'Rex'], ['photo' => 'animal']);
        $r = $this->send(['text' => 'Medaile s reliéfem z fotky a jménem Rex', 'photo' => $this->photo()])->assertCreated();
        $intake = $this->shared((string) $this->get('/tools/medallion?intake='.$r->json('token'))->assertOk()->getContent())['intake'];
        $this->assertSame('photo', $intake['params']['decor']);
        $this->assertNotNull($intake['file']);
        $this->assertContains(__('router.got.photo_relief'), $intake['understood']);

        // the key ring opens its quick form, the one with the fields the values belong to
        FakeAssistant::$answers['intake'] = $this->answer('tool', 'keychain', 0.9, ['text' => 'Jana']);
        $r = $this->send(['text' => 'Klíčenka se jménem Jana'])->assertCreated();
        $this->assertSame(url('/tools/keychain').'?intake='.$r->json('token').'&form=1', $r->json('go'));
    }

    public function test_a_shaky_tool_still_opens_and_says_so_while_the_free_design_is_off(): void
    {
        FakeAssistant::$answers['intake'] = $this->answer('tool', 'charm', 0.5);
        $r = $this->send(['text' => 'Něco malého na klíče pro bráchu'])->assertCreated();
        $r->assertJsonPath('route', 'tool')->assertJsonPath('unsure', true);
        $this->assertSame(url('/tools/charm').'?intake='.$r->json('token'), $r->json('go'));
        $this->assertSame(0, GenerationRequest::count(), 'the switch is off: nothing is generated');
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertSame(IntakeDesign::OFF, $intake->answer['design_refused']);
        $page = $this->shared((string) $this->get('/tools/charm?intake='.$r->json('token'))->assertOk()->getContent())['intake'];
        $this->assertTrue($page['unsure']);
        $this->assertSame(__('router.got.unsure'), $page['words']['unsure']);
    }

    public function test_without_a_tool_and_with_the_free_design_off_there_is_one_honest_card(): void
    {
        FakeAssistant::$answers['intake'] = $this->answer('shape', null, 0.8, [], ['alternatives' => ['charm', 'sign'], 'prompt_en' => 'a headband with two devil horns']);
        $r = $this->send(['text' => 'Čelenka s rohy na Mikuláše'])->assertCreated();
        $r->assertJsonPath('route', 'human')->assertJsonPath('wants_shape', true)->assertJsonPath('go', null)
            ->assertJsonPath('actions.start_design', null)->assertJsonPath('actions.design', null)->assertJsonPath('design_note', null);
        $this->assertSame(['charm', 'sign'], array_column($r->json('others'), 'key'), 'the tools that would also do, each with its address');
        $this->assertSame(url('/tools/charm').'?intake='.$r->json('token'), $r->json('others.0.url'));
        $this->assertNotNull($r->json('actions.request'), 'a person is there, as the visitor\'s own choice');
        $this->assertSame(0, GenerationRequest::count());
    }

    public function test_without_a_tool_a_free_design_is_made_from_the_words_when_it_is_switched_on(): void
    {
        $this->designOn();
        FakeAssistant::$answers['intake'] = $this->answer('shape', null, 0.8, ['size_mm' => 150], ['alternatives' => ['charm'], 'prompt_en' => 'a small dragon statuette sitting on a rock']);
        $r = $this->send(['text' => 'Soška draka na poličku, asi 15 cm'])->assertCreated();
        $r->assertJsonPath('route', 'design')->assertJsonPath('fit', false);
        $generation = GenerationRequest::firstOrFail();
        $this->assertSame(url('/').'?design='.$generation->token.'&wish='.$r->json('token'), $r->json('go'), 'the design opens by itself');
        $this->assertSame('text', $generation->type);
        $this->assertSame('a small dragon statuette sitting on a rock', $generation->prompt, 'the generator gets the thing in plain English');
        $this->assertSame(150, $generation->target_mm);
        $this->assertSame($r->json('token'), $generation->description['intake']);
        $this->assertStringContainsString(__('router.design.summary'), $r->json('summary'));

        // the fake generator finishes at once (a sync queue): the model went through the same pipeline as an upload
        $this->assertSame('done', $generation->fresh()->status);
        $file = ModelFile::findOrFail($generation->fresh()->result_model_file_id);
        $this->assertSame('generated', $file->origin);
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $show = $this->getJson('/api/generate/'.$generation->token)->assertOk();
        $this->assertSame($file->uuid, $show->json('generation.file.uuid'));
        $this->assertTrue($this->getJson('/api/files/'.$file->uuid)->assertOk()->json('file.generation.refinable'), 'it can be changed in words');

        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertSame(['generation' => $generation->token, 'by' => 'auto', 'from' => 'text'], array_intersect_key($intake->answer['design'], array_flip(['generation', 'by', 'from'])));
        $this->assertSame('human', $intake->answer['design']['was']['route']);
        $this->assertSame(1, Event::where('type', 'intake_designed')->count());

        // the calculator's page carries the design's line; the way back gives the words and the other ways
        $home = (string) $this->get('/?design='.$generation->token.'&wish='.$r->json('token'))->assertOk()->getContent();
        foreach (['id="design-line"', 'data-design-back', 'data-design-fit', e(__('router.design.note')), e(__('router.design.waiting')), 'id="design-failed"'] as $needle) {
            $this->assertStringContainsString($needle, $home);
        }
        $again = $this->getJson('/intake/'.$r->json('token'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $again->assertJsonPath('again', true)->assertJsonPath('go', null)->assertJsonPath('text', 'Soška draka na poličku, asi 15 cm')->assertJsonPath('actions.design', $r->json('go'));
        $this->assertSame(['charm'], array_column($again->json('others'), 'key'));
    }

    public function test_a_thing_that_is_worn_never_gets_a_promise_that_it_fits(): void
    {
        $this->designOn();
        FakeAssistant::$answers['intake'] = $this->answer('shape', null, 0.8, [], ['prompt_en' => 'a headband with two devil horns']);
        $r = $this->send(['text' => 'Čelenka s rohy na Mikuláše'])->assertCreated()->assertJsonPath('route', 'design')->assertJsonPath('fit', true);
        $this->assertStringContainsString(__('router.design.fit'), $r->json('summary'));
        foreach (['cs', 'en', 'es'] as $lang) {
            foreach (['router.design.summary', 'router.design.note', 'router.design.honest'] as $key) {
                $this->assertDoesNotMatchRegularExpression('/bude sedět|padne|will fit\b(?! )|quedará bien\.$/u', __($key, [], $lang), "$lang $key promises no fit");
            }
        }
        $this->assertTrue(IntakeDesign::mustFit('a headband for my daughter'));
        $this->assertFalse(IntakeDesign::mustFit('soška draka'));
    }

    public function test_a_shaky_tool_becomes_a_free_design_when_that_is_on_and_a_sure_one_does_not(): void
    {
        $this->designOn();
        FakeAssistant::$answers['intake'] = $this->answer('tool', 'charm', 0.5, [], ['prompt_en' => 'a small rocket for a keyring']);
        $r = $this->send(['text' => 'Malá raketa na klíče pro bráchu'])->assertCreated()->assertJsonPath('route', 'design');
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertSame('charm', $intake->answer['design']['was']['tool_key']);
        $this->assertSame(['charm'], array_column($this->getJson('/intake/'.$r->json('token'))->json('others'), 'key'), 'the way back offers the tool it was near');

        FakeAssistant::$answers['intake'] = $this->answer('tool', 'charm', 0.6);
        $this->send(['text' => 'Přívěsek na klíče z kresby'])->assertCreated()->assertJsonPath('route', 'tool');
        $this->assertSame(1, GenerationRequest::count(), 'at the threshold the tool opens, nothing is generated');

        // the admin moves the threshold without a deploy
        $this->designOn(['direct_confidence' => 0.8]);
        $this->assertSame(0.8, IntakeDesign::directConfidence());
        FakeAssistant::$answers['intake'] = $this->answer('tool', 'charm', 0.7, [], ['prompt_en' => 'a small rocket with fins']);
        $this->send(['text' => 'Raketa s křidélky na klíče'])->assertCreated()->assertJsonPath('route', 'design');
    }

    public function test_a_part_with_dimensions_stays_with_a_person_and_the_free_design_is_a_press(): void
    {
        $this->designOn();
        FakeAssistant::$answers['intake'] = $this->answer('human', null, 0.9);
        $r = $this->send(['text' => 'Držák na Alexu pod linku, 100 x 60 mm, aby to pasovalo.'])->assertCreated();
        $r->assertJsonPath('route', 'human')->assertJsonPath('go', null);
        $this->assertSame(0, GenerationRequest::count(), 'a part that has to fit is no free design by itself');
        $this->assertSame(url('/intake/'.$r->json('token').'/design'), $r->json('actions.start_design'));

        $d = $this->postJson('/intake/'.$r->json('token').'/design')->assertOk()->assertJsonPath('route', 'design')->assertJsonPath('fit', true);
        $this->assertSame(url('/').'?design='.GenerationRequest::firstOrFail()->token.'&wish='.$r->json('token'), $d->json('go'));
        $this->assertSame('button', Intake::where('token', $r->json('token'))->firstOrFail()->answer['design']['by']);
        // a second press starts nothing new
        $this->postJson('/intake/'.$r->json('token').'/design')->assertOk()->assertJsonPath('route', 'design');
        $this->assertSame(1, GenerationRequest::count());

        // a failed call of the assistant ends with a person too, never with a paid design by itself
        FakeAssistant::$fail = 'timeout';
        $this->send(['text' => 'Soška draka na poličku'])->assertCreated()->assertJsonPath('route', 'human');
        $this->assertSame(1, GenerationRequest::count());
    }

    public function test_a_photo_of_a_person_is_never_the_source_of_a_free_design(): void
    {
        $this->designOn();
        FakeAssistant::$answers['intake'] = $this->answer('shape', null, 0.8, [], ['prompt_en' => 'a garden gnome with a fishing rod', 'photo' => 'person']);
        $r = $this->send(['text' => 'Trpaslík s udicí jako můj děda', 'photo' => $this->photo()])->assertCreated()->assertJsonPath('route', 'design');
        $generation = GenerationRequest::firstOrFail();
        $this->assertSame('text', $generation->type, 'the words alone');
        $this->assertNull($generation->image_path);
        $this->assertSame('text', Intake::where('token', $r->json('token'))->firstOrFail()->answer['design']['from']);

        FakeAssistant::$answers['intake'] = $this->answer('shape', null, 0.8, [], ['prompt_en' => 'a toy excavator', 'photo' => 'object']);
        $r = $this->send(['text' => 'Hračka bagr podle téhle fotky', 'photo' => $this->photo()])->assertCreated()->assertJsonPath('route', 'design');
        $generation = GenerationRequest::latest('id')->firstOrFail();
        $this->assertSame('image', $generation->type, 'a photo of the thing itself is the source');
        $this->assertSame('photo', Intake::where('token', $r->json('token'))->firstOrFail()->answer['design']['from']);
        $this->assertNull($generation->fresh()->image_path, 'the copy for the run is gone when the model is made');
        $this->assertTrue(Intake::where('token', $r->json('token'))->firstOrFail()->hasPhoto(), 'the intake keeps its own for 30 days');
        $this->assertTrue($this->getJson('/api/files/'.$generation->fresh()->resultFile->uuid)->json('file.generation.refinable'), 'it still has its words');

        // a photo alone, of a person: nothing to make a design of; the card says where a bust is made
        FakeAssistant::$answers['intake'] = $this->answer('human', null, 0.8, [], ['photo' => 'person']);
        $r = $this->send(['photo' => $this->photo()])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('actions.start_design', null);
    }

    public function test_ten_free_without_an_account_ten_more_with_one_then_the_price_or_nothing(): void
    {
        $this->designOn(['free_guest' => 2, 'free_user' => 2]);
        $n = 0;
        $wish = function () use (&$n): TestResponse {
            $n++;
            FakeAssistant::$answers['intake'] = $this->answer('shape', null, 0.8, [], ['prompt_en' => 'a statuette number '.$n]);

            return $this->send(['text' => 'Soška číslo '.$n.' na poličku'])->assertCreated();
        };
        // the browser keeps its cookie from the first answer on, as a browser does
        $first = $wish()->assertJsonPath('route', 'design');
        $cookie = collect($first->headers->getCookies())->first(fn ($c) => $c->getName() === AnonymousSession::COOKIE);
        $this->assertNotNull($cookie);
        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $cookie->getValue())->withCredentials();   // JSON calls carry it too
        $session = AnonymousSession::where('token', $cookie->getValue())->firstOrFail();
        $wish()->assertJsonPath('route', 'design');
        $this->assertSame(2, IntakeDesign::used(null, $session->id, null), 'counted by the browser\'s cookie alone too');

        // the third without an account: no run, the sentence says to sign in
        $r = $wish()->assertJsonPath('route', 'human')->assertJsonPath('go', null)->assertJsonPath('actions.start_design', null)->assertJsonPath('actions.pay_design', null);
        $this->assertSame(__('router.design.guest_limit', ['n' => 2, 'm' => 2]), $r->json('design_note'));
        $this->assertSame(url('/login'), $r->json('login'));
        $this->assertSame(2, GenerationRequest::count());
        $this->assertSame(IntakeDesign::GUEST_LIMIT, Intake::where('token', $r->json('token'))->firstOrFail()->answer['design_refused']);
        $this->postJson('/intake/'.$r->json('token').'/design')->assertStatus(422)->assertJsonPath('errors.route.0', __('router.design.guest_limit', ['n' => 2, 'm' => 2]));

        // "change it in words" is a use like any other, and says the same
        $first = GenerationRequest::firstOrFail();
        $refine = $this->postJson('/api/generate/'.$first->token.'/refine', ['instruction' => 'longer tail'])->assertStatus(429);
        $refine->assertJsonPath('error', IntakeDesign::GUEST_LIMIT)->assertJsonPath('message', __('router.design.guest_limit', ['n' => 2, 'm' => 2]))->assertJsonPath('can_pay', false);
        $this->assertSame(url('/login'), $refine->json('login_url'));

        // signed in, in the same browser: what was used counts on, two more are free
        $user = User::factory()->create();
        $this->actingAs($user);
        $again = $this->getJson('/intake/'.$r->json('token'))->assertOk()->assertJsonPath('design_note', null)->assertJsonPath('login', null);
        $this->assertSame(url('/intake/'.$r->json('token').'/design'), $again->json('actions.start_design'));
        $this->postJson('/intake/'.$r->json('token').'/design')->assertOk()->assertJsonPath('route', 'design');
        $this->postJson('/api/generate/'.$first->token.'/refine', ['instruction' => 'longer tail'])->assertCreated();
        $this->assertSame(4, GenerationRequest::count());
        $this->assertSame(4, IntakeDesign::used('127.0.0.1', $session->id, $user->id), 'two of the account, two of the browser before it');
        $this->assertSame(2, IntakeDesign::used('127.0.0.1', null, $user->id), 'from another browser the account has its own two');

        // the fifth: no price is set, so nothing is sold and the sentence says so
        $r = $wish()->assertJsonPath('route', 'human')->assertJsonPath('actions.pay_design', null)->assertJsonPath('login', null);
        $this->assertSame(__('router.design.user_limit'), $r->json('design_note'));
        $this->postJson('/api/generate/'.$first->token.'/refine', ['instruction' => 'shorter tail'])->assertStatus(429)->assertJsonPath('error', IntakeDesign::USER_LIMIT)->assertJsonPath('can_pay', false);

        // a price is set: the paid press is offered, and only the paid press charges
        $this->designOn(['free_guest' => 2, 'free_user' => 2, 'price' => 12]);
        $again = $this->getJson('/intake/'.$r->json('token'))->assertOk();
        $this->assertSame(url('/intake/'.$r->json('token').'/design'), $again->json('actions.pay_design.url'));
        $this->assertSame("12\u{00A0}Kč", $again->json('actions.pay_design.price'));
        $this->assertStringContainsString("12\u{00A0}Kč", $again->json('design_note'));
        $this->postJson('/intake/'.$r->json('token').'/design')->assertStatus(422);
        $this->postJson('/intake/'.$r->json('token').'/design', ['pay' => true])->assertStatus(422)->assertJsonPath('errors.route.0', __('router.design.credit', ['price' => "12\u{00A0}Kč", 'missing' => "12\u{00A0}Kč"]));
        $wallet = app(Wallet::class);
        $wallet->adjust($user, 30, 'test', $user->id);
        $this->postJson('/intake/'.$r->json('token').'/design', ['pay' => true])->assertOk()->assertJsonPath('route', 'design');
        $this->assertSame(18.0, $wallet->balance($user)->amount);
        $this->assertSame(12.0, (float) GenerationRequest::latest('id')->first()->paid_credit);
        $this->assertSame(4, IntakeDesign::used('127.0.0.1', $session->id, $user->id), 'a paid run is no free one');
        $paid = $this->postJson('/api/generate/'.$first->token.'/refine', ['instruction' => 'bigger wings'])->assertStatus(429)->assertJsonPath('error', IntakeDesign::PAY)->assertJsonPath('can_pay', true);
        $this->assertSame("12\u{00A0}Kč", $paid->json('price'));
        $this->postJson('/api/generate/'.$first->token.'/refine', ['instruction' => 'bigger wings', 'pay' => true])->assertCreated();
        $this->assertSame(6.0, $wallet->balance($user)->amount);
    }

    public function test_the_days_cap_a_failed_run_a_copy_and_the_admin(): void
    {
        $this->designOn(['free_guest' => 5, 'per_day' => 2]);
        $n = 0;
        $wish = function (?string $prompt = null) use (&$n): TestResponse {
            $n++;
            FakeAssistant::$answers['intake'] = $this->answer('shape', null, 0.8, [], ['prompt_en' => $prompt ?? 'a figurine number '.$n]);

            return $this->send(['text' => 'Figurka číslo '.$n.' na poličku'])->assertCreated();
        };
        $wish('a figurine of a fox')->assertJsonPath('route', 'design');
        // the same thing asked again is a copy of the first result: free, instant, not counted
        $wish('a figurine of a fox')->assertJsonPath('route', 'design');
        $this->assertSame(1, IntakeDesign::startedToday());
        $this->assertSame(1, IntakeDesign::used('127.0.0.1', null, null));
        // a run that failed gave the visitor nothing: it is not counted either
        $wish()->assertJsonPath('route', 'design');
        GenerationRequest::latest('id')->first()->update(['status' => 'failed']);
        $this->assertSame(1, IntakeDesign::startedToday());
        $wish()->assertJsonPath('route', 'design');
        $r = $wish()->assertJsonPath('route', 'human');
        $this->assertSame(__('router.design.day_limit'), $r->json('design_note'));
        $this->assertSame(IntakeDesign::DAY_LIMIT, IntakeDesign::limit('10.0.0.9', null, null), 'the cap holds everybody');

        // the admin tries freely
        $this->actingAs($this->admin());
        $wish()->assertJsonPath('route', 'design');
    }

    public function test_a_held_wish_a_closed_farm_and_too_few_words_start_nothing(): void
    {
        $this->designOn();
        // advertising is held before anything is asked or generated
        $this->send(['text' => 'Great post! Visit our website https://spam.example/a https://spam.example/b'])->assertCreated()->assertJsonPath('held', 'held')->assertJsonPath('go', null)->assertJsonPath('actions.start_design', null);
        $this->assertSame([], FakeAssistant::$calls);
        $held = Intake::latest('id')->firstOrFail();
        $this->postJson('/intake/'.$held->token.'/design')->assertStatus(422);
        $this->getJson('/intake/'.$held->token)->assertNotFound();

        // one short word is nothing to make a design of
        $this->app->instance(Assistant::class, new ClaudeAssistant(['api_key' => '']));
        $r = $this->send(['text' => 'mia'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('actions.start_design', null);
        $this->assertSame(IntakeDesign::WORDS, Intake::where('token', $r->json('token'))->firstOrFail()->answer['design_refused']);

        // a closed farm takes no orders: nothing is generated, the tools still open
        config(['farm.open' => false, 'farm.state' => 'closed']);
        $r = $this->send(['text' => 'Soška draka na stůl'])->assertCreated()->assertJsonPath('route', 'human');
        $this->assertSame(IntakeDesign::CLOSED, Intake::where('token', $r->json('token'))->firstOrFail()->answer['design_refused']);
        $r = $this->send(['text' => 'Klíčenka se jménem Jana'])->assertCreated()->assertJsonPath('route', 'tool');
        $this->assertNotNull($r->json('go'));
        $this->assertSame(0, GenerationRequest::count());
    }

    public function test_keywords_alone_send_a_wish_nothing_names_to_the_free_design(): void
    {
        $this->designOn();
        $this->app->instance(Assistant::class, new ClaudeAssistant(['api_key' => '']));
        $r = $this->send(['text' => 'Maska lišky na karneval'])->assertCreated()->assertJsonPath('route', 'design')->assertJsonPath('fit', true);
        $this->assertSame('Maska lišky na karneval', GenerationRequest::firstOrFail()->prompt, 'without a model the visitor\'s own words go to the generator');
        // a part stays with a person
        $this->send(['text' => 'Náhradní kolečko do myčky, průměr 3 cm'])->assertCreated()->assertJsonPath('route', 'human');
        $this->assertSame(1, GenerationRequest::count());
    }

    public function test_the_golden_cases_end_on_a_design_after_one_press(): void
    {
        $cases = json_decode((string) file_get_contents(base_path('tests/fixtures/intake/cases.json')), true);
        $this->app->instance(Assistant::class, new ClaudeAssistant(['api_key' => '']));
        $count = ['tool' => 0, 'design' => 0, 'card' => 0];
        foreach ([false, true] as $on) {
            IntakeDesign::save(['enabled' => $on, 'free_guest' => 1000, 'free_user' => 0, 'per_day' => 5000, 'direct_confidence' => 0.6]);
            foreach ($cases as $i => $case) {
                if ($case['text'] === '' || $case['photo']) {
                    // a photo needs Python to be stored; the decision of these cases is covered by IntakeTest, the flow by the tests above
                    $d = IntakeRouter::guess($case['text'], (bool) $case['photo'], 'cs');
                    $this->assertSame($case['route'] === 'tool', $d['route'] === 'tool' && $d['confidence'] >= IntakeDesign::directConfidence(), "#$i");

                    continue;
                }
                $r = $this->send(['text' => $case['text']])->assertCreated();
                if ($case['route'] === 'tool') {
                    $this->assertStringContainsString('?intake='.$r->json('token'), (string) $r->json('go'), "#$i {$case['text']}: the tool opens by itself");
                    $this->assertFalse($r->json('unsure'), "#$i");
                    $count['tool'] += $on ? 1 : 0;
                } elseif ($on && ! IntakeRouter::technical($case['text'])) {
                    $r->assertJsonPath('route', 'design');
                    $count['design']++;
                } else {
                    $r->assertJsonPath('go', null)->assertJsonPath('route', 'human');
                    $count['card'] += $on ? 1 : 0;
                }
            }
        }
        $this->assertGreaterThanOrEqual(8, $count['tool']);
        $this->assertGreaterThanOrEqual(1, $count['card'], 'a part with dimensions stays with a person');
    }

    public function test_every_tool_of_the_catalogue_takes_what_the_words_say(): void
    {
        $cases = json_decode((string) file_get_contents(base_path('tests/fixtures/intake/direct.json')), true);
        $seen = [];
        foreach ($cases as $i => $case) {
            $d = IntakeRouter::guess($case['text'], (bool) $case['photo'], 'cs');
            $this->assertSame('tool', $d['route'], "#$i {$case['text']}");
            $this->assertSame($case['tool'], $d['tool_key'], "#$i {$case['text']}");
            // as the tool's page gets them (IntakeService::handoff): the words read for this tool, the wish itself for a picture of the library
            $params = IntakeRouter::params(IntakePrefill::complete($d['params'], $case['text'], $case['tool']), $case['tool']);
            foreach ((array) ($case['params'] ?? []) as $k => $v) {
                $this->assertSame($v, $params[$k] ?? null, "#$i param $k");
            }
            $values = IntakePrefill::values($case['tool'], $params, $case['text'], (bool) $case['photo']);
            ksort($values);
            $want = $case['values'];
            ksort($want);
            $this->assertSame($want, $values, "#$i {$case['text']}");
            $this->assertSame($case['got'], array_map(fn ($s) => str_replace([__('toolpage.color.basic.red'), __('toolpage.color.basic.white'), __('toolpage.color.basic.black'), __('toolpage.color.basic.blue')], ['červená', 'bílá', 'černá', 'modrá'], $s),
                IntakePrefill::understood($case['tool'], $params, $values, (bool) $case['photo'], 'cs')), "#$i {$case['text']}");
            $seen[$case['tool']] = true;
        }
        $this->assertSame([], array_diff(array_keys(IntakeRouter::TOOLS), array_keys($seen)), 'every tool of the catalogue has a case');

        // the values are the form's own: every key is a field, a text, a choice or a flag of that tool
        foreach (array_keys(IntakePrefill::KIND) as $tool) {
            $kind = IntakePrefill::KIND[$tool];
            $values = IntakePrefill::values($tool, ['size_mm' => 5000, 'text' => str_repeat('Ž', 80), 'text2' => '2026', 'number' => '7', 'shape' => 'heart', 'colors' => ['red', 'white', 'nonsense'], 'colors_n' => 99, 'copies' => 5000, 'hang' => 'ribbon', 'material' => 'PLA']);
            foreach ($values as $key => $value) {
                if (in_array($key, ['_qty', '_material', '_plain', 'preset', 'part_colors', 'artwork'], true)) {
                    continue;
                }
                $field = ParametricGenerator::FIELDS[$kind][$key] ?? null;
                $text = ParametricGenerator::TEXTS[$kind][$key] ?? null;
                $choice = ParametricGenerator::CHOICES[$kind][$key] ?? null;
                $flag = in_array($key, ParametricGenerator::FLAGS[$kind] ?? [], true);
                $this->assertTrue($field !== null || $text !== null || $choice !== null || $flag, "$tool: $key is a part of its form");
                if ($field) {
                    $this->assertTrue($value >= $field[0] && $value <= $field[1], "$tool: $key inside its limits");
                }
                if ($text) {
                    $this->assertLessThanOrEqual($text[0], mb_strlen((string) $value), "$tool: $key no longer than its field");
                }
                if ($choice) {
                    $this->assertContains($value, $choice, "$tool: $key is one of its choices");
                }
            }
            $this->assertSame('1000', $values['_qty']);
        }
    }

    public function test_the_prompt_sends_a_thing_without_a_tool_to_a_free_design_and_the_schema_carries_the_form(): void
    {
        $system = IntakeRouter::system();
        $this->assertStringContainsString('a 3D generator makes a first free design', $system);
        $this->assertStringNotContainsString('has to be modelled from the photo by a person', $system);
        $this->assertStringContainsString('never promise that a thing to wear or a thing that has to fit will fit', $system);
        $this->assertStringContainsString('`prompt_en`', $system);
        $schema = IntakeRouter::schema();
        foreach (['text2', 'number', 'shape'] as $key) {
            $this->assertSame(['type' => ['string', 'null']], $schema['properties']['params']['properties'][$key]);
        }
        $this->assertSame(['type' => 'string'], $schema['properties']['prompt_en']);
        $this->assertSame(IntakeRouter::PHOTO_SUBJECTS, $schema['properties']['photo']['enum']);
        $this->assertLessThanOrEqual(16, IntakeRouter::unionCount());
        $this->assertContains('prompt_en', $schema['required']);
    }

    public function test_the_home_page_and_the_admin_page(): void
    {
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $lang => $prefix) {
            $html = (string) $this->get($prefix.'/')->assertOk()->getContent();
            $this->assertStringContainsString('data-show-url="', $html, $lang);
            $this->assertStringContainsString('__TOKEN__', $html, $lang);
            $this->assertStringContainsString(e(__('router.paths.shape.title', [], $lang)), $html, "$lang: the switch is off, the second way is the designer's");
            $this->assertStringNotContainsString(e(__('router.paths.shape.title.free', [], $lang)), $html, $lang);
            preg_match('/data-i18n="([^"]*)"/', $html, $m);
            $words = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
            $this->assertSame(__('router.act.rather_designer', [], $lang), $words['router.act.rather_human'], "$lang: a person is the visitor's own choice, and the button says who");
            foreach (['router.opening', 'router.again.lead', 'router.act.free_design', 'router.act.pay_design', 'router.act.login', 'router.design.honest'] as $key) {
                $this->assertNotSame($key, $words[$key] ?? $key, "$lang $key");
            }
        }
        $this->designOn();
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('router.paths.shape.title.free')), $html);
        $this->assertStringContainsString(e(__('router.paths.shape.text.free')), $html);

        // the way back is counted
        $this->postJson('/intake/event', ['type' => 'again'])->assertNoContent();
        $this->assertSame(1, Event::where('type', 'intake_again')->count());

        // the admin's page: the switch, the counts, the price, the confidence
        $this->get('/admin/intake/design')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/intake/design')->assertRedirect(route('account'));
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/intake/design')->assertOk()->assertSee('Volný návrh z úvodní strany')->assertSee('name="free_guest"', false)->assertSee('name="price"', false);
        $this->actingAs($admin)->post('/admin/intake/design', ['enabled' => '0', 'free_guest' => 7, 'free_user' => 3, 'price' => '', 'per_day' => 40, 'direct_confidence' => 0.7])->assertRedirect('/admin/intake/design');
        $this->assertSame(['enabled' => false, 'free_guest' => 7, 'free_user' => 3, 'price' => null, 'per_day' => 40, 'direct_confidence' => 0.7], array_diff_key(IntakeDesign::settings(), ['target_mm' => 1]));
        $this->actingAs($admin)->post('/admin/intake/design', ['enabled' => '1', 'free_guest' => 10, 'free_user' => 10, 'price' => '19.5', 'per_day' => 100, 'direct_confidence' => 0.6])->assertRedirect();
        $this->assertSame(19.5, IntakeDesign::settings()['price']);
        $this->assertTrue(IntakeDesign::settings()['enabled']);
        $this->actingAs($admin)->get('/admin/intake')->assertOk()->assertSee('Volný návrh');
    }

    public function test_the_defaults_are_off_ten_and_ten_and_no_price(): void
    {
        $set = IntakeDesign::settings();
        $this->assertFalse($set['enabled'], 'nothing is generated until the admin switches it on');
        $this->assertSame([10, 10, null], [$set['free_guest'], $set['free_user'], $set['price']]);
        $this->assertSame(0.6, $set['direct_confidence']);
        $this->assertSame(IntakeRouter::DIRECT_CONFIDENCE, $set['direct_confidence']);
        $this->assertFalse(app(IntakeDesign::class)->enabled());
        // switched on without a generator: still nothing
        config(['engines.generator' => 'null']);
        foreach ([ModelGenerator::class, GenerationService::class, IntakeDesign::class] as $made) {
            $this->app->forgetInstance($made);
        }
        $this->designOn();
        $this->assertFalse(app(IntakeDesign::class)->enabled());
    }
}
