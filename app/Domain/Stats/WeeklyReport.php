<?php

namespace App\Domain\Stats;

use App\Models\FarmVideo;
use App\Models\SocialPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The week in words for the owner's e-mail (matplace:stats-report): the same numbers as the overview in
 * /admin/stats, each next to the week before, plus what was published and how many people it brought.
 */
final class WeeklyReport
{
    public const SOURCES = ['google' => 'Google', 'seznam' => 'Seznam', 'bing' => 'Bing', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'designer' => 'Odkazy designérů', 'direct' => 'Přímo (bez odkazu)', 'other' => 'Ostatní weby'];

    public const STEPS = ['people' => 'Lidé na webu', 'calculation' => 'Spočítali cenu', 'upload' => 'Nahráli model', 'order_created' => 'Založili zakázku', 'order_paid' => 'Zaplatili'];

    public const LOCALES = ['cs' => 'česky', 'en' => 'anglicky', 'es' => 'španělsky'];

    public function __construct(private Overview $overview) {}

    /**
     * @return array{subject: string, intro: string, sections: list<array{title: string, lines: list<string>}>}
     */
    public function build(int $days = 7, ?Carbon $until = null): array
    {
        $until = ($until ?? now())->copy()->startOfDay();
        $o = $this->overview->compute($days, $until);
        $period = $o['from']->format('j. n.').' – '.$until->copy()->subDay()->format('j. n. Y');
        $sections = [];

        $sections[] = ['title' => 'Kolik lidí a co udělali', 'lines' => array_map(
            fn (array $s) => sprintf('%s: %d%s%s', self::STEPS[$s['step']], $s['people'], $s['rate'] !== null && $s['people'] > 0 ? ' ('.self::number($s['rate']).' % lidí)' : '', self::change($s['people'], $s['before'])),
            $o['funnel'],
        )];

        $lines = [];
        foreach ($o['sources'] as $key => $row) {
            $lines[] = sprintf('%s: %d%s', self::SOURCES[$key] ?? $key, $row['people'], self::change($row['people'], $row['before']));
        }
        $sections[] = ['title' => 'Odkud přišli', 'lines' => $lines ?: ['Nikdo.']];

        if (count($o['locales']) > 1) {
            $sections[] = ['title' => 'Jazyky', 'lines' => array_map(fn (string $k) => sprintf('%s: %d%s', self::LOCALES[$k] ?? $k, $o['locales'][$k]['people'], self::change($o['locales'][$k]['people'], $o['locales'][$k]['before'])), array_keys($o['locales']))];
        }

        $sections[] = ['title' => 'Co jsme zveřejnili a co to přineslo', 'lines' => $this->published($o)];

        if ($o['pages']['rows']) {
            $sections[] = ['title' => $o['pages']['landing'] ? 'Stránky, na které lidé přišli' : 'Nejnavštěvovanější stránky', 'lines' => array_map(fn (array $p) => sprintf('%s — %d lidí', $p['path'], $p['people']), array_slice($o['pages']['rows'], 0, 10))];
        }
        if ($o['searches']['empty']) {
            $sections[] = ['title' => 'Hledali a v našem katalogu nenašli', 'lines' => array_map(fn (array $s) => sprintf('„%s“ — %d×', $s['query'], $s['searches']), array_slice($o['searches']['empty'], 0, 10))];
        }
        $missing = array_values(array_filter($o['missing'], fn (array $m) => $m['people'] > 0));
        if ($missing) {
            $sections[] = ['title' => 'Adresy, které neexistují (kandidáti na přesměrování)', 'lines' => array_map(fn (array $m) => sprintf('%s — %d× lidé, %d× roboti%s', $m['path'], $m['people'], $m['robots'], $m['referer'] ? ', odkaz z '.$m['referer'] : ''), array_slice($missing, 0, 10))];
        }

        $robots = [];
        foreach (array_slice($o['robots']['by'], 0, 6, true) as $name => $r) {
            $robots[] = sprintf('%s: %d stránek (%d různých adres)', $name, $r['pages'], $r['addresses']);
        }
        if ($o['unconfirmed'] > 0) {
            $robots[] = sprintf('Roboti vydávající se za prohlížeč (návštěvy, které žádný prohlížeč nepotvrdil): %d', $o['unconfirmed']);
        }
        $sections[] = ['title' => 'Roboti (nepočítají se mezi lidi)', 'lines' => $robots ?: ['Žádné stránky.']];

        return [
            'subject' => sprintf('Matplace za týden %s: %d lidí%s, %d zaplacených zakázek', $period, $o['people'], self::change($o['people'], $o['people_before']), $o['funnel'][4]['people']),
            'intro' => sprintf('Týden %s ve srovnání s týdnem před ním. Lidé = návštěvy potvrzené prohlížečem, bez robotů a bez nás.', $period),
            'sections' => $sections,
        ];
    }

    /**
     * Videos and posts that went public in the period, and the people who came through their links.
     *
     * @param  array<string, mixed>  $o
     * @return list<string>
     */
    private function published(array $o): array
    {
        $lines = [];
        foreach (FarmVideo::where('status', FarmVideo::STATUS_PUBLISHED)->where('published_at', '>=', $o['from'])->where('published_at', '<', $o['until'])->orderBy('published_at')->get() as $video) {
            $lines[] = sprintf('Video „%s“ (%s) — %d zhlédnutí na YouTube', Str::limit((string) $video->title, 70), $video->published_at->format('j. n.'), (int) $video->views);
        }
        $posts = SocialPost::where('status', SocialPost::STATUS_POSTED)->where('posted_at', '>=', $o['from'])->where('posted_at', '<', $o['until'])->get()->groupBy('platform');
        foreach ($posts as $platform => $group) {
            $lines[] = sprintf('%s: %d příspěvků', ucfirst((string) $platform), $group->count());
        }
        if (! $lines) {
            $lines[] = 'Tento týden nic nového nevyšlo.';
        }
        foreach ($o['campaigns'] as $c) {
            $lines[] = sprintf('Přes odkaz %s / %s: %d lidí, %d zaplacených zakázek', $c['source'] ?: '?', $c['campaign'] ?: ($c['medium'] ?: '—'), $c['people'], $c['orders']);
        }
        if (! $o['campaigns']) {
            $lines[] = 'Přes odkazy z videí a příspěvků (UTM) nepřišel nikdo.';
        }

        return $lines;
    }

    /** " (minule 12, +25 %)" */
    public static function change(int $now, int $before): string
    {
        if ($before === 0) {
            return $now === 0 ? '' : ' (minule 0)';
        }
        $percent = (int) round(100 * ($now - $before) / $before);

        return sprintf(' (minule %d, %s%d %%)', $before, $percent >= 0 ? '+' : '−', abs($percent));
    }

    private static function number(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, ',', ' '), '0'), ',');
    }
}
