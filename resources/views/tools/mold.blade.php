@extends('layouts.app', ['title' => __('tools.mold.title').' · matplace'])

@php
    $i18n = collect(['mold.page.bad_format', 'mold.page.too_big', 'mold.page.uploading', 'mold.page.processing', 'mold.page.building', 'mold.page.failed', 'mold.page.model_failed',
        'mold.unavailable', 'mold.report', 'mold.report.parts', 'mold.report.filled', 'mold.page.analysing', 'mold.page.analysis.none', 'mold.page.analysis.rigid', 'mold.page.analysis.flexible', 'mold.page.analysis.silicone', 'mold.page.options', 'mold.page.building.fill', 'calc.tip.mold.parts', 'mold.page.cast', 'mold.report.silicone', 'mold.report.silicone.how', 'mold.report.angle', 'mold.report.large', 'mold.verdict.rigid', 'mold.verdict.flexible', 'mold.verdict.silicone',
        'calc.tip.mold', 'calc.tip.mold.silicone'])
        ->mapWithKeys(fn ($k) => [$k => \App\Support\NextStep::text($k, ['max' => $config['max_upload_mb']])])->all();
@endphp

@push('head')
<script>
    window.MP_I18N = {{ \Illuminate\Support\Js::from($i18n) }};
    window.MP_MOLD = { upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), home: @json(route('home')), from: @json($from), formats: {{ \Illuminate\Support\Js::from($config['formats']) }}, maxMb: {{ (int) $config['max_upload_mb'] }} };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ __('tools.mold.title') }}</h1>
    <p class="hint">{{ __('mold.lead') }}</p>

    @unless($available)
        <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ __('mold.unavailable') }}</div>
    @else
    <form id="mold-form" class="mt-4 grid gap-4 lg:grid-cols-[1fr_280px]">
        <div class="space-y-4">
            <label id="mold-drop" class="card block cursor-pointer border-2 border-dashed p-6 text-center hover:border-action">
                <input id="mold-file" type="file" class="sr-only" accept="{{ collect($config['formats'])->map(fn ($f) => '.'.$f)->join(',') }}">
                <span id="mold-pick" class="block text-lg font-bold text-ink">{{ __('mold.page.pick') }}</span>
                <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $config['formats'])) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
                <span id="mold-source" class="mt-2 hidden text-sm font-semibold text-action-dark"></span>
            </label>
            {{-- the uploaded model, so it is clear what the mold is made for --}}
            <div id="mold-model" class="card hidden overflow-hidden">
                <canvas id="mold-model-viewer" class="block h-[36vh] w-full touch-none" role="img" aria-label="{{ __('mold.page.model') }}"></canvas>
                {{-- what a printed mold would hold on to: painted red on the model, said in words here --}}
                <div id="mold-analysis" class="hidden border-t border-line px-4 py-3 text-sm text-slate-700" aria-live="polite">
                    <p id="mold-analysis-text"></p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                        <span><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#d72828"></span>{{ __('mold.page.legend.hidden') }}</span>
                        <span><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#6f93c4"></span><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#c9a662"></span>{{ __('mold.page.legend.parts') }}</span>
                    </p>
                    <p id="mold-options" class="mt-1 text-xs text-muted"></p>
                </div>
            </div>
        </div>
        <div class="card self-start p-4">
            <label class="block text-xs font-semibold text-slate-600">{{ __('mold.type') }}
                <select id="mold-type" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                    @foreach($types as $t)<option value="{{ $t }}">{{ __('mold.type.'.$t) }}</option>@endforeach
                </select>
            </label>
            <label class="mt-3 block text-xs font-semibold text-slate-600">{{ __('mold.wall') }}
                <select id="mold-wall" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                    @foreach($walls as $w)<option value="{{ $w }}" @selected($w === 8)>{{ $w }} mm</option>@endforeach
                </select>
            </label>
            <label data-mold-rigid class="mt-3 block text-xs font-semibold text-slate-600">{{ __('mold.parts') }}
                <select id="mold-parts" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                    @foreach($parts as $n)<option value="{{ $n }}">{{ __('mold.parts.'.$n) }}</option>@endforeach
                </select>
            </label>
            <label data-mold-two class="mt-3 block text-xs font-semibold text-slate-600">{{ __('mold.axis') }}
                <select id="mold-axis" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                    @foreach($axes as $a)<option value="{{ $a }}">{{ __('mold.axis.'.$a) }}</option>@endforeach
                </select>
            </label>
            <label data-mold-two class="mt-3 block text-xs font-semibold text-slate-600">{{ __('mold.split') }}
                <select id="mold-split" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                    <option value="">{{ __('mold.split.auto') }}</option>
                    @foreach($splits as $s)<option value="{{ $s }}">{{ __('mold.split.at', ['n' => $s]) }}</option>@endforeach
                </select>
            </label>
            <label data-mold-rigid class="mt-3 flex items-start gap-2 text-xs text-slate-700">
                <input id="mold-fill" type="checkbox" class="mt-0.5 h-4 w-4 accent-action">
                <span><span class="font-semibold">{{ __('mold.fill') }}</span><br><span class="text-muted">{{ __('mold.fill.hint') }}</span></span>
            </label>
            <button id="mold-go" class="btn-primary mt-4 w-full" disabled>{{ __('mold.apply') }}</button>
            <p class="mt-2 text-xs text-muted">{{ __('mold.hint') }}</p>
        </div>
    </form>
    <p id="mold-status" class="note-warn mt-3 hidden" role="status" aria-live="polite"></p>

    {{-- undercuts were filled: the shape that will really be cast, added material in orange --}}
    <div id="mold-cast" class="card mt-4 hidden overflow-hidden">
        <div class="px-4 pt-3 text-sm font-semibold text-ink">{{ __('mold.page.cast.title') }}</div>
        <canvas id="mold-cast-viewer" class="block h-[44vh] w-full touch-none" role="img" aria-label="{{ __('mold.page.cast.title') }}"></canvas>
        <p class="border-t border-line px-4 py-3 text-sm text-slate-700"><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#e68c28"></span><span id="mold-cast-text"></span></p>
    </div>

    <div id="mold-result" class="mt-4 hidden grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden"><canvas id="mold-viewer" class="block h-[40vh] w-full touch-none" role="img" aria-label="{{ __('param.viewer') }}"></canvas></div>
        <div>
            <div id="mold-report" class="card p-5 text-sm text-slate-700"></div>
            <button id="mold-to-silicone" type="button" class="btn-secondary mt-3 hidden w-full">{{ __('mold.page.to_silicone') }}</button>
            <a id="mold-open" href="{{ route('home') }}" class="btn-primary mt-3 w-full">{{ \App\Support\NextStep::text('mold.page.go') }}</a>
        </div>
    </div>
    @endunless
</div>
@endsection
