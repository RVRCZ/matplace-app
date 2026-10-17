<?php

namespace App\Domain\YouTube;

use App\Domain\Farm\TestPhotos;
use App\Domain\Farm\TimelapseFrames;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Plans and renders the farm videos from the frames the agent sent during a print (settings in config youtube.cut):
 *
 *   customer  the order page: black, the logo, 3 2 1, the print grows, the finished piece, the photo, thank you
 *   short     square for Shorts and Reels: the finished piece first, the print, the piece held, the photo (under 20 s)
 *   wide      classic 16:9 at 1920×1080: title, the print, the piece held, the photo, the tool's address (under 45 s)
 *   weekly    "Tento týden na farmě": every print of a week with its name, 2 to 4 minutes, with chapters
 *
 * Every video gets the order's track (one per order for all versions, FarmVideos::pickMusic) faded out at the end.
 * Planning needs no ffmpeg (VideoCut), rendering does (`FFMPEG_BIN`, default `ffmpeg`).
 */
class TimelapseCutter
{
    /** @var array<int, Collection<int, string>> frames per order id: the stray check takes seconds, every cut asks */
    private array $frames = [];

    private bool $silenceLogged = false;

    public function __construct(private readonly FarmVideos $videos, private readonly TestPhotos $photos) {}

    /**
     * Pictures taken after every layer with the head parked (TimelapseGcode) when there are enough of them, else the
     * one-a-minute pictures, oldest first, absolute paths. Pictures that caught the head printing are left out.
     *
     * @return Collection<int, string>
     */
    public function frames(FarmOrder $order): Collection
    {
        return $this->frames[$order->id] ??= $this->collectFrames($order);
    }

    private function collectFrames(FarmOrder $order): Collection
    {
        $disk = Storage::disk(config('farm.disk'));
        $jpgs = fn (string $dir) => collect($disk->files($order->dir().'/'.$dir))->filter(fn ($f) => str_ends_with($f, '.jpg'))
            ->sortBy(fn ($f) => (float) basename($f, '.jpg'))->map(fn ($f) => $disk->path($f))->values();
        $layer = $jpgs('frames_layer');
        if ($layer->count() < 10) {
            return $jpgs('frames');
        }
        $strays = TimelapseFrames::strays($layer->all());
        if ($strays) {
            Log::info('Farm time-lapse: stray layer pictures left out', ['order' => $order->id, 'count' => count($strays), 'of' => $layer->count()]);
            $layer = $layer->forget($strays)->values();
        }

        return $layer;
    }

    /**
     * An even pace for the growing print: one frame = youtube.cut.frame_seconds, stretched or squeezed into the
     * range, never slower than youtube.cut.slowest_frame. When a frame would be shorter than a video frame, every n-th
     * one is taken instead (first and last kept), so ffmpeg drops none at random and the length holds.
     *
     * @param  array{0: float|int, 1: float|int}  $growth  seconds, shortest and longest
     * @return array{0: Collection<int, string>, 1: float} the frames shown and seconds per frame
     */
    public function paced(Collection $frames, array $growth, int $fps): array
    {
        $n = max(1, $frames->count());
        $target = max((float) $growth[0], min((float) $growth[1], $n * (float) config('youtube.cut.frame_seconds', 0.1)));
        $seconds = min((float) config('youtube.cut.slowest_frame', 0.2), $target / $n);
        $min = 1 / $fps;
        if ($seconds >= $min || $frames->count() < 3) {
            return [$frames->values(), max($seconds, $min)];
        }
        $keep = max(2, (int) round($frames->count() * $seconds / $min));
        $last = $frames->count() - 1;

        return [collect(range(0, $keep - 1))->map(fn ($i) => $frames[(int) round($i * $last / ($keep - 1))])->values(), $min];
    }

    /**
     * The three videos of one order, planned; null when the print is too short to tell a story.
     *
     * @return array{customer: VideoCut, short: VideoCut, wide: VideoCut}|null
     */
    public function cuts(FarmOrder $order): ?array
    {
        if ($this->frames($order)->count() < 4) {
            return null;
        }

        return ['customer' => $this->customer($order), 'short' => $this->short($order), 'wide' => $this->wide($order)];
    }

