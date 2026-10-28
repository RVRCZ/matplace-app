@extends('layouts.app', ['title' => __('farm.order.title', ['name' => $order->modelFile?->original_name]).' · matplace', 'noindex' => true])

@php
    $keys = ['farm.stage.checking', 'farm.stage.loading', 'farm.stage.repairing', 'farm.stage.orienting', 'farm.stage.placing', 'farm.stage.slicing', 'farm.stage_step', 'farm.order.supports_yes', 'farm.order.supports_no',
        'farm.order.low_filament', 'farm.order.starts_now', 'farm.order.goes_to_queue', 'farm.order.no_colors', 'farm.order.paying', 'farm.order.pay', 'farm.order.pay_short',
        'farm.order.pay_card', 'farm.order.pay_card_rest', 'farm.order.card_pending', 'farm.order.card_kept',
        'farm.order.queue_ahead', 'farm.order.queue_start', 'farm.order.queue_starting', 'farm.order.queue_finish', 'farm.order.blocked_plate', 'farm.order.blocked_offline',
        'farm.order.blocked_approval', 'farm.order.cancel_confirm', 
        'farm.units.guess', 'farm.units.ask', 'farm.top_up', 'farm.copies.max', 'farm.copies.note', 'farm.copies.plates', 'farm.copies.plate_of', 'farm.copies.more_plates', 'farm.order.printer', 'farm.order.supports_off', 'farm.order.second_same', 'farm.order.second_same_hint', 'farm.order.second_line', 'farm.order.second_color', 'farm.order.changes_title', 'farm.order.change_title', 'farm.order.change_same', 'farm.order.change_same_hint', 'farm.order.change_first', 'farm.order.change_line', 'farm.order.parts_title', 'farm.order.parts_hint', 'farm.order.part_main', 'farm.order.part_line', 'farm.order.parts_one_plate', 'farm.order.parts_one_machine', 'farm.order.colors_no_machine', 'farm.order.one_plate_required', 'farm.order.one_plate_copies', 'farm.order.one_plate_single', 'farm.order.kind_all', 'farm.order.codes_hint', 'farm.order.by_parts',
        'farm.units.mm', 'farm.units.cm', 'farm.units.in', 'farm.units.m', 'farm.order.cancel_running_confirm', 'farm.order.supports_hide', 'farm.order.supports_show',
        'farm.delivery.free', 'farm.delivery.not_here', 'farm.delivery.too_big', 'farm.delivery.too_big_pickup', 'farm.delivery.none', 'farm.delivery.pick_point', 'farm.delivery.fill_address', 'farm.delivery.to', 'farm.delivery.track',
        'farm.order.tl.print', 'farm.order.tl.printing', 'farm.order.tl.printing_now', 'farm.order.tl.printing_end', 'farm.order.tl.done', 'farm.order.tl.dispatched', 'farm.order.tl.parcel', 'farm.order.tl.pickup', 'farm.order.tl.none', 'farm.order.why.lead', 'farm.order.why.printer', 'farm.order.why.kind', 'farm.order.why.settings', 'farm.order.why.time', 'farm.order.reslicing', 'farm.order.ship.total', 'farm.order.ship.total_estimate', 'farm.order.pay_note_round', 'farm.order.pay_note_min', 'farm.order.while_slicing', 'farm.order.while_slicing_big', 'farm.order.estimate_range', 'farm.order.estimate_time', 'farm.order.estimate_wait', 'farm.order.estimate_running', 'farm.order.slice_start', 'farm.order.slice_start_hint', 'farm.order.restart_slice', 'farm.recalculate', 'farm.order.ship.choose', 'farm.order.ship.pickup', 'farm.order.ship.parcel', 'farm.order.ship.counting', 'farm.order.ship.free', 'farm.order.pay_total', 'farm.order.refunded',
        'farm.order.bar.pay', 'farm.order.bar.pay_card', 'farm.order.bar.pay_card_rest', 'farm.order.bar.pick_color', 'farm.order.bar.total', 'farm.order.bar.total_estimate', 'farm.order.bar.email', 'farm.order.bar.terms', 'farm.order.recolor_first'];
    $farmCfg = [
        'state' => $state,
        'prefill' => $prefill,
        'routes' => [
            'status' => route('farm.orders.status', $order), 'reslice' => route('farm.orders.reslice', $order), 'pay' => route('farm.orders.pay', $order),
            'checkout' => route('farm.orders.checkout', $order), 'quote' => route('farm.orders.quote', $order),
            'cancel' => route('farm.orders.cancel', $order), 'credit' => route('account.credit'),
            // the top-up page comes back here; "need" (what is missing) is appended by the page
            'topup' => route('account.credit', ['back' => $order->token]),
        ],
        'csrf' => csrf_token(),
        // a guest pays by card with an e-mail and never sees credit (docs/AO.md)
        'guest' => $guest ?? false,
        'paid' => (int) request()->query('paid'),   // back from the card payment of this order: the page waits for the webhook
        'i18n' => collect($keys)->mapWithKeys(fn ($k) => [$k => __($k)])->all(),
    ];
