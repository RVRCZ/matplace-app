<?php

namespace Tests\Feature;

use App\Domain\Intake\IntakeRouter;
use App\Domain\Stats\Funnel;
use App\Engines\Ai\FakeAssistant;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\Intake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Try it now without a designer" (TODO #80, docs/AN.md §22.11): an intake that ended with a person or a shape is
 * decided again with both ruled out. The model's tool is taken; a model that insists is overruled by the alternatives
 * of either answer, then by the words, then by the kind of input (a photo → coaster, words → sign). The earlier
 * decision stays in the history, the status stays, the button counts in the five an hour, the event is written, the
 * admin sees it all. Nothing real is called.
 */
class IntakeRerouteTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        FakeAssistant::reset();
        // these tests post without the page (no stamp of it); the rules over the words still run (HomeWhatToMakeTest covers the stamp)
        config(['home.intake.spam.require_stamp' => false]);
        config(['farm.settings.admin_email' => 'farm@example.com', 'farm.settings.intake_reply_hours' => 24]);
    }

    private function send(array $data): TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::PHONE])->post('/intake', $data, ['Accept' => 'application/json']);
    }

    private function reroute(string $token): TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::PHONE])->postJson('/intake/'.$token.'/reroute');
    }

    /** The model's answer that ends with a person (or a shape). */
    private function person(string $route = 'human', array $alternatives = [], string $reason = ''): array
    {
        return [
            'route' => $route, 'tool_key' => null, 'params' => ['size_mm' => null, 'colors' => [], 'copies' => null, 'text' => null, 'place' => null, 'kind' => null],
            'summary' => ['cs' => 'Podívá se člověk.', 'en' => 'A person looks at it.', 'es' => 'Lo mira una persona.'],
            'questions' => [], 'alternatives' => $alternatives, 'reason' => $reason, 'confidence' => 0.8,
        ];
    }

    private function tool(string $tool, string $summary = 'Zkusíme nástroj.', array $alternatives = []): array
    {
        return [
            'route' => 'tool', 'tool_key' => $tool, 'params' => ['size_mm' => 200, 'colors' => ['red'], 'copies' => null, 'text' => null, 'place' => null, 'kind' => null],
            'summary' => ['cs' => $summary, 'en' => 'We try a tool.', 'es' => 'Probamos una herramienta.'],
            'questions' => ['Jak velké?'], 'alternatives' => $alternatives, 'reason' => 'Asked for no designer.', 'confidence' => 0.7,
        ];
    }

    /** A JPEG that goes through without Python (the picture is not normalised, the upload itself is kept). */
    private function photo(): UploadedFile
    {
        Process::fake(['*' => Process::result('{"ok": false, "error": "unreadable", "heif": false}')]);

        return new UploadedFile(base_path('tests/fixtures/intake/photo.jpg'), 'photo.jpg', 'image/jpeg', null, true);
    }

    public function test_a_person_becomes_a_tool_at_the_press_of_the_button(): void
    {
        FakeAssistant::$answers['intake'] = $this->person('human', ['coaster'], 'Unclear what is wanted.');
        $r = $this->send(['text' => 'Obraz na zeď'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('rerouted', false)->assertJsonPath('alternatives', ['coaster']);
        $token = $r->json('token');
        $this->assertSame(url('/intake/'.$token.'/reroute'), $r->json('actions.reroute'));

        FakeAssistant::$answers['intake'] = $this->tool('filament_art', 'Obraz z vrstev filamentu, 20 cm.', ['coaster', 'charm']);
        $r2 = $this->reroute($token)->assertOk()->assertJsonPath('token', $token)->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'filament_art')
            ->assertJsonPath('tool', __('tools.filament_art.title'))->assertJsonPath('summary', 'Obraz z vrstev filamentu, 20 cm.')->assertJsonPath('params.size_mm', 200)
            ->assertJsonPath('questions', ['Jak velké?'])->assertJsonPath('alternatives', ['coaster', 'charm'])->assertJsonPath('rerouted', true)->assertJsonPath('actions.reroute', null)
            ->assertJsonPath('wants_shape', false);
        $this->assertSame(url('/tools/filament-art').'?intake='.$token, $r2->json('actions.tool'));
        // the model heard the extra rule and the catalogue once more
        $this->assertCount(2, FakeAssistant::$calls);
        $call = FakeAssistant::$calls[1];
        $this->assertSame('intake', $call['kind']);
        $this->assertStringContainsString('do not answer `human` or `shape`', $call['system']);
        $this->assertStringContainsString('The customer asked to try it right away without a designer.', $call['user']);
        $this->assertStringContainsString("\nfilament_art | ", $call['user']);
        $this->assertStringContainsString("<words>\nObraz na zeď\n</words>", $call['user']);

        // the intake: the new decision on it, the old one in the history, the status unchanged, the cost of both calls added up
        $intake = Intake::where('token', $token)->firstOrFail();
        $this->assertSame('tool', $intake->route);
        $this->assertSame('filament_art', $intake->tool_key);
        $this->assertSame('new', $intake->status);
        $this->assertFalse($intake->wants_shape);
        $this->assertSame(0.7, $intake->confidence);
        $this->assertSame(IntakeRouter::ORIGIN_REROUTE, $intake->answer['origin']);
        $this->assertSame('Asked for no designer.', $intake->answer['reason']);
        $this->assertArrayNotHasKey('forced', $intake->answer, 'the model chose the tool itself');
        $history = $intake->answer['history'];
        $this->assertCount(1, $history);
        $this->assertSame(IntakeRouter::ORIGIN_INITIAL, $history[0]['origin']);
        $this->assertSame('human', $history[0]['route']);
        $this->assertNull($history[0]['tool_key']);
        $this->assertSame('Podívá se člověk.', $history[0]['summary']);
        $this->assertSame(0.8, $history[0]['confidence']);
        $this->assertSame('Unclear what is wanted.', $history[0]['answer']['reason']);
        $this->assertSame(['coaster'], $history[0]['answer']['alternatives']);
        $this->assertArrayHasKey('at', $history[0]);
        $this->assertArrayNotHasKey('history', $history[0]['answer']);
        $this->assertSame(50, $history[0]['tokens_out']);
        $this->assertSame(100, $intake->tokens_out, 'both calls add up (50 each from the fake)');
        // measured: where it came from, where it went
        $meta = Event::where('type', 'intake_rerouted')->firstOrFail()->meta;
        $this->assertSame('human', $meta['from']);
        $this->assertSame('tool', $meta['to']);
        $this->assertSame('filament_art', $meta['tool']);
        $this->assertSame(0.7, $meta['confidence']);
        $this->assertArrayHasKey('ms', $meta);

        // pressing it again changes nothing and asks nobody
        $this->reroute($token)->assertOk()->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'filament_art');
        $this->assertCount(2, FakeAssistant::$calls);
        $this->assertSame(1, Event::where('type', 'intake_rerouted')->count());
        $this->assertCount(1, Intake::where('token', $token)->firstOrFail()->answer['history']);
    }

    public function test_a_shape_goes_the_same_way_and_a_model_that_insists_is_overruled_by_the_alternatives(): void
    {
        FakeAssistant::$answers['intake'] = $this->person('shape', ['teleport', 'charm', 'coaster'], 'A dragon.');
        $r = $this->send(['text' => 'Soška draka podle kresby'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('wants_shape', true)->assertJsonPath('alternatives', ['charm', 'coaster']);
        $token = $r->json('token');
        $this->assertNotNull($r->json('actions.reroute'));

        // the model says "shape" again and names nothing: the first alternative of the first answer it is
        FakeAssistant::$answers['intake'] = $this->person('shape');
        $this->reroute($token)->assertOk()->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'charm')->assertJsonPath('wants_shape', false)->assertJsonPath('rerouted', true)
            ->assertJsonPath('summary', __('router.summary.forced', ['tool' => __('tools.charm.title')]))->assertJsonPath('actions.reroute', null);
        $intake = Intake::where('token', $token)->firstOrFail();
        $this->assertSame('alternatives', $intake->answer['forced']);
        $this->assertSame('shape', $intake->answer['route'], 'what the model said stays for the admin');
        $this->assertTrue($intake->answer['history'][0]['wants_shape']);
        $this->assertSame('shape', Event::where('type', 'intake_rerouted')->firstOrFail()->meta['from']);
        $this->assertSame('charm', Event::where('type', 'intake_rerouted')->firstOrFail()->meta['tool']);

        // the alternatives of the second answer come first
        FakeAssistant::$answers['intake'] = $this->person('human', ['earrings'], 'x');
        $token2 = $this->send(['text' => 'Soška kočky'])->assertCreated()->json('token');
        FakeAssistant::$answers['intake'] = $this->person('human', ['coaster', 'earrings']);
        $this->reroute($token2)->assertOk()->assertJsonPath('tool_key', 'coaster');
        $this->assertSame(__('router.summary.forced', ['tool' => __('tools.coaster.title')]), Intake::where('token', $token2)->firstOrFail()->summary);
    }

    public function test_without_alternatives_the_words_and_the_kind_of_input_choose(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        // a pet named in the words, with a photo → the pet
        FakeAssistant::$answers['intake'] = $this->person();
        $token = $this->send(['photo' => $this->photo(), 'text' => 'Figurka našeho psa, nic hotového nechci'])->assertCreated()->assertJsonPath('route', 'human')->json('token');
        $this->reroute($token)->assertOk()->assertJsonPath('tool_key', 'pet')->assertJsonPath('has_photo', true);
        $this->assertSame('words', Intake::where('token', $token)->firstOrFail()->answer['forced']);

        // a photo and words no tool knows → a coaster; words alone → a sign
        $token = $this->send(['photo' => $this->photo(), 'text' => 'něco divného'])->assertCreated()->json('token');
        $this->reroute($token)->assertOk()->assertJsonPath('tool_key', 'coaster')->assertJsonPath('summary', __('router.summary.forced', ['tool' => __('tools.coaster.title')]));
        $this->assertSame('kind', Intake::where('token', $token)->firstOrFail()->answer['forced']);
        $token = $this->send(['text' => 'něco divného'])->assertCreated()->json('token');
        $this->reroute($token)->assertOk()->assertJsonPath('tool_key', 'sign')->assertJsonPath('has_photo', false);
        $this->assertSame(url('/tools/sign').'?intake='.$token, Intake::where('token', $token)->firstOrFail()->token ? $this->reroute($token)->json('actions.tool') : '');

        // a bust is no alternative without a photo: the words would say figure too, so the kind decides
        FakeAssistant::$answers['intake'] = $this->person('human', ['figure', 'pet']);
        $token = $this->send(['text' => 'Busta táty'])->assertCreated()->json('token');
        $this->reroute($token)->assertOk()->assertJsonPath('tool_key', 'sign');
        $this->assertSame('kind', Intake::where('token', $token)->firstOrFail()->answer['forced']);

        // the second call fails: still a tool (the first alternative), the failure kept for the admin
        FakeAssistant::$answers['intake'] = $this->person('human', ['earrings']);
        $token = $this->send(['text' => 'x'])->assertCreated()->json('token');
        FakeAssistant::$fail = 'HTTP 529';
        $this->reroute($token)->assertOk()->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'earrings');
        $intake = Intake::where('token', $token)->firstOrFail();
        $this->assertTrue($intake->answer['failed']);
        $this->assertStringContainsString('HTTP 529', $intake->answer['error']);
        $this->assertSame('alternatives', $intake->answer['forced']);
        $this->assertSame('human', $intake->answer['history'][0]['route']);
    }

    public function test_the_button_counts_in_the_five_an_hour(): void
    {
        FakeAssistant::$answers['intake'] = $this->person();
        $cookie = $this->get('/', ['User-Agent' => self::PHONE, 'Accept-Language' => 'cs-CZ,cs;q=0.9'])->assertOk()->getCookie(AnonymousSession::COOKIE, false)?->getValue();
        $this->assertNotNull($cookie);
        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $cookie);
        $token = $this->send(['text' => 'kolečko 1'])->assertCreated()->json('token');
        for ($i = 2; $i <= 4; $i++) {
            $this->send(['text' => 'kolečko '.$i])->assertCreated();
        }
        $this->reroute($token)->assertOk()->assertJsonPath('route', 'tool');           // the fifth decision of the hour
        $this->send(['text' => 'kolečko 6'])->assertStatus(429);
        FakeAssistant::$answers['intake'] = $this->person();
        $token2 = Intake::where('route', 'human')->where('token', '!=', $token)->firstOrFail()->token;
        $this->reroute($token2)->assertStatus(429);
        $this->assertSame(4, Intake::count());
        $this->assertSame(5, count(FakeAssistant::$calls));
    }

    public function test_a_tool_changes_nothing_and_a_file_has_no_designer_to_skip(): void
    {
        FakeAssistant::$answers['intake'] = $this->tool('coaster');
        $token = $this->send(['text' => 'Podtácek'])->assertCreated()->assertJsonPath('route', 'tool')->assertJsonPath('actions.reroute', null)->json('token');
        $this->reroute($token)->assertOk()->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'coaster')->assertJsonPath('rerouted', false);
        $this->assertCount(1, FakeAssistant::$calls);
        $this->assertSame(0, Event::where('type', 'intake_rerouted')->count());

        $file = Intake::create(['token' => str_repeat('f', 24), 'kind' => 'file', 'locale' => 'cs', 'route' => 'file', 'status' => 'new', 'confidence' => 1.0]);
        $this->reroute($file->token)->assertStatus(422);
        $this->reroute(str_repeat('z', 24))->assertNotFound();
    }

    public function test_the_admin_sees_the_history_and_the_funnel_counts_the_button(): void
    {
        FakeAssistant::$answers['intake'] = $this->person('human', ['coaster'], 'Technical words, a picture attached.');
        $token = $this->send(['text' => 'Obraz, aby pasoval'])->assertCreated()->json('token');
        FakeAssistant::$answers['intake'] = $this->person();
        $this->reroute($token)->assertOk()->assertJsonPath('tool_key', 'coaster');
        $intake = Intake::where('token', $token)->firstOrFail();

        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get('/admin/intake/'.$token)->assertOk()
            ->assertSee('Historie rozhodnutí')->assertSee('původní')->assertSee('Po tlačítku „Zkusit hned bez návrháře“')->assertSee('podle alternativ')
            ->assertSee('Technical words, a picture attached.')->assertSee(__('tools.coaster.title'))->assertSee('Podívá se člověk.');
        $this->actingAs($admin)->get('/admin/intake?show=all')->assertOk()->assertSee('bez návrháře (z člověka)');

        $counts = app(Funnel::class)->reroutes(30);
        $this->assertSame(['offered' => 1, 'pressed' => 1, 'to' => ['coaster' => 1]], $counts);
        $this->actingAs($admin)->get(route('admin.stats.funnel'))->assertOk()->assertSee('člověk nabídnut <strong>1×</strong>', false)->assertSee('tlačítko stisknuto <strong>1×</strong>', false)->assertSee(__('tools.coaster.title').' 1×');
        $this->assertSame($intake->id, Event::where('type', 'intake_rerouted')->firstOrFail()->subject_id);
    }
}
