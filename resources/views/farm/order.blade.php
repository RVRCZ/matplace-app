@extends('layouts.app', ['title' => __('farm.order.title', ['name' => $order->modelFile?->original_name]).' · matplace', 'noindex' => true])

@php
    $keys = ['farm.stage.checking', 'farm.stage.orienting', 'farm.stage.slicing', 'farm.order.supports_yes', 'farm.order.supports_no',
        'farm.order.low_filament', 'farm.order.starts_now', 'farm.order.goes_to_queue', 'farm.order.no_colors', 'farm.order.paying', 'farm.order.pay',
        'farm.order.queue_ahead', 'farm.order.queue_start', 'farm.order.queue_finish', 'farm.order.blocked_plate', 'farm.order.blocked_offline',
        'farm.order.blocked_approval', 'farm.order.cancel_confirm', 'farm.order.b_time', 'farm.order.b_material', 'farm.order.b_fixed', 'farm.order.b_min',
        'farm.order.b_net', 'farm.order.b_vat', 'farm.order.b_shipping', 'farm.order.b_total', 'farm.units.guess', 'farm.units.ask', 'farm.top_up',
        'farm.units.mm', 'farm.units.cm', 'farm.units.in', 'farm.units.m'];
    $farmCfg = [
        'state' => $state,
        'routes' => [
            'status' => route('farm.orders.status', $order), 'reslice' => route('farm.orders.reslice', $order), 'pay' => route('farm.orders.pay', $order),
            'cancel' => route('farm.orders.cancel', $order), 'credit' => route('account.credit'),
        ],
        'csrf' => csrf_token(),
        'i18n' => collect($keys)->mapWithKeys(fn ($k) => [$k => __($k)])->all(),
    ];
@endphp

@push('head')
<script>window.MP_FARM = {{ \Illuminate\Support\Js::from($farmCfg) }};</script>
@endpush

