@php
    $keys = ['repair.uploading', 'repair.checking', 'repair.repairing', 'repair.failed', 'repair.bad_format', 'repair.too_big', 'repair.unavailable',
        'repair.verdict.clean', 'repair.verdict.repaired', 'repair.verdict.improved', 'repair.verdict.unchanged',
        'repair.before', 'repair.after', 'repair.row.open_edges', 'repair.row.non_manifold_edges', 'repair.row.shells', 'repair.row.flipped_normals', 'repair.row.triangles', 'repair.row.watertight',
        'repair.yes', 'repair.no', 'repair.done', 'repair.act.removed_degenerate', 'repair.act.removed_duplicate', 'repair.act.removed_dust', 'repair.act.rebuilt_bodies',
        'repair.act.closed_edges', 'repair.act.fixed_normals', 'repair.act.fixed_non_manifold', 'repair.act.none', 'repair.left', 'repair.download'];
    $i18n = collect($keys)->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $config['max_upload_mb']])])->all();
@endphp

@extends('tools.page', ['tool' => 'repair', 'module' => 'repair', 'lead' => __('repair.lead'), 'available' => $available, 'unavailable' => __('repair.unavailable'),
    'sections' => ['file' => __('toolpage.section.file'), 'result' => __('toolpage.section.result')]])

@push('head')
<script>
    window.MP_I18N = {{ \Illuminate\Support\Js::from($i18n) }};
    window.MP_REPAIR = { upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), home: @json(route('home')), formats: {{ \Illuminate\Support\Js::from($config['formats']) }}, maxMb: {{ (int) $config['max_upload_mb'] }} };
</script>
@endpush

@section('viewer-empty'){{ __('repair.pick') }}@endsection

@section('panel')
<form id="repair-form" onsubmit="return false">
    <x-tool-section id="file" :title="__('toolpage.section.file')">
        <label id="repair-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-ink">
            <input id="repair-file" type="file" class="sr-only" accept="{{ collect($config['formats'])->map(fn ($f) => '.'.$f)->join(',') }}">
            <x-icon name="upload" class="mx-auto h-7 w-7 text-muted" />
            <span class="mt-1 block font-medium text-ink">{{ __('repair.pick') }}</span>
            <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $config['formats'])) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
        </label>
        <p id="repair-status" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
    </x-tool-section>

    <x-tool-section id="result" :title="__('toolpage.section.result')">
        <p id="repair-wait" class="text-sm text-muted">{{ __('toolpage.status.empty') }}</p>
        <div id="repair-result" class="hidden">
            <h3 id="repair-verdict" class="font-semibold text-ink"></h3>
            <ul id="repair-actions" class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink"></ul>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-muted"><th class="py-1 font-normal"></th><th class="py-1 font-semibold">{{ __('repair.before') }}</th><th class="py-1 font-semibold">{{ __('repair.after') }}</th></tr></thead>
                <tbody id="repair-table"></tbody>
            </table>
            <p id="repair-left" class="mt-3 hidden text-sm text-warn"></p>
            <p class="mt-3 text-xs text-muted">{{ __('repair.note') }}</p>
        </div>
    </x-tool-section>
</form>

<section class="mt-3 grid gap-3 text-sm text-ink">
    @foreach(['holes', 'normals', 'doubles'] as $k)
        <div class="card p-4"><h2 class="font-semibold">{{ __('repair.why.'.$k) }}</h2><p class="mt-1 text-muted">{{ __('repair.why.'.$k.'.text') }}</p></div>
    @endforeach
</section>
@endsection
