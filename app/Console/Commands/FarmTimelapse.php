<?php

namespace App\Console\Commands;

use App\Jobs\BuildFarmTimelapse;
use App\Models\FarmOrder;
use Illuminate\Console\Command;

/** Builds the time-lapse and the Short of an order again, from the frames kept for BuildFarmTimelapse::KEEP_FRAMES_DAYS days. */
class FarmTimelapse extends Command
{
    protected $signature = 'farm:timelapse {number : order number, e.g. F26-000013}';

    protected $description = 'Build the time-lapse video and the square Short of a farm order again';

    public function handle(): int
    {
        $order = FarmOrder::where('number', $this->argument('number'))->first();
        if (! $order) {
            $this->error('No such order.');

            return self::FAILURE;
        }
        $before = [$order->timelapse_path, $order->timelapse_short_path];
        (new BuildFarmTimelapse($order->id, rebuild: true))->handle();
        $order->refresh();
        if (! $order->timelapse_path) {
            $this->error('No video: the frames are gone or ffmpeg failed (see the log).');

            return self::FAILURE;
        }
        $this->info('time-lapse: '.$order->timelapse_path.' | Short: '.($order->timelapse_short_path ?: '-'));
        if ($order->video()->whereNotNull('youtube_id')->exists()) {
            $this->warn('The video on YouTube is the old one: /admin/youtube -> "Nahradit novou verzí" puts the new one there.');
        }

        return self::SUCCESS;
    }
}
