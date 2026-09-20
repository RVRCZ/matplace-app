<?php

namespace App\Http\Controllers;

use App\Domain\Generation\GenerationService;
use Illuminate\Contracts\View\View;

/** "Tools" menu: everything that does not belong on the one main screen. */
class ToolsController extends Controller
{
    public function index(GenerationService $generation): View
    {
        return view('tools.index', ['generator' => $generation->enabled()]);
    }

    public function figure(GenerationService $generation): View
    {
        return view('tools.figure', [
            'generator' => $generation->enabled(),
            'guestLimit' => (int) config('ai.daily_limits.generate_guest'),
            'userLimit' => (int) config('ai.daily_limits.generate_user'),
        ]);
    }
}
