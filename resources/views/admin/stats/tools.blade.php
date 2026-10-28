@extends('layouts.app', ['title' => 'Nástroje týdně · admin', 'noindex' => true])

@php
    $keys = $use['keys'];
    $recent = array_slice($keys, 0, 4);
    $label = fn (string $from) => \Carbon\Carbon::parse($from)->format('j. n.');
    $pct = fn (int $k, int $n) => $n > 0 ? str_replace('.', ',', (string) round(100 * $k / $n, 1)).' %' : '–';
@endphp

@section('content')
@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Statistiky">
    <a href="{{ route('admin.stats.funnel') }}" class="chip">Cesty návštěvníků</a>
    <a href="{{ route('admin.stats.search') }}" class="chip">Vyhledávání</a>
    <a href="{{ route('admin.ai.index') }}" class="chip">AI aktivita</a>
    <a href="{{ route('admin.stats.speed') }}" class="chip">Rychlost</a>
    <a href="{{ route('admin.stats.tools') }}" class="chip chip-on">Nástroje týdně</a>
    <a href=\"{{ route('admin.search_console.index') }}\" class=\"chip\">Search Console</a>
</nav>

<h2 class="mt-3 font-bold">Unikátní návštěvníci, kteří použili aspoň jeden nástroj</h2>
<p class="mt-1 text-xs text-slate-500">Metrika fáze „návštěvnost“ (docs/AN.md §12.3): prohlížeče (nebo přihlášení bez session), které v týdnu udělaly něco s nástrojem (kalkulace, model z fotky, stažení, Vytisknout u nás, sdílení). Jen lidé, bez nás. Týden od pondělí; běžící týden je neúplný.</p>
<div class="mt-2 flex gap-1 text-sm">@foreach(\App\Domain\Stats\ToolUse::PERIODS as $w)<a href="{{ route('admin.stats.tools', ['weeks' => $w]) }}" class="chip {{ $weeks === $w ? 'chip-on' : '' }}">{{ $w }} týdnů</a>@endforeach</div>
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Týden</th><th class="px-3 py-2 text-right">S nástrojem</th><th class="px-3 py-2 text-right">čeština</th><th class="px-3 py-2 text-right">angličtina</th><th class="px-3 py-2 text-right">španělština</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($use['weeks'] as $w)
                <tr><td class="px-3 py-2 font-semibold">{{ $w['week'] }} <span class="font-normal text-slate-500">od {{ $label($w['from']) }}</span></td><td class="px-3 py-2 text-right font-semibold">{{ $w['users'] }}</td>@foreach(['cs', 'en', 'es'] as $l)<td class="px-3 py-2 text-right">{{ $w['by_locale'][$l] ?? 0 }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2 class="mt-6 font-bold">Podle nástroje: poslední čtyři týdny a Search Console</h2>
@if($console && ($console['source'] ?? 'csv') === 'api')
    <p class="mt-1 text-xs text-slate-500" data-console-source="api">Data ze Search Console (API) {{ $console['from'] !== '' ? 'od '.\Carbon\Carbon::parse($console['from'])->format('j. n. Y') : '' }}{{ $console['until'] !== '' ? ' do '.\Carbon\Carbon::parse($console['until'])->format('j. n. Y') : ', pro toto období nic' }}: {{ number_format($console['rows'], 0, ',', ' ') }} stránek, celkem {{ number_format($console['total']['impressions'], 0, ',', ' ') }} zobrazení a {{ number_format($console['total']['clicks'], 0, ',', ' ') }} kliknutí. Stahuje se denně, 2 až 3 dny zpoždění · <a href="{{ route('admin.search_console.index') }}" class="underline">účet a stažení</a></p>
    <div class="mt-1 flex flex-wrap gap-1 text-sm" aria-label="Země">@foreach(['all' => 'všechny země', 'cz' => 'Česko', 'other' => 'ostatní země'] as $c => $name)<a href="{{ route('admin.stats.tools', ['weeks' => $weeks, 'country' => $c]) }}" class="chip {{ $country === $c ? 'chip-on' : '' }}">{{ $name }}</a>@endforeach</div>
@elseif($console)
    <p class="mt-1 text-xs text-slate-500" data-console-source="csv">Search Console: {{ $console['file'] }} ({{ $console['rows'] }} řádků, celkem {{ number_format($console['total']['impressions'], 0, ',', ' ') }} zobrazení a {{ number_format($console['total']['clicks'], 0, ',', ' ') }} kliknutí; CSV záloha, bez období a zemí) · <a href="{{ route('admin.search_console.index') }}" class="underline">připojit přes API</a></p>
@else
    <p class="mt-1 text-xs text-slate-500">Search Console zatím není: <a href="{{ route('admin.search_console.index') }}" class="underline">připojit přes API</a>, nebo uložte export jako <code>{{ \App\Domain\Stats\SearchConsole::dir() }}/search-console-&lt;datum&gt;.csv</code> (stránky, nebo stránky s dotazy; sloupce Page/Query/Clicks/Impressions/Position, česky i anglicky).</p>
@endif
@php $toolKeys = array_values(array_unique(array_merge(array_keys($use['tools']), $console ? array_keys($console['tools']) : []))); @endphp
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Nástroj</th>@foreach($recent as $from)<th class="px-3 py-2 text-right">od {{ $label($from) }}</th>@endforeach<th class="px-3 py-2 text-right">Zobrazení</th><th class="px-3 py-2 text-right">Kliknutí</th><th class="px-3 py-2 text-right">CTR</th><th class="px-3 py-2 text-right">Pozice</th><th class="px-3 py-2">Dotazy</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($toolKeys as $key)
                @php $c = $console['tools'][$key] ?? null; @endphp
                <tr>
                    <td class="px-3 py-2 font-semibold">{{ \Illuminate\Support\Facades\Lang::has('tools.'.$key.'.title') ? __('tools.'.$key.'.title') : $key }}</td>
                    @foreach($recent as $from)<td class="px-3 py-2 text-right">{{ $use['tools'][$key][$from] ?? 0 }}</td>@endforeach
                    <td class="px-3 py-2 text-right">{{ $c ? number_format($c['impressions'], 0, ',', ' ') : '–' }}@if($c && count($c['by_locale'] ?? []) > 1)<span class="block text-xs font-normal text-slate-500">@foreach($c['by_locale'] as $l => $n){{ $l }} {{ number_format($n['impressions'], 0, ',', ' ') }}@if(! $loop->last) · @endif @endforeach</span>@endif</td>
                    <td class="px-3 py-2 text-right">{{ $c ? number_format($c['clicks'], 0, ',', ' ') : '–' }}</td>
                    <td class="px-3 py-2 text-right">{{ $c ? $pct($c['clicks'], $c['impressions']) : '–' }}</td>
                    <td class="px-3 py-2 text-right">{{ $c && $c['position'] !== null ? str_replace('.', ',', (string) $c['position']) : '–' }}</td>
                    <td class="px-3 py-2 text-xs text-slate-600">{{ $c ? implode(' · ', array_map(fn ($q) => $q['query'].' ('.$q['clicks'].'/'.$q['impressions'].')', $c['queries'])) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 6 + count($recent) }}" class="px-3 py-4 text-center text-slate-500">Zatím nikdo nástroj nepoužil a Search Console nic nemá.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($console && $console['other'])
    <h3 class="mt-4 text-sm font-bold">Ostatní stránky v Search Console</h3>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Stránka</th><th class="px-3 py-2 text-right">Zobrazení</th><th class="px-3 py-2 text-right">Kliknutí</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($console['other'] as $path => $row)
                    <tr><td class="px-3 py-2 font-mono text-xs">{{ $path }}</td><td class="px-3 py-2 text-right">{{ number_format($row['impressions'], 0, ',', ' ') }}</td><td class="px-3 py-2 text-right">{{ number_format($row['clicks'], 0, ',', ' ') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2 class="mt-6 font-bold">Vložená kalkulačka: kde se ukázala (30 dní)</h2>
<p class="mt-1 text-xs text-slate-500">Každé načtení rámu <code>/embed/calc</code> z cizího webu (docs/AN.md §12.2); návštěvy, které z něj přišly, jsou v cestách jako zdroj „Vložená kalkulačka“.</p>
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Web</th><th class="px-3 py-2 text-right">Načtení</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($embeds as $host => $n)
                <tr><td class="px-3 py-2 font-mono text-xs">{{ $host }}</td><td class="px-3 py-2 text-right">{{ $n }}</td></tr>
            @empty
                <tr><td colspan="2" class="px-3 py-4 text-center text-slate-500">Zatím nikde.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
