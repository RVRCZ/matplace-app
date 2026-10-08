@extends('tools.sell', ['tool' => 'plan', 'lead' => __('sell.plan.lead'), 'payload' => $payload,
    'sections' => ['products' => __('sell.plan.sec.products'), 'costs' => __('sell.plan.sec.costs'), 'season' => __('sell.plan.sec.season'), 'save' => __('sell.plan.sec.save')]])

@section('panel')
<form id="sell-form" novalidate>
    <x-tool-section id="products" :title="__('sell.plan.sec.products')">
        <p class="hint !text-xs">{{ __('sell.plan.products.hint') }}</p>
        <div id="plan-products" class="grid gap-2"></div>
        <template id="plan-product-row">
            <div class="rounded-lg border border-line p-2 text-sm" data-product>
                <div class="flex items-center gap-2">
                    <input data-prod="name" type="text" maxlength="60" placeholder="{{ __('sell.plan.p.name') }}" class="field !mt-0 min-w-0 flex-1" aria-label="{{ __('sell.plan.p.name') }}">
                    <button type="button" data-remove class="tool-icon-btn shrink-0" title="{{ __('sell.plan.p.remove') }}" aria-label="{{ __('sell.plan.p.remove') }}"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    <label class="text-xs text-muted">{{ __('sell.plan.p.cost') }}<span class="tool-unit mt-0.5 !w-full" data-unit="{{ __('sell.unit.money') }}"><input data-prod="cost" type="number" inputmode="decimal" min="0" max="10000000" step="1" value="0" class="field !mt-0"></span></label>
                    <label class="text-xs text-muted">{{ __('sell.plan.p.price') }}<span class="tool-unit mt-0.5 !w-full" data-unit="{{ __('sell.unit.money') }}"><input data-prod="price" type="number" inputmode="decimal" min="0" max="10000000" step="1" value="0" class="field !mt-0"></span></label>
                    <label class="text-xs text-muted">{{ __('sell.plan.p.qty') }}<span class="tool-unit mt-0.5 !w-full" data-unit="{{ __('sell.unit.pcs_month') }}"><input data-prod="qty" type="number" inputmode="decimal" min="0" max="1000000" step="1" value="0" class="field !mt-0 !pr-16"></span></label>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink">
                    <label class="flex items-center gap-1.5"><input data-prod="photo" type="checkbox" class="h-4 w-4 accent-ink">{{ __('sell.plan.p.photo') }}</label>
                    <label class="flex items-center gap-1.5"><input data-prod="listed" type="checkbox" class="h-4 w-4 accent-ink">{{ __('sell.plan.p.listed') }}</label>
                </div>
            </div>
        </template>
        <button type="button" id="plan-add" class="btn-quiet w-full gap-1.5 text-sm"><x-icon name="plus" class="h-4 w-4" />{{ __('sell.plan.p.add') }}</button>
        <p class="hint !text-xs"><a href="{{ route('tools.cost') }}" class="underline">{{ __('sell.plan.cost_link') }}</a> · <a href="{{ route('tools.profit') }}" class="underline">{{ __('sell.plan.profit_link') }}</a></p>
    </x-tool-section>
    <x-tool-section id="costs" :title="__('sell.plan.sec.costs')">
        <div class="grid gap-3">
            @include('tools.sell._num', ['key' => 'fixed', 'label' => __('sell.plan.f.fixed'), 'unit' => __('sell.unit.per_month'), 'min' => 0, 'max' => 100000000, 'step' => 1, 'value' => 0, 'hint' => __('sell.plan.f.fixed.hint'), 'attrs' => 'class="field !mt-0 !pr-16"'])
            <label class="block text-sm font-medium text-ink">{{ __('sell.plan.f.channel') }}
                <select data-param-text="channel" class="field">
                    <option value="">{{ __('sell.plan.f.channel.none') }}</option>
                    @foreach(array_keys((array) config('sell.platforms')) as $p)<option value="{{ $p }}">{{ __('sell.platform.'.$p) }}</option>@endforeach
                </select>
                <span class="mt-1 block text-xs font-normal text-muted">{{ __('sell.plan.f.channel.hint') }}</span>
            </label>
            <label class="block text-sm font-medium text-ink">{{ __('sell.plan.f.start') }}
                <select data-param-text="start" class="field">
                    @for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected($m === $payload['start'])>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->isoFormat('MMMM') }}</option>@endfor
                </select>
            </label>
        </div>
    </x-tool-section>
    <x-tool-section id="season" :title="__('sell.plan.sec.season')">
        <div class="flex flex-wrap gap-1.5" role="radiogroup">
            @foreach(array_keys((array) config('sell.seasons')) as $i => $s)
                <label class="tool-choice"><input type="radio" name="season" data-choice="season" value="{{ $s }}" class="sr-only" @checked($i === 0)>{{ __('sell.plan.season.'.$s) }}</label>
            @endforeach
            <label class="tool-choice"><input type="radio" name="season" data-choice="season" value="custom" class="sr-only">{{ __('sell.plan.season.custom') }}</label>
        </div>
        <p class="hint !text-xs">{{ __('sell.plan.season.hint') }}</p>
        <div id="plan-months" class="grid grid-cols-6 gap-1.5 text-xs" data-when="season=custom">
            @for($m = 1; $m <= 12; $m++)
                <label class="text-center text-muted">{{ \Illuminate\Support\Carbon::create(null, $m, 1)->isoFormat('MMM') }}<input data-month="{{ $m }}" type="number" inputmode="decimal" min="0" max="10" step="0.1" value="1" class="field !mt-0.5 !min-h-9 !px-1 text-center"></label>
            @endfor
        </div>
    </x-tool-section>
    <x-tool-section id="save" :title="__('sell.plan.sec.save')">
        <label class="block text-sm font-medium text-ink">{{ __('sell.plan.f.name') }}<input data-param-text="name" type="text" maxlength="80" class="field" placeholder="{{ __('sell.plan.f.name.placeholder') }}"></label>
        <p class="hint !text-xs">{{ __('sell.plan.save.local') }}</p>
        @if($payload['plans'])
            <div class="flex flex-wrap gap-2">
                <button type="button" id="plan-save" class="btn-secondary flex-1 text-sm">{{ __('sell.plan.save.account') }}</button>
                <select id="plan-saved" class="field !mt-0 min-w-0 flex-1" aria-label="{{ __('sell.plan.save.open') }}"><option value="">{{ __('sell.plan.save.open') }}</option></select>
            </div>
            <p id="plan-save-msg" class="hint !text-xs" aria-live="polite"></p>
        @else
            <p class="hint !text-xs">{{ __('sell.plan.save.login') }}</p>
        @endif
        <div class="flex flex-wrap gap-2">
            <button type="button" id="plan-csv" class="btn-quiet flex-1 text-sm">{{ __('sell.plan.export.csv') }}</button>
            <button type="button" id="plan-pdf" class="btn-quiet flex-1 text-sm">{{ __('sell.plan.export.pdf') }}</button>
        </div>
        <button type="button" id="sell-reset" class="btn-quiet w-full text-sm">{{ __('sell.plan.reset') }}</button>
    </x-tool-section>
