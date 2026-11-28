<?php

namespace App\Domain\YouTube;

use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\FarmVideoMedia;
use Illuminate\Support\Facades\Storage;

/**
 * The Short and the 16:9 of a print from its media list (StudioMedia, docs/AL.md §13.10), every included item in the
 * admin's order:
 *
 *   short  9:16, 1080×1920: the logo with the opening text over the first item, the items, the tool's name and
 *          matplace.com over the last second
 *   wide   16:9, 1920×1080: a title card (the opening text and the facts), the items with the logo in the corner, the
 *          closing card with the address
 *
 * Pictures stand whole on their ground (the app's own drawings on their light colour, the rest on black), clips fill the
 * Short (cut at the sides) and stand whole in the 16:9; the print grows from growth.mp4 (made once from the camera
 * frames, kept after they go) or the frames themselves. A clip may keep its own sound: the music is ducked under it.
 * Planning needs no ffmpeg; TimelapseCutter::render() builds the plan.
 */
class StudioCutter
{
    public const LIGHT = '0xF4F3F0';

    public function __construct(private readonly TimelapseCutter $cutter, private readonly FarmVideos $videos, private readonly StudioMedia $media) {}

    /**
     * growth.mp4: the print growing at the camera's size (at most 1280 wide), paced like the 16:9's growth, made from
     * the frames once and kept for later builds. Null when there are no frames and no such file (or ffmpeg failed).
     */
    public function growthMaster(FarmOrder $order, bool $make = true): ?string
    {
        $disk = Storage::disk(config('farm.disk'));
        $rel = $order->dir().'/growth.mp4';
        if ($disk->exists($rel)) {
            return $disk->path($rel);
        }
        $frames = $this->cutter->frames($order);
        if (! $make || $frames->count() < 4) {
            return null;
        }
        $c = (array) config('youtube.cut.studio.master');
        $fps = (int) $c['fps'];
        [$shown, $per] = $this->cutter->paced($frames, $c['growth'], $fps);
        [$w, $h] = @getimagesize((string) $shown->first()) ?: [1280, 960];
        if ($w > 1280) {
            [$w, $h] = [1280, (int) round($h * 1280 / $w)];
        }
        $list = $disk->path($order->dir()).'/growth_master.txt';
        $seconds = $shown->count() * $per;
        $cut = new VideoCut('growth', $disk->path($rel), [$list => $this->cutter->timed($shown, $per)], [], ['-f', 'concat', '-safe', '0', '-i', $list],
            sprintf('[0:v]scale=%d:%d:flags=lanczos,fps=%d,format=yuv420p,setsar=1[v]', $w - $w % 2, $h - $h % 2, $fps), $seconds, $seconds, $fps, null, 20);

        return $this->cutter->render($cut, 300) ? $disk->path($rel) : null;
    }

    /** The opening text: the admin's, else the Short's title (the 16:9: the model's name on the title card). */
    public function intro(FarmOrder $order, string $kind, ?FarmVideo $video): string
    {
        $own = trim((string) ($video?->meta['intro'] ?? ''));
        if ($own !== '') {
            return $own;
        }

        return $kind === FarmVideo::KIND_WIDE
            ? $this->videos->cardFacts($order, (string) config('youtube.language'))['name']
            : ($video?->title ?: $this->videos->defaultTitle($order));
    }

