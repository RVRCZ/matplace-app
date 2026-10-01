@extends('layouts.app', ['title' => 'AI aktivita · admin', 'noindex' => true])

@php
    $kinds = ['translate' => 'Překlad popisů', 'classify' => 'Kategorie modelů', 'describe' => 'Popis z obrázku', 'text' => 'Přepis popisu', 'social' => 'Text příspěvku', 'email' => 'Návrh e-mailu', 'collections' => 'Návrhy kolekcí',
        'generate' => 'Generování 3D modelu', 'moderate' => 'Kontrola nahrané fotky', 'advise' => 'Rada k tisku', 'inspect' => 'Hodnocení testovacího tisku'];
    $czk = fn (float $v) => \App\Support\Money::czk($v)->format('cs');
@endphp

@section('content')
@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Statistiky">
    <a href="{{ route('admin.stats.funnel') }}" class="chip">Cesty návštěvníků</a>
    <a href="{{ route('admin.stats.search') }}" class="chip">Vyhledávání</a>
    <a href="{{ route('admin.ai.index') }}" class="chip chip-on">AI aktivita</a>
</nav>

<div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
    @foreach([7, 30, 90] as $d)<a href="{{ route('admin.ai.index', ['days' => $d]) }}" class="chip {{ $stats['days'] === $d ? 'chip-on' : '' }}">{{ $d }} dní</a>@endforeach
</div>

<dl class="mt-3 grid gap-3 sm:grid-cols-4">
    <div class="card p-4"><dt class="text-xs font-semibold uppercase text-muted">Náklady za období</dt><dd class="mt-1 text-2xl font-extrabold">{{ $czk($stats['total']['cost']) }}</dd><dd class="text-xs text-muted">{{ $stats['total']['calls'] }} volání</dd></div>
    <div class="card p-4"><dt class="text-xs font-semibold uppercase text-muted">Na návštěvníka</dt><dd class="mt-1 text-2xl font-extrabold">{{ $stats['per_visitor'] !== null ? $czk($stats['per_visitor']) : '—' }}</dd><dd class="text-xs text-muted">{{ $stats['visitors'] }} návštěvníků</dd></div>
    <div class="card p-4"><dt class="text-xs font-semibold uppercase text-muted">Na zaplacenou objednávku</dt><dd class="mt-1 text-2xl font-extrabold">{{ $stats['per_order'] !== null ? $czk($stats['per_order']) : '—' }}</dd><dd class="text-xs text-muted">{{ $stats['orders'] }} objednávek</dd></div>
    <div class="card p-4"><dt class="text-xs font-semibold uppercase text-muted">Tokeny</dt><dd class="mt-1 text-lg font-extrabold">{{ number_format($stats['total']['tokens_in'], 0, ',', ' ') }} dovnitř</dd><dd class="text-xs text-muted">{{ number_format($stats['total']['tokens_out'], 0, ',', ' ') }} ven</dd></div>
</dl>
<p class="mt-1 text-xs text-slate-500">Ceny podle ceníku v <code>config/ai.php</code> (USD za milion tokenů nebo za volání, kurz <code>AI_USD_CZK</code>). Každé volání AI se zapisuje samo přes <code>AiUsage::record()</code>.</p>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <section>
        <h2 class="font-bold">Podle druhu práce</h2>
        <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Druh</th><th class="px-3 py-2 text-right">Volání</th><th class="px-3 py-2 text-right">Cena</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($stats['by_kind'] as $row)
                        <tr><td class="px-3 py-2"><span class="font-semibold">{{ $kinds[$row['kind']] ?? $row['kind'] }}</span><span class="block text-xs text-slate-500">{{ implode(', ', $row['engines']) }}</span></td><td class="px-3 py-2 text-right">{{ $row['calls'] }}</td><td class="px-3 py-2 text-right font-semibold">{{ $czk($row['cost']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">V tomhle období žádné volání.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section>
        <h2 class="font-bold">Po měsících</h2>
        <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Měsíc</th><th class="px-3 py-2 text-right">Volání</th><th class="px-3 py-2 text-right">Cena</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($stats['by_month'] as $row)
                        <tr><td class="px-3 py-2">{{ \App\Domain\Stats\AiActivity::monthName($row['month']) }}</td><td class="px-3 py-2 text-right">{{ $row['calls'] }}</td><td class="px-3 py-2 text-right font-semibold">{{ $czk($row['cost']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">Zatím nic.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<h2 class="mt-6 font-bold">Po dnech</h2>
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Den</th><th class="px-3 py-2 text-right">Volání</th><th class="px-3 py-2 text-right">Tokeny dovnitř</th><th class="px-3 py-2 text-right">Tokeny ven</th><th class="px-3 py-2 text-right">Cena</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($stats['by_day'] as $row)
                <tr><td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($row['day'])->format('j. n. Y') }}</td><td class="px-3 py-2 text-right">{{ $row['calls'] }}</td><td class="px-3 py-2 text-right">{{ number_format($row['tokens_in'], 0, ',', ' ') }}</td><td class="px-3 py-2 text-right">{{ number_format($row['tokens_out'], 0, ',', ' ') }}</td><td class="px-3 py-2 text-right font-semibold">{{ $czk($row['cost']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">V tomhle období žádné volání.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
