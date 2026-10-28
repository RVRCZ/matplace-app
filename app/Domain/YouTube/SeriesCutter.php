<?php

namespace App\Domain\YouTube;

use App\Domain\Farm\PrintPhotos;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Two videos cut from what the farm already has (docs/AL.md §13.7; YouTube audit B7, priorities point 6), no new
 * footage: the rendered time-lapses of the prints (TimelapseCutter's files on the farm disk) and the composed photos
 * of the pieces (PrintPhotos).
 *
 *   price    the Short "Kolik stojí 3D tisk?": n pieces, each a second of its print growing, then the photographed
 *            piece on the studio backdrop with the tool's name and "Cena: 99 Kč"; the end card asks what to print
 *            next and shows matplace.com/tools (youtube:price-short)
 *   monthly  the 16:9 "Jak funguje naše 3D tisková farma": the month's prints between title cards that tell the
 *            steps (upload → price → print → photo → shipping), the photographed pieces, the farm's numbers
 *            (PublicStats), 3 to 5 minutes with chapters (farm:monthly-video)
 *
 * Planned without ffmpeg (VideoCut); TimelapseCutter renders. Both wait for the admin in /admin/youtube.
 */
class SeriesCutter
{
    /** The shortest piece of growing print worth a segment of the monthly video, seconds. */
    public const SEGMENT_MIN = 4.0;

    /** @var array<string, ?float> lengths of the rendered videos by ffmpeg, asked once per file */
    private array $durations = [];

    public function __construct(private readonly TimelapseCutter $cutter, private readonly FarmVideos $videos) {}

    // ── the price Short ──

    /**
     * @param  Collection<int, FarmOrder>  $orders  the pieces, in the order they are shown; each with a composed photo and a time-lapse file
     */
    public function priceShort(Collection $orders, string $out): VideoCut
    {
        $c = (array) config('youtube.cut.price');
        [$fps, $w, $h] = [(int) $c['fps'], (int) $c['width'], (int) $c['height']];
        [$clip, $piece, $open, $end, $fade] = [(float) $c['clip'], (float) $c['piece'], (float) $c['open'], (float) $c['end'], 0.3];
        $locale = (string) config('youtube.language');
        $work = dirname($out).'/'.pathinfo($out, PATHINFO_FILENAME);
        $font = $this->cutter->font();
        $texts = [
            $work.'_open.txt' => __('youtube.price.card', [], $locale),
            $work.'_open_sub.txt' => trans_choice('youtube.price.things', $orders->count(), ['n' => $orders->count()], $locale),
            $work.'_url.txt' => $this->toolsAddress(),
            $work.'_ask.txt' => implode("\n", $this->cutter->wrap(__('youtube.price.ask', [], $locale), (int) round($h * 0.03), $w)),
        ];
        $inputs = [];
        $graph = [];
        $labels = [];
        $index = 0;
        $line = fn (string $file, int $size, string $y, string $color = 'white', string $enable = '', bool $box = true) => sprintf("drawtext=fontfile='%s':textfile='%s':expansion=none:text_align=C:line_spacing=%d:fontsize=%d:fontcolor=%s%s:x=(w-text_w)/2:y=%s%s",
            $font, $this->cutter->path($file), (int) round($size * 0.3), $size, $color, $box ? ':box=1:boxcolor=black@0.55:boxborderw='.(int) round($h * 0.012) : '', $y, $enable !== '' ? ":enable='".$enable."'" : '');
        // the opening card: the question, then how many pieces (black, so no footage is needed before the first piece)
        $graph[] = sprintf('color=c=black:s=%dx%d:r=%d:d=%s,%s,%s,fade=in:st=0:d=0.3,format=yuv420p,setsar=1,settb=AVTB[open]', $w, $h, $fps, VideoCut::number($open),
            $line($work.'_open.txt', (int) round($h * 0.055), 'h*0.40', 'white', '', false), $line($work.'_open_sub.txt', (int) round($h * 0.034), 'h*0.49', '0xcbd5e1', '', false));
        foreach ($orders->values() as $i => $order) {
            [$source, $fromEnd] = $this->clipSource($order);
            $photo = PrintPhotos::file($order, 'short') ?? PrintPhotos::file($order);
            $name = $this->pieceName($order, $locale);
            $texts[$work.'_name_'.$i.'.txt'] = implode("\n", $this->cutter->wrap($name, (int) round($h * 0.036), $w));
            $texts[$work.'_price_'.$i.'.txt'] = $this->videos->priceLine($order, $locale);
            // the pieces, in the chapters slot: name, price, the tool's link, order id (the description lists them)
            $labels[] = [$name, $this->videos->price($order, $locale), $this->videos->link($order, FarmVideo::KIND_PRICE), $order->id];
            // the second of the print growing: taken from the end of the rendered video, before its own end card
            array_push($inputs, '-sseof', '-'.VideoCut::number($fromEnd), '-t', VideoCut::number($clip + 0.3), '-i', $source);
            $a = $index++;
            array_push($inputs, '-loop', '1', '-t', VideoCut::number($piece + 0.5), '-i', $photo);
            $b = $index++;
            // any shape of source (9:16, square, 16:9) across the frame, the same picture blurred behind it
            $graph[] = sprintf('[%d:v]split[bg%d][fg%d];[bg%d]scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,boxblur=20:4,eq=brightness=-0.15[b%d];[fg%d]scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos[f%d];[b%d][f%d]overlay=(W-w)/2:(H-h)/2,fps=%d,trim=duration=%s,setpts=PTS-STARTPTS,format=yuv420p,setsar=1,settb=AVTB[c%d]',
                $a, $i, $i, $i, $w, $h, $w, $h, $i, $i, $w, $h, $i, $i, $i, $fps, VideoCut::number($clip), $i);
            $graph[] = sprintf('[%d:v]scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,setsar=1,fps=%d,trim=duration=%s,setpts=PTS-STARTPTS,%s,format=yuv420p,settb=AVTB[p%d]',
                $b, $w, $h, $w, $h, $fps, VideoCut::number($piece), $line($work.'_price_'.$i.'.txt', (int) round($h * 0.06), 'h*0.72'), $i);
            // the clip fades into the photo; the piece's name sits at the top the whole time
            $graph[] = sprintf('[c%d][p%d]xfade=transition=fade:duration=%s:offset=%s,%s,settb=AVTB[piece%d]', $i, $i, VideoCut::number($fade), VideoCut::number($clip - $fade),
                $line($work.'_name_'.$i.'.txt', (int) round($h * 0.036), 'h*0.17'), $i);
        }
        $n = $orders->count();
        array_push($inputs, '-loop', '1', '-t', VideoCut::number($end + 1), '-i', public_path('img/logo.png'));
        $graph[] = sprintf('[%d:v]scale=%d:-2[logo];color=c=black:s=%dx%d:r=%d:d=%s[end_bg];[end_bg][logo]overlay=(W-w)/2:H*0.30:shortest=1,%s,%s,fade=in:st=0:d=0.4,format=yuv420p,setsar=1,settb=AVTB[end]',
            $index, (int) round($w * 0.5), $w, $h, $fps, VideoCut::number($end),
            $line($work.'_url.txt', (int) round($h * 0.045), 'h*0.52', 'white', '', false), $line($work.'_ask.txt', (int) round($h * 0.03), 'h*0.60', '0xe2e8f0', '', false));
        $graph[] = '[open]'.implode('', array_map(fn ($i) => "[piece{$i}]", range(0, $n - 1))).'[end]concat=n='.($n + 2).':v=1:a=0[v]';
        $seconds = $open + $n * ($clip + $piece - $fade) + $end;
        $tracks = $this->videos->tracks();
        $music = $tracks ? $this->cutter->musicFile($tracks[(int) now()->dayOfYear % count($tracks)]) : null;

        return $this->cutter->withMusic(new VideoCut(FarmVideo::KIND_PRICE, $out, [], $texts, $inputs, implode(';', $graph), $seconds, $n * $clip, $fps, null, 23, $labels), $music);
    }

    /**
     * The prints the Short may show, by the tool that made them (null = a customer's own file): a composed photo, a
     * price, a time-lapse file on the disk, the customer's consent, nothing made from people's photos, none the
     * admin rejected for the channel.
     *
     * @return Collection<string, Collection<int, FarmOrder>>
     */
    public function priceCandidates(): Collection
    {
        $disk = Storage::disk(config('farm.disk'));

        return FarmOrder::with(['modelFile', 'color.material'])->whereIn('kind', [FarmOrder::KIND_PRINT, FarmOrder::KIND_SHOWCASE])
            ->where('video_consent', true)->whereNotNull('photo_path')->where('price_total', '>', 0)
            ->whereDoesntHave('videos', fn ($q) => $q->where('status', FarmVideo::STATUS_REJECTED))
            ->orderByDesc('finished_at')->get()
            ->filter(fn (FarmOrder $o) => $this->shareable($o) && PrintPhotos::file($o, 'short') !== null
                && collect([$o->timelapse_short_path, $o->timelapse_path])->filter()->contains(fn ($p) => $disk->exists($p)))
            ->groupBy(fn (FarmOrder $o) => (string) ($this->videos->toolKind($o) ?? ''));
    }

    /**
     * $n prints for one Short: one of every tool first (the variety is the point), drawn at random, the ones no
     * price Short showed yet before the others; the same tool twice only when there are not enough tools.
     *
     * @param  Collection<string, Collection<int, FarmOrder>>  $byTool
     * @return Collection<int, FarmOrder>
     */
    public function pickForPrice(Collection $byTool, int $n, ?int $seed = null): Collection
    {
        if ($seed !== null) {
            mt_srand($seed);
        }
        $shown = FarmVideo::where('kind', FarmVideo::KIND_PRICE)->whereNotIn('status', [FarmVideo::STATUS_REJECTED, FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_FAILED])->get()
            ->flatMap(fn (FarmVideo $v) => (array) ($v->meta['orders'] ?? []))->unique()->all();
        $pools = $byTool->map(fn (Collection $orders) => $orders->sortBy(fn (FarmOrder $o) => [in_array($o->id, $shown, true) ? 1 : 0, mt_rand()])->values())
            ->sortBy(fn (Collection $pool) => [in_array($pool->first()?->id, $shown, true) ? 1 : 0, mt_rand()])->values()->all();
        $picked = collect();
        while ($picked->count() < $n && array_filter($pools, fn ($p) => $p->isNotEmpty())) {
            foreach ($pools as $k => $pool) {
                if ($pool->isEmpty() || $picked->count() >= $n) {
                    continue;
                }
                $picked->push($pool->shift());
                $pools[$k] = $pool;
            }
        }
        if ($seed !== null) {
            mt_srand();
        }

        return $picked->values();
    }

    /** The title and description of a price Short, for the queue and for the admin's form. @return array{title: string, description: string} */
    public function priceTexts(VideoCut $cut, ?string $music): array
    {
        $locale = (string) config('youtube.language');
        $n = count($cut->chapters);
        $lines = array_map(fn ($c) => __('youtube.price.line', ['name' => $c[0], 'price' => $c[1] ?? '', 'url' => $c[2]], $locale), $cut->chapters);
        $text = __('youtube.price.description', ['cta' => $this->videos->callLine($this->videos->link(null, FarmVideo::KIND_PRICE, 'youtube', $this->toolsUrl()), $locale),
            'pieces' => trans_choice('youtube.price.pieces', $n, ['n' => $n], $locale)], $locale)
            ."\n\n".implode("\n", $lines);
        if ($music) {
            $text .= "\n\n".__('youtube.video.music', ['track' => FarmVideos::musicCredit($music)], $locale);
        }
        $text .= "\n\n".__('youtube.price.hashtags', [], $locale);

        return ['title' => mb_substr(__('youtube.price.title', ['things' => trans_choice('youtube.price.things', $n, ['n' => $n], $locale)], $locale), 0, 100), 'description' => $text];
    }

    // ── the monthly 16:9 ──

    /**
     * @param  Collection<int, FarmOrder>  $orders  the month's prints, oldest first, each with a time-lapse file
     * @param  array{printed: int, handed_over_days_median: ?float, last_30_days_printed: int, videos_published: int}  $numbers  PublicStats::summary()
     */
    public function monthly(Collection $orders, Carbon $month, array $numbers, string $out): VideoCut
    {
        $c = (array) config('youtube.cut.monthly');
        [$fps, $w, $h] = [(int) $c['fps'], (int) $c['width'], (int) $c['height']];
        [$intro, $card, $photoSeconds, $numbersSeconds, $end] = [(float) $c['intro'], (float) $c['card'], (float) $c['photo'], (float) $c['numbers'], (float) $c['end']];
        $locale = (string) config('youtube.language');
        $steps = (array) __('youtube.monthly.steps', [], $locale);
        $work = dirname($out).'/'.pathinfo($out, PATHINFO_FILENAME);
        $font = $this->cutter->font();
        $orders = $this->fitMonth($orders);
        $photos = $orders->filter(fn (FarmOrder $o) => PrintPhotos::file($o) !== null)->take((int) $c['photos_max'])->values();
        // the pieces grow to fill 3 minutes when the month was quiet, within the range and what the source holds
        $perPiece = $orders->isEmpty() ? 0.0 : max((float) $c['piece'][0], min((float) $c['piece'][1],
            ((float) $c['min_seconds'] - $this->fixedSeconds($c, count($steps)) - $photos->count() * $photoSeconds) / $orders->count()));
        $text = fn (string $file, int $size, string $y, string $color = 'white') => sprintf("drawtext=fontfile='%s':textfile='%s':expansion=none:text_align=C:line_spacing=%d:fontsize=%d:fontcolor=%s:x=(w-text_w)/2:y=%s",
            $font, $this->cutter->path($file), (int) round($size * 0.3), $size, $color, $y);
        $black = fn (float $seconds) => sprintf('color=c=black:s=%dx%d:r=%d:d=%s', $w, $h, $fps, VideoCut::number($seconds));
        $texts = [$work.'_intro.txt' => __('youtube.monthly.card', [], $locale), $work.'_month.txt' => $this->monthLabel($month), $work.'_url.txt' => $this->videos->address(null),
            $work.'_ask.txt' => __('youtube.monthly.ask', [], $locale)];
        $inputs = [];
        $graph = [];
        $order = [];
        $chapters = [];
        $index = 0;
        $at = 0.0;
        $growth = 0.0;
        // the prints, split over the first three steps (the model, the price, the print itself); the photos under the fourth
        $groups = $orders->isEmpty() ? [collect(), collect(), collect()] : $orders->split(3)->pad(3, collect())->values()->all();
        $sequence = ['intro'];
        $chapters[] = [0.0, __('youtube.monthly.intro_chapter', [], $locale), null, null];
        $at += $intro;
        foreach (array_values($steps) as $s => $step) {
            $label = 'card'.$s;
            $texts[$work.'_step_'.$s.'.txt'] = implode("\n", $this->cutter->wrap((string) $step['title'], (int) round($h * 0.075), $w));
            $texts[$work.'_sub_'.$s.'.txt'] = implode("\n", $this->cutter->wrap((string) ($step['sub'] ?? ''), (int) round($h * 0.04), $w));
            $graph[] = sprintf('%s,%s,%s,fade=in:st=0:d=0.3,fade=out:st=%s:d=0.3,format=yuv420p,setsar=1,settb=AVTB[%s]', $black($card),
                $text($work.'_step_'.$s.'.txt', (int) round($h * 0.075), 'h*0.36'), $text($work.'_sub_'.$s.'.txt', (int) round($h * 0.04), 'h*0.56', '0xcbd5e1'), VideoCut::number($card - 0.3), $label);
            $sequence[] = $label;
            $chapters[] = [$at, (string) $step['title'], null, null];
            $at += $card;
            if ($s < 3) {
                foreach ($groups[$s] as $print) {
                    [$source, $skip, $max] = $this->segmentSource($print);
                    $seconds = min($perPiece, $max);
                    $texts[$work.'_name_'.$index.'.txt'] = $this->videos->cardFacts($print, $locale)['name'];
                    array_push($inputs, '-ss', VideoCut::number($skip), '-t', VideoCut::number($seconds + 0.5), '-i', $source);
                    $graph[] = sprintf("[%d:v]scale=%d:%d:force_original_aspect_ratio=decrease:flags=lanczos,pad=%d:%d:(ow-iw)/2:(oh-ih)/2,fps=%d,trim=duration=%s,setpts=PTS-STARTPTS,drawtext=fontfile='%s':textfile='%s':expansion=none:fontsize=%d:fontcolor=white:box=1:boxcolor=black@0.55:boxborderw=%d:x=w*0.04:y=h*0.86,fade=in:st=0:d=0.25,format=yuv420p,setsar=1,settb=AVTB[s%d]",
                        $index, $w, $h, $w, $h, $fps, VideoCut::number($seconds), $font, $this->cutter->path($work.'_name_'.$index.'.txt'), (int) round($h * 0.045), (int) round($h * 0.015), $index);
                    $sequence[] = 's'.$index;
                    $order[] = $print->id;
                    $at += $seconds;
                    $growth += $seconds;
                    $index++;
                }
            } elseif ($s === 3) {
                foreach ($photos as $k => $print) {
                    array_push($inputs, '-loop', '1', '-t', VideoCut::number($photoSeconds + 1), '-i', PrintPhotos::file($print));
                    $graph[] = sprintf('[%d:v]scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=%d,trim=duration=%s,setpts=PTS-STARTPTS,fade=in:st=0:d=0.4,fade=out:st=%s:d=0.4,format=yuv420p,settb=AVTB[ph%d]',
                        $index, $w, $h, $w, $h, $fps, VideoCut::number($photoSeconds), VideoCut::number($photoSeconds - 0.4), $k);
                    $sequence[] = 'ph'.$k;
                    $at += $photoSeconds;
                    $index++;
                }
            } else {
                // the farm in numbers, under the shipping step
                $texts[$work.'_numbers.txt'] = $this->numbersText($numbers, $locale);
                $graph[] = sprintf('%s,%s,fade=in:st=0:d=0.3,fade=out:st=%s:d=0.3,format=yuv420p,setsar=1,settb=AVTB[numbers]', $black($numbersSeconds),
                    $text($work.'_numbers.txt', (int) round($h * 0.05), 'h*0.34'), VideoCut::number($numbersSeconds - 0.3));
                $sequence[] = 'numbers';
                $at += $numbersSeconds;
            }
        }
        array_push($inputs, '-loop', '1', '-t', VideoCut::number($at + $end + 1), '-i', public_path('img/logo.png'));
        $graph[] = sprintf('[%d:v]format=rgba,split[logo_i][logo_e];[logo_i]scale=%d:-2[logo_intro];%s[bg_i];[bg_i][logo_intro]overlay=(W-w)/2:H*0.16:shortest=1,%s,%s,fade=in:st=0:d=0.4,fade=out:st=%s:d=0.3,format=yuv420p,setsar=1,settb=AVTB[intro]',
            $index, (int) round($w * 0.28), $black($intro), $text($work.'_intro.txt', (int) round($h * 0.08), 'h*0.56'), $text($work.'_month.txt', (int) round($h * 0.045), 'h*0.70', '0xcbd5e1'), VideoCut::number($intro - 0.3));
        $graph[] = sprintf('[logo_e]scale=%d:-2[end_logo];%s[end_bg];[end_bg][end_logo]overlay=(W-w)/2:H*0.22:shortest=1,%s,%s,fade=in:st=0:d=0.4,format=yuv420p,setsar=1,settb=AVTB[end]',
            (int) round($w * 0.34), $black($end), $text($work.'_url.txt', (int) round($h * 0.06), 'h*0.60'), $text($work.'_ask.txt', (int) round($h * 0.035), 'h*0.72', '0xe2e8f0'));
        $sequence[] = 'end';
        $graph[] = implode('', array_map(fn ($l) => "[{$l}]", $sequence)).'concat=n='.count($sequence).':v=1:a=0[v]';
        $seconds = $at + $end;
        $tracks = $this->videos->tracks();
        $music = $tracks ? $this->cutter->musicFile($tracks[(int) $month->month % count($tracks)]) : null;
        $cut = new VideoCut(FarmVideo::KIND_MONTHLY, $out, [], $texts, $inputs, implode(';', $graph), $seconds, $growth, $fps, null, 21, $chapters);

        return $this->cutter->withMusic($cut, $music);
    }

    /**
     * The month's prints that may be in the video, oldest first: the customer agreed, nothing made from people's photos,
     * none the admin rejected, a time-lapse file still on the disk.
     *
     * @return Collection<int, FarmOrder>
     */
    public function monthCandidates(Carbon $month): Collection
    {
        return FarmOrder::with(['modelFile', 'color.material'])->whereIn('kind', [FarmOrder::KIND_PRINT, FarmOrder::KIND_SHOWCASE])
            ->where('video_consent', true)->whereNotNull('timelapse_path')
            ->whereBetween('finished_at', [$month->copy()->startOfMonth()->utc(), $month->copy()->endOfMonth()->utc()])
            ->whereDoesntHave('videos', fn ($q) => $q->where('status', FarmVideo::STATUS_REJECTED))
            ->orderBy('finished_at')->get()
            ->filter(fn (FarmOrder $o) => $this->shareable($o) && $this->segmentSource($o) !== null)->values();
    }

    /** The title and description of the month's video: written for what people search ("jak funguje 3D tisková farma", "jak zjistit cenu 3D tisku"). @return array{title: string, description: string} */
    public function monthlyTexts(VideoCut $cut, Carbon $month, array $numbers, ?string $music): array
    {
        $locale = (string) config('youtube.language');
        $lines = array_map(fn ($c) => sprintf('%d:%02d %s', intdiv((int) $c[0], 60), (int) $c[0] % 60, $c[1]), $cut->chapters);
        $text = __('youtube.monthly.description', ['cta' => $this->videos->callLine($this->videos->link(null, FarmVideo::KIND_MONTHLY), $locale), 'month' => $this->monthLabel($month),
            'numbers' => str_replace("\n", ' · ', $this->numbersText($numbers, $locale))], $locale)."\n\n".implode("\n", $lines);
        if ($music) {
            $text .= "\n\n".__('youtube.video.music', ['track' => FarmVideos::musicCredit($music)], $locale);
        }
        $text .= "\n\n".__('youtube.monthly.hashtags', [], $locale);

        return ['title' => mb_substr(__('youtube.monthly.title', ['month' => $this->monthLabel($month)], $locale), 0, 100), 'description' => $text];
    }

    /** "říjen 2026" in the channel's language. */
    public function monthLabel(Carbon $month): string
    {
        return $month->copy()->locale((string) config('youtube.language'))->isoFormat('MMMM YYYY');
    }

    // ── helpers ──

    /** No faces: nothing generated from a person's photo (FarmVideos::PRIVATE_KINDS), as the weekly cut. */
    public function shareable(FarmOrder $order): bool
    {
        return ! in_array($order->modelFile?->kind(), FarmVideos::PRIVATE_KINDS, true) && ! in_array($order->modelFile?->origin, FarmVideos::PRIVATE_KINDS, true);
    }

    /**
     * The rendered video a second of growing print is taken from and how far before its end that second lies: the
     * Short (its end card and the held plate come after), else the customer's video.
     *
     * @return array{0: string, 1: float}
     */
    private function clipSource(FarmOrder $order): array
    {
        $disk = Storage::disk(config('farm.disk'));
        $c = (array) config('youtube.cut.price');
        if ($order->timelapse_short_path && $disk->exists($order->timelapse_short_path)) {
            return [$disk->path($order->timelapse_short_path), (float) $c['short_from_end']];
        }

        return [$disk->path((string) $order->timelapse_path), (float) $c['customer_from_end']];
    }

    /**
     * The rendered video a segment of the monthly cut comes from, where its growing print starts (after the title or
     * the countdown) and how many seconds of growing there are: the 16:9, else the customer's video; null without one
     * or when the print grows for less than SEGMENT_MIN. The growing part is what the file holds minus what comes
     * after it (the held piece, the photo, the end card – the customer's video ends with a black "thank you" card that
     * must never land in the month's cut: 28 Oct 2026, a print of 18.8 s got a 12.5 s window); without ffmpeg to
     * measure the file the kind's shortest growth is assumed.
     *
     * @return array{0: string, 1: float, 2: float}|null
     */
    private function segmentSource(FarmOrder $order): ?array
    {
        $disk = Storage::disk(config('farm.disk'));
        $photo = $order->hasPhoto() ? 1 : 0;
        if ($order->timelapse_wide_path && $disk->exists($order->timelapse_wide_path)) {
            $wide = (array) config('youtube.cut.wide');
            $file = $disk->path($order->timelapse_wide_path);
            $after = (float) $wide['hold'] + (float) $wide['end'] + $photo * (float) $wide['photo'];
            $max = $this->growing($file, (float) $wide['title'], $after, (float) $wide['growth'][0]);

            return $max >= self::SEGMENT_MIN ? [$file, (float) $wide['title'], $max] : null;
        }
        if ($order->timelapse_path && $disk->exists($order->timelapse_path)) {
            $customer = (array) config('youtube.cut.customer');
            $file = $disk->path($order->timelapse_path);
            // the customer's video: logo and countdown 7 s, the print, the piece held, the photo, 5 s of thanks
            $after = (float) $customer['hold'] + 5.0 + $photo * (float) $customer['photo'];
            $max = $this->growing($file, 7.0, $after, (float) $customer['growth'][0]);

            return $max >= self::SEGMENT_MIN ? [$file, 7.0, $max] : null;
        }

        return null;
    }

    /** Seconds of growing print in a rendered video: its length minus what is before and after; $assumed when ffmpeg cannot measure it. */
    private function growing(string $file, float $before, float $after, float $assumed): float
    {
        if (! array_key_exists($file, $this->durations)) {
            $this->durations[$file] = $this->cutter->duration($file);
        }
        $duration = $this->durations[$file];

        return $duration === null ? $assumed : max(0.0, $duration - $before - $after);
    }

    /**
     * At most as many prints as fit the longest video at the shortest piece: the likely hits (FarmVideos::score) stay,
     * shown in the order printed. The command keeps the same list (meta.orders).
     *
     * @param  Collection<int, FarmOrder>  $orders
     * @return Collection<int, FarmOrder>
     */
    public function fitMonth(Collection $orders): Collection
    {
        $c = (array) config('youtube.cut.monthly');
        // the cards, the numbers, the end and the photos (at most photos_max of them) come off the top; the rest is for the prints at their shortest
        $fixed = $this->fixedSeconds($c, count((array) __('youtube.monthly.steps', [], (string) config('youtube.language')))) + (int) $c['photos_max'] * (float) $c['photo'];
        $max = max(1, (int) floor(((float) $c['max_seconds'] - $fixed) / (float) $c['piece'][0]));
        if ($orders->count() <= $max) {
            return $orders->values();
        }
        // positions, not ids: an Eloquent collection's only() would take the models whose ids happen to match
        $keep = $orders->sortByDesc(fn (FarmOrder $o) => FarmVideo::where('farm_order_id', $o->id)->value('score') ?? $this->videos->score($o) ?? 0)->take($max)->keys()->all();

        return $orders->filter(fn (FarmOrder $o, $k) => in_array($k, $keep, true))->values();
    }

    private function fixedSeconds(array $c, int $steps): float
    {
        return (float) $c['intro'] + $steps * (float) $c['card'] + (float) $c['numbers'] + (float) $c['end'];
    }

    /** The farm's numbers on their card, one a line. */
    private function numbersText(array $numbers, string $locale): string
    {
        $days = $numbers['handed_over_days_median'];
        $lines = [
            trans_choice('youtube.monthly.numbers.recent', (int) $numbers['last_30_days_printed'], ['n' => (int) $numbers['last_30_days_printed']], $locale),
            trans_choice('youtube.monthly.numbers.printed', (int) $numbers['printed'], ['n' => (int) $numbers['printed']], $locale),
        ];
        if ($days !== null) {
            $lines[] = trans_choice('youtube.monthly.numbers.days', (int) round((float) $days), ['d' => (int) round((float) $days)], $locale);
        }
        if ((int) $numbers['videos_published'] > 0) {
            $lines[] = trans_choice('youtube.monthly.numbers.videos', (int) $numbers['videos_published'], ['n' => (int) $numbers['videos_published']], $locale);
        }

        return implode("\n", $lines);
    }

    /**
     * What the piece is called on its card: the tool's title ("Busta z jedné fotky"). A customer's own file never shows
     * its file name ("Mystical Chine Dragon V EYE", the dry run of 28 Oct 2026): the title the admin gave its video
     * (before the print time, i.e. after the retitle of the channel), else a plain label with the material.
     */
    private function pieceName(FarmOrder $order, string $locale): string
    {
        $kind = $this->videos->toolKind($order);
        $key = 'youtube.title.'.$kind;
        if ($kind !== null && __($key, [], $locale) !== $key) {
            return __($key, [], $locale);
        }
        $title = (string) FarmVideo::where('farm_order_id', $order->id)->whereIn('kind', [FarmVideo::KIND_SHORT, FarmVideo::KIND_WIDE])
            ->whereNotIn('status', [FarmVideo::STATUS_WITHDRAWN, FarmVideo::STATUS_FAILED, FarmVideo::STATUS_REJECTED, FarmVideo::STATUS_MISSING])
            ->orderByRaw("case when status = 'published' then 0 else 1 end")->orderByDesc('id')->value('title');
        $name = trim((string) preg_split('/\s+[–-]\s+/u', $title, 2)[0]);
        if ($name !== '' && $name !== $title) {
            return mb_substr($name, 0, 60);
        }
        $material = trim(($order->color?->material?->label() ?? '').' '.($order->color?->displayName() ?? ''));

        return __('youtube.price.own_file', [], $locale).($material !== '' ? ' · '.$material : '');
    }

    /** The tools page, absolute and without marks: where the price Short sends people. */
    public function toolsUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/tools';
    }

    /** "matplace.com/tools" as the end card shows it. */
    public function toolsAddress(): string
    {
        return rtrim((string) preg_replace('#^https?://(www\.)?#', '', $this->toolsUrl()), '/');
    }
}
