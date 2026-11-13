{{-- One printer (docs/AQ.md): what "Tiskárny a fronta" used to show for every machine at once – camera, telemetry, the
     printer's messages, commands, the running job, the queue – plus its filaments and the files on its disk. --}}
@extends('layouts.app', ['title' => $printer->name.' · '.__('farm.admin.nav.dashboard').' · admin', 'noindex' => true])

@php
    $dur = fn (int $m) => $m >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min';
    // the dryer gets its own worded line under the drying buttons, the rest of the telemetry stays a plain list
    $dryerKeys = ['dryer', 'dryer_state', 'dryer_temp', 'dryer_target', 'dryer_remain_min', 'dryer_rh'];
    // how many ACE units the printer reports (agent 0.2.14): worded next to the switch "changer attached", not in the plain list
    $dryerKeys[] = 'ace_units';
@endphp

{{-- with JavaScript the cards are redrawn in place every 30 s (bootFarmDashboard); the meta refresh is the fallback --}}
@push('head')<noscript><meta http-equiv="refresh" content="30"></noscript>@endpush

@section('content')
@include('admin.farm.nav')

<div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
    <a href="{{ route('admin.farm.dashboard') }}" class="underline">← {{ __('farm.admin.nav.dashboard') }}</a>
    <a href="{{ route('admin.farm.printers.edit', $printer) }}" class="underline">{{ __('farm.admin.pd.settings') }}</a>
</div>

<div class="mt-3 grid gap-4 lg:grid-cols-2 lg:items-start">
{{-- the live half: redrawn in place every 30 s and after every button, like the overview (bootFarmDashboard) --}}
<div id="farm-dashboard" data-refresh="{{ route('admin.farm.printers.show', $printer) }}" data-every="30" data-error="{{ __('farm.admin.request_failed') }}">
<p class="mb-2 flex flex-wrap items-center gap-2 text-sm" data-printer-status="{{ $overview['status']['key'] }}"><span class="rounded-full px-3 py-1 text-sm font-bold {{ $overview['status']['tone'] }}">{{ $overview['status']['label'] }}</span>@if($overview['status']['reason'])<span class="text-red-800">{{ $overview['status']['reason'] }}</span>@endif</p>
<div class="grid gap-4">
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
                    <h2 class="text-lg font-bold"><span class="mr-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-sm">{{ $p->shortCode() }}</span>{{ $p->name }}</h2>
                    <p class="text-xs text-slate-500">{{ $p->model }} · {{ __('farm.admin.mode.'.$p->mode) }}@if($p->withoutChanger()) · {{ __('farm.admin.changer.short_off') }}@endif @if($p->agent) · {{ $p->agent->name }}@endif · {{ $p->bedTypeLabel() }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ in_array($state, ['offline', 'error', 'unknown']) ? 'bg-red-100 text-red-800' : ($state === 'printing' ? 'bg-action-soft text-action-dark' : 'bg-ok-soft text-ok') }}">{{ __('farm.admin.state.'.$state) }}</span>
            </div>

            @if($t)
                <p class="mt-2 text-xs text-slate-600">@foreach(collect($t)->except([...$dryerKeys, 'message', 'bed_cleared_at']) as $k => $v){{ $k }}: <strong>{{ is_float($v) ? round($v, 1) : $v }}</strong>@if(! $loop->last) · @endif @endforeach</p>
            @endif
            @if($mk = \App\Domain\Farm\PrinterStatus::messageKind($p))
                {{-- what the printer says in its last report, with the time it began to say it: the reason of a pause, the screen's
                     text, the ACE's complaint. Amber while it is news (or the reason of a pause / an error that lasts), grey once
                     it has been repeating for long, or when it is the last word of a printer the agent cannot reach. A report
                     without the words wipes them (docs/AL.md §25). --}}
                <p class="mt-1 rounded-lg {{ $mk === 'fresh' ? 'bg-amber-50 text-amber-900' : 'bg-slate-50 text-slate-600' }} px-2 py-1 text-xs" data-printer-message="{{ $mk }}"><span class="font-semibold">{{ __('farm.admin.message') }}</span> @if($mk === 'lasting'){{ __('farm.admin.message_lasting') }} @elseif($mk === 'last')({{ __('farm.admin.message_last') }}) @endif{{ $p->message_at?->format('j. n. H:i') }}: {{ $p->message }}</p>
            @endif
            @if($row['command'])
                @php $c = $row['command']; @endphp
                <p class="mt-1 text-xs text-slate-500" data-last-command>{{ __('farm.admin.last_command') }}: {{ __('farm.admin.command_type.'.$c->type) }} · {{ __('farm.admin.command_status.'.$c->status) }} {{ ($c->finished_at ?? $c->created_at)->format('H:i') }}@if($c->result) · {{ $c->resultText() }}@endif</p>
            @endif
            @if($row['cancel'])
                {{-- the order is cancelled on the web, the printer has not let go of its print (agent 0.2.13, docs/AM.md §13) --}}
                @php $cc = $row['cancel']; $refused = $cc->cancel_state === \App\Models\FarmCommand::CANCEL_REFUSED; $co = $cc->printJob?->order; @endphp
                <div class="mt-2 rounded-xl {{ $refused ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-900' }} p-3 text-sm" id="cancel-{{ $p->id }}" data-cancel-state="{{ $cc->cancel_state }}">
                    <p class="font-semibold">{{ __('farm.admin.cancel.'.$cc->cancel_state) }}@if($co) · <a class="underline" href="{{ route('admin.farm.orders.show', $co) }}">{{ $co->number }}</a>@endif <span class="font-normal text-xs">{{ ($cc->finished_at ?? $cc->created_at)->format('j. n. H:i') }}</span></p>
                    <p class="mt-1 text-xs">{{ $refused ? ($cc->resultText() ?? __('farm.admin.cancel.refused_hint')) : __('farm.admin.cancel.waiting_hint') }}</p>
                    @if($refused)
                        <form method="post" action="{{ route('admin.farm.printers.command', $p) }}" data-ajax class="mt-2">@csrf<button name="type" value="cancel" class="btn-quiet text-sm">{{ __('farm.admin.cancel.again') }}</button></form>
                    @endif
                </div>
            @endif
            @if($p->snapshot_path)
                <img src="{{ route('admin.farm.printers.snapshot', $p) }}?t={{ $p->snapshot_at?->timestamp }}" alt="{{ __('farm.order.camera') }}" class="mt-2 w-full rounded-xl border border-slate-200">
                <p class="text-xs text-slate-500">{{ $p->snapshot_at?->format('j. n. H:i:s') }}</p>
            @endif

            {{-- the one confirmation nothing starts without; while a print runs the plate is occupied by definition --}}
            <form method="post" action="{{ route('admin.farm.printers.bed', $p) }}" data-ajax class="mt-3 rounded-xl {{ $p->bed_clear ? 'bg-ok-soft' : 'bg-slate-100' }} p-3">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold">{{ $p->bed_clear ? '✓ '.__('farm.admin.bed_clear') : __('farm.admin.bed_busy') }}@if($p->bed_clear && $p->bed_cleared_at) <span class="font-normal text-xs text-slate-500">{{ $p->bed_cleared_at->format('j. n. H:i') }}</span>@endif</span>
                    @if($p->bed_clear)
                        <button name="clear" value="0" class="btn-quiet text-sm">{{ __('farm.admin.bed_busy_do') }}</button>
                    @else
                        <button name="clear" value="1" class="btn-primary text-sm" @disabled($busy) @if($busy) title="{{ __('farm.admin.bed_locked_hint') }}" @endif>{{ __('farm.admin.bed_clear_do') }}</button>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-600">{{ $busy ? __('farm.admin.bed_locked_hint') : __('farm.admin.bed_note') }}</p>
            </form>
            @if($p->isAgentDriven() && in_array($state, ['error', 'unknown']))
                {{-- a failed print leaves the printer in "error" until somebody clears it; the queue waits meanwhile --}}
                <form method="post" action="{{ route('admin.farm.printers.command', $p) }}" data-ajax class="mt-2 flex flex-wrap items-center gap-2 rounded-xl bg-red-50 p-3 text-sm">
                    @csrf
                    <button name="type" value="reset" class="btn-quiet text-sm" onclick="return confirm(@js(__('farm.admin.reset_confirm')))">{{ __('farm.admin.reset_error') }}</button>
                    <span class="text-xs text-slate-600">{{ __('farm.admin.reset_hint') }}</span>
                </form>
            @endif
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
                            // a printer without an ACE (or one that does not report the box temperature) gets the plain "off"
                            $line = $off
                                ? ((float) ($t['dryer_temp'] ?? 0) > 0 ? __('farm.admin.dryer_off', ['temp' => round((float) $t['dryer_temp'])]) : __('farm.admin.dryer_off_plain'))
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
                    {{-- which spools the job takes: farm, printer, slot ("U33"); a badge when one is fed straight from the holder --}}
                    @if($spools = $job->order->spoolText())<div class="mt-0.5 text-xs text-slate-600">{{ $spools }}@if($job->order->manualOnly()) <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800" data-manual-only>{{ __('farm.admin.manual_only_badge') }}</span>@endif</div>@endif
                    <div class="mt-1 text-xs text-slate-600">{{ $job->status }} · {{ round($job->progress) }} % @if($job->message)· {{ $job->message }}@endif</div>
                    @if($history = array_slice($job->messageHistory(), 0, 3))
                        {{-- the printer's last words about this job, newest first; the whole history is on the order's page --}}
                        <ul class="mt-1 space-y-0.5 text-xs text-slate-600" data-job-messages>
                            @foreach($history as $m)<li><span class="text-slate-400">{{ \Illuminate\Support\Carbon::parse($m['t'])->format('H:i:s') }}</span> {{ $m['m'] }}</li>@endforeach
                        </ul>
                    @endif
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-action" style="width: {{ min(100, $job->progress) }}%"></div></div>
                    @if($p->isAgentDriven())
                        {{-- a pause or a resume the agent is carrying out: said here, and no second one is offered meanwhile (a Kobra S1
                             confirms a resume only after minutes; three presses were three commands, F26-000162). The page redraws
                             itself every 30 s, so the line goes away when the agent has answered. --}}
                        @php $ctl = $row['control'] ?? null; @endphp
                        @if($ctl)
                            <p class="mt-2 rounded-lg bg-action-soft px-2 py-1 text-xs font-semibold text-action-dark" data-control-in-flight="{{ $ctl->type }}" role="status">{{ __('farm.admin.control.'.$ctl->type) }}</p>
                        @endif
                        <form method="post" action="{{ route('admin.farm.printers.command', $p) }}" data-ajax class="mt-2 flex flex-wrap gap-2">
                            @csrf
                            <button name="type" value="pause" class="btn-quiet text-sm" @disabled($ctl)>{{ __('farm.admin.pause') }}</button>
                            <button name="type" value="resume" class="btn-quiet text-sm" @disabled($ctl)>{{ __('farm.admin.resume') }}</button>
                            <button name="type" value="cancel" class="btn-quiet text-sm text-red-700" onclick="return confirm(@js(__('farm.admin.cancel_confirm')))">{{ __('farm.admin.cancel') }}</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- prints that stopped on this printer and wait for the operator's word: not in the queue, nothing starts them (docs/AL.md §25) --}}
            @foreach(($overview['stopped'] ?? collect()) as $so)
                <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900" data-stopped-order="{{ $so->number }}"><a class="font-semibold underline" href="{{ route('admin.farm.orders.show', $so) }}">{{ $so->number }}</a> · {{ __('farm.admin.stopped.title') }}</p>
            @endforeach

            <h3 class="mt-4 text-sm font-bold">{{ __('farm.admin.queue') }}</h3>
            <ol class="mt-1 space-y-1 text-sm">
                @forelse($row['queue'] as $q)
                    <li class="flex flex-wrap justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                        <span><a class="underline" href="{{ route('admin.farm.orders.show', $q['order']) }}">{{ $q['order']->number }}</a> · {{ $q['order']->modelFile?->original_name }} · {{ $q['order']->spoolText() ?: $q['order']->color?->name }}@if($q['order']->manualOnly()) <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800" data-manual-only>{{ __('farm.admin.manual_only_badge') }}</span>@endif</span>
                        @if($q['eta'])<span class="text-xs text-slate-600">{{ __('farm.admin.eta', ['start' => $dur($q['eta']['start_in']), 'finish' => $dur($q['eta']['finish_in'])]) }}</span>@endif
                    </li>
                @empty
                    <li class="text-slate-500">{{ __('farm.admin.queue_empty') }}</li>
                @endforelse
            </ol>

            <p class="mt-3 text-xs text-slate-500">
                @foreach($p->slots as $s)
                    {{-- struck through: a kind that breaks in the changer sitting in a machine run by the agent – not offered, move the spool --}}
                    {{-- faded: a position of the changer that is off the machine (docs/AL.md §24) --}}
                    @php $fits = \App\Models\FarmPrinterSlot::kindFits($p, $s->color); $away = $s->setRelation('printer', $p)->isDetached(); @endphp
                    <span class="mr-2 inline-flex items-center gap-1 {{ $s->enabled && ! $away ? '' : 'opacity-40' }} {{ $fits ? '' : 'line-through decoration-amber-700' }}" @if($away) title="{{ __('farm.admin.changer.detached_row') }}" @elseif(! $fits) title="{{ __('farm.admin.manual_only_refused', ['color' => $s->color?->fullLabel(), 'printer' => $p->name]) }}" @endif><span class="inline-block h-3 w-3 rounded-full border border-slate-300" style="background: {{ $s->color?->hex ?? '#fff' }}"></span><span class="font-mono">{{ $s->code() }}</span>: {{ $s->color?->fullLabel() ?? '—' }} ({{ round($s->remaining_g) }} g)</span>
                    @if($s->color)
                        <a href="{{ route('admin.farm.colors.show', $s->color) }}" class="mr-2 underline" title="{{ __('farm.admin.pd.filament_detail') }}">→</a>
                    @endif
                @endforeach
                <a href="{{ route('admin.farm.printers.edit', $p) }}" class="underline">{{ __('farm.admin.nav.printers') }}</a>
            </p>
        </section>
    @endforeach
</div>
</div>

{{-- the half that is typed into: outside the redrawn box, so a refresh never takes a choice away --}}
<div class="space-y-4">
    @include('admin.farm.partials.printer_slots', ['printer' => $printer, 'colors' => $colors])
    @if($files)
        @include('admin.farm.partials.files', ['printer' => $printer, 'files' => $files])
    @endif
</div>
</div>

{{-- the answer to a button: shows for a moment at the bottom, the page itself stays where it is --}}
<div id="farm-toast" class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 opacity-0 transition-opacity duration-300" role="status" aria-live="polite"><span class="max-w-full rounded-xl bg-slate-900 px-4 py-2 text-sm text-white shadow-lg"></span></div>
@endsection
