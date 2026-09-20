@extends('layouts.app', ['title' => __('tools.'.$kind.'.title').' · matplace'])

@php
    $integer = fn (array $f) => $f[3] === 1;
    $unit = fn (string $k) => in_array($k, ['rows', 'cols', 'count'], true) ? '' : ($k === 'angle' ? '°' : 'mm');
    $main = ['organizer' => ['width', 'depth', 'height', 'rows', 'cols'], 'box' => ['inner_w', 'inner_d', 'inner_h'], 'phone_stand' => ['width', 'device', 'angle', 'back'], 'cable_holder' => ['count', 'cable']][$kind];
    $i18n = collect(['param.working', 'param.failed', 'param.estimate', 'param.outer', 'param.inner', 'param.cell', 'param.slot', 'param.hole', 'param.hole.remove', 'param.creating', 'param.too_many_holes',
        'param.wall.front', 'param.wall.back', 'param.wall.left', 'param.wall.right', 'param.shape.circle', 'param.shape.rect', 'param.hole.w', 'param.hole.d', 'param.hole.h', 'param.hole.x', 'param.hole.z',
        'param.part.body', 'param.part.lid', 'param.part.all'])->mapWithKeys(fn ($k) => [$k => __($k)])->all();
    $colors = ['white', 'black', 'grey', 'red', 'blue', 'green', 'yellow', 'orange', 'any'];
@endphp

