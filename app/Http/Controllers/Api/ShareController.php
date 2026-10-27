<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/shared — the page's script says a visitor shared a result: the link of a calculation (/c/<code>) or of a
 * tool's model, through the phone's share sheet or by copying the link. One `share` event in our statistics (and in
 * Google Analytics when the visitor allowed it), with the kind and the tool, nothing about the model (docs/AN.md §12).
 * A shared link is a visit that costs nothing. Always answers 204.
 */
class ShareController extends Controller
{
    public const KINDS = ['calc', 'tool'];

    public const HOW = ['share', 'copy'];

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', self::KINDS)],
            'tool' => ['nullable', 'string', 'max:40'],
            'how' => ['nullable', 'in:'.implode(',', self::HOW)],
        ]);
        $tool = isset($data['tool']) && array_key_exists($data['tool'], (array) config('tools')) ? $data['tool'] : null;
        Track::event('share', null, array_filter(['kind' => $data['kind'], 'tool' => $tool, 'code' => $data['how'] ?? null]));

        return response()->noContent();
    }
}
