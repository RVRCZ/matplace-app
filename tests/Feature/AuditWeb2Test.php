<?php

namespace Tests\Feature;

use App\Domain\Stats\Funnel;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Support\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audit of 26 Oct 2026 and the traffic priority of 27 Oct (docs/prompts/audit-web-2.md, docs/AN.md §12): a shared
 * result is a visit that costs nothing, the calculator other sites embed, and the quality fixes of the audit.
 */
class AuditWeb2Test extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    public function test_a_shared_result_is_one_event_with_its_kind_and_tool(): void
    {
        $this->get('/', ['User-Agent' => self::BROWSER, 'Accept-Language' => 'cs'])->assertOk()->assertSee('shared:', false);
        $this->postJson('/api/shared', ['kind' => 'calc', 'tool' => 'box', 'how' => 'copy'])->assertNoContent();
        $this->postJson('/api/shared', ['kind' => 'tool', 'tool' => 'not-a-tool', 'how' => 'share'])->assertNoContent();
        $this->postJson('/api/shared', ['kind' => 'photo'])->assertStatus(422);
        $events = Event::where('type', 'share')->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame(['kind' => 'calc', 'tool' => 'box', 'code' => 'copy'], $events[0]->meta);
        $this->assertSame(['kind' => 'tool', 'code' => 'share'], $events[1]->meta, 'a tool nobody has is not written down');
        $this->assertContains('share', Track::SHARED, 'Google Analytics hears about it when allowed');
        // every tool's page has the button, hidden until a result exists
        $this->get('/tools/box')->assertOk()->assertSee('id="tool-share"', false)->assertSee(__('toolpage.share'));
        $this->get('/en/tools/qr')->assertOk()->assertSee('Share');
    }

    public function test_the_embedded_calculator_has_a_page_with_the_code_and_a_small_page_for_the_frame(): void
    {
        $page = $this->get('/embed')->assertOk();
        $page->assertSee(__('embed.h1'))->assertSee('&lt;iframe src=&quot;'.route('embed.calc').'&quot;', false)->assertSee('<iframe src="'.route('embed.calc').'"', false);
        $page->assertDontSee('noindex', false);
        $this->get('/en/embed')->assertOk()->assertSee('Copy the code')->assertSee(route('embed.calc', [], true));
        $this->get('/es/embed')->assertOk()->assertSee('Copiar el código');

        // the frame: no site header, no cookie bar, no third-party script; noindex; the routes its script needs
        $frame = (string) $this->get('/embed/calc', ['Referer' => 'https://blog.example.com/3d-tisk/'])->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $frame);
        $this->assertStringContainsString('id="embed-file"', $frame);
        $this->assertStringContainsString('window.MP_ROUTES = { uploads: ', $frame);
        $this->assertStringContainsString('utm_source=embed', $frame);
        $this->assertStringContainsString('utm_campaign=blog.example.com', $frame);
        $this->assertStringNotContainsString('id="site-nav"', $frame);
        $this->assertStringNotContainsString('MP_MEASURE', $frame);
        $this->assertStringNotContainsString('googletagmanager', $frame);
        // where the frame was shown is recorded (the host only), not when the frame is on matplace itself
        $embed = Event::where('type', 'embed')->get();
        $this->assertCount(1, $embed);
        $this->assertSame(['host' => 'blog.example.com'], $embed[0]->meta);
        $this->get('/embed/calc', ['Referer' => url('/embed')])->assertOk();
        $this->assertCount(1, Event::where('type', 'embed')->get());
        // a visit that came out of the frame is its own source
        $this->assertSame('embed', Track::classify('embed', 'https://blog.example.com/', 'matplace.com'));
        $this->assertSame('other', Track::classify('newsletter', 'https://blog.example.com/', 'matplace.com'));
        $this->assertContains('embed', Funnel::SOURCES);
        $this->assertDatabaseCount('anonymous_sessions', AnonymousSession::count());   // nothing special is needed of the visitor
    }
}
