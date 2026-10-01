<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Address of a page in a language: Czech has no prefix, the others /en/… and /es/… */
    protected function localized(string $path, string $locale = 'cs'): string
    {
        return $locale === 'cs' ? $path : rtrim('/'.$locale.$path, '/');
    }
}
