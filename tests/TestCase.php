<?php

namespace Tests;

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

    /** Address of a page in a language: Czech has no prefix, the others /en/… and /es/… */
    protected function localized(string $path, string $locale = 'cs'): string
    {
        return $locale === 'cs' ? $path : rtrim('/'.$locale.$path, '/');
    }
}