@push('head')
<script>
    window.MP_PARAM = {
        kind: @json($kind),
        preview: @json(route('api.tools.param.preview')),
        create: @json(route('api.tools.param')),
        home: @json(route('home')),
        presets: {{ \Illuminate\Support\Js::from($presets) }},
        config: {{ \Illuminate\Support\Js::from($config) }},
        locale: @json(app()->getLocale()),
        i18n: {{ \Illuminate\Support\Js::from($i18n) }},
    };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-6xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ __('tools.'.$kind.'.title') }}</h1>
    <p class="hint">{{ __('param.'.$kind.'.lead') }}</p>

    <ol class="steps mt-3" aria-label="{{ __('param.steps') }}">
        <li aria-current="step"><span class="step-no">1</span>{{ __('param.step.settings') }}</li>
        <li aria-current="step"><span class="step-no">2</span>{{ __('param.step.preview') }}</li>
        <li><span class="step-no">3</span>{{ __('param.step.inquiry') }}</li>
    </ol>

    @unless($available)
        <div class="note-warn mt-4">{{ __('sign.unavailable') }}</div>
    @else
    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
        {{-- 1 · settings --}}
        <form id="param-form" class="card space-y-5 p-5" novalidate>
            @if($presets)
                <fieldset>
                    <legend class="lbl">{{ __('param.presets') }}</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($presets as $key => $values)
                            <button type="button" class="chip" data-preset="{{ $key }}">{{ __('param.preset.'.$key) }}</button>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <fieldset>
                <legend class="lbl">{{ __('param.'.$kind.'.size') }}</legend>
                <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($main as $key)
                        @php $f = $fields[$key]; @endphp
                        <label class="text-sm font-medium text-ink">{{ __('param.f.'.$key) }} @if($unit($key))<span class="font-normal text-muted">{{ $unit($key) }}</span>@endif
                            <input data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $f[0] }}" max="{{ $f[1] }}" step="{{ $f[3] }}" value="{{ $f[2] }}" class="field" aria-describedby="range-{{ $key }}">
                            <span id="range-{{ $key }}" class="text-xs text-muted">{{ $f[0] }}–{{ $f[1] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            @foreach($flags as $flag)
                <label class="flex items-start gap-3 text-sm text-ink">
                    <input data-flag="{{ $flag }}" type="checkbox" class="mt-1 h-5 w-5 accent-action" @checked($flag === 'cable')>
                    <span><span class="font-semibold">{{ __('param.flag.'.$flag) }}</span><br><span class="text-muted">{{ __('param.flag.'.$flag.'.hint') }}</span></span>
                </label>
            @endforeach

            @if($kind === 'box')
                <fieldset>
                    <legend class="lbl">{{ __('param.holes') }}</legend>
                    <p class="hint">{{ __('param.holes.hint') }}</p>
                    <div id="holes" class="mt-2 space-y-2"></div>
                    <button type="button" id="add-hole" class="btn-quiet mt-2 text-sm">+ {{ __('param.holes.add') }}</button>
                </fieldset>
            @endif

            <details class="text-sm">
                <summary class="cursor-pointer font-semibold text-action-dark">{{ __('param.more') }}</summary>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($fields as $key => $f)
                        @continue(in_array($key, $main, true))
                        <label class="text-sm font-medium text-ink">{{ __('param.f.'.$key) }} <span class="font-normal text-muted">{{ $unit($key) }}</span>
                            <input data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $f[0] }}" max="{{ $f[1] }}" step="{{ $f[3] }}" value="{{ $f[2] }}" class="field">
                            <span class="text-xs text-muted">{{ $f[0] }}–{{ $f[1] }}</span>
                        </label>
                    @endforeach
                </div>
                @if($kind === 'box')<p class="hint mt-2">{{ __('param.clearance.hint') }}</p>@endif
            </details>

            <fieldset class="border-t border-line pt-4">
                <legend class="sr-only">{{ __('param.order') }}</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <label class="lbl">{{ __('calc.material') }}
                        <select id="param-material" class="field">
                            @foreach($config['materials'] as $m)<option value="{{ $m['code'] }}" @selected($m['code'] === $config['default_material'])>{{ $m['label'] }} ({{ $m['code'] }})</option>@endforeach
                        </select>
                    </label>
                    <label class="lbl">{{ __('param.color') }}
                        <select id="param-color" class="field">
                            @foreach($colors as $c)<option value="{{ $c }}">{{ __('color.'.$c) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="lbl">{{ __('calc.quantity') }}
                        <input id="param-qty" type="number" inputmode="numeric" min="1" max="1000" value="1" class="field">
                    </label>
                </div>
            </fieldset>
        </form>

        {{-- 2 · preview and price, 3 · on to the inquiry --}}
        <div class="space-y-4">
            <div class="card overflow-hidden">
                <div class="relative">
                    <canvas id="param-viewer" class="block h-[42vh] w-full touch-none lg:h-[52vh]" role="img" aria-label="{{ __('param.viewer') }}"></canvas>
                    <div id="param-busy" class="absolute right-3 top-3 hidden rounded-full bg-white/90 px-3 py-1 text-xs text-muted shadow">{{ __('param.working') }}</div>
                </div>
                <dl id="param-dims" class="grid grid-cols-1 gap-x-4 gap-y-1 border-t border-line px-4 py-3 text-sm sm:grid-cols-2" aria-live="polite"></dl>
            </div>

            <div id="param-error" class="note-error hidden" role="alert"></div>

            <div class="card p-5">
                <div class="text-sm text-muted">{{ __('param.estimate.title') }}</div>
                <div id="param-price" class="text-3xl font-extrabold text-ink" aria-live="polite">—</div>
                <div id="param-price-sub" class="text-sm text-muted"></div>
                <p class="mt-2 text-xs text-muted">{{ __('param.estimate.note') }}</p>

                <button id="param-go" type="button" class="btn-primary mt-4 w-full">{{ __('param.go') }}</button>
                <p class="mt-2 text-xs text-muted">{{ __('param.go.hint') }}</p>

                <div class="mt-4 border-t border-line pt-3">
                    <div class="text-sm font-semibold text-ink">{{ __('param.download') }}</div>
                    <div id="param-downloads" class="mt-2 flex flex-wrap gap-2"></div>
                </div>
            </div>
            <p class="text-xs text-muted">{{ __('param.'.$kind.'.tip') }}</p>
        </div>
    </div>
    @endunless
</div>
@endsection
