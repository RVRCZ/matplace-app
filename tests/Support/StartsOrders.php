<?php

namespace Tests\Support;

use App\Models\FarmOrder;
use App\Models\User;

/**
 * Orders made the way the start page makes them: the colours are no longer ticked there but chosen when the order is
 * made (OrderController::preselect) and then on the order page. A test that used to read the ticked radios of the
 * start page makes the order and reads what it started with.
 */
trait StartsOrders
{
    /** The order the start page makes from an address (/farm?<query>): its hidden fields posted as they are. */
    protected function startOrder(User $user, string $query): FarmOrder
    {
        $html = (string) $this->actingAs($user)->get('/farm?'.$query)->assertOk()->getContent();
        preg_match('/<form id="farm-start".*?<\/form>/s', $html, $form);
        preg_match_all('/<input type="hidden" name="([^"]+)"[^>]*value="([^"]*)"/', $form[0] ?? '', $fields, PREG_SET_ORDER);
        $pairs = [];
        foreach ($fields as [, $name, $value]) {
            if ($name !== '_token') {
                $pairs[] = rawurlencode(html_entity_decode($name)).'='.rawurlencode(html_entity_decode($value));
            }
        }
        parse_str(implode('&', $pairs), $body);
        $url = $this->actingAs($user)->postJson('/farm/orders', $body)->assertCreated()->json('url');

        return FarmOrder::where('token', basename((string) $url))->firstOrFail();
    }

    /**
     * The colour an order started with for a field of the former start page: `color`, `change_color[i]` (0 = no change
     * there), `part_color[<part>]` (0 = the part prints from the first colour).
     */
    protected static function picked(FarmOrder $order, string $name): ?int
    {
        if ($name === 'color') {
            return $order->farm_color_id;
        }
        if (preg_match('/^change_color\[(\d+)\]$/', $name, $m)) {
            $z = $order->wantedChanges()[(int) $m[1]]['z'] ?? null;
            foreach ($order->colorSlots() as $c) {
                if ($z !== null && abs($c['z'] - $z) < 0.005) {
                    return $c['color_id'];
                }
            }

            return 0;
        }
        if (preg_match('/^part_color\[(.+)\]$/', $name, $m)) {
            foreach ($order->partPlates() as $p) {
                if ($p['part'] === $m[1]) {
                    return (int) $p['color_id'] === (int) $order->farm_color_id ? 0 : (int) $p['color_id'];
                }
            }
        }

        return null;
    }
}
