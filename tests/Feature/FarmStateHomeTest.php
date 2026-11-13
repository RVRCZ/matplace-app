<?php

namespace Tests\Feature;

use App\Domain\Intake\IntakeHints;
use App\Domain\Intake\IntakePage;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page follows the state of the farm (docs/AL.md §23.5): the Christmas promise only while the farm can keep
 * it, the sentence of the pause under the trust row, and nothing of a price, an order or a delivery while the farm is
 * closed. The title, the meta description and the h1 are the same in every state.
 */
class FarmStateHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.marketplace' => false, 'farm.enabled' => true, 'farm.open' => true, 'farm.public' => true, 'farm.state' => 'open',
            'farm.settings.christmas_deadline' => '2026-12-15']);
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Europe/Prague'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pause(string $until): void
    {
        config(['farm.open' => true, 'farm.state' => 'paused', 'farm.pause_until' => $until, 'farm.pause_note' => []]);
    }

    private function close(): void
    {
        config(['farm.open' => false, 'farm.state' => 'closed']);
    }

    private function seoOf(string $html): array
    {
        preg_match('/<title>(.*?)<\/title>/s', $html, $title);
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $description);
        preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $h1);

        return [$title[1] ?? null, $description[1] ?? null, trim(strip_tags($h1[1] ?? ''))];
    }

    /** The sentence under the button as the page shows it and as the script gets it (data-i18n). */
    private function hintOf(string $html): array
    {
        preg_match('/<p id="intake-hint"[^>]*>(.*?)<\/p>/s', $html, $shown);
        preg_match('/data-i18n="([^"]*)"/', $html, $json);
        $texts = (array) json_decode(html_entity_decode($json[1] ?? '', ENT_QUOTES), true);

        return [html_entity_decode(trim($shown[1] ?? ''), ENT_QUOTES), $texts['router.submit.hint'] ?? null];
    }

    public function test_the_christmas_promise_is_made_only_while_the_farm_can_keep_it(): void
    {
        $codes = fn () => array_column(IntakeHints::chips('cs'), 'event');

        $this->assertSame('15. 12.', IntakePage::christmas());
        $this->assertContains('christmas', $codes());
        $open = (string) $this->get('/')->assertOk()->assertSee('data-trust="christmas"', false)->assertSee('data-deadline', false)
            ->assertSee('data-hint="christmas.1"', false)->assertDontSee('data-farm-pause', false)->getContent();

        // a pause that ends before the last day: the promise stands, and the page says the pause under the trust row
        $this->pause('2026-11-20');
        $this->assertSame('15. 12.', IntakePage::christmas());
        $this->assertContains('christmas', $codes());
        $paused = (string) $this->get('/')->assertOk()->assertSee('data-trust="christmas"', false)->assertSee('data-deadline', false)
            ->assertSee('data-farm-pause', false)->assertSee('Farma má teď odstávku. Tisk začne nejdřív 20. 11. 2026.')->getContent();
        $this->assertLessThan(strpos($paused, 'id="router-paths"'), strpos($paused, 'data-farm-pause'), 'the sentence sits on the first screen');
        $this->assertGreaterThan(strpos($paused, 'data-trust="no_signup"'), strpos($paused, 'data-farm-pause'), 'under the trust row');
        $this->get('/en')->assertOk()->assertSee('Printing starts on 20 Nov 2026 at the earliest.');

        // a pause that ends after it, or nobody knows when: no promise of Christmas and no Christmas examples
        foreach (['2026-12-16', ''] as $until) {
            $this->pause($until);
            $this->assertNull(IntakePage::christmas(), $until);
            $this->assertNotContains('christmas', $codes(), $until);
            $late = (string) $this->get('/')->assertOk()->assertDontSee('data-trust="christmas"', false)->assertDontSee('data-deadline', false)
                ->assertDontSee('data-hint="christmas.1"', false)->assertSee('data-farm-pause', false)->assertSee('id="router-paths"', false)->getContent();
            $this->assertSame($this->seoOf($open), $this->seoOf($late), $until);
            $this->assertSame([__('router.submit.hint'), __('router.submit.hint')], $this->hintOf($late), 'a pause keeps the promise of a proposal with a price');
        }
        // the other events of the season are not the farm's promise of a day: St Nicholas stays
        $this->assertContains('mikulas', $codes());

        $this->close();
        $this->assertNull(IntakePage::christmas());
        $this->assertNotContains('christmas', $codes());

        $this->assertSame($this->seoOf($open), $this->seoOf($paused));
    }

    public function test_a_closed_farm_promises_no_print_on_the_home_page_and_the_page_keeps_its_title(): void
    {
        $from = e(__('router.gifts.from', ['price' => Money::of(99, Money::CZK)->format()]));
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $lang) {
            config(['farm.open' => true, 'farm.state' => 'open']);
            $open = (string) $this->get($prefix.'/')->assertOk()->getContent();
            foreach (['id="router-paths"', 'data-trust="preview"', 'data-trust="christmas"', 'data-deadline', 'id="router-gifts"', 'id="router-file"'] as $needle) {
                $this->assertStringContainsString($needle, $open, "$lang: $needle");
            }
            $this->assertStringNotContainsString('data-farm-closed', $open, $lang);
            $this->assertSame([__('router.submit.hint', [], $lang), __('router.submit.hint', [], $lang)], $this->hintOf($open), $lang);
            $this->assertStringContainsString(e(__('router.file.title', [], $lang)), $open, $lang);

            $this->close();
            $closed = (string) $this->get($prefix.'/')->assertOk()->getContent();
            foreach (['id="router-paths"', 'data-trust="preview"', 'data-trust="christmas"', 'data-deadline', 'id="router-prints"', 'data-farm-pause', 'id="cta-farm"'] as $needle) {
                $this->assertStringNotContainsString($needle, $closed, "$lang: $needle");
            }
            // what works without the farm stays: the field, the examples, the gift tools, the file's entrance
            foreach (['id="intake-form"', 'id="intake-submit"', 'data-trust="no_signup"', 'id="router-gifts"', 'data-gift="figure"', 'id="router-file"', 'data-farm-closed'] as $needle) {
                $this->assertStringContainsString($needle, $closed, "$lang: $needle");
            }
            $this->assertStringContainsString(e(__('farm.state.closed.note', [], $lang)), $closed, $lang);
            $this->assertSame([__('router.submit.hint.closed', [], $lang), __('router.submit.hint.closed', [], $lang)], $this->hintOf($closed), $lang);
            foreach (['router.submit.hint', 'router.gifts.lead', 'router.file.title', 'router.file.lead', 'home.start.file.hint', 'home.start.gift.hint', 'home.farm.home.step3.text'] as $key) {
                $this->assertStringNotContainsString(e(__($key, [], $lang)), $closed, "$lang: $key promises a price or a print");
                $this->assertStringContainsString(e(__($key.'.closed', [], $lang)), $closed, "$lang: $key.closed");
            }
            $this->assertSame($this->seoOf($open), $this->seoOf($closed), "$lang: the title, the description and the h1 do not follow the state");
        }

        // the words the owner named, and the price of a gift card
        config(['farm.open' => true, 'farm.state' => 'open']);
        $this->get('/')->assertOk()->assertSee('a kolik to bude stát')->assertSee($from, false);
        $this->close();
        $this->get('/')->assertOk()->assertDontSee('a kolik to bude stát')->assertDontSee($from, false)
            ->assertDontSee(e(__('home.benefit.farm_from', ['price' => Money::of(99, Money::CZK)->format()])), false);

        // the admin tries the farm out while it is closed: the page is the one of an open farm, without the promise of Christmas
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin->fresh())->get('/')->assertOk()->assertSee('id="router-paths"', false)->assertSee('a kolik to bude stát')
            ->assertDontSee('data-farm-closed', false)->assertDontSee('data-trust="christmas"', false);
    }
}
