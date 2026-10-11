<?php

namespace App\Domain\Farm;

use App\Models\User;

/**
 * Who may order a print right now. A closed farm ("Ceny a pravidla": farm_open off) takes no orders from customers,
 * running ones finish; an admin orders anyway – that is how the farm is tried out while it is closed (Roman,
 * 11 Oct 2026). Until the farm is public only admins see it at all.
 */
final class FarmGate
{
    /** The farm exists and takes an order from this person. */
    public static function open(?User $user = null): bool
    {
        return (bool) config('farm.enabled') && ((bool) config('farm.open', true) || (bool) $user?->isAdmin());
    }

    /** The farm is offered to this person on the site (calculator button, the next step of a tool page). */
    public static function offered(?User $user = null): bool
    {
        return self::open($user) && ((bool) config('farm.public') || (bool) $user?->isAdmin());
    }
}