    /** The customer's video on the order page, at the camera's size (at most 1280 wide), in the customer's language. */
    public function customer(FarmOrder $order): VideoCut
    {
        $c = (array) config('youtube.cut.customer');
        $fps = (int) $c['fps'];
        [$frames, $per] = $this->paced($this->frames($order), $c['growth'], $fps);
        [$w, $h] = $this->size($frames->first());
        $dir = $this->dir($order);
        $font = $this->font();
        $photo = $this->finishPhoto($order);
        $music = $this->music($order);
        $growth = $frames->count() * $per;
        $hold = (float) $c['hold'];
        $photoSeconds = (float) $c['photo'];
        $intro = 7.0;                                    // logo 0-3.5 s, countdown 3.5-6.5 s, a breath of black
        $outro = 5.0;
        $seconds = $intro + $growth + $hold + ($photo ? $photoSeconds : 0) + $outro;
        $thanks = $dir.'/thanks.txt';
        $count = collect([[3, 3.5], [2, 4.5], [1, 5.5]])->map(fn ($n) => sprintf(
            "drawtext=fontfile='%s':text='%d':fontsize=%d:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2:enable='between(t,%s,%s)'",
            $font, $n[0], (int) round($h * 0.4), $n[1], $n[1] + 1
        ))->implode(',');
        $inputs = ['-f', 'concat', '-safe', '0', '-i', $dir.'/frames.txt', '-loop', '1', '-t', '8', '-i', public_path('img/logo.png')];
        if ($photo) {
            array_push($inputs, '-loop', '1', '-t', VideoCut::number($photoSeconds + 1), '-i', $photo);
        }
        $graph = [
            // the logo, once for the intro and once for the outro
            sprintf('[1:v]scale=%d:-2,format=rgba,split[logo_a][logo_b]', (int) round($w * 0.5)),
            '[logo_a]fade=in:st=0.3:d=1:alpha=1,fade=out:st=2.5:d=0.8:alpha=1[logo_in]',
            "color=c=black:s={$w}x{$h}:r={$fps}:d={$intro}[bg_in]",
            "[bg_in][logo_in]overlay=(W-w)/2:(H-h)/2:shortest=1,{$count},format=yuv420p,setsar=1[intro]",
            // the print at frame size, the last frame held, fade to black at the end
            sprintf('[0:v]scale=%d:%d,fps=%d,tpad=stop_mode=clone:stop_duration=%s,fade=out:st=%s:d=0.8,format=yuv420p,setsar=1[print]',
                $w, $h, $fps, VideoCut::number($hold), VideoCut::number($growth + $hold - 0.8)),
            // thank you above the logo, fading in
            "color=c=black:s={$w}x{$h}:r={$fps}:d={$outro}[bg_out]",
            '[logo_b]scale='.(int) round($w * 0.4).':-2[logo_small]',
            sprintf("[bg_out]drawtext=fontfile='%s':textfile='%s':expansion=none:fontsize=%d:fontcolor=white:x=(w-text_w)/2:y=h*0.28[thanks]", $font, $this->path($thanks), (int) round($h * 0.07)),
            '[thanks][logo_small]overlay=(W-w)/2:H*0.42:shortest=1,fade=in:st=0:d=0.8,format=yuv420p,setsar=1[outro]',
        ];
        if ($photo) {
            $graph[] = $this->photoFilter(2, $w, $h, $fps, $photoSeconds, 0.5);
            $graph[] = '[intro][print][photo][outro]concat=n=4:v=1:a=0[v]';
        } else {
            $graph[] = '[intro][print][outro]concat=n=3:v=1:a=0[v]';
        }

        return $this->withMusic(new VideoCut('customer', $dir.'/timelapse.mp4', [$dir.'/frames.txt' => $this->timed($frames, $per)],
            [$thanks => __('farm.timelapse.thanks', [], $order->user?->locale ?? config('app.locale'))], $inputs, implode(';', $graph), $seconds, $growth, $fps, null, 26), $music);
    }

