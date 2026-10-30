<?php

namespace App\Domain\Farm;

use App\Models\FarmAgent;
use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * What an agent's report means for printers, print jobs and orders. The agent only ever talks about printers that
 * are assigned to it; anything else in its report is ignored.
 */
final class AgentService
{
    /** A start that nobody picked up for this long is withdrawn: the plate may not be empty any more. */
    public const START_EXPIRES_MINUTES = 10;

    /** The message a job carries while a failure reported without a word, right after the start, waits to be confirmed. */
    public const ERROR_PENDING = 'error reported by the printer, waiting for a second report';

    /** A failure the printer gave no reason for, before the first per cent, in the first minutes of the job: a Kobra S1 says that for a moment while it starts. */
    private function looksTransient(FarmPrintJob $job): bool
    {
        $message = (string) $job->message;

        return (float) $job->progress < 1 && ($message === '' || $message === self::ERROR_PENDING)
            && $job->started_at !== null && $job->started_at->gt(now()->subMinutes(15));
    }

    private const PRINTER_STATES = [FarmPrinter::STATE_IDLE, FarmPrinter::STATE_PRINTING, FarmPrinter::STATE_PAUSED, FarmPrinter::STATE_ERROR, FarmPrinter::STATE_UNKNOWN];

    /** What the "paused" mail says when the printer gave no reason (the Kobra S1's screen only offers resume or stop). */
    public const NO_REASON = 'tiskárna důvod neposlala';

    public function __construct(private readonly OrderFlow $flow, private readonly Dispatcher $dispatcher, private readonly FarmSettings $settings, private readonly PrinterFiles $files) {}

    /**
     * @param  array<int, array<string,mixed>>  $reports  one per printer: key, state, telemetry, job
     * @return array<int, array<string,mixed>> commands for the agent
     */
    public function sync(FarmAgent $agent, array $reports, ?string $version, ?string $ip): array
    {
        $agent->update(['last_heartbeat_at' => now(), 'version' => $version ? mb_substr($version, 0, 40) : $agent->version, 'last_ip' => $ip]);
        $printers = $agent->printers()->where('enabled', true)->get()->keyBy('key');

        foreach ($reports as $report) {
            $printer = $printers->get((string) ($report['key'] ?? ''));
            if (! $printer) {
                continue;
            }
            $state = in_array($report['state'] ?? null, self::PRINTER_STATES, true) ? $report['state'] : FarmPrinter::STATE_UNKNOWN;
            $reachable = ($report['state'] ?? null) !== 'offline';
            $telemetry = is_array($report['telemetry'] ?? null) ? $report['telemetry'] : null;
            $printer->update([
                'state' => $reachable ? $state : FarmPrinter::STATE_UNKNOWN,
                'telemetry' => $telemetry !== null ? $this->slim($telemetry) : $printer->telemetry,
                // the agent is alive but cannot reach the printer: the printer itself is not "seen"
                'last_seen_at' => $reachable ? now() : $printer->last_seen_at,
                'offline_notified_at' => $reachable ? null : $printer->offline_notified_at,
            ]);
            if ($reachable && $telemetry !== null) {
                $this->printerMessage($printer, $telemetry);
                $this->plateConfirmed($printer, $telemetry);
            }
            if (is_array($report['job'] ?? null)) {
                $this->jobReport($printer, $report['job']);
            }
        }

        $this->expireStaleStarts($printers->pluck('id')->all());
        foreach ($printers as $printer) {
            $this->dispatcher->kick($printer->refresh());
        }

        return $this->takeCommands($printers->pluck('id')->all());
    }

    /**
     * What the printer has to say right now, kept with its time even outside a print (the screen's text, the ACE's
     * complaint, the reason of a pause). The agent repeats it in every report, so the words are stored when they
     * change; an empty report does not wipe the last ones, the time says how old they are (docs/AM.md §10).
     */
    private function printerMessage(FarmPrinter $printer, array $telemetry): void
    {
        $text = mb_substr(trim((string) ($telemetry['message'] ?? '')), 0, 300);
        if ($text !== '' && $text !== (string) $printer->message) {
            $printer->update(['message' => $text, 'message_at' => now()]);
        }
    }

