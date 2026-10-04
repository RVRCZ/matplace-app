{{--
    The owner's overview on top of /admin/stats (App\Domain\Stats\Overview): people, where they came from, how far
    they got, what is missing, what the robots did. The same numbers go out by e-mail every Monday.
--}}
@php
    $sourceNames = \App\Domain\Stats\WeeklyReport::SOURCES;
    $stepNames = \App\Domain\Stats\WeeklyReport::STEPS;
    $localeNames = ['cs' => 'čeština', 'en' => 'angličtina', 'es' => 'španělština'];
    $change = function (int $now, int $before): string {
        if ($before === 0) {
            return $now === 0 ? 'beze změny' : 'předtím 0';
        }
        $p = (int) round(100 * ($now - $before) / $before);

        return 'předtím '.$before.' ('.($p >= 0 ? '+' : '−').abs($p).' %)';
    };
    $peak = max(1, max(array_column($o['daily'], 'people') ?: [0]));
    $cut = $o['confirmed_since']?->toDateString();
@endphp

<section aria-label="Přehled" class="mt-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach($o['funnel'] as $step)
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $stepNames[$step['step']] }}</div>
                <div class="mt-1 text-3xl font-bold text-ink">{{ $step['people'] }}</div>
                <div class="mt-1 text-xs text-slate-500">
                    @if($step['rate'] !== null && $step['people'] > 0)<span class="font-semibold text-slate-700">{{ $step['rate'] }} % lidí</span> · @endif{{ $change($step['people'], $step['before']) }}
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-slate-500">
        Posledních {{ $o['days'] }} dní proti {{ $o['days'] }} dnům před nimi. <strong>Lidé</strong> = návštěvy, které potvrdil prohlížeč (stránka se opravdu zobrazila) nebo po nich někdo něco udělal; roboti a naše vlastní návštěvy se nepočítají.
        @if(! $cut) Potvrzování prohlížečem ještě nezačalo: starší návštěvy se počítají všechny, dokud je <code>matplace:events-bots</code> neoznačí jako roboty. @endif
    </p>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-bold">Lidé po dnech</h2>
            @if($cut)<span class="text-xs text-slate-500"><span class="mr-1 inline-block h-3 w-0.5 bg-ink align-middle"></span>{{ \Illuminate\Support\Carbon::parse($cut)->format('j. n. Y') }}: od tohoto dne se počítají jen potvrzené návštěvy (roboti pryč)</span>@endif
        </div>
        <div class="mt-3 flex h-36 items-end gap-0.5" role="img" aria-label="Počet lidí po dnech">
            @foreach($o['daily'] as $day)
                <div class="relative flex h-full flex-1 items-end {{ $day['day'] === $cut ? 'border-l-2 border-ink' : '' }}" title="{{ \Illuminate\Support\Carbon::parse($day['day'])->format('j. n.') }}: {{ $day['people'] }} lidí, {{ $day['robots'] }} stránek roboti">
                    <div class="w-full rounded-t bg-action" style="height: {{ $day['people'] > 0 ? max(2, round(100 * $day['people'] / $peak)) : 0 }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-xs text-slate-500">
            <span>{{ \Illuminate\Support\Carbon::parse($o['daily'][0]['day'])->format('j. n.') }}</span>
            <span>nejvíc {{ $peak }} za den</span>
            <span>{{ \Illuminate\Support\Carbon::parse(end($o['daily'])['day'])->format('j. n.') }}</span>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Odkud přišli</th><th class="px-3 py-2 text-right">Lidé</th><th class="px-3 py-2 text-right">Předtím</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($o['sources'] as $key => $row)
                        <tr><td class="px-3 py-2 font-semibold">{{ $sourceNames[$key] ?? $key }}</td><td class="px-3 py-2 text-right">{{ $row['people'] }}</td><td class="px-3 py-2 text-right text-slate-500">{{ $row['before'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">V tomhle období nikdo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="space-y-4">
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Jazyk</th><th class="px-3 py-2 text-right">Lidé</th><th class="px-3 py-2 text-right">Předtím</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($o['locales'] as $key => $row)
                            <tr><td class="px-3 py-2 font-semibold">{{ $localeNames[$key] ?? $key }}</td><td class="px-3 py-2 text-right">{{ $row['people'] }}</td><td class="px-3 py-2 text-right text-slate-500">{{ $row['before'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">V tomhle období nikdo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Odkazy z videí a příspěvků (UTM)</th><th class="px-3 py-2 text-right">Lidé</th><th class="px-3 py-2 text-right">Zaplatili</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($o['campaigns'] as $c)
                            <tr><td class="px-3 py-2"><span class="font-semibold">{{ $c['source'] ?: '?' }}</span> <span class="text-slate-500">{{ $c['medium'] }} · {{ $c['campaign'] ?: '—' }}</span></td><td class="px-3 py-2 text-right">{{ $c['people'] }}</td><td class="px-3 py-2 text-right">{{ $c['orders'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">Přes označený odkaz nikdo nepřišel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">{{ $o['pages']['landing'] ? 'Stránky, na které lidé přišli' : 'Nejnavštěvovanější stránky' }}</th><th class="px-3 py-2 text-right">Lidé</th><th class="px-3 py-2 text-right">Zobrazení</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($o['pages']['rows'] as $p)
                        <tr><td class="max-w-0 truncate px-3 py-2" title="{{ $p['path'] }}">@if(str_contains($p['path'], '{') || $p['path'] === '/?'){{ $p['path'] }}@else<a href="{{ $p['path'] }}" target="_blank" rel="noopener" class="underline">{{ $p['path'] }}</a>@endif</td><td class="px-3 py-2 text-right">{{ $p['people'] }}</td><td class="px-3 py-2 text-right text-slate-500">{{ $p['views'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">Zatím žádná stránka.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="space-y-4">
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Hledali a u nás nenašli <a href="{{ route('admin.stats.search', ['days' => $o['days']]) }}" class="ml-1 normal-case underline">vše ({{ $o['searches']['total'] }})</a></th><th class="px-3 py-2 text-right">Kolikrát</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($o['searches']['empty'] as $s)
                            <tr><td class="px-3 py-2">{{ $s['query'] }}</td><td class="px-3 py-2 text-right">{{ $s['searches'] }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="px-3 py-4 text-center text-slate-500">Nic, co by katalog neměl.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Adresy, které neexistují</th><th class="px-3 py-2 text-right">Lidé</th><th class="px-3 py-2 text-right">Roboti</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($o['missing'] as $m)
                            <tr><td class="px-3 py-2"><span class="break-all font-mono text-xs">{{ $m['path'] }}</span>@if($m['referer'])<div class="break-all text-xs text-slate-500">odkaz z {{ $m['referer'] }}</div>@endif</td><td class="px-3 py-2 text-right {{ $m['people'] > 0 ? 'font-semibold' : 'text-slate-500' }}">{{ $m['people'] }}</td><td class="px-3 py-2 text-right text-slate-500">{{ $m['robots'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">Žádná nenalezená adresa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="border-t border-slate-100 px-3 py-2 text-xs text-slate-500">Kam lidé chodí a nic tam není: kandidáti na přesměrování v <code>config/legacy.php</code>. Pokusy o cizí systémy (wp-login.php apod.) a chybějící obrázky se nesbírají.</p>
            </div>
        </div>
    </div>

    <p class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        <strong>Roboti:</strong>
        @if($o['robots']['pages'] === 0 && $o['unconfirmed'] === 0)
            v tomhle období žádné stránky.
        @else
            {{ $o['robots']['pages'] }} stránek —
            @foreach($o['robots']['by'] as $name => $r){{ $name }} {{ $r['pages'] }} ({{ $r['addresses'] }} adres)@if(! $loop->last), @endif @endforeach
            @if($o['unconfirmed'] > 0) · a {{ $o['unconfirmed'] }} návštěv „prohlížečů“, které žádný prohlížeč nepotvrdil (roboti v převleku).@endif
        @endif
        <span class="text-slate-500">Mezi lidi se nepočítají; vyhledávače takhle čtou web kvůli výsledkům hledání.</span>
    </p>
</section>

<h2 class="mt-8 text-lg font-bold">Cesty návštěvníků podrobně</h2>
