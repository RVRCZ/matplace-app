{{-- "Tiskárny a fronta" as a short list (docs/AQ.md): printers by farm, one row each – status, the plate button, the
     order that prints (or the last one printed), the next one waiting, the way to the printer's detail. Everything else
     about a printer lives on its detail (printer.blade.php). --}}
@extends('layouts.app', ['title' => __('farm.admin.nav.dashboard').' · admin', 'noindex' => true])

@php
    $when = fn ($t) => $t->isToday() ? $t->format('H:i') : $t->format('j. n. H:i');
@endphp

{{-- with JavaScript the list is redrawn in place every 30 s (bootFarmDashboard); the meta refresh is the fallback --}}
@push('head')<noscript><meta http-equiv="refresh" content="30"></noscript>@endpush

@section('content')
@include('admin.farm.nav')

<div id="farm-dashboard" data-refresh="{{ route('admin.farm.dashboard') }}" data-every="30" data-error="{{ __('farm.admin.request_failed') }}">
@if($attention->isNotEmpty())
    <section class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-bold text-amber-900">{{ __('farm.admin.attention') }}</h2>
            {{-- clears the list only: the orders keep their state and nobody is mailed; an order comes back when something changes on it --}}
            <form method="post" action="{{ route('admin.farm.attention.clear') }}" onsubmit="return confirm(@js(__('farm.admin.attention_clear_confirm', ['n' => $attention->count()])))">@csrf<button class="btn-quiet text-xs">{{ __('farm.admin.attention_clear') }}</button></form>
        </div>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach($attention as $o)
                <li class="flex items-center gap-2"><span><a class="underline" href="{{ route('admin.farm.orders.show', $o) }}">{{ $o->number ?? $o->token }}</a> · {{ __('farm.status.'.$o->status) }} · {{ $o->modelFile?->original_name }} · {{ $o->user?->email }}</span>
                    <form method="post" action="{{ route('admin.farm.attention.clear.one', $o) }}" class="inline">@csrf<button class="text-xs text-slate-500 hover:text-slate-800" title="{{ __('farm.admin.attention_clear_one') }}" aria-label="{{ __('farm.admin.attention_clear_one') }}">×</button></form></li>
            @endforeach
        </ul>
    </section>
@endif

