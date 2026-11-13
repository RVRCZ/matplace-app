<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something the agent should do with a printer. The agent polls, takes pending commands and reports the result. */
class FarmCommand extends Model
{
    public const TYPE_START = 'start';

    public const TYPE_PAUSE = 'pause';

    public const TYPE_RESUME = 'resume';

    public const TYPE_CANCEL = 'cancel';

    /** payload {on: bool} - chamber light for the camera; needs no running job */
    public const TYPE_LIGHT = 'light';

    public const TYPE_DRY = 'dry';        // filament dryer of the spool unit (ACE): payload on, temp, minutes

    /** clears the "error" a failed print leaves behind (Klipper keeps it until told otherwise); needs no job, moves and heats nothing */
    public const TYPE_RESET = 'reset';

    /** the printer's storage (docs/AM.md §10): the list of its files to the server; payloads: fetch {name}, delete {name}, cleanup {keep: [names]} */
    public const TYPE_FILES = 'files';

    public const TYPE_FETCH = 'fetch';

    public const TYPE_DELETE = 'delete';

    public const TYPE_CLEANUP = 'cleanup';

    public const FILE_TYPES = [self::TYPE_FILES, self::TYPE_FETCH, self::TYPE_DELETE, self::TYPE_CLEANUP];

    /** A cancel the agent has not answered yet: the order is cancelled on the web, the printer has not said so. */
    public const CANCEL_WAITING = 'waiting';

    /** A cancel the printer did not carry out: it still holds the print (agent 0.2.13, docs/AM.md §13). */
    public const CANCEL_REFUSED = 'refused';

    /** How long an unanswered cancel is shown as waiting; older rows are history (an agent that died in the middle). */
    public const CANCEL_SHOWN_DAYS = 7;

    /** How long a pause or a resume the agent has not answered counts as being carried out; after that a new one may be sent (an agent that died in the middle). */
    public const CONTROL_WAIT_MINUTES = 10;

    /** Filled by FarmPrinter::unconfirmedCancel() for the views (CANCEL_WAITING | CANCEL_REFUSED); not a column. */
    public ?string $cancel_state = null;

    /** What the agent answers to a pause asked while the printer still heats and levels (agent.py PAUSE_WHILE_STARTING). */
    public const PAUSE_WHILE_STARTING = 'the printer is getting ready (heating, levelling); a pause will work once the first layer has started';

    protected $fillable = ['farm_printer_id', 'farm_print_job_id', 'type', 'payload', 'status', 'result', 'created_by', 'sent_at', 'finished_at'];

    protected $casts = ['payload' => 'array', 'sent_at' => 'datetime', 'finished_at' => 'datetime'];

    /**
     * The agent's note on how the command went, in the operator's language where the note is one the agent is known to
     * write (the English stays in the row): "slot 1 not checked, the printer does not report its slots; slot 2 not checked…"
     * of a machine whose positions the API does not report (Prusa INDX, docs/AM.md §12j) becomes one sentence naming the
     * positions. Anything else is shown as the agent wrote it.
     */
    public function resultText(?string $locale = null): ?string
    {
        $result = trim((string) $this->result);
        if ($result === '') {
            return null;
        }
        $known = $this->controlText($result, $locale);
        if ($known !== null) {
            return $known;
        }
        $parts = array_map('trim', explode(';', $result));
        $slots = [];
        $rest = [];
        foreach ($parts as $part) {
            if (preg_match('/^slot (\d+) not checked, the printer does not report its slots$/', $part, $m)) {
                $slots[] = (int) $m[1];
            } elseif (preg_match('/^the printer took\b/', $part)) {
                $rest[] = __('farm.admin.result.printer_took');
            } elseif ($part !== '') {
                $rest[] = $part;
            }
        }
        if ($slots) {
            array_unshift($rest, __('farm.admin.result.slots_unchecked', ['slots' => implode(', ', $slots)]));
        }

        return implode('; ', $rest);
    }

