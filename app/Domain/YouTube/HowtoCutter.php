<?php

namespace App\Domain\YouTube;

use App\Models\FarmVideo;
use Illuminate\Support\Facades\Process;

/**
 * A how-to video cut from data (scripts/video/*.json, farm:howto-video): segments of a screen recording, the order's
 * time-lapse and a phone shot, each trimmed, sped up and captioned in cs and en; a title card, a closing card and the
 * farm's music. One spec gives the 16:9 video (1920×1080) and the vertical Short (1080×1920), the subtitles and the
 * YouTube description, so the next how-to video is only a new JSON.
 *
 * Spec, in short (the full example is scripts/video/howto.json):
 *   sources  name => {path, crop?: [x, y, w, h]}   crop = the browser's page without its tabs and address bar
 *   blur     [{source, from, to, box: [x, y, w, h]}] in source pixels and seconds (a phone number, a token…)
 *   wide     [segment]   short [segment]
 *   segment  {source, from, to (negative = from the end), speed? | fit? (seconds), caption?: {cs, en}, caption_at?:
 *            top|bottom, caption_seconds?, short_crop?: [centre x, centre y, width], optional?, missing?: {seconds, text}}
 *            | {card: intro|end, seconds}
 */
class HowtoCutter
{
    public const FPS = 30;

    public function __construct(private readonly TimelapseCutter $cutter, private readonly FarmVideos $videos) {}

    /**
     * The video of one variant, planned: the inputs, the filter graph, its length and where each caption is shown.
     *
     * @param  'wide'|'short'  $variant
     * @return array{cut: VideoCut, captions: list<array{0: float, 1: float, 2: array{cs: string, en: string}}>, skipped: list<string>}
     */
    public function plan(array $spec, string $variant, string $out): array
    {
        [$w, $h] = $variant === 'short' ? [1080, 1920] : [1920, 1080];
        $work = dirname($out).'/'.pathinfo($out, PATHINFO_FILENAME);
        $inputs = [];
        $graph = [];
        $labels = [];
        $texts = [];
        $captions = [];
        $skipped = [];
        $at = 0.0;
        $logo = null;
        if (collect($spec[$variant] ?? [])->contains(fn ($s) => ($s['card'] ?? null) === 'end')) {
            array_push($inputs, '-loop', '1', '-t', '30', '-i', public_path('img/logo.png'));   // input 0: the closing card's logo
            $logo = 0;
        }
        foreach (array_values((array) ($spec[$variant] ?? [])) as $i => $seg) {
            $label = "s{$i}";
            if (isset($seg['card'])) {
                $seconds = (float) ($seg['seconds'] ?? 2);
                $graph[] = $this->card($seg, $spec, $w, $h, $seconds, $label, $work.'_'.$i, $texts, $logo);
                $labels[] = "[{$label}]";
                $at += $seconds;

                continue;
            }
            $source = (array) ($spec['sources'][$seg['source'] ?? ''] ?? []);
            $path = (string) ($source['path'] ?? '');
            if ($path === '' || ! is_file($path)) {
                if (isset($seg['missing'])) {
                    // the time-lapse or the phone shot is not there yet: a card holds its place
                    $seconds = (float) ($seg['missing']['seconds'] ?? 3);
                    $graph[] = $this->card(['card' => 'text', 'text' => $seg['missing']['text'] ?? []], $spec, $w, $h, $seconds, $label, $work.'_'.$i, $texts, null);
                    $labels[] = "[{$label}]";
                    $at += $seconds;
                }
                $skipped[] = ($seg['source'] ?? '?').': '.($path ?: 'no path');

                continue;
            }
            $from = (float) ($seg['from'] ?? 0);
            $to = (float) ($seg['to'] ?? 0);
            if ($to <= 0) {
                $to = ($this->duration($path) ?? $from) + $to;   // 0 = the end, -3 = three seconds before it
            }
            $length = max(0.1, $to - $from);
            $speed = isset($seg['fit']) ? max(1.0, $length / (float) $seg['fit']) : (float) ($seg['speed'] ?? 1);
            $seconds = $length / $speed;
            $input = count(array_keys($inputs, '-i', true));
            array_push($inputs, '-ss', VideoCut::number($from), '-t', VideoCut::number($length), '-i', $path);
            $chain = "[{$input}:v]".$this->blurs((array) ($spec['blur'] ?? []), (string) $seg['source'], $from, $to, $label);
            $chain .= sprintf('setpts=(PTS-STARTPTS)/%s,fps=%d', VideoCut::number($speed), self::FPS);
            $chain .= $variant === 'short' ? $this->vertical($seg, $source, $label) : $this->landscape($source, $w, $h);
            if (isset($seg['caption'])) {
                $shown = min($seconds, (float) ($seg['caption_seconds'] ?? 3.5));
                $chain .= ','.$this->caption($seg, $variant, $w, $h, $shown, $work.'_'.$i, $texts);
                $captions[] = [$at, $at + $shown, ['cs' => (string) ($seg['caption']['cs'] ?? ''), 'en' => (string) ($seg['caption']['en'] ?? '')]];
            }
            $graph[] = $chain.",setsar=1,format=yuv420p[{$label}]";
            $labels[] = "[{$label}]";
            $at += $seconds;
        }
        $graph[] = implode('', $labels).'concat=n='.count($labels).':v=1:a=0[v]';
        $cut = new VideoCut('howto', $out, [], $texts, $inputs, implode(';', $graph), $at, 0.0, self::FPS, null, 21);

        return ['cut' => $this->cutter->withMusic($cut, $this->music($spec), (float) ($spec['music_volume'] ?? 0.6)), 'captions' => $captions, 'skipped' => $skipped];
    }

