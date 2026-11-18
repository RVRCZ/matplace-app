<?php

namespace Tests\Feature;

use App\Domain\Stats\CampaignFunnel;
use App\Domain\Stats\Humans;
use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\User;
use App\Support\Consent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /admin/stats/campaigns (Roman, 17 Nov 2026): where the visitors of a paid campaign leave, per channel and ad, from
 * the visit to the payment (docs/AJ.md §7).
 */
class CampaignFunnelTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    /** A visitor who came by an ad's link: the visit, the page shown (unless told not to), then the given steps and events. */
    private function visitor(string $source, string $content, array $steps = [], array $events = [], bool $shown = true): void
    {
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $path = '/tools/pet-figurine?utm_source='.$source.'&utm_medium=cpc&utm_campaign=figurka-vanoce-2026&utm_content='.$content;
        $response = $this->get($path, ['User-Agent' => self::BROWSER, 'Accept-Language' => 'cs'])->assertOk();
        $token = $response->getCookie(AnonymousSession::COOKIE, false)?->getValue();
        $this->withUnencryptedCookie(AnonymousSession::COOKIE, $token)->withCredentials();
        if ($shown) {
            $this->post('/api/seen', ['path' => '/tools/pet-figurine'], ['User-Agent' => self::BROWSER])->assertNoContent();
        }
        foreach ($steps as $step => $ms) {
            $this->postJson('/api/step', array_filter(['tool' => 'pet', 'step' => is_int($step) ? $ms : $step, 'ms' => is_int($step) ? null : $ms], fn ($v) => $v !== null), ['User-Agent' => self::BROWSER])->assertNoContent();
        }
        $session = AnonymousSession::where('token', $token)->firstOrFail();
        $first = Event::where('session_id', $session->id)->orderBy('id')->firstOrFail();
        foreach ($events as $type) {
            Event::create(['session_id' => $session->id, 'locale' => 'cs', 'type' => $type, 'source' => $first->source, 'utm' => $first->utm]);
        }
    }

    public function test_the_campaign_page_counts_every_step_per_channel_and_ad(): void
    {
        Humans::forget();
        // Facebook, the realistic video: one only looked, one stopped at the consent, one left after 40 s of waiting, one paid
        $this->visitor('facebook', 'video-real');
        $this->visitor('facebook', 'video-real', ['scroll', 'pick', 'photo', 'no_consent'], ['download']);
        $this->visitor('facebook', 'video-real', ['pick', 'photo', 'create', 'wait_left' => 40000], ['generate']);
        $this->visitor('facebook', 'video-real', ['pick', 'photo', 'create', 'shown' => 120000], ['generate', 'model_ready', 'order_created', 'price_shown', 'checkout_started', 'order_paid']);
        // a visit the browser never confirmed counts as a visit and nothing more
        $this->visitor('facebook', 'video-real', [], [], false);
        // Seznam, search: one picked a photo
        $this->visitor('seznam', 'search', ['pick', 'photo']);

        $stats = app(CampaignFunnel::class)->compute(7);
        $this->assertSame('figurka-vanoce-2026', $stats['campaign']);
        $this->assertSame(['figurka-vanoce-2026' => 6], $stats['campaigns']);
        $this->assertSame(['facebook', 'seznam'], array_column($stats['rows'], 'source'));
        $fb = $stats['rows'][0];
        $this->assertSame('video-real', $fb['content']);
        $this->assertSame(['visit' => 5, 'page' => 4, 'scroll' => 1, 'pick' => 3, 'photo' => 3, 'create' => 2, 'generate' => 2, 'model' => 1, 'to_print' => 1, 'price' => 1, 'checkout' => 1, 'paid' => 1, 'download' => 1], $fb['steps']);
        $this->assertSame(['no_consent' => 1, 'no_photo' => 0, 'refused' => 0, 'wait_left' => 1], $fb['stops']);
        $this->assertSame(40, $fb['wait_s']);
        $this->assertSame(1, $stats['rows'][1]['steps']['photo']);
        $this->assertSame(6, $stats['total']['steps']['visit']);
        $this->assertSame(4, $stats['total']['steps']['photo']);
        $this->assertSame(1, $stats['total']['steps']['paid']);

        // the page: only for the admin, with the campaign, both channels and the tab among the statistics
        $this->flushSession();
        $this->get('/admin/stats/campaigns')->assertRedirect();
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $page = $this->actingAs($admin)->get('/admin/stats/campaigns')->assertOk();
        $page->assertSee('figurka-vanoce-2026')->assertSee('Facebook')->assertSee('Seznam')->assertSee('video-real')->assertSee('Odešel při čekání')->assertSee('40 s');
        $this->actingAs($admin)->get('/admin/stats/campaigns?days=30&campaign=nothing-like-it')->assertOk()->assertSee('figurka-vanoce-2026');
        $this->actingAs($admin)->get('/admin/stats')->assertOk()->assertSee(route('admin.stats.campaigns'), false);
    }

    public function test_the_ad_tags_reach_the_page_only_when_set_and_the_security_header_lets_them_work(): void
    {
        // nothing set: the page's script hears of no Google Ads tag and no Sklik code
        config(['services.google_ads.tag' => null, 'services.google_ads.purchase' => null, 'services.sklik.sem' => null]);
        $none = Consent::forScripts();
        $this->assertNull($none['ads']);
        $this->assertNull($none['sklik']);
        $this->get('/tools', ['User-Agent' => self::BROWSER, 'Accept-Language' => 'cs'])->assertOk()->assertDontSee('AW-123', false);

        // set: their ids travel with the page (the script loads them after the marketing consent, measure.ts)
        config(['services.google_ads.tag' => 'AW-123', 'services.google_ads.purchase' => 'AW-123/abc', 'services.sklik.sem' => 'abc123sem']);
        $set = Consent::forScripts();
        $this->assertSame(['AW-123', 'AW-123/abc', 'abc123sem'], [$set['ads'], $set['adsPurchase'], $set['sklik']]);
        $page = $this->get('/tools', ['User-Agent' => self::BROWSER, 'Accept-Language' => 'cs'])->assertOk()->assertSee('AW-123', false);
        // no third-party script is printed without the consent
        $page->assertDontSee('googletagmanager.com/gtag/js', false)->assertDontSee('l.seznam.cz/sul.js', false);
        $csp = (string) ($page->headers->get('Content-Security-Policy') ?? $page->headers->get('Content-Security-Policy-Report-Only'));
        foreach (['https://www.googleadservices.com', 'https://googleads.g.doubleclick.net', 'https://l.seznam.cz', 'https://td.doubleclick.net'] as $host) {
            $this->assertStringContainsString($host, $csp);
        }
    }

    public function test_the_campaign_page_says_so_when_nobody_came_by_a_campaign_link(): void
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->get('/admin/stats/campaigns')->assertOk()->assertSee('data-campaigns-empty', false);
        $this->assertSame([], app(CampaignFunnel::class)->compute(7)['rows']);
    }
}