    /**
     * The agent's sentences about a pause, a resume and a cancel (agent 0.2.13, docs/AM.md §13) in plain words, without
     * the technical line: a pause while the printer is getting ready, a pause or a resume the printer did not take, a
     * cancel the printer did not carry out, the second step that ended the print, the heaters. Null when the result is
     * none of them.
     */
    private function controlText(string $result, ?string $locale): ?string
    {
        $t = fn (string $key, array $replace = []) => __('farm.admin.result.'.$key, $replace, $locale);
        $state = fn (string $word) => in_array($word, ['printing', 'paused', 'error', 'idle', 'unknown'], true) ? __('farm.admin.state.'.$word, [], $locale) : $word;
        $heaters = fn (string $what) => strtr($what, ['nozzle' => $t('nozzle'), 'bed' => $t('bed')]);

        if ($result === self::PAUSE_WHILE_STARTING) {
            return $t('pause_starting');
        }
        if (in_array($this->type, [self::TYPE_PAUSE, self::TYPE_RESUME], true) && preg_match("/the printer's state did not change in (\\d+) s$/", $result, $m)) {
            return $t($this->type === self::TYPE_PAUSE ? 'pause_refused' : 'resume_refused', ['s' => $m[1]]);
        }
        // agent 0.2.15 (docs/AM.md §15): a pause or a resume the printer confirmed by its state, and one whose
        // wait a cancel cut short
        if (in_array($this->type, [self::TYPE_PAUSE, self::TYPE_RESUME], true)) {
            if (preg_match("/^confirmed by the printer's state after (\\d+) s/", $result, $m)) {
                return $t('control_confirmed', ['s' => $m[1]]);
            }
            if (preg_match('/a cancel came after (\\d+) s; the wait for the printer was given up$/', $result, $m)) {
                return $t('control_interrupted', ['s' => $m[1]]);
            }
        }
        if (str_starts_with($result, 'cancel not confirmed:')) {
            if (str_contains($result, 'has not answered')) {
                return $t('cancel_unreachable');
            }
            if (preg_match("/not the farm's \\((.+?)\\)/", $result, $m)) {
                return $t('cancel_foreign', ['file' => $m[1]]);
            }
            $still = preg_match('/the printer is still (\\w+) after/', $result, $m) ? $state($m[1]) : $state('unknown');
            if (preg_match('/ and after (.+?); stop the print/', $result, $m) || preg_match('/ and (\\S+) was refused/', $result, $m)) {
                return $t('cancel_stuck_step', ['state' => $still, 'step' => $m[1]]);
            }

            return $t('cancel_stuck', ['state' => $still]);
        }
        $out = [];
        if (preg_match('/but the printer stayed (\\w+) for (\\d+) s; (.+?) ended the print/', $result, $m)) {
            $out[] = $t('cancel_second_step', ['state' => $state($m[1]), 's' => $m[2], 'step' => $m[3]]);
        }
        if (preg_match('/the heaters were left on \\(([^)]*)\\), switched off with /', $result, $m)) {
            $out[] = $t('heaters_switched_off', ['what' => $heaters($m[1])]);
        } elseif (preg_match('/the heaters are still on \\(([^)]*)\\)/', $result, $m)) {
            $out[] = $t('heaters_still_on', ['what' => $heaters($m[1])]);
        }

        return $out ? implode(' ', $out) : null;
    }

    /**
     * The pause or the resume of this printer the agent has taken (or is about to take) and has not answered yet: the
     * printer is being paused or resumed right now. Null once the agent answered, when a later command of the printer
     * is newer than it, or after CONTROL_WAIT_MINUTES. Derived from the commands, nothing new is stored.
     */
    public static function controlInFlight(FarmPrinter $printer): ?self
    {
        $last = self::where('farm_printer_id', $printer->id)->whereIn('type', [self::TYPE_PAUSE, self::TYPE_RESUME, self::TYPE_CANCEL, self::TYPE_START])->latest('id')->first();

        return $last && in_array($last->type, [self::TYPE_PAUSE, self::TYPE_RESUME], true) && in_array($last->status, ['pending', 'sent'], true)
            && $last->created_at !== null && $last->created_at->gt(now()->subMinutes(self::CONTROL_WAIT_MINUTES)) ? $last : null;
    }

    /** The agent says the cancelled print left the heaters on and it could not switch them off. */
    public function heatersLeftOn(): bool
    {
        return $this->type === self::TYPE_CANCEL && str_contains((string) $this->result, 'the heaters are still on');
    }

    /**
     * A cancel the printer has not confirmed (CANCEL_WAITING: the agent has not answered yet) or did not carry out
     * (CANCEL_REFUSED: the command failed and the printer still prints, pauses or cannot be seen); null when the
     * cancel is done, when the printer has let go since (the operator stopped it on the screen), or when a later
     * start or cancel took the machine. Derived from the command and the printer's state, so nothing new is stored.
     */
    public function cancelState(): ?string
    {
        if ($this->type !== self::TYPE_CANCEL || ! in_array($this->status, ['pending', 'sent', 'failed'], true)
            || $this->created_at === null || $this->created_at->lt(now()->subDays(self::CANCEL_SHOWN_DAYS))) {
            return null;
        }
        $later = self::where('farm_printer_id', $this->farm_printer_id)->where('id', '>', $this->id)
            ->where(fn ($q) => $q->where(fn ($s) => $s->where('type', self::TYPE_START)->whereIn('status', ['sent', 'done']))
                ->orWhere(fn ($c) => $c->where('type', self::TYPE_CANCEL)))->exists();
        if ($later) {
            return null;
        }
        if ($this->status !== 'failed') {
            return self::CANCEL_WAITING;
        }

        return in_array($this->printer?->state, [FarmPrinter::STATE_PRINTING, FarmPrinter::STATE_PAUSED, FarmPrinter::STATE_UNKNOWN], true) ? self::CANCEL_REFUSED : null;
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(FarmPrintJob::class, 'farm_print_job_id');
    }
}
