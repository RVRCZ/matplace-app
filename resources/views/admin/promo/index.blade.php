{{-- Discount codes (docs/AT.md §D). The operator reads Czech. --}}
@extends('layouts.app', ['title' => 'Slevové kódy · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
@endphp

@section('content')
@include('admin.farm.nav')
@include('partials.flash')

<div class="mt-4 flex flex-wrap items-baseline justify-between gap-2">
    <h1 class="text-xl font-bold">Slevové kódy</h1>
    <a href="{{ route('admin.affiliates') }}" class="text-sm text-action-dark underline">Partneři →</a>
</div>
@unless($enabled)
    <p class="mt-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Slevové kódy jsou <strong>vypnuté</strong> (<a href="{{ route('admin.farm.settings') }}#farm-affiliate" class="underline">nastavení farmy</a>): na stránce zakázky není pole a žádná cena se nemění.</p>
@endunless
<p class="mt-2 text-xs text-slate-600">Sleva se bere z ceny tisku (s DPH), ne z dopravy a ne z odměny návrháře. Procento nechá cenu zaokrouhlenou nahoru na celé koruny (v eurech na 10 centů); pevná částka je v korunách a v eurech se přepočítá jako ceny. Použití se počítá při zaplacení a vrací se, když zakázka selže nebo se vrátí celá.</p>

<div class="mt-4 space-y-3">
    @foreach($codes as $c)
        <section class="rounded-2xl border {{ $c->active ? 'border-slate-200 bg-white' : 'border-slate-300 bg-slate-50' }} p-4" data-code="{{ $c->code }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-bold"><span class="font-mono">{{ $c->code }}</span> · {{ $c->kind === 'percent' ? rtrim(rtrim(number_format($c->value, 2, ',', ''), '0'), ',').' %' : number_format($c->value, 0, ',', ' ').' Kč' }}
                    @unless($c->active)<span class="rounded bg-slate-200 px-2 text-xs font-semibold">vypnutý</span>@endunless
                    @if($c->partner)<span class="text-xs font-normal text-slate-500">partner {{ $c->partner->name }} ({{ $c->partner->code }})</span>@endif</h2>
                <span class="text-sm">použito <strong>{{ $c->uses }}</strong>@if($c->max_uses !== null) z {{ $c->max_uses }}@endif</span>
            </div>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm font-semibold text-action-dark">Upravit</summary>
                <form method="post" action="{{ route('admin.promo-codes.update', $c) }}" class="mt-2 grid gap-3 sm:grid-cols-4">
                    @csrf
                    @include('admin.promo._fields', ['c' => $c])
                    <div class="sm:col-span-4 flex justify-end"><button class="btn-quiet text-sm">Uložit</button></div>
                </form>
            </details>
        </section>
    @endforeach

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">+ Nový kód</h2>
        <form method="post" action="{{ route('admin.promo-codes.create') }}" class="mt-2 grid gap-3 sm:grid-cols-4">
            @csrf
            @include('admin.promo._fields', ['c' => new \App\Models\PromoCode(['kind' => 'percent', 'active' => true, 'once_per_account' => true, 'min_order_czk' => 0])])
            <div class="sm:col-span-4 flex justify-end"><button class="btn-primary text-sm">Přidat</button></div>
        </form>
    </section>
</div>
@endsection
