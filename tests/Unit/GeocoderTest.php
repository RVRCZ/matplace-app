<?php

namespace Tests\Unit;

use App\Domain\Geo\Geocoder;
use PHPUnit\Framework\TestCase;

class GeocoderTest extends TestCase
{
    public function test_distance_brno_praha(): void
    {
        $km = Geocoder::distanceKm(49.1951, 16.6068, 50.0875, 14.4213);
        $this->assertEqualsWithDelta(184, $km, 3);
        $this->assertSame(0.0, Geocoder::distanceKm(50.0, 14.0, 50.0, 14.0));
    }
}
