<?php

namespace App\Domain\Farm;

use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;

/**
 * One word and one colour for a printer in the admin's overview (docs/AQ.md). Display only: nothing here is stored,
 * every status is derived from what the farm already knows (the printer's row, its running job, its orders, the
 * commands sent to it). The first rule that holds wins, in the order of the constants below.
 */
final class PrinterStatus
{
    public const DISABLED = 'disabled';   // switched off in its settings

    public const OFFLINE = 'offline';     // run by the agent, silent for longer than farm setting offline_after_seconds

    public const PROBLEM = 'problem';     // an error, an unknown state, a paused print, a cancel the printer did not confirm, a message of the printer

    public const PRINTING = 'printing';   // a job of ours (also while it uploads and heats), or the machine says it prints

    public const BED = 'bed';             // free, but the plate is not confirmed as cleared: nothing starts by itself

    public const STANDBY = 'standby';     // free and the plate is clear: the next job may start

    /**
     * How long a message of a printer that is neither paused nor in error counts as news. The ACE of an idle Kobra S1
     * can say "busy · slot 2 unwinding" for days (U1, 12–13 Nov 2026): the agent repeats it in every report, so it is
     * "live", but it is no reason to call the printer a problem for ever.
     */
    public const MESSAGE_FRESH_MINUTES = 30;

    /** Pill colours (Tailwind classes that the admin pages already use). */
    public const TONES = [
        self::DISABLED => 'bg-slate-100 text-slate-500',
        self::OFFLINE => 'bg-slate-700 text-white',
        self::PROBLEM => 'bg-red-100 text-red-800',
        self::PRINTING => 'bg-action-soft text-action-dark',
        self::BED => 'bg-amber-100 text-amber-900',
        self::STANDBY => 'bg-ok-soft text-ok',
    ];

    /**
     * @param  FarmPrintJob|null  $job  the printer's active job (FarmPrinter::activeJob)
     * @param  FarmOrder|null  $running  the order that prints on it (a manual printer has an order in "printing" and no job)
     * @return array{key: string, label: string, tone: string, reason: string|null, busy: bool}
     *                                                                                          `reason` says why it is a problem; `busy` = something is on the plate, the plate button is not offered
     */
    public static function of(FarmPrinter $printer, ?FarmPrintJob $job = null, ?FarmOrder $running = null): array
    {
        $busy = $job !== null || $running !== null || $printer->isPrintingNow();
        $reason = null;
        $key = match (true) {
            ! $printer->enabled => self::DISABLED,
            ! $printer->isOnline() => self::OFFLINE,
            ($reason = self::problem($printer, $job)) !== null => self::PROBLEM,
            $busy => self::PRINTING,
            ! $printer->bed_clear => self::BED,
            default => self::STANDBY,
        };

        return ['key' => $key, 'label' => __('farm.admin.ov.status.'.$key), 'tone' => self::TONES[$key], 'reason' => $key === self::PROBLEM ? $reason : null, 'busy' => $busy];
    }

    /** Why the printer needs a person, or null when it does not. */
    public static function problem(FarmPrinter $printer, ?FarmPrintJob $job = null): ?string
    {
        $message = trim((string) $printer->message);
        if ($printer->isAgentDriven() && $printer->state === FarmPrinter::STATE_ERROR) {
            return $message !== '' ? $message : __('farm.admin.ov.reason.error');
        }
        // before the unknown state: a printer the agent cannot see after a refused cancel still holds the cancelled print,
        // and the box on its card says so (FarmCommand::cancelState counts `unknown` as refused)
        $cancel = self::cancelUnconfirmed($printer);
        if ($cancel !== null) {
            // the words of the box on the printer's card, with the order the cancel belongs to
            $number = $cancel->printJob?->order?->number;

            return __('farm.admin.cancel.'.$cancel->cancel_state).($number ? ' · '.$number : '');
        }
        if (($printer->isAgentDriven() && $printer->state === FarmPrinter::STATE_UNKNOWN) || $job?->status === FarmPrintJob::STATUS_UNKNOWN) {
            return __('farm.admin.ov.reason.unknown');
        }
        // a paused print waits for a person whoever paused it (Roman, 12 Nov 2026); the printer's own words say why when it has any
        if ($job?->status === FarmPrintJob::STATUS_PAUSED || ($printer->isAgentDriven() && $printer->state === FarmPrinter::STATE_PAUSED)) {
            return $message !== '' && trim((string) ($printer->telemetry['message'] ?? '')) === $message ? $message : __('farm.admin.ov.reason.paused');
        }
        // what the printer says right now (the agent repeats it in the telemetry while it lasts): the reason of a pause,
        // the ACE's complaint. While the print runs a message is only a note; a message that has been repeating for
        // longer than MESSAGE_FRESH_MINUTES on a printer that neither pauses nor fails is no news any more.
        if (self::messageFresh($printer) && ! ($printer->isAgentDriven() && $printer->state === FarmPrinter::STATE_PRINTING)) {
            return $message;
        }

        return null;
    }

    /** The printer's last report said these very words (AgentService::printerMessage wipes them when a report comes without). */
    public static function messageLive(FarmPrinter $printer): bool
    {
        $message = trim((string) $printer->message);

        return $message !== '' && trim((string) ($printer->telemetry['message'] ?? '')) === $message;
    }

    /** Live, and news: said for the first time less than MESSAGE_FRESH_MINUTES ago. */
    public static function messageFresh(FarmPrinter $printer): bool
    {
        return self::messageLive($printer) && ($printer->message_at === null || $printer->message_at->gt(now()->subMinutes(self::MESSAGE_FRESH_MINUTES)));
    }

    /**
     * How the printer's card shows its message: `fresh` (amber: said now and news, or the reason of the pause or the
     * error that lasts), `lasting` (grey: still said, for longer than MESSAGE_FRESH_MINUTES), `last` (grey: the last
     * words of a printer the agent cannot reach); null without a message.
     */
    public static function messageKind(FarmPrinter $printer): ?string
    {
        if (trim((string) $printer->message) === '') {
            return null;
        }
        if (! self::messageLive($printer)) {
            return 'last';
        }

        return self::messageFresh($printer) || in_array($printer->state, [FarmPrinter::STATE_PAUSED, FarmPrinter::STATE_ERROR], true) ? 'fresh' : 'lasting';
    }

    /**
     * The cancel this printer has not confirmed (FarmCommand::CANCEL_WAITING) or did not carry out (CANCEL_REFUSED),
     * with the state in `cancel_state`; null when there is none. One source of truth with the box on the printer's
     * card, the order page and the order list: FarmCommand::cancelState() through FarmPrinter::unconfirmedCancel()
     * (docs/AM.md §13c). The order may be cancelled and its job closed already, so this does not look at the job.
     */
    public static function cancelUnconfirmed(FarmPrinter $printer): ?FarmCommand
    {
        return $printer->isAgentDriven() ? $printer->unconfirmedCancel() : null;
    }
}
