@extends('layouts.app', ['title' => 'Facebook a Instagram · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<h1 class="mt-2 text-xl font-extrabold">Facebook a Instagram</h1>
@unless($available)
    <p class="note-warn mt-2 text-sm">Meta není připojená: v <code>.env</code> chybí <code>META_SYSTEM_TOKEN</code> a <code>META_PAGE_ID</code> (pro Instagram i <code>META_IG_ID</code>). Text příspěvku si připravíte i tak, zveřejnit ho nepůjde.</p>
@endunless

<h2 class="mt-5 font-bold">Nový příspěvek o modelu</h2>
<p class="text-sm text-slate-600">Vyberte model, který farma tiskne. AI navrhne text, vy ho v náhledu upravíte a teprve pak se zveřejní.</p>
<div class="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
    @forelse($cards as $card)
        <a href="{{ route('admin.meta.compose', ['type' => 'designer_model', 'id' => $card->id]) }}" class="card flex items-center gap-2 p-2 text-sm hover:border-action">
            @if($card->coverUrl())<img src="{{ $card->coverUrl() }}" alt="" loading="lazy" class="h-12 w-14 rounded object-cover">@endif
            <span class="min-w-0 truncate font-semibold">{{ $card->title }}</span>
        </a>
    @empty
        <p class="text-sm text-slate-500">Žádný tisknutelný model.</p>
    @endforelse
</div>
@if($collections->isNotEmpty())
    <h2 class="mt-5 font-bold">Nebo o kolekci</h2>
    <div class="mt-2 flex flex-wrap gap-2">
        @foreach($collections as $c)<a href="{{ route('admin.meta.compose', ['type' => 'collection', 'id' => $c->id]) }}" class="chip">{{ $c->title['cs'] ?? $c->slug }}</a>@endforeach
    </div>
@endif

<h2 class="mt-6 font-bold">Zveřejněné příspěvky</h2>
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Kdy</th><th class="px-3 py-2">Kde</th><th class="px-3 py-2">Text</th><th class="px-3 py-2">Výsledek</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($posts as $p)
                <tr><td class="px-3 py-2 text-xs text-slate-500">{{ $p->created_at->format('j. n. Y H:i') }}</td><td class="px-3 py-2">{{ $p->platform }}</td><td class="max-w-md truncate px-3 py-2" title="{{ $p->text }}">{{ $p->text }}</td>
                    <td class="px-3 py-2">@if($p->status === 'posted')<span class="text-ok">zveřejněno</span>@elseif($p->status === 'failed')<span class="text-red-700" title="{{ $p->error }}">selhalo: {{ \Illuminate\Support\Str::limit($p->error, 60) }}</span>@else koncept @endif</td></tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Zatím nic.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<h2 class="mt-6 font-bold">Reklamy <span class="text-sm font-normal text-slate-500">jen přehled, správa zůstává v Meta Ads</span></h2>
<div class="mt-2 flex gap-2 text-sm">
    <a href="{{ route('admin.meta.index', ['period' => 'last_7d']) }}" class="chip {{ $period === 'last_7d' ? 'chip-on' : '' }}">7 dní</a>
    <a href="{{ route('admin.meta.index', ['period' => 'last_30d']) }}" class="chip {{ $period === 'last_30d' ? 'chip-on' : '' }}">30 dní</a>
</div>
@if($adsError)
    <p class="note-warn mt-2 text-sm">Přehled reklam se nepodařilo načíst: {{ $adsError }}</p>
@elseif(! $campaigns)
    <p class="mt-2 text-sm text-slate-500">Reklamní účet není nastavený (<code>META_AD_ACCOUNT_ID</code>) nebo nemá kampaně.</p>
@else
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Kampaň</th><th class="px-3 py-2">Stav</th><th class="px-3 py-2 text-right">Útrata</th><th class="px-3 py-2 text-right">Zobrazení</th><th class="px-3 py-2 text-right">Kliky</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($campaigns as $c)
                    <tr><td class="px-3 py-2 font-semibold">{{ $c['name'] }}</td><td class="px-3 py-2">{{ $c['status'] }}</td>
                        <td class="px-3 py-2 text-right">@if(in_array($c['currency'], ['CZK', 'EUR'], true))@money($c['spend'], $c['currency'])@else{{ number_format($c['spend'], 2, ',', ' ') }} {{ $c['currency'] }}@endif</td>
                        <td class="px-3 py-2 text-right">{{ number_format($c['impressions'], 0, ',', ' ') }}</td><td class="px-3 py-2 text-right">{{ number_format($c['clicks'], 0, ',', ' ') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
