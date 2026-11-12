<?php

namespace App\Engines\Exceptions;

/**
 * The work this process did is no longer wanted (a farm order asked for another slice while this one ran, docs/AL.md
 * §22): the child process was killed and nothing of its result may be written. Deliberately no EngineException: the
 * places that fall back on something else when an engine fails must not fall back here.
 */
final class SupersededException extends \RuntimeException {}
