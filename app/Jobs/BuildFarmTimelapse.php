<?php

namespace App\Jobs;

use App\Models\FarmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The frames the agent sent during the print (one a minute) become a short MP4 the customer can watch and share.
 * Needs ffmpeg on the server (`FFMPEG_BIN`, default `ffmpeg`); without it the frames stay and nothing else happens.
 */
class BuildFarmTimelapse implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = FarmOrder::find($this->orderId);
        $disk = Storage::disk(config('farm.disk'));
        if (! $order || $order->timelapse_path) {
            return;
        }
        $frames = collect($disk->files($order->dir().'/frames'))->filter(fn ($f) => str_ends_with($f, '.jpg'))->sort()->values();
        if ($frames->count() < 4) {
            return;   // a print shorter than a few minutes has no story to tell
        }
        $dir = $disk->path($order->dir());
        $list = $dir.'/frames.txt';
        file_put_contents($list, $frames->map(fn ($f) => "file '".$disk->path($f)."'\nduration 0.125")->implode("\n")."\nfile '".$disk->path($frames->last())."'\n");
        $out = $dir.'/timelapse.mp4';
        $bin = (string) config('farm.ffmpeg', 'ffmpeg');
        $r = Process::timeout(240)->run([$bin, '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', $list,
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2,format=yuv420p', '-r', '8', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-movflags', '+faststart', $out]);
        @unlink($list);
        if (! $r->successful() || ! is_file($out)) {
            Log::warning('Farm time-lapse failed', ['order' => $order->id, 'error' => mb_substr($r->errorOutput(), 0, 500)]);

            return;
        }
        $order->forceFill(['timelapse_path' => $order->dir().'/timelapse.mp4'])->save();
        // the frames did their job
        $disk->deleteDirectory($order->dir().'/frames');
    }
}
