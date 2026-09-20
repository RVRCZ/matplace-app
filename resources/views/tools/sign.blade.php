@extends('layouts.app', ['title' => __('tools.sign.title').' · matplace'])

@push('head')
<script>
    window.MP_SIGN = { url: @json(route('api.tools.sign')), home: @json(route('home')), files: @json(url('/api/files')), i18n: { working: @json(__('sign.working')), failed: @json(__('sign.failed')) } };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-2xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold">{{ __('tools.sign.title') }}</h1>
    <p class="text-slate-600">{{ __('sign.lead') }}</p>

    @unless($available)
        <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ __('sign.unavailable') }}</div>
    @else
    <form id="sign-form" class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
        <label class="block text-sm font-semibold">{{ __('sign.line1') }}<input name="line1" required maxlength="40" placeholder="{{ __('sign.line1_ph') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-lg font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('sign.line2') }}<input name="line2" maxlength="40" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>

        <div class="grid grid-cols-3 gap-2 text-sm">
            @foreach (['rounded' => '▢', 'rect' => '▭', 'oval' => '⬭'] as $k => $ico)
                <label class="cursor-pointer rounded-xl border border-slate-300 p-2 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft"><input type="radio" name="shape" value="{{ $k }}" @checked($k === 'rounded') class="sr-only"><div class="text-xl">{{ $ico }}</div>{{ __('sign.shape.'.$k) }}</label>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-2 text-sm">
            <label class="cursor-pointer rounded-xl border border-slate-300 p-2 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft"><input type="radio" name="style" value="emboss" checked class="sr-only">{{ __('sign.style.emboss') }}</label>
            <label class="cursor-pointer rounded-xl border border-slate-300 p-2 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft"><input type="radio" name="style" value="engrave" class="sr-only">{{ __('sign.style.engrave') }}</label>
        </div>

        <label class="block text-sm font-semibold">{{ __('sign.text_height') }} <span id="sign-th-val" class="font-normal text-action-dark">12 mm</span>
            <input id="sign-th" name="text_height" type="range" min="5" max="60" step="1" value="12" class="mt-1 w-full accent-action">
        </label>

        <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="hole" value="1"> {{ __('sign.hole') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="border" value="1" checked> {{ __('sign.border') }}</label>
        </div>

        <details class="text-sm"><summary class="cursor-pointer text-action-dark">{{ __('calc.more') }}</summary>
            <div class="mt-2 grid grid-cols-3 gap-3">
                <label class="font-semibold">{{ __('sign.font') }}<select name="font" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 font-normal">@foreach ($fonts as $f)<option value="{{ $f }}">{{ __('sign.font.'.$f) }}</option>@endforeach</select></label>
                <label class="font-semibold">{{ __('sign.thickness') }}<input name="thickness" type="number" min="1.2" max="10" step="0.2" value="3" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 font-normal"></label>
                <label class="font-semibold">{{ __('sign.relief') }}<input name="relief" type="number" min="0.4" max="5" step="0.2" value="1.2" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 font-normal"></label>
            </div>
        </details>

        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-60">{{ __('sign.submit') }}</button>
        <p id="sign-msg" class="text-sm text-slate-600"></p>
        <p class="text-xs text-slate-400">{{ __('sign.tip') }}</p>
    </form>
    @endunless
</div>
@endsection
