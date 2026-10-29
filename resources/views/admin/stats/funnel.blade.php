@extends('layouts.app', ['title' => 'Statistiky · admin', 'noindex' => true])

@php
    $link = fn (array $change) => route('admin.stats.funnel', array_filter(array_merge(['days' => $stats['days'], 'source' => $source, 'locale' => $locale, 'tool' => $tool], $change)));
    $steps = [
        'visit' => 'Návštěva', 'upload|generate' => 'Nahrál nebo vygeneroval model', 'calculation' => 'Spočítal cenu', 'order_paid' => 'Zaplatil tisk',
        'model_view|tool_view|calculation|generate' => 'Stránka modelu nebo výstup nástroje', 'download' => 'Stáhl model',
        'register' => 'Registrace', 'designer_enabled' => 'Zapnul designérský profil', 'designer_import' => 'Import portfolia', 'designer_file_uploaded' => 'Nahrál soubor pro farmu',
        'model_ready' => 'Model hotový', 'price_shown' => 'Viděl přesnou cenu', 'order_started|order_created' => 'Začal objednávku', 'checkout_started' => 'Šel zaplatit',
        // the sales path (docs/AN.md §9)
        'upload|failed:upload' => 'Nahrání začalo (i odmítnuté)', 'upload' => 'Nahráno', 'order_started' => 'Klepl na Vytisknout u nás',
        'register|register_visit|order_created' => 'Registrace, účet, nebo zakázka bez účtu', 'order_created' => 'Zakázka založena', 'checkout_started|topup_started' => 'Platba začala (karta nebo dobití)',
    ];
    $paths = ['sales' => 'Prodejní cesta', 'customer' => 'Zákazník', 'owner' => 'Majitel tiskárny', 'designer' => 'Designér', 'order' => 'Objednávka (audit)'];
    $sources = ['google' => 'Google', 'seznam' => 'Seznam', 'bing' => 'Bing', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'designer' => 'Odkaz designéra', 'embed' => 'Vložená kalkulačka', 'direct' => 'Přímo', 'other' => 'Ostatní'];
@endphp

@section('content')
@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Statistiky">
    <a href="{{ route('admin.stats.funnel') }}" class="chip chip-on">Cesty návštěvníků</a>
    <a href="{{ route('admin.stats.search') }}" class="chip">Vyhledávání</a>
    <a href="{{ route('admin.ai.index') }}" class="chip">AI aktivita</a>
    <a href="{{ route('admin.stats.speed') }}" class="chip">Rychlost</a>
    <a href="{{ route('admin.stats.tools') }}" class="chip">Nástroje týdně</a>
    <a href=\"{{ route('admin.search_console.index') }}\" class=\"chip\">Search Console</a>
</nav>

<div class="mt-3 flex gap-1 text-sm">@foreach(\App\Domain\Stats\Funnel::PERIODS as $d)<a href="{{ $link(['days' => $d]) }}" class="chip {{ $stats['days'] === $d ? 'chip-on' : '' }}">{{ $d }} dní</a>@endforeach</div>

@include('admin.stats.overview')
{{-- "print again" on the order page and in My prints (docs/AL.md §15) --}}
<p class="mt-3 text-sm text-slate-600">Zakázky přes Opakovat tisk za {{ $stats['days'] }} dní: <strong>{{ $stats['repeats']['made'] }}</strong>, zaplacené {{ $stats['repeats']['paid'] }}.</p>