    /**
     * The square Short: the finished piece first (the first second decides), the layers growing, the piece held so
     * the loop closes, the cleaned piece crossfading in, a small logo in the corner.
     */
    public function short(FarmOrder $order): VideoCut
    {
        $c = (array) config('youtube.cut.short');
        $fps = (int) $c['fps'];
        [$frames, $per] = $this->paced($this->frames($order), $c['growth'], $fps);
        $dir = $this->dir($order);
        $last = (string) $frames->last();
        $start = (float) $c['start'];
        $hold = (float) $c['hold'];
        $photoSeconds = (float) $c['photo'];
        $timed = [[$last, $start], ...$this->timed($frames, $per), [$last, $hold]];
        [$w, $h] = @getimagesize((string) $frames->first()) ?: [1280, 720];
        [$cx, $cy, $side] = $this->crop($order, (int) $w, (int) $h);
        $size = min(1080, $side - $side % 2);
        $photo = $this->finishPhoto($order);
        $growth = $frames->count() * $per;
        $printSeconds = $start + $growth + $hold;
        $inputs = ['-f', 'concat', '-safe', '0', '-i', $dir.'/short.txt', '-i', public_path('img/logo.png')];
        $graph = sprintf('[0:v]crop=%d:%d:%d:%d,scale=%d:%d,fps=%d,format=yuv420p,setsar=1,settb=AVTB[print];[1:v]scale=%d:-2[logo];',
            $side, $side, $cx, $cy, $size, $size, $fps, (int) round($size * 0.22));
        if ($photo) {
            array_push($inputs, '-loop', '1', '-t', VideoCut::number($photoSeconds + 1), '-i', $photo);
            // the cleaned piece crossfades in after the finished print and stays to the end
            $graph .= sprintf('[2:v]scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,setsar=1,fps=%d,format=yuv420p,settb=AVTB,trim=duration=%s[photo];[print][photo]xfade=transition=fade:duration=0.5:offset=%s[body];',
                $size, $size, $size, $size, $fps, VideoCut::number($photoSeconds), VideoCut::number($printSeconds - 0.5));
        } else {
            $graph .= '[print]null[body];';
        }
        $graph .= sprintf('[body][logo]overlay=%d:%d,format=yuv420p[v]', (int) round($size * 0.03), (int) round($size * 0.03));

        return $this->withMusic(new VideoCut('short', $dir.'/short.mp4', [$dir.'/short.txt' => $timed], [], $inputs, $graph,
            $printSeconds + ($photo ? $photoSeconds - 0.5 : 0), $growth, $fps, null), $this->music($order));
    }

    /**
     * The classic 16:9 for YouTube: the model's name on a title card, the print, the finished piece held, the photo
     * from the box, a card with the address of the tool that made it (a customer's own file: matplace.com).
     */
    public function wide(FarmOrder $order): VideoCut
    {
        $c = (array) config('youtube.cut.wide');
        [$fps, $w, $h] = [(int) $c['fps'], (int) $c['width'], (int) $c['height']];
        [$frames, $per] = $this->paced($this->frames($order), $c['growth'], $fps);
        $dir = $this->dir($order);
        $locale = (string) config('youtube.language');
        $photo = $this->finishPhoto($order);
        [$title, $hold, $photoSeconds, $end] = [(float) $c['title'], (float) $c['hold'], (float) $c['photo'], (float) $c['end']];
        $growth = $frames->count() * $per;
        $seconds = $title + $growth + $hold + ($photo ? $photoSeconds : 0) + $end;
        $facts = $this->videos->cardFacts($order, $locale);
        $texts = [$dir.'/wide_name.txt' => $facts['name'], $dir.'/wide_facts.txt' => $facts['line'], $dir.'/wide_url.txt' => $this->videos->address($order)];
        $inputs = ['-f', 'concat', '-safe', '0', '-i', $dir.'/wide.txt', '-loop', '1', '-t', VideoCut::number($seconds + 1), '-i', public_path('img/logo.png')];
        $graph = [
            '[1:v]format=rgba,split[logo_c][logo_e]',
            sprintf('color=c=black:s=%dx%d:r=%d:d=%s,%s,%s,fade=in:st=0:d=0.3,fade=out:st=%s:d=0.3,format=yuv420p,setsar=1[title]', $w, $h, $fps, VideoCut::number($title),
                $this->text($dir.'/wide_name.txt', (int) round($h * 0.075), 'h*0.38'), $this->text($dir.'/wide_facts.txt', (int) round($h * 0.04), 'h*0.53', '0xcbd5e1'), VideoCut::number($title - 0.3)),
            sprintf('[0:v]%s,fps=%d,tpad=stop_mode=clone:stop_duration=%s,format=yuv420p,setsar=1[grown]', $this->fit($w, $h), $fps, VideoCut::number($hold)),
            sprintf('[logo_c]scale=%d:-2[logo_corner]', (int) round($w * 0.1)),
            sprintf('[grown][logo_corner]overlay=%d:%d:shortest=1,format=yuv420p[print]', (int) round($w * 0.025), (int) round($h * 0.035)),
            $this->endCard('logo_e', $dir.'/wide_url.txt', $w, $h, $fps, $end, 'end'),
        ];
        if ($photo) {
            array_push($inputs, '-loop', '1', '-t', VideoCut::number($photoSeconds + 1), '-i', $photo);
            $graph[] = $this->photoFilter(2, $w, $h, $fps, $photoSeconds, 0.4);
            $graph[] = '[title][print][photo][end]concat=n=4:v=1:a=0[v]';
        } else {
            $graph[] = '[title][print][end]concat=n=3:v=1:a=0[v]';
        }

        return $this->withMusic(new VideoCut('wide', $dir.'/wide.mp4', [$dir.'/wide.txt' => $this->timed($frames, $per)], $texts, $inputs, implode(';', $graph),
            $seconds, $growth, $fps, null, 21), $this->music($order));
    }

