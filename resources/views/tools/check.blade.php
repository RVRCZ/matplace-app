@extends('layouts.app', ['title' => __('tools.check.title').' · matplace'])

@php
    $keys = ['check.head.error', 'check.head.advice', 'check.head.ok', 'check.group.error', 'check.group.advice', 'check.group.ok', 'check.disclaimer',
        'check.page.bad_format', 'check.page.too_big', 'check.page.uploading', 'check.page.checking', 'check.page.failed'];
    foreach (['units_tiny', 'units_huge', 'very_small', 'exceeds_bed', 'size_ok', 'parts_fit', 'part_exceeds_bed', 'too_thin', 'watertight_ok', 'not_watertight', 'flipped_normals', 'multiple_shells', 'heavy_mesh', 'very_coarse'] as $c) {
        $keys[] = 'check.'.$c; $keys[] = 'check.'.$c.'.impact';
    }
    $i18n = collect($keys)->mapWithKeys(fn ($k) => [$k => __($k)])->all();
@endphp

@push('head')
<script>
    window.MP_I18N = {{ \Illuminate\Support\Js::from($i18n) }};
    window.MP_CHECK = { upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), home: @json(route('home')), formats: {{ \Illuminate\Support\Js::from($config['formats']) }}, maxMb: {{ (int) $config['max_upload_mb'] }} };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ __('tools.check.title') }}</h1>
    <p class="hint">{{ __('check.page.lead') }}</p>

    <label id="check-drop" class="card mt-4 block cursor-pointer border-2 border-dashed p-8 text-center hover:border-action">
        <input id="check-file" type="file" class="sr-only" accept="{{ collect($config['formats'])->map(fn ($f) => '.'.$f)->join(',') }}">
        <span class="block text-lg font-bold text-ink">{{ __('check.page.pick') }}</span>
        <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $config['formats'])) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
    </label>
    <p id="check-status" class="note-warn mt-3 hidden" role="status" aria-live="polite"></p>

    <div id="check-result" class="mt-4 hidden grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden"><canvas id="check-viewer" class="block h-[40vh] w-full touch-none" role="img" aria-label="{{ __('param.viewer') }}"></canvas></div>
        <div>
            <div id="check-report" class="card p-5"></div>
            <a id="check-go" href="{{ route('home') }}" class="btn-primary mt-3 w-full">{{ __('check.page.go') }}</a>
        </div>
    </div>
</div>
@endsection
