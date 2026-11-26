<?php

namespace App\Domain\Mail;

use App\Domain\Farm\FarmSettings;
use App\Engines\Mail\Mailbox;
use App\Engines\Mail\MailboxFailed;
use App\Mail\NewMailAlert;
use App\Models\MailboxAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * The watch over the shared mailbox (docs/AU.md). Every few minutes what is unread in the inbox is sorted into four
 * kinds: people, our own notifications ("[farm] …" from the app), reports (bounces, DMARC) and robots (newsletters,
 * "noreply"). People's mails the owner has not heard about yet go to the owner's own address (farm setting
 * `alert_email`) in one mail with a link to each; our own notifications and the reports leave the inbox (archived, still
 * in "all mail"); robots stay, the admin's inbox page just folds them away. A person's mail still unread a day later is
 * mentioned again, once a day.
 *
 * Why: the Saunia inquiry of 29 Oct 2026 sat unread for four weeks under six hundred of the farm's own mails.
 */
final class MailboxWatch
{
    public const HUMAN = 'human';

    public const OWN = 'own';

    public const REPORT = 'report';

    public const ROBOT = 'robot';

    /** Cache key with the last pass: ['unread_human' => n, 'checked_at' => iso]; the admin's badge and the health check read it. */
    public const CACHE = 'watch.mailbox';

    public function __construct(private readonly Mailbox $mailbox, private readonly FarmSettings $settings) {}

    /**
     * Which of the four kinds a message is, by its sender and subject (config/watch.php). `$own` = our own addresses.
     *
     * @param  string[]  $own
     */
    public static function kind(string $from, string $subject, array $own = []): string
    {
        $from = mb_strtolower(trim($from));
        $subject = mb_strtolower(trim($subject));
        foreach ($own as $address) {
            if ($address !== '' && $from === mb_strtolower(trim($address))) {
                return self::OWN;
            }
        }
        if (self::startsWithAny($subject, (array) config('watch.own_subjects'))) {
            return self::OWN;
        }
        if (self::containsAny($from, (array) config('watch.report_senders')) || self::startsWithAny($subject, (array) config('watch.report_subjects'))) {
            return self::REPORT;
        }
        if (self::containsAny($from, (array) config('watch.robot_senders'))) {
            return self::ROBOT;
        }

        return self::HUMAN;
    }

    /** @return string[] mail from these addresses is our own (the mailbox itself, the app's sender) */
    public function ownAddresses(): array
    {
        return array_values(array_filter(array_unique([$this->mailbox->address(), (string) config('mail.from.address')])));
    }

    /**
     * One pass over the unread inbox.
     *
     * @return array{available: bool, new: list<array<string, mixed>>, unread_human: int, archived: int, alerted: bool, reminded: int}
     *
     * @throws MailboxFailed
     */
    public function run(): array
    {
        $out = ['available' => false, 'new' => [], 'unread_human' => 0, 'archived' => 0, 'alerted' => false, 'reminded' => 0];
        if (! $this->mailbox->available()) {
            return $out;
        }
        $out['available'] = true;
        $own = $this->ownAddresses();
        $human = [];
        foreach ($this->mailbox->recent(100, true) as $m) {
            $kind = self::kind($m['from'], $m['subject'], $own);
            if ($kind === self::OWN || $kind === self::REPORT) {
                $this->mailbox->archive($m['id']);
                $out['archived']++;
            } elseif ($kind === self::HUMAN) {
                $human[] = $m;
            }
        }

        // people's mails nobody was told about yet: remembered, then the owner hears about all of them at once
        $known = MailboxAlert::whereIn('message_id', array_column($human, 'id'))->pluck('message_id')->all();
        $new = array_values(array_filter($human, fn (array $m) => ! in_array($m['id'], $known, true)));
        foreach ($new as $m) {
            MailboxAlert::create([
                'message_id' => $m['id'], 'thread_id' => $m['thread_id'] ?? null, 'from' => mb_substr($m['from'], 0, 190),
                'from_name' => mb_substr((string) ($m['from_name'] ?? ''), 0, 120) ?: null, 'subject' => mb_substr((string) $m['subject'], 0, 250) ?: null,
                'received_at' => self::when($m['date'] ?? null),
            ]);
        }
        $to = (string) $this->settings->get('alert_email');
        if ($new !== [] && $to !== '') {
            Mail::to($to)->queue(new NewMailAlert($new, $this->mailbox->address()));
            MailboxAlert::whereIn('message_id', array_column($new, 'id'))->update(['alerted_at' => now()]);
            $out['alerted'] = true;
        }

        // still unread a day later: once a day, all of them in one mail
        $limit = now()->subHours(max(1, (int) config('watch.remind_after_hours', 24)));
        $newIds = array_column($new, 'id');
        $stale = array_values(array_filter($human, fn (array $m) => ! in_array($m['id'], $newIds, true) && ($when = self::when($m['date'] ?? null)) !== null && $when->lt($limit)));
        if ($stale !== [] && $to !== '') {
            $due = MailboxAlert::whereIn('message_id', array_column($stale, 'id'))
                ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<', $limit))->pluck('message_id')->all();
            if ($due !== []) {
                Mail::to($to)->queue(new NewMailAlert(array_values(array_filter($stale, fn (array $m) => in_array($m['id'], $due, true))), $this->mailbox->address(), reminder: true));
                MailboxAlert::whereIn('message_id', $due)->update(['reminded_at' => now()]);
                $out['reminded'] = count($due);
            }
        }

        $out['new'] = $new;
        $out['unread_human'] = count($human);
        Cache::put(self::CACHE, ['unread_human' => count($human), 'checked_at' => now()->toIso8601String()], now()->addDay());

        return $out;
    }

    public static function when(?string $date): ?Carbon
    {
        try {
            return $date ? Carbon::parse($date) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param  string[]  $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /** @param  string[]  $needles */
    private static function startsWithAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