    /** Subtitles of one language as SRT (the burnt-in captions, for YouTube's own track). */
    public function srt(array $captions, string $lang): string
    {
        $time = fn (float $s) => sprintf('%02d:%02d:%02d,%03d', intdiv((int) $s, 3600), intdiv((int) $s, 60) % 60, (int) $s % 60, (int) round(($s - floor($s)) * 1000) % 1000);
        $out = [];
        foreach (array_values($captions) as $n => [$from, $to, $text]) {
            $out[] = ($n + 1)."\n".$time($from).' --> '.$time($to)."\n".$text[$lang]."\n";
        }

        return implode("\n", $out);
    }

    /** The YouTube title and description: the text of the spec, the model's credit, our link (video_howto) and the music. */
    public function description(array $spec, ?string $music): array
    {
        $model = (array) ($spec['model'] ?? []);
        $credit = isset($model['url'])
            ? sprintf('Model: %s od %s (Printables, %s) – %s', $model['name'] ?? '', $model['author'] ?? '', $model['licence'] ?? '', $model['url'])
            : 'MODEL: …';
        $text = trim((string) ($spec['description'] ?? ''))."\n\n".$credit."\n\n"
            .'Vyzkoušejte to: '.$this->link($spec);
        if ($music) {
            $text .= "\n\n".__('youtube.video.music', ['track' => FarmVideos::musicCredit(basename($music))], config('youtube.language'));
        }

        return ['title' => mb_substr((string) ($spec['youtube_title'] ?? $spec['title']['cs'] ?? 'Matplace'), 0, 100), 'description' => $text."\n\n#3Dprinting #Printables"];
    }

    /** Our site with the how-to campaign; `site` in the spec when the video is built away from the server (the local APP_URL is not the site). */
    private function link(array $spec): string
    {
        $link = $this->videos->link(null, FarmVideo::KIND_HOWTO);

        return isset($spec['site']) ? rtrim((string) $spec['site'], '/').'/?'.parse_url($link, PHP_URL_QUERY) : $link;
    }

    /** The track: the one the spec names, else one of the library picked by the video's name (the same on every build). */
    public function music(array $spec): ?string
    {
        $named = (string) ($spec['music'] ?? 'auto');
        if ($named !== 'auto' && $named !== '') {
            return $this->cutter->musicFile($named);
        }
        $tracks = $this->videos->tracks();

        return $tracks ? $this->cutter->musicFile($tracks[crc32((string) ($spec['name'] ?? '')) % count($tracks)]) : null;
    }

