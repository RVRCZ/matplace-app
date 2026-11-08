<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Intake\IntakeRouter;
use App\Engines\Ai\Assistant;
use App\Engines\Ai\ClaudeAssistant;
use App\Engines\Ai\FakeAssistant;
use App\Engines\Repair\PythonTool;
use App\Mail\FarmAdminAlert;
use App\Mail\IntakeReceived;
use App\Mail\PlainMessage;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\Intake;
use App\Models\IntakeRequest;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The home page's router (TODO #78, docs/AN.md §22 B, C, E, F, G): a photo, a file or words go in; the assistant's
 * answer (faked) becomes a tool with its parameters, a person, or the calculator; a low confidence, a shape, a broken
 * answer or no answer at all end with a person and a sentence, never an empty page. HEIC becomes a JPEG through
 * Python; without Python a JPEG still goes through. Five an hour per session. A request leaves an e-mail and mails
 * the operator under its own switch. Photos go after 30 days. Nothing real is called.
 */
class IntakeTest extends TestCase
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
        config(['farm.settings.admin_email' => 'farm@example.com', 'farm.settings.intake_reply_hours' => 24]);
    }

    /** The answer a tool comes out of. */
    private function toolAnswer(string $tool = 'figure', float $confidence = 0.92, array $params = []): array
    {
        return [
            'route' => 'tool', 'tool_key' => $tool,
            'params' => $params + ['size_mm' => 120, 'colors' => ['white'], 'copies' => null, 'text' => null, 'place' => null, 'kind' => 'bust'],
            'summary' => ['cs' => 'Na fotce je člověk, navrhuji bustu 12 cm.', 'en' => 'A person: a bust of 12 cm.', 'es' => 'Una persona: un busto de 12 cm.'],
            'questions' => ['Jakou barvu?'], 'confidence' => $confidence,
        ];
    }

    private function send(array $data, array $headers = []): TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::PHONE] + $headers)->post('/intake', $data, ['Accept' => 'application/json']);
    }

    private function photo(string $name = 'photo.jpg'): UploadedFile
    {
        return new UploadedFile(base_path('tests/fixtures/intake/'.$name), $name, $name === 'photo.heic' ? 'image/heic' : 'image/jpeg', null, true);
    }

    /** Python with Pillow answers the photo tool; otherwise the tests of the picture are skipped. */
    private function needsPython(bool $heif = false): void
    {
        try {
            $r = app(PythonTool::class)->runScript('photo_tool.py', ['stats', base_path('tests/fixtures/intake/'.($heif ? 'photo.heic' : 'photo.jpg')), '-'], 60);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Python with Pillow is not available: '.$e->getMessage());
        }
        if (empty($r['ok'])) {
            $this->markTestSkipped($heif ? 'pillow_heif is not installed.' : 'Pillow cannot read the fixture.');
        }
    }

    public function test_words_alone_are_decided_by_the_assistant_and_a_tool_comes_back(): void
    {
        FakeAssistant::$answers['intake'] = $this->toolAnswer();
        $r = $this->send(['text' => 'Busta mého táty, asi 12 cm.'])->assertCreated();
        $r->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'figure')->assertJsonPath('tool', __('tools.figure.title'))
            ->assertJsonPath('params.size_mm', 120)->assertJsonPath('params.kind', 'bust')->assertJsonPath('params.colors.0', 'white')
            ->assertJsonPath('summary', 'Na fotce je člověk, navrhuji bustu 12 cm.')->assertJsonPath('questions.0', 'Jakou barvu?')->assertJsonPath('hours', 24)
            ->assertJsonPath('has_photo', false)->assertJsonPath('file', null);
        $token = $r->json('token');
        $this->assertSame(24, strlen($token));
        $this->assertSame(url('/tools/figure').'?intake='.$token, $r->json('actions.tool'));
        $this->assertSame(url('/intake/'.$token.'/request'), $r->json('actions.request'));

        $intake = Intake::where('token', $token)->firstOrFail();
        $this->assertSame('text', $intake->kind);
        $this->assertSame('tool', $intake->route);
        $this->assertSame('fake', $intake->model);
        $this->assertSame(50, $intake->tokens_out);
        $this->assertNotNull($intake->anonymous_session_id);
        $this->assertSame('Busta mého táty, asi 12 cm.', $intake->text);
        $this->assertSame(0.92, $intake->confidence);
        // the assistant got the words as data and the catalogue of tools, in the visitor's language
        $call = FakeAssistant::$calls[0];
        $this->assertSame('intake', $call['kind']);
        $this->assertStringContainsString("<words>\nBusta mého táty, asi 12 cm.\n</words>", $call['user']);
        $this->assertStringContainsString('<lang>cs</lang>', $call['user']);
        $this->assertStringContainsString('figure | a photo of a person', $call['user']);
        $this->assertSame([], $call['images']);
        $this->assertSame('intake', $call['context']['subject_type']);
        // measured: sent and decided, with numbers and codes only
        $this->assertSame(['kind' => 'text', 'has_text' => true], Event::where('type', 'intake_submitted')->firstOrFail()->meta);
        $routed = Event::where('type', 'intake_routed')->firstOrFail()->meta;
        $this->assertSame('tool', $routed['route']);
        $this->assertSame('figure', $routed['tool']);
        $this->assertSame(0.92, $routed['confidence']);
        $this->assertArrayHasKey('ms', $routed);
        $this->assertArrayHasKey('tokens', $routed);
    }

    public function test_the_language_of_the_page_is_the_language_of_the_answer(): void
    {
        FakeAssistant::$answers['intake'] = $this->toolAnswer();
        $this->withHeaders(['User-Agent' => self::PHONE])->post('/en/intake', ['text' => 'A bust of my dad'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('summary', 'A person: a bust of 12 cm.')->assertJsonPath('tool', __('tools.figure.title', [], 'en'));
        $this->assertStringContainsString('<lang>en</lang>', FakeAssistant::$calls[0]['user']);
        $this->assertSame('en', Intake::firstOrFail()->locale);
        $this->assertStringContainsString('/en/tools/figure?intake=', Intake::firstOrFail()->token ? url('/en/tools/figure').'?intake=' : '');
    }

    public function test_a_photo_is_made_upright_and_small_and_handed_to_the_assistant(): void
    {
        $this->needsPython();
        FakeAssistant::$answers['intake'] = $this->toolAnswer();
        $r = $this->send(['photo' => $this->photo(), 'text' => 'Busta'])->assertCreated()->assertJsonPath('has_photo', true);
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertSame('photo', $intake->kind);
        $disk = Storage::disk('local');
        $this->assertSame('intake/'.$intake->token.'/original.jpg', $intake->photo_path);
        $this->assertSame('intake/'.$intake->token.'/model.jpg', $intake->photo_model_path);
        $disk->assertExists($intake->photo_path);
        $disk->assertExists($intake->photo_model_path);
        // the fixture lies on its side with EXIF orientation 6: upright it is taller than wide
        [$w, $h] = getimagesize($disk->path($intake->photo_model_path));
        $this->assertSame([48, 64], [$w, $h]);
        $this->assertSame([$disk->path($intake->photo_model_path)], FakeAssistant::$calls[0]['images']);
        $this->assertStringContainsString('A photo is attached.', FakeAssistant::$calls[0]['user']);
        // the picture is served by the token only, and never indexed
        $this->get('/intake/'.$intake->token.'/file')->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(['kind' => 'photo', 'has_text' => true], Event::where('type', 'intake_submitted')->firstOrFail()->meta);
    }

    public function test_a_heic_photo_from_a_phone_becomes_a_jpeg(): void
    {
        $this->needsPython(true);
        FakeAssistant::$answers['intake'] = $this->toolAnswer();
        $r = $this->send(['photo' => $this->photo('photo.heic')])->assertCreated();
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertSame('intake/'.$intake->token.'/original.heic', $intake->photo_path);
        $path = Storage::disk('local')->path((string) $intake->photo_model_path);
        $this->assertFileExists($path);
        $this->assertSame('image/jpeg', getimagesize($path)['mime']);
        $this->assertSame([64, 40], array_slice(getimagesize($path), 0, 2));
        $this->get('/intake/'.$intake->token.'/file')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_without_python_a_jpeg_still_goes_through_and_a_heic_is_refused(): void
    {
        Process::fake(['*' => Process::result('{"ok": false, "error": "unreadable", "heif": false}')]);
        FakeAssistant::$answers['intake'] = $this->toolAnswer();
        $r = $this->send(['photo' => $this->photo(), 'text' => 'Busta'])->assertCreated()->assertJsonPath('has_photo', true);
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertNull($intake->photo_model_path, 'no normalised picture');
        $this->assertSame([Storage::disk('local')->path($intake->photo_path)], FakeAssistant::$calls[0]['images'], 'the upload itself goes to the model');
        Process::assertRan(fn ($process) => str_contains($cmd = implode(' ', (array) $process->command), 'photo_tool.py') && str_contains($cmd, 'normalize') && str_contains($cmd, '2000'));

        $this->send(['photo' => $this->photo('photo.heic')])->assertStatus(422)->assertJsonPath('errors.photo.0', __('router.photo_unreadable'));
        $this->assertSame(1, Intake::count(), 'the refused intake left no row');
        $this->assertSame([], Storage::disk('local')->allFiles('intake/'.Intake::firstOrFail()->token.'x'));
    }

    public function test_nothing_at_all_and_a_file_that_is_no_picture_are_refused_with_a_sentence(): void
    {
        $this->send([])->assertStatus(422)->assertJsonPath('errors.text.0', __('router.need_input'));
        $this->send(['text' => '   '])->assertStatus(422)->assertJsonPath('errors.text.0', __('router.need_input'));
        $this->send(['photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertStatus(422)->assertJsonPath('errors.photo.0', __('photo.not_image'));
        $this->send(['photo' => UploadedFile::fake()->create('big.jpg', 16 * 1024, 'image/jpeg')])->assertStatus(422)->assertJsonPath('errors.photo.0', __('photo.too_big', ['mb' => 15]));
        $this->send(['model' => UploadedFile::fake()->create('model.xyz', 10)])->assertStatus(422)->assertJsonStructure(['errors' => ['model']]);
        $this->assertSame(0, Intake::count());
        $this->assertSame([], FakeAssistant::$calls);
    }

    public function test_a_low_confidence_a_shape_and_a_broken_answer_end_with_a_person(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);   // seven decisions in one test; the limit has its own test
        // the assistant names a tool but is not sure: a person, its questions kept, the sentence of the house (not its promise of a tool)
        FakeAssistant::$answers['intake'] = $this->toolAnswer('figure', 0.4);
        $r = $this->send(['text' => 'něco'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('tool_key', null)->assertJsonPath('actions.tool', null)
            ->assertJsonPath('summary', __('router.summary.fallback', ['hours' => 24]))->assertJsonPath('questions.0', 'Jakou barvu?')->assertJsonPath('wants_shape', false);
        $this->assertSame('tool', Intake::where('token', $r->json('token'))->firstOrFail()->answer['route'], 'what the model said stays for the admin');

        // a shape: phase 1, a person with the wish for a shape
        FakeAssistant::$answers['intake'] = ['route' => 'shape', 'tool_key' => null, 'params' => [], 'summary' => ['cs' => 'Tvar vytvoříme.', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $this->send(['text' => 'soška draka'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('wants_shape', true)->assertJsonPath('summary', __('router.summary.shape', ['hours' => 24]));

        // the model's own "human" keeps the model's sentence
        FakeAssistant::$answers['intake'] = ['route' => 'human', 'tool_key' => null, 'params' => ['copies' => 4], 'summary' => ['cs' => 'Náhradní díl, podívá se člověk.', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.95];
        $this->send(['text' => 'kolečko'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('summary', 'Náhradní díl, podívá se člověk.')->assertJsonPath('params.copies', 4);

        // a tool nobody has, a route nobody knows, a garbage answer: a person
        FakeAssistant::$answers['intake'] = ['route' => 'tool', 'tool_key' => 'teleport', 'params' => [], 'summary' => [], 'questions' => [], 'confidence' => 0.99];
        $this->send(['text' => 'x'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('tool_key', null);
        FakeAssistant::$answers['intake'] = ['foo' => 'bar'];
        $this->send(['text' => 'x'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('summary', __('router.summary.fallback', ['hours' => 24]));
        // "file" without a file is a person too
        FakeAssistant::$answers['intake'] = ['route' => 'file', 'tool_key' => null, 'params' => [], 'summary' => [], 'questions' => [], 'confidence' => 0.9];
        $this->send(['text' => 'mám STL'])->assertCreated()->assertJsonPath('route', 'human');

        // the API fails: a person, the sentence, the failure kept for the admin
        FakeAssistant::$fail = 'HTTP 529';
        $r = $this->send(['text' => 'x'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('summary', __('router.summary.fallback', ['hours' => 24]));
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertTrue($intake->answer['failed']);
        $this->assertStringContainsString('HTTP 529', $intake->answer['error']);
        $this->assertSame(0.0, $intake->confidence);
    }

    public function test_the_parameters_are_kept_only_in_shapes_a_tool_can_take(): void
    {
        $d = IntakeRouter::decide(['route' => 'tool', 'tool_key' => 'charm', 'confidence' => 0.8, 'summary' => ['cs' => 'ok'],
            'params' => ['size_mm' => 900, 'colors' => ['red', 'blue', 7, '', 'c', 'd', 'e', 'f', 'g'], 'copies' => 500, 'text' => str_repeat('a', 100), 'place' => 'Praha', 'kind' => 'bust'],
            'questions' => ['a', 'b', 'c', 'd', 5]], true, 'cs');
        $this->assertSame(120, $d['params']['size_mm'], 'inside the charm\'s range');
        $this->assertSame(['red', 'blue', 'c', 'd', 'e', 'f'], $d['params']['colors']);
        $this->assertSame(100, $d['params']['copies']);
        $this->assertSame(60, mb_strlen($d['params']['text']));
        $this->assertSame('Praha', $d['params']['place']);
        $this->assertArrayNotHasKey('kind', $d['params'], 'a bust is no kind of a charm');
        $this->assertSame(['a', 'b', 'c'], $d['answer']['questions']);
        $this->assertSame('ok', $d['summary']);
    }

    public function test_the_golden_cases_route_by_keywords_when_no_model_is_there(): void
    {
        $cases = json_decode((string) file_get_contents(base_path('tests/fixtures/intake/cases.json')), true);
        $this->assertGreaterThanOrEqual(15, count($cases));
        foreach ($cases as $i => $case) {
            $d = IntakeRouter::guess($case['text'], (bool) $case['photo'], 'cs');
            $this->assertSame($case['route'], $d['route'], "#$i {$case['text']}");
            $this->assertSame($case['tool'], $d['tool_key'], "#$i {$case['text']}");
            if (array_key_exists('wants_shape', $case)) {
                $this->assertSame($case['wants_shape'], $d['wants_shape'], "#$i wants_shape");
            }
            foreach ((array) ($case['params'] ?? []) as $k => $v) {
                $this->assertSame($v, $d['params'][$k] ?? null, "#$i param $k");
            }
            $this->assertNotSame('', $d['summary'], "#$i has a sentence");
        }

        // the live assistant without a key: the keywords decide, the intake says so
        $this->app->instance(Assistant::class, new ClaudeAssistant(['api_key' => '']));
        $r = $this->send(['text' => 'Figurka našeho psa podle fotky, 10 cm'])->assertCreated()->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'pet')->assertJsonPath('params.size_mm', 100);
        $this->assertSame(IntakeRouter::ENGINE_KEYWORDS, Intake::where('token', $r->json('token'))->firstOrFail()->model);
    }

    public function test_a_3d_file_goes_the_calculators_way_and_the_words_reach_the_order(): void
    {
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();
        $path = sys_get_temp_dir().'/mp_intake_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $r = $this->actingAs($user)->post('/intake', ['model' => new UploadedFile($path, 'part.stl', null, null, true), 'text' => 'Zmenšit na 10 cm, dvě barvy.'], ['Accept' => 'application/json'])->assertCreated();
        $r->assertJsonPath('route', 'file')->assertJsonPath('file.name', 'part.stl')->assertJsonPath('summary', __('router.summary.file', ['name' => 'part.stl']));
        $uuid = $r->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame($user->id, $file->owner_user_id);
        $this->assertSame(url('/').'?open='.$uuid.'&note='.rawurlencode('Zmenšit na 10 cm, dvě barvy.'), str_replace('+', '%20', (string) $r->json('actions.calc')));
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertSame($file->id, $intake->model_file_id);
        $this->assertSame('file', $intake->kind);
        $this->assertSame([], FakeAssistant::$calls, 'no assistant for a file');
        $this->assertSame('stl', Event::where('type', 'intake_file_to_calc')->firstOrFail()->meta['kind']);
        $this->assertSame(1, Event::where('type', 'upload')->count(), 'the calculator\'s own upload event');

        // the order's page of that file starts its note with the words
        $orderUrl = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid])->assertCreated()->json('url');
        $order = FarmOrder::where('token', basename($orderUrl))->firstOrFail();
        $this->actingAs($user)->get('/farm/orders/'.$order->token)->assertOk()->assertSee('name="note"', false)->assertSee('>Zmenšit na 10 cm, dvě barvy.</textarea>', false);
        // somebody else's order of the same file does not
        $other = User::factory()->create();
        $other->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($other)->get('/farm/orders/'.$order->token)->assertOk()->assertDontSee('>Zmenšit na 10 cm, dvě barvy.</textarea>', false);
    }

    public function test_five_an_hour_per_session_then_a_429(): void
    {
        FakeAssistant::$answers['intake'] = $this->toolAnswer();
        $token = $this->get('/', ['User-Agent' => self::PHONE, 'Accept-Language' => 'cs-CZ,cs;q=0.9'])->assertOk()->getCookie(AnonymousSession::COOKIE, false)?->getValue();
        $this->assertNotNull($token);
        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $token);
        for ($i = 0; $i < 5; $i++) {
            $this->send(['text' => 'busta '.$i])->assertCreated();
        }
        $this->send(['text' => 'busta 6'])->assertStatus(429);
        $this->assertSame(5, Intake::count());
    }

    public function test_a_person_takes_it_over_with_an_e_mail_and_the_operator_hears_of_it(): void
    {
        FakeAssistant::$answers['intake'] = ['route' => 'shape', 'tool_key' => null, 'params' => [], 'summary' => ['cs' => 'x', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $token = $this->send(['text' => 'Soška draka podle kresby'])->assertCreated()->json('token');

        // no consent, no e-mail: refused with the page's sentences
        $this->postJson('/intake/'.$token.'/request', ['email' => 'jana@example.com'])->assertStatus(422)->assertJsonPath('errors.consent.0', __('router.request.need_consent'));
        $this->postJson('/intake/'.$token.'/request', ['consent' => 1])->assertStatus(422)->assertJsonPath('errors.email.0', __('router.request.need_email'));
        Mail::assertNothingQueued();

        $this->postJson('/intake/'.$token.'/request', ['email' => 'Jana@Example.com', 'consent' => true])->assertOk()
            ->assertJsonPath('message', __('router.request.sent', ['hours' => 24, 'email' => 'jana@example.com']));
        $intake = Intake::where('token', $token)->firstOrFail();
        $this->assertSame('human', $intake->status);
        $made = IntakeRequest::where('intake_id', $intake->id)->firstOrFail();
        $this->assertSame('jana@example.com', $made->email);
        $this->assertTrue($made->wants_shape);
        $this->assertSame('new', $made->status);
        $this->assertNotNull($made->consent_at);
        Mail::assertQueued(IntakeReceived::class, fn (IntakeReceived $m) => $m->hasTo('jana@example.com') && $m->hours === 24 && $m->intake->is($intake));
        Mail::assertQueued(FarmAdminAlert::class, fn (FarmAdminAlert $m) => $m->hasTo('farm@example.com') && $m->kind === 'inquiry'
            && str_contains($m->subjectLine, 'Soška draka') && $m->url === route('admin.intake.show', $intake) && in_array('jana@example.com', $m->lines, true));
        $this->assertSame(['wants_shape' => true], Event::where('type', 'intake_human_created')->firstOrFail()->meta);
        // the mail reads in the customer's language and names the hours
        $html = (new IntakeReceived($intake, $made, 24))->render();
        $this->assertStringContainsString('24', $html);
        $this->assertStringContainsString('Soška draka podle kresby', $html);

        // the same e-mail again replaces, it does not double
        $this->postJson('/intake/'.$token.'/request', ['email' => 'jana@example.com', 'consent' => true])->assertOk();
        $this->assertSame(1, IntakeRequest::count());

        // the operator switched that kind of mail off: the customer still hears, the operator does not
        Mail::fake();
        app(FarmSettings::class)->set('notify_inquiry', false);
        $this->app->forgetInstance(FarmSettings::class);
        $token2 = $this->send(['text' => 'Soška kočky'])->assertCreated()->json('token');
        $this->postJson('/intake/'.$token2.'/request', ['email' => 'petr@example.com', 'consent' => true])->assertOk();
        Mail::assertQueued(IntakeReceived::class);
        Mail::assertNotQueued(FarmAdminAlert::class);
    }

    public function test_the_admin_sees_the_requests_answers_and_closes(): void
    {
        FakeAssistant::$answers['intake'] = ['route' => 'human', 'tool_key' => null, 'params' => [], 'summary' => ['cs' => 'Podívá se člověk.', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $token = $this->send(['text' => 'Kolečko do myčky, 4 ks'])->assertCreated()->json('token');
        $this->postJson('/intake/'.$token.'/request', ['email' => 'jana@example.com', 'consent' => true])->assertOk();
        $intake = Intake::where('token', $token)->firstOrFail();

        $this->get('/admin/intake')->assertRedirect();
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get('/admin/intake')->assertOk()->assertSee('Kolečko do myčky')->assertSee('jana@example.com')->assertSee('čeká na odpověď');
        $this->actingAs($admin)->get('/admin/intake/'.$token)->assertOk()->assertSee('Podívá se člověk.')->assertSee('Odeslat odpověď');

        Mail::fake();
        $this->actingAs($admin)->post('/admin/intake/'.$token.'/reply', ['subject' => 'Nabídka', 'text' => "Dobrý den,\n\nkolečko vymodelujeme za 350 Kč."])->assertRedirect(route('admin.intake.show', $intake));
        Mail::assertQueued(PlainMessage::class, fn ($m) => $m->hasTo('jana@example.com') && $m->title === 'Nabídka');
        $made = $intake->request()->firstOrFail();
        $this->assertSame('answered', $made->status);
        $this->assertSame($admin->id, $made->replied_by);
        $this->assertStringContainsString('350 Kč', $made->reply);

        $this->actingAs($admin)->post('/admin/intake/'.$token.'/status', ['status' => 'closed'])->assertRedirect();
        $this->assertSame('closed', $made->refresh()->status);
        $this->assertSame('closed', $intake->refresh()->status);
    }

    public function test_photos_go_after_thirty_days_and_the_words_stay(): void
    {
        $this->needsPython();
        FakeAssistant::$answers['intake'] = $this->toolAnswer();
        $young = Intake::where('token', $this->send(['photo' => $this->photo(), 'text' => 'mladá'])->assertCreated()->json('token'))->firstOrFail();
        $old = Intake::where('token', $this->send(['photo' => $this->photo(), 'text' => 'stará'])->assertCreated()->json('token'))->firstOrFail();
        $old->forceFill(['created_at' => now()->subDays(31)])->saveQuietly();
        $disk = Storage::disk('local');

        $this->artisan('matplace:prune', ['--dry-run' => true])->expectsOutputToContain('router photos removed: 1')->assertSuccessful();
        $disk->assertExists($old->photo_path);
        $this->artisan('matplace:prune')->expectsOutputToContain('router photos removed: 1')->assertSuccessful();
        $disk->assertMissing($old->photo_path);
        $disk->assertMissing((string) $old->photo_model_path);
        $disk->assertExists($young->photo_path);
        $old->refresh();
        $this->assertNotNull($old->photos_pruned_at);
        $this->assertSame('stará', $old->text, 'the words and the decision stay');
        $this->assertFalse($old->hasPhoto());
        $this->get('/intake/'.$old->token.'/file')->assertNotFound();
        $this->get('/intake/'.$young->token.'/file')->assertOk();
        $this->artisan('matplace:prune')->expectsOutputToContain('router photos removed: 0')->assertSuccessful();
    }
}
