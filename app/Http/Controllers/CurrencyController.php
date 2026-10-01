<?php

namespace App\Http\Controllers;

use App\Support\Currency;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The Kč / € switch in the header: remembers which currency this browser wants prices in. An account money has
 * already moved on keeps its own currency, whatever is posted here.
 */
class CurrencyController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['currency' => ['required', 'in:'.implode(',', Money::CURRENCIES)]]);

        return back()->withCookie(cookie(Currency::COOKIE, $data['currency'], 60 * 24 * 365, sameSite: 'lax'));
    }
}
