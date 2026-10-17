<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The visitor review, round 2 (docs/Z.md §4, the coordinator's part, 17 Oct 2026): old addresses lead somewhere
 * useful, the menu shows the designers' catalogue only once it has models, the cookie bar is one line, the
 * calculator's "Recalculate" is ink and the failed exact calculation speaks like a person.
 */
class WebRound2Test extends TestCase
{
    use RefreshDatabase;

    public function test_old_addresses_of_the_previous_site_redirect_for_good(): void
    {
        foreach (['/signup' => '/login', '/signin' => '/login', '/dashboard' => '/login', '/info' => '/about', '/wp' => '/blog', '/wp/2024/05/post' => '/blog'] as $old => $new) {
            $this->get($old)->assertRedirect($new)->assertStatus(301);
        }
    }

    public function test_the_menu_shows_the_catalogue_only_once_it_has_six_models(): void
    {
        $this->get('/')->assertOk()->assertDontSee(__('models.nav'));
        Cache::put('nav.models', 6, 600);
        $this->get('/')->assertOk()->assertSee(__('models.nav'));
    }

    public function test_the_cookie_bar_is_one_line_with_both_choices(): void
    {
        $page = $this->get('/')->assertOk();
        $page->assertSee(__('site.consent.short'))->assertSee(__('site.consent.only_necessary'))->assertSee(__('site.consent.all'))->assertSee(__('site.consent.settings'));
        // the long explanation moved to /cookies: the bar does not carry it
        $page->assertDontSee(__('site.consent.text'));
    }

    public function test_the_calculator_keeps_the_orange_for_printing_with_us(): void
    {
        $page = $this->get('/')->assertOk();
        $page->assertSee('id="cta-recalc" type="button" class="btn-ink', false);
        $this->assertStringNotContainsString('nepovedl', __('calc.status.failed'));
    }
}
