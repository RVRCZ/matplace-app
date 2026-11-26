<?php

namespace App\Domain\Ops;

use App\Domain\Farm\FarmSettings;
use App\Mail\HealthAlert;
use App\Models\HealthCheck;
use Illuminate\Support\Facades\Mail;

/**
 * Runs the checks, remembers each one's state and mails the owner (farm setting `alert_email`) when a state flips:
 * a problem once when it appears, "solved" once when it goes, and again once a day while it stays (docs/AU.md).
 */
final class Health
{
    public function __construct(private readonly HealthChecks $checks, private readonly FarmSettings $settings) {}

    /** @return array{checks: list<array{key: string, ok: bool, message: string}>, changes: list<array<string, mixed>>, alerted: bool} */
    public function run(): array
    {
        $changes = [];
        $checks = $this->checks->run();
        foreach ($checks as $c) {
            $row = HealthCheck::firstOrNew(['key' => $c['key']]);
            $was = $row->exists ? $row->ok : null;
            $row->ok = $c['ok'];
            $row->message = mb_substr($c['message'], 0, 500);
            $row->checked_at = now();
            if ($was !== $c['ok']) {
                $row->changed_at = now();
                if (! $c['ok'] || $was === false) {
                    $changes[] = $c + ['what' => $c['ok'] ? 'solved' : 'problem'];
                }
            } elseif (! $c['ok'] && $row->alerted_at === null) {
                $changes[] = $c + ['what' => 'problem'];   // nobody could be told yet (no address at the time)
            } elseif (! $c['ok'] && $row->alerted_at->lt(now()->subDay())) {
                $changes[] = $c + ['what' => 'still'];
            }
            $row->save();
        }
        $to = (string) $this->settings->get('alert_email');
        $alerted = false;
        if ($changes !== [] && $to !== '') {
            Mail::to($to)->queue(new HealthAlert($changes, $checks));
            HealthCheck::whereIn('key', array_column($changes, 'key'))->update(['alerted_at' => now()]);
            $alerted = true;
        }

        return ['checks' => $checks, 'changes' => $changes, 'alerted' => $alerted];
    }
}
