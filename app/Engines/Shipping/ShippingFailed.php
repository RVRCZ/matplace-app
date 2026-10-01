<?php

namespace App\Engines\Shipping;

use App\Engines\Exceptions\EngineException;

/** The carrier refused or could not be reached. The message is the carrier's own answer, for the operator to read. */
class ShippingFailed extends EngineException {}