@endphp

@push('head')
<script>window.MP_FARM = {{ \Illuminate\Support\Js::from($farmCfg) }};</script>
@endpush

@section('content')
<div id="farm-order" class="group/order">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-extrabold sm:text-2xl">{{ __('farm.order.title', ['name' => $order->modelFile?->original_name]) }}</h1>
        <div class="flex items-center gap-3 text-sm">
            @if($guest ?? false)
                {{-- a guest: nothing of an account here; one who has an account signs in and comes back to this order --}}
                <a href="{{ route('login') }}" class="text-action-dark underline">{{ __('farm.guest.sign_in') }}</a>
            @else
                <a href="{{ route('account.orders') }}" class="text-action-dark underline">{{ __('farm.my_orders') }}</a>
                <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('farm.credit_balance') }}: <span id="farm-balance">@money($state['balance'], $state['currency'])</span></a>
            @endif
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1.2fr_1fr] lg:items-start">
        {{-- the left column: the model and the price. While the customer chooses (data-choosing and .stick-stage, set by
             farm.ts) it stays in the window on a desktop and the model gives way to the price card, never the other way
             round; once paid (camera, time-lapse) it runs with the page (docs/AO.md §5) --}}
        <div id="farm-side" class="flex min-w-0 flex-col gap-4 lg:group-data-[choosing]/order:overflow-y-auto">
            {{-- model as it will be printed --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white lg:group-data-[choosing]/order:flex lg:group-data-[choosing]/order:min-h-[180px] lg:group-data-[choosing]/order:flex-1 lg:group-data-[choosing]/order:flex-col">
                <div class="relative lg:group-data-[choosing]/order:min-h-0 lg:group-data-[choosing]/order:flex-1">
                    <canvas id="farm-viewer" class="block h-[45vh] w-full touch-none lg:h-[70vh] lg:group-data-[choosing]/order:h-full {{ ($modelHidden ?? false) ? 'hidden' : '' }}"></canvas>
                    @if($modelHidden ?? false)
                        {{-- a designer's model that is not offered for download: its picture instead of the 3D preview --}}
                        @if($card?->coverUrl(false))<img src="{{ $card->coverUrl(false) }}" alt="{{ $card->title }}" class="h-[45vh] w-full bg-slate-50 object-contain lg:h-[70vh] lg:group-data-[choosing]/order:h-full">@else<div class="h-[45vh] bg-slate-50 lg:h-[70vh] lg:group-data-[choosing]/order:h-full"></div>@endif
                    @endif
                    <div id="farm-dims" class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs text-slate-600 shadow"></div>
                    <button type="button" id="farm-supports-toggle" class="absolute right-3 top-3 hidden rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-slate-700 shadow" aria-pressed="true">{{ __('farm.order.supports_hide') }}</button>
                </div>
                <p id="farm-oriented" class="hidden border-t border-slate-100 bg-action-soft px-4 py-2 text-sm text-action-dark">{{ __('farm.order.oriented') }}</p>
                {{-- the finished print photographed in the photo box, on its backdrop (PrintPhotos) --}}
                <figure id="farm-photo-box" class="hidden border-t border-slate-100 p-3">
                    <img id="farm-photo" alt="{{ __('farm.order.photo_of_print') }}" class="w-full rounded-xl" loading="lazy">
                    <figcaption class="mt-1 text-xs text-slate-500">{{ __('farm.order.photo_of_print') }}</figcaption>
                </figure>
            </div>

            {{-- status + numbers --}}
            <div id="farm-card" class="shrink-0 rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between text-sm">
                    <span id="farm-status" class="font-semibold text-slate-700" role="status"></span>
                    <span id="farm-spinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-action border-t-transparent"></span>
                </div>
                <div id="farm-progress" class="mt-2 hidden">
                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div id="farm-progress-bar" class="h-full rounded-full bg-action transition-all duration-500" style="width:0%"></div></div>
                    <p id="farm-progress-step" class="mt-1 text-xs text-slate-500"></p>
                </div>
                <p id="farm-number" class="mt-1 hidden text-xs text-slate-500"></p>
                <p id="farm-printer" class="mt-1 hidden text-xs text-slate-500"></p>
                <p id="farm-error" class="mt-2 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"></p>
                {{-- money the bank has given back to the card for this order (CardRefunds) --}}
                <p id="farm-refunded" class="mt-2 hidden rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" aria-live="polite"></p>
                <ul id="farm-warnings" class="mt-2 space-y-1 text-sm text-amber-700"></ul>

                {{-- a big model before its slice (docs/AL.md §11): the rough price from the model's volume and the choices below,
                     as a range like the calculator's first page; the exact one comes with the button in the form --}}
                <div id="farm-estimate" class="hidden">
                    <div class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('farm.order.estimate_title') }}</div>
                    <div class="mt-1 flex items-end gap-2">
                        <span id="farm-estimate-price" class="text-3xl font-extrabold tracking-tight">—</span>
                        <span class="pb-1 text-xs text-slate-500">{{ __('farm.order.with_vat') }}</span>
                    </div>
                    <p id="farm-estimate-time" class="mt-1 text-sm text-slate-600"></p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('farm.order.estimate_note') }}</p>
                    <p id="farm-estimate-wait" class="mt-2 rounded-lg bg-action-soft px-3 py-2 text-sm text-ink" role="status"></p>
                </div>

                <div id="farm-result" class="hidden">
                    <div class="mt-2 flex items-end gap-2">
                        <span id="farm-price" class="text-4xl font-extrabold tracking-tight">—</span>
                        <span class="pb-1 text-xs text-slate-500">{{ __('farm.order.with_vat') }}</span>
                    </div>
                    {{-- the big number is the print (with VAT); delivery is its own small line under it, by the way chosen below,
                         and the sum of both is said only where it is paid (Roman, 22 Oct 2026) --}}
                    <p id="farm-shipping-line" class="mt-1 text-sm text-slate-600" aria-live="polite"></p>
                    {{-- the sum with the delivery right here (the sales path, 25 Oct 2026): exact once the carrier's quote is in, an estimate before --}}
                    <p id="farm-total-line" class="mt-1 hidden text-sm font-semibold text-slate-800" aria-live="polite"></p>
                    <p id="farm-copies-line" class="mt-1 hidden text-sm font-semibold text-slate-700"></p>
                    {{-- after a new calculation: why the price is not what it was (another machine, another material, other settings) --}}
                    <p id="farm-price-why" class="mt-2 hidden rounded-lg bg-action-soft px-3 py-2 text-sm text-ink" role="status"></p>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div><dt class="text-slate-500">{{ __('farm.order.time') }}</dt><dd id="farm-time" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('farm.order.weight') }}</dt><dd id="farm-grams" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('farm.order.supports') }}</dt><dd id="farm-supports" class="font-semibold">—</dd></div>
                    </dl>
                    {{-- print, dispatch and delivery apart, each an estimate (audit 10/2026, bod 6; Roman 17 Oct: a parcel leaves
                         on the day its print ends, on working days) --}}
                    <dl id="farm-timeline" class="mt-3 space-y-1 rounded-xl bg-slate-50 p-3 text-sm">
                        <div><dt class="inline font-semibold text-slate-700">{{ __('farm.order.tl.print_label') }}:</dt> <dd id="farm-tl-print" class="inline"></dd></div>
                        <div id="farm-tl-dispatch"><dt class="inline font-semibold text-slate-700">{{ __('farm.order.tl.dispatch_label') }}:</dt> <dd id="farm-tl-dispatch-text" class="inline" data-rule="{{ __('farm.order.tl.dispatch') }}">{{ __('farm.order.tl.dispatch') }}</dd></div>
                        <div><dt class="inline font-semibold text-slate-700">{{ __('farm.order.tl.delivery_label') }}:</dt> <dd id="farm-tl-delivery" class="inline"></dd></div>
                        <p class="pt-1 text-xs text-slate-500">{{ __('farm.order.tl.estimate') }}</p>
                    </dl>
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
                        <video id="farm-timelapse" controls playsinline loop class="w-full rounded-xl border border-slate-200"></video>
                        <figcaption class="mt-1 text-xs text-slate-500">{{ __('farm.order.timelapse') }}
                            <a id="farm-short" href="{{ route('farm.orders.short', $order) }}" download class="ml-2 hidden font-semibold text-action-dark underline">{{ __('youtube.order.short_download') }}</a></figcaption>
                    </figure>
                </div>
                <p id="farm-queue" class="mt-3 hidden text-sm text-slate-600"></p>
                {{-- a paid order that leaves as a parcel: where it goes and, once sent, where to follow it --}}
                <p id="farm-destination" class="mt-3 hidden text-sm text-slate-600"></p>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            {{-- step 2 of 3, until paid: material and colours (one list, chosen once), quality and the print settings --}}
            <form id="farm-presets" class="hidden rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="text-base font-bold text-slate-800">{{ __('farm.order.step_material') }}</h2>
                <p class="mt-1 text-xs text-slate-600">{{ __('farm.order.step_material_hint') }}</p>
                <p id="farm-presets-wait" class="mt-1 hidden text-xs text-slate-600">{{ __('farm.order.while_slicing') }}</p>
                <div id="farm-material" class="mt-3 hidden scroll-mt-4">
                    {{-- the kinds loaded right now (PLA, PLA+, PETG…): a filter over the colours below, the colour sets the kind --}}
                    <div class="text-sm font-semibold text-slate-700">{{ __('farm.order.material') }}</div>
                    <div id="farm-kinds" class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('farm.order.material') }}"></div>
                    <div id="farm-color-title" class="mt-4 text-sm font-semibold text-slate-700" data-plain="{{ __('farm.order.color') }}">{{ __('farm.order.color') }}</div>
                    <p class="text-xs text-slate-600">{{ __('farm.order.colors_now') }}</p>
                    <p class="text-xs text-slate-500">{{ __('farm.order.codes_hint') }}</p>
                    <div id="farm-colors" class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('farm.order.color') }}"></div>
                    {{-- a design of several separately printed parts (a box and its lid): together in one colour, or each part
                         its own print from its own spool of the machine, one plate after another (sliced again when changed) --}}
                    <label id="farm-by-parts-box" class="mt-3 hidden cursor-pointer items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                        <input type="checkbox" id="farm-by-parts" class="mt-1 h-4 w-4 accent-action">
                        <span><span id="farm-by-parts-title" class="font-semibold text-slate-700"></span><br><span class="text-xs text-slate-500">{{ __('farm.order.by_parts_hint', ['max' => \App\Models\FarmOrder::MAX_COLORS]) }}</span></span>
                    </label>
                    <div id="farm-second" class="mt-3 hidden">
                        <div class="text-sm font-semibold text-slate-700">{{ __('farm.order.second_color') }}</div>
                        <p class="text-xs text-slate-600">{{ __('farm.order.second_color_hint') }}</p>
                        <div id="farm-second-colors" class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('farm.order.second_color') }}"></div>
                    </div>
                    <p id="farm-start-note" class="mt-2 text-xs text-slate-600"></p>
                    <p id="farm-recolor-note" class="mt-3 hidden rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ __('farm.order.recolor_note') }}</p>
                    <button id="farm-recolor" type="button" class="mt-3 hidden w-full rounded-xl bg-action px-4 py-3 font-semibold text-white">{{ __('farm.order.recolor') }}</button>
                </div>
                <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.quality.label') }}</div>
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
                <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.supports.label') }}</div>
                <div class="mt-2 grid grid-cols-2 gap-2" data-group="supports">
                    @foreach(['auto', 'off'] as $key)
                        <button type="button" data-value="{{ $key }}" class="seg">{{ __('farm.supports.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ __('farm.supports.'.$key.'_hint') }}</span></button>
                    @endforeach
                </div>
                {{-- the numbers a drawing asks for; empty = the presets above --}}
                <details id="farm-advanced" class="mt-4 rounded-xl border border-slate-200 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">{{ __('farm.advanced.label') }} <span class="font-normal text-slate-500">{{ __('farm.advanced.hint') }}</span></summary>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach(\App\Domain\Farm\PrintSettings::FIELDS as $field => [$min, $max])
                            <label class="text-xs font-semibold text-slate-600">{{ __('farm.advanced.'.$field) }}
                                <input id="farm-adv-{{ $field }}" data-setting="{{ $field }}" type="number" inputmode="numeric" min="{{ $min }}" max="{{ $max }}" step="1" placeholder="{{ __('farm.advanced.preset') }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                            </label>
                        @endforeach
                        {{-- ironing: the slicer smooths the top surface with a hot, almost empty pass; minutes to tens of minutes more --}}
                        @foreach(\App\Domain\Farm\PrintSettings::CHOICES as $field => $words)
                            <label class="text-xs font-semibold text-slate-600 col-span-2">{{ __('farm.advanced.'.$field) }}
                                <select id="farm-adv-{{ $field }}" data-setting="{{ $field }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                                    <option value="">{{ __('farm.advanced.'.$field.'_none') }}</option>
                                    @foreach($words as $word)<option value="{{ $word }}">{{ __('farm.advanced.'.$field.'_'.$word) }}</option>@endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ __('farm.advanced.note') }} {{ __('farm.advanced.ironing_hint') }}</p>
                </details>
                @if(($ironing = $order->modelFile?->processHints()['ironing_type'] ?? 'no ironing') !== 'no ironing')
                    {{-- the design itself asks for an ironed top (a medal's plaque, ModelFile::processHints): said here, the choice above may still change it --}}
                    <p id="farm-design-ironing" class="mt-2 text-xs text-slate-600"><span class="font-semibold text-slate-700">{{ __('farm.advanced.ironing') }}:</span> {{ __('farm.advanced.design_ironing', ['mode' => __('farm.advanced.ironing_'.($ironing === 'top' ? 'top' : 'topmost'))]) }}</p>
                @endif
                <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.size.label') }} <span id="farm-size-pct" class="font-normal text-action-dark"></span> <button id="farm-size-reset" type="button" class="hidden text-xs font-semibold text-action-dark underline">{{ __('farm.size.reset') }}</button></div>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach(['x', 'y', 'z'] as $axis)
                        <label class="text-xs font-semibold text-slate-600">{{ __('farm.size.'.$axis) }} <span class="font-normal text-slate-500">mm</span>
                            <input id="farm-size-{{ $axis }}" type="number" inputmode="decimal" min="1" step="any" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                        </label>
                    @endforeach
                </div>
                <label class="mt-4 block text-sm font-semibold text-slate-700">{{ __('farm.copies.label') }}
                    <input id="farm-copies" type="number" inputmode="numeric" min="1" max="{{ \App\Domain\Farm\PlateLayout::MAX_COPIES }}" class="mt-1 w-32 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                </label>
                <p id="farm-copies-note" class="mt-1 hidden text-xs text-slate-500"></p>
                <label class="mt-4 block text-sm font-semibold text-slate-700">{{ __('farm.units.label') }}
                    <select id="farm-unit" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                        @foreach(array_keys(\App\Domain\Farm\ModelValidator::UNITS) as $u)<option value="{{ $u }}">{{ __('farm.units.'.$u) }}</option>@endforeach
                    </select>
                </label>
                <p id="farm-unit-note" class="mt-1 hidden text-xs text-amber-800"></p>
                <button id="farm-reslice" type="submit" class="btn-secondary mt-3 hidden w-full text-sm">{{ __('farm.recalculate') }}</button>
                {{-- a big model waiting for the choices: one button starts the exact computation for the colour and the settings above --}}
                <div id="farm-slice-box" class="hidden">
                    <p id="farm-slice-hint" class="mt-3 text-xs text-slate-600"></p>
                    <button id="farm-slice-start" type="button" class="mt-2 w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-50">{{ __('farm.order.slice_start') }}</button>
                </div>
            </form>

            {{-- step 3 of 3: the order – what was chosen above (only shown here), delivery, terms, pay --}}
            <form id="farm-pay" class="hidden rounded-2xl border border-line bg-action-soft p-4">
                <h2 class="text-base font-bold text-slate-800">{{ __('farm.order.step_order') }}</h2>
                <p class="mt-1 text-xs text-slate-600">{{ __('farm.order.step_order_hint') }}</p>
                <div class="mt-2 flex items-center justify-between gap-2 text-sm font-semibold text-slate-700">{{ __('farm.order.summary_colors') }}
                    <a href="#farm-material" id="farm-change-colors" class="text-xs font-semibold text-action-dark underline">{{ __('farm.order.change') }}</a></div>
                <ul id="farm-summary" class="mt-2 flex flex-wrap gap-2 text-sm" aria-live="polite"></ul>

                {{-- delivery is chosen before the payment: it changes the price (resources/js/calc/farm.ts asks the server for it) --}}
                <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.order.delivery') }}</div>
                <div class="mt-2 grid gap-2 sm:grid-cols-3" id="farm-delivery">
                    @foreach(['packeta_point', 'packeta_home', 'pickup'] as $mode)
                        <button type="button" data-value="{{ $mode }}" class="seg hidden">{{ __('farm.delivery.'.$mode) }}<span class="block text-xs font-normal text-slate-500" data-price></span></button>
                    @endforeach
                </div>
                <p id="farm-delivery-note" class="mt-2 hidden text-xs text-amber-800"></p>
                <div id="farm-parcel" class="mt-2 hidden">
                    <label class="block text-xs font-semibold text-slate-600">{{ __('farm.delivery.country') }}
                        <select id="farm-country" autocomplete="country" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal"></select>
                    </label>
                    {{-- the Packeta picker fills the hidden fields (resources/js/site/pickup.ts); the favourite point of the profile comes prefilled --}}
                    <div id="farm-point" class="mt-2 hidden rounded-lg border border-slate-300 bg-white p-3 text-sm" data-pickup-box data-key="{{ $packetaKey }}" data-language="{{ app()->getLocale() }}" data-country-field="farm-country" data-none="{{ __('farm.delivery.point_none') }}">
                        @foreach(['id', 'name', 'carrier_id', 'country'] as $f)
                            <input type="hidden" data-pickup="{{ $f }}" value="{{ $prefill['point'][$f] ?? '' }}">
                        @endforeach
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span data-pickup="label" class="font-semibold">{{ ($prefill['point']['name'] ?? '') ?: __('farm.delivery.point_none') }}</span>
                            @if($packetaKey !== '')
                                <button type="button" data-pickup="choose" class="btn-quiet min-h-0 px-3 py-1.5 text-sm">{{ __('farm.delivery.point_choose') }}</button>
                            @else
                                <span class="text-xs text-amber-800">{{ __('farm.delivery.point_unavailable') }}</span>
                            @endif
                        </div>
                    </div>
                    <div id="farm-address" class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach(['name' => 'name', 'phone' => 'tel', 'street' => 'street-address', 'city' => 'address-level2', 'zip' => 'postal-code'] as $f => $autocomplete)
                            <input name="address[{{ $f }}]" placeholder="{{ __('farm.order.address.'.$f) }}" aria-label="{{ __('farm.order.address.'.$f) }}" value="{{ $prefill[$f] ?? '' }}" autocomplete="{{ $autocomplete }}" @if($f === 'phone') type="tel" @endif
                                   @if(in_array($f, ['street', 'city', 'zip'])) data-home @endif class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm {{ $f === 'street' ? 'sm:col-span-2' : '' }}">
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('farm.delivery.phone_hint') }}</p>
                </div>
                @if($guest ?? false)
                    {{-- a guest: the e-mail goes with the card payment; the account is made when the bank confirms it --}}
                    <label class="mt-3 block text-sm font-semibold text-slate-700" for="farm-email">{{ __('farm.guest.email') }}</label>
                    <input id="farm-email" name="email" type="email" required autocomplete="email" maxlength="190" value="{{ $prefill['email'] ?? '' }}" placeholder="{{ __('farm.guest.email_placeholder') }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">{{ __('farm.guest.email_hint') }}</p>
                @endif
                <textarea name="note" rows="2" maxlength="500" placeholder="{{ __('farm.order.note') }}" aria-label="{{ __('farm.order.note') }}" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>

                <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" id="farm-terms" required class="mt-1 h-4 w-4 accent-action">
                    <span>{!! __('farm.order.terms', ['url' => route('farm.terms')]) !!}</span>
                </label>
                <label class="mt-2 flex items-start gap-2 text-sm text-slate-700">
                    {{-- ticked in advance (the customer unticks it); never for models that may show a person or a private photo --}}
                    <input type="checkbox" id="farm-video-consent" class="mt-1 h-4 w-4 accent-action" @checked(! in_array($order->modelFile?->kind(), \App\Domain\YouTube\FarmVideos::PRIVATE_KINDS, true))>
                    <span>{{ __('youtube.consent.label') }}</span>
                </label>

                <p id="farm-pay-error" class="mt-2 hidden text-sm text-red-700" role="alert"></p>
                <p id="farm-card-note" class="mt-2 hidden rounded-lg bg-amber-50 p-3 text-sm text-amber-900" aria-live="polite"></p>
                <a id="farm-topup" href="{{ route('account.credit', ['back' => $order->token]) }}" class="btn-secondary mt-2 hidden w-full text-sm">{{ __('farm.top_up') }}</a>
                <p id="farm-pay-recolor" class="mt-3 hidden text-sm text-amber-900">{{ __('farm.order.recolor_first') }}</p>
                {{-- what is paid: the print and the delivery together, once the delivery is known --}}
                <p id="farm-pay-total" class="mt-3 hidden text-sm text-ink" aria-live="polite"></p>
                {{-- aria-disabled, not disabled: pressed before the order is complete it says what is missing (farm.ts payBlocker) --}}
                <button id="farm-pay-btn" type="submit" class="mt-3 w-full rounded-xl bg-action px-4 py-3 font-semibold text-white aria-disabled:opacity-50" aria-disabled="true">{{ __('farm.order.pay') }}</button>
                {{-- the card takes whole crowns or euros and never less than the gateway's floor: what is over the price stays as credit --}}
                <p id="farm-pay-note" class="mt-2 hidden text-xs text-slate-600" aria-live="polite"></p>
            </form>

            {{-- the customer's YouTube switch, once the order is paid --}}
            @if($order->isCommitted() && ! $order->isTest() && $order->user_id === auth()->id())
                @php($video = $order->video)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="font-semibold text-slate-700">{{ __('youtube.order.title') }}</h2>
                    @include('partials.flash')
                    @include('partials.verify_banner')
                    <p class="mt-1 text-slate-600">{{ __($order->video_consent ? 'youtube.order.on' : 'youtube.order.off') }}</p>
                    @php($wide = $order->videos->firstWhere('kind', 'wide'))
                    @if($order->video_consent && $video?->status === 'published' && $video->watchUrl())
                        <p class="mt-1">{{ __('youtube.order.published') }} <a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener" class="text-action-dark underline">{{ __('youtube.order.watch') }}</a>
                            @if($wide?->status === 'published' && $wide->watchUrl()) · <a href="{{ $wide->watchUrl() }}" target="_blank" rel="noopener" class="text-action-dark underline">{{ __('youtube.order.watch_wide') }}</a>@endif</p>
                    @elseif($order->video_consent && $wide?->status === 'published' && $wide->watchUrl())
                        <p class="mt-1">{{ __('youtube.order.published') }} <a href="{{ $wide->watchUrl() }}" target="_blank" rel="noopener" class="text-action-dark underline">{{ __('youtube.order.watch_wide') }}</a></p>
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
                <a id="farm-repeat" href="{{ route('farm.orders.repeat', $order) }}" class="btn-secondary hidden text-sm" title="{{ __('farm.order.repeat_hint') }}">{{ __('farm.order.repeat') }}</a>
                <a href="{{ route('farm.start') }}" class="btn-quiet text-sm">{{ __('farm.order.new') }}</a>
            </div>
        </div>
    </div>
    {{-- the bar at the bottom of the window while the order waits for its payment, on a desktop and a phone alike
         (components/action-bar.blade.php mirrors the pay button above and forwards its press): what is paid, what is
         still missing; farm.ts fills it and shows it only while the payment box is on the page --}}
    <x-action-bar for="farm-pay-btn" id="farm-bar" hidden>
        <div id="farm-bar-total" class="action-bar-price rounded-md" aria-live="polite"></div>
        <div id="farm-bar-sub" class="action-bar-sub" hidden></div>
        <p id="farm-bar-error" class="text-xs text-red-700" role="alert" hidden></p>
    </x-action-bar>
</div>
@endsection