    /**
     * The week's cut: an intro with the dates, every print growing with its name in the corner (one chapter each,
     * at least youtube.cut.weekly.piece_min long, so YouTube shows the chapters), the closing card. More prints than
     * fit into youtube.cut.weekly.max_seconds: the likely hits stay (FarmVideos::score), in the order they were printed.
     *
     * @param  Collection<int, FarmOrder>  $orders  the week's prints, oldest first
     */
    public function weekly(Collection $orders, Carbon $monday, string $out): VideoCut
    {
        $c = (array) config('youtube.cut.weekly');
        [$fps, $w, $h] = [(int) $c['fps'], (int) $c['width'], (int) $c['height']];
        [$intro, $end, $pieceMin] = [(float) $c['intro'], (float) $c['end'], (float) $c['piece_min']];
        $locale = (string) config('youtube.language');
        $orders = $this->fitWeek($orders->filter(fn (FarmOrder $o) => $this->frames($o)->count() >= 4)->values(),
            (int) floor(((float) $c['max_seconds'] - $intro - $end) / $pieceMin));
        $work = dirname($out).'/'.pathinfo($out, PATHINFO_FILENAME);
        $texts = [$work.'_intro.txt' => __('youtube.weekly.card', [], $locale), $work.'_dates.txt' => $this->weekLabel($monday), $work.'_url.txt' => $this->videos->address(null)];
        $inputs = [];
        $lists = [];
        $graph = [];
        $chapters = [];
        $at = $intro;
        $growth = 0.0;
        foreach ($orders->values() as $i => $order) {
            [$frames, $per] = $this->paced($this->frames($order), $c['growth'], $fps);
            $grow = $frames->count() * $per;
            $hold = max(1.0, $pieceMin - $grow);
            $list = $work.'_'.$i.'.txt';
            $lists[$list] = $this->timed($frames, $per);
            $texts[$work.'_name_'.$i.'.txt'] = $name = $this->videos->cardFacts($order, $locale)['name'];
            array_push($inputs, '-f', 'concat', '-safe', '0', '-i', $list);
            $graph[] = sprintf("[%d:v]%s,fps=%d,tpad=stop_mode=clone:stop_duration=%s,drawtext=fontfile='%s':textfile='%s':expansion=none:fontsize=%d:fontcolor=white:box=1:boxcolor=black@0.55:boxborderw=%d:x=w*0.04:y=h*0.86,fade=in:st=0:d=0.25,format=yuv420p,setsar=1[p%d]",
                $i, $this->fit($w, $h), $fps, VideoCut::number($hold), $this->font(), $this->path($work.'_name_'.$i.'.txt'), (int) round($h * 0.045), (int) round($h * 0.015), $i);
            // YouTube wants the first chapter at 0:00 and every one 10 s or longer: the first print takes the intro in
            $chapters[] = [$chapters ? $at : 0.0, $name, $this->videos->link($order, FarmVideo::KIND_WEEKLY), $order->id];
            $at += $grow + $hold;
            $growth += $grow;
        }
        $logo = count($orders);
        $seconds = $at + $end;
        array_push($inputs, '-loop', '1', '-t', VideoCut::number($seconds + 1), '-i', public_path('img/logo.png'));
        array_unshift($graph,
            "[{$logo}:v]format=rgba,split[logo_i][logo_e]",
            sprintf('[logo_i]scale=%d:-2[logo_intro]', (int) round($w * 0.28)),
            sprintf('color=c=black:s=%dx%d:r=%d:d=%s[bg_i]', $w, $h, $fps, VideoCut::number($intro)),
            sprintf('[bg_i][logo_intro]overlay=(W-w)/2:H*0.16:shortest=1,%s,%s,fade=in:st=0:d=0.4,fade=out:st=%s:d=0.3,format=yuv420p,setsar=1[intro]',
                $this->text($work.'_intro.txt', (int) round($h * 0.08), 'h*0.56'), $this->text($work.'_dates.txt', (int) round($h * 0.045), 'h*0.70', '0xcbd5e1'), VideoCut::number($intro - 0.3)),
        );
        $graph[] = $this->endCard('logo_e', $work.'_url.txt', $w, $h, $fps, $end, 'end');
        $graph[] = '[intro]'.implode('', array_map(fn ($i) => "[p{$i}]", array_keys($orders->all()))).'[end]concat=n='.(count($orders) + 2).':v=1:a=0[v]';
        $tracks = $this->videos->tracks();
        $music = $tracks ? $this->musicFile($tracks[(int) $monday->isoWeek() % count($tracks)]) : $this->silent();

        return $this->withMusic(new VideoCut('weekly', $out, $lists, $texts, $inputs, implode(';', $graph), $seconds, $growth, $fps, null, 21, $chapters), $music);
    }

