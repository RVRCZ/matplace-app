<?php

namespace Tests\Feature;

use App\Console\Commands\IntakeCases;
use App\Domain\Farm\FarmSettings;
use App\Domain\Intake\IntakeHints;
use App\Domain\Intake\IntakeRouter;
use App\Domain\Intake\IntakeSpam;
use App\Engines\Ai\FakeAssistant;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\FarmSetting;
use App\Models\Intake;
use App\Models\User;
use App\Support\ToolSeo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The first screen "Co chcete vyrobit?" (docs/AN.md §23): the field for words leads the page, the photo and the
 * dictation sit at it, the examples follow the calendar and the language, the admin changes the calendar without a
 * deploy; advertising is held by rules that cost nothing and never reaches the model; what a visitor did before
 * sending is counted, the staff's browsers left out. Nothing real is called: the assistant is the fake one.
 */
class HomeWhatToMakeTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        FakeAssistant::reset();
        config(['farm.settings.intake_reply_hours' => 24, 'farm.settings.christmas_deadline' => '2026-12-15']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A wish posted the way the page's script posts it: with the stamp of a page made `age` seconds ago. */
    private function send(array $data, int $age = 30, string $prefix = ''): TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::PHONE])->post($prefix.'/intake', $data + [IntakeSpam::STAMP_FIELD => IntakeSpam::stamp(now()->timestamp - $age)], ['Accept' => 'application/json']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    /** @return list<array<string, mixed>> */
    private function samples(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/fixtures/intake/spam.json')), true);
    }

    // ── 1: the page ──────────────────────────────────────────────────────────────────────────────────────────────

    public function test_the_question_and_the_field_lead_the_page_in_every_language(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $lang) {
            $html = (string) $this->get($prefix.'/')->assertOk()->getContent();
            $this->assertMatchesRegularExpression('~<h1[^>]*>'.preg_quote(e(__('router.title', [], $lang)), '~').'</h1>~u', $html, "$lang: the question is the h1");
            $this->assertSame(1, substr_count($html, '<h1'), "$lang: one h1");
            // the order of the first screen: the question, the field, the photo and the dictation at the field, the examples, the action, the file's link; the rest below
            $at = fn (string $needle) => strpos($html, $needle) ?: $this->fail("$lang: $needle is missing");
            $order = ['<h1', 'id="intake-text"', 'id="intake-photo-btn"', 'id="intake-mic"', 'id="intake-hints"', 'id="intake-submit"', 'id="intake-file-link"', 'id="router-paths"', 'id="router-gifts"', 'id="router-file"', 'id="home-tools"'];
            for ($i = 1; $i < count($order); $i++) {
                $this->assertLessThan($at($order[$i]), $at($order[$i - 1]), "$lang: {$order[$i - 1]} comes before {$order[$i]}");
            }
            // the photo and the dictation are inside the field's own frame, not under it
            preg_match('~id="intake-box".*?id="intake-hints"~s', $html, $box);
            $this->assertStringContainsString('id="intake-photo-btn"', $box[0] ?? '', $lang);
            $this->assertStringContainsString('id="intake-mic"', $box[0] ?? '', $lang);
            // the dictation stays unseen until the script finds the browser can do it
            $this->assertMatchesRegularExpression('~id="intake-mic"[^>]*class="hidden ~', $html, $lang);
            // no `capture`: a phone offers the camera and the gallery; the file's own input is still there for the small link
            $this->assertMatchesRegularExpression('~<input id="intake-photo" name="photo" type="file" accept="image/\*,\.heic,\.heif" class="sr-only">~', $html);
            $this->assertMatchesRegularExpression('~<label for="intake-model" id="intake-file-link"[^>]*>'.preg_quote(e(__('router.file_link', [], $lang)), '~').'</label>~u', $html, $lang);
            $this->assertStringContainsString('>'.e(__('router.submit', [], $lang)).'</button>', $html, $lang);
            $this->assertStringContainsString('placeholder="'.e(__('router.text_placeholder', [], $lang)).'"', $html, $lang);
            // against robots: the stamp of the page and the unseen field; where the script reports what was done
            $this->assertMatchesRegularExpression('~name="started" id="intake-started" value="\d{10}\.[0-9a-f]{20}"~', $html, $lang);
            $this->assertStringContainsString('name="topic" id="intake-trap" tabindex="-1"', $html, $lang);
            $this->assertStringContainsString('data-event-url="'.url($prefix.'/intake/event').'"', $html, $lang);
            // the old photo-first area is gone
            $this->assertStringNotContainsString('id="intake-drop"', $html);
            $visible = preg_replace('/data-i18n="[^"]*"/', '', $html);
            $this->assertDoesNotMatchRegularExpression('/\brouter\.[a-z_]+(\.[a-z_0-9]+)+\b/', (string) $visible, "$lang: a raw key");
        }
        // the examples: a label, the wish it writes into the field, its code for the numbers; the season's are marked
        $html = (string) $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('~data-hint="mikulas\.1" data-fill="'.preg_quote(e(__('router.hint.mikulas.1.fill')), '~').'"\s+data-seasonal\s*>'.preg_quote(e(__('router.hint.mikulas.1')), '~').'</button>~u', $html);
        $this->assertStringContainsString('data-hint="steady.1"', $html);
        $this->assertStringNotContainsString('data-hint="mikulas', (string) $this->get('/en')->getContent(), 'St Nicholas is Czech');
    }

    public function test_the_title_the_description_and_the_structured_data_say_what_the_page_is(): void
    {
        $words = ['cs' => ['3D tisk na zakázku', 'dárek z fotky', 'podle fotky nebo popisu'], 'en' => ['Custom 3D printing', 'from a photo or a description'], 'es' => ['Impresión 3D a medida', 'una foto']];
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $lang) {
            $html = (string) $this->get($prefix.'/')->assertOk()->getContent();
            $seo = ToolSeo::texts('calc', $lang);
            $title = $seo['title'].' · matplace';
            // the usual lengths, and what people search for in the title or the description
            $this->assertLessThanOrEqual(60, mb_strlen($title), $lang);
            $this->assertLessThanOrEqual(155, mb_strlen($seo['description']), $lang);
            foreach ($words[$lang] as $word) {
                $this->assertStringContainsString($word, $seo['title'].' '.$seo['description'], "$lang: $word");
            }
            $this->assertStringNotContainsStringIgnoringCase($lang === 'cs' ? 'kalkulačka' : 'calcul', $seo['title'], "$lang: the title is no longer the calculator's");
            // one sentence everywhere: the title, the description, the link preview
            $this->assertStringContainsString('<title>'.e($title).'</title>', $html, $lang);
            $this->assertStringContainsString('<meta name="description" content="'.e($seo['description']).'">', $html, $lang);
            $this->assertStringContainsString('<meta property="og:title" content="'.e($title).'">', $html, $lang);
            $this->assertStringContainsString('<meta property="og:description" content="'.e($seo['description']).'">', $html, $lang);
            // the preview picture is the site's ("3D tisk na zakázku"), not the card of the calculator tool
            $this->assertMatchesRegularExpression('~<meta property="og:image" content="[^"]*/og/'.($lang === 'cs' ? '' : $lang.'/').'site/home\.png~', $html, $lang);
            // structured data: the site describes itself with the same sentence; the HowTo of the calculator keeps its own
            preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
            $data = collect($m[1])->map(fn (string $j) => json_decode($j, true))->keyBy(fn ($d) => $d['@type'] ?? '?');
            $this->assertSame($seo['description'], $data['WebSite']['description'] ?? null, $lang);
            $this->assertSame($seo['howto_description'], $data['HowTo']['description'] ?? null, $lang);
            $this->assertNotSame($seo['description'], $seo['howto_description'], $lang);
            $this->assertArrayHasKey('FAQPage', $data->all(), $lang);
        }
    }

    // ── 3: the examples by the date and the language ─────────────────────────────────────────────────────────────

    public function test_the_examples_follow_the_calendar_and_the_language(): void
    {
        $codes = fn (string $locale, string $day) => array_column(IntakeHints::chips($locale, Carbon::parse($day)), 'code');

        // 12 Nov 2026: St Nicholas (Czech only, ends first) and Christmas are open; three of the season, then the steady ones
        $this->assertSame(['mikulas.1', 'christmas.1', 'mikulas.2', 'steady.1', 'steady.2', 'steady.3'], $codes('cs', '2026-11-12'));
        $this->assertSame(['christmas.1', 'christmas.2', 'christmas.3', 'steady.1', 'steady.2', 'steady.3'], $codes('en', '2026-11-12'));
        $this->assertSame(['christmas.1', 'christmas.2', 'christmas.3', 'steady.1', 'steady.2', 'steady.3'], $codes('es', '2026-11-12'));
        $chips = IntakeHints::chips('cs', Carbon::parse('2026-11-12'));
        $this->assertSame(['Čelenka s rohy na Mikuláše', 'Ozdoba na stromek se jménem'], [$chips[0]['label'], $chips[1]['label']]);
        $this->assertTrue($chips[0]['seasonal']);
        $this->assertFalse($chips[3]['seasonal']);

        // the offer ends before the day, not on it: St Nicholas 5 Nov – 1 Dec, Christmas until the farm's own deadline (15 Dec)
        $w = IntakeHints::windowOf('mikulas', 'cs', 2026);
        $this->assertSame(['2026-11-05', '2026-12-01'], [$w['from']->toDateString(), $w['to']->toDateString()]);
        $this->assertNull(IntakeHints::windowOf('mikulas', 'en', 2026));
        $this->assertSame('2026-12-15', IntakeHints::windowOf('christmas', 'cs', 2026)['to']->toDateString());
        $this->assertSame('2026-11-04', IntakeHints::windowOf('christmas', 'cs', 2026)['from']->toDateString());
        config(['farm.settings.christmas_deadline' => '']);
        $this->assertSame('2026-12-15', IntakeHints::windowOf('christmas', 'cs', 2026)['to']->toDateString(), 'nine days before the 24th');
        $this->assertSame('2026-12-16', IntakeHints::windowOf('christmas', 'en', 2026)['to']->toDateString(), 'nine days before the 25th');
        config(['farm.settings.christmas_deadline' => '2026-12-10']);
        $this->assertNotContains('christmas.1', $codes('cs', '2026-12-11'));

        // 2 Dec: St Nicholas is over, New Year's Eve began; Spain has the Three Kings too. The year in the example is the coming one
        config(['farm.settings.christmas_deadline' => '2026-12-15']);
        $this->assertSame(['christmas.1', 'silvestr.1', 'christmas.2'], array_slice($codes('cs', '2026-12-02'), 0, 3));
        $this->assertSame(['christmas.1', 'silvestr.1', 'reyes.1'], array_slice($codes('es', '2026-12-02'), 0, 3));
        $this->assertSame('Nápis 2027 na stůl', collect(IntakeHints::chips('cs', Carbon::parse('2026-12-02')))->firstWhere('code', 'silvestr.1')['label']);
        // over the New Year the Three Kings stay open in Spain until 1 January
        $this->assertContains('reyes.1', $codes('es', '2027-01-01'));
        $this->assertNotContains('reyes.1', $codes('es', '2027-01-02'));

        // no event open (mid July): only the steady ones, a birthday and a wedding among them
        $this->assertSame(['steady.1', 'steady.2', 'steady.3', 'steady.4', 'birthday.1', 'wedding.1'], $codes('cs', '2026-07-15'));

        // movable days: Easter 2027 is 28 March; Mother's Day is the second Sunday of May, in Spain the first; Father's Day in Spain is 19 March
        $this->assertSame('2027-03-28', IntakeHints::day('easter', 2027)->toDateString());
        $this->assertSame('2026-04-05', IntakeHints::day('easter', 2026)->toDateString());
        $this->assertSame('2027-05-09', IntakeHints::day('sunday:2:5', 2027)->toDateString());
        $this->assertSame('2027-05-02', IntakeHints::day('sunday:1:5', 2027)->toDateString());
        $w = IntakeHints::windowOf('easter', 'cs', 2027);
        $this->assertSame(['2027-02-21', '2027-03-23'], [$w['from']->toDateString(), $w['to']->toDateString()]);
        $this->assertContains('fathers.1', $codes('es', '2027-03-01'));
        $this->assertNotContains('fathers.1', $codes('cs', '2027-03-01'));
        $this->assertContains('fathers.1', $codes('cs', '2027-06-01'));
        foreach (['valentine' => '2027-02-01', 'easter' => '2027-03-01', 'mothers' => '2027-04-20', 'school_end' => '2027-06-10', 'school_start' => '2026-08-20', 'halloween' => '2026-10-10'] as $event => $day) {
            $this->assertContains($event.'.1', $codes('cs', $day), "$event is offered on $day");
        }

        // the page shows what the calendar says on the day
        Carbon::setTestNow('2026-07-15 09:00:00');
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-seasonal', $html);
        $this->assertStringContainsString('data-hint="wedding.1"', $html);
    }

    public function test_every_example_leads_somewhere_and_none_is_held_as_advertising(): void
    {
        $cases = IntakeCases::hintCases();
        $this->assertGreaterThan(100, count($cases));
        foreach ($cases as $case) {
            // every event has 2–4 examples with a label that fits a chip and a wish that fits the field
            $d = IntakeRouter::guess($case['text'], false, $case['locale']);
            $this->assertSame($case['route'], $d['route'], $case['code'].': '.$case['text']);
            $this->assertSame($case['tool'], $d['tool_key'], $case['code'].': '.$case['text']);
            $this->assertLessThan((int) config('home.intake.spam.threshold'), IntakeSpam::score($case['text'], false)['points'], $case['code']);
            $this->assertFalse(IntakeSpam::priceOnly($case['text']), $case['code']);
        }
        foreach (IntakeHints::events() as $key => $event) {
            $this->assertContains(count($event['examples']), [2, 3, 4], $key);
            foreach (IntakeHints::locales($key) as $locale) {
                foreach (IntakeHints::defaults($key, $locale) as $i => $e) {
                    $this->assertStringNotContainsString('router.hint', $e['label'].$e['fill'], "$key.$i $locale has its text");
                    $this->assertLessThanOrEqual(IntakeHints::LABEL_MAX, mb_strlen($e['label']), "$key.$i $locale");
                    $this->assertLessThanOrEqual(IntakeHints::FILL_MAX, mb_strlen($e['fill']), "$key.$i $locale");
                }
            }
        }
    }

    public function test_a_wish_without_a_photo_that_no_tool_makes_gets_an_honest_answer_and_the_way_to_a_person(): void
    {
        // the assistant says a shape (no template for a headband with horns): a person, a sentence that does not speak of a photo, the e-mail's address
        FakeAssistant::$answers['intake'] = ['route' => 'shape', 'tool_key' => null, 'params' => [], 'summary' => ['cs' => 'x', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.8, 'alternatives' => []];
        $r = $this->send(['text' => 'Čelenka s čertovskými rohy na Mikuláše pro osmileté dítě, červená a černá.', 'hint' => 'mikulas.1'])->assertCreated();
        $r->assertJsonPath('route', 'human')->assertJsonPath('held', null)->assertJsonPath('has_photo', false)->assertJsonPath('summary', __('router.summary.shape_text', ['hours' => 24]));
        $this->assertStringNotContainsString('fotky připraví', $r->json('summary'));
        $this->assertSame(url('/intake/'.$r->json('token').'/request'), $r->json('actions.request'));
        // the model down: still a person, still honest
        FakeAssistant::$fail = 'timeout';
        $this->send(['text' => 'Čelenka s rohy'])->assertCreated()->assertJsonPath('route', 'human')->assertJsonPath('summary', __('router.summary.fallback_text', ['hours' => 24]));
        $this->post('/intake/'.$r->json('token').'/request', ['email' => 'stella@example.com', 'consent' => '1'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_the_admin_switches_an_event_off_and_changes_its_dates_and_texts_without_a_deploy(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->get('/admin/intake/hints')->assertRedirect();
        $this->assertNotSame(200, $this->actingAs(User::factory()->create())->get('/admin/intake/hints')->getStatusCode(), 'a customer does not get in');

        $admin = $this->admin();
        $page = $this->actingAs($admin)->get('/admin/intake/hints')->assertOk();
        $page->assertSee('Mikuláš')->assertSee('5. 11. 2026 – 1. 12. 2026 (teď)')->assertSee('Čelenka s rohy na Mikuláše | Čelenka s čertovskými rohy', false)->assertSee('Dnes na hlavní straně');

        // the form as the page shows it: every event on, nothing changed → nothing is kept
        $form = fn (array $change = []) => ['events' => array_replace_recursive(
            collect(IntakeHints::table())->map(fn (array $e) => ['on' => '1', 'from' => '', 'to' => '', 'texts' => collect($e['texts'])->map(fn ($t) => $t['lines'])->all()])->all(), $change)];
        $this->actingAs($admin)->post('/admin/intake/hints', $form())->assertRedirect('/admin/intake/hints');
        $this->assertSame([], FarmSetting::find(IntakeHints::SETTING)->value);

        // St Nicholas off, Christmas only from 20 November, a text of one's own for the Czech bust
        $data = $form(['christmas' => ['from' => '20.11.', 'to' => '18.12.'], 'steady' => ['texts' => ['cs' => "Busta dědy | Busta mého dědy z fotky, 15 cm, bílá.\nNáhradní díl | Kolečko do myčky, 4 kusy."]]]);
        unset($data['events']['mikulas']['on']);
        $this->actingAs($admin)->post('/admin/intake/hints', $data)->assertRedirect('/admin/intake/hints');
        $kept = FarmSetting::find(IntakeHints::SETTING)->value;
        $this->assertSame(['mikulas' => ['off' => true], 'christmas' => ['from' => '11-20', 'to' => '12-18'], 'steady' => ['texts' => ['cs' => [['Busta dědy', 'Busta mého dědy z fotky, 15 cm, bílá.'], ['Náhradní díl', 'Kolečko do myčky, 4 kusy.']]]]],
            array_replace(['mikulas' => null, 'christmas' => null, 'steady' => null], $kept));

        $this->app->forgetInstance(FarmSettings::class);
        $this->assertSame(['steady.1', 'steady.2', 'birthday.1', 'wedding.1', 'birthday.2', 'wedding.2'], array_column(IntakeHints::chips('cs', now()), 'code'), 'before 20 Nov nothing of the season, two steady examples of the admin');
        $this->assertSame(['christmas.1', 'christmas.2', 'christmas.3'], array_slice(array_column(IntakeHints::chips('cs', Carbon::parse('2026-11-20')), 'code'), 0, 3));
        $this->assertContains('christmas.1', array_column(IntakeHints::chips('cs', Carbon::parse('2026-12-18')), 'code'), "the admin's last day beats the farm's deadline");
        $this->assertNotContains('christmas.1', array_column(IntakeHints::chips('cs', Carbon::parse('2026-12-19')), 'code'));
        $this->assertSame('A bust from a photo', IntakeHints::chips('en', now())[0]['label'], 'English keeps its own texts');

        auth()->logout();
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-fill="Busta mého dědy z fotky, 15 cm, bílá."', $html);
        $this->assertStringContainsString('>Busta dědy</button>', $html);
        $this->assertStringNotContainsString('data-hint="mikulas', $html);
        $this->assertStringNotContainsString('data-seasonal', $html);
        $this->actingAs($admin)->get('/admin/intake/hints')->assertOk()->assertSee('(upraveno)')->assertSee('value="20.11."', false);

        // a line that is no "label | wish" and a date that is no date are refused with a sentence, nothing is kept
        $this->actingAs($admin)->post('/admin/intake/hints', $form(['valentine' => ['texts' => ['cs' => 'Jen štítek bez přání']]]))->assertSessionHasErrors('events');
        $this->actingAs($admin)->post('/admin/intake/hints', $form(['valentine' => ['from' => '31.2.']]))->assertSessionHasErrors('events');
        $this->assertSame($kept, FarmSetting::find(IntakeHints::SETTING)->value);

        // an empty box brings the dictionary's texts back; the event on again
        $this->actingAs($admin)->post('/admin/intake/hints', $form(['steady' => ['texts' => ['cs' => '']]]))->assertRedirect();
        $this->assertSame([], FarmSetting::find(IntakeHints::SETTING)->value);
        $this->app->forgetInstance(FarmSettings::class);
        $this->assertSame('mikulas.1', IntakeHints::chips('cs', now())[0]['code']);
    }

    // ── the live test of 13 Nov 2026: "Chci medaili s reliéfem z fotky" ───────────────────────────────────────────

    public function test_the_prompt_forbids_promising_or_asking_for_what_the_chosen_tool_does_not_take(): void
    {
        $system = IntakeRouter::system();
        foreach (['Never promise the customer, and never ask them for, an input that the chosen tool\'s `takes` does not list', 'do not ask for a photo for a tool that takes no photo',
            'do not promise a relief of a photo on a thing whose tool takes no photo', 'a cake topper with a relief from a photo: the topper tool takes no photo',
            'a combination no single tool makes', 'say plainly in `summary` what that tool makes and what it does not', 'put the tools that DO take the photo into `alternatives`',
            'relief, coaster, charm, filament_art', 'name the best of them in `summary` by its public name', 'a picture is still not a person\'s job', 'a photo as a relief or a lithophane → relief'] as $rule) {
            $this->assertStringContainsString($rule, $system, $rule);
        }
        $this->assertStringContainsString('a picture is never a person\'s job', $system, 'the main rule of round 2 stays');
        $this->assertStringContainsString('Never promise the customer', IntakeRouter::system(true), 'the rule holds for "without a designer" too');

        // the catalogue the model sees has the relief tool (it was missing), with a photo as what it takes; the medal
        // takes a photo as a relief since the decorations (docs/AR.md §8, IntakeDecorTest), a cake topper takes none
        $this->assertArrayHasKey('relief', IntakeRouter::TOOLS);
        $this->assertStringContainsString('a photo', IntakeRouter::TOOLS['relief']['takes']);
        $this->assertStringContainsString('a photo', IntakeRouter::TOOLS['medallion']['takes']);
        $this->assertStringNotContainsString('photo', IntakeRouter::TOOLS['topper']['takes']);
        $user = IntakeRouter::user(new Intake(['text' => 'Chci medaili s reliéfem z fotky', 'locale' => 'cs']));
        $this->assertStringContainsString("\nrelief | ".__('tools.relief.title', [], 'cs').' / '.__('tools.relief.title', [], 'en').' / '.__('tools.relief.title', [], 'es').' | a photo', $user);
        $this->assertTrue((bool) config('tools.relief.available'));
        $this->assertSame(7, IntakeRouter::unionCount(), 'the schema has one more nullable field: params.decor');

        // the tool's page takes the photo the router hands over
        $this->get('/tools/relief')->assertOk()->assertSee('<input id="relief-photo" name="photo" data-intake-photo type="file"', false);
        // an answer of the model that says plainly what the chosen tool does not make and names the tools that take the photo
        FakeAssistant::$answers['intake'] = ['route' => 'tool', 'tool_key' => 'topper', 'params' => ['decor' => 'photo'], 'summary' => ['cs' => 'Zápich do dortu umíme se jménem a číslem; reliéf z fotky na něj neumíme. Fotku zvládne Reliéf a litofanie z fotky.', 'en' => 'x', 'es' => 'x'],
            'questions' => [], 'confidence' => 0.6, 'alternatives' => ['relief', 'coaster', 'teleport']];
        $r = $this->send(['text' => 'Chci zápich do dortu s reliéfem z fotky'])->assertCreated()->assertJsonPath('tool_key', 'topper')->assertJsonPath('alternatives', ['relief', 'coaster']);
        $this->assertStringContainsString('reliéf z fotky na něj neumíme', $r->json('summary'));
        $this->assertArrayNotHasKey('decor', (array) $r->json('params'), 'a tool without decorations gets no relief');
    }

    public function test_the_keyword_fallback_says_the_tool_it_chose_not_the_designer(): void
    {
        foreach ([false, true] as $photo) {
            foreach (['cs', 'en', 'es'] as $lang) {
                $d = IntakeRouter::guess('Chci medaili s reliéfem z fotky', $photo, $lang);
                $this->assertSame(['tool', 'medallion', ['relief']], [$d['route'], $d['tool_key'], $d['answer']['alternatives']]);
                // the sentence names the tool and the one that takes the photo, and is not the designer's; with a photo
                // it says that the photo becomes a relief on the medal (params.decor, IntakeDecorTest)
                $this->assertSame(__('router.summary.tool', ['tool' => __('tools.medallion.title', [], $lang)], $lang)
                    .($photo ? ' '.__('router.summary.tool_decor', [], $lang) : '')
                    .' '.__('router.summary.tool_also', ['tools' => __('tools.relief.title', [], $lang)], $lang), $d['summary']);
                $this->assertSame($photo ? 'photo' : null, $d['params']['decor'] ?? null);
                foreach (['router.summary.fallback', 'router.summary.fallback_text', 'router.summary.shape', 'router.summary.shape_text'] as $key) {
                    $this->assertNotSame(__($key, ['hours' => 24], $lang), $d['summary']);
                }
            }
        }
        // every tool the words choose says its own name; a person and a shape keep the designer's sentences
        foreach (json_decode((string) file_get_contents(base_path('tests/fixtures/intake/cases.json')), true) as $case) {
            $d = IntakeRouter::guess($case['text'], (bool) $case['photo'], 'cs');
            if ($d['route'] === 'tool') {
                $this->assertStringStartsWith(__('router.summary.tool', ['tool' => __('tools.'.$d['tool_key'].'.title')]), $d['summary'], $case['text']);
            } else {
                $this->assertContains($d['summary'], array_map(fn ($k) => __($k, ['hours' => 24]), ['router.summary.fallback', 'router.summary.fallback_text', 'router.summary.shape', 'router.summary.shape_text']), $case['text']);
            }
        }
        $this->assertSame('tool', IntakeRouter::guess('Reliéf z fotky naší rodiny, 15 cm', true)['route']);
        $this->assertSame('relief', IntakeRouter::guess('Litofanie z fotky jako lampička', true)['tool_key']);
        // without a key the page gets the same sentence
        $this->assertSame([], IntakeRouter::alsoNamed('busta mého táty', 'figure'));
    }

    // ── 2: advertising ───────────────────────────────────────────────────────────────────────────────────────────

    public function test_the_rules_hold_the_advertising_of_november_and_let_honest_wishes_through(): void
    {
        $threshold = (int) config('home.intake.spam.threshold');
        $verdict = function (string $text, bool $photo) use ($threshold): string {
            $score = IntakeSpam::score($text, $photo);

            return $score['points'] >= $threshold ? 'held' : (! $photo && IntakeSpam::priceOnly($text) ? 'price' : 'pass');
        };
        $caught = $missed = $wrong = $honest = 0;
        foreach ($this->samples() as $s) {
            $got = $verdict($s['text'], (bool) $s['photo']);
            $this->assertSame($s['expect'], $got, $s['text'].' ['.implode(',', IntakeSpam::score($s['text'], (bool) $s['photo'])['rules']).']');
            $isHonest = $s['expect'] === 'pass' || ! empty($s['honest']);
            $honest += $isHonest ? 1 : 0;
            $wrong += $isHonest && $got === 'held' ? 1 : 0;
            $caught += ! $isHonest && $got !== 'pass' ? 1 : 0;
            $missed += ! $isHonest && $got === 'pass' ? 1 : 0;
        }
        // the numbers of the report (docs/AN.md §23.2): 13 advertisements held (one of them the bare price question, answered about prices), none
        // missed; of 19 honest texts one is held (Ukrainian without a photo) and one price question gets the answer about prices
        $this->assertSame([13, 0, 19, 1], [$caught, $missed, $honest, $wrong]);

        // the golden cases of the router (38) and every example of the first screen (122): none is held, none is taken for a bare price question
        $golden = json_decode((string) file_get_contents(base_path('tests/fixtures/intake/cases.json')), true);
        $this->assertCount(38, $golden);
        foreach (array_merge($golden, IntakeCases::hintCases()) as $case) {
            $this->assertSame('pass', $verdict((string) $case['text'], (bool) $case['photo']), (string) $case['text']);
        }

        // which rule holds what
        $rules = fn (string $text, bool $photo = false) => IntakeSpam::score($text, $photo)['rules'];
        $this->assertSame(['markup'], $rules('Hello <b>there</b> friend of mine'));
        $this->assertSame(['links'], $rules('see http://a.example.org and www.b.example.org'));
        $this->assertSame(['link'], $rules('figurka jako na https://www.alza.cz/x'));
        $this->assertSame([], $rules('model z https://www.printables.com/model/1 a https://makerworld.com/en/models/2'));
        $this->assertSame(['strong'], $rules('We do SEO for shops'));
        $this->assertSame([], $rules('Figurka pro Museo Nacional, 10 cm'), 'a phrase is matched as words, not inside a word');
        $this->assertContains('script', $rules('Здравствуйте, хочу фигурку собаки'));
        $this->assertNotContains('script', $rules('Здравствуйте, хочу фигурку собаки', true), 'a photo lets a foreign script through to the model');
        $this->assertSame(['language'], $rules('Hæ, ég vildi vita verð þitt.'));
        $this->assertSame([], $rules('Hæ, ég vildi vita verð þitt.', true));
        $this->assertSame(['long'], $rules(str_repeat('Potřebuji držák na kolo, který vydrží. ', 20)));
        $this->assertSame(['example.com', 'shop.example.net'], IntakeSpam::hosts('http://www.example.com/a?b=1 and shop.example.net, mail me at jan@firma.cz, 10.5 cm'));
        // the rules are the config's: a phrase added there holds at once, the switch turns everything off
        config(['home.intake.spam.strong' => ['držák na kolo']]);
        $this->assertSame(['strong'], $rules('Potřebuji držák na kolo'));
    }

    public function test_what_the_rules_hold_is_stored_as_spam_and_never_reaches_the_model(): void
    {
        $ad = 'Fantastic post but I was wanting to know if you could write a little more on this topic?';
        $r = $this->send(['text' => $ad])->assertCreated();
        // a neutral answer: no tool, no person, nothing to press
        $r->assertJsonPath('held', 'held')->assertJsonPath('route', null)->assertJsonPath('summary', __('router.held'))->assertJsonPath('actions.request', null)->assertJsonPath('actions.reroute', null)->assertJsonPath('actions.tool', null);
        $this->assertSame([], FakeAssistant::$calls, 'the model was not asked');
        $intake = Intake::where('token', $r->json('token'))->firstOrFail();
        $this->assertSame(Intake::STATUS_SPAM, $intake->status);
        $this->assertNull($intake->route);
        $this->assertNull($intake->cost_czk);
        $this->assertSame(['strong'], $intake->answer['spam']['rules']);
        $this->assertSame($ad, $intake->text);
        // counted as spam, not as a wish (a wish would confirm the visit as a person's)
        $this->assertSame(['kind' => 'text', 'code' => 'strong'], Event::where('type', 'intake_spam')->firstOrFail()->meta);
        $this->assertSame(0, Event::whereIn('type', ['intake_submitted', 'intake_routed'])->count());
        // the token of a held wish opens nothing further
        $this->post('/intake/'.$intake->token.'/request', ['email' => 'a@example.com', 'consent' => '1'], ['Accept' => 'application/json'])->assertNotFound();
        $this->post('/intake/'.$intake->token.'/reroute', [], ['Accept' => 'application/json'])->assertStatus(422);

        // the unseen field filled in; sent sooner than a person can; no stamp of the page at all (a bare POST); a stamp nobody signed
        $honest = 'Busta mého táty z fotky, asi 12 cm vysoká, jedna barva.';
        $this->send(['text' => $honest, 'topic' => 'http://x.example.com'])->assertCreated()->assertJsonPath('held', 'held');
        $this->send(['text' => $honest], 1)->assertCreated()->assertJsonPath('held', 'held');
        $this->withHeaders(['User-Agent' => self::PHONE])->post('/intake', ['text' => $honest], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('held', 'held');
        $this->withHeaders(['User-Agent' => self::PHONE])->post('/intake', ['text' => $honest, 'started' => (now()->timestamp - 60).'.'.str_repeat('0', 20)], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('held', 'held');
        $this->assertSame(['honeypot', 'too_fast', 'no_stamp', 'no_stamp'], Intake::where('status', 'spam')->orderBy('id')->skip(1)->take(4)->get()->map(fn (Intake $i) => $i->answer['spam']['rules'][0])->all());
        $this->assertSame([], FakeAssistant::$calls);

        // a bare question about the price: not advertising, but nothing to decide; the answer about prices, no model
        $this->send(['text' => 'Hi, I wanted to know your price.'], 30, '/en')->assertCreated()->assertJsonPath('held', 'price')->assertJsonPath('summary', __('router.price_only', [], 'en'));
        $this->assertSame([], FakeAssistant::$calls);
        $this->assertSame('price_only', Event::where('type', 'intake_spam')->latest('id')->first()->meta['code']);

        // the same honest wish sent the way the page sends it goes to the model; so does the unclear "mia"
        FakeAssistant::$answers['intake'] = ['route' => 'tool', 'tool_key' => 'figure', 'params' => [], 'summary' => ['cs' => 'Busta.', 'en' => 'A bust.', 'es' => 'Un busto.'], 'questions' => [], 'confidence' => 0.9];
        $this->send(['text' => $honest])->assertCreated()->assertJsonPath('held', null)->assertJsonPath('route', 'tool');
        $this->send(['text' => 'mia'])->assertCreated()->assertJsonPath('held', null);
        $this->assertCount(2, FakeAssistant::$calls);
        // a 3D file is not read for words (its text is a note, no model is asked); the trap and the stamp still count
        config(['home.intake.spam.enabled' => false]);
        $this->withHeaders(['User-Agent' => self::PHONE])->post('/intake', ['text' => $ad], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('held', null);
    }

    public function test_the_admin_does_not_see_spam_until_asked_and_can_give_it_back(): void
    {
        FakeAssistant::$answers['intake'] = ['route' => 'tool', 'tool_key' => 'figure', 'params' => [], 'summary' => ['cs' => 'Busta 12 cm.', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $good = $this->send(['text' => 'Busta mého táty z fotky, 12 cm.'])->json('token');
        $spam = $this->send(['text' => 'Looking to improve your posture? Order now, free shipping.'])->json('token');
        $wrong = $this->send(['text' => 'Потрібна фігурка собаки 10 см'])->assertJsonPath('held', 'held')->json('token');
        $this->assertCount(1, FakeAssistant::$calls);

        $admin = $this->admin();
        $list = (string) $this->actingAs($admin)->get('/admin/intake?show=all')->assertOk()->getContent();
        $this->assertStringContainsString('Busta mého táty', $list);
        $this->assertStringNotContainsString('improve your posture', $list);
        $this->assertStringNotContainsString('data-spam ', $list);
        $this->assertStringContainsString('Ukázat i spam (2)', $list);
        $this->assertStringNotContainsString('improve your posture', (string) $this->actingAs($admin)->get('/admin/intake')->getContent(), 'nor among the requests');
        $with = (string) $this->actingAs($admin)->get('/admin/intake?show=all&spam=1')->assertOk()->getContent();
        $this->assertStringContainsString('improve your posture', $with);
        $this->assertStringContainsString('reklamní fráze', $with);
        $this->assertStringContainsString('cizí písmo bez fotky', $with);
        $this->assertStringContainsString('Skrýt spam (2)', $with);

        // the detail says why; "not spam" gives it back and the model decides it now
        $this->actingAs($admin)->get('/admin/intake/'.$wrong)->assertOk()->assertSee('Zadrženo jako spam, modelu se neposlalo.')->assertSee('cizí písmo bez fotky')->assertSee('Není to spam: vrátit a nechat vyhodnotit');
        FakeAssistant::$answers['intake'] = ['route' => 'tool', 'tool_key' => 'pet', 'params' => ['size_mm' => 100], 'summary' => ['cs' => 'Figurka psa 10 cm.', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $this->actingAs($admin)->post('/admin/intake/'.$wrong.'/spam', ['spam' => 0])->assertRedirect('/admin/intake/'.$wrong);
        $back = Intake::where('token', $wrong)->firstOrFail();
        $this->assertSame([Intake::STATUS_NEW, 'tool', 'pet'], [$back->status, $back->route, $back->tool_key]);
        $this->assertArrayNotHasKey('spam', $back->answer);
        $this->assertSame(['script'], $back->answer['released']['was']['rules']);
        $this->assertCount(2, FakeAssistant::$calls);
        $this->assertStringContainsString('Потрібна', (string) $this->actingAs($admin)->get('/admin/intake?show=all')->getContent());

        // and the other way: a wish the rules let through is held by hand, its decision stays; given back it is not decided twice
        $this->actingAs($admin)->get('/admin/intake/'.$good)->assertOk()->assertSee('Označit jako spam');
        $this->actingAs($admin)->post('/admin/intake/'.$good.'/spam', ['spam' => 1])->assertRedirect();
        $held = Intake::where('token', $good)->firstOrFail();
        $this->assertSame([Intake::STATUS_SPAM, 'tool', ['manual']], [$held->status, $held->route, $held->answer['spam']['rules']]);
        $this->assertStringNotContainsString('Busta mého táty', (string) $this->actingAs($admin)->get('/admin/intake?show=all')->getContent());
        $this->actingAs($admin)->post('/admin/intake/'.$good.'/spam', ['spam' => 0])->assertRedirect();
        $this->assertSame([Intake::STATUS_NEW, 'figure'], [$held->fresh()->status, $held->fresh()->tool_key]);
        $this->assertCount(2, FakeAssistant::$calls);
        $this->assertSame(Intake::STATUS_SPAM, Intake::where('token', $spam)->firstOrFail()->status);
        // the word fits the column that keeps it (intakes.status is varchar(16))
        $this->assertLessThanOrEqual(16, strlen(Intake::STATUS_SPAM));
    }

    public function test_the_hourly_and_daily_limits_are_the_configs(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        config(['home.intake.limits.per_hour' => 2, 'home.intake.limits.per_day' => 3]);
        FakeAssistant::$answers['intake'] = ['route' => 'human', 'tool_key' => null, 'params' => [], 'summary' => ['cs' => 'x', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $this->send(['text' => 'busta 1'])->assertCreated();
        // advertising counts in the limit too
        $this->send(['text' => 'Fantastic post, great post'])->assertCreated()->assertJsonPath('held', 'held');
        $this->send(['text' => 'busta 3'])->assertStatus(429);
        // an hour later one more fits the day, then the day is full
        $this->travel(61)->minutes();
        $this->send(['text' => 'busta 4'])->assertCreated();
        $this->send(['text' => 'busta 5'])->assertStatus(429);
        $this->travel(61)->minutes();
        $this->send(['text' => 'busta 6'])->assertStatus(429);
    }

    // ── 4: the numbers ───────────────────────────────────────────────────────────────────────────────────────────

    public function test_what_a_visitor_did_before_sending_is_counted(): void
    {
        $say = fn (array $data, string $prefix = '') => $this->withHeaders(['User-Agent' => self::PHONE])->postJson($prefix.'/intake/event', $data)->assertNoContent();
        $say(['type' => 'view']);
        $say(['type' => 'chip', 'code' => 'mikulas.1']);
        $say(['type' => 'chip', 'code' => 'steady.2'], '/en');
        $say(['type' => 'photo']);
        $say(['type' => 'voice']);
        // what is no event of the page, and a code that is no code of an example, adds nothing of its own
        $say(['type' => 'order_paid']);
        $say(['type' => 'chip', 'code' => 'Fantastic post <a href=x>']);
        $this->assertSame(['intake_view' => 1, 'intake_chip' => 3, 'intake_photo' => 1, 'intake_voice' => 1], Event::where('type', 'like', 'intake_%')->get()->countBy('type')->all());
        $this->assertSame(['mikulas.1', 'steady.2', null], Event::where('type', 'intake_chip')->orderBy('id')->get()->map(fn (Event $e) => $e->meta['code'] ?? null)->all());
        $this->assertSame('en', Event::where('type', 'intake_chip')->orderBy('id')->skip(1)->first()->locale);
        $this->assertSame('intake', Event::where('type', 'intake_view')->first()->subject_type);
        // a robot's "view" is no view
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->postJson('/intake/event', ['type' => 'view'])->assertNoContent();
        $this->assertSame(1, Event::where('type', 'intake_view')->count());

        // sending: what it began with (words / words and a photo / a photo / a file) and the example, changed or sent as it was
        FakeAssistant::$answers['intake'] = ['route' => 'human', 'tool_key' => null, 'params' => [], 'summary' => ['cs' => 'x', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $example = __('router.hint.mikulas.1.fill');
        $this->send(['text' => $example, 'hint' => 'mikulas.1'])->assertCreated();
        $this->send(['text' => $example.' Obvod hlavy 52 cm.', 'hint' => 'mikulas.1'])->assertCreated();
        $this->send(['text' => 'Držák na kolo'])->assertCreated();
        $photo = new UploadedFile(base_path('tests/fixtures/intake/photo.jpg'), 'photo.jpg', 'image/jpeg', null, true);
        $this->send(['photo' => $photo])->assertCreated();
        $this->assertSame([
            ['kind' => 'text', 'has_text' => true, 'code' => 'text', 'hint' => 'mikulas.1', 'edited' => false],
            ['kind' => 'text', 'has_text' => true, 'code' => 'text', 'hint' => 'mikulas.1', 'edited' => true],
            ['kind' => 'text', 'has_text' => true, 'code' => 'text'],
            ['kind' => 'photo', 'has_text' => false, 'code' => 'photo'],
        ], Event::where('type', 'intake_submitted')->orderBy('id')->get()->map(fn (Event $e) => $e->meta)->all());
        // a month later one query says what people begin with
        $this->assertSame(['text' => 3, 'photo' => 1], Event::where('type', 'intake_submitted')->get()->countBy(fn (Event $e) => $e->meta['code'])->all());
    }

    public function test_the_staffs_browser_is_left_out_of_the_numbers(): void
    {
        // Roman signed in as the admin: his clicks, his wishes and his test advertisement count nowhere, and the browser stays marked
        $admin = $this->admin();
        $this->actingAs($admin)->withHeaders(['User-Agent' => self::PHONE])->postJson('/intake/event', ['type' => 'view'])->assertNoContent();
        $this->actingAs($admin)->withHeaders(['User-Agent' => self::PHONE])->postJson('/intake/event', ['type' => 'chip', 'code' => 'steady.1'])->assertNoContent();
        $this->actingAs($admin)->send(['text' => 'Fantastic post, great post'])->assertCreated()->assertJsonPath('held', 'held');
        FakeAssistant::$answers['intake'] = ['route' => 'human', 'tool_key' => null, 'params' => [], 'summary' => ['cs' => 'x', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'confidence' => 0.9];
        $this->actingAs($admin)->send(['text' => 'Držák na kolo'])->assertCreated();
        $this->assertSame(0, Event::where('type', 'like', 'intake_%')->whereNull('meta->staff')->count());
        $this->assertTrue((bool) AnonymousSession::query()->latest('id')->first()->staff);
        // a test run of ours (the cookie mp_test) is left out the same way
        $this->withCookie('mp_test', '1')->withHeaders(['User-Agent' => self::PHONE])->postJson('/intake/event', ['type' => 'voice'])->assertNoContent();
        $this->assertSame(0, Event::where('type', 'intake_voice')->count());
    }
}
