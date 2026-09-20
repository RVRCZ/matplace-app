<?php

namespace App\Console\Commands;

use App\Models\Inquiry;
use App\Models\Quote;
use Illuminate\Console\Command;

/** Open inquiries past expires_at → expired; their open offers → expired. Scheduled hourly. */
class ExpireInquiries extends Command
{
    protected $signature = 'matplace:expire-inquiries';

    protected $description = 'Expire inquiries and offers past their validity';

    public function handle(): int
    {
        $n = 0;
        Inquiry::whereIn('status', [Inquiry::STATUS_PENDING, Inquiry::STATUS_OPEN, Inquiry::STATUS_OFFERED])
            ->where('expires_at', '<', now())->each(function (Inquiry $i) use (&$n) {
                $i->forceFill(['status' => Inquiry::STATUS_EXPIRED])->save();
                $i->offers()->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])->update(['status' => Quote::STATUS_EXPIRED]);
                $n++;
            });
        Quote::whereNull('inquiry_id')->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])
            ->whereNotNull('valid_until')->where('valid_until', '<', now()->startOfDay())->update(['status' => Quote::STATUS_EXPIRED]);
        $this->info("Expired {$n} inquiries.");

        return self::SUCCESS;
    }
}
