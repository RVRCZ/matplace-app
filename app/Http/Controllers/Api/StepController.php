<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/step — the page's script says how far a visitor got on a tool's page before the server hears of them
 * (resources/js/calc/figure.ts): the photo picker opened, a photo chosen, "Create" pressed without the consent, the
 * page left while the model was being made, the model shown. Between the page shown and `generate` nothing else is
 * recorded, and that is where the visitors of an ad leave. Takes the tool, the step and at most a time in
 * milliseconds; nothing about the photo or the visitor; always answers 204.
 */
class StepController extends Controller
{
    public const TYPE = 'tool_step';

    public const STEPS = ['scroll', 'pick', 'photo', 'no_photo', 'no_consent', 'create', 'limit', 'rejected', 'error', 'wait_left', 'shown'];

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,23}$/'],
            'step' => ['required', 'in:'.implode(',', self::STEPS)],
            'ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ]);
        Track::event(self::TYPE, null, array_filter(['tool' => $data['tool'], 'step' => $data['step'], 'ms' => isset($data['ms']) ? (int) $data['ms'] : null], fn ($v) => $v !== null));

        return response()->noContent();
    }
}
