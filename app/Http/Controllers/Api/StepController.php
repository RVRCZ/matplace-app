<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/step — the page's script says how far a visitor got on a tool's page before the server hears of them
 * (resources/js/calc/figure.ts): the photo picker opened, a photo chosen, "Create" pressed without the consent, the
 * page left while the model was being made, the model shown; and of the order's page (resources/js/calc/farm.ts,
 * the `o_` steps): a colour picked, the price asked for, the delivery, the e-mail, the terms, the pay button pressed
 * with something missing (`what` = the field), the payment sent, the page left unpaid (`what` = the last step); `what`
 * is kept as the event's `code`. Between the page shown and `generate` nothing else is
 * recorded, and that is where the visitors of an ad leave. Takes the tool, the step and at most a time in
 * milliseconds; nothing about the photo or the visitor; always answers 204.
 */
class StepController extends Controller
{
    public const TYPE = 'tool_step';

    public const STEPS = ['scroll', 'pick', 'photo', 'no_photo', 'no_consent', 'create', 'limit', 'rejected', 'error', 'wait_left', 'shown',
        'o_color', 'o_setting', 'o_slice', 'o_delivery', 'o_email', 'o_terms', 'o_blocked', 'o_pay', 'o_left'];

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,23}$/'],
            'step' => ['required', 'in:'.implode(',', self::STEPS)],
            'ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'what' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_-]{0,31}$/'],
        ]);
        Track::event(self::TYPE, null, array_filter(['tool' => $data['tool'], 'step' => $data['step'], 'ms' => isset($data['ms']) ? (int) $data['ms'] : null, 'code' => $data['what'] ?? null], fn ($v) => $v !== null));

        return response()->noContent();
    }
}
