<?php

namespace Tests\Feature;

use App\Domain\Intake\IntakeRouter;
use App\Models\Intake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The headband in the home page's router (docs/P.md §13): a wish for a headband, for horns or ears to wear, goes to
 * the tool with the decoration the words name, and the tool's page gets the head's circumference and the costume.
 */
class IntakeHeadbandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_words_of_a_headband_choose_the_tool_and_its_decoration_in_three_languages(): void
    {
        $this->assertArrayHasKey('headband', IntakeRouter::TOOLS);
        $this->assertSame([440, 640], IntakeRouter::TOOLS['headband']['size']);
        $this->assertSame('text', IntakeRouter::TOOLS['headband']['input'], 'the tool takes words, no photo is handed to it');
        $this->assertNotEmpty(config('tools.headband.available'));
        foreach ([
            'Čelenka s čertovskými rohy na Mikuláše pro osmileté dítě, červená a černá.' => 'horns',
            'celenka s ruzky pro certa' => 'horns',
            'Černá čelenka s kočičíma ušima, obvod hlavy 54 cm' => 'cat',
            'kočičí uši na karneval' => 'cat',
            'Zaječí uši na hlavu na Velikonoce' => 'bunny',
            'Čelenka se sobími parohy na vánoční besídku' => 'antlers',
            'Čelenka s korunkou pro princeznu' => 'crown',
            'Čelenka se svatozáří pro anděla' => 'halo',
            'Čelenka jednorožec, bílá a zlatá' => 'unicorn',
            'A headband with devil horns for an eight-year-old child, red and black.' => 'horns',
            'cat ears for a costume' => 'cat',
            'A headband with bunny ears, head 52 cm around' => 'bunny',
            'reindeer antlers hairband for the office party' => 'antlers',
            'Una diadema con cuernos de diablo para un niño de ocho años, roja y negra.' => 'horns',
            'Una diadema con orejas de gato' => 'cat',
            'Una diadema con astas de reno para Navidad' => 'antlers',
            'Una diadema de unicornio' => 'unicorn',
        ] as $text => $kind) {
            foreach ([false, true] as $photo) {
                $d = IntakeRouter::guess($text, $photo, 'cs');
                $this->assertSame(['tool', 'headband', $kind], [$d['route'], $d['tool_key'], $d['params']['kind'] ?? null], $text);
                $this->assertStringStartsWith(__('router.summary.tool', ['tool' => __('tools.headband.title')]), $d['summary'], $text);
            }
        }
        // a headband without a named decoration is still the tool; it opens with its horns
        $plain = IntakeRouter::guess('Čelenka pro dceru', false, 'cs');
        $this->assertSame(['tool', 'headband'], [$plain['route'], $plain['tool_key']]);
        $this->assertArrayNotHasKey('kind', $plain['params']);
        // the head's circumference is the tool's size; a number that is no head is held at the tool's lowest
        $this->assertSame(540, IntakeRouter::guess('Černá čelenka s kočičíma ušima, obvod hlavy 54 cm', false, 'cs')['params']['size_mm']);
        $this->assertSame(440, IntakeRouter::guess('Čelenka s rohy 5 cm vysokými', false, 'cs')['params']['size_mm']);
        // what belongs elsewhere stays there: a pet from a photo, a holder for headbands, a statuette
        $this->assertSame('pet', IntakeRouter::guess('Figurka naší kočky podle fotky', true, 'cs')['tool_key']);
        $this->assertSame('human', IntakeRouter::guess('Držák na čelenky na zeď, rozměry 100 x 60 mm', false, 'cs')['route']);
        $this->assertSame('medallion', IntakeRouter::guess('Medaile pro vítěze turnaje', false, 'cs')['tool_key']);
        // the model's answer: the decoration is kept only when the tool has it, the size inside the tool's range
        $answer = fn (array $params) => ['route' => 'tool', 'tool_key' => 'headband', 'params' => $params, 'summary' => ['cs' => 'x', 'en' => 'x', 'es' => 'x'], 'questions' => [], 'alternatives' => [], 'reason' => '', 'confidence' => 0.9];
        $this->assertSame(['size_mm' => 520, 'kind' => 'antlers'], IntakeRouter::decide($answer(['size_mm' => 520, 'kind' => 'antlers']), false, 'cs')['params']);
        $this->assertSame(['size_mm' => 640], IntakeRouter::decide($answer(['size_mm' => 900, 'kind' => 'dragon']), false, 'cs')['params']);
        // the catalogue the model reads names the tool in three languages and says what `kind` may be
        $user = IntakeRouter::user(new Intake(['text' => 'Čelenka s rohy', 'locale' => 'cs']));
        $this->assertStringContainsString('headband | '.__('tools.headband.title', [], 'cs').' / '.__('tools.headband.title', [], 'en').' / '.__('tools.headband.title', [], 'es'), $user);
        $this->assertStringContainsString('params.kind = horns, cat, bunny, antlers, crown, halo or unicorn', $user);
    }

    public function test_the_tool_page_gets_the_head_and_the_costume_from_the_wish(): void
    {
        $intake = Intake::create(['token' => str_repeat('h', 24), 'kind' => 'text', 'text' => 'Čelenka s kočičíma ušima, obvod hlavy 54 cm', 'locale' => 'cs', 'route' => 'tool',
            'tool_key' => 'headband', 'params' => ['size_mm' => 540, 'kind' => 'cat'], 'status' => 'new']);
        $html = (string) $this->get('/tools/headband?intake='.$intake->token)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/window\.MP_TOOL = JSON\.parse\(\'(.*?)\'\);|window\.MP_TOOL = (\{.*?\});<\/script>/s', $html, $m));
        $shared = (array) json_decode((string) (isset($m[2]) && $m[2] !== '' ? $m[2] : json_decode('"'.$m[1].'"')), true);
        $this->assertSame('headband', $shared['intake']['tool']);
        $this->assertNull($shared['intake']['file']);
        $this->assertSame(['size_mm' => 540, 'kind' => 'cat'], $shared['intake']['params']);
        // what the hand-over's script fills: the head's circumference and the costume's button (resources/js/calc/intake_preload.ts)
        $this->assertStringContainsString('data-param="head"', $html);
        $this->assertStringContainsString('data-preset="hb_cat"', $html);
        $script = (string) file_get_contents(resource_path('js/calc/intake_preload.ts'));
        $this->assertStringContainsString('[data-param="head"]', $script);
        $this->assertStringContainsString('[data-preset="hb_${kind}"]', $script);
    }
}