    /** The video of this kind planned from the included media; null when nothing in the list can be shown. */
    public function plan(FarmOrder $order, string $kind, ?FarmVideo $video = null): ?VideoCut
    {
        $wide = $kind === FarmVideo::KIND_WIDE;
        $c = (array) config('youtube.cut.studio.'.($wide ? 'wide' : 'short'));
        [$w, $h, $fps] = [(int) $c['width'], (int) $c['height'], (int) $c['fps']];
        $disk = Storage::disk(config('farm.disk'));
        $dir = $disk->path($order->dir());
        $prefix = $dir.'/studio_'.($wide ? 'wide' : 'short');
        $inputs = [];
        $graph = [];
        $sequence = [];
        $sounds = [];
        $lists = [];
        $at = 0.0;
        $growth = 0.0;
        $n = 0;
        $finish = fn (float $d, string $label) => sprintf('fps=%d,trim=duration=%s,setpts=PTS-STARTPTS,format=yuv420p,setsar=1[%s]', $fps, VideoCut::number($d), $label);

        // what can be shown, with its length; the growing print's length is settled once the rest is known
        $pieces = [];
        foreach ($this->media->items($order)->where('included', true)->values() as $item) {
            if ($item->kind === FarmVideoMedia::KIND_GROWTH) {
                $master = $this->growthMaster($order);
                if ($master || $this->cutter->frames($order)->count() >= 4) {
                    $pieces[] = ['item' => $item, 'file' => $master, 'duration' => null];
                }

                continue;
            }
            $file = $this->media->file($order, $item, $kind);
            if (! $file) {
                continue;
            }
            if ($item->kind === FarmVideoMedia::KIND_CLIP) {
                $length = isset($item->meta['duration']) ? (float) $item->meta['duration'] : null;
                $duration = $item->seconds ?: (float) $c['clip'];
                $pieces[] = ['item' => $item, 'file' => $file, 'duration' => $length ? min($duration, $length) : $duration];
            } else {
                $pieces[] = ['item' => $item, 'file' => $file, 'duration' => $item->seconds ?: (float) $c['still']];
            }
        }
        $others = array_sum(array_map(fn ($p) => (float) $p['duration'], $pieces));

        foreach ($pieces as $i => $piece) {
            $item = $piece['item'];
            $file = $piece['file'];
            $label = 'm'.$i;
            if ($item->kind === FarmVideoMedia::KIND_GROWTH) {
                $hold = $wide ? (float) ($c['hold'] ?? 0) : 0.0;
                if ($file) {
                    $length = $this->media->probe($file)['duration'] ?? null;
                    $length = $length ?: (float) ((array) config('youtube.cut.studio.master'))['growth'][0];
                    $base = max((float) $c['growth'][0], min((float) $c['growth'][1], $length));
                } else {
                    [$shown, $per] = $this->cutter->paced($this->cutter->frames($order), $c['growth'], $fps);
                    $base = $shown->count() * $per;
                }
                // a short Short reaches its least length by a longer growth (never past the longest growth)
                $grow = $item->seconds ?: ($wide ? $base : min((float) $c['growth'][1], max($base, (float) ($c['min_seconds'] ?? 0) - $others)));
                if ($file) {
                    array_push($inputs, '-i', $file);
                    $speed = sprintf('setpts=PTS*%s,', VideoCut::number($grow / $length));
                } else {
                    $lists[$prefix.'_growth.txt'] = $this->cutter->timed($shown, $grow / max(1, $shown->count()));
                    array_push($inputs, '-f', 'concat', '-safe', '0', '-i', $prefix.'_growth.txt');
                    $speed = '';
                }
                $graph[] = sprintf('[%d:v]%s%s,fps=%d%s,', $n, $speed, $this->cutter->fit($w, $h), $fps, $hold > 0 ? ',tpad=stop_mode=clone:stop_duration='.VideoCut::number($hold) : '').$finish($grow + $hold, $label);
                $duration = $grow + $hold;
                $growth += $grow;
            } elseif ($item->kind === FarmVideoMedia::KIND_CLIP) {
                $duration = (float) $piece['duration'];
                array_push($inputs, '-t', VideoCut::number($duration), '-i', $file);
                // the Short is filled (a phone clip on its side loses its edges), the 16:9 shows the clip whole
                $graph[] = sprintf($wide
                    ? '[%d:v]scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black,setsar=1,'
                    : '[%d:v]scale=%d:%d:force_original_aspect_ratio=increase:flags=lanczos,crop=%d:%d,setsar=1,', $n, $w, $h, $w, $h).$finish($duration, $label);
                if ($item->sound && ! empty($item->meta['has_audio'])) {
                    $sounds[] = ['input' => $n, 'start' => $at, 'duration' => $duration];
                }
            } else {
                $duration = (float) $piece['duration'];
                array_push($inputs, '-loop', '1', '-t', VideoCut::number($duration + 1), '-i', $file);
                $ground = ModelPicture::isOurs($file) ? self::LIGHT : 'black';
                $graph[] = sprintf('[%d:v]scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:%s,setsar=1,fade=in:st=0:d=0.2,',
                    $n, $w, $h, $w, $h, $ground).$finish($duration, $label);
            }
            $sequence[] = '['.$label.']';
            $at += $duration;
            $n++;
        }
        if (! $sequence) {
            return null;
        }
        $graph[] = implode('', $sequence).'concat=n='.count($sequence).':v=1:a=0[body]';
        $logo = $n;
        $texts = [];
        $tool = $this->videos->toolName($order, $wide ? (string) config('youtube.language') : null);

        if ($wide) {
            $title = (float) $c['title'];
            $end = (float) $c['end'];
            $seconds = $title + $at + $end;
            $facts = $this->videos->cardFacts($order, (string) config('youtube.language'));
            $texts[$prefix.'_name.txt'] = $this->intro($order, $kind, $video);
            $texts[$prefix.'_facts.txt'] = $facts['line'];
            $texts[$prefix.'_url.txt'] = $this->videos->address($order);
            if ($tool !== null) {
                $texts[$prefix.'_tool.txt'] = $tool;
            }
            array_push($inputs, '-loop', '1', '-t', VideoCut::number($seconds + 1), '-i', public_path('img/logo.png'));
            $graph[] = "[{$logo}:v]format=rgba,split[logo_c][logo_e]";
            $graph[] = sprintf('color=c=black:s=%dx%d:r=%d:d=%s,%s,%s,fade=in:st=0:d=0.3,fade=out:st=%s:d=0.3,format=yuv420p,setsar=1[title]', $w, $h, $fps, VideoCut::number($title),
                $this->cutter->text($prefix.'_name.txt', (int) round($h * 0.075), 'h*0.38'), $this->cutter->text($prefix.'_facts.txt', (int) round($h * 0.04), 'h*0.53', '0xcbd5e1'), VideoCut::number($title - 0.3));
            $graph[] = sprintf('[logo_c]scale=%d:-2[logo_corner]', (int) round($w * 0.1));
            $graph[] = sprintf('[body][logo_corner]overlay=%d:%d:shortest=1,format=yuv420p[shown]', (int) round($w * 0.025), (int) round($h * 0.035));
            $graph[] = $this->cutter->endCard('logo_e', $prefix.'_url.txt', $w, $h, $fps, $end, 'end', $tool !== null ? $prefix.'_tool.txt' : null);
            $graph[] = '[title][shown][end]concat=n=3:v=1:a=0[v]';
            $offset = $title;
        } else {
            $intro = min((float) $c['intro'], $at);
            $end = min((float) $c['end'], $at);
            $seconds = $at;
            $size = (int) round($h * 0.045);
            $texts[$prefix.'_intro.txt'] = implode("\n", $this->cutter->wrap($this->intro($order, $kind, $video), $size, $w));
            $texts[$prefix.'_url.txt'] = 'matplace.com';
            if ($tool !== null) {
                $texts[$prefix.'_tool.txt'] = implode("\n", $this->cutter->wrap($tool, (int) round($h * 0.036), $w));
            }
            // the logo above the opening text (Roman, 28 Nov 2026): the Short opens with who made it and what it is
            [$lw, $lh] = @getimagesize(public_path('img/logo.png')) ?: [1000, 300];
            $logoWidth = (int) round($w * 0.42);
            $logoHeight = (int) round($logoWidth * $lh / max(1, $lw));
            $top = (int) round($h * 0.07);
            array_push($inputs, '-loop', '1', '-t', VideoCut::number($seconds + 1), '-i', public_path('img/logo.png'));
            $opening = "lt(t,{$this->n($intro)})";
            $closing = 'gte(t,'.$this->n($seconds - $end).')';
            $line = fn (string $file, int $px, string $y, string $enable, string $colour = 'white') => $this->cutter->caption($file, $px, 'x=(w-text_w)/2:y='.$y, $colour).":enable='".$enable."'";
            $graph[] = sprintf('[%d:v]format=rgba,scale=%d:-2,fade=out:st=%s:d=0.25:alpha=1[logo_top]', $logo, $logoWidth, $this->n(max(0, $intro - 0.25)));
            $graph[] = sprintf("[body][logo_top]overlay=(W-w)/2:%d:shortest=1:enable='%s',", $top, $opening)
                .$line($prefix.'_intro.txt', $size, (string) ($top + $logoHeight + (int) round($h * 0.02)), $opening).','
                .($tool !== null ? $line($prefix.'_tool.txt', (int) round($h * 0.036), 'h*0.78-text_h-h*0.02', $closing, TimelapseCutter::TEAL).',' : '')
                .$line($prefix.'_url.txt', (int) round($h * 0.04), 'h*0.78', $closing).',format=yuv420p,settb=AVTB[v]';
            $offset = 0.0;
        }

        $cut = new VideoCut($wide ? 'wide' : 'short', $dir.'/'.($wide ? 'wide' : 'short').'.mp4', $lists, $texts, $inputs, implode(';', $graph), $seconds, $growth, $fps, null, $wide ? 21 : 23);

        return $this->withSound($cut, $this->cutter->music($order), array_map(fn ($s) => ['start' => $s['start'] + $offset] + $s, $sounds));
    }