@forelse($groups as $farmId => $rows)
    @php $farm = $rows->first()['printer']->farm; @endphp
    <section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white" data-farm-group="{{ $farm?->code ?? '' }}">
        <header class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 border-b border-slate-200 bg-slate-50 px-4 py-2">
            <h2 class="font-bold">{{ $farm ? $farm->label() : __('farm.admin.farms.unassigned_group') }}</h2>
            <p class="text-xs text-slate-500">@if($farm && trim((string) $farm->street) !== '') {{ $farm->addressLine() }} · @endif {{ __('farm.admin.ov.printers', ['n' => $rows->count()]) }} @if(! $farm) · <a class="underline" href="{{ route('admin.farm.printers') }}">{{ __('farm.admin.farms.assign_hint') }}</a> @endif</p>
        </header>
        <ul class="divide-y divide-slate-100">
            @foreach($rows as $row)
                @php
                    $p = $row['printer'];
                    $st = $row['status'];
                    $job = $row['job'];
                    $next = $row['waiting']->first();
                    $more = $row['waiting']->count() - 1;
                    // paid orders that wait for the admin's approval, not for their turn
                    $approval = $row['waiting']->where('status', \App\Models\FarmOrder::STATUS_PAID)->count();
                    // nobody can vouch for the plate of a machine that is switched off or silent: the button is on its detail
                    $away = in_array($st['key'], [\App\Domain\Farm\PrinterStatus::OFFLINE, \App\Domain\Farm\PrinterStatus::DISABLED], true);
                @endphp
                <li class="grid gap-x-4 gap-y-2 px-4 py-3 lg:grid-cols-[minmax(0,17rem)_minmax(0,1.2fr)_minmax(0,1fr)_12.5rem_auto] lg:items-center" data-printer-row="{{ $p->id }}" data-status="{{ $st['key'] }}">
                    <div class="flex min-w-0 items-start justify-between gap-2">
                        <a href="{{ route('admin.farm.printers.show', $p) }}" class="min-w-0">
                            <span class="block truncate font-bold"><span class="mr-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-sm">{{ $p->shortCode() }}</span>{{ $p->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $p->model }} @if($p->agent) · {{ $p->agent->name }} @endif</span>
                        </a>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $st['tone'] }}" data-status-pill>{{ $st['label'] }}</span>
                    </div>

                    {{-- what prints now, or what was printed last --}}
                    <div class="min-w-0 text-sm">
                        @if($row['running'])
                            <p class="break-words"><span class="text-slate-500">{{ __('farm.admin.ov.running') }}:</span> <a class="font-semibold underline" href="{{ route('admin.farm.orders.show', $row['running']) }}">{{ $row['running']->number }}</a>
                                @if($job) · <strong>{{ round($job->progress) }} %</strong> @endif
                                @if($row['finish_at']) · {{ __('farm.admin.ov.done_at', ['time' => $when($row['finish_at'])]) }} @endif
                            </p>
                            @if($job)
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-action" style="width: {{ min(100, $job->progress) }}%"></div></div>
                            @endif
                        @elseif($row['last'])
                            <p class="break-words"><span class="text-slate-500">{{ __('farm.admin.ov.last') }}:</span> <a class="font-semibold underline" href="{{ route('admin.farm.orders.show', $row['last']) }}">{{ $row['last']->number }}</a> · {{ __('farm.admin.ov.finished_at', ['time' => $when($row['last']->finished_at)]) }}</p>
                        @else
                            <p class="text-slate-500">{{ __('farm.admin.ov.no_order') }}</p>
                        @endif
                        @php
                            // a cancel the printer has not confirmed or did not carry out (docs/AM.md §13): the same command the box
                            // on the printer's card shows, here in one line with the way to the box and, when refused, the retry
                            $cc = $row['cancel'];
                            $refused = $cc?->cancel_state === \App\Models\FarmCommand::CANCEL_REFUSED;
                            $co = $cc?->printJob?->order;
                            $cancelReason = $cc && $st['reason'] && str_starts_with($st['reason'], __('farm.admin.cancel.'.$cc->cancel_state));
                        @endphp
                        @if($st['reason'] && ! $cancelReason)
                            <p class="mt-0.5 break-words text-xs text-red-800" data-status-reason>{{ \Illuminate\Support\Str::limit($st['reason'], 160) }}</p>
                        @endif
                        @if($cc)
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg {{ $refused ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-900' }} px-2 py-1 text-xs" data-cancel-state="{{ $cc->cancel_state }}" @if($cancelReason) data-status-reason @endif>
                                <a class="font-semibold underline" href="{{ route('admin.farm.printers.show', $p) }}#cancel-{{ $p->id }}">{{ __('farm.admin.cancel.'.$cc->cancel_state) }}</a>
                                @if($co)
                                    <a class="underline" href="{{ route('admin.farm.orders.show', $co) }}">{{ $co->number }}</a>
                                @endif
                                @if($refused)
                                    <form method="post" action="{{ route('admin.farm.printers.command', $p) }}" data-ajax>@csrf <button name="type" value="cancel" class="btn-quiet text-xs">{{ __('farm.admin.cancel.again') }}</button></form>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- the first order that waits for this printer (its queue, then the paid ones that wait for approval) and how many more --}}
                    <p class="min-w-0 break-words text-sm" data-next><span class="text-slate-500">{{ __('farm.admin.ov.next') }}:</span>
                        @if($next)
                            <a class="font-semibold underline" href="{{ route('admin.farm.orders.show', $next) }}">{{ $next->number ?? $next->token }}</a>
                            @if($next->status === \App\Models\FarmOrder::STATUS_PAID)
                                <span class="whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900" data-next-approval>{{ __('farm.admin.ov.next_approval') }}</span>
                            @endif
                            @if($more > 0)
                                <span class="text-slate-500">{{ __('farm.admin.ov.next_more', ['n' => $more]) }}</span>
                            @endif
                            @if($approval > 0 && ($more > 0 || $next->status !== \App\Models\FarmOrder::STATUS_PAID) && $approval - ($next->status === \App\Models\FarmOrder::STATUS_PAID ? 1 : 0) > 0)
                                <span class="text-xs text-amber-900" data-more-approval>({{ __('farm.admin.ov.more_approval', ['n' => $approval - ($next->status === \App\Models\FarmOrder::STATUS_PAID ? 1 : 0)]) }})</span>
                            @endif
                        @else
                            <span class="text-slate-500">{{ __('farm.admin.ov.next_none') }}</span>
                        @endif
                    </p>

                    {{-- one button that turns the plate's mark over; while something prints the plate is occupied by definition --}}
                    @if($st['busy'])
                        <p class="text-xs text-slate-500" data-bed="busy">{{ __('farm.admin.ov.bed_printing') }}</p>
                    @elseif($away)
                        <p class="text-xs text-slate-500" data-bed="away">{{ $p->bed_clear ? __('farm.admin.ov.bed_away_clear') : __('farm.admin.ov.bed_away_blocked') }}</p>
                    @else
                        <form method="post" action="{{ route('admin.farm.printers.bed', $p) }}" data-ajax data-bed="{{ $p->bed_clear ? 'clear' : 'blocked' }}">
                            @csrf
                            @if($p->bed_clear)
                                <button name="clear" value="0" class="btn-quiet w-full text-sm" title="{{ __('farm.admin.ov.bed_free') }} {{ $p->bed_cleared_at?->format('j. n. H:i') }}">✓ {{ __('farm.admin.ov.bed_block') }}</button>
                            @else
                                <button name="clear" value="1" class="btn-primary w-full text-sm" title="{{ __('farm.admin.bed_note') }}">{{ __('farm.admin.ov.bed_release') }}</button>
                            @endif
                        </form>
                    @endif

                    <a href="{{ route('admin.farm.printers.show', $p) }}" class="text-sm font-semibold underline lg:text-right">{{ __('farm.admin.ov.detail') }} →</a>
                </li>
            @endforeach
        </ul>
    </section>
@empty
    <p class="mt-4 text-sm text-slate-500">{{ __('farm.admin.ov.empty') }}</p>
@endforelse
</div>

{{-- the answer to a button: shows for a moment at the bottom, the page itself stays where it is --}}
<div id="farm-toast" class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 opacity-0 transition-opacity duration-300" role="status" aria-live="polite"><span class="max-w-full rounded-xl bg-slate-900 px-4 py-2 text-sm text-white shadow-lg"></span></div>
@endsection