    /**
     * The operator confirmed the finished print at the printer (agent: telemetry.bed_cleared_at, the unix time of the
     * moment; on a Kobra S1 with Rinkhals the reset of the finished job, docs/AM.md §10): the plate counts as clear and
     * the queue goes on, exactly as the button in the admin does. Only forward in time (the agent repeats the moment
     * for a minute), only without a job on the machine, only fresh.
     */
    private function plateConfirmed(FarmPrinter $printer, array $telemetry): void
    {
        $at = (int) ($telemetry['bed_cleared_at'] ?? 0);
        if ($at <= 0 || $printer->bed_clear || $at < now()->subHours(6)->timestamp || ($printer->bed_cleared_at !== null && $at <= $printer->bed_cleared_at->timestamp)) {
            return;
        }
        if ($printer->activeJob()) {
            return;
        }
        $printer->update(['bed_clear' => true, 'bed_cleared_at' => Carbon::createFromTimestamp($at)]);
    }

    /** The printer's file list (or the result of a delete / cleanup, with the fresh list) the agent sent. */
    public function filesReport(FarmPrinter $printer, array $data): void
    {
        $this->files->store($printer, $data);
    }

    /** @param array<string,mixed> $r id, status, progress, print_duration, filament_used, message */
    public function jobReport(FarmPrinter $printer, array $r): void
    {
        $job = FarmPrintJob::where('id', (int) ($r['id'] ?? 0))->where('farm_printer_id', $printer->id)->first();
        if (! $job || (! $job->isActive() && $job->status !== FarmPrintJob::STATUS_UNKNOWN)) {
            return;   // finished jobs are closed: a late report cannot reopen them
        }
        $status = (string) ($r['status'] ?? '');
        $job->fill([
            'progress' => max(0, min(100, (float) ($r['progress'] ?? $job->progress))),
            'print_duration_s' => isset($r['print_duration']) ? (int) $r['print_duration'] : $job->print_duration_s,
            'filament_used_mm' => isset($r['filament_used']) ? (float) $r['filament_used'] : $job->filament_used_mm,
            'message' => isset($r['message']) ? mb_substr((string) $r['message'], 0, 300) : $job->message,
            'telemetry' => is_array($r['telemetry'] ?? null) ? $this->slim($r['telemetry']) : $job->telemetry,
            'reported_at' => now(),
        ]);
        // the printer's words stay with the job (the newest with its time, the last 20 as history) even after a later
        // report without them: the reason of B3's pause was lost that way (F26-000108, 29 Oct 2026)
        if (isset($r['message'])) {
            $job->noteMessage((string) $r['message']);
        }
        $order = $job->order;

        switch ($status) {
            case 'printing':
            case 'paused':
                $job->status = $status === 'paused' ? FarmPrintJob::STATUS_PAUSED : FarmPrintJob::STATUS_PRINTING;
                $job->started_at ??= now();
                // pauses are counted: the fallback duration of a print the printer gave no numbers for leaves them out
                $newPause = $status === 'paused' && $job->paused_at === null;
                if ($status === 'paused') {
                    $job->paused_at ??= now();
                } else {
                    $this->closePause($job);
                }
                $job->save();
                if ($order->status === FarmOrder::STATUS_QUEUED) {
                    $this->flow->move($order, FarmOrder::STATUS_PRINTING, 'agent');
                }
                if ($newPause) {
                    // one mail per pause (the next report of the same pause says nothing new; a pause after a resume is news again)
                    $this->adminPaused($job, $order, $printer);
                }
                break;

            case 'done':
                $this->closePause($job);
                $job->fill(['status' => FarmPrintJob::STATUS_DONE, 'progress' => 100, 'finished_at' => now()]);
                $this->ensureStats($job, $order);
                $job->save();
                $this->recordOrderActuals($order);
                if ($order->status === FarmOrder::STATUS_QUEUED) {
                    $this->flow->move($order, FarmOrder::STATUS_PRINTING, 'agent');
                }
                if ($order->status === FarmOrder::STATUS_PRINTING) {
                    $this->flow->plateFinished($order, 'agent');
                }
                break;

            case 'failed':
            case 'cancelled':
                // a failure without a word from the printer, before the first per cent, in the first minutes: a Kobra S1
                // says "error" for a moment while it heats and levels and prints on (F26-000059, 12 Oct 2026). The order
                // is not closed on that: the job waits as unknown, the admin is told, and only a failure reported again
                // a minute later (or with a message) ends it. An agent from 0.1.1 keeps watching such a print itself.
                if ($status === 'failed' && $this->looksTransient($job)) {
                    $pending = $job->message === self::ERROR_PENDING;
                    if (! $pending) {
                        $job->fill(['status' => FarmPrintJob::STATUS_UNKNOWN, 'message' => self::ERROR_PENDING])->save();
                        $this->flow->alertAdmin(__('farm.admin.mail.error_pending', ['number' => $order->number, 'printer' => $printer->name], 'cs'), $order, [...$this->flow->orderLines($order), __('farm.admin.mail.error_pending_text', [], 'cs')]);
                        break;
                    }
                    if ($job->updated_at && $job->updated_at->gt(now()->subSeconds(60))) {
                        break;   // said again too soon: still the same moment
                    }
                }
                $this->closePause($job);
                $job->fill(['status' => $status === 'failed' ? FarmPrintJob::STATUS_FAILED : FarmPrintJob::STATUS_CANCELLED, 'finished_at' => now()]);
                $this->ensureStats($job, $order, durationOnly: true);   // how long it ran, at least; what it used nobody knows
                $job->save();
                if (in_array($order->status, [FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING], true)) {
                    $this->flow->fail($order, $status === 'failed' ? 'print_failed' : 'print_cancelled', 'agent', detail: $job->message === self::ERROR_PENDING ? null : $job->message);
                }
                break;

            default:
                $job->save();
        }
    }

