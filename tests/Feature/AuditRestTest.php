<?php

namespace Tests\Feature;

use App\Models\FarmOrder;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * The customer audit, stage 1, the rest (docs/AH.md §9): the header for a thumb, the calculation's own heading, the
 * print button as the start of an unpaid order, print / dispatch / delivery apart on the order page, and every text of
 * these pages in all three languages.
 */
class AuditRestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create();
    }

    private function order(): FarmOrder
    {
        $path = sys_get_temp_dir().'/mp_audit_rest_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'part.stl', null, null, true)])->json('file.uuid');
        $url = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $uuid])->json('url');

        return FarmOrder::where('token', basename($url))->firstOrFail();
    }

    public function test_the_header_is_for_a_thumb_and_language_and_currency_are_quiet(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        preg_match('/<header.*?<\/header>/s', $html, $header);
        $this->assertNotEmpty($header);
        $header = $header[0];
        // Tools and the account in the row, every link of the row a thumb's height
        $this->assertStringContainsString('class="nav-link">'.e(__('footer.tools')).'</a>', $header);
        $this->assertStringContainsString('class="nav-link font-medium">'.e(__('nav.login')).'</a>', $header);
        // language and currency behind one quiet menu (a <details>), which on a phone also holds the price from a file
        $this->assertStringContainsString('<details id="site-menu"', $header);
        $this->assertStringContainsString('aria-label="'.e(__('nav.menu')).'"', $header);
        preg_match('/<details id="site-menu".*?<\/details>/s', $header, $menu);
        $this->assertStringContainsString('hreflang="en" lang="en"', $menu[0]);
        $this->assertStringContainsString('name="currency" value="EUR"', $menu[0]);
        $this->assertStringContainsString('class="nav-link w-full">'.e(__('nav.price')).'</a>', $menu[0]);
        // every link and button of the menu is 44 px high (min-h-11 or the nav-link of the same height)
        preg_match_all('/<(a|button)\b[^>]*>/', $menu[0], $targets);
        foreach ($targets[0] as $tag) {
            $this->assertMatchesRegularExpression('/min-h-11|nav-link/', $tag, $tag);
        }
        // the cookie bar's buttons are no smaller than that either
        preg_match('/<section id="consent".*?<\/section>/s', $html, $bar);
        $this->assertStringNotContainsString('min-h-0', $bar[0]);
        // signed in: the account in the row, signing out in the menu on a phone and in the row on a computer
        $html = (string) $this->actingAs($this->user)->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('class="nav-link font-medium">My account</a>', $html);
        $this->assertSame(2, substr_count($html, 'action="'.route('logout').'"'));
    }

    public function test_the_home_page_names_its_formats_short_and_the_calculation_has_its_own_heading(): void
    {
        $home = (string) $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/STL, 3MF, STEP a další formáty, do \d+ MB<span class="sr-only"> \(STL, 3MF, OBJ/', $home);
        $this->assertSame(1, substr_count($home, '<h1'), 'the home page: the slogan is the one heading');
        $this->assertStringContainsString('STL, 3MF, STEP and other formats', (string) $this->get('/en')->getContent());

        $calc = $this->actingAs($this->user)->postJson('/api/calculations', ['file' => $this->order()->modelFile->uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15])->json('calculation.token');
        $page = (string) $this->get('/c/'.$calc)->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, '<h1'), 'a calculation has one heading');
        $this->assertMatchesRegularExpression('/<h1 [^>]*id="file-badge">part\.stl<\/h1>/', $page);
        // the fields a finger fills in are 44 px high
        $this->assertMatchesRegularExpression('/<input id="quantity"[^>]*min-h-11/', $page);
        $this->assertMatchesRegularExpression('/<input id="size-x"[^>]*min-h-11/', $page);
        // the mold says how long it takes, not "a few seconds"
        $this->assertStringContainsString(e(__('mold.hint')), $page);
        $this->assertStringContainsString('desítky sekund', __('mold.hint'));
        // the calculation's download says what you get (the glossary: a model is the file)
        $this->assertStringContainsString('>'.e(__('calc.cta.download')).'</button>', $page);
        $this->assertSame('Stáhnout model', __('calc.cta.download'));
    }

    public function test_the_print_button_starts_an_unpaid_order_and_says_so(): void
    {
        config(['farm.public' => true]);
        $guest = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('farm.cta')), $guest);
        $this->assertSame('Objednat tisk', __('farm.cta'));
        $this->assertStringContainsString(e(__('farm.cta_hint_guest')), $guest, 'a guest learns the sign-in comes first and nothing is paid yet');
        $signed = (string) $this->actingAs($this->user)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('farm.cta_hint')), $signed);
        $this->assertStringContainsString('data-busy="'.e(__('farm.cta_busy')).'"', $signed, 'the button says it works while the order is made');
        foreach (['cs', 'en', 'es'] as $locale) {
            $this->assertMatchesRegularExpression('/nic neplatíte|Nothing to pay yet|no paga nada/', __('farm.cta_hint', [], $locale), $locale);
        }
        // the order is made unpaid: nothing is taken from the credit
        $order = $this->order();
        $this->assertNull($order->paid_at);
        $this->assertNull($order->number);
    }

    public function test_the_order_page_puts_print_dispatch_and_delivery_apart_and_explains_a_new_price(): void
    {
        $order = $this->order();
        foreach (['cs', 'en', 'es'] as $lang) {
            $html = (string) $this->actingAs($this->user)->get($this->localized("/farm/orders/{$order->token}", $lang))->assertOk()->getContent();
            app()->setLocale($lang);
            $this->assertStringContainsString('id="farm-timeline"', $html);
            $this->assertStringContainsString(e(__('farm.order.tl.dispatch')), $html);
            $this->assertStringContainsString(e(__('farm.order.tl.estimate')), $html);
            $this->assertStringContainsString('id="farm-price-why"', $html);
            // the sentences the script writes travel with the page (their translations: the last test)
            foreach (['farm.order.why.lead', 'farm.order.why.printer', 'farm.order.why.kind', 'farm.order.tl.parcel', 'farm.order.tl.pickup', 'farm.order.reslicing'] as $key) {
                $this->assertStringContainsString($key, $html, "{$key} ({$lang})");
            }
            // the print line follows the order (resources/js/calc/order_timeline.ts, checked by scripts/check_order_timeline.mjs
            // in every state): printing with what is left and the plate, printed, sent – F26-000088, 19 Oct 2026
            foreach (['farm.order.tl.printing', 'farm.order.tl.printing_now', 'farm.order.tl.printing_end', 'farm.order.tl.done', 'farm.order.tl.dispatched', 'farm.copies.plate_of'] as $key) {
                $this->assertStringContainsString($key, $html, "{$key} ({$lang})");
                $this->assertNotSame($key, __($key), "{$key} ({$lang})");
            }
            $this->assertStringContainsString('id="farm-tl-dispatch-text" class="inline" data-rule="'.e(__('farm.order.tl.dispatch')).'"', $html);
        }
        $this->assertSame('tiskne se, zbývá asi :time', __('farm.order.tl.printing', [], 'cs'));
        app()->setLocale('cs');
        $this->assertSame('v den, kdy tisk skončí, v pracovní dny (co se dotiskne o víkendu, odešleme v pondělí)', __('farm.order.tl.dispatch'));
    }

    public function test_the_examples_with_a_price_wait_for_the_photos_behind_a_switch(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="home-examples"', false);
        config(['site.examples' => true]);
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="home-examples"', $html);
        $this->assertStringContainsString('Busta z fotky, výška 80 mm', $html);
        $this->assertMatchesRegularExpression('/99\s*Kč/u', $html);
        $this->assertStringContainsString(e(__('site.examples.preview')), $html, 'a preview says it is not a photo of a print');
        $this->assertStringContainsString(url('/c/vxauvlvidinz'), $html);
        // in euros the block does not invent a price: the calculation shows it
        $this->withCookie('currency', 'EUR')->get('/en')->assertOk()->assertSee('Bust from a photo, 150 mm high');
    }

    public function test_every_text_of_the_home_page_the_calculation_the_order_and_the_account_exists_in_all_three_languages(): void
    {
        $files = array_merge(
            glob(resource_path('views/calculator/*.blade.php')),
            glob(resource_path('views/farm/*.blade.php')),
            glob(resource_path('views/account/*.blade.php')),
            [resource_path('views/layouts/app.blade.php'), resource_path('views/partials/consent.blade.php')],
        );
        $all = ToolPageTest::keysIn($files);
        $this->assertGreaterThan(300, array_sum(array_map('count', $all)), 'the scan finds the texts of these templates');
        $this->assertContains('farm.order.why.lead', $all['farm/order.blade.php'], 'and those of the lists for the scripts');
        $this->assertContains('calc.status.failed', $all['calculator/index.blade.php']);
        $missing = [];
        foreach ($all as $file => $keys) {
            foreach ($keys as $key) {
                foreach (['cs', 'en', 'es'] as $locale) {
                    if (trans($key, [], $locale) === $key) {
                        $missing[] = "{$file}: {$key} ({$locale})";
                    }
                }
            }
        }
        $this->assertSame([], $missing);
    }
}
