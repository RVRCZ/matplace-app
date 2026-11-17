{{-- Partners (docs/AT.md §D): numbers per partner, the commissions waiting for a payout, add / change / switch off. The operator reads Czech. --}}
@extends('layouts.app', ['title' => 'Partneři · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $stateText = ['pending' => 'čeká (zakázka se ještě netiskla)', 'payable' => 'k výplatě', 'paid' => 'vyplaceno', 'void' => 'zrušeno'];
@endphp

@section('content')
@include('admin.farm.nav')
@include('partials.flash')

<div class="mt-4 flex flex-wrap items-baseline justify-between gap-2">
    <h1 class="text-xl font-bold">Partneři</h1>
    <a href="{{ route('admin.promo-codes') }}" class="text-sm text-action-dark underline">Slevové kódy →</a>
</div>
@unless($enabled)
    <p class="mt-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Partnerský program je <strong>vypnutý</strong> (<a href="{{ route('admin.farm.settings') }}#farm-affiliate" class="underline">nastavení farmy</a>): odkaz /r/KÓD jen přesměruje, veřejná stránka neexistuje a nové provize nevznikají. Tabulky níže ukazují, co už vzniklo.</p>
@endunless
<p class="mt-2 text-xs text-slate-600">Provize se počítá ze zaplacené zakázky bez DPH (tisk po slevě a doprava, bez odměn návrhářů) v procentu partnera v okamžiku zaplacení. Je „k výplatě“, až se zakázka dotiskne. Systém nic nevyplácí: zaplatíte sami a tady to označíte.</p>

<div class="mt-4 space-y-4">
    @foreach($partners as $p)
        @php
            $st = $stats[$p->id];
            $open = $commissions[$p->id] ?? collect();
        @endphp
        <section class="rounded-2xl border {{ $p->active ? 'border-slate-200' : 'border-slate-300 bg-slate-50' }} bg-white p-4" data-partner="{{ $p->code }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-bold">{{ $p->name }} <span class="font-mono text-sm font-normal text-slate-500">{{ $p->code }}</span> @unless($p->active)<span class="rounded bg-slate-200 px-2 text-xs font-semibold">vypnutý</span>@endunless</h2>
                <span class="text-xs text-slate-500">{{ $p->email }}@if($p->user) · účet #{{ $p->user_id }}@endif · <a href="{{ $p->link() }}" class="underline">{{ $p->link() }}</a></span>
            </div>
            <dl class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4 lg:grid-cols-8">
                @foreach([['Kliky', $st['clicks']], ['Zakázky', $st['orders']], ['Zaplacené', $st['paid_orders']], ['Obrat bez DPH', $money($st['turnover'])], ['Čeká', $money($st['pending'])], ['K výplatě', $money($st['payable'])], ['Vyplaceno', $money($st['paid'])]] as [$label, $value])
                    <div class="rounded-lg bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                @endforeach
            </dl>
            {{-- clicks by day, 30 days: a bar per day --}}
            @php($max = max(1, max($st['clicks_by_day'] ?: [0])))
            <div class="mt-3 flex h-10 items-end gap-px" aria-label="Kliky po dnech, 30 dní" title="Kliky po dnech, posledních 30 dní">
                @foreach($st['clicks_by_day'] as $day => $n)
                    <span class="w-full rounded-sm bg-action/70" style="height: {{ $n > 0 ? max(8, (int) round($n / $max * 100)) : 2 }}%" title="{{ $day }}: {{ $n }}"></span>
                @endforeach
            </div>

            @if($open->isNotEmpty())
                <form method="post" action="{{ route('admin.affiliates.paid', $p) }}" class="mt-3 rounded-xl border border-slate-200 p-3 text-sm">
                    @csrf
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-500"><tr><th></th><th>Zakázka</th><th>Základ</th><th>%</th><th>Provize</th><th>Stav</th></tr></thead>
                        <tbody>
                        @foreach($open as $c)
                            <tr class="border-t border-slate-100">
                                <td>@if($c->state === 'payable')<input type="checkbox" name="ids[]" value="{{ $c->id }}" class="h-4 w-4 accent-action">@endif</td>
                                <td><a href="{{ route('admin.farm.orders.show', $c->farm_order_id) }}" class="underline">{{ $c->order?->number ?: '#'.$c->farm_order_id }}</a></td>
                                <td>@money($c->base_amount, $c->currency)</td><td>{{ rtrim(rtrim(number_format($c->percent, 2, ',', ''), '0'), ',') }}</td>
                                <td class="font-semibold">@money($c->amount, $c->currency)</td><td>{{ $stateText[$c->state] ?? $c->state }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="mt-2 flex flex-wrap items-end gap-3">
                        <label class="{{ $lb }} grow">Poznámka k výplatě (povinná, např. „převod 20. 11.“)<input name="note" required maxlength="300" class="{{ $in }}"></label>
                        <button name="all" value="1" class="btn-primary min-h-0 px-3 py-2 text-sm" onclick="return confirm('Označit všechny provize k výplatě tohoto partnera jako vyplacené?')">Vše k výplatě je vyplaceno</button>
                        <button class="btn-quiet min-h-0 px-3 py-2 text-sm">Jen zaškrtnuté</button>
                    </div>
                </form>
            @endif

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold text-action-dark">Upravit partnera</summary>
                <form method="post" action="{{ route('admin.affiliates.update', $p) }}" class="mt-2 grid gap-3 sm:grid-cols-3">
                    @csrf
                    @include('admin.affiliates._partner_fields', ['p' => $p])
                    <div class="sm:col-span-3 flex justify-end"><button class="btn-quiet text-sm">Uložit</button></div>
                </form>
            </details>
        </section>
    @endforeach

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">+ Nový partner</h2>
        <p class="text-xs text-slate-500">Registrovat se mohou partneři i sami na stránce programu. Výchozí: {{ rtrim(rtrim(number_format($defaults['percent'], 2, ',', ''), '0'), ',') }} % a {{ $defaults['days'] }} dní.</p>
        <form method="post" action="{{ route('admin.affiliates.create') }}" class="mt-2 grid gap-3 sm:grid-cols-3">
            @csrf
            @include('admin.affiliates._partner_fields', ['p' => new \App\Models\Partner(['commission_percent' => $defaults['percent'], 'cookie_days' => $defaults['days'], 'active' => true])])
            <div class="sm:col-span-3 flex justify-end"><button class="btn-primary text-sm">Přidat</button></div>
        </form>
    </section>
</div>
@endsection