    /** "13. 10.–19. 10. 2026" */
    public function weekLabel(Carbon $monday): string
    {
        return $monday->format('j. n.').'–'.$monday->copy()->addDays(6)->format('j. n. Y');
    }

    /**
     * Renders a planned video: writes its lists and texts, runs ffmpeg, cleans up. False (and one line in the log) when
     * ffmpeg failed or wrote nothing.
     */
    public function render(VideoCut $cut, int $timeout = 300): bool
    {
        foreach ($cut->lists as $file => $frames) {
            file_put_contents($file, VideoCut::listText($frames));
        }
        foreach ($cut->texts as $file => $text) {
            file_put_contents($file, $text);
        }
        @unlink($cut->out);
        $r = Process::timeout($timeout)->run($cut->command((string) config('farm.ffmpeg', 'ffmpeg')));
        array_map(fn ($f) => @unlink($f), [...array_keys($cut->lists), ...array_keys($cut->texts)]);
        if (! $r->successful() || ! is_file($cut->out)) {
            Log::warning('Farm video failed', ['kind' => $cut->kind, 'out' => $cut->out, 'error' => mb_substr($r->errorOutput(), 0, 500)]);

            return false;
        }

        return true;
    }

    /** The order's track: the one its videos already name, else the next in turn (FarmVideos::pickMusic). */
    public function music(FarmOrder $order): ?string
    {
        $named = FarmVideo::where('farm_order_id', $order->id)->whereNotNull('music')->value('music');
        $track = $named && $this->musicFile($named) ? $named : $this->videos->pickMusic($order);

        return $track ? $this->musicFile($track) : $this->silent();
    }

    /** The track's file, null when it is not in the library. */
    private function musicFile(string $track): ?string
    {
        $file = rtrim((string) config('youtube.music_dir'), '/\\').'/'.basename($track);

        return is_file($file) ? $file : null;
    }

    /** No library: the videos are built silent, one line in the log says why. */
    private function silent(): null
    {
        if (! $this->silenceLogged) {
            Log::info('Farm videos are built without music: no MP3 in youtube.music_dir', ['dir' => config('youtube.music_dir')]);
            $this->silenceLogged = true;
        }

        return null;
    }

    /** The track under the whole video at youtube.music_volume, cut to its length (looped when shorter), faded out at the end. */
    private function withMusic(VideoCut $cut, ?string $music): VideoCut
    {
        if (! $music) {
            return $cut;
        }
        $input = count(array_keys($cut->inputs, '-i', true));
        $fade = min((float) config('youtube.music_fade_out', 1.5), $cut->seconds / 2);
        $audio = sprintf('[%d:a]atrim=duration=%s,asetpts=PTS-STARTPTS,volume=%s,afade=t=in:st=0:d=0.5,afade=t=out:st=%s:d=%s[a]',
            $input, VideoCut::number($cut->seconds), VideoCut::number((float) config('youtube.music_volume', 0.8)), VideoCut::number($cut->seconds - $fade), VideoCut::number($fade));

        return new VideoCut($cut->kind, $cut->out, $cut->lists, $cut->texts, [...$cut->inputs, '-stream_loop', '-1', '-i', $music],
            $cut->graph.';'.$audio, $cut->seconds, $cut->growth, $cut->fps, $music, $cut->crf, $cut->chapters);
    }

