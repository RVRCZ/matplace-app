<?php

namespace Tests\Unit;

use App\Domain\Farm\PriceCalculator;
use PHPUnit\Framework\TestCase;

class FarmPriceCalculatorTest extends TestCase
{
    private function rates(array $over = []): array
    {
        return $over + [
            'hourly_rate' => 40, 'price_per_gram' => 1.5, 'fixed_fee' => 30, 'min_price' => 0,
            'vat_percent' => 0, 'rounding' => 0, 'time_factor' => 1, 'weight_factor' => 1,
        ];
    }

    public function test_formula_time_plus_material_plus_fixed(): void
    {
        // 90 min = 1.5 h × 40 = 60; 20 g × 1.5 = 30; fixed 30 → 120
        $p = (new PriceCalculator)->price(90, 20, $this->rates());
        $this->assertSame(60.0, $p['time']);
        $this->assertSame(30.0, $p['material']);
        $this->assertSame(30.0, $p['fixed']);
        $this->assertSame(120.0, $p['net']);
        $this->assertSame(120.0, $p['total']);
        $this->assertFalse($p['min_price_applied']);
    }

    public function test_printer_correction_factors_scale_time_and_weight_separately(): void
    {
        // time ×1.2: 1.8 h × 40 = 72; weight ×1.1: 22 g × 1.5 = 33; + 30 → 135
        $p = (new PriceCalculator)->price(90, 20, $this->rates(['time_factor' => 1.2, 'weight_factor' => 1.1]));
        $this->assertSame(72.0, $p['time']);
        $this->assertSame(33.0, $p['material']);
        $this->assertSame(135.0, $p['net']);
        $this->assertSame(1.8, $p['billed_hours']);
        $this->assertSame(22.0, $p['billed_grams']);
    }

    public function test_minimum_price_applies_before_vat(): void
    {
        // 6 min, 1 g: 4 + 1.5 + 30 = 35.5 → minimum 99 → ×1.21 = 119.79
        $p = (new PriceCalculator)->price(6, 1, $this->rates(['min_price' => 99, 'vat_percent' => 21]));
        $this->assertTrue($p['min_price_applied']);
        $this->assertSame(99.0, $p['net']);
        $this->assertSame(119.79, $p['total']);
        $this->assertSame(20.79, $p['vat']);
    }

    public function test_rounding_goes_up_to_the_configured_step_and_vat_absorbs_it(): void
    {
        // net 120 × 1.21 = 145.2 → step 5 → 150; net + vat must equal what the customer pays
        $p = (new PriceCalculator)->price(90, 20, $this->rates(['vat_percent' => 21, 'rounding' => 5]));
        $this->assertSame(150.0, $p['print_total']);
        $this->assertSame(4.8, $p['rounding_added']);
        $this->assertEqualsWithDelta($p['print_total'], $p['net'] + $p['vat'], 0.001);
    }

    public function test_an_exact_multiple_is_not_pushed_to_the_next_step(): void
    {
        $this->assertSame(120.0, PriceCalculator::roundUp(120.0, 10));
        $this->assertSame(120.0, PriceCalculator::roundUp(120.00000001, 10));
        $this->assertSame(130.0, PriceCalculator::roundUp(120.01, 10));
        $this->assertSame(120.46, PriceCalculator::roundUp(120.456, 0));
    }

    public function test_shipping_is_added_after_rounding_and_not_rounded_again(): void
    {
        $p = (new PriceCalculator)->price(90, 20, $this->rates(['rounding' => 10, 'shipping' => 99]));
        $this->assertSame(120.0, $p['print_total']);
        $this->assertSame(219.0, $p['total']);
    }

    public function test_breakdown_keeps_the_inputs_for_reproducibility(): void
    {
        $p = (new PriceCalculator)->price(90, 20.04, $this->rates(['time_factor' => 1.3]));
        $this->assertSame(90, $p['inputs']['minutes']);
        $this->assertSame(20.0, $p['inputs']['grams']);
        $this->assertSame(1.3, $p['inputs']['time_factor']);
        $this->assertSame(40.0, $p['inputs']['hourly_rate']);
    }

    public function test_negative_or_zero_input_never_produces_a_negative_price(): void
    {
        $p = (new PriceCalculator)->price(-5, -3, $this->rates());
        $this->assertSame(30.0, $p['total']);
    }
}