<div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
    <form method="get" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="days" value="{{ $stats['days'] }}">
        <select name="source" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5" aria-label="Zdroj">
            <option value="">všechny zdroje</option>@foreach($sources as $k => $v)<option value="{{ $k }}" @selected($source === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="locale" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5" aria-label="Jazyk">
            <option value="">všechny jazyky</option>@foreach(\App\Support\Locales::SUPPORTED as $l)<option value="{{ $l }}" @selected($locale === $l)>{{ $l }}</option>@endforeach
        </select>
        <select name="tool" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5" aria-label="Nástroj">
            <option value="">všechny nástroje</option>@foreach($tools as $t)<option value="{{ $t }}" @selected($tool === $t)>{{ __('tools.'.$t.'.title') }}</option>@endforeach
        </select>
        <noscript><button class="btn-quiet min-h-0 px-3 py-1.5">Filtrovat</button></noscript>
    </form>
    <span class="text-slate-600">Návštěvníků: <strong>{{ $stats['sessions'] }}</strong></span>
</div>
<p class="mt-1 text-xs text-slate-500">Z vlastních událostí webu (bez cookies třetích stran, i bez souhlasu), jen lidé. Krok počítá návštěvníky, kteří udělali jeho i všechny předchozí kroky. Stažení je plnohodnotný výsledek, ne mezikrok.</p>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach($paths as $key => $name)
        <section class="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">{{ $name }}</h2>
            @php $first = max(1, $stats['paths'][$key][0]['sessions']); @endphp
            <ol class="mt-3 space-y-2 text-sm">
                @foreach($stats['paths'][$key] as $step)
                    <li>
                        <div class="flex justify-between gap-2"><span>{{ $steps[$step['step']] ?? $step['step'] }}</span><span class="font-semibold">{{ $step['sessions'] }}@if($step['rate'] !== null) <span class="text-xs font-normal text-slate-500">{{ $step['rate'] }} %</span>@endif</span></div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $loop->last ? 'bg-ok' : 'bg-action' }}" style="width: {{ max($step['sessions'] > 0 ? 2 : 0, round(100 * $step['sessions'] / $first)) }}%"></div></div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>

@php
    $audit = $stats['audit'];
    $pct = fn (array $x) => $x['rate'] === null ? '–' : str_replace('.', ',', (string) $x['rate']).' %';
    $splitNames = [
        'device' => ['Podle zařízení', ['mobile' => 'Mobil', 'desktop' => 'Počítač', 'unknown' => 'Neznámé']],
        'visitor' => ['Nový nebo vracející se', ['new' => 'Nový', 'returning' => 'Vracející se']],
        'entry' => ['Podle vstupní cesty', ['file' => 'Soubor', 'photo' => 'Fotka', 'text' => 'Text', 'tool' => 'Nástroj', 'model' => 'Model ke stažení', 'none' => 'Nic z toho']],
    ];
@endphp
<h2 class="mt-6 font-bold">Měření podle auditu</h2>
<p class="mt-1 text-xs text-slate-500">Události „model hotový“, „viděl cenu“, „začal objednávku“, „šel zaplatit“ a chyby se zapisují od nasazení měření (docs/AJ.md); starší návštěvy je nemají. U každého podílu je velikost vzorku n: při 6 až 12 lidech denně jsou závěry o konverzi možné až po týdnech.</p>
<div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach(['price' => 'Návštěvníci s cenou', 'paid_of_started' => 'Zahájené objednávky zaplacené', 'download' => 'Úspěšná stažení', 'topup' => 'Dobití kreditu dokončená'] as $key => $label)
        <div class="rounded-2xl border border-slate-200 bg-white p-3"><div class="text-xs text-slate-500">{{ $label }}</div><div class="text-lg font-bold">{{ $pct($audit['shares'][$key]) }}</div><div class="text-xs text-slate-500">{{ $audit['shares'][$key]['k'] }} z n = {{ $audit['shares'][$key]['n'] }}</div></div>
    @endforeach
