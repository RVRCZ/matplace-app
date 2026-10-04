<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/seen — the script of a page says that a browser really showed it. That is what makes a visit a
 * person's in our statistics (robots fetch pages and never run their scripts). Takes the page's address and
 * nothing about the visitor; always answers 204.
 */
class SeenController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $path = $request->input('path');
        if (is_string($path) && $path !== '' && strlen($path) <= 500 && str_starts_with($path, '/')) {
            Track::seen($request, $path);
        }

        return response()->noContent();
    }
}
