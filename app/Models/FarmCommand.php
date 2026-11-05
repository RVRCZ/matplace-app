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

    protected $fillable = ['farm_printer_id', 'farm_print_job_id', 'type', 'payload', 'status', 'result', 'created_by', 'sent_at', 'finished_at'];

    protected $casts = ['payload' => 'array', 'sent_at' => 'datetime', 'finished_at' => 'datetime'];

    /**
     * The agent's note on how the command went, in the operator's language where the note is one the agent is known to
     * write (the English stays in the row): "slot 1 not checked, the printer does not report its slots; slot 2 not checked…"
     * of a machine whose positions the API does not report (Prusa INDX, docs/AM.md §12j) becomes one sentence naming the
     * positions. Anything else is shown as the agent wrote it.
     */
    public function resultText(): ?string
    {
        $result = trim((string) $this->result);
        if ($result === '') {
            return null;
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

    public function printer(): BelongsTo
    {
        return $this->belongsTo(FarmPrinter::class, 'farm_printer_id');
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(FarmPrintJob::class, 'farm_print_job_id');
    }
}
