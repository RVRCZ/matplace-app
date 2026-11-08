<?php

namespace Tests\Feature;

use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\Intake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A tool's page opened from the home page's router (TODO #78, docs/AN.md §22 D): `/tools/<key>?intake=<token>` hands
 * the photo's address and the parameters to the page's scripts (window.MP_TOOL.intake), marks the intake as opened
 * once, and every page that takes a photo marks the field the hand-over lands in. A token nobody knows opens nothing.
 */
class IntakeToolPreloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k']);
    }

    private function intake(array $extra = []): Intake
    {
        $token = str_repeat('a', 20).'TOK1';
        Storage::disk('local')->put('intake/'.$token.'/original.jpg', (string) file_get_contents(base_path('tests/fixtures/intake/photo.jpg')));
        Storage::disk('local')->put('intake/'.$token.'/model.jpg', (string) file_get_contents(base_path('tests/fixtures/intake/photo.jpg')));

        return Intake::create($extra + [
            'token' => $token, 'kind' => 'photo', 'photo_path' => 'intake/'.$token.'/original.jpg', 'photo_model_path' => 'intake/'.$token.'/model.jpg',
            'text' => 'Busta táty, 12 cm', 'locale' => 'cs', 'route' => 'tool', 'tool_key' => 'figure',
            'params' => ['size_mm' => 120, 'kind' => 'bust', 'colors' => ['white']], 'confidence' => 0.9, 'summary' => 'Busta.', 'status' => 'new',
        ]);
    }

    /** window.MP_TOOL of a tool's page, as its scripts read it. */
    private function shared(string $html): array
    {
        $this->assertSame(1, preg_match('/window\.MP_TOOL = JSON\.parse\(\'(.*?)\'\);|window\.MP_TOOL = (\{.*?\});<\/script>/s', $html, $m), 'the page hands its scripts the shared config');
        $json = isset($m[2]) && $m[2] !== '' ? $m[2] : json_decode('"'.$m[1].'"');

        return (array) json_decode((string) $json, true);
    }

    public function test_the_figure_page_hands_the_photo_and_the_parameters_to_its_script(): void
    {
        $intake = $this->intake();
        $html = (string) $this->get('/tools/figure?intake='.$intake->token)->assertOk()->getContent();
        $shared = $this->shared($html);
        $this->assertSame($intake->token, $shared['intake']['token']);
        $this->assertSame('figure', $shared['intake']['tool']);
        $this->assertSame(url('/intake/'.$intake->token.'/file'), $shared['intake']['file']);
        $this->assertSame('original.jpg', $shared['intake']['name']);
        $this->assertSame(['size_mm' => 120, 'kind' => 'bust', 'colors' => ['white']], $shared['intake']['params']);
        $this->assertSame('Busta táty, 12 cm', $shared['intake']['text']);
        // the field the photo lands in, and the picture itself by the token
        $this->assertMatchesRegularExpression('~<input id="figure-photo"[^>]*data-intake-photo~', $html);
        $this->get($shared['intake']['file'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // opened, once
        $intake->refresh();
        $this->assertSame('opened', $intake->status);
        $this->assertNotNull($intake->opened_at);
        $this->assertSame(['tool' => 'figure'], Event::where('type', 'intake_tool_opened')->firstOrFail()->meta);
        $this->get('/tools/figure?intake='.$intake->token)->assertOk();
        $this->assertSame(1, Event::where('type', 'intake_tool_opened')->count());
        // the English twin of the page works the same way
        $this->assertSame($intake->token, $this->shared((string) $this->get('/en/tools/figure?intake='.$intake->token)->assertOk()->getContent())['intake']['token']);
    }

    public function test_the_pet_and_the_charm_pages_mark_their_photo_field_and_take_the_hand_over(): void
    {
        $intake = $this->intake(['tool_key' => 'pet']);
        $pet = (string) $this->get('/tools/pet-figurine?intake='.$intake->token)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<input id="figure-photo"[^>]*data-intake-photo~', $pet);
        $this->assertSame('pet', $this->shared($pet)['intake']['tool']);

        $charm = (string) $this->get('/tools/charm?intake='.$intake->token)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<input id="param-artwork" data-intake-photo~', $charm);
        $this->assertSame('charm', $this->shared($charm)['intake']['tool']);
        // the other gift tools open with the parameters at least (no photo field of their own: the map takes a place)
        $map = (string) $this->get('/tools/map?intake='.$intake->token)->assertOk()->getContent();
        $this->assertSame(120, $this->shared($map)['intake']['params']['size_mm']);
        foreach (['/tools/cake-topper', '/tools/medallion', '/tools/coaster', '/tools/earrings'] as $path) {
            $this->assertSame($intake->token, $this->shared((string) $this->get($path.'?intake='.$intake->token)->assertOk()->getContent())['intake']['token'], $path);
        }
    }

    public function test_a_token_nobody_knows_and_a_page_that_is_no_tool_change_nothing(): void
    {
        $intake = $this->intake();
        $html = (string) $this->get('/tools/figure?intake='.str_repeat('b', 24))->assertOk()->getContent();
        $this->assertNull($this->shared($html)['intake']);
        $this->get('/tools/figure?intake=../../etc')->assertOk();
        $this->get('/intake/'.str_repeat('b', 24).'/file')->assertNotFound();
        // the home page and the catalogue ignore the token: nothing is opened by it there
        $this->get('/?intake='.$intake->token)->assertOk();
        $this->get('/tools?intake='.$intake->token)->assertOk();
        $this->assertSame('new', $intake->refresh()->status);
        $this->assertSame(0, Event::where('type', 'intake_tool_opened')->count());
        // an intake without a photo hands over the parameters alone, and its picture address is none
        $words = Intake::create(['token' => str_repeat('c', 24), 'kind' => 'text', 'text' => 'Zápich Olivia', 'locale' => 'cs', 'route' => 'tool', 'tool_key' => 'topper', 'params' => ['text' => 'Olivia', 'size_mm' => 110], 'status' => 'new']);
        $shared = $this->shared((string) $this->get('/tools/cake-topper?intake='.$words->token)->assertOk()->getContent());
        $this->assertNull($shared['intake']['file']);
        $this->assertSame('Olivia', $shared['intake']['params']['text']);
        $this->get('/intake/'.$words->token.'/file')->assertNotFound();
    }

    public function test_the_picture_is_not_indexed_and_needs_no_session(): void
    {
        $intake = $this->intake();
        $before = AnonymousSession::count();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->get('/intake/'.$intake->token.'/file')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'max-age=600, private');
        $this->assertSame($before, AnonymousSession::count());
    }
}
