<?php

namespace App\Engines\Social;

use App\Engines\Exceptions\EngineException;

/** Meta refused or could not be reached. The message is Meta's own answer, for the admin to read. */
class MetaFailed extends EngineException
{
    /** Meta was still working on it (a video being processed): the same call may succeed a little later. */
    public bool $retryLater = false;

    public static function later(string $message): self
    {
        $e = new self($message);
        $e->retryLater = true;

        return $e;
    }
}