@section('content')
<div id="farm-order">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-extrabold sm:text-2xl">{{ __('farm.order.title', ['name' => $order->modelFile?->original_name]) }}</h1>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('farm.orders') }}" class="text-action-dark underline">{{ __('farm.my_orders') }}</a>
            <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('farm.credit_balance') }}: <span id="farm-balance">{{ number_format($state['balance'], 0, ',', ' ') }}</span> Kč</a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1.2fr_1fr]">
        {{-- model as it will be printed --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="relative">
                <canvas id="farm-viewer" class="block h-[45vh] w-full touch-none lg:h-[70vh]"></canvas>
                <div id="farm-dims" class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs text-slate-600 shadow"></div>
                <button type="button" id="farm-supports-toggle" class="absolute right-3 top-3 hidden rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-slate-700 shadow" aria-pressed="true">{{ __('farm.order.supports_hide') }}</button>
            </div>
            <p id="farm-oriented" class="hidden border-t border-slate-100 bg-action-soft px-4 py-2 text-sm text-action-dark">{{ __('farm.order.oriented') }}</p>
        </div>

        <div class="flex flex-col gap-4">
            {{-- status + numbers --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between text-sm">
                    <span id="farm-status" class="font-semibold text-slate-700" role="status"></span>
                    <span id="farm-spinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-action border-t-transparent"></span>
                </div>
                <p id="farm-number" class="mt-1 hidden text-xs text-slate-500"></p>
                <p id="farm-error" class="mt-2 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"></p>
                <ul id="farm-warnings" class="mt-2 space-y-1 text-sm text-amber-700"></ul>

                <div id="farm-result" class="hidden">
                    <div class="mt-2 flex items-end gap-2">
                        <span id="farm-price" class="text-4xl font-extrabold tracking-tight">—</span>
                        <span class="pb-1 text-slate-500">Kč <span class="text-xs">{{ __('farm.order.with_vat') }}</span></span>
                    </div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div><dt class="text-slate-500">{{ __('farm.order.time') }}</dt><dd id="farm-time" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('farm.order.weight') }}</dt><dd id="farm-grams" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('farm.order.supports') }}</dt><dd id="farm-supports" class="font-semibold">—</dd></div>
                    </dl>
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-action-dark">{{ __('farm.order.breakdown') }}</summary>
                        <dl id="farm-breakdown" class="mt-2 space-y-1"></dl>
                    </details>
                </div>

                {{-- print progress --}}
                <div id="farm-print" class="mt-3 hidden">
                    <div class="text-sm font-semibold text-slate-700">{{ __('farm.order.progress') }} <span id="farm-progress-val" class="font-normal text-action-dark"></span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200"><div id="farm-progress-bar" class="h-full bg-action" style="width:0%"></div></div>
                    <figure id="farm-camera" class="mt-3 hidden">
                        <img id="farm-camera-img" alt="{{ __('farm.order.camera') }}" class="w-full rounded-xl border border-slate-200">
                        <figcaption class="mt-1 text-xs text-slate-500">{{ __('farm.order.camera') }} <span id="farm-camera-at"></span></figcaption>
                    </figure>
                    <figure class="mt-3 hidden">
                        <video id="farm-timelapse" controls muted playsinline loop class="w-full rounded-xl border border-slate-200"></video>
                        <figcaption class="mt-1 text-xs text-slate-500">{{ __('farm.order.timelapse') }}</figcaption>
                    </figure>
                </div>
                <p id="farm-queue" class="mt-3 hidden text-sm text-slate-600"></p>
            </div>

            {{-- presets (until paid) --}}
            <form id="farm-presets" class="hidden rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-sm font-semibold text-slate-700">{{ __('farm.quality.label') }}</div>
                <div class="mt-2 grid grid-cols-3 gap-2" data-group="quality">
                    @foreach($settings['qualities'] as $key => $q)
                        <button type="button" data-value="{{ $key }}" class="seg">{{ __('farm.quality.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ $q['layer_mm'] }} mm</span></button>
                    @endforeach
                </div>
                <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.strength.label') }}</div>
                <div class="mt-2 grid grid-cols-3 gap-2" data-group="strength">
                    @foreach($settings['strengths'] as $key => $s)
                        <button type="button" data-value="{{ $key }}" class="seg">{{ __('farm.strength.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ __('farm.strength.infill', ['n' => $s['infill']]) }}</span></button>
                    @endforeach
                </div>
                <label class="mt-4 block text-sm font-semibold text-slate-700">{{ __('farm.units.label') }}
                    <select id="farm-unit" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                        @foreach(array_keys(\App\Domain\Farm\ModelValidator::UNITS) as $u)<option value="{{ $u }}">{{ __('farm.units.'.$u) }}</option>@endforeach
                    </select>
                </label>
                <p id="farm-unit-note" class="mt-1 hidden text-xs text-amber-800"></p>
                <button id="farm-reslice" type="submit" class="btn-secondary mt-3 hidden w-full text-sm">{{ __('farm.recalculate') }}</button>
            </form>

            {{-- colour, delivery, terms, pay --}}
            <form id="farm-pay" class="hidden rounded-2xl border border-line bg-action-soft p-4">
                <div class="text-sm font-semibold text-slate-700">{{ __('farm.order.color') }}</div>
                <p class="text-xs text-slate-600">{{ __('farm.order.colors_now') }}</p>
                <div id="farm-colors" class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('farm.order.color') }}"></div>
                <p id="farm-start-note" class="mt-2 text-xs text-slate-600"></p>

                <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.order.delivery') }}</div>
                <div class="mt-2 grid grid-cols-2 gap-2" id="farm-delivery">
                    @foreach($settings['delivery_modes'] as $mode)
                        <button type="button" data-value="{{ $mode }}" class="seg {{ $loop->first ? 'seg-on' : '' }}">{{ __('farm.order.'.$mode, ['price' => number_format($settings['shipping_price'], 0, ',', ' ')]) }}</button>
                    @endforeach
                </div>
                <div id="farm-address" class="mt-2 hidden gap-2 sm:grid-cols-2">
                    @foreach(['name', 'street', 'city', 'zip', 'phone'] as $f)
                        <input name="address[{{ $f }}]" placeholder="{{ __('farm.order.address.'.$f) }}" aria-label="{{ __('farm.order.address.'.$f) }}" value="{{ auth()->user()->{$f} ?? '' }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm {{ $f === 'street' ? 'sm:col-span-2' : '' }}">
                    @endforeach
                </div>
                <textarea name="note" rows="2" maxlength="500" placeholder="{{ __('farm.order.note') }}" aria-label="{{ __('farm.order.note') }}" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>

                <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" id="farm-terms" class="mt-1 h-4 w-4 accent-action">
                    <span>{!! __('farm.order.terms', ['url' => route('farm.terms')]) !!}</span>
                </label>
                <label class="mt-2 flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" id="farm-video-consent" class="mt-1 h-4 w-4 accent-action">
                    <span>{{ __('youtube.consent.label') }}</span>
                </label>

                <p id="farm-pay-error" class="mt-2 hidden text-sm text-red-700" role="alert"></p>
                <a id="farm-topup" href="#" class="btn-secondary mt-2 hidden w-full text-sm">{{ __('farm.top_up') }}</a>
                <button id="farm-pay-btn" type="submit" class="mt-3 w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-50" disabled>{{ __('farm.order.pay') }}</button>
            </form>

            {{-- the customer's YouTube switch, once the order is paid --}}
            @if($order->isCommitted() && ! $order->isTest() && $order->user_id === auth()->id())
                @php($video = $order->video)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="font-semibold text-slate-700">{{ __('youtube.order.title') }}</h2>
                    @include('partials.flash')
                    <p class="mt-1 text-slate-600">{{ __($order->video_consent ? 'youtube.order.on' : 'youtube.order.off') }}</p>
                    @if($order->video_consent && $video?->status === 'published' && $video->watchUrl())
                        <p class="mt-1">{{ __('youtube.order.published') }} <a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener" class="text-action-dark underline">{{ __('youtube.order.watch') }}</a></p>
                    @endif
                    <form method="post" action="{{ route('farm.orders.video_consent', $order) }}" class="mt-2"
                          @if($order->video_consent) onsubmit="return confirm(@js(__('youtube.order.withdraw_confirm')))" @endif>
                        @csrf
                        <input type="hidden" name="consent" value="{{ $order->video_consent ? 0 : 1 }}">
                        <button class="btn-quiet text-sm">{{ __($order->video_consent ? 'youtube.order.withdraw' : 'youtube.order.agree') }}</button>
                    </form>
                </section>
            @endif

            <div class="flex flex-wrap gap-2">
                <button id="farm-cancel" type="button" class="btn-quiet hidden text-sm">{{ __('farm.order.cancel') }}</button>
                <a href="{{ route('farm.start') }}" class="btn-quiet text-sm">{{ __('farm.order.new') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
