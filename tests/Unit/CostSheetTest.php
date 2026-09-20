<?php

namespace Tests\Unit;

use App\Domain\Quote\CostSheet;
use PHPUnit\Framework\TestCase;

class CostSheetTest extends TestCase
{
    private function base(array $over = []): array
    {
        return $over + ['quantity' => 4, 'material_cost' => 60, 'machine_hours' => 3, 'machine_rate' => 80, 'setup_cost' => 40, 'labour_minutes' => 20, 'labour_rate' => 300, 'failure_pct' => 10, 'mode' => 'markup', 'pct' => 25];
    }

    public function test_every_step_of_the_sum(): void
    {
        $s = CostSheet::compute($this->base());
        $this->assertSame(240.0, $s['machine_cost']);
        $this->assertSame(30.0, $s['reserve']);                 // 10 % of material + machine (300)
        $this->assertSame(100.0, $s['labour_cost']);
        $this->assertSame(470.0, $s['cost']);                   // 300 + 30 + 40 + 100
        $this->assertSame(588.0, $s['final']);                  // 470 × 1.25 = 587.5 → rounded up
        $this->assertSame(147.0, $s['unit_price']);
        $this->assertFalse($s['overridden']);
    }

    public function test_markup_and_margin_are_different_things_and_both_are_reported(): void
    {
        $markup = CostSheet::compute(['material_cost' => 100, 'mode' => 'markup', 'pct' => 25]);
        $margin = CostSheet::compute(['material_cost' => 100, 'mode' => 'margin', 'pct' => 20]);
        $this->assertSame(125.0, $markup['final']);
        $this->assertSame(125.0, $margin['final']);             // 25 % markup = 20 % margin
        $this->assertSame(25.0, $margin['markup_pct']);
        $this->assertSame(20.0, $markup['margin_pct']);

        // the same number means a very different price
        $this->assertSame(150.0, CostSheet::compute(['material_cost' => 100, 'mode' => 'markup', 'pct' => 50])['final']);
        $this->assertSame(200.0, CostSheet::compute(['material_cost' => 100, 'mode' => 'margin', 'pct' => 50])['final']);
        // a margin can never reach 100 %
        $this->assertSame(95.0, CostSheet::compute(['material_cost' => 100, 'mode' => 'margin', 'pct' => 100])['pct']);
    }

    public function test_minimum_price_rounding_and_the_printers_own_price(): void
    {
        $min = CostSheet::compute($this->base(['min_price' => 900]));
        $this->assertSame(900.0, $min['final']);
        $this->assertTrue($min['minimum_applied']);

        $this->assertSame(590.0, CostSheet::compute($this->base(), 10)['final']);          // round up to tens

        $own = CostSheet::compute($this->base(['final_price' => 650]));
        $this->assertSame(650.0, $own['final']);
        $this->assertSame(588.0, $own['suggested']);
        $this->assertTrue($own['overridden']);
        $this->assertSame(180.0, $own['profit']);
        $this->assertEqualsWithDelta(27.7, $own['margin_pct'], 0.05);

        // selling under cost is allowed, the sheet just says so
        $this->assertLessThan(0, CostSheet::compute($this->base(['final_price' => 300]))['profit']);
    }

    public function test_garbage_input_never_produces_nonsense(): void
    {
        $s = CostSheet::compute(['quantity' => -3, 'material_cost' => 'abc', 'machine_hours' => -5, 'failure_pct' => 500, 'mode' => 'x', 'pct' => -10]);
        $this->assertSame(1, $s['quantity']);
        $this->assertSame(0.0, $s['cost']);
        $this->assertSame(100.0, $s['failure_pct']);
        $this->assertSame('markup', $s['mode']);
        $this->assertNull($s['markup_pct']);
    }
}
