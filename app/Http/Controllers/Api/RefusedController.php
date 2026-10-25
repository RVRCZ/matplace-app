<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/refused — the page's script says that a visitor's file was refused before any upload began: the
 * format is none we read, or the size is over the limit (resources/js/calc/calculator.ts). The server records the
 * uploads it refuses itself (UploadController); together they are the "upload began and failed" step of the funnel
 * (docs/AN.md §9). Takes a step and a code, nothing about the file or the visitor; always answers 204.
 */
class RefusedController extends Controller
{
    public const STEPS = ['upload'];

    public const KINDS = ['format', 'too_big'];

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'step' => ['required', 'in:'.implode(',', self::STEPS)],
            'kind' => ['required', 'in:'.implode(',', self::KINDS)],
        ]);
        Track::event('failed', null, ['step' => $data['step'], 'kind' => $data['kind']]);

        return response()->noContent();
    }
}
