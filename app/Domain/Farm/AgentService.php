<?php

namespace App\Domain\Farm;

use App\Models\FarmAgent;
use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use Illuminate\Support\Facades\Log;

/**
 * What an agent's report means for printers, print jobs and orders. The agent only ever talks about printers that
 * are assigned to it; anything else in its report is ignored.
 */
final class AgentService
{
    /** A start that nobody picked up for this long is withdrawn: the plate may not be empty any more. */
    public const START_EXPIRES_MINUTES = 10;

    private const PRINTER_STATES = [FarmPrinter::STATE_IDLE, FarmPrinter::STATE_PRINTING, FarmPrinter::STATE_PAUSED, FarmPrinter::STATE_ERROR, FarmPrinter::STATE_UNKNOWN];

    public function __construct(private readonly OrderFlow $flow, private readonly Dispatcher $dispatcher, private readonly FarmSettings $settings) {}

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
            $printer->update([
                'state' => $reachable ? $state : FarmPrinter::STATE_UNKNOWN,
                'telemetry' => is_array($report['telemetry'] ?? null) ? $this->slim($report['telemetry']) : $printer->telemetry,
                // the agent is alive but cannot reach the printer: the printer itself is not "seen"
                'last_seen_at' => $reachable ? now() : $printer->last_seen_at,
                'offline_notified_at' => $reachable ? null : $printer->offline_notified_at,
            ]);
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
        $order = $job->order;

        switch ($status) {
            case 'printing':
            case 'paused':
                $job->status = $status === 'paused' ? FarmPrintJob::STATUS_PAUSED : FarmPrintJob::STATUS_PRINTING;
                $job->started_at ??= now();
                $job->save();
                if ($order->status === FarmOrder::STATUS_QUEUED) {
                    $this->flow->move($order, FarmOrder::STATUS_PRINTING, 'agent');
                }
                break;

            case 'done':
                $job->fill(['status' => FarmPrintJob::STATUS_DONE, 'progress' => 100, 'finished_at' => now()])->save();
                $this->flow->recordActuals($order, $job->print_duration_s ? (int) ceil($job->print_duration_s / 60) : null, $this->grams($job, $order), 'agent');
                if ($order->status === FarmOrder::STATUS_QUEUED) {
                    $this->flow->move($order, FarmOrder::STATUS_PRINTING, 'agent');
                }
                if ($order->status === FarmOrder::STATUS_PRINTING) {
                    $this->flow->move($order, FarmOrder::STATUS_DONE, 'agent');
                }
                break;

            case 'failed':
            case 'cancelled':
                $job->fill(['status' => $status === 'failed' ? FarmPrintJob::STATUS_FAILED : FarmPrintJob::STATUS_CANCELLED, 'finished_at' => now()])->save();
                if (in_array($order->status, [FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING], true)) {
                    $this->flow->fail($order, $status === 'failed' ? 'print_failed' : 'print_cancelled', 'agent', detail: $job->message);
                }
                break;

            default:
                $job->save();
        }
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
            $this->flow->alertAdmin(__('farm.admin.mail.start_failed', ['printer' => $command->printer->name]), $job->order, [(string) $command->result]);
            Log::warning('Farm start command failed', ['command' => $command->id, 'message' => $message]);
        }
    }

    /**
     * Printers whose agent went silent: running jobs become "unknown" and an admin hears about it once.
     *
     * @return int number of printers newly reported offline
     */
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
            if ($printer->last_seen_at !== null || $jobs->isNotEmpty()) {
                $this->flow->alertAdmin(__('farm.admin.mail.offline', ['printer' => $printer->name]), $jobs->first()?->order, [
                    __('farm.admin.mail.offline_since', ['time' => $printer->last_seen_at?->format('j. n. H:i') ?? '—']),
                ]);
                $count++;
            }
            $printer->update(['offline_notified_at' => now(), 'state' => FarmPrinter::STATE_UNKNOWN]);
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
        $density = (float) ($order->material?->density ?? 1.24);

        return round($job->filament_used_mm * M_PI * 0.875 ** 2 / 1000 * $density, 1);
    }

    /** Telemetry is shown, not analysed: keep a small flat structure whatever the agent sends. */
    private function slim(array $t): array
    {
        $out = [];
        foreach (array_slice($t, 0, 24, true) as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[mb_substr((string) $k, 0, 40)] = is_string($v) ? mb_substr($v, 0, 120) : $v;
            }
        }

        return $out;
    }
}