    /** The printer paused the print by itself (or somebody did at the printer): the operator hears once, with the reason when there is one. */
    private function adminPaused(FarmPrintJob $job, FarmOrder $order, FarmPrinter $printer): void
    {
        $reason = trim((string) $job->message);
        $where = $printer->name.($order->plates > 1 ? ', '.__('farm.admin.mail.line_plate', ['n' => $job->plate, 'of' => $order->plates], 'cs') : '').', '
            .__('farm.admin.mail.line_progress', ['percent' => round((float) $job->progress)], 'cs');
        $this->flow->alertAdmin(__('farm.admin.mail.paused', ['number' => $order->number, 'printer' => $printer->name], 'cs'), $order, [
            ...$this->flow->orderLines($order),
            __('farm.admin.mail.line_where', ['where' => $where], 'cs'),
            $reason !== '' && $reason !== self::ERROR_PENDING ? __('farm.admin.mail.line_pause_reason', ['message' => $reason], 'cs') : __('farm.admin.mail.line_pause_none', ['none' => self::NO_REASON], 'cs'),
            __('farm.admin.mail.line_next_paused', [], 'cs'),
        ], 'paused');
    }

    /** The agent finished (or could not do) a command. */
    public function commandResult(FarmAgent $agent, FarmCommand $command, bool $ok, ?string $message): void
    {
        abort_unless($command->printer && $command->printer->farm_agent_id === $agent->id, 404);
        $command->update(['status' => $ok ? 'done' : 'failed', 'result' => $message ? mb_substr($message, 0, 300) : null, 'finished_at' => now()]);
        $job = $command->printJob;
        if (! $ok && $command->type === FarmCommand::TYPE_START && $job && $job->isActive()) {
            // nothing was started: the order goes back to the queue and waits for a person, the plate state is unknown
            $job->update(['status' => FarmPrintJob::STATUS_FAILED, 'message' => $command->result, 'finished_at' => now()]);
            $this->flow->alertAdmin(__('farm.admin.mail.start_failed', ['number' => $job->order->number, 'printer' => $command->printer->name], 'cs'), $job->order,
                [...$this->flow->orderLines($job->order), __('farm.admin.mail.line_what', ['what' => (string) $command->result], 'cs'), __('farm.admin.mail.line_next_start_failed', [], 'cs')]);
            Log::warning('Farm start command failed', ['command' => $command->id, 'message' => $message]);
        }
    }

