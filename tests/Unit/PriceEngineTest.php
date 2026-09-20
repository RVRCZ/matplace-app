<?php

namespace Tests\Unit;

use App\Domain\Calculation\PriceEngine;
use App\Domain\Calculation\PricingProfile;
use PHPUnit\Framework\TestCase;

class PriceEngineTest extends TestCase
{
    private function engine(): PriceEngine
    {
        return new PriceEngine(require __DIR__.'/../../config/pricing.php');
    }

    private function profile(array $over = []): PricingProfile
    {
        return PricingProfile::fromArray($over + [
            'key' => 't', 'hourly_rate' => 60, 'price_per_gram' => 2, 'setup_fee' => 30, 'margin_pct' => 0, 'min_price' => 0, 'lead_time_days' => 3,
        ]);
    }

    public function test_basic_breakdown_and_rounding(): void
    {
        // 10 g × 2 = 20; 90 min = 1.5 h × 60 = 90; unit 110; + setup 30 = 140 → ceil to 10 = 140
        $b = $this->engine()->price(10, 90, 1, $this->profile());
        $this->assertSame(20.0, $b->unitMaterial);
        $this->assertSame(90.0, $b->unitTime);
        $this->assertSame(140.0, $b->subtotal);
        $this->assertSame(140.0, $b->total);

        $b2 = $this->engine()->price(10.3, 90, 1, $this->profile());
        $this->assertSame(150.0, $b2->total); // 140.6 → 150
    }

    public function test_slower_printer_takes_longer_and_costs_more_time(): void
    {
        // 100 min on the reference printer, a slower printer (×1.8) needs 180 min = 3 h × 60 = 180
        $b = $this->engine()->price(10, 100, 1, $this->profile(['time_factor' => 1.8]));
        $this->assertSame(180, $b->minutes);
        $this->assertSame(180.0, $b->unitTime);
        $this->assertSame(100, $this->engine()->price(10, 100, 1, $this->profile())->minutes);
        $this->assertSame(180, $b->toArray()['minutes']);
    }

    public function test_quantity_setup_once_and_discount(): void
    {
        $p = $this->profile(['qty_discounts' => [['from' => 10, 'pct' => 10]]]);
        $b = $this->engine()->price(10, 90, 10, $p);
        $this->assertSame(110.0 * 10 + 30, $b->subtotal);
        $this->assertSame(10.0, $b->discountPct);
        $this->assertSame(1130 * 0.1, $b->discount);
        $this->assertSame(ceil(1017 / 10) * 10, $b->total); // 1020
    }

    public function test_margin_min_price_and_royalty(): void
    {
        $p = $this->profile(['margin_pct' => 20, 'min_price' => 500]);
        $b = $this->engine()->price(10, 90, 1, $p, royaltyPerPiece: 25);
        $this->assertSame(25.0, $b->unitRoyalty);
        $this->assertSame((110 + 25 + 30) * 0.2, $b->margin);
        $this->assertSame(500.0, $b->total); // 198 → min 500
    }

    public function test_overrides_replace_single_line(): void
    {
        $b = $this->engine()->price(10, 90, 1, $this->profile(), overrideUnitTime: 50);
        $this->assertSame(50.0, $b->unitTime);
        $this->assertSame(100.0, $b->total); // 20 + 50 + 30
    }

    public function test_orientation_profiles_and_range(): void
    {
        $e = $this->engine();
        $all = $e->priceAll(20, 120, 1, $e->orientationProfiles());
        $this->assertCount(3, $all);
        $this->assertSame('budget', $all[0]->profileKey);
        [$min, $max] = $e->range($all);
        $this->assertLessThanOrEqual($max, $min);
        [$rmin, $rmax] = $e->range($all, rough: true);
        $this->assertLessThanOrEqual($min, $rmin);
        $this->assertGreaterThanOrEqual($max, $rmax);
    }
}
