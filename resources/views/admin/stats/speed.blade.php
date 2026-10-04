@extends('layouts.app', ['title' => 'Rychlost · admin', 'noindex' => true])

@php
    $titles = [
        'files' => 'Soubory (nahrání → připraven)',
        'calculations' => 'Kalkulace (přesný výpočet)',
        'farm_first' => 'Zakázky farmy, první slicování (nahrání → nacenění)',
        'farm_reslice' => 'Zakázky farmy, nové slicování (klik → nacenění)',
    ];
    $steps = [
        'wait' => 'čekání od nahrání / kliku', 'queue' => 've frontě', 'convert' => 'převod na STL', 'analyse' => 'analýza sítě',
        'prepare' => 'oprava + natočení', 'layout' => 'rozložení kopií', 'mesh' => 'přepis STL (měřítko, umístění)',
        'slice1' => 'slicování', 'slice2' => 'druhé slicování (s podpěrami)', 'parse' => 'čtení G-code', 'rest_slice' => 'slicování posledního plátu',
        'post' => 'úpravy G-code', 'price' => 'cena', 'total' => 'práce celkem', 'to_sliced' => 'zákazník čekal',
    ];
@endphp

@section('content')
@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Statistiky">
    <a href="{{ route('admin.stats.funnel') }}" class="chip">Cesty návštěvníků</a>
    <a href="{{ route('admin.stats.search') }}" class="chip">Vyhledávání</a>
    <a href="{{ route('admin.ai.index') }}" class="chip">AI aktivita</a>
    <a href="{{ route('admin.stats.speed') }}" class="chip chip-on">Rychlost</a>
</nav>

<div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
    <span class="flex gap-1">@foreach([1, 7, 30] as $d)<a href="{{ route('admin.stats.speed', ['days' => $d]) }}" class="chip {{ $stats['days'] === $d ? 'chip-on' : '' }}">{{ $d }} {{ $d === 1 ? 'den' : 'dní' }}</a>@endforeach</span>
    <span class="text-slate-600">Dvojí slicování: <strong>{{ $stats['double_slices'] }}</strong> z {{ $stats['slices'] }}</span>
    <span class="text-slate-600">Z cache: kalkulace <strong>{{ $stats['calc_cache_hits'] }}</strong>, slicování <strong>{{ $stats['slice_cache_hits'] }}</strong>, příprava farmy <strong>{{ $stats['prepare_cached'] }}</strong> z {{ $stats['prepared'] }}</span>
    <span class="text-slate-600">Ve frontě přes 5 s: <strong>{{ $stats['queued_over_5s'] }}</strong>, nejvíc úloh najednou: <strong>{{ $stats['peak'] }}</strong></span>
</div>
<p class="mt-1 text-xs text-slate-500">Sekundy; p50 = medián, p90 = 9 z 10 bylo rychlejších. Totéž v konzoli: <code>php artisan matplace:perf-report --days=7</code>.</p>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach($stats['groups'] as $key => $group)
        <section>
            <h2 class="font-bold">{{ $titles[$key] }} <span class="font-normal text-slate-500">– {{ $group['n'] }}×, selhalo {{ $group['failed'] }}</span></h2>
            <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Krok</th><th class="px-3 py-2 text-right">Počet</th><th class="px-3 py-2 text-right">p50</th><th class="px-3 py-2 text-right">p90</th><th class="px-3 py-2 text-right">max</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($group['phases'] as $phase => $v)
                            <tr @class(['font-semibold' => in_array($phase, ['total', 'to_sliced'], true)])><td class="px-3 py-2">{{ $steps[$phase] ?? $phase }}</td><td class="px-3 py-2 text-right">{{ $v['n'] }}</td><td class="px-3 py-2 text-right">{{ $v['p50'] }}</td><td class="px-3 py-2 text-right">{{ $v['p90'] }}</td><td class="px-3 py-2 text-right">{{ $v['max'] }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">Zatím nic změřeno.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>

@if($stats['slice1_errors'])
    <section class="mt-4">
        <h2 class="font-bold">Proč slicování bez podpěr neprošlo (posledních 5)</h2>
        <ul class="mt-2 space-y-1 text-xs text-slate-600">
            @foreach($stats['slice1_errors'] as $e)<li class="rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono">{{ $e }}</li>@endforeach
        </ul>
    </section>
@endif
@endsection
