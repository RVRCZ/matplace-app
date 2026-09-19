@extends('layouts.app', ['title' => $initial ? ($initial['file']['name'] ?? 'matplace') . ' · matplace' : 'matplace'])

@php
    $formats = strtoupper(implode(', ', $config['formats']));
    $i18n = collect([
        'calc.status.reading','calc.status.rough','calc.status.uploading','calc.status.queued','calc.status.done',
        'calc.status.failed','calc.status.converting','calc.price.from','calc.price.range','calc.price.per_piece',
        'calc.days','calc.cta.copied','calc.error.read','calc.error.upload','calc.error.too_big','calc.est_only',
        'calc.profile.budget','calc.profile.standard','calc.profile.express','calc.breakdown.material',
        'calc.breakdown.time','calc.breakdown.setup','calc.breakdown.total',
        'calc.warn.exceeds_typical_bed','calc.warn.supports_added','calc.warn.not_watertight',
        'calc.warn.multiple_shells','calc.warn.flipped_normals',
    ])->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $config['max_upload_mb'], 'n' => ':n'])])->all();
@endphp

@push('head')
<script>
    window.MP_CONFIG = @json($config);
    window.MP_I18N = @json($i18n);
    window.MP_INITIAL = @json($initial);
    window.MP_ROUTES = { uploads: @json(route('api.uploads.store')), calculations: @json(route('api.calculations.store')), calcShow: @json(url('/api/calculations')), files: @json(url('/api/files')) };
</script>
@endpush

