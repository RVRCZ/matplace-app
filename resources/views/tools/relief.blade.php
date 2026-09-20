@extends('layouts.app', ['title' => __('tools.relief.title').' · matplace'])

@push('head')
<script>
    window.MP_RELIEF = { url: @json(route('api.tools.relief')), home: @json(route('home')), i18n: { working: @json(__('relief.working')), failed: @json(__('relief.failed')), not_image: @json(__('search.not_image')) } };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-2xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold">{{ __('tools.relief.title') }}</h1>
    <p class="text-slate-600">{{ __('relief.lead') }}</p>

    @unless($available)
        <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ __('sign.unavailable') }}</div>
    @else
    <form id="relief-form" class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="grid grid-cols-2 gap-2 text-sm">
            @foreach (['lithophane' => '💡', 'relief' => '🖼️'] as $k => $ico)
                <label class="cursor-pointer rounded-xl border border-slate-300 p-3 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft">
                    <input type="radio" name="mode" value="{{ $k }}" @checked($k === 'lithophane') class="sr-only">
                    <div class="text-2xl">{{ $ico }}</div>
                    <div class="font-semibold">{{ __('relief.mode.'.$k) }}</div>
                    <div class="text-xs text-slate-500">{{ __('relief.mode.'.$k.'.hint') }}</div>
                </label>
            @endforeach
        </div>

        <label class="block cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 p-5 text-center hover:border-action">
            <input id="relief-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
            <img id="relief-preview" alt="" class="mx-auto hidden max-h-56 rounded-lg">
            <div id="relief-pick" class="text-slate-600"><div class="text-3xl">📷</div>{{ __('relief.pick') }}</div>
        </label>

        <label class="block text-sm font-semibold">{{ __('relief.width') }} <span id="relief-w-val" class="font-normal text-action-dark">100 mm</span>
            <input id="relief-w" name="width" type="range" min="40" max="200" step="5" value="100" class="mt-1 w-full accent-action">
        </label>

        <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="frame" value="1" checked> {{ __('relief.frame') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="invert" value="1"> {{ __('relief.invert') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="stand" value="1"> {{ __('relief.stand') }}</label>
        </div>

        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-60">{{ __('relief.submit') }}</button>
        <p id="relief-msg" class="text-sm text-slate-600"></p>
        <p class="text-xs text-slate-400">{{ __('relief.privacy') }}</p>
    </form>
    @endunless
</div>
@endsection
