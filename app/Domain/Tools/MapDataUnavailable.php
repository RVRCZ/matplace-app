<?php

namespace App\Domain\Tools;

/** A map service did not answer (Overpass down, the height tiles unreachable, the geocoder busy): `reason` names which, the page says what to try. */
final class MapDataUnavailable extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($reason.($detail !== '' ? ': '.$detail : ''));
    }
}