    /** Blurred boxes of this source that fall into the segment, in the segment's own time (before the speed-up). */
    private function blurs(array $blurs, string $source, float $from, float $to, string $label): string
    {
        $chain = '';
        foreach (array_values($blurs) as $n => $b) {
            if (($b['source'] ?? '') !== $source || (float) $b['to'] <= $from || (float) $b['from'] >= $to) {
                continue;
            }
            [$x, $y, $bw, $bh] = array_map('intval', $b['box']);
            // the radius must stay under half the box, in the half-size colour planes too
            $luma = max(1, min(14, intdiv(min($bw, $bh), 2) - 1));
            $chroma = max(1, min($luma, intdiv(min($bw, $bh), 4) - 1));
            $chain .= sprintf("split[%s_m%d][%s_c%d];[%s_c%d]crop=%d:%d:%d:%d,boxblur=%d:3:%d:3[%s_b%d];[%s_m%d][%s_b%d]overlay=%d:%d:enable='between(t,%s,%s)',",
                $label, $n, $label, $n, $label, $n, $bw, $bh, $x, $y, $luma, $chroma, $label, $n, $label, $n, $label, $n, $x, $y,
                VideoCut::number(max(0, (float) $b['from'] - $from)), VideoCut::number((float) $b['to'] - $from));
        }

        return $chain;
    }

    /** 16:9: the page without the browser around it, whole, on a dark ground. */
    private function landscape(array $source, int $w, int $h): string
    {
        $crop = isset($source['crop']) ? vsprintf(',crop=%d:%d:%d:%d', [$source['crop'][2], $source['crop'][3], $source['crop'][0], $source['crop'][1]]) : '';

        return $crop.sprintf(',scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:color=0x111827', $w, $h, $w, $h);
    }

    /** 9:16: a 16:9 window of the picture (short_crop, else all of it) across the middle, the same picture blurred behind. */
    private function vertical(array $seg, array $source, string $label): string
    {
        [$sx, $sy, $sw, $sh] = $source['crop'] ?? [0, 0, 0, 0];
        if (isset($seg['short_crop'])) {
            [$cx, $cy, $cw] = array_map('intval', $seg['short_crop']);
            $ch = (int) round($cw * 9 / 16);
            $window = sprintf('crop=%d:%d:%d:%d,', $cw, $ch, max(0, $cx - intdiv($cw, 2)), max(0, $cy - intdiv($ch, 2)));
        } else {
            $window = $sw ? sprintf('crop=%d:%d:%d:%d,', $sw, $sh, $sx, $sy) : '';
        }

        return sprintf(',split[%s_bg][%s_fg];[%s_bg]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,boxblur=24:4,eq=brightness=-0.18[%s_b];[%s_fg]%sscale=1080:-2:flags=lanczos[%s_f];[%s_b][%s_f]overlay=0:(H-h)/2-200',
            $label, $label, $label, $label, $label, $window, $label, $label, $label);
    }

