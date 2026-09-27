<?php

namespace App\Domain\Farm;

use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use Illuminate\Support\Facades\DB;

/**
 * Queue → printer. A print starts by itself only when an operator has declared the plate empty, the agent says the
 * printer is idle and nothing else is running on it. Starting consumes the "plate is clear" flag: the next print
 * needs a person to take this one off and say so again.
 */
final class Dispatcher
{
    /** Try to start the next queued order of this printer. Returns the job when one was started. */
    public function kick(FarmPrinter $printer): ?FarmPrintJob
    {
        return DB::transaction(function () use ($printer) {
            $printer = FarmPrinter::whereKey($printer->id)->lockForUpdate()->first();
            if (! $printer || ! $printer->readyForAutoStart()) {
                return null;
            }
            $order = $this->queue($printer)->first();
            if (! $order || ! $order->gcode_path) {
                return null;
            }

            return $this->start($printer, $order);
        });
    }

    /** Queued orders of a printer in the order they will print. */
    public function queue(FarmPrinter $printer)
    {
        return FarmOrder::where('farm_printer_id', $printer->id)->where('status', FarmOrder::STATUS_QUEUED)->orderBy('queued_at')->orderBy('id')->get();
    }

    private function start(FarmPrinter $printer, FarmOrder $order): FarmPrintJob
    {
        $slot = (int) ($order->slot?->slot ?? 0);
        // a multi-plate order: the next plate in line; the last one may have its own (smaller) G-code
        $plate = $order->nextPlate();
        $gcode = $order->absoluteGcodePath($plate);
        $job = FarmPrintJob::create([
            'farm_order_id' => $order->id, 'farm_printer_id' => $printer->id, 'slot' => $slot, 'plate' => $plate,
            'status' => FarmPrintJob::STATUS_PENDING, 'remote_filename' => 'matplace-'.$order->number.($order->plates > 1 ? '-p'.$plate : '').'.gcode',
        ]);
        FarmCommand::create([
            'farm_printer_id' => $printer->id, 'farm_print_job_id' => $job->id, 'type' => FarmCommand::TYPE_START,
            'payload' => ['job_id' => $job->id, 'filename' => $job->remote_filename, 'slot' => $slot, 'plate' => $plate, 'sha256_slot0' => $gcode && is_file($gcode) ? hash_file('sha256', $gcode) : $order->gcode_sha256],
        ]);
        $printer->update(['bed_clear' => false]);

        return $job;
    }

    /**
     * When will this order start and finish? Minutes from now, on its printer, with the corrected times.
     *
     * @return array{start_in: int, finish_in: int, ahead: int, blocked: string|null}|null
     */
    public function estimate(FarmOrder $order, FarmSettings $settings): ?array
    {
        $printer = $order->printer;
        if (! $printer || ! in_array($order->status, [FarmOrder::STATUS_PAID, FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING], true)) {
            return null;
        }
        $swap = (int) $settings->get('changeover_minutes');
        // several plates print one after another with a plate change between them
        $plates = max(1, (int) $order->plates);
        $perPlate = (int) ceil((int) $order->est_minutes / $plates * $printer->time_factor);
        $own = $perPlate * $plates + ($plates - 1) * $swap;
        $job = $printer->activeJob();

        if ($order->status === FarmOrder::STATUS_PRINTING) {
            $platesLeft = max(0, $plates - (int) $order->plates_done - 1);
            $thisPlate = $job && $job->farm_order_id === $order->id ? (int) ceil($perPlate * (1 - min(100, $job->progress) / 100)) : $perPlate;

            return ['start_in' => 0, 'finish_in' => $thisPlate + $platesLeft * ($perPlate + $swap), 'ahead' => 0, 'blocked' => null];
        }

        // the job on the machine is this very order (sent, the printer is heating up): nobody is ahead of it
        if ($job && $job->farm_order_id === $order->id) {
            return ['start_in' => 0, 'finish_in' => $own, 'ahead' => 0, 'blocked' => $printer->isOnline() ? null : 'offline'];
        }

        $wait = 0;
        if ($job) {
            $running = (int) ceil((int) $job->order->est_minutes * $printer->time_factor);
            $wait += (int) ceil($running * (1 - min(100, $job->progress) / 100)) + $swap;
        }
        $ahead = 0;
        foreach ($this->queue($printer) as $other) {
            if ($other->id === $order->id) {
                break;
            }
            $wait += (int) ceil((int) $other->est_minutes * $printer->time_factor) + $swap;
            $ahead++;
        }
        $blocked = match (true) {
            ! $printer->isOnline() => 'offline',
            $order->status === FarmOrder::STATUS_PAID => 'approval',
            ! $job && $ahead === 0 && ! $printer->bed_clear => 'plate',      // next in line, waits for the operator
            default => null,
        };

        return ['start_in' => $wait, 'finish_in' => $wait + $own, 'ahead' => $ahead + ($job ? 1 : 0), 'blocked' => $blocked];
    }
}
