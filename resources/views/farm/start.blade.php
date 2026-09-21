@extends('layouts.app', ['title' => __('farm.title').' · matplace', 'noindex' => true])

@php
    $printer = \App\Models\FarmPrinter::where('enabled', true)->orderBy('id')->first();
    $startCfg = [
        'upload' => route('api.uploads.store'), 'files' => url('/api/files'), 'maxMb' => (int) $settings['max_upload_mb'],
        'text' => [
            'uploading' => __('farm.start.uploading'), 'processing' => __('farm.start.processing'), 'failed' => __('farm.start.upload_failed'),
            'badFormat' => __('farm.start.bad_format'), 'tooBig' => __('farm.start.too_big', ['max' => $settings['max_upload_mb']]),
        ],
    ];
@endphp

@push('head')
<script>window.MP_FARM_START = {{ \Illuminate\Support\Js::from($startCfg) }};</script>
@endpush

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold">{{ __('farm.title') }}</h1>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('farm.orders') }}" class="text-action-dark underline">{{ __('farm.my_orders') }}</a>
            <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('farm.credit_balance') }}: {{ number_format($balance, 0, ',', ' ') }} Kč</a>
        </div>
    </div>
    <p class="mt-2 text-slate-600">{{ __('farm.lead') }}</p>
    @include('partials.flash')

    <ol class="mt-4 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
        @foreach(__('farm.start.steps') as $i => $step)
            <li class="flex gap-2 rounded-xl border border-slate-200 bg-white p-3"><span class="font-bold text-action-dark">{{ $i + 1 }}</span><span>{{ $step }}</span></li>
        @endforeach
    </ol>

    <form id="farm-start" method="post" action="{{ route('farm.orders.store') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        @csrf
        <input type="hidden" name="file" id="farm-file" value="{{ $file?->uuid }}">

        <div class="text-sm font-semibold text-slate-700">{{ __('farm.start.model') }}</div>
        @if($file)
            <p class="mt-1 text-sm">{{ $file->original_name }}</p>
        @else
            <input id="farm-upload" type="file" accept=".stl" class="mt-1 block w-full text-sm" aria-label="{{ __('farm.start.upload') }}">
            <p class="mt-1 text-xs text-slate-500">{{ __('farm.start.upload_hint', ['max' => $settings['max_upload_mb'], 'x' => (int) ($printer?->bed_x ?? 250), 'y' => (int) ($printer?->bed_y ?? 250), 'z' => (int) ($printer?->bed_z ?? 250)]) }}</p>
            <p id="farm-upload-status" class="mt-1 hidden text-sm text-slate-600" role="status"></p>
        @endif

        <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.quality.label') }}</div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            @foreach($settings['qualities'] as $key => $q)
                <label class="seg block cursor-pointer text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:checked]:text-action-dark has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action"><input type="radio" name="quality" value="{{ $key }}" class="sr-only" @checked($key === $quality || ($loop->first && ! array_key_exists($quality, $settings['qualities'])))>{{ __('farm.quality.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ $q['layer_mm'] }} mm</span></label>
            @endforeach
        </div>

        <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.strength.label') }}</div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            @foreach($settings['strengths'] as $key => $s)
                <label class="seg block cursor-pointer text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:checked]:text-action-dark has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action"><input type="radio" name="strength" value="{{ $key }}" class="sr-only" @checked($key === 'standard')>{{ __('farm.strength.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ __('farm.strength.infill', ['n' => $s['infill']]) }}</span></label>
            @endforeach
        </div>

        <button id="farm-continue" type="submit" class="mt-4 w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-50" @disabled(! $file)>{{ __('farm.start.continue') }}</button>
        <p class="mt-2 text-xs text-slate-500">{{ __('farm.slices_left', ['n' => $slicesLeft]) }}</p>
    </form>
</div>
@endsection
