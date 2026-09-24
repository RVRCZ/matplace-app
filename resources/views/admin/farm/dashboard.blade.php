@extends('layouts.app', ['title' => __('farm.admin.nav.dashboard').' · admin', 'noindex' => true])

@php
    $dur = fn (int $m) => $m >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min';
    // the dryer gets its own worded line under the drying buttons, the rest of the telemetry stays a plain list
    $dryerKeys = ['dryer', 'dryer_state', 'dryer_temp', 'dryer_target', 'dryer_remain_min', 'dryer_rh'];
@endphp

{{-- with JavaScript the cards are redrawn in place every 30 s (bootFarmDashboard); the meta refresh is the fallback --}}
@push('head')<noscript><meta http-equiv="refresh" content="30"></noscript>@endpush

@section('content')
@include('admin.farm.nav')

<div id="farm-dashboard" data-refresh="{{ route('admin.farm.dashboard') }}" data-every="30" data-error="{{ __('farm.admin.request_failed') }}">
@if($attention->isNotEmpty())
    <section class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4">
        <h2 class="font-bold text-amber-900">{{ __('farm.admin.attention') }}</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach($attention as $o)
                <li><a class="underline" href="{{ route('admin.farm.orders.show', $o) }}">{{ $o->number ?? $o->token }}</a> · {{ __('farm.status.'.$o->status) }} · {{ $o->modelFile?->original_name }} · {{ $o->user?->email }}</li>
            @endforeach
        </ul>
    </section>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach($printers as $row)
        @php
            $p = $row['printer'];
            $state = $p->displayState();
            $busy = $p->isPrintingNow();
            $t = $p->telemetry ?? [];
        @endphp
        <section class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-bold">{{ $p->name }}</h2>
                    <p class="text-xs text-slate-500">{{ $p->model }} · {{ __('farm.admin.mode.'.$p->mode) }}@if($p->agent) · {{ $p->agent->name }}@endif · {{ $p->bedTypeLabel() }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ in_array($state, ['offline', 'error', 'unknown']) ? 'bg-red-100 text-red-800' : ($state === 'printing' ? 'bg-action-soft text-action-dark' : 'bg-ok-soft text-ok') }}">{{ __('farm.admin.state.'.$state) }}</span>
            </div>

            @if($t)
                <p class="mt-2 text-xs text-slate-600">@foreach(collect($t)->except($dryerKeys) as $k => $v){{ $k }}: <strong>{{ is_float($v) ? round($v, 1) : $v }}</strong>@if(! $loop->last) · @endif @endforeach</p>
            @endif
            @if($p->snapshot_path)
                <img src="{{ route('admin.farm.printers.snapshot', $p) }}?t={{ $p->snapshot_at?->timestamp }}" alt="{{ __('farm.order.camera') }}" class="mt-2 w-full rounded-xl border border-slate-200">
                <p class="text-xs text-slate-500">{{ $p->snapshot_at?->format('j. n. H:i:s') }}</p>
            @endif

            {{-- the one confirmation nothing starts without; while a print runs the plate is occupied by definition --}}
            <form method="post" action="{{ route('admin.farm.printers.bed', $p) }}" data-ajax class="mt-3 rounded-xl {{ $p->bed_clear ? 'bg-ok-soft' : 'bg-slate-100' }} p-3">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold">{{ $p->bed_clear ? '✓ '.__('farm.admin.bed_clear') : __('farm.admin.bed_busy') }}</span>
                    @if($p->bed_clear)
                        <button name="clear" value="0" class="btn-quiet text-sm">{{ __('farm.admin.bed_busy_do') }}</button>
                    @else
                        <button name="clear" value="1" class="btn-primary text-sm" @disabled($busy) @if($busy) title="{{ __('farm.admin.bed_locked_hint') }}" @endif>{{ __('farm.admin.bed_clear_do') }}</button>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-600">{{ $busy ? __('farm.admin.bed_locked_hint') : __('farm.admin.bed_note') }}</p>
            </form>
            @if($p->isAgentDriven())
                <form method="post" action="{{ route('admin.farm.printers.command', $p) }}" data-ajax class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    @csrf
                    <span class="text-slate-600">{{ __('farm.admin.light') }}:</span>
                    <button name="type" value="light_on" class="btn-quiet text-sm">{{ __('farm.admin.light_on') }}</button>
                    <button name="type" value="light_off" class="btn-quiet text-sm">{{ __('farm.admin.light_off') }}</button>
                </form>
                <form method="post" action="{{ route('admin.farm.printers.command', $p) }}" data-ajax class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    @csrf
                    <span class="text-slate-600">{{ __('farm.admin.dry') }}:</span>
                    <select name="dry_temp" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm">@foreach([45, 50, 55] as $dt)<option value="{{ $dt }}" @selected($dt === 50)>{{ $dt }} °C</option>@endforeach</select>
                    <select name="dry_hours" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm">@foreach([2, 4, 6, 8, 12] as $h)<option value="{{ $h }}" @selected($h === 6)>{{ $h }} h</option>@endforeach</select>
                    <button name="type" value="dry_on" class="btn-quiet text-sm">{{ __('farm.admin.dry_start') }}</button>
                    <button name="type" value="dry_off" class="btn-quiet text-sm">{{ __('farm.admin.dry_stop') }}</button>
                    @if(isset($t['dryer_state']))
                        @php
                            $off = $t['dryer_state'] === 'off';
                            $line = $off
                                ? __('farm.admin.dryer_off', ['temp' => round((float) ($t['dryer_temp'] ?? 0))])
                                : __('farm.admin.dryer_on', ['temp' => round((float) ($t['dryer_temp'] ?? 0)), 'target' => round((float) ($t['dryer_target'] ?? 0)), 'left' => $dur((int) ($t['dryer_remain_min'] ?? 0))]);
                            if ((float) ($t['dryer_rh'] ?? 0) > 0) {
                                $line .= ', '.__('farm.admin.dryer_rh', ['rh' => round((float) $t['dryer_rh'])]);
                            }
                        @endphp
                        <span class="basis-full text-xs {{ $off ? 'text-slate-500' : 'font-semibold text-action-dark' }}">{{ $line }}</span>
                    @elseif(isset($t['dryer']))
                        {{-- an agent from before the structured keys --}}
                        <span class="text-xs text-slate-500">{{ $t['dryer'] }}</span>
                    @endif
                </form>
            @endif

            @if($row['job'])
                @php $job = $row['job']; @endphp
                <div class="mt-3 rounded-xl border border-slate-200 p-3">
                    <div class="text-sm font-semibold">{{ __('farm.admin.running') }}: <a class="underline" href="{{ route('admin.farm.orders.show', $job->order) }}">{{ $job->order->number }}</a> · {{ $job->order->modelFile?->original_name }}</div>
                    <div class="mt-1 text-xs text-slate-600">{{ $job->status }} · {{ round($job->progress) }} % @if($job->message)· {{ $job->message }}@endif</div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-action" style="width: {{ min(100, $job->progress) }}%"></div></div>
                    @if($p->isAgentDriven())
                        <form method="post" action="{{ route('admin.farm.printers.command', $p) }}" data-ajax class="mt-2 flex flex-wrap gap-2">
                            @csrf
                            <button name="type" value="pause" class="btn-quiet text-sm">{{ __('farm.admin.pause') }}</button>
                            <button name="type" value="resume" class="btn-quiet text-sm">{{ __('farm.admin.resume') }}</button>
                            <button name="type" value="cancel" class="btn-quiet text-sm text-red-700" onclick="return confirm(@js(__('farm.admin.cancel_confirm')))">{{ __('farm.admin.cancel') }}</button>
                        </form>
                    @endif
                </div>
            @endif

            <h3 class="mt-4 text-sm font-bold">{{ __('farm.admin.queue') }}</h3>
            <ol class="mt-1 space-y-1 text-sm">
                @forelse($row['queue'] as $q)
                    <li class="flex flex-wrap justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                        <span><a class="underline" href="{{ route('admin.farm.orders.show', $q['order']) }}">{{ $q['order']->number }}</a> · {{ $q['order']->modelFile?->original_name }} · {{ $q['order']->color?->name }}</span>
                        @if($q['eta'])<span class="text-xs text-slate-600">{{ __('farm.admin.eta', ['start' => $dur($q['eta']['start_in']), 'finish' => $dur($q['eta']['finish_in'])]) }}</span>@endif
                    </li>
                @empty
                    <li class="text-slate-500">{{ __('farm.admin.queue_empty') }}</li>
                @endforelse
            </ol>

            <p class="mt-3 text-xs text-slate-500">
                @foreach($p->slots as $s)
                    <span class="mr-2 inline-flex items-center gap-1 {{ $s->enabled ? '' : 'opacity-40' }}"><span class="inline-block h-3 w-3 rounded-full border border-slate-300" style="background: {{ $s->color?->hex ?? '#fff' }}"></span>{{ $s->slot + 1 }}: {{ $s->color?->name ?? '—' }} ({{ round($s->remaining_g) }} g)</span>
                @endforeach
                <a href="{{ route('admin.farm.printers.edit', $p) }}" class="underline">{{ __('farm.admin.nav.printers') }}</a>
            </p>
        </section>
    @endforeach
</div>
</div>

{{-- the answer to a button: shows for a moment at the bottom, the page itself stays where it is --}}
<div id="farm-toast" class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 opacity-0 transition-opacity duration-300" role="status" aria-live="polite"><span class="max-w-full rounded-xl bg-slate-900 px-4 py-2 text-sm text-white shadow-lg"></span></div>
@endsection
