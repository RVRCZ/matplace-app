<?php

namespace App\Domain\YouTube;

/**
 * One farm video as ffmpeg will build it: the frame lists, the text files, the inputs, the filter graph and the planned
 * length. TimelapseCutter plans it and renders it; a test reads it without ffmpeg.
 */
final class VideoCut
{
    /**
     * @param  string  $kind  customer | short | wide | weekly
     * @param  string  $out  the MP4, absolute
     * @param  array<string, list<array{0: string, 1: float}>>  $lists  concat list file => frames (absolute) with their seconds
     * @param  array<string, string>  $texts  text file => its content (drawtext reads them as they are)
     * @param  list<string>  $inputs  ffmpeg input arguments in input order
     * @param  float  $seconds  the planned length of the whole video
     * @param  float  $growth  of which the print growing (frames × their seconds; the weekly cut: all pieces)
     * @param  ?string  $music  the track under it (absolute), null = silent
     * @param  list<array{0: float, 1: string, 2: ?string, 3: ?int}>  $chapters  start second, name, link, order id (the weekly cut)
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $out,
        public readonly array $lists,
        public readonly array $texts,
        public readonly array $inputs,
        public readonly string $graph,
        public readonly float $seconds,
        public readonly float $growth,
        public readonly int $fps,
        public readonly ?string $music,
        public readonly int $crf = 23,
        public readonly array $chapters = [],
    ) {}

    /** Frames shown, over all lists. */
    public function frameCount(): int
    {
        return array_sum(array_map('count', $this->lists));
    }

    /**
     * @param  ?string  $graphFile  the graph read from this file (`-/filter_complex`, ffmpeg 7+): a long graph does not fit
     *                              into a Windows command line (cmd.exe stops at 8191 characters)
     * @return list<string>
     */
    public function command(string $ffmpeg, ?string $graphFile = null): array
    {
        return [
            $ffmpeg, '-y', '-loglevel', 'error', ...$this->inputs,
            ...$graphFile ? ['-/filter_complex', $graphFile] : ['-filter_complex', $this->graph], '-map', '[v]',
            ...$this->music ? ['-map', '[a]', '-c:a', 'aac', '-b:a', '160k'] : [],
            '-r', (string) $this->fps, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', (string) $this->crf, '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
            // the planned length, whatever ffmpeg makes of the concat list's last frame (9.0 holds it once more than 7.1)
            '-t', self::number($this->seconds), $this->out,
        ];
    }

    /** The concat demuxer's list: every frame with its time, the last one named once more (else its time is lost). */
    public static function listText(array $frames): string
    {
        $quote = fn (string $f) => "'".str_replace("'", "'\\''", $f)."'";
        $lines = array_map(fn ($f) => 'file '.$quote($f[0])."\nduration ".self::number($f[1]), $frames);
        $lines[] = 'file '.$quote((string) end($frames)[0]);

        return implode("\n", $lines)."\n";
    }

    /** A time for ffmpeg: a dot, no exponent, no trailing zeros. */
    public static function number(float $seconds): string
    {
        return rtrim(rtrim(sprintf('%.6F', $seconds), '0'), '.') ?: '0';
    }
}
