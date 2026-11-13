<?php

namespace App\Domain\Farm;

use App\Models\FarmOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Lang;

/**
 * The state of the whole service, one choice of three at "Ceny a pravidla" (docs/AL.md §23):
 *
 *  open    – everything as always;
 *  paused  – a short break for repairs: prices stay, an order can be made and paid, but no customer's print starts
 *            (Dispatcher); the pages say "the farm is paused, printing starts on … at the earliest" instead of promises of speed;
 *  closed  – a longer break: no new orders from customers and no texts or prices of printing with us (FarmGate, as
 *            the old switch `farm_open` off did); the customers' orders already in the queue wait, as in a pause.
 *
 * The old switches stay: `farm_open` is written with the state (closed = off, open and paused = on) and a database
 * that has no `farm_state` yet is open or closed by `farm_open` alone; `farm_public` (only admins see the farm) and
 * `require_approval` (a paid order waits for the admin) work in every state as before.
 * An admin orders and prints in every state: that is how the farm is tried out while it does not serve customers.
 */
final class FarmState
{
    public const OPEN = 'open';

    public const PAUSED = 'paused';

    public const CLOSED = 'closed';

    public const ALL = [self::OPEN, self::PAUSED, self::CLOSED];

    /** The state the admin's settings give: `farm_open` off = closed (the old switch, still the one that closes); else paused when `farm_state` says so; else open. */
    public static function fromSettings(FarmSettings $settings): string
    {
        if (! $settings->get('farm_open')) {
            return self::CLOSED;
        }

        return $settings->get('farm_state') === self::PAUSED ? self::PAUSED : self::OPEN;
    }

    /** The state right now (config `farm.open` and `farm.state`, filled from the settings by AppServiceProvider). */
    public static function current(): string
    {
        if (! config('farm.open', true)) {
            return self::CLOSED;
        }

        return config('farm.state') === self::PAUSED ? self::PAUSED : self::OPEN;
    }

    public static function isOpen(): bool
    {
        return self::current() === self::OPEN;
    }

    public static function isPaused(): bool
    {
        return self::current() === self::PAUSED;
    }

    public static function isClosed(): bool
    {
        return self::current() === self::CLOSED;
    }

    /** The day printing starts again at the earliest, while it is still ahead (or today); null = no date to name. */
    public static function pauseUntil(): ?Carbon
    {
        $raw = (string) config('farm.pause_until', '');
        if (! self::isPaused() || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }
        $tz = (string) config('farm.operator_timezone', 'Europe/Prague');
        try {
            $day = Carbon::createFromFormat('Y-m-d', $raw, $tz)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $day->lt(Carbon::now($tz)->startOfDay()) ? null : $day;
    }

    /** "20. 11. 2026" / "20 Nov 2026": a date as the pages of that language write it. */
    public static function dateText(Carbon $day, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'cs' ? $day->format('j. n. Y') : $day->locale($locale)->translatedFormat('j M Y');
    }

    /**
     * The honest sentence of a pause: "The farm is paused right now. Printing starts on 20 Nov 2026 at the earliest."
     * (without a date: "as soon as we start the farm again"), followed by the admin's own note for customers in that
     * language when there is one. Null while the farm is not paused.
     */
    public static function pauseLine(?string $locale = null, bool $withNote = true): ?string
    {
        if (! self::isPaused()) {
            return null;
        }
        $locale ??= app()->getLocale();
        $until = self::pauseUntil();
        $line = $until
            ? Lang::get('farm.state.pause.until', ['date' => self::dateText($until, $locale)], $locale)
            : Lang::get('farm.state.pause.open_end', [], $locale);
        $note = $withNote ? self::pauseNote($locale) : '';

        return $note !== '' ? $line.' '.$note : $line;
    }

    /** The admin's short note for customers: the one written for this language, nothing else (no Czech on an English page). */
    public static function pauseNote(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return trim((string) (((array) config('farm.pause_note', []))[$locale] ?? ''));
    }

    /**
     * Does the state hold this order back from the printer? While the farm is paused or closed a customer's order
     * waits in the queue (a closed farm takes no new orders, and those it has wait until it is open again); the
     * farm's own prints (tests, showcase) and an admin's orders print in every state.
     */
    public static function holds(FarmOrder $order): bool
    {
        return ! self::isOpen() && ! $order->isFree() && ! $order->user?->isAdmin();
    }

    /**
     * What an order that is held is told instead of start times: the sentence of the pause, or, while the farm is
     * closed, that printing starts once the farm opens again. Null while the farm is open.
     */
    public static function holdLine(?string $locale = null): ?string
    {
        return match (self::current()) {
            self::PAUSED => self::pauseLine($locale),
            self::CLOSED => Lang::get('farm.state.closed.held', [], $locale ?? app()->getLocale()),
            default => null,
        };
    }

    /**
     * May the pages promise "delivered by Christmas when ordered by <day>"? Open: yes. Paused: only when the pause ends
     * (by its date) no later than that day; without a date nobody knows. Closed: no.
     */
    public static function allowsPromiseBy(Carbon $deadline): bool
    {
        return match (self::current()) {
            self::OPEN => true,
            self::PAUSED => ($until = self::pauseUntil()) !== null && $until->toDateString() <= $deadline->toDateString(),
            default => false,
        };
    }

    /** schema.org availability of a print we sell: in stock, on back order while paused, out of stock while closed. */
    public static function schemaAvailability(): string
    {
        return 'https://schema.org/'.match (self::current()) {
            self::OPEN => 'InStock',
            self::PAUSED => 'BackOrder',
            default => 'OutOfStock',
        };
    }
}