</div>
<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Chyby v kroku</th><th class="px-3 py-2 text-right">Zkusili (n)</th><th class="px-3 py-2 text-right">S chybou</th><th class="px-3 py-2 text-right">Podíl</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach(['upload' => 'Nahrání souboru (formát, velikost)', 'model' => 'Model (nahrání, nástroj, generování)', 'calculation' => 'Výpočet ceny', 'checkout' => 'Platba (karta, dobití, kredit)', 'download' => 'Stažení'] as $step => $label)
                <tr><td class="px-3 py-2 font-semibold">{{ $label }}</td><td class="px-3 py-2 text-right">{{ $audit['errors'][$step]['n'] }}</td><td class="px-3 py-2 text-right">{{ $audit['errors'][$step]['k'] }}</td><td class="px-3 py-2 text-right">{{ $pct($audit['errors'][$step]) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@foreach($splitNames as $by => [$title, $names])
    <h3 class="mt-4 text-sm font-bold">{{ $title }}</h3>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2"></th><th class="px-3 py-2 text-right">Návštěvníků (n)</th><th class="px-3 py-2 text-right">Model</th><th class="px-3 py-2 text-right">Cena</th><th class="px-3 py-2 text-right">Začali objednávku</th><th class="px-3 py-2 text-right">Zaplatili</th><th class="px-3 py-2 text-right">Stáhli</th><th class="px-3 py-2 text-right">S chybou</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($audit['splits'][$by] as $key => $row)
                    @continue($row['sessions'] === 0 && in_array($key, ['unknown', 'none'], true))
                    <tr><td class="px-3 py-2 font-semibold">{{ $names[$key] ?? $key }}</td>@foreach(['sessions', 'model', 'price', 'started', 'paid', 'download', 'failed'] as $col)<td class="px-3 py-2 text-right">{{ $row[$col] }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@foreach(['by_source' => ['Podle zdroje návštěvy', $sources], 'by_locale' => ['Podle jazyka', ['cs' => 'čeština', 'en' => 'angličtina', 'es' => 'španělština']]] as $key => [$title, $names])
    <h2 class="mt-6 font-bold">{{ $title }}</h2>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2"></th><th class="px-3 py-2 text-right">Návštěvníků</th><th class="px-3 py-2 text-right">Zaplacené tisky</th><th class="px-3 py-2 text-right">Stažení</th><th class="px-3 py-2 text-right">Registrace</th><th class="px-3 py-2 text-right">Soubory designérů</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($stats[$key] as $name => $row)
                    <tr><td class="px-3 py-2 font-semibold">{{ $names[$name] ?? $name }}</td><td class="px-3 py-2 text-right">{{ $row['sessions'] }}</td><td class="px-3 py-2 text-right">{{ $row['order_paid'] }}</td><td class="px-3 py-2 text-right">{{ $row['download'] }}</td><td class="px-3 py-2 text-right">{{ $row['register'] }}</td><td class="px-3 py-2 text-right">{{ $row['designer_file_uploaded'] }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">V tomhle období nikdo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endforeach

<h2 class="mt-6 font-bold">Nástroje</h2>
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Nástroj</th><th class="px-3 py-2 text-right">Návštěvy</th><th class="px-3 py-2 text-right">Výstupy</th><th class="px-3 py-2 text-right">Stažení</th><th class="px-3 py-2 text-right">Objednávky</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($stats['tools'] as $key => $row)
                <tr><td class="px-3 py-2 font-semibold"><a href="{{ $link(['tool' => $key]) }}" class="underline">{{ \Illuminate\Support\Facades\Lang::has('tools.'.$key.'.title') ? __('tools.'.$key.'.title') : $key }}</a></td>
                    <td class="px-3 py-2 text-right">{{ $row['visits'] }}</td><td class="px-3 py-2 text-right">{{ $row['outputs'] }}</td><td class="px-3 py-2 text-right">{{ $row['downloads'] }}</td><td class="px-3 py-2 text-right">{{ $row['orders'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">Žádný nástroj v tomhle období nikdo neotevřel.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<h2 class="mt-6 font-bold">Co přivedly odkazy designérů</h2>
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Designér</th><th class="px-3 py-2 text-right">Příchody přes odkaz</th><th class="px-3 py-2 text-right">Návštěvníků</th><th class="px-3 py-2 text-right">Zaplacené tisky</th><th class="px-3 py-2 text-right">Stažení</th><th class="px-3 py-2 text-right">Registrace</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($stats['designers'] as $d)
                <tr><td class="px-3 py-2 font-semibold"><a href="{{ route('designers.show', $d['slug']) }}" target="_blank" class="underline">{{ $d['name'] }}</a></td>
                    <td class="px-3 py-2 text-right">{{ $d['ref_visits'] }}</td><td class="px-3 py-2 text-right">{{ $d['sessions'] }}</td><td class="px-3 py-2 text-right">{{ $d['orders'] }}</td><td class="px-3 py-2 text-right">{{ $d['downloads'] }}</td><td class="px-3 py-2 text-right">{{ $d['registrations'] }}</td></tr>
            @empty
                <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">Přes odkaz designéra zatím nikdo nepřišel.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
