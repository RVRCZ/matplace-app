@extends('layouts.app', ['title' => __('tools.repair.title').' · matplace', 'description' => __('repair.lead')])

@php
    $keys = ['repair.uploading', 'repair.checking', 'repair.repairing', 'repair.failed', 'repair.bad_format', 'repair.too_big', 'repair.unavailable',
        'repair.verdict.clean', 'repair.verdict.repaired', 'repair.verdict.improved', 'repair.verdict.unchanged',
        'repair.before', 'repair.after', 'repair.row.open_edges', 'repair.row.non_manifold_edges', 'repair.row.shells', 'repair.row.flipped_normals', 'repair.row.triangles', 'repair.row.watertight',
        'repair.yes', 'repair.no', 'repair.done', 'repair.act.removed_degenerate', 'repair.act.removed_duplicate', 'repair.act.removed_dust', 'repair.act.rebuilt_bodies',
        'repair.act.closed_edges', 'repair.act.fixed_normals', 'repair.act.fixed_non_manifold', 'repair.act.none', 'repair.left'];
    $i18n = collect($keys)->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $config['max_upload_mb']])])->all();
@endphp

@push('head')
<script>
    window.MP_I18N = {{ \Illuminate\Support\Js::from($i18n) }};
    window.MP_REPAIR = { upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), home: @json(route('home')), formats: {{ \Illuminate\Support\Js::from($config['formats']) }}, maxMb: {{ (int) $config['max_upload_mb'] }} };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ __('tools.repair.title') }}</h1>
    <p class="hint">{{ __('repair.lead') }}</p>

    <ol class="steps mt-3" aria-label="{{ __('param.steps') }}">
        <li aria-current="step"><span class="step-no">1</span>{{ __('repair.step.upload') }}</li>
        <li><span class="step-no">2</span>{{ __('repair.step.report') }}</li>
        <li><span class="step-no">3</span>{{ \App\Support\NextStep::text('param.step.inquiry') }}</li>
    </ol>

    @unless($available)
        <div class="note-warn mt-4 text-sm">{{ __('repair.unavailable') }}</div>
    @else
    <label id="repair-drop" class="card mt-4 block cursor-pointer border-2 border-dashed p-8 text-center hover:border-action">
        <input id="repair-file" type="file" class="sr-only" accept="{{ collect($config['formats'])->map(fn ($f) => '.'.$f)->join(',') }}">
        <span class="block text-lg font-bold text-ink">{{ __('repair.pick') }}</span>
        <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $config['formats'])) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
    </label>
    <p id="repair-status" class="note-warn mt-3 hidden" role="status" aria-live="polite"></p>

    <div id="repair-result" class="mt-4 hidden grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden"><canvas id="repair-viewer" class="block h-[40vh] w-full touch-none" role="img" aria-label="{{ __('param.viewer') }}"></canvas></div>
        <div>
            <div class="card p-5">
                <h2 id="repair-verdict" class="font-bold text-ink"></h2>
                <ul id="repair-actions" class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink"></ul>
                <table class="mt-3 w-full text-sm">
                    <thead><tr class="text-left text-muted"><th class="py-1 font-normal"></th><th class="py-1 font-semibold">{{ __('repair.before') }}</th><th class="py-1 font-semibold">{{ __('repair.after') }}</th></tr></thead>
                    <tbody id="repair-table"></tbody>
                </table>
                <p id="repair-left" class="mt-3 hidden text-sm text-amber-800"></p>
                <p class="mt-3 text-xs text-muted">{{ __('repair.note') }}</p>
            </div>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <a id="repair-download" href="#" class="btn-secondary w-full">{{ __('repair.download') }}</a>
                <a id="repair-go" href="{{ route('home') }}" class="btn-primary w-full">{{ \App\Support\NextStep::text('check.page.go') }}</a>
            </div>
        </div>
    </div>
    @endunless

    <section class="mt-8 grid gap-3 text-sm text-ink sm:grid-cols-3">
        @foreach(['holes', 'normals', 'doubles'] as $k)
            <div class="card p-4"><h2 class="font-bold">{{ __('repair.why.'.$k) }}</h2><p class="mt-1 text-muted">{{ __('repair.why.'.$k.'.text') }}</p></div>
        @endforeach
    </section>
</div>
@endsection
