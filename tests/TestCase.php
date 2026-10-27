<?php

namespace Tests;

use App\Support\RegisterGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The suite walks every kind of delivery the code knows, pickup in person included (it is the one that needs
        // no address, so most order tests pay with it). The site itself starts without pickup: config/farm.php, and
        // ShippingCurrencyTest::test_pickup_in_person_is_not_offered_unless_the_admin_switches_it_on.
        config(['farm.settings.delivery_modes' => ['pickup', 'packeta_point', 'packeta_home']]);
    }

    /** What the registration form adds to a post besides the fields a person fills in (App\Support\RegisterGuard). */
    protected function registerStamp(): array
    {
        // the form as a person sends it: shown a while ago (RegisterGuard refuses under 4 s and over 2 h)
        return ['t' => RegisterGuard::stamp(now()->getTimestamp() - 10)];
    }

    /**
     * The calculator's constants and the texts its scripts say, as the browser gets them: one cached JSON the page names
     * in window.MP_CONFIG_URL (ConfigController::calc, docs/AN.md §12.4), not inline HTML any more.
     *
     * @return array{config: array<string, mixed>, i18n: array<string, string>}
     */
    protected function calcConfig(string $path = '/', array $headers = []): array
    {
        $html = (string) $this->get($path, $headers)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/window\.MP_CONFIG_URL = "([^"]+)"/', $html, $m), 'the page names its calculator JSON');

        return (array) $this->get((string) json_decode('"'.$m[1].'"'))->assertOk()->json();
    }

    /** Address of a page in a language: Czech has no prefix, the others /en/… and /es/… */
    protected function localized(string $path, string $locale = 'cs'): string
    {
        return $locale === 'cs' ? $path : rtrim('/'.$locale.$path, '/');
    }
}