    /** The track under the whole video; under a clip that keeps its sound the music goes quiet and the clip is heard. */
    private function withSound(VideoCut $cut, ?string $music, array $sounds): VideoCut
    {
        if (! $music) {
            return $cut;   // no library: silent as every farm video (TimelapseCutter::silent), the clips' sound too
        }
        $input = count(array_keys($cut->inputs, '-i', true));
        $fade = min((float) config('youtube.music_fade_out', 1.5), $cut->seconds / 2);
        $audio = sprintf('[%d:a]atrim=duration=%s,asetpts=PTS-STARTPTS,volume=%s,afade=t=in:st=0:d=0.5,afade=t=out:st=%s:d=%s',
            $input, $this->n($cut->seconds), $this->n((float) config('youtube.music_volume', 0.8)), $this->n($cut->seconds - $fade), $this->n($fade));
        if ($sounds) {
            $quiet = implode('+', array_map(fn ($s) => 'between(t,'.$this->n($s['start']).','.$this->n($s['start'] + $s['duration']).')', $sounds));
            $audio .= ",volume='if({$quiet},0.18,1)':eval=frame[music]";
            $mix = '[music]';
            foreach ($sounds as $i => $s) {
                $delay = (int) round($s['start'] * 1000);
                $audio .= sprintf(';[%d:a]atrim=duration=%s,asetpts=PTS-STARTPTS,adelay=%d|%d[sound%d]', $s['input'], $this->n($s['duration']), $delay, $delay, $i);
                $mix .= '[sound'.$i.']';
            }
            $audio .= ';'.$mix.'amix=inputs='.(count($sounds) + 1).':duration=first:dropout_transition=0[a]';
        } else {
            $audio .= '[a]';
        }

        return new VideoCut($cut->kind, $cut->out, $cut->lists, $cut->texts, [...$cut->inputs, '-stream_loop', '-1', '-i', $music],
            $cut->graph.';'.$audio, $cut->seconds, $cut->growth, $cut->fps, $music, $cut->crf, $cut->chapters);
    }

    private function n(float $seconds): string
    {
        return VideoCut::number($seconds);
    }
}