    /**
     * At most $max prints: the likely hits (score) when there are more, shown in the order they were printed.
     *
     * @param  Collection<int, FarmOrder>  $orders
     * @return Collection<int, FarmOrder>
     */
    private function fitWeek(Collection $orders, int $max): Collection
    {
        if ($orders->count() <= $max) {
            return $orders;
        }
        $keep = $orders->sortByDesc(fn (FarmOrder $o) => FarmVideo::where('farm_order_id', $o->id)->value('score') ?? $this->videos->score($o) ?? 0)->take($max)->keys();

        return $orders->only($keep->all())->values();
    }

    /** @return list<array{0: string, 1: float}> */
    private function timed(Collection $frames, float $seconds): array
    {
        return $frames->map(fn ($f) => [(string) $f, $seconds])->values()->all();
    }

    /** A camera picture into the frame: whole (black bars where the shapes differ), enlarged smoothly and sharpened a little. */
    private function fit(int $w, int $h): string
    {
        return sprintf('scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2,unsharp=5:5:0.5:5:5:0', $w, $h, $w, $h);
    }

    /** One line of white (or $color) text centred at $y, read from a file. */
    private function text(string $file, int $size, string $y, string $color = 'white'): string
    {
        return sprintf("drawtext=fontfile='%s':textfile='%s':expansion=none:fontsize=%d:fontcolor=%s:x=(w-text_w)/2:y=%s", $this->font(), $this->path($file), $size, $color, $y);
    }

    /** The closing card: the logo and an address under it, fading in. */
    private function endCard(string $logo, string $urlFile, int $w, int $h, int $fps, float $seconds, string $label): string
    {
        return sprintf('[%s]scale=%d:-2[%s_logo];color=c=black:s=%dx%d:r=%d:d=%s[%s_bg];[%s_bg][%s_logo]overlay=(W-w)/2:H*0.26:shortest=1,%s,fade=in:st=0:d=0.4,format=yuv420p,setsar=1[%s]',
            $logo, (int) round($w * 0.34), $label, $w, $h, $fps, VideoCut::number($seconds), $label, $label, $label, $this->text($urlFile, (int) round($h * 0.06), 'h*0.66'), $label);
    }

    /** The cleaned piece from the photo box, whole, on black, fading in and out. */
    private function photoFilter(int $input, int $w, int $h, int $fps, float $seconds, float $fade): string
    {
        return sprintf('[%d:v]scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=%d,trim=duration=%s,fade=in:st=0:d=%s,fade=out:st=%s:d=%s,format=yuv420p[photo]',
            $input, $w, $h, $w, $h, $fps, VideoCut::number($seconds), VideoCut::number($fade), VideoCut::number($seconds - $fade), VideoCut::number($fade));
    }

    /** The square out of the camera picture: the printer's setting (admin), else the centre. Always inside the picture. */
    private function crop(FarmOrder $order, int $w, int $h): array
    {
        $t = $order->printer?->timelapseSettings() ?? [];
        $side = min($w, $h, (int) ($t['crop_size'] ?? 0) ?: min($w, $h));
        $x = $t['crop_x'] ?? intdiv($w - $side, 2);
        $y = $t['crop_y'] ?? intdiv($h - $side, 2);

        return [max(0, min($w - $side, (int) $x)), max(0, min($h - $side, (int) $y)), $side - $side % 2];
    }

    /** The cleaned piece from the photo box (TestPhotos::finishIndex says which one). */
    private function finishPhoto(FarmOrder $order): ?string
    {
        $index = $this->photos->finishIndex($order);
        if ($index === null) {
            return null;
        }
        $path = $this->photos->path($this->photos->all($order)[$index]);

        return is_file($path) ? $path : null;
    }

    private function dir(FarmOrder $order): string
    {
        return Storage::disk(config('farm.disk'))->path($order->dir());
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
    private function size(?string $frame): array
    {
        [$w, $h] = ($frame ? @getimagesize($frame) : null) ?: [1280, 960];
        if ($w > 1280) {
            $h = (int) round($h * 1280 / $w);
            $w = 1280;
        }

        return [$w - $w % 2, $h - $h % 2];
    }
}
