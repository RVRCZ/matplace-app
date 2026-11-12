<?php

namespace Tests\Feature;

use App\Domain\Intake\IntakePage;
use App\Domain\Tools\ToolVisibility;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page's router (TODO #78, docs/AN.md §22 A): the first screen asks for a photo, a file or words in three
 * languages, with no key of the dictionary showing; the three ways, the gifts and the file's entrance follow in order;
 * the Christmas line and the gifts' prices come from the farm's settings and from real calculations, never from the code.
 */
class HomeRouterPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_router_leads_the_page_in_every_language(): void
    {
        config(['features.marketplace' => false, 'farm.settings.christmas_deadline' => now()->addDays(30)->format('Y-m-d')]);
        foreach (['' => 'cs', '/en' => 'en', '/es' => 'es'] as $prefix => $lang) {
            $html = (string) $this->get($prefix.'/')->assertOk()->getContent();
            $this->assertStringContainsString('<h1 class="', $html, $lang);
            $this->assertMatchesRegularExpression('~<h1[^>]*>'.preg_quote(e(__('router.title', [], $lang)), '~').'</h1>~u', $html, "$lang: the title is the h1");
            foreach (['id="intake-form"', 'id="intake-photo"', 'id="intake-model"', 'id="intake-text"', 'id="intake-submit"', 'id="router-paths"', 'id="router-gifts"', 'id="router-file"', 'data-trust="christmas"', 'data-deadline'] as $needle) {
                $this->assertStringContainsString($needle, $html, "$lang: $needle");
            }
            $this->assertStringContainsString(e(__('router.submit', [], $lang)), $html, $lang);
            $this->assertStringContainsString(e(__('router.hint.steady.3.fill', [], $lang)), $html, $lang);
            $this->assertStringContainsString(e(__('router.paths.human.text', ['hours' => 24], $lang)), $html, "$lang: the reply time of the farm is in the text");
            foreach (IntakePage::GIFTS as $gift) {
                $this->assertStringContainsString('data-gift="'.$gift.'"', $html, "$lang: $gift");
            }
            // no key of the dictionary on the page (the scripts' texts travel as JSON in data-i18n, keys included, so that attribute is skipped)
            $visible = preg_replace('/data-i18n="[^"]*"/', '', $html);
            $this->assertDoesNotMatchRegularExpression('/\brouter\.[a-z_]+(\.[a-z_]+)+\b/', (string) $visible, "$lang: a raw key");
            // the form posts to the page's own language
            $this->assertStringContainsString('data-url="'.url($prefix.'/intake').'"', $html, $lang);
        }
    }

    public function test_the_christmas_line_follows_the_farm_setting(): void
    {
        config(['farm.settings.christmas_deadline' => '']);
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-trust="christmas"', $html);
        $this->assertStringNotContainsString('data-deadline', $html);

        config(['farm.settings.christmas_deadline' => now()->subDays(2)->format('Y-m-d')]);
        $this->assertStringNotContainsString('data-trust="christmas"', (string) $this->get('/')->getContent(), 'a day gone by says nothing');

        $date = now()->addDays(20);
        config(['farm.settings.christmas_deadline' => $date->format('Y-m-d')]);
        $this->get('/')->assertOk()->assertSee(__('router.trust.christmas', ['date' => $date->format('j. n.')]));
        $this->get('/en')->assertOk()->assertSee(__('router.deadline', ['date' => $date->locale('en')->isoFormat('D MMMM')], 'en'));
        $this->get('/es')->assertOk()->assertSee(__('router.deadline', ['date' => $date->locale('es')->isoFormat('D [de] MMMM')], 'es'));
    }

    public function test_a_gift_names_a_price_only_from_a_real_calculation(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        preg_match('~data-gift="figure".*?</a>~s', $html, $figure);
        preg_match('~data-gift="pet".*?</a>~s', $html, $pet);
        $this->assertNotEmpty($figure);
        $this->assertNotEmpty($pet);
        // the bust has a priced example in config/site.php (99 Kč of a real calculation), the pet none
        $this->assertStringContainsString(e(__('router.gifts.from', ['price' => Money::of(99, Money::CZK)->format()])), $figure[0]);
        $this->assertStringNotContainsString(e(__('router.gifts.from', ['price' => ''])), $pet[0]);
        $this->assertSame(Money::of(99, Money::CZK)->format(), IntakePage::fromPrice('figure'));
        $this->assertNull(IntakePage::fromPrice('pet'));
    }

    public function test_the_sections_come_in_the_order_of_the_design_and_the_calculator_keeps_its_entrance(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $at = fn (string $needle) => strpos($html, $needle);
        $this->assertLessThan($at('id="router-paths"'), $at('id="intake-form"'));
        $this->assertLessThan($at('id="router-gifts"'), $at('id="router-paths"'));
        $this->assertLessThan($at('id="router-file"'), $at('id="router-gifts"'));
        $this->assertLessThan($at('data-ways'), $at('id="router-file"'));
        $this->assertLessThan($at('id="home-tools"'), $at('data-ways'));
        // the calculator's scripts still find their fields, inside the file's own section; the old photo and words buttons are there but unseen
        foreach (['id="dropzone"', 'id="file-input"', 'id="search-form"', 'id="hero-text-btn"', 'id="calc-note"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertMatchesRegularExpression('~id="hero-text-btn" class="[^"]*\bhidden\b~', $html);
        $this->assertLessThan($at('id="dropzone"'), $at('id="router-file"'));
        // the main action is tall enough for a thumb and the whole width on a phone
        $this->assertMatchesRegularExpression('~id="intake-submit" class="[^"]*min-h-\[60px\][^"]*w-full~', $html);
    }

    public function test_the_gifts_follow_the_tools_a_visitor_may_open(): void
    {
        config(['tools.pet.available' => false]);
        ToolVisibility::forget();
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-gift="pet"', $html);
        $this->assertStringContainsString('data-gift="figure"', $html);
    }
}
