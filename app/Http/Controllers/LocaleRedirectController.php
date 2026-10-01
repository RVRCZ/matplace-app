<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Czech pages have no prefix: /cs/tools moves to /tools for good. */
class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect()->to('/'.ltrim((string) $path, '/').($query ? '?'.$query : ''), 301);
    }
}
