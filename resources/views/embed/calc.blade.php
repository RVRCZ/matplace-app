@extends('layouts.embed', ['title' => __('embed.frame_title')])

@php
    $i18n = collect(['embed.pick', 'embed.formats', 'embed.reading', 'embed.measuring', 'embed.counting', 'embed.estimate', 'embed.exact', 'embed.time', 'embed.open', 'embed.failed', 'embed.too_big', 'embed.not_model', 'embed.big_model', 'embed.price_note', 'embed.again'])
        ->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $maxMb, 'formats' => strtoupper(implode(', ', $formats))])])->all();
@endphp

@push('head')
<script>
    window.MP_ROUTES = { uploads: @json(route('api.uploads.store')), calculations: @json(route('api.calculations.store')), calcShow: @json(url('/api/calculations')), files: @json(url('/api/files')) };
    window.MP_EMBED = { i18n: @json($i18n), formats: @json($formats), maxMb: {{ (int) $maxMb }}, host: @json($host), utm: @json(['utm_source' => 'embed', 'utm_medium' => 'iframe', 'utm_campaign' => $host !== '' ? $host : 'unknown']), material: @json(config('materials.default') ?? 'PLA'), logo: @json(url('/img/logo-header.webp')) };
</script>
@endpush

@section('content')
<div id="embed" class="mx-auto flex min-h-full max-w-md flex-col gap-3 p-4" data-state="idle">
    <div class="flex items-center justify-between gap-2">
        <a href="{{ url('/') }}?utm_source=embed&utm_medium=iframe&utm_campaign={{ urlencode($host !== '' ? $host : 'unknown') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm font-semibold text-ink">
            <img src="/img/logo-header.webp" alt="matplace" width="299" height="180" class="h-7 w-auto"><span>{{ __('embed.heading') }}</span>
        </a>
    </div>
    <label id="embed-drop" class="flex min-h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-300 bg-white p-4 text-center text-sm text-slate-600 hover:border-action" for="embed-file">
        <span class="font-semibold text-ink">{{ __('embed.pick') }}</span>
        <span class="text-xs text-slate-500">{{ __('embed.formats', ['formats' => strtoupper(implode(', ', $formats)), 'max' => $maxMb]) }}</span>
        <input id="embed-file" type="file" accept="{{ implode(',', array_map(fn ($f) => '.'.$f, $formats)) }}" class="sr-only">
    </label>
    <div id="embed-status" class="hidden items-center gap-2 text-sm text-slate-600" role="status">
        <span id="embed-spinner" class="h-4 w-4 animate-spin rounded-full border-2 border-action border-t-transparent"></span>
        <span id="embed-status-text"></span>
    </div>
    <div id="embed-result" class="hidden rounded-2xl border border-slate-200 bg-white p-4">
        <div id="embed-file-name" class="truncate text-xs text-slate-500"></div>
        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500" id="embed-kind"></div>
        <div class="mt-0.5 text-3xl font-extrabold tracking-tight text-ink" id="embed-price">—</div>
        <div class="mt-1 text-sm text-slate-600" id="embed-time"></div>
        <p class="mt-1 text-xs text-slate-500">{{ __('embed.price_note') }}</p>
        <a id="embed-open" target="_blank" rel="noopener" class="mt-3 block rounded-xl bg-action px-4 py-3 text-center font-semibold text-white">{{ __('embed.open') }}</a>
        <button type="button" id="embed-again" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">{{ __('embed.again') }}</button>
    </div>
    <p id="embed-error" class="hidden rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert"></p>
    <p class="mt-auto text-center text-xs text-slate-500">{{ __('embed.footer') }} <a href="{{ url('/') }}?utm_source=embed&utm_medium=iframe&utm_campaign={{ urlencode($host !== '' ? $host : 'unknown') }}" target="_blank" rel="noopener" class="underline">matplace.com</a></p>
</div>
@endsection
