@extends('layouts.app', ['title' => ($order->number ?? '#'.$order->id).' · admin', 'noindex' => true])

@php
    $job = $order->printJobs->last();
@endphp

@section('content')
@include('admin.farm.nav')

<div class="mt-4 flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-xl font-extrabold">{{ $order->number ?? '#'.$order->id }} · {{ $order->modelFile?->original_name }}</h1>
    <span class="rounded-full bg-action-soft px-3 py-1 text-sm font-bold text-action-dark">{{ __('farm.status.'.$order->status) }}</span>
</div>
@if($order->error)<p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $order->error }}@if($order->error_detail) · {{ $order->error_detail }}@endif</p>@endif

<div class="mt-4 grid gap-4 lg:grid-cols-[1.2fr_1fr]">
    <div class="space-y-4">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <canvas id="admin-farm-viewer" data-model="{{ route('farm.orders.model', $order) }}" class="block h-[45vh] w-full touch-none"></canvas>
        </div>
        @if($order->timelapse_path)
            <figure class="rounded-2xl border border-slate-200 bg-white p-3">
                <video src="{{ route('admin.farm.orders.timelapse', $order) }}" controls muted playsinline loop class="w-full rounded-xl"></video>
                <figcaption class="mt-1 text-xs text-slate-500">{{ __('farm.order.timelapse') }}</figcaption>
            </figure>
        @endif
        @if($job && $job->snapshot_path)
            <figure class="rounded-2xl border border-slate-200 bg-white p-3">
                <img src="{{ route('admin.farm.orders.snapshot', $order) }}?t={{ $job->snapshot_at?->timestamp }}" alt="{{ __('farm.order.camera') }}" class="w-full rounded-xl" id="admin-snapshot">
                <script>if (@json($order->status === 'printing')) setInterval(() => { const i = document.getElementById('admin-snapshot'); i.src = i.src.split('?')[0] + '?t=' + Date.now(); }, 15000);</script>
                <figcaption class="mt-1 text-xs text-slate-500">{{ __('farm.order.camera') }} · {{ $job->snapshot_at?->format('j. n. H:i:s') }}</figcaption>
            </figure>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="font-bold">{{ __('farm.admin.history') }}</h2>
            <ol class="mt-2 space-y-1">
                @foreach($order->events as $e)
                    <li><span class="text-xs text-slate-500">{{ $e->created_at->format('j. n. H:i:s') }}</span> {{ $e->from ?? '∅' }} → <strong>{{ $e->to }}</strong> <span class="text-slate-500">({{ $e->actor }})</span>@if($e->note) · {{ $e->note }}@endif</li>
                @endforeach
            </ol>
            @if($order->printJobs->isNotEmpty())
                <h3 class="mt-3 font-semibold">Print jobs</h3>
                <ul class="mt-1 space-y-1 text-xs text-slate-600">
                    @foreach($order->printJobs as $j)
                        <li>#{{ $j->id }} · {{ $j->status }} · {{ round($j->progress) }} % · slot {{ $j->slot + 1 }} @if($j->print_duration_s)· {{ round($j->print_duration_s / 60) }} min @endif @if($j->filament_used_mm)· {{ round($j->filament_used_mm / 1000, 2) }} m @endif @if($j->message)· {{ $j->message }}@endif</li>
                    @endforeach
                </ul>
            @endif
        </section>

        @php $overridesLocked = ! in_array($order->status, ['uploaded', 'sliced', 'failed', 'paid'], true); @endphp
        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="font-bold">Přepisy nastavení sliceru pro tuhle zakázku</h2>
            <p class="mt-1 text-xs text-slate-600">JSON objekt s klíči procesního profilu OrcaSlicer, například
                <code>{"wall_loops": "3", "sparse_infill_density": "20%", "sparse_infill_pattern": "gyroid", "top_shell_layers": "5"}</code>.
                Má přednost před předvolbami i před nastavením zákazníka. Po uložení se zakázka naslicuje a (u nezaplacené) nacení znovu.
                @if($order->print_settings) Zákazník zadal: <code>{{ json_encode($order->print_settings) }}</code>.@endif</p>
            <form method="post" action="{{ route('admin.farm.orders.overrides', $order) }}" class="mt-2">@csrf
                <textarea name="overrides" rows="3" @disabled($overridesLocked) class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" placeholder='{"wall_loops": "3"}'>{{ old('overrides', $order->admin_overrides ? json_encode($order->admin_overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                <div class="mt-2 flex items-center gap-3">
                    <button class="btn-quiet min-h-0 px-3 py-2 text-sm" @disabled($overridesLocked)>Uložit a naslicovat znovu</button>
                    @if($overridesLocked)<span class="text-xs text-slate-500">Zakázka už je ve frontě nebo dál; přepisy jdou měnit jen před zařazením.</span>@endif
                </div>
            </form>
        </section>

        <details class="rounded-2xl border border-slate-200 bg-white p-4 text-xs">
            <summary class="cursor-pointer text-sm font-bold">Slice parameters (reproducibility)</summary>
            <pre class="mt-2 overflow-x-auto whitespace-pre-wrap">{{ json_encode(['slice_params' => $order->slice_params, 'slice_result' => $order->slice_result, 'orientation' => $order->orientation, 'check' => $order->check, 'gcode_sha256' => $order->gcode_sha256], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    </div>

    <div class="space-y-4">
        @if($order->isTest())
            @php $tp = (array) $order->test_params; $cand = (array) ($tp['candidate'] ?? []); @endphp
            <section class="rounded-2xl border border-action bg-action-soft/40 p-4 text-sm">
                <h2 class="font-bold">Testovací tisk · {{ __('farm.test.object.'.($tp['object'] ?? 'quick')) }}</h2>
                <p class="mt-1 text-xs text-slate-600">Ladění: @if($order->printerMaterial)<a class="underline" href="{{ route('admin.farm.tuning.edit', $order->printerMaterial) }}">{{ $order->printerMaterial->label() }} na {{ $order->printer?->name }}</a> (verze {{ $tp['row_version'] ?? '?' }})@else — @endif</p>
                <p class="mt-1 text-xs text-slate-600">Zkoušeno: tryska {{ $cand['nozzle_temp'] ?? '—' }} / {{ $cand['nozzle_temp_first'] ?? '—' }} °C · podložka {{ $cand['bed_temp'] ?? '—' }} °C</p>
                @if(! empty($tp['temps']))<p class="mt-1 text-xs text-slate-600">Patra zdola ({{ $tp['floor_mm'] ?? 10 }} mm): {{ implode(' · ', array_map(fn ($i, $v) => ($i + 1).': '.$v.' °C', array_keys($tp['temps']), $tp['temps'])) }}@if(isset($tp['floors_set'])) · do G-code zapsáno {{ $tp['floors_set'] }} přechodů @endif</p>@endif
                @if(! empty($tp['features']))
                    <details class="mt-1 text-xs"><summary class="cursor-pointer">Co na objektu sledovat</summary>
                        <ul class="mt-1 list-disc pl-4 text-slate-600">@foreach($tp['features'] as $f)<li>{{ $f['name'] }}@isset($f['index']) {{ $f['index'] }}@endisset: {{ implode(', ', $f['checks'] ?? []) }}</li>@endforeach</ul>
                    </details>
                @endif
                @if(in_array($order->status, ['done', 'handed_over']) && $order->printerMaterial)
                    <a href="{{ route('admin.farm.tuning.edit', $order->printerMaterial) }}#tests" class="btn-primary mt-2 w-full text-sm">Vyhodnotit a převzít nastavení</a>
                @endif
            </section>
        @endif
        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <dl class="grid grid-cols-2 gap-x-3 gap-y-1">
                <dt class="text-slate-500">E-mail</dt><dd>{{ $order->user?->email }} <span class="text-xs text-slate-500">(@money($balance))</span></dd>
                <dt class="text-slate-500">{{ __('farm.admin.nav.printers') }}</dt><dd>{{ $order->printer?->name ?? '—' }} · slot {{ ($order->slot?->slot ?? 0) + 1 }}</dd>
                <dt class="text-slate-500">{{ __('farm.order.color') }}</dt><dd>{{ $order->material?->code }} {{ $order->color?->name ?? '—' }}</dd>
                <dt class="text-slate-500">{{ __('farm.quality.label') }} / {{ __('farm.strength.label') }}</dt><dd>{{ $order->quality }} / {{ $order->strength }}</dd>
                @if($order->copies > 1)<dt class="text-slate-500">{{ __('farm.copies.label') }}</dt><dd>{{ $order->plates > 1 ? __('farm.copies.plates', ['n' => $order->copies, 'p' => $order->plates, 'layout' => implode(' + ', $order->plateLayout())]) : __('farm.copies.note', ['n' => $order->copies]) }}@if($order->plates > 1) · {{ __('farm.copies.plate_of', ['i' => $order->plates_done, 'p' => $order->plates]) }}@endif</dd>@endif
                <dt class="text-slate-500">{{ __('farm.order.dims') }}</dt><dd>@if($order->check){{ implode(' × ', array_map(fn ($v) => round($v, 1), $order->check['dims'] ?? [])) }} mm @endif</dd>
                <dt class="text-slate-500">{{ __('farm.order.time') }}</dt><dd>{{ $order->est_minutes }} min</dd>
                <dt class="text-slate-500">{{ __('farm.order.weight') }}</dt><dd>{{ $order->est_grams }} g · {{ $order->est_meters }} m</dd>
                <dt class="text-slate-500">{{ __('farm.order.supports') }}</dt><dd>{{ $order->supports_used ? '✓' : '—' }}</dd>
                <dt class="text-slate-500">{{ __('farm.order.price') }}</dt><dd class="font-semibold">@if($order->price_total)@money($order->total())@if($order->shipping_price > 0) <span class="font-normal text-slate-500">({{ __('farm.order.b_shipping') }} @money($order->shipping_price, $order->currency))</span>@endif @else — @endif</dd>
                <dt class="text-slate-500">{{ __('farm.order.delivery') }}</dt><dd>{{ __('farm.delivery.'.$order->delivery) }}@if($order->shipping_address) · {{ implode(', ', array_filter(\Illuminate\Support\Arr::only($order->shipping_address, ['name', 'phone', 'pickup_point_name', 'street', 'zip', 'city', 'country']))) }}@endif
                    @if($order->isParcel())<span class="block text-xs text-slate-500">{{ app(\App\Domain\Farm\Shipping::class)->parcelGrams($order) }} g @if($order->shipping_address['carrier_id'] ?? null)· carrier {{ $order->shipping_address['carrier_id'] }}@endif @if($order->shipping_address['pickup_point_id'] ?? null)· {{ $order->shipping_address['pickup_point_id'] }}@endif</span>@endif
                    @if($order->packeta_barcode)<span class="block text-xs"><a href="{{ $order->tracking_url }}" target="_blank" rel="noopener" class="text-action-dark underline">{{ $order->packeta_barcode }}</a> · {{ $order->shipped_at?->format('j. n. Y H:i') }} · <a href="{{ route('admin.farm.orders.label', $order) }}" target="_blank" class="text-action-dark underline">{{ __('farm.admin.label') }}</a></span>@endif</dd>
                @if($order->note)<dt class="text-slate-500">{{ __('farm.admin.note') }}</dt><dd>{{ $order->note }}</dd>@endif
                @if($order->terms_accepted_at)<dt class="text-slate-500">Terms</dt><dd>{{ $order->terms_version }} · {{ $order->terms_accepted_at->format('j. n. Y H:i') }} · {{ $order->terms_ip }}</dd>@endif
            </dl>
            @if($eta)<p class="mt-2 text-xs text-slate-600">{{ __('farm.admin.eta', ['start' => $eta['start_in'].' min', 'finish' => $eta['finish_in'].' min']) }}@if($eta['blocked']) · {{ __('farm.order.blocked_'.$eta['blocked']) }}@endif</p>@endif
            @if($order->gcode_path)<a href="{{ route('admin.farm.orders.gcode', $order) }}" class="btn-secondary mt-3 w-full text-sm">⬇ {{ __('farm.admin.download_gcode') }}</a>@endif
        </section>

        @if($order->status === 'paid' && ! $order->approved_at)
            <form method="post" action="{{ route('admin.farm.orders.approve', $order) }}" class="rounded-2xl border border-amber-300 bg-amber-50 p-4">@csrf
                <button class="btn-primary w-full">{{ __('farm.admin.approve') }}</button>
            </form>
        @endif

        {{-- a finished print that leaves as a parcel: one button announces it to Packeta, hands the order over and mails the tracking link --}}
        @if($order->status === 'done' && $order->isParcel())
            <form method="post" action="{{ route('admin.farm.orders.ship', $order) }}" class="rounded-2xl border border-line bg-action-soft p-4 text-sm">@csrf
                <button class="btn-primary w-full">{{ __('farm.admin.ship') }}</button>
                <p class="mt-2 text-xs text-slate-600">{{ __('farm.admin.ship_hint') }}</p>
            </form>
        @endif

        @if($targets)
            <form method="post" action="{{ route('admin.farm.orders.status', $order) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
                <h2 class="font-bold">{{ __('farm.admin.change_status') }}</h2>
                <select name="to" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    @foreach($targets as $t)<option value="{{ $t }}">{{ __('farm.status.'.$t) }}</option>@endforeach
                </select>
                <input name="note" maxlength="500" placeholder="{{ __('farm.admin.note') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">
                @if($order->isParcel())<input name="tracking" maxlength="80" value="{{ $order->tracking }}" placeholder="{{ __('farm.admin.tracking') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">@endif
                <button class="btn-primary mt-2 w-full text-sm">{{ __('farm.admin.change_status') }}</button>
            </form>
        @endif

        <form method="post" action="{{ route('admin.farm.orders.actuals', $order) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
            <h2 class="font-bold">{{ __('farm.admin.actuals') }}</h2>
            <p class="text-xs text-slate-500">{{ __('farm.admin.actuals_hint') }} @if($order->actual_source)({{ $order->actual_source }})@endif</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <label class="text-xs font-semibold text-slate-600">{{ __('farm.admin.actual_minutes') }}<input type="number" name="actual_minutes" min="1" value="{{ $order->actual_minutes }}" placeholder="{{ $order->est_minutes }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                <label class="text-xs font-semibold text-slate-600">{{ __('farm.admin.actual_grams') }}<input type="number" step="0.1" name="actual_grams" min="0.1" value="{{ $order->actual_grams }}" placeholder="{{ $order->est_grams }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                <label class="text-xs font-semibold text-slate-600">{{ __('farm.admin.quality') }}
                    <select name="quality_rating" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal"><option value="">—</option>@foreach([5, 4, 3, 2, 1] as $q)<option value="{{ $q }}" @selected($order->quality_rating === $q)>{{ $q }}</option>@endforeach</select>
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ __('farm.admin.quality_note') }}<input name="quality_note" maxlength="500" value="{{ $order->quality_note }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
            </div>
            <button class="btn-quiet mt-2 w-full text-sm">OK</button>
        </form>

        @if($order->paid_at)
            <form method="post" action="{{ route('admin.farm.orders.refund', $order) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
                <h2 class="font-bold">{{ __('farm.admin.refund') }}</h2>
                <input name="note" required maxlength="300" placeholder="{{ __('farm.admin.note') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">
                <button class="btn-quiet mt-2 w-full text-sm">{{ __('farm.admin.refund') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
