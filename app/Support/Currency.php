<?php

namespace App\Support;

use App\Models\User;

/**
 * Which currency a visitor sees prices in: crowns for Czechia, euros for everybody else.
 *
 *   1. the currency of the account, once money has moved on it (locked: nothing below can change it);
 *   2. the visitor's own choice (the Kč / € switch in the header, kept in a cookie);
 *   3. the country things are delivered to, when the account knows it;
 *   4. the language of the page: Czech = crowns, any other = euros.
 */
final class Currency
{
    public const COOKIE = 'currency';

    public static function current(?User $user = null): string
    {
        $user ??= self::visitor();
        if ($user?->currency) {
            return (string) $user->currency;
        }
        $chosen = strtoupper((string) (app()->bound('request') ? request()->cookie(self::COOKIE) : ''));
        if (in_array($chosen, Money::CURRENCIES, true)) {
            return $chosen;
        }
        if ($user?->country) {
            return self::forCountry($user->country);
        }

        return app()->getLocale() === 'cs' ? Money::CZK : Money::EUR;
    }

    /** The account's currency can no longer be chosen: money has moved on it. */
    public static function locked(?User $user = null): bool
    {
        return (bool) ($user ?? self::visitor())?->currency;
    }

    public static function forCountry(?string $country): string
    {
        return strtoupper((string) ($country ?: 'CZ')) === 'CZ' ? Money::CZK : Money::EUR;
    }

    /** What the scripts of a page need to print amounts the same way the server does. */
    public static function forScripts(): array
    {
        return ['currency' => self::current(), 'rate' => Money::rate(), 'locale' => app()->getLocale()];
    }

    private static function visitor(): ?User
    {
        $user = app()->bound('auth') ? auth()->user() : null;

        return $user instanceof User ? $user : null;
    }
}