    /**
     * Printers whose agent went silent: running jobs become "unknown" and an admin hears about it once.
     *
     * @return int number of printers newly reported offline
     */
    /** Hours between two "offline" mails about the same printer (a running print is told at once regardless). */
    public const ALERT_GAP_HOURS = 6;

    public function watch(): int
    {
        $limit = (int) $this->settings->get('offline_after_seconds');
        $count = 0;
        $silent = FarmPrinter::where('enabled', true)->where('mode', FarmPrinter::MODE_AGENT)->whereNull('offline_notified_at')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subSeconds($limit)))->get();
        foreach ($silent as $printer) {
            $jobs = $printer->printJobs()->whereIn('status', [FarmPrintJob::STATUS_PRINTING, FarmPrintJob::STATUS_PAUSED, FarmPrintJob::STATUS_SENT])->get();
            foreach ($jobs as $job) {
                $job->update(['status' => FarmPrintJob::STATUS_UNKNOWN, 'message' => 'connection lost']);
            }
            // a connection that flaps would mail at every drop: the admin hears about a printer at most once in a while,
            // unless a print was running on it (that is news every time)
            $recently = $printer->offline_alerted_at !== null && $printer->offline_alerted_at->gt(now()->subHours(self::ALERT_GAP_HOURS));
            $tell = ($printer->last_seen_at !== null || $jobs->isNotEmpty()) && ($jobs->isNotEmpty() || ! $recently);
            if ($tell) {
                $order = $jobs->first()?->order;
                $this->flow->alertAdmin(
                    $order ? __('farm.admin.mail.offline_order', ['number' => $order->number, 'printer' => $printer->name], 'cs') : __('farm.admin.mail.offline', ['printer' => $printer->name], 'cs'),
                    $order,
                    [...($order ? $this->flow->orderLines($order) : []), __('farm.admin.mail.offline_since', ['time' => $printer->last_seen_at?->format('j. n. H:i') ?? '—'], 'cs')],
                );
                $count++;
            }
            $printer->update(['offline_notified_at' => now(), 'offline_alerted_at' => $tell ? now() : $printer->offline_alerted_at, 'state' => FarmPrinter::STATE_UNKNOWN]);
        }

        return $count;
    }

    private function takeCommands(array $printerIds): array
    {
        $out = [];
        foreach (FarmCommand::with(['printer', 'printJob.order'])->whereIn('farm_printer_id', $printerIds)->where('status', 'pending')->orderBy('id')->get() as $c) {
            $c->update(['status' => 'sent', 'sent_at' => now()]);
            $payload = (array) $c->payload;
            if ($c->type === FarmCommand::TYPE_START && $c->printJob) {
                $c->printJob->update(['status' => FarmPrintJob::STATUS_SENT]);
                $payload += ['gcode_url' => route('agent.jobs.gcode', $c->printJob), 'order' => $c->printJob->order->number];
            }
            $out[] = ['id' => $c->id, 'printer' => $c->printer->key, 'type' => $c->type, 'job_id' => $c->farm_print_job_id, 'payload' => $payload];
        }

        return $out;
    }

    private function expireStaleStarts(array $printerIds): void
    {
        $stale = FarmCommand::with('printJob')->whereIn('farm_printer_id', $printerIds)->where('type', FarmCommand::TYPE_START)
            ->where('status', 'pending')->where('created_at', '<', now()->subMinutes(self::START_EXPIRES_MINUTES))->get();
        foreach ($stale as $c) {
            $c->update(['status' => 'failed', 'result' => 'expired before the agent took it', 'finished_at' => now()]);
            $c->printJob?->update(['status' => FarmPrintJob::STATUS_CANCELLED, 'message' => 'start expired', 'finished_at' => now()]);
        }
    }

    /** Filament length → grams (1.75 mm filament, the material's density). */
    private function grams(FarmPrintJob $job, FarmOrder $order): ?float
    {
        if (! $job->filament_used_mm) {
            return null;
        }

        return round($job->filament_used_mm * M_PI * 0.875 ** 2 / 1000 * self::density($order), 1);
    }

    private static function density(FarmOrder $order): float
    {
        return (float) ($order->material?->density ?? 1.24);
    }

    /** A pause that is still open is added to the job's paused seconds and closed. */
    private function closePause(FarmPrintJob $job): void
    {
        if ($job->paused_at) {
            $job->paused_s = (int) $job->paused_s + max(0, (int) $job->paused_at->diffInSeconds(now()));
            $job->paused_at = null;
        }
    }

    /**
     * A job that ends without numbers from the printer (the Kobra X after a 15-hour dragon, job 154, 23 Oct 2026:
     * done with 0 s and 0 mm) gets them estimated and says so (`stats_estimated`): the duration from its own clock
     * (started → finished, pauses taken off; the order's estimate for its plate when it never said it started), the
     * filament from the order's slice for that plate. Fills the job in place; the caller saves.
     */
    public function ensureStats(FarmPrintJob $job, FarmOrder $order, bool $durationOnly = false): void
    {
        $estimated = false;
        if ((int) $job->print_duration_s <= 0) {
            $finished = $job->finished_at ?? now();
            $seconds = $job->started_at ? (int) $job->started_at->diffInSeconds($finished) - (int) $job->paused_s : 0;
            if ($seconds < 60) {
                $seconds = (int) round(self::plateShare($order, $job) * 60 * (int) ($order->est_minutes ?? 0));
            }
            if ($seconds > 0) {
                $job->print_duration_s = $seconds;
                $estimated = true;
            }
        }
        if (! $durationOnly && (float) $job->filament_used_mm <= 0) {
            $grams = self::plateShare($order, $job, grams: true);
            if ($grams > 0) {
                $job->filament_used_mm = round($grams / (M_PI * 0.875 ** 2 / 1000 * self::density($order)), 1);
                $estimated = true;
            }
        }
        if ($estimated) {
            $job->stats_estimated = true;
        }
    }

    /**
     * How much of the order's estimate this job's plate is: its part's grams for an order printed by parts, else the
     * plate's share of the copies; as grams or as a fraction.
     */
    private static function plateShare(FarmOrder $order, FarmPrintJob $job, bool $grams = false): float
    {
        $plate = max(1, (int) $job->plate);
        $plates = max(1, (int) $order->plates);
        if ($order->isByParts() && ! $order->onePlate()) {
            $part = $order->partPlates()[$plate - 1] ?? null;
            if ($grams && isset($part['grams'])) {
                return (float) $part['grams'];
            }
            if (! $grams && isset($part['minutes']) && $order->est_minutes) {
                return (float) $part['minutes'] / (float) $order->est_minutes;
            }
        }
        $layout = $order->plateLayout();
        $copies = max(1, (int) array_sum($layout));
        $share = isset($layout[$plate - 1]) ? (float) $layout[$plate - 1] / $copies : 1 / $plates;

        return $grams ? round($share * (float) ($order->est_grams ?? 0), 1) : $share;
    }

    /** The whole order's actuals: every finished plate of it added up, estimates marked. */
    public function recordOrderActuals(FarmOrder $order): void
    {
        $doneJobs = $order->printJobs()->where('status', FarmPrintJob::STATUS_DONE)->get();
        $seconds = (int) $doneJobs->sum('print_duration_s');
        $grams = $doneJobs->sum(fn (FarmPrintJob $j) => (float) ($this->grams($j, $order) ?? 0));
        $this->flow->recordActuals($order, $seconds ? (int) ceil($seconds / 60) : null, $grams > 0 ? round($grams, 1) : null, 'agent', $doneJobs->contains(fn (FarmPrintJob $j) => $j->stats_estimated));
    }

    /** Telemetry is shown, not analysed: keep a small flat structure whatever the agent sends (the printer's message may be a long line). */
    private function slim(array $t): array
    {
        $out = [];
        foreach (array_slice($t, 0, 24, true) as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[mb_substr((string) $k, 0, 40)] = is_string($v) ? mb_substr($v, 0, $k === 'message' ? 300 : 120) : $v;
            }
        }

        return $out;
    }
}
