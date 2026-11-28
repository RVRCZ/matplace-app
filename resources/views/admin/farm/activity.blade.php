{{-- "Aktivita na webu" (Roman, 28 Nov 2026): what happened on the web, newest first – models, the way to an order,
     the farm's prints, the router's requests (App\Domain\Stats\ActivityStream). With JavaScript the list redraws in
     place every 20 s (bootAdminActivity) and marks the lines that came since; the meta refresh is the fallback. --}}
@extends('layouts.app', ['title' => 'Aktivita na webu · admin', 'noindex' => true])

@php
    $link = fn (array $change) => route('admin.farm.activity', array_filter(array_merge($query, $change), fn ($v) => $v !== null && $v !== false));
    $tones = [
        'ok' => 'bg-emerald-100 text-emerald-900',
        'warn' => 'bg-amber-100 text-amber-900',
        'bad' => 'bg-red-100 text-red-900',
        'info' => 'bg-sky-100 text-sky-900',
        'muted' => 'bg-slate-100 text-slate-600',
    ];
    $groupNames = \App\Domain\Stats\ActivityStream::GROUPS;
    $windowNames = ['1h' => 'hodina', '6h' => '6 hodin', '24h' => '24 hodin', '3d' => '3 dny', '7d' => '7 dní'];
    $day = null;
@endphp

@push('head')<noscript><meta http-equiv="refresh" content="60"></noscript>@endpush

@section('content')
@include('admin.farm.nav')

<div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
    <a href="{{ $link(['group' => null]) }}" class="chip {{ $group === null ? 'chip-on' : '' }}">Vše</a>
    @foreach($groupNames as $key => $name)
        <a href="{{ $link(['group' => $key]) }}" class="chip {{ $group === $key ? 'chip-on' : '' }}">{{ $name }}</a>
    @endforeach
    @unless($session)
        <span class="mx-1 text-slate-300" aria-hidden="true">|</span>
        @foreach($windowNames as $key => $name)
            <a href="{{ $link(['window' => $key === '24h' ? null : $key]) }}" class="chip {{ $window === $key ? 'chip-on' : '' }}">{{ $name }}</a>
        @endforeach
        <span class="mx-1 text-slate-300" aria-hidden="true">|</span>
        <a href="{{ $link(['detail' => $detailed ? null : 1]) }}" class="chip {{ $detailed ? 'chip-on' : '' }}" title="Ukáže i drobné kroky: posun stránky, náhledy, příchody, spam">Podrobně</a>
    @endunless
</div>

@if($session)
    <p class="mt-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-950">
        Cesta návštěvníka <strong>host #{{ $session }}</strong> za posledních 30 dní, všechny kroky.
        <a class="underline" href="{{ route('admin.farm.activity', array_filter(['group' => $group])) }}">Zpět na celou aktivitu</a>
    </p>
@endif

<div id="admin-activity" class="mt-4" data-refresh="{{ $link([]) }}" data-every="20">
    <p class="mb-2 text-xs text-slate-500">
        {{ count($lines) }} {{ count($lines) === 1 ? 'záznam' : (count($lines) >= 2 && count($lines) <= 4 ? 'záznamy' : 'záznamů') }}@if(count($lines) >= \App\Domain\Stats\ActivityStream::LIMIT) (nejnovějších {{ \App\Domain\Stats\ActivityStream::LIMIT }})@endif
        · obnoveno {{ now()->format('H:i:s') }} · bez robotů, bez vás a dalších adminů
    </p>
    @if($lines === [])
        <p class="rounded-2xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Za zvolenou dobu se na webu nic nestalo.</p>
    @else
        <ol class="overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm">
            @foreach($lines as $l)
                @php $d = $l['at']->format('Y-m-d'); @endphp
                @if($d !== $day)
                    @php $day = $d; @endphp
                    <li class="border-b border-slate-200 bg-slate-50 px-4 py-1 text-xs font-semibold text-slate-600">{{ $l['at']->isToday() ? 'Dnes' : ($l['at']->isYesterday() ? 'Včera' : $l['at']->format('j. n. Y')) }}</li>
                @endif
                <li data-line="{{ $l['key'] }}" class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-slate-100 px-4 py-2 last:border-b-0">
                    <time class="w-12 shrink-0 tabular-nums text-slate-500" datetime="{{ $l['at']->toIso8601String() }}" title="{{ $l['at']->format('j. n. Y H:i:s') }}">{{ $l['at']->format('H:i') }}</time>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tones[$l['tone']] ?? $tones['muted'] }}">{{ $l['label'] }}</span>
                    @if($l['order'])
                        <a class="font-medium underline" href="{{ route('admin.farm.orders.show', $l['order']) }}">{{ $l['order']->number ?? 'zakázka #'.$l['order']->id }}</a>
                    @endif
                    @if($l['detail'] !== [])
                        <span class="min-w-0 break-words text-slate-700">{{ implode(' · ', $l['detail']) }}</span>
                    @endif
                    @if($l['model'])
                        {{-- the very model the visitor saw: the tool's page by the generation's token (opens in a new tab) --}}
                        <a class="text-xs font-semibold text-sky-800 underline" href="{{ $l['model'] }}" target="_blank" rel="noopener">otevřít model ↗</a>
                    @endif
                    @if($l['intake'])
                        <a class="text-xs underline" href="{{ route('admin.intake.index') }}">rozcestník</a>
                    @endif
                    <span class="ml-auto flex flex-wrap items-baseline gap-x-2 text-xs text-slate-500">
                        @if($l['source'])<span class="rounded bg-violet-50 px-1.5 py-0.5 text-violet-800">{{ $l['source'] }}</span>@endif
                        @if($l['user'])
                            <a class="underline" href="{{ route('admin.users.show', $l['user']) }}">{{ $l['who'] }}</a>
                        @else
                            <span>{{ $l['who'] }}</span>
                        @endif
                        @if($l['session'] && $l['session'] !== $session)
                            <a class="underline" href="{{ route('admin.farm.activity', array_filter(['session' => $l['session']])) }}" title="Všechny kroky tohoto návštěvníka">cesta →</a>
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
@endsection