</form>
<form id="plan-pdf-form" method="post" action="{{ route('tools.plan.pdf') }}" class="hidden">@csrf<input type="hidden" name="plan" value=""></form>
@endsection

@section('stage')
<div class="card p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.plan.result') }}</div>
    <div class="mt-1 flex flex-wrap items-baseline gap-x-6 gap-y-1">
        <div><span id="plan-profit" class="num text-3xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.plan.year_profit') }}</span></div>
        <div><span id="plan-revenue" class="num text-xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.plan.year_revenue') }}</span></div>
        <div><span id="plan-pieces" class="num text-xl font-semibold text-ink">—</span> <span class="text-sm text-muted">{{ __('sell.plan.year_pieces') }}</span></div>
    </div>
    <p id="plan-break" class="mt-2 text-sm text-muted"></p>
    <svg id="plan-chart" viewBox="0 0 720 260" class="mt-3 h-auto w-full" role="img" aria-label="{{ __('sell.plan.chart') }}"></svg>
    <div class="mt-1 flex flex-wrap gap-x-4 text-xs text-muted"><span><span class="inline-block h-3 w-3 rounded-sm align-middle" style="background:#cfc9c0"></span> {{ __('sell.plan.col.revenue') }}</span><span><span class="inline-block h-3 w-3 rounded-sm align-middle" style="background:#54966f"></span> {{ __('sell.plan.col.profit') }}</span><span><span class="inline-block h-3 w-3 rounded-full align-middle" style="background:#e96e2c"></span> {{ __('sell.plan.col.cumulative') }}</span></div>
    <div class="mt-3 overflow-x-auto">
        <table class="num w-full text-sm"><thead><tr class="text-left text-xs uppercase text-muted"><th class="py-1 pr-3">{{ __('sell.plan.col.month') }}</th><th class="py-1 pr-3 text-right">{{ __('sell.plan.col.revenue') }}</th><th class="py-1 pr-3 text-right">{{ __('sell.plan.col.costs') }}</th><th class="py-1 pr-3 text-right">{{ __('sell.plan.col.profit') }}</th><th class="py-1 text-right">{{ __('sell.plan.col.cumulative') }}</th></tr></thead><tbody id="plan-rows" class="divide-y divide-line"></tbody></table>
    </div>
</div>
<div class="card p-4">
    <div class="font-semibold text-ink">{{ __('sell.plan.steps.title') }}</div>
    <p class="mt-1 text-sm text-muted">{{ __('sell.plan.steps.text') }}</p>
    <ul id="plan-steps" class="mt-3 space-y-1.5 text-sm"></ul>
</div>
@endsection
