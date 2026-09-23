<?php

namespace App\Jobs;

use App\Models\FarmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The frames the agent sent during the print (one a minute) become a short MP4 the customer can watch and share:
 *
 *   black -> the logo appears and fades away -> 3, 2, 1 -> the print grows -> the finished piece holds still
 *   -> "Thank you for printing with us" and the logo.
 *
 * Needs ffmpeg on the server (`FFMPEG_BIN`, default `ffmpeg`); without it the frames stay and nothing else happens.
 */
class BuildFarmTimelapse implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    private const FPS = 8;

    private const FRAME_SECONDS = 0.125;    // one frame of the print per video frame

    private const HOLD_SECONDS = 4.0;       // the finished piece, still

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
        [$w, $h] = $this->size($disk->path($frames->first()));
        $dir = $disk->path($order->dir());
        $list = $dir.'/frames.txt';
        $thanks = $dir.'/thanks.txt';
        file_put_contents($list, $frames->map(fn ($f) => "file '".$disk->path($f)."'\nduration ".self::FRAME_SECONDS)->implode("\n")."\nfile '".$disk->path($frames->last())."'\n");
        file_put_contents($thanks, __('farm.timelapse.thanks', [], $order->user?->locale ?? config('app.locale')));
        $out = $dir.'/timelapse.mp4';
        $bin = (string) config('farm.ffmpeg', 'ffmpeg');
        $r = Process::timeout(240)->run([
            $bin, '-y', '-loglevel', 'error',
            '-f', 'concat', '-safe', '0', '-i', $list,            // 0: the print
            '-loop', '1', '-t', '8', '-i', public_path('img/logo.png'),   // 1: the logo
            '-filter_complex', $this->graph($w, $h, $frames->count(), $thanks),
            '-map', '[v]', '-r', (string) self::FPS, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '26', '-movflags', '+faststart', $out,
        ]);
        @unlink($list);
        @unlink($thanks);
        if (! $r->successful() || ! is_file($out)) {
            Log::warning('Farm time-lapse failed', ['order' => $order->id, 'error' => mb_substr($r->errorOutput(), 0, 500)]);

            return;
        }
        $order->forceFill(['timelapse_path' => $order->dir().'/timelapse.mp4'])->save();
        // the frames did their job
        $disk->deleteDirectory($order->dir().'/frames');
    }

    /** ffmpeg filter graph: intro, the print with the finished piece held, outro; all at the frame size, 8 fps. */
    private function graph(int $w, int $h, int $frames, string $thanksFile): string
    {
        $fps = self::FPS;
        $font = $this->font();
        $logoW = (int) round($w * 0.5);
        $intro = 7.0;                                    // logo 0-3.5 s, countdown 3.5-6.5 s, a breath of black
        $print = $frames * self::FRAME_SECONDS + self::HOLD_SECONDS;
        $outro = 5.0;
        $count = collect([[3, 3.5], [2, 4.5], [1, 5.5]])->map(fn ($c) => sprintf(
            "drawtext=fontfile='%s':text='%d':fontsize=%d:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2:enable='between(t,%s,%s)'",
            $font, $c[0], (int) round($h * 0.4), $c[1], $c[1] + 1
        ))->implode(',');

        return implode(';', [
            // the logo, once for the intro and once for the outro
            "[1:v]scale={$logoW}:-2,format=rgba,split[logo_a][logo_b]",
            // intro: black, the logo fades in and out, then 3 2 1
            "[logo_a]fade=in:st=0.3:d=1:alpha=1,fade=out:st=2.5:d=0.8:alpha=1[logo_in]",
            "color=c=black:s={$w}x{$h}:r={$fps}:d={$intro}[bg_in]",
            "[bg_in][logo_in]overlay=(W-w)/2:(H-h)/2:shortest=1,{$count},format=yuv420p[intro]",
            // the print at frame size, the last frame held, fade to black at the end
            sprintf('[0:v]scale=%d:%d,fps=%d,tpad=stop_mode=clone:stop_duration=%s,fade=out:st=%s:d=0.8,format=yuv420p[print]', $w, $h, $fps, self::HOLD_SECONDS, $print - 0.8),
            // outro: thank you above the logo, fading in
            "color=c=black:s={$w}x{$h}:r={$fps}:d={$outro}[bg_out]",
            "[logo_b]scale=".(int) round($w * 0.4).":-2[logo_small]",
            sprintf("[bg_out]drawtext=fontfile='%s':textfile='%s':fontsize=%d:fontcolor=white:x=(w-text_w)/2:y=h*0.28[thanks]", $font, $this->path($thanksFile), (int) round($h * 0.07)),
            '[thanks][logo_small]overlay=(W-w)/2:H*0.42:shortest=1,fade=in:st=0:d=0.8,format=yuv420p[outro]',
            '[intro][print][outro]concat=n=3:v=1:a=0[v]',
        ]);
    }

    /** The bold DejaVu that ships with dompdf: the same file on every install, covers Czech and Spanish. */
    private function font(): string
    {
        return $this->path((string) config('farm.font', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf')));
    }

    /** A file name inside the filter graph: forward slashes, the drive colon escaped. */
    private function path(string $file): string
    {
        return str_replace(['\\', ':'], ['/', '\\:'], $file);
    }

    /** Even dimensions (libx264 wants them), capped at 1280 px wide. */
    private function size(string $frame): array
    {
        [$w, $h] = @getimagesize($frame) ?: [1280, 960];
        if ($w > 1280) {
            $h = (int) round($h * 1280 / $w);
            $w = 1280;
        }

        return [$w - $w % 2, $h - $h % 2];
    }
}
