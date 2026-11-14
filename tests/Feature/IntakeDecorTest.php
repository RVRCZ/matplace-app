<?php

namespace Tests\Feature;

use App\Console\Commands\IntakeCases;
use App\Domain\Intake\IntakeHints;
use App\Domain\Intake\IntakeRouter;
use App\Engines\Ai\FakeAssistant;
use App\Models\Intake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The router's catalogue after the decorations (docs/AR.md §8): a medal, a key ring, an ornament and a coaster take
 * a photo as a relief (`params.decor` = "photo", read by the tool's page), the ornament for the tree is a tool of its
 * own, and the prompt no longer says that a relief of a photo on a medal cannot be made. No model is called.
 */
class IntakeDecorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        FakeAssistant::reset();
    }

    /** window.MP_TOOL of a tool's page, as its scripts read it. */
    private function shared(string $html): array
    {
        $this->assertSame(1, preg_match('/window\.MP_TOOL = JSON\.parse\(\'(.*?)\'\);|window\.MP_TOOL = (\{.*?\});<\/script>/s', $html, $m), 'the page hands its scripts the shared config');
        $json = isset($m[2]) && $m[2] !== '' ? $m[2] : json_decode('"'.$m[1].'"');

        return (array) json_decode((string) $json, true);
    }

    private function answer(string $tool, array $params): array
    {
        return ['route' => 'tool', 'tool_key' => $tool, 'params' => $params, 'summary' => ['cs' => 'Medaile s reliéfem vaší fotky vpředu.', 'en' => 'x', 'es' => 'x'],
            'questions' => [], 'confidence' => 0.9, 'alternatives' => [], 'reason' => 'a relief on a medal'];
    }

    public function test_the_catalogue_says_what_the_decorated_tools_take_and_the_ornament_is_a_tool(): void
    {
        $tools = IntakeRouter::TOOLS;
        $this->assertSame('a place (1st, 2nd, 3rd), a number, a motif or a short text; or a photo (a face, a pet, a house) that becomes a relief on the front, with a name or a year on the back', $tools['medallion']['takes']);
        $this->assertStringEndsWith(', or with a relief of a photo on its front', $tools['medallion']['makes']);
        $this->assertSame('a name or a short text, a logo or a small picture, or a photo that becomes a small relief', $tools['keychain']['takes']);
        $this->assertStringEndsWith('; a logo and a text side by side, a text engraved on the back', $tools['keychain']['makes']);
        $this->assertStringEndsWith('; or a photo that becomes a relief sunk into the coaster, with a text', $tools['coaster']['takes']);
        $this->assertSame([
            'takes' => 'a photo (a face, a pet), a drawing, a logo, or a name with a year',
            'makes' => 'a Christmas tree ornament with an eyelet for a ribbon: a relief of the photo or the picture in filament colours on the front, a name and a year on the back',
            'when' => 'a Christmas ornament, a bauble, a gift tag for the tree', 'size' => [40, 150], 'colors' => 4,
        ], $tools['ornament']);
        $this->assertStringEndsWith('a relief on a medal, a key ring, an ornament or a coaster is that tool with params.decor = "photo"', $tools['relief']['when']);
        $this->assertSame(['medallion', 'keychain', 'ornament', 'coaster'], IntakeRouter::DECORATED);
        // every tool of the catalogue is a tool of the site with a public name in three languages
        foreach (array_keys($tools) as $key) {
            $this->assertNotNull(config('tools.'.$key), $key);
            foreach (['cs', 'en', 'es'] as $lang) {
                $this->assertNotSame('tools.'.$key.'.title', __('tools.'.$key.'.title', [], $lang), "$key $lang");
            }
        }

        // the words: the ornament for the tree is the ornament's, no longer the charm's; a pendant stays a charm
        foreach (['Ozdoba na stromek se jménem Ema, 8 cm' => 'cs', 'ozdoby na stromeček pro celou rodinu' => 'cs', 'A Christmas tree ornament with the name Emily' => 'en', 'Un adorno para el árbol de Navidad' => 'es', 'Un adorno de árbol con nombre' => 'es'] as $text => $lang) {
            $d = IntakeRouter::guess($text, false, $lang);
            $this->assertSame(['tool', 'ornament'], [$d['route'], $d['tool_key']], $text);
            $this->assertStringContainsString(__('tools.ornament.title', [], $lang), $d['summary'], $text);
        }
        $this->assertSame(80, IntakeRouter::guess('Ozdoba na stromek se jménem Ema, 8 cm', false)['params']['size_mm']);
        $this->assertSame(150, IntakeRouter::params(['size_mm' => 400], 'ornament')['size_mm'], 'the size stays inside the tool\'s range');
        foreach (['Přívěsek na klíče s logem klubu' => 'charm', 'Kresba mé dcery jako přívěsek' => 'charm', 'Klíčenka se jménem Jana.' => 'keychain', 'Sada podtácků s fotkou' => 'coaster'] as $text => $tool) {
            $this->assertSame($tool, IntakeRouter::guess($text, false)['tool_key'], $text);
        }
        $keys = array_keys((new \ReflectionClassConstant(IntakeRouter::class, 'WORDS'))->getValue());
        $this->assertLessThan(array_search('charm', $keys, true), array_search('ornament', $keys, true), 'the ornament is asked before the charm');
        $this->assertSame(0, preg_match((new \ReflectionClassConstant(IntakeRouter::class, 'WORDS'))->getValue()['charm'], 'ozdoba na stromek ornament adorno para el árbol'), 'the ornament words left the charm');

        // the first screen's Christmas chip goes to the ornament, and every example still goes where the config says
        $this->assertSame('ornament', config('home.intake.hints.events.christmas.examples')[0]);
        $this->assertSame('Ozdoba na stromek se jménem', IntakeHints::examples('christmas', 'cs')[0]['label']);
        $cases = IntakeCases::hintCases();
        $this->assertCount(125, $cases);
        foreach ($cases as $case) {
            $d = IntakeRouter::guess($case['text'], false, $case['locale']);
            $this->assertSame([$case['route'], $case['tool']], [$d['route'], $d['tool_key']], $case['code']);
        }
        $this->assertSame(['ornament', 'ornament', 'ornament'], array_column(array_values(array_filter($cases, fn ($c) => str_starts_with($c['code'], 'christmas.1 '))), 'tool'));
    }

    public function test_a_photo_for_a_relief_on_a_medal_is_handed_over_as_a_decoration(): void
    {
        // what the model may say and what is kept: "photo" only, only for the tools with decorations, only with a photo
        foreach (IntakeRouter::DECORATED as $tool) {
            $this->assertSame('photo', IntakeRouter::decide($this->answer($tool, ['decor' => 'photo', 'text' => 'Jana 2026']), true, 'cs')['params']['decor'], $tool);
            $this->assertArrayNotHasKey('decor', IntakeRouter::decide($this->answer($tool, ['decor' => 'photo']), false, 'cs')['params'], "$tool without a photo");
            $this->assertArrayNotHasKey('decor', IntakeRouter::decide($this->answer($tool, ['decor' => 'logo']), true, 'cs')['params'], "$tool: an unknown value");
            $this->assertArrayNotHasKey('decor', IntakeRouter::decide($this->answer($tool, ['decor' => null]), true, 'cs')['params'], "$tool: null");
        }
        foreach (['charm', 'figure', 'topper', 'relief', 'sign'] as $tool) {
            $this->assertArrayNotHasKey('decor', IntakeRouter::decide($this->answer($tool, ['decor' => 'photo']), true, 'cs')['params'], $tool);
        }
        $d = IntakeRouter::decide($this->answer('medallion', ['decor' => 'photo', 'text' => 'Jana 2026', 'size_mm' => 60]), true, 'cs');
        $this->assertSame(['size_mm' => 60, 'text' => 'Jana 2026', 'decor' => 'photo'], $d['params']);
        $this->assertSame('Medaile s reliéfem vaší fotky vpředu.', $d['summary'], 'the model\'s own honest sentence is kept');

        // the schema lets the model say it, the prompt tells it when and no longer says a medal cannot carry a relief
        $schema = IntakeRouter::schema();
        $this->assertSame(['type' => ['string', 'null']], $schema['properties']['params']['properties']['decor']);
        $this->assertContains('decor', $schema['properties']['params']['required']);
        $this->assertSame(10, IntakeRouter::unionCount(), 'under the API\'s limit of 16');
        $system = IntakeRouter::system();
        foreach (['A medal, a key ring, a Christmas ornament and a coaster DO take a photo as a decoration', 'set `params.decor` to "photo" and say it honestly in `summary`',
            'the photo becomes a relief in one colour on the front of the thing', 'the preview shows it before anything is ordered', 'a name or a year from the words goes on the back (`params.text`)',
            'When no photo is attached, choose that tool all the same', '`decor`: "photo" when the attached photo should become a relief', 'a Christmas tree ornament, a bauble, a gift tag for the tree → ornament'] as $rule) {
            $this->assertStringContainsString($rule, $system, $rule);
        }
        foreach (['the medal tool takes no photo', 'a relief of a photo on it is not something we can make yet', 'whose tool takes a place, a number, a motif or a text'] as $gone) {
            $this->assertStringNotContainsString($gone, $system, $gone);
        }
        $user = IntakeRouter::user(new Intake(['text' => 'Chci medaili s reliéfem z fotky', 'locale' => 'cs']));
        $this->assertStringContainsString("\nornament | ".__('tools.ornament.title', [], 'cs').' / '.__('tools.ornament.title', [], 'en').' / '.__('tools.ornament.title', [], 'es').' | a photo (a face, a pet), a drawing', $user);
        $this->assertStringContainsString('or a photo (a face, a pet, a house) that becomes a relief on the front', $user);

        // the golden cases 31 and 32: the medal, with the relief only when a photo came; the keywords say it honestly too
        $golden = json_decode((string) file_get_contents(base_path('tests/fixtures/intake/cases.json')), true);
        $this->assertSame(['Chci medaili s reliéfem z fotky', false, ['medallion'], ['decor' => null]], [$golden[30]['text'], $golden[30]['photo'], $golden[30]['tools'], $golden[30]['params']]);
        $this->assertSame(['Chci medaili s reliéfem z fotky', true, ['medallion'], ['decor' => 'photo']], [$golden[31]['text'], $golden[31]['photo'], $golden[31]['tools'], $golden[31]['params']]);
        $with = IntakeRouter::guess('Chci medaili s reliéfem z fotky', true, 'cs');
        $this->assertSame(['tool', 'medallion', 'photo'], [$with['route'], $with['tool_key'], $with['params']['decor'] ?? null]);
        $this->assertStringContainsString('Fotku na něj položíme jako reliéf v jedné barvě; jak vyjde, uvidíte v náhledu.', $with['summary']);
        $without = IntakeRouter::guess('Chci medaili s reliéfem z fotky', false, 'cs');
        $this->assertSame(['tool', 'medallion', null], [$without['route'], $without['tool_key'], $without['params']['decor'] ?? null]);
        $this->assertStringNotContainsString('položíme jako reliéf', $without['summary'], 'no photo, no promise of what happens to it');
        $this->assertArrayNotHasKey('decor', IntakeRouter::guess('Medaile za 1. místo s fotkou', true, 'cs')['params'], 'a medal without the word relief keeps the photo as a picture');

        // the tool's page gets the parameter with the photo: its script lays the photo on the front as a relief (param.ts)
        $token = str_repeat('d', 20).'TOK9';
        Storage::disk('local')->put('intake/'.$token.'/original.jpg', (string) file_get_contents(base_path('tests/fixtures/intake/photo.jpg')));
        Storage::disk('local')->put('intake/'.$token.'/model.jpg', (string) file_get_contents(base_path('tests/fixtures/intake/photo.jpg')));
        $intake = Intake::create(['token' => $token, 'kind' => 'photo', 'photo_path' => 'intake/'.$token.'/original.jpg', 'photo_model_path' => 'intake/'.$token.'/model.jpg',
            'text' => 'Chci medaili s reliéfem z fotky', 'locale' => 'cs', 'route' => 'tool', 'tool_key' => 'medallion',
            'params' => $d['params'], 'confidence' => 0.9, 'summary' => $d['summary'], 'status' => 'new']);
        foreach (['medallion' => '/tools/medallion', 'ornament' => '/tools/ornament', 'coaster' => '/tools/coaster', 'keychain' => '/tools/keychain?form=1'] as $tool => $path) {
            $intake->update(['tool_key' => $tool]);
            $html = (string) $this->get($path.(str_contains($path, '?') ? '&' : '?').'intake='.$token)->assertOk()->assertSee('id="decor-panel"', false)->getContent();
            $this->assertMatchesRegularExpression('~<input id="param-artwork" data-intake-photo~', $html, "$tool: the field the photo lands in");
            $shared = $this->shared($html)['intake'];
            $this->assertSame(['photo', 'Jana 2026', url('/intake/'.$token.'/file')], [$shared['params']['decor'] ?? null, $shared['params']['text'] ?? null, $shared['file']], $tool);
        }
    }
}
