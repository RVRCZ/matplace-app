<?php

namespace Tests\Unit;

use App\Engines\DTO\Dimensions;
use App\Engines\Slicer\OrcaSlicer;
use App\Jobs\PrepareFarmOrder;
use PHPUnit\Framework\TestCase;

/**
 * The prime tower's place on a plate of several filaments and the colours the slicer gets (docs/AL.md §10.8b): Orca
 * stops when the tower lands on a part and falls over when a filament has no colour of its own.
 */
final class OnePlateTowerTest extends TestCase
{
    private Dimensions $bed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bed = new Dimensions(260, 260, 260);
    }

    public function test_the_tower_goes_below_small_parts_in_the_middle(): void
    {
        $spot = PrepareFarmOrder::towerSpot([60.0, 60.0, 3.0], $this->bed, 5.0, 2);
        $this->assertSame(['x' => 117.5, 'y' => 10.0, 'shift' => [0.0, 0.0]], $spot);
    }

    public function test_the_medal_keeps_its_place_and_the_tower_takes_the_band_below(): void
    {
        // the medal of 19 Oct 2026: 195 × 176.5 mm on the Kobra X; the band below it is 36.75 mm with a 5 mm margin
        $spot = PrepareFarmOrder::towerSpot([195.0, 176.5, 4.4], $this->bed, 5.0, 2);
        $this->assertSame([0.0, 0.0], $spot['shift']);
        $this->assertSame(10.0, $spot['y']);
        $this->assertLessThan(260 - 176.5, $spot['y'] + 9 + 5, 'the tower (9 mm deep for two filaments) stays off the medal');
    }

    public function test_tall_parts_send_the_tower_to_the_left_band(): void
    {
        $spot = PrepareFarmOrder::towerSpot([100.0, 240.0, 10.0], $this->bed, 5.0, 2);
        $this->assertSame([0.0, 0.0], $spot['shift']);
        $this->assertSame(10.0, $spot['x']);
        $this->assertSame(124.0, $spot['y'], 'centred along the band, half its depth up');
    }

    public function test_a_tight_plate_is_pushed_off_the_band_the_tower_takes(): void
    {
        // 220 deep: 15 mm free above and below, the tower of two filaments needs 25 below; the parts move up 10
        $spot = PrepareFarmOrder::towerSpot([200.0, 220.0, 10.0], $this->bed, 5.0, 2);
        $this->assertSame(['x' => 117.5, 'y' => 10.0, 'shift' => [0.0, 10.0]], $spot);
        // 230 deep: 10 mm free, 20 together is too little below; 25 mm left and right, 50 together take the 38 the tower needs
        $spot = PrepareFarmOrder::towerSpot([200.0, 230.0, 10.0], $this->bed, 5.0, 2);
        $this->assertSame(['x' => 10.0, 'y' => 124.0, 'shift' => [13.0, 0.0]], $spot);
    }

    public function test_more_filaments_need_a_deeper_band(): void
    {
        $two = PrepareFarmOrder::towerSpot([200.0, 200.0, 10.0], $this->bed, 5.0, 2);
        $four = PrepareFarmOrder::towerSpot([200.0, 200.0, 10.0], $this->bed, 5.0, 4);
        $this->assertSame([0.0, 0.0], $two['shift']);
        $this->assertSame([0.0, 24.0], $four['shift'], '36 + 13 = 49 mm needed, 25 free: pushed up by 24');
    }

    public function test_a_plate_the_parts_fill_has_no_room_for_the_tower(): void
    {
        $this->assertNull(PrepareFarmOrder::towerSpot([250.0, 250.0, 10.0], $this->bed, 5.0, 2));
    }

    public function test_every_filament_gets_its_own_colour(): void
    {
        $this->assertSame(['#F4F4F0', '#C01818'], OrcaSlicer::filamentColours([['colour' => '#f4f4f0'], ['colour' => 'C01818']]));
        $this->assertSame(['#F2754E', '#2E86DE'], OrcaSlicer::filamentColours([['colour' => null], ['colour' => 'no colour']]), 'the palette where the spool has none');
        $this->assertSame(['#AABBCC', '#ABBCCD'], OrcaSlicer::filamentColours([['colour' => '#abc'], ['colour' => '#AABBCC']]), 'a twin is nudged a shade');
        $this->assertSame(['#F2754E', '#2E86DE', '#F3764F'], OrcaSlicer::filamentColours([['colour' => '#F2754E'], [], ['colour' => '#F2754E']]), 'the palette goes by position, a twin of a spool is nudged');
    }
}