@section('content')
<div id="calculator" data-state="idle">

    {{-- ── Hero: one field ──────────────────────────────────────────── --}}
    <section id="hero" class="calc-hero">
        <h1 class="text-2xl font-extrabold leading-tight sm:text-4xl">{{ __('app.tagline') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('app.subline') }}</p>

        <label id="dropzone" class="mt-6 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-teal-300 bg-white px-4 py-10 text-center transition hover:border-teal-500 hover:bg-teal-50">
            <svg class="h-10 w-10 text-teal-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            <span class="mt-3 text-lg font-semibold">{{ __('hero.drop') }}</span>
            <span class="mt-1 text-sm text-slate-500">{{ __('hero.formats', ['formats' => $formats, 'max' => $config['max_upload_mb']]) }}</span>
            <input id="file-input" type="file" class="sr-only" accept="{{ implode(',', array_map(fn ($f) => '.' . $f, $config['formats'])) }}">
        </label>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <button type="button" class="rounded-full bg-teal-600 px-4 py-2 font-semibold text-white" onclick="document.getElementById('file-input').click()">{{ __('hero.choose_file') }}</button>
            <button type="button" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-slate-400" disabled title="{{ __('hero.soon') }}">📷 {{ __('hero.photo') }} <span class="text-xs">({{ __('hero.soon') }})</span></button>
            <button type="button" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-slate-400" disabled title="{{ __('hero.soon') }}">✍️ {{ __('hero.text') }} <span class="text-xs">({{ __('hero.soon') }})</span></button>
        </div>
        <p id="hero-error" class="mt-3 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>

        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ([['broken','📸'],['idea','💡'],['file','📄'],['printer','🖨️'],['designer','🧩']] as [$k,$ico])
                <a href="{{ $k === 'file' ? '#dropzone' : '#' }}" class="calc-tile {{ $k === 'file' ? 'ring-2 ring-teal-500' : 'opacity-70' }}" @if($k==='file') onclick="document.getElementById('file-input').click();return false;" @endif>
                    <span class="text-2xl">{{ $ico }}</span>
                    <span class="mt-1 font-semibold">{{ __('tiles.'.$k) }}</span>
                    <span class="text-xs text-slate-500">{{ __('tiles.'.$k.'.hint') }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ── Result: viewer + controls + price ─────────────────────────── --}}
    <section id="result" class="hidden">
        <div class="grid gap-4 lg:grid-cols-[1.2fr_1fr]">
            <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <canvas id="viewer" class="block h-[45vh] w-full touch-none lg:h-[70vh]"></canvas>
                <div class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-slate-700 shadow" id="file-badge"></div>
                <div class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs text-slate-600 shadow" id="dims-badge"></div>
            </div>

            <div class="flex flex-col gap-4">
                {{-- price panel --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span id="status" class="font-medium text-slate-600">{{ __('calc.status.reading') }}</span>
                        <span id="status-spinner" class="h-4 w-4 animate-spin rounded-full border-2 border-teal-600 border-t-transparent"></span>
                    </div>
                    <div class="mt-2 flex items-end gap-2">
                        <span id="price-main" class="text-4xl font-extrabold tracking-tight">—</span>
                        <span class="pb-1 text-slate-500">{{ $config['currency'] === 'CZK' ? 'Kč' : $config['currency'] }}</span>
                    </div>
                    <div id="price-sub" class="mt-1 text-sm text-slate-500"></div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div><dt class="text-slate-500">{{ __('calc.weight') }}</dt><dd id="stat-grams" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('calc.time') }}</dt><dd id="stat-time" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('calc.lead') }}</dt><dd id="stat-lead" class="font-semibold">—</dd></div>
                    </dl>
                    <ul id="warnings" class="mt-3 space-y-1 text-sm text-amber-700"></ul>
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-teal-700">{{ __('calc.breakdown') }}</summary>
                        <div id="breakdown" class="mt-2 space-y-2"></div>
                    </details>
                </div>

                {{-- controls --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-sm font-semibold text-slate-700">{{ __('calc.material') }}</div>
                    <div id="materials" class="mt-2 flex flex-wrap gap-2"></div>
                    <p id="material-hint" class="mt-1 text-xs text-slate-500"></p>

                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('calc.quality') }}</div>
                    <div id="quality" class="mt-2 grid grid-cols-3 gap-2">
                        @foreach ($config['qualities'] as $q)
                            <button type="button" data-quality="{{ $q }}" class="seg {{ $q === 'standard' ? 'seg-on' : '' }}">{{ __('calc.quality.'.$q) }}<span class="block text-xs font-normal text-slate-500">{{ $config['rough']['quality_layer_mm'][$q] }} mm</span></button>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between text-sm font-semibold text-slate-700">
                        <span>{{ __('calc.infill') }}</span><span id="infill-val" class="text-teal-700">15 %</span>
                    </div>
                    <input id="infill" type="range" min="5" max="100" step="5" value="15" class="mt-1 w-full accent-teal-600">
                    <div class="flex justify-between text-xs text-slate-500"><span>{{ __('calc.infill.light') }}</span><span>{{ __('calc.infill.solid') }}</span></div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <label class="text-sm font-semibold text-slate-700">{{ __('calc.quantity') }}
                            <input id="quantity" type="number" min="1" max="1000" value="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal">
                        </label>
                        <label class="text-sm font-semibold text-slate-700">{{ __('calc.scale') }} <span id="scale-val" class="font-normal text-teal-700">100 %</span>
                            <input id="scale" type="range" min="25" max="{{ (int) ($config['max_scale'] * 100) }}" step="5" value="100" class="mt-3 w-full accent-teal-600">
                        </label>
                    </div>

                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-teal-700">{{ __('calc.more') }}</summary>
                        <div class="mt-2 text-sm font-semibold text-slate-700">{{ __('calc.supports') }}</div>
                        <div id="supports" class="mt-2 grid grid-cols-3 gap-2">
                            <button type="button" data-supports="auto" class="seg seg-on">{{ __('calc.supports.auto') }}</button>
                            <button type="button" data-supports="1" class="seg">{{ __('calc.supports.yes') }}</button>
                            <button type="button" data-supports="0" class="seg">{{ __('calc.supports.no') }}</button>
                        </div>
                    </details>
                </div>

                {{-- actions --}}
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button id="cta-make" type="button" class="rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white disabled:opacity-60" title="{{ __('calc.cta.make.soon') }}">{{ __('calc.cta.make') }}</button>
                    <a id="cta-download" href="#" class="rounded-xl border border-teal-600 px-4 py-3 text-center font-semibold text-teal-700 aria-disabled:opacity-50" aria-disabled="true">{{ __('calc.cta.download') }}</a>
                    <button id="cta-share" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700">{{ __('calc.cta.share') }}</button>
                    <button id="cta-new" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700">{{ __('calc.cta.new') }}</button>
                </div>
                <p id="make-note" class="hidden text-sm text-slate-500">{{ __('calc.cta.make.soon') }}</p>
            </div>
        </div>
    </section>
</div>
@endsection
