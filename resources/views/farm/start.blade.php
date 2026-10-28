@extends('layouts.app', ['title' => __('farm.title').' · matplace', 'noindex' => true])

@php
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
            <a href="{{ route('account.orders') }}" class="text-action-dark underline">{{ __('farm.my_orders') }}</a>
            <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('farm.credit_balance') }}: @money($balance)</a>
        </div>
    </div>
    <p class="mt-2 text-slate-600">{{ __('farm.lead') }}</p>
    @include('partials.flash')
    @include('partials.verify_banner')

    <ol class="mt-4 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
        @foreach(__('farm.start.steps') as $i => $step)
            <li class="flex gap-2 rounded-xl border border-slate-200 bg-white p-3"><span class="font-bold text-action-dark">{{ $i + 1 }}</span><span>{{ $step }}</span></li>
        @endforeach
    </ol>

    {{-- the way in: the model (handed over, or uploaded here) and one button. Material, settings and colours are chosen
         once, on the order page, next to the price; what the address handed over (a calculation, the settings and
         colours of a print made before) rides along in hidden fields --}}
    <form id="farm-start" method="post" action="{{ route('farm.orders.store') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        @csrf
        <input type="hidden" name="file" id="farm-file" value="{{ $file?->uuid }}">
        @if($calc)<input type="hidden" name="calc" value="{{ $calc->token }}">@endif
        @if($card)<input type="hidden" name="designer_model" value="{{ $card->id }}">@endif
        @if($inspiration)<input type="hidden" name="catalog_model" value="{{ $inspiration->id }}">@endif
        @foreach($carry as $name => $value)
            @if(is_array($value))
                @foreach($value as $k => $v)<input type="hidden" name="{{ $name }}[{{ $k }}]" value="{{ $v }}">@endforeach
            @else
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        <div class="text-sm font-semibold text-slate-700">{{ __('farm.start.model') }}</div>
        @if($card)
            {{-- a designer's model: its name and author instead of a file name; the reward is part of the price --}}
            <p class="mt-1 text-sm font-semibold">{{ __('models.farm.card', ['title' => $card->title, 'name' => $card->profile->display_name]) }}</p>
            @if($card->royalty_czk > 0 && $card->profile->user_id !== auth()->id())<p class="text-xs text-slate-500">{{ __('models.farm.reward', ['amount' => \App\Support\Money::price((float) $card->royalty_czk)->format()]) }}</p>@endif
            @unless($previewUrl)
                @if($card->coverUrl(false))<img src="{{ $card->coverUrl(false) }}" alt="" class="mt-2 max-h-64 w-full rounded-xl border border-slate-200 bg-slate-50 object-contain">@endif
                <p class="mt-1 text-xs text-slate-500">{{ __('models.farm.no_preview') }}</p>
            @endunless
        @elseif($file)
            <p class="mt-1 text-sm">{{ $file->original_name }}</p>
        @else
            {{-- a real drop zone: the bare file input looks like a line of text --}}
            <label id="farm-dropzone" for="farm-upload" class="mt-2 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition hover:border-action hover:bg-action-soft">
                <x-icon name="folder-open" class="h-8 w-8 text-muted" />
                <span class="btn-primary pointer-events-none text-sm">{{ __('farm.start.upload') }}</span>
                <span class="text-xs text-slate-500">{{ __('farm.start.drop_hint') }}</span>
            </label>
            <input id="farm-upload" type="file" accept=".stl" class="sr-only" aria-label="{{ __('farm.start.upload') }}">
            <p class="mt-1 text-xs text-slate-500">{{ __('farm.start.upload_hint', ['max' => $settings['max_upload_mb'], 'x' => (int) ($bed?->x ?? 250), 'y' => (int) ($bed?->y ?? 250), 'z' => (int) ($bed?->z ?? 250)]) }}</p>
            <p id="farm-upload-status" class="mt-1 hidden text-sm text-slate-600" role="status"></p>
        @endif
        <div id="farm-preview-box" class="mt-3 {{ $file && ($previewUrl || ! $card) ? '' : 'hidden' }} overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
            <canvas id="farm-preview" class="block h-64 w-full touch-none" data-model="{{ $previewUrl ?? '' }}" data-scale="{{ (float) ($carry['scale'] ?? 1) }}"></canvas>
        </div>
        @if($inspiration)
            <div class="mt-3 rounded-xl bg-action-soft p-3 text-sm">
                <p class="font-semibold">{{ __('models.farm.source_title') }}</p>
                <p class="text-slate-700">{{ __('models.farm.source_text', ['line' => $inspiration->attribution()]) }}</p>
            </div>
        @endif
        <p class="mt-3 text-xs text-slate-500">{{ __('farm.start.next_hint') }}</p>

        <button id="farm-continue" type="submit" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-50" @disabled(! $file)>{{ __('farm.start.continue') }}<x-icon name="arrow-right" class="h-4 w-4" /></button>
        @if($slicesLeft !== null)<p class="mt-2 text-xs text-slate-500">{{ trans_choice('farm.slices_left', $slicesLeft, ['n' => $slicesLeft]) }}</p>@endif
    </form>
</div>
@endsection
