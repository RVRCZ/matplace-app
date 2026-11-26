<?php

namespace App\Domain\Mail;

use App\Models\FarmOrder;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What we know about the person who wrote (docs/AU.md §7): the orders named in the message (F26-000194 …) and the
 * last orders of the account with the sender's address. Shown next to the message in the admin and handed to the
 * assistant, so a reply about "my order" does not have to guess.
 */
final class ReplyContext
{
    /** @return Collection<int, FarmOrder> */
    public static function orders(string $from, string $subject, string $text): Collection
    {
        preg_match_all('/\b[A-Z]\d{2}-\d{6}\b/', $subject."\n".$text, $m);
        $named = FarmOrder::whereIn('number', array_unique($m[0] ?? []))->get();
        $user = User::where('email', mb_strtolower(trim($from)))->first();
        $own = $user ? FarmOrder::where('user_id', $user->id)->whereNotNull('paid_at')->latest('id')->limit(3)->get() : collect();

        return $named->concat($own)->unique('id')->sortByDesc('id')->take(5)->values();
    }

    /** One line per order, the facts a reply may need. */
    public static function line(FarmOrder $o): string
    {
        $color = $o->color?->name ?? null;
        $parts = array_filter([
            $o->number,
            __('farm.status.'.$o->status) !== 'farm.status.'.$o->status ? __('farm.status.'.$o->status) : $o->status,
            $o->modelFile?->original_name ? '„'.$o->modelFile->original_name.'“' : null,
            $o->material?->name ? $o->material->name.($color ? ' '.$color : '') : $color,
            $o->price_total ? rtrim(rtrim(number_format((float) $o->price_total, 2, ',', ' '), '0'), ',').' '.($o->currency ?: 'CZK') : null,
            $o->paid_at ? 'zaplaceno '.$o->paid_at->timezone(config('app.timezone'))->format('j. n. Y') : null,
            $o->shipped_at ? 'odesláno '.$o->shipped_at->timezone(config('app.timezone'))->format('j. n. Y') : null,
            $o->packeta_barcode ? 'zásilka '.$o->packeta_barcode : null,
        ]);

        return implode(' · ', $parts);
    }

    /** The orders as text for the assistant; '' when none. */
    public static function text(string $from, string $subject, string $text): string
    {
        return self::orders($from, $subject, $text)->map(fn (FarmOrder $o) => '- '.self::line($o))->implode("\n");
    }
}
