<?php

namespace Tests\Feature;

use App\Domain\Farm\Palette;
use App\Domain\Intake\IntakeHintCheck;
use App\Domain\Intake\IntakeHints;
use App\Domain\Intake\IntakeMotif;
use App\Domain\Intake\IntakePlace;
use App\Domain\Intake\IntakePrefill;
use App\Domain\Intake\IntakeRouter;
use App\Domain\Tools\Artwork;
use App\Domain\Tools\MapData;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Ai\Assistant;
use App\Engines\Ai\ClaudeAssistant;
use App\Engines\Ai\FakeAssistant;
use App\Models\Intake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every example under the home page's field ends on a design that matches its sentence (docs/AN.md §25, Roman
 * 14 Nov 2026: beads came out without their colours, the map showed another town, a charm opened with our logo).
 * The rule a new hint has to pass: what its sentence names (a name, a number, a size, colours, a count, a shape, a
 * place) is in the form the tool's page opens with, and no page opens with the tool's own sample as if that were the
 * answer: a tool that needs a picture or a photo asks for it. The walk in a real browser is scripts/hints_walk.mjs.
 */
class IntakeHintsMatchTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        FakeAssistant::reset();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withHeaders(['User-Agent' => self::PHONE, 'Accept-Language' => 'cs']);
        config(['home.intake.spam.require_stamp' => false, 'farm.settings.intake_reply_hours' => 24]);
        // no key: the keyword router decides, the weakest reader (the model's answer is completed by the same words;
        // the test that says so puts the fake assistant back)
        $this->app->instance(Assistant::class, new ClaudeAssistant(['api_key' => '']));
    }

    private function send(array $data): TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::PHONE])->post('/intake', $data, ['Accept' => 'application/json']);
    }

    /** What the tool's page is handed: window.MP_TOOL.intake. */
    private function handed(string $go): array
    {
        $html = (string) $this->get($go)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/window\.MP_TOOL = JSON\.parse\(\'(.*?)\'\);|window\.MP_TOOL = (\{.*?\});<\/script>/s', $html, $m), 'the page hands its scripts the shared config');
        $json = isset($m[2]) && $m[2] !== '' ? $m[2] : json_decode('"'.$m[1].'"');

        return (array) ((array) json_decode((string) $json, true))['intake'];
    }

    private function answer(string $tool, array $params = [], float $confidence = 0.9): array
    {
        return [
            'route' => 'tool', 'tool_key' => $tool,
            'params' => $params + ['size_mm' => null, 'colors' => [], 'copies' => null, 'text' => null, 'place' => null, 'kind' => null, 'decor' => null, 'text2' => null, 'number' => null, 'shape' => null],
            'summary' => ['cs' => 'Věta asistenta.', 'en' => 'The assistant\'s sentence.', 'es' => 'La frase del asistente.'],
            'questions' => [], 'alternatives' => [], 'reason' => 'test', 'prompt_en' => '', 'photo' => 'none', 'confidence' => $confidence,
        ];
    }

    // ── the rule: every hint, every language ─────────────────────────────────────────────────────────────

    public function test_every_hint_of_every_language_ends_on_a_design_that_matches_its_sentence(): void
    {
        $hints = IntakeHintCheck::all(null, 2026);
        $this->assertGreaterThanOrEqual(120, count($hints), 'every event, every example, every language it is offered in');
        foreach ($hints as $h) {
            $where = "{$h['code']} {$h['locale']}: {$h['fill']}";
            $this->assertSame($h['wanted'], $h['tool'], "$where goes where config/home.php says");
            $this->assertSame([], $h['lost'], "$where: the page opens with everything the sentence names");
            // never the tool's own sample: the page has something of the wish to show, or it asks for what is missing
            $this->assertTrue($h['understood'] !== [] || $h['ask'] !== null, "$where: something of the wish is on the page");
            if (in_array($h['tool'], IntakePrefill::PICTURE_TOOLS, true) && $h['tool'] !== 'medallion') {
                $this->assertArrayHasKey('artwork', $h['values'], "$where: the tool's sample picture is not left in place");
                $this->assertTrue($h['values']['_plain'] ?? false, "$where: the page leaves its own preset out");
                $this->assertTrue($h['values']['artwork'] !== '' || ($h['values']['line1'] ?? '') !== '' || $h['ask'] === 'picture', "$where: a picture of the library, words, or the page asks");
            }
            if (in_array($h['tool'], IntakePrefill::PHOTO_TOOLS, true)) {
                $this->assertSame('photo', $h['ask'], "$where: the page asks for the photo");
            }
            // every value is a field of the tool's own form, inside what the form takes
            $kind = IntakePrefill::KIND[$h['tool']] ?? null;
            foreach ($kind === null ? [] : $h['values'] as $key => $value) {
                if (in_array($key, ['preset', 'part_colors', 'artwork', '_plain', '_qty', '_material'], true)) {
                    continue;
                }
                $fields = ParametricGenerator::FIELDS[$kind];
                $known = isset($fields[$key]) || isset(ParametricGenerator::TEXTS[$kind][$key]) || isset(ParametricGenerator::CHOICES[$kind][$key]) || in_array($key, ParametricGenerator::FLAGS[$kind] ?? [], true);
                $this->assertTrue($known, "$where: $key is a field of the $kind form");
                if (isset($fields[$key])) {
                    $this->assertTrue($value >= $fields[$key][0] && $value <= $fields[$key][1], "$where: $key = $value is inside the form's range");
                }
                if (isset(ParametricGenerator::CHOICES[$kind][$key])) {
                    $this->assertContains($value, ParametricGenerator::CHOICES[$kind][$key], "$where: $key is a choice the form has");
                }
                if (isset(ParametricGenerator::TEXTS[$kind][$key])) {
                    $this->assertLessThanOrEqual(ParametricGenerator::TEXTS[$kind][$key][0], mb_strlen((string) $value), "$where: $key fits its field uncut");
                }
            }
            foreach ((array) ($h['values']['part_colors'] ?? []) as $part => $hex) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', (string) $hex, "$where: the colour of $part is a colour of the colour window");
                $this->assertContains($hex, Palette::BASIC);
            }
            if (isset($h['values']['preset'])) {
                $this->assertArrayHasKey($h['values']['preset'], ParametricGenerator::PRESETS[$kind], "$where: the preset exists");
            }
        }
    }

    /** The sentences of the Czech hints, one by one: what exactly the page opens with (tests/fixtures/intake/hints_expect.json). */
    public function test_the_czech_hints_open_with_exactly_what_their_sentences_say(): void
    {
        $expect = json_decode((string) file_get_contents(base_path('tests/fixtures/intake/hints_expect.json')), true);
        $hints = collect(IntakeHintCheck::all(['cs'], 2026))->keyBy('code');
        foreach ($hints as $code => $h) {
            $this->assertArrayHasKey($code, $expect, "$code: a new hint says in tests/fixtures/intake/hints_expect.json what its page opens with (docs/AN.md §25)");
        }
        foreach ($expect as $code => $want) {
            $h = $hints[$code] ?? null;
            $this->assertNotNull($h, "$code is a hint of config/home.php");
            $this->assertSame($want['sentence'], $h['fill'], "$code: the sentence changed, say again in the fixture what its page opens with");
            $this->assertSame($want['tool'], $h['tool'], $code);
            foreach ($want['values'] ?? [] as $key => $value) {
                $this->assertEquals($value, $h['values'][$key] ?? null, "$code: $key ({$h['fill']})");
            }
            foreach ($want['params'] ?? [] as $key => $value) {
                $this->assertEquals($value, $h['params'][$key] ?? null, "$code: $key ({$h['fill']})");
            }
            $this->assertSame($want['ask'] ?? null, $h['ask'], "$code: what the page asks for");
        }
    }

    // ── the four things Roman found ──────────────────────────────────────────────────────────────────────

    public function test_beads_open_in_the_colours_of_the_wish(): void
    {
        $r = $this->send(['text' => 'Korálky s písmenky na náramek se jménem EMA, růžové s bílými písmeny.'])->assertCreated();
        $r->assertJsonPath('route', 'tool')->assertJsonPath('tool_key', 'beads');
        $this->assertStringContainsString('/tools/letter-beads?intake=', (string) $r->json('go'));
        $page = $this->handed((string) $r->json('go'));
        $this->assertSame('EMA', $page['values']['line1']);
        $this->assertSame(['body' => Palette::BASIC['pink'], 'text' => Palette::BASIC['white']], $page['values']['part_colors'], 'the beads pink, the letters white');
        $this->assertSame('raised', $page['values']['style'], 'letters of their own colour stand out');
        $this->assertContains('barvy: '.__('toolpage.color.basic.pink').', '.__('toolpage.color.basic.white'), $page['understood']);
        $this->assertNull($page['ask']);

        // one colour: the letters get black or white, whichever reads on it; the model's answer is completed the same way
        $this->assertSame(['body' => Palette::BASIC['black'], 'text' => Palette::BASIC['white']], IntakePrefill::values('beads', ['text' => 'TOM', 'colors' => ['black']])['part_colors']);
        $this->assertSame(['body' => Palette::BASIC['yellow'], 'text' => Palette::BASIC['black']], IntakePrefill::values('beads', ['text' => 'TOM', 'colors' => ['yellow']])['part_colors']);
        $this->assertSame('beads', IntakeRouter::guess('beads with the name JANA, blue with white letters', false, 'en')['tool_key']);
        $this->assertSame('beads', IntakeRouter::guess('cuentas con letras con el nombre ANA', false, 'es')['tool_key']);
        $this->assertArrayHasKey('beads', IntakeRouter::TOOLS);
        $this->assertStringContainsString('beads | ', IntakeRouter::user(new Intake(['text' => 'x', 'locale' => 'cs'])));
    }

    public function test_a_map_opens_with_the_place_the_wish_names(): void
    {
        foreach ([
            '3D mapa Prahy jako dárek, 15 cm.' => 'Praha', '3D mapa Olomouce jako dárek, 15 cm.' => 'Olomouc', 'mapa města Plzeň' => 'Plzeň', 'Chci mapu Plzně' => 'Plzeň', '3D mapa Brna' => 'Brno',
            'mapa vesnice Útušice' => 'Útušice', 'mapa okolí Náměstí Míru 5, Praha' => 'Náměstí Míru 5, Praha', 'A 3D map of Bath as a gift, 15 cm.' => 'Bath', 'Un mapa 3D de Toledo como regalo, 15 cm.' => 'Toledo',
            'mapa Českých Budějovic' => 'České Budějovice', 'mapa Ústí nad Labem, 20 cm' => 'Ústí nad Labem',
        ] as $wish => $place) {
            $this->assertSame($place, IntakePrefill::fromWords($wish)['place'] ?? null, $wish);
            $d = IntakeRouter::guess($wish, false, 'cs');
            $this->assertSame('map', $d['tool_key'], $wish);
            $this->assertSame($place, $d['params']['place'] ?? null, "$wish: the keyword router hands the place over too");
        }
        // no place named: nothing is made up, the page asks
        foreach (['3D mapa našeho města', 'A 3D map of our town', 'mapa na Vánoce'] as $wish) {
            $this->assertArrayNotHasKey('place', IntakePrefill::fromWords($wish), $wish);
        }

        $r = $this->send(['text' => '3D mapa Prahy jako dárek, 15 cm.'])->assertCreated();
        $page = $this->handed((string) $r->json('go'));
        $this->assertSame('Praha', $page['params']['place']);
        $this->assertSame(150, $page['params']['size_mm']);
        $this->assertContains('místo: Praha', $page['understood']);
        $this->assertNull($page['ask']);

        $r = $this->send(['text' => '3D mapa našeho města jako dárek.'])->assertCreated()->assertJsonPath('tool_key', 'map');
        $page = $this->handed((string) $r->json('go'));
        $this->assertArrayNotHasKey('place', $page['params']);
        $this->assertSame(['kind' => 'place', 'text' => 'Napište město nebo adresu a mapu hned uvidíte.', 'button' => null], $page['ask']);

        // the model's own place stands (it is told to give the name as a map calls it); what it leaves out the words give
        $this->assertSame('Olomouc', IntakePrefill::complete(['place' => 'Olomouc'], '3D mapa Olomouce', 'map')['place']);
        $this->assertSame('Praha', IntakePrefill::complete(['size_mm' => 150], '3D mapa Prahy, 15 cm', 'map')['place']);
        $this->assertStringContainsString('as a map names it', IntakeRouter::system());
    }

    public function test_a_czech_name_is_looked_up_as_the_map_calls_it(): void
    {
        $this->assertSame(['Praha'], IntakePlace::tries('Prahy'));
        $this->assertSame(['Praha'], IntakePlace::tries('Praha'));
        $this->assertSame(['Karlovy Vary'], IntakePlace::tries('Karlových Varů'));
        $this->assertSame(['Útušice', 'Útušic'], IntakePlace::tries('Útušic'));
        $this->assertSame(['Útušice'], IntakePlace::tries('Útušice'));
        $this->assertSame('Lhota', IntakePlace::tries('Lhoty')[0]);
        $this->assertContains('Lhota', IntakePlace::tries('Lhota'), 'a first case typed is still asked for as written');
        $this->assertSame(['Bath'], IntakePlace::tries('Bath', 'en'));
        $this->assertSame(['Sevilla'], IntakePlace::tries('Sevilla', 'es'), 'only Czech names change their endings');
        $this->assertSame(['Dlouhá 12, Plzeň'], IntakePlace::tries('Dlouhá 12, Plzeň'), 'an address is asked for as written');

        // Nominatim takes the words as they are: "Prahy" is a hamlet in France. The name of the map is asked for first.
        Cache::flush();
        $place = fn (string $name, string $type, string $country) => ['name' => $name, 'display_name' => $name.', '.$country, 'lat' => '50.1', 'lon' => '14.4', 'addresstype' => $type, 'address' => ['country' => $country]];
        Http::fake(function ($request) use ($place) {
            $q = $request->data()['q'] ?? '';

            return Http::response(match ($q) {
                'Praha' => [$place('Praha', 'city', 'Česko')],
                'Prahy' => [$place('Prahy', 'hamlet', 'Francie')],
                'Útušice' => [$place('Zoo Útušice', 'tourism', 'Česko'), $place('Útušice', 'village', 'Česko')],
                'Kocourkov' => [$place('Hostinec Kocourkov', 'amenity', 'Česko')],
                default => [],
            });
        });
        $data = app(MapData::class);
        $found = $data->places('Prahy', 'cs');
        $this->assertSame(['Praha', 'Česko', true], [$found[0]['name'], $found[0]['country'], $found[0]['exact']]);
        $found = $data->places('Útušic', 'cs');
        $this->assertSame(['Útušice', 'village', true], [$found[0]['name'], $found[0]['kind'], $found[0]['exact']], 'the village, not the zoo named after it');
        $this->assertCount(2, $found);
        $found = $data->places('Kocourkov', 'cs');
        $this->assertSame('Hostinec Kocourkov', $found[0]['name']);
        $this->assertArrayNotHasKey('exact', $found[0], 'nothing of that very name: the visitor picks');
        $this->assertSame([], $data->places('Nicnenajdu', 'cs'));
        Http::assertSent(fn ($request) => str_contains((string) $request->header('User-Agent')[0], 'matplace.com'));
    }

    public function test_the_tree_ornament_hint_says_ema(): void
    {
        foreach (['cs' => 'Ema', 'en' => 'Emma', 'es' => 'Ema'] as $locale => $name) {
            $fill = (string) __('router.hint.christmas.1.fill', [], $locale);
            $this->assertStringContainsString($name, $fill);
            $this->assertStringNotContainsString('Elišk', $fill);
            $this->assertStringNotContainsString('Emily', $fill);
            $this->assertStringNotContainsString('Elisa', $fill);
        }
        // the older source the texts were born in says the same: applying it again does not bring Eliška back
        foreach (glob(base_path('lang/src/*.json')) as $file) {
            $this->assertStringNotContainsString('Elišk', (string) file_get_contents($file), basename($file));
        }
        $r = $this->send(['text' => (string) __('router.hint.christmas.1.fill', [], 'cs'), 'hint' => 'christmas.1'])->assertCreated()->assertJsonPath('tool_key', 'ornament');
        $page = $this->handed((string) $r->json('go'));
        $this->assertSame('Ema', $page['values']['line1']);
        $this->assertSame('circle', $page['values']['body'], 'a name alone is a disc with the name on it, not loose letters');
        $this->assertSame(80, $page['values']['width']);
        $this->assertSame(['body' => Palette::BASIC['white'], 'color_1' => Palette::BASIC['gold']], $page['values']['part_colors'], 'white with gold lettering');
        $this->assertSame('', $page['values']['artwork'], 'no sample picture of ours under the name');
    }

    public function test_a_charm_never_opens_with_our_logo(): void
    {
        // a motif of our library is the picture
        $r = $this->send(['text' => 'Přívěsek na batoh s andělem k Mikuláši, 5 cm, zlatý.'])->assertCreated()->assertJsonPath('tool_key', 'charm');
        $page = $this->handed((string) $r->json('go'));
        $this->assertSame('lib:holidays/angel', $page['values']['artwork']);
        $this->assertTrue($page['values']['_plain'], 'the page skips its own preset (the Matplace logo)');
        $this->assertArrayNotHasKey('preset', $page['values']);
        $this->assertSame(['body' => Palette::BASIC['gold'], 'color_1' => Palette::BASIC['gold']], $page['values']['part_colors']);
        $this->assertContains('motiv: anděl (z naší knihovny)', $page['understood']);
        $this->assertNull($page['ask']);

        // a motif we have no picture of, and a picture only the visitor has: the page asks, it shows nothing of ours
        foreach (['Přívěsek na batoh s čertem k Mikuláši, 5 cm, tři barvy.', 'Přívěsek z kresby od dcery, 4 cm, dvě barvy.', 'Přívěsek s logem naší třídy, 20 kusů, 4 cm.', 'Náušnice podle mého obrázku', 'Vykrajovátko ve tvaru naší chalupy podle kresby, 7 cm.', 'Podtácek s naší rodinnou fotkou, 10 cm.'] as $wish) {
            $r = $this->send(['text' => $wish])->assertCreated()->assertJsonPath('route', 'tool');
            $page = $this->handed((string) $r->json('go'));
            $this->assertSame('', $page['values']['artwork'], $wish);
            $this->assertTrue($page['values']['_plain'], $wish);
            $this->assertSame('picture', $page['ask']['kind'], $wish);
            $this->assertSame('K tomuhle potřebujeme váš obrázek: nahrajte kresbu, logo nebo fotku, nebo vyberte motiv z naší knihovny.', $page['ask']['text']);
            $this->assertSame($wish, $page['text'], 'the wish goes along: the page shows it under the question');
            foreach (['line1', 'line2'] as $line) {
                $this->assertSame('', $page['values'][$line] ?? '', "$wish: no sample name of the tool");
            }
        }
        // a bust, a pet, a relief, a picture for the wall: nothing without the photo
        foreach (['Busta mého táty z fotky, asi 12 cm vysoká.' => 'figure', 'Figurka našeho psa podle fotky, 10 cm vysoká.' => 'pet', 'Reliéf z fotky naší rodiny' => 'relief'] as $wish => $tool) {
            $r = $this->send(['text' => $wish])->assertCreated()->assertJsonPath('tool_key', $tool);
            $this->assertSame('photo', $this->handed((string) $r->json('go'))['ask']['kind'], $wish);
        }

        // every picture the words can name is in the library, and words about one's own picture never get ours
        foreach (IntakeMotif::refs() as $ref) {
            $this->assertNotNull(Artwork::path($ref), "$ref is in the library");
        }
        $this->assertSame('lib:holidays/bat', IntakeMotif::fromWords('Přívěsek netopýr na batoh', 'charm')['ref']);
        $this->assertSame('lib:cookies/cookie-pumpkin', IntakeMotif::fromWords('Vykrajovátko ve tvaru dýně', 'cutter')['ref'], 'a cutter takes the plain outline');
        $this->assertSame('lib:holidays/easter-bunny', IntakeMotif::fromWords('A cookie cutter in the shape of an Easter bunny', 'cutter')['ref']);
        $this->assertSame('lib:holidays/bat', IntakeMotif::fromWords('Un colgante de murciélago para la mochila', 'charm')['ref']);
        $this->assertNull(IntakeMotif::fromWords('Přívěsek podle kresby netopýra od syna', 'charm'), 'their drawing, not our bat');
        $this->assertNull(IntakeMotif::fromWords('Přívěsek na batoh', 'charm'), 'a backpack is no bat');
    }

    // ── colours, sizes and the head, in general ──────────────────────────────────────────────────────────

    public function test_colours_of_the_wish_reach_every_tool_that_has_colours(): void
    {
        $hex = fn (string ...$keys) => array_map(fn (string $k) => Palette::BASIC[$k], $keys);
        $colors = fn (string $tool, string $wish) => array_values((array) (IntakePrefill::values($tool, IntakeRouter::params(IntakePrefill::complete([], $wish, $tool), $tool), $wish)['part_colors'] ?? []));

        $this->assertSame($hex('white', 'black'), $colors('sign', 'Cedulka se jménem Novákovi, bílá s černým písmem'), 'the plate, the letters');
        $this->assertSame($hex('white', 'gold'), $colors('sign', 'Jmenovka se jménem Anna, bílá se zlatým písmem'), 'gold is no built-in name: it used to break the preview');
        $this->assertSame($hex('pink'), $colors('text', 'Stojící nápis MAMINKA, růžový'));
        $this->assertSame($hex('blue', 'white'), $colors('keychain', 'Klíčenka se jménem Jana, modrá s bílým písmem'));
        $this->assertSame($hex('black', 'silver'), $colors('keychain', 'A keychain saying Best Father, black with silver lettering'));
        $this->assertSame($hex('red', 'white'), $colors('charm', 'Přívěsek ve tvaru srdce se jmény Anna a Petr, červený'), 'one colour: the letters read on it');
        $this->assertSame($hex('black', 'white'), $colors('coaster', 'Podtácek se jménem Karel, černý'));
        $this->assertSame($hex('black', 'black'), $colors('charm', 'Přívěsek netopýr, černý'), 'a silhouette in one colour is the whole thing');
        $this->assertSame($hex('yellow'), $colors('cutter', 'Vykrajovátko ve tvaru hvězdy, žluté'));
        $this->assertSame($hex('pink', 'white'), $colors('beads', 'Korálky se jménem EMA, růžové s bílými písmeny'));
        // a headband: the band and the decoration; a costume named by its own two colours keeps which is which
        $this->assertSame($hex('black', 'red'), $colors('headband', 'Čelenka s čertovskými rohy, červená a černá'), 'a devil: a black band, red horns');
        $this->assertSame($hex('white', 'gold'), $colors('headband', 'Čelenka jednorožec, bílá a zlatá'));
        $this->assertSame($hex('green', 'yellow'), $colors('headband', 'Čelenka s kočičíma ušima, zelená a žlutá'), 'said first: the band');
        $this->assertSame($hex('gold', 'gold'), $colors('headband', 'Čelenka s korunkou, zlatá'));
        // a picture of the visitor's own keeps its colours: only how many of them
        $own = IntakePrefill::values('charm', IntakeRouter::params(IntakePrefill::complete([], 'Přívěsek kuřátko podle dětské kresby, žlutá a oranžová', 'charm'), 'charm'), 'Přívěsek kuřátko podle dětské kresby, žlutá a oranžová');
        $this->assertSame(2, $own['colors_n']);
        $this->assertArrayNotHasKey('part_colors', $own);
        // a name is no colour
        $this->assertArrayNotHasKey('colors', IntakePrefill::fromWords('A desk sign for the teacher with the name Mrs Brown, 15 cm, two colours.'));
        $this->assertSame(2, IntakePrefill::fromWords('A desk sign with the name Mrs Brown, two colours.')['colors_n']);
    }

    public function test_a_size_means_what_people_mean_by_it(): void
    {
        $values = fn (string $tool, string $wish, array $model = []) => IntakePrefill::values($tool, IntakeRouter::params(IntakePrefill::complete($model, $wish, $tool), $tool), $wish);

        // a sign "15 cm wide" is 15 cm across: the letters get the height that makes it so (they used to be set to 8 cm)
        $sign = $values('sign', 'Cedulka na dveře se jménem Novákovi, 15 cm široká');
        $this->assertSame(20, $sign['text_height']);
        $this->assertSame(11, $values('sign', 'Jmenovka se jménem Adam, 6 cm')['text_height']);
        // … whatever the model made of the number (it is told that a sign's size is its letter height)
        $this->assertSame(20, $values('sign', 'Cedulka na dveře se jménem Novákovi, 15 cm široká', ['size_mm' => 80, 'text' => 'Novákovi'])['text_height']);
        // the letters named: their height
        $this->assertSame(20, $values('sign', 'Cedulka s nápisem Novákovi, písmo 2 cm')['text_height']);
        $this->assertSame(50, $values('text', 'Stojící nápis LOVE na poličku, písmena 5 cm vysoká')['text_height']);
        $this->assertSame(60, $values('text', 'Standing letters 2027, digits 6 cm tall')['text_height']);
        $this->assertSame(49, $values('text', 'Stojící nápis LOVE, 20 cm')['text_height'], 'over 8 cm it is the whole word across');

        // a headband: the head in centimetres, or by the age; another size is no head
        $this->assertSame([54, 'custom'], [$values('headband', 'Čelenka se sobími parohy, obvod hlavy 54 cm')['head'], $values('headband', 'Čelenka se sobími parohy, obvod hlavy 54 cm')['head_size']]);
        $kid = $values('headband', 'Čelenka s čertovskými rohy na Mikuláše pro osmileté dítě');
        $this->assertSame(['kid', 54, 'hb_horns', 'horns'], [$kid['head_size'], $kid['head'], $kid['preset'], $kid['decoration']]);
        $this->assertSame('child', $values('headband', 'A headband with cat ears for a four-year-old')['head_size']);
        $this->assertSame('adult', $values('headband', 'Una diadema con cuernos de diablo para un chico de 16 años')['head_size']);
        $this->assertArrayNotHasKey('head', $values('headband', 'Čelenka s korunkou, korunka 12 cm vysoká'));
        // the model's kind stands; without it the words say it
        $this->assertSame('hb_cat', $values('headband', 'Černá čelenka s kočičíma ušima', ['kind' => 'cat'])['preset']);

        // the inscription of several words, the year that is part of it, a set of pieces
        $this->assertSame('Nový rok 2027', IntakePrefill::fromWords('Zápich do dortu s textem Nový rok 2027, 14 cm široký.')['text']);
        $this->assertArrayNotHasKey('text2', IntakePrefill::fromWords('Zápich do dortu s textem Nový rok 2027, 14 cm široký.'));
        $this->assertSame('none', $values('topper', 'Zápich do dortu s textem Nový rok 2027, 14 cm široký.')['template'], 'no number was said: no big "2" behind the words');
        $this->assertSame('number', $values('topper', 'Zápich do dortu se jménem Olivia a číslem 2')['template']);
        $this->assertSame('Pozor, tady straší', IntakePrefill::fromWords('Cedulka na dveře s textem „Pozor, tady straší“, 15 cm')['text']);
        $this->assertSame(4, IntakePrefill::fromWords('Sada 4 podtácků s naší rodinnou fotkou')['copies']);
    }

    /** With the model (production): its answer comes first, the words give what it left out and say what a size is the size of. */
    public function test_the_models_answer_is_completed_by_the_same_words(): void
    {
        $this->app->instance(Assistant::class, new FakeAssistant);
        // the model gives the tool and the name; the colours, the width and the count come from the words
        FakeAssistant::$answers['intake'] = $this->answer('sign', ['text' => 'Novákovi', 'size_mm' => 80]);
        $r = $this->send(['text' => 'Cedulka na dveře se jménem Novákovi, 15 cm široká, bílá se zlatým písmem, 2 kusy.'])->assertCreated()->assertJsonPath('tool_key', 'sign');
        $page = $this->handed((string) $r->json('go'));
        $this->assertEquals(['_qty' => '2', 'line1' => 'Novákovi', 'text_height' => 20, 'two_color' => true, 'part_colors' => ['plate' => Palette::BASIC['white'], 'text' => Palette::BASIC['gold']]], $page['values']);
        $this->assertContains('šířka asi 150 mm', $page['understood']);

        // a map: the model's place stands; a model that left it out is helped by the words
        FakeAssistant::$answers['intake'] = $this->answer('map', ['place' => 'Olomouc', 'size_mm' => 150]);
        $r = $this->send(['text' => '3D mapa Olomouce jako dárek, 15 cm.'])->assertCreated();
        $this->assertSame('Olomouc', $this->handed((string) $r->json('go'))['params']['place']);
        FakeAssistant::$answers['intake'] = $this->answer('map');
        $r = $this->send(['text' => '3D mapa Prahy jako dárek, 15 cm.'])->assertCreated();
        $page = $this->handed((string) $r->json('go'));
        $this->assertSame(['Praha', 150], [$page['params']['place'], $page['params']['size_mm']]);

        // a charm: the model cannot hand a picture over, the words find it in the library; with colours of the model's own
        FakeAssistant::$answers['intake'] = $this->answer('charm', ['size_mm' => 50, 'colors' => ['black']]);
        $r = $this->send(['text' => 'Přívěsek netopýr na batoh, 5 cm, černý.'])->assertCreated();
        $page = $this->handed((string) $r->json('go'));
        $this->assertSame('lib:holidays/bat', $page['values']['artwork']);
        $this->assertSame(['body' => Palette::BASIC['black'], 'color_1' => Palette::BASIC['black']], $page['values']['part_colors']);

        // a headband: the model's decoration and head, the words' colours; and a tool the model is unsure of opens the same way
        FakeAssistant::$answers['intake'] = $this->answer('headband', ['kind' => 'unicorn', 'size_mm' => 540], 0.5);
        $r = $this->send(['text' => 'Čelenka jednorožec na karneval, obvod hlavy 54 cm, bílá a zlatá.'])->assertCreated()->assertJsonPath('unsure', true);
        $page = $this->handed((string) $r->json('go'));
        $this->assertEquals(['preset' => 'hb_unicorn', 'decoration' => 'unicorn', 'head' => 54, 'head_size' => 'custom', 'part_colors' => ['body' => Palette::BASIC['white'], 'details' => Palette::BASIC['gold']]], $page['values']);

        // beads are a tool of the catalogue the model reads
        FakeAssistant::$answers['intake'] = $this->answer('beads', ['text' => 'JANA']);
        $r = $this->send(['text' => 'Korálky se jménem JANA, modré s bílými písmeny.'])->assertCreated()->assertJsonPath('tool_key', 'beads');
        $this->assertSame(['body' => Palette::BASIC['blue'], 'text' => Palette::BASIC['white']], $this->handed((string) $r->json('go'))['values']['part_colors']);
    }

    // ── the admin's own texts go through the same rule ───────────────────────────────────────────────────

    public function test_a_hint_the_admin_rewrote_is_checked_too(): void
    {
        IntakeHints::save(['steady' => ['texts' => ['cs' => [['Busta z fotky', 'Busta mého táty z fotky, 12 cm vysoká.'], ['Figurka psa', 'Figurka našeho psa podle fotky, 10 cm, hnědá a bílá.']]]]]);
        $hints = collect(IntakeHintCheck::all(['cs'], 2026))->keyBy('code');
        $this->assertSame([], $hints['steady.1']['lost']);
        $this->assertNotSame([], $hints['steady.2']['lost'], 'a figurine has no colours to open with: the sentence promises what the page cannot show');
        $this->artisan('matplace:intake-hints', ['--lang' => 'cs'])->assertFailed();
        IntakeHints::save([]);
        $this->artisan('matplace:intake-hints')->assertSuccessful();
        $this->artisan('matplace:intake-hints', ['--json' => true, '--only' => 'steady.3'])->assertSuccessful();
    }
}
