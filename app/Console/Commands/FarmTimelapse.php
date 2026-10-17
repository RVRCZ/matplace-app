<?php

namespace App\Console\Commands;

use App\Jobs\BuildFarmTimelapse;
use App\Models\FarmOrder;
use Illuminate\Console\Command;

/** Builds the videos of an order again (customer's, Short, 16:9), from the frames kept for BuildFarmTimelapse::KEEP_FRAMES_DAYS days. */
class FarmTimelapse extends Command
{
    protected $signature = 'farm:timelapse {number : order number, e.g. F26-000013}';

    protected $description = 'Build the videos of a farm order again: the customer\'s time-lapse, the square Short and the 16:9';

    public function handle(): int
    {
        $order = FarmOrder::where('number', $this->argument('number'))->first();
        if (! $order) {
            $this->error('No such order.');

            return self::FAILURE;
        }
        (new BuildFarmTimelapse($order->id, rebuild: true))->handle();
        $order->refresh();
        if (! $order->timelapse_path) {
            $this->error('No video: the frames are gone or ffmpeg failed (see the log).');

            return self::FAILURE;
        }
        $this->info('time-lapse: '.$order->timelapse_path.' | Short: '.($order->timelapse_short_path ?: '-').' | 16:9: '.($order->timelapse_wide_path ?: '-'));
        if ($order->videos()->whereNotNull('youtube_id')->exists()) {
            $this->warn('The video on YouTube is the old one: /admin/youtube -> "Nahradit novou verzí" puts the new one there.');
        }

        return self::SUCCESS;
    }
}