    /** cs above en on a dark band, for the first seconds of the segment, at the top or the bottom; long lines wrap. */
    private function caption(array $seg, string $variant, int $w, int $h, float $seconds, string $work, array &$texts): string
    {
        $short = $variant === 'short';
        [$cs, $en] = $short ? [62, 46] : [46, 34];
        $csLines = $this->wrap((string) ($seg['caption']['cs'] ?? ''), $cs, $w);
        $enLines = $this->wrap((string) ($seg['caption']['en'] ?? ''), $en, $w);
        $pad = (int) round($cs * 0.45);
        $csHeight = count($csLines) * (int) round($cs * 1.3);
        $band = 2 * $pad + $csHeight + count($enLines) * (int) round($en * 1.3);
        $y = $short ? 1280 : (($seg['caption_at'] ?? 'bottom') === 'top' ? 0 : $h - $band);
        $texts[$work.'_cs.txt'] = implode('
', $csLines);
        $texts[$work.'_en.txt'] = implode('
', $enLines);
        $on = sprintf("enable='lt(t,%s)'", VideoCut::number($seconds));

        return implode(',', [
            sprintf('drawbox=x=0:y=%d:w=%d:h=%d:color=black@0.62:t=fill:%s', $y, $w, $band, $on),
            $this->text($work.'_cs.txt', $cs, $y + $pad, 'white').':'.$on,
            $this->text($work.'_en.txt', $en, $y + $pad + $csHeight, '0xcbd5e1').':'.$on,
        ]);
    }

    /** intro: the title in cs and en with the address; end: the logo and the address; text: a line holding a missing shot's place. */
    private function card(array $seg, array $spec, int $w, int $h, float $seconds, string $label, string $work, array &$texts, ?int $logo): string
    {
        $big = (int) round(min($w, $h) * 0.07);
        $small = (int) round($big * 0.62);
        $bg = sprintf('color=c=0x111827:s=%dx%d:r=%d:d=%s', $w, $h, self::FPS, VideoCut::number($seconds));
        $address = (string) ($spec['address'] ?? 'matplace.com');
        if ($seg['card'] === 'end' && $logo !== null) {
            $texts[$work.'_url.txt'] = $address;
            // the logo (about 1.7 : 1) above the address, a gap between them, landscape and portrait alike
            [$top, $text, $width] = $h > $w ? [0.3, 0.52, 0.6] : [0.2, 0.64, 0.3];

            return sprintf('[%d:v]scale=%d:-2[%s_logo];%s[%s_bg];[%s_bg][%s_logo]overlay=(W-w)/2:H*%s:shortest=1,%s,fade=in:st=0:d=0.4,setsar=1,format=yuv420p[%s]',
                $logo, (int) round($w * $width), $label, $bg, $label, $label, $label, $top, $this->text($work.'_url.txt', $big, (int) round($h * $text), 'white'), $label);
        }
        $text = $seg['card'] === 'intro' ? (array) ($spec['title'] ?? []) : (array) ($seg['text'] ?? []);
        $blocks = [[$this->wrap((string) ($text['cs'] ?? ''), $big, $w), $big, 'white', '_cs'], [$this->wrap((string) ($text['en'] ?? ''), $small, $w), $small, '0xcbd5e1', '_en']];
        if ($seg['card'] === 'intro') {
            $blocks[] = [[$address], $small, '0xf97316', '_url'];
        }
        $gap = (int) round($big * 0.5);
        $total = array_sum(array_map(fn ($b) => count($b[0]) * (int) round($b[1] * 1.3), $blocks)) + $gap * (count($blocks) - 1);
        $y = intdiv($h - $total, 2);
        $parts = [$bg];
        foreach ($blocks as [$lines, $size, $color, $suffix]) {
            $texts[$work.$suffix.'.txt'] = implode('
', $lines);
            $parts[] = $this->text($work.$suffix.'.txt', $size, $y, $color);
            $y += count($lines) * (int) round($size * 1.3) + $gap;
        }

        return implode(',', $parts).',fade=in:st=0:d=0.3,setsar=1,format=yuv420p['.$label.']';
    }

    /** Lines of text read from a file, centred as a block and line by line. */
    private function text(string $file, int $size, int $y, string $color): string
    {
        return sprintf("drawtext=fontfile='%s':textfile='%s':expansion=none:text_align=C:line_spacing=%d:fontsize=%d:fontcolor=%s:x=(w-text_w)/2:y=%d",
            $this->cutter->font(), $this->cutter->path($file), (int) round($size * 0.3), $size, $color, $y);
    }

    /**
     * Words into lines that fit the frame (bold DejaVu is about 0.62 em a character on average).
     *
     * @return list<string>
     */
    public function wrap(string $text, int $size, int $width): array
    {
        $max = max(8, (int) floor($width * 0.88 / ($size * 0.62)));
        $lines = [''];
        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $last = count($lines) - 1;
            if ($lines[$last] !== '' && mb_strlen($lines[$last].' '.$word) > $max) {
                $lines[] = $word;
            } else {
                $lines[$last] = trim($lines[$last].' '.$word);
            }
        }

        return $lines;
    }

    /** Seconds of a video file (ffmpeg reads it), null when unknown. */
    private function duration(string $file): ?float
    {
        $r = Process::timeout(20)->run([(string) config('farm.ffmpeg', 'ffmpeg'), '-hide_banner', '-i', $file]);

        return preg_match('/Duration: (\d+):(\d+):(\d+(?:\.\d+)?)/', $r->errorOutput().$r->output(), $m) ? $m[1] * 3600 + $m[2] * 60 + (float) $m[3] : null;
    }
}
