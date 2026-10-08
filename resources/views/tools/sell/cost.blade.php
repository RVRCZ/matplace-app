@extends('tools.sell', ['tool' => 'cost', 'lead' => __('sell.cost.lead'), 'payload' => $payload,
    'sections' => ['piece' => __('sell.cost.sec.piece'), 'printer' => __('sell.cost.sec.printer'), 'work' => __('sell.cost.sec.work'), 'price' => __('sell.cost.sec.price')]])

@php
    // field → unit, step; the limits come from App\Domain\Sell\Cost::FIELDS, the values from config (or the calculation / the printer's profile)
    $units = ['filament_kg' => [__('sell.unit.per_kg'), 1], 'grams' => ['g', 0.1], 'hours' => ['h', 0.1], 'watts' => ['W', 1], 'kwh' => [__('sell.unit.per_kwh'), 0.1], 'printer_price' => [__('sell.unit.money'), 1], 'printer_hours' => ['h', 1],
        'scrap_pct' => ['%', 1], 'labour_rate' => [__('sell.unit.per_hour'), 1], 'labour_minutes' => ['min', 1], 'other' => [__('sell.unit.money'), 1], 'margin_pct' => ['%', 1]];
    $num = fn (string $key) => view('tools.sell._num', ['key' => $key, 'label' => __('sell.cost.f.'.$key), 'unit' => $units[$key][0], 'min' => \App\Domain\Sell\Cost::FIELDS[$key][0], 'max' => \App\Domain\Sell\Cost::FIELDS[$key][1], 'step' => $units[$key][1], 'value' => $values[$key], 'hint' => __('sell.cost.f.'.$key.'.hint')])->render();
@endphp

@section('panel')
<form id="sell-form" novalidate>
    @if($from)
        <p class="note-ok mb-3 text-sm">{{ __('sell.cost.from', ['name' => $from['name'], 'g' => $from['grams'], 'h' => $from['hours']]) }}</p>
    @endif
    @if($printer)
        <p class="hint mb-3 !text-xs">{{ __('sell.cost.printer_prefill') }}</p>
    @endif
    <x-tool-section id="piece" :title="__('sell.cost.sec.piece')">
        <div class="grid gap-3">{!! $num('filament_kg') !!}{!! $num('grams') !!}{!! $num('hours') !!}</div>
    </x-tool-section>
    <x-tool-section id="printer" :title="__('sell.cost.sec.printer')">
        <div class="grid gap-3">{!! $num('watts') !!}{!! $num('kwh') !!}{!! $num('printer_price') !!}{!! $num('printer_hours') !!}{!! $num('scrap_pct') !!}</div>
    </x-tool-section>
    <x-tool-section id="work" :title="__('sell.cost.sec.work')">
        <div class="grid gap-3">{!! $num('labour_rate') !!}{!! $num('labour_minutes') !!}{!! $num('other') !!}</div>
    </x-tool-section>
    <x-tool-section id="price" :title="__('sell.cost.sec.price')">
        <div class="grid gap-3">{!! $num('margin_pct') !!}</div>
        <p class="hint !text-xs">{{ __('sell.cost.as_of', ['date' => \Illuminate\Support\Carbon::parse(config('sell.cost.as_of'))->isoFormat('LL')]) }}</p>
        <button type="button" id="sell-reset" class="btn-quiet w-full text-sm">{{ __('sell.reset') }}</button>
    </x-tool-section>
</form>
@endsection

@section('stage')
<div class="card p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.cost.result') }}</div>
    <div class="mt-1 flex flex-wrap items-baseline gap-x-6 gap-y-1">
        <div><span id="cost-total" class="num text-3xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.cost.per_piece') }}</span></div>
        <div><span id="cost-price" class="num text-xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.cost.with_margin') }}</span></div>
    </div>
    <table class="num mt-3 w-full text-sm">
        <tbody id="cost-rows" class="divide-y divide-line"></tbody>
    </table>
    <p id="cost-hour" class="mt-3 text-sm text-muted"></p>
</div>
@if($from)
    <div class="card p-4">
        <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.cost.us.title') }}</div>
        <div class="mt-1 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <span class="num text-2xl font-semibold text-ink">{{ \App\Support\Money::show($from['price']) }}</span>
            <span id="cost-us-diff" class="text-sm text-muted"></span>
        </div>
        <p class="mt-2 text-sm text-muted">{{ __('sell.cost.us.text') }}</p>
        <a href="{{ $from['url'] }}" class="btn-primary mt-3 gap-1.5"><x-icon name="printer" class="h-4 w-4" />{{ __('sell.cost.us.go') }}</a>
    </div>
@endif
<div class="card p-4">
    <div class="font-semibold text-ink">{{ __('sell.cost.next.title') }}</div>
    <p class="mt-1 text-sm text-muted">{{ __('sell.cost.next.text') }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
        <a id="cost-to-profit" href="{{ route('tools.profit') }}" class="btn-secondary text-sm">{{ __('sell.cost.next.profit') }}</a>
        <a id="cost-to-plan" href="{{ route('tools.plan') }}" class="btn-quiet text-sm">{{ __('sell.cost.next.plan') }}</a>
    </div>
</div>
@endsection
