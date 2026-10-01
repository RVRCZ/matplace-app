<?php

namespace App\Engines\Social;

use App\Engines\Exceptions\EngineException;

/** Meta refused or could not be reached. The message is Meta's own answer, for the admin to read. */
class MetaFailed extends EngineException {}
