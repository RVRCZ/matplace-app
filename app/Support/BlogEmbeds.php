<?php

namespace App\Support;

use App\Models\Calculation;
use App\Models\FarmVideo;

/**
 * What a blog article can embed without HTML in its Markdown (docs/AP.md §7.1): a line of its own
 *
 *   [video: <youtube id>]   a published video of the farm (FarmVideo), the no-cookie player only after a click;
 *   [video: latest]         the newest published one; nothing when there is none
 *   [calc: <token>]         a shared calculation /c/<token> as a card with its size, grams, print time and price,
 *                           or just the link while the calculation is not here (another database, a typo)
 *   [farm: <YYYY-MM>]       the farm's figures for that month (FarmMonthStats), counted when the page is shown, so an
 *                           article scheduled for the month after carries real numbers; nothing for a bad month
 *
 * The line is swapped for a plain word before Markdown renders (raw HTML is stripped there on purpose) and the
 * paragraph the word becomes is swapped for the view afterwards.
 */
final class BlogEmbeds
{
    // only spaces at the ends of the line: \s would eat the blank line after it and glue the word to the next paragraph
    private const LINE = '/^\[(video|calc|farm):[ \t]*([A-Za-z0-9_-]{1,40})\][ \t]*$/m';

    /** @param  callable(string): string  $toHtml  the Markdown renderer */
    public static function render(string $markdown, callable $toHtml): string
    {
        $embeds = [];
        $markdown = (string) preg_replace_callback(self::LINE, function (array $m) use (&$embeds) {
            $embeds[] = [$m[1], $m[2]];

            return 'MPEMBED'.(count($embeds) - 1).'END';
        }, $markdown);
        $html = $toHtml($markdown);
        foreach ($embeds as $i => [$kind, $ref]) {
            $html = (string) preg_replace('#<p>\s*MPEMBED'.$i.'END\s*</p>#', self::view($kind, $ref), $html, 1);
        }

        return $html;
    }

    /** @return list<string> the tokens of the calculations an article embeds (to check they are public) */
    public static function calculations(string $markdown): array
    {
        preg_match_all(self::LINE, $markdown, $m, PREG_SET_ORDER);

        return array_values(array_unique(array_map(fn ($x) => $x[2], array_filter($m, fn ($x) => $x[1] === 'calc'))));
    }

    private static function view(string $kind, string $ref): string
    {
        if ($kind === 'video') {
            $query = FarmVideo::query()->where('status', FarmVideo::STATUS_PUBLISHED)->whereNotNull('youtube_id');
            $video = $ref === 'latest' ? $query->latest('published_at')->first() : $query->where('youtube_id', $ref)->first();

            return $video ? view('blog._video', ['video' => $video])->render() : '';
        }
        if ($kind === 'farm') {
            $stats = FarmMonthStats::month($ref);

            return $stats ? view('blog._farm_month', ['stats' => $stats, 'ym' => $ref])->render() : '';
        }
        $calc = Calculation::query()->with('modelFile')->where('token', $ref)->first();

        return view('blog._calc', ['calc' => $calc && $calc->status === Calculation::STATUS_DONE ? $calc : null, 'token' => $ref])->render();
    }
}
