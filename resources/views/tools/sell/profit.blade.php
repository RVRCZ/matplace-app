@extends('tools.sell', ['tool' => 'profit', 'lead' => __('sell.profit.lead'), 'payload' => $payload,
    'sections' => ['where' => __('sell.profit.sec.where'), 'sale' => __('sell.profit.sec.sale'), 'month' => __('sell.profit.sec.month')]])

@php
    $units = ['price' => [__('sell.unit.money'), 1], 'cost' => [__('sell.unit.money'), 1], 'ship_charged' => [__('sell.unit.money'), 1], 'ship_actual' => [__('sell.unit.money'), 1], 'discount_pct' => ['%', 1],
        'monthly_pieces' => [__('sell.unit.pieces'), 1], 'fixed_monthly' => [__('sell.unit.money'), 1], 'stall_fee' => [__('sell.unit.money'), 1], 'stall_pieces' => [__('sell.unit.pieces'), 1]];
    $num = fn (string $key, string $when = '') => view('tools.sell._num', ['key' => $key, 'label' => __('sell.profit.f.'.$key), 'unit' => $units[$key][0], 'min' => \App\Domain\Sell\Profit::FIELDS[$key][0], 'max' => \App\Domain\Sell\Profit::FIELDS[$key][1], 'step' => $units[$key][1], 'value' => $values[$key], 'hint' => __('sell.profit.f.'.$key.'.hint'), 'attrs' => $when])->render();
    $platforms = array_keys((array) config('sell.platforms'));
@endphp

@section('panel')
<form id="sell-form" novalidate>
    <x-tool-section id="where" :title="__('sell.profit.sec.where')">
        <fieldset>
            <legend class="lbl">{{ __('sell.profit.platform') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($platforms as $i => $p)
                    <label class="tool-choice"><input type="radio" name="platform" data-choice="platform" value="{{ $p }}" class="sr-only" @checked($p === $values['platform'])>{{ __('sell.platform.'.$p) }}</label>
                @endforeach
            </div>
            <p id="profit-platform-note" class="hint mt-2 !text-xs"></p>
        </fieldset>
        <label class="flex items-start gap-3 text-sm text-ink">
            <input data-flag="vat" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked($values['vat'])>
            <span><span class="font-medium">{{ __('sell.profit.vat') }}</span><br><span class="text-muted">{{ __('sell.profit.vat.hint', ['pct' => (int) config('sell.vat_pct')]) }}</span></span>
        </label>
        <label class="flex items-start gap-3 text-sm text-ink" data-when="platform=etsy">
            <input data-flag="foreign" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked($values['foreign'])>
            <span><span class="font-medium">{{ __('sell.profit.foreign') }}</span><br><span class="text-muted">{{ __('sell.profit.foreign.hint') }}</span></span>
        </label>
    </x-tool-section>
    <x-tool-section id="sale" :title="__('sell.profit.sec.sale')">
        <div class="grid gap-3">{!! $num('price') !!}{!! $num('cost') !!}{!! $num('discount_pct') !!}{!! $num('ship_charged') !!}{!! $num('ship_actual') !!}</div>
        <p class="hint !text-xs"><a href="{{ route('tools.cost') }}" class="underline">{{ __('sell.profit.cost_link') }}</a></p>
    </x-tool-section>
    <x-tool-section id="month" :title="__('sell.profit.sec.month')">
        <div class="grid gap-3">
            {!! $num('monthly_pieces') !!}{!! $num('fixed_monthly') !!}
            <div class="grid gap-3" data-when="platform=fair">{!! $num('stall_fee') !!}{!! $num('stall_pieces') !!}</div>
        </div>
        <button type="button" id="sell-reset" class="btn-quiet w-full text-sm">{{ __('sell.reset') }}</button>
    </x-tool-section>
</form>
@endsection

@section('stage')
<div class="card p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.profit.result') }}</div>
    <div class="mt-1 flex flex-wrap items-baseline gap-x-6 gap-y-1">
        <div><span id="profit-total" class="num text-3xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.profit.per_piece') }}</span></div>
        <div><span id="profit-margin" class="num text-xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.profit.margin') }}</span></div>
        <div><span id="profit-net" class="num text-xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.profit.net') }}</span></div>
    </div>
    <table class="num mt-3 w-full text-sm"><tbody id="profit-rows" class="divide-y divide-line"></tbody></table>
    <p id="profit-break" class="mt-3 text-sm text-muted"></p>
    <p id="profit-asof" class="mt-1 text-xs text-muted"></p>
</div>
<div class="card p-4">
    <div class="font-semibold text-ink">{{ __('sell.profit.three.title') }}</div>
    <p class="mt-1 text-sm text-muted">{{ __('sell.profit.three.text') }}</p>
    <table class="num mt-3 w-full text-sm"><thead><tr class="text-left text-xs uppercase text-muted"><th class="py-1 pr-3">{{ __('sell.profit.three.price') }}</th><th class="py-1 pr-3">{{ __('sell.profit.three.fees') }}</th><th class="py-1 pr-3">{{ __('sell.profit.three.profit') }}</th><th class="py-1">{{ __('sell.profit.three.margin') }}</th></tr></thead><tbody id="profit-three" class="divide-y divide-line"></tbody></table>
</div>
<div class="card p-4">
    <div class="font-semibold text-ink">{{ __('sell.profit.next.title') }}</div>
    <p class="mt-1 text-sm text-muted">{{ __('sell.profit.next.text') }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
        <a id="profit-to-plan" href="{{ route('tools.plan') }}" class="btn-secondary text-sm">{{ __('sell.profit.next.plan') }}</a>
        <a href="{{ route('tools.vendors') }}" class="btn-quiet text-sm">{{ __('sell.profit.next.vendors') }}</a>
    </div>
</div>
@endsection
