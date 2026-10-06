@php
    $keys = ['check.head.error', 'check.head.advice', 'check.head.ok', 'check.group.error', 'check.group.advice', 'check.group.ok', 'check.disclaimer',
        'check.page.bad_format', 'check.page.too_big', 'check.page.uploading', 'check.page.checking', 'check.page.failed'];
    foreach (['units_tiny', 'units_huge', 'very_small', 'exceeds_bed', 'size_ok', 'parts_fit', 'part_exceeds_bed', 'too_thin', 'watertight_ok', 'not_watertight', 'flipped_normals', 'multiple_shells', 'heavy_mesh', 'very_coarse'] as $c) {
        $keys[] = 'check.'.$c; $keys[] = 'check.'.$c.'.impact';
    }
    $i18n = collect($keys)->mapWithKeys(fn ($k) => [$k => \App\Support\NextStep::text($k)])->all();
@endphp

@extends('tools.page', ['tool' => 'check', 'module' => 'check', 'lead' => __('check.page.lead'), 'goLabel' => \App\Support\NextStep::text('check.page.go'),
    'sections' => ['file' => __('toolpage.section.file'), 'result' => __('toolpage.section.result')]])

@push('head')
<script>
    window.MP_I18N = {{ \Illuminate\Support\Js::from($i18n) }};
    window.MP_CHECK = { upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), home: @json(route('home')), formats: {{ \Illuminate\Support\Js::from($config['formats']) }}, maxMb: {{ (int) $config['max_upload_mb'] }} };
</script>
@endpush

@section('viewer-empty'){{ __('check.page.pick') }}@endsection

@section('panel')
<form id="check-form" onsubmit="return false">
    <x-tool-section id="file" :title="__('toolpage.section.file')">
        <label id="check-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-ink">
            <input id="check-file" type="file" class="sr-only" accept="{{ collect($config['formats'])->map(fn ($f) => '.'.$f)->join(',') }}">
            <x-icon name="upload" class="mx-auto h-7 w-7 text-muted" />
            <span class="mt-1 block font-medium text-ink">{{ __('check.page.pick') }}</span>
            <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $config['formats'])) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
        </label>
        <p id="check-status" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
    </x-tool-section>

    <x-tool-section id="result" :title="__('toolpage.section.result')">
        <div id="check-result" class="hidden"><div id="check-report"></div></div>
        <p id="check-wait" class="text-sm text-muted">{{ __('toolpage.status.empty') }}</p>
    </x-tool-section>
</form>
@endsection
