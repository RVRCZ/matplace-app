<?php

namespace App\Engines\Import;

use App\Engines\Exceptions\EngineException;

/**
 * A model or profile could not be read from its source. `reason` is a code the page translates
 * (designer.import.error.<reason>): not_found | blocked | unreadable.
 */
class ImportFailed extends EngineException
{
    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($reason.($detail !== '' ? ': '.$detail : ''));
    }
}
