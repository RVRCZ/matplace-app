<?php

namespace Tests\Feature;

use App\Domain\Farm\PublicStats;
use App\Models\FarmOrder;
use App\Models\FarmOrderEvent;
use App\Models\FarmVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The farm's public figures (App\Domain\Farm\PublicStats): what counts, what does not, and that they are remembered for an hour. */
final class PublicStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_figures_count_paid_prints_the_handover_median_the_last_month_and_the_published_videos(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        // printed: paid and done or handed over
        FarmOrder::factory()->paid(now()->subDays(40))->done(now()->subDays(38))->create();            // older than a month
        FarmOrder::factory()->paid(now()->subDays(5))->done(now()->subDays(3))->create();
        $handed = FarmOrder::factory()->paid(now()->subDays(10))->handedOver(now()->subDays(6))->create();
        FarmOrderEvent::factory()->for($handed, 'order')->create(['created_at' => now()->subDays(6)]);        // 4 days
        $quick = FarmOrder::factory()->paid(now()->subDays(3))->handedOver(now()->subDays(2))->create();
        FarmOrderEvent::factory()->for($quick, 'order')->create(['created_at' => now()->subDays(2)]);         // 1 day
        $slow = FarmOrder::factory()->paid(now()->subDays(20))->handedOver(now()->subDays(9))->create();
        FarmOrderEvent::factory()->for($slow, 'order')->create(['created_at' => now()->subDays(9)]);          // 11 days
        // not printed: unpaid test print that is done, a paid one still printing, a failed one, a cancelled one
        FarmOrder::factory()->done()->create(['kind' => FarmOrder::KIND_TEST]);
        FarmOrder::factory()->paid()->create(['status' => FarmOrder::STATUS_PRINTING]);
        FarmOrder::factory()->paid()->create(['status' => FarmOrder::STATUS_FAILED]);
        FarmOrder::factory()->create(['status' => FarmOrder::STATUS_CANCELLED]);
        // an unpaid order handed over by hand (a showcase) does not count for the median either
        $free = FarmOrder::factory()->handedOver()->create(['kind' => FarmOrder::KIND_SHOWCASE]);
        FarmOrderEvent::factory()->for($free, 'order')->create();
        // videos: two published, one waiting, one rejected
        FarmVideo::factory()->published()->for($handed, 'order')->create();
        FarmVideo::factory()->published()->for($quick, 'order')->create();
        FarmVideo::factory()->for($slow, 'order')->create();
        FarmVideo::factory()->for(FarmOrder::factory()->paid()->done()->create(), 'order')->create(['status' => FarmVideo::STATUS_REJECTED]);

        $s = PublicStats::compute();
        $this->assertSame(6, $s['printed'], 'two done, three handed over, and the done one the rejected video hangs on');
        $this->assertSame(4.0, $s['handed_over_days_median'], 'days 1, 4, 11: the middle one');
        $this->assertSame(5, $s['last_30_days_printed']);
        $this->assertSame(2, $s['videos_published']);
    }

    public function test_the_median_takes_the_last_fifty_handovers_and_an_even_count_averages_the_middle_two(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        // sixty handovers: the oldest ten took 30 days, the last fifty took 2 or 4 days
        for ($i = 0; $i < 60; $i++) {
            $handedAt = now()->subDays(60 - $i);
            $days = $i < 10 ? 30 : ($i % 2 ? 4 : 2);
            $o = FarmOrder::factory()->paid($handedAt->copy()->subDays($days))->handedOver($handedAt)->create();
            FarmOrderEvent::factory()->for($o, 'order')->create(['created_at' => $handedAt]);
        }
        $this->assertSame(3.0, PublicStats::compute()['handed_over_days_median'], '25 × 2 and 25 × 4 days: the average of the middle two');
        $this->assertSame(60, PublicStats::compute()['printed']);
    }

    public function test_nothing_yet_gives_zeros_and_no_median_and_the_summary_is_remembered_for_an_hour(): void
    {
        Cache::flush();
        $this->assertSame(['printed' => 0, 'handed_over_days_median' => null, 'last_30_days_printed' => 0, 'videos_published' => 0], PublicStats::summary());
        FarmOrder::factory()->paid()->done()->create();
        $this->assertSame(0, PublicStats::summary()['printed'], 'the hour has not passed');
        $this->travel(61)->minutes();
        $this->assertSame(1, PublicStats::summary()['printed']);
        FarmVideo::factory()->published()->create();
        PublicStats::forget();
        $this->assertSame(1, PublicStats::summary()['videos_published']);
    }
}
