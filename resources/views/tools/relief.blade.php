@extends('layouts.app', ['title' => __('tools.relief.title').' · matplace'])

@push('head')
<script>
    window.MP_RELIEF = { url: @json(route('api.tools.relief')), home: @json(route('home')), files: @json(url('/api/files')), i18n: { working: @json(__('relief.working')), failed: @json(__('relief.failed')), not_image: @json(__('search.not_image')), photo_again: @json(__('relief.photo_again')) } };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-2xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ __('tools.relief.title') }}</h1>
    <p class="hint">{{ __('relief.lead') }}</p>

    <ol class="steps mt-3" aria-label="{{ __('param.steps') }}">
        <li aria-current="step"><span class="step-no">1</span>{{ __('relief.step.photo') }}</li>
        <li><span class="step-no">2</span>{{ __('relief.step.model') }}</li>
        <li><span class="step-no">3</span>{{ \App\Support\NextStep::text('param.step.inquiry') }}</li>
    </ol>

    @unless($available)
        <div class="note-warn mt-4 text-sm">{{ __('sign.unavailable') }}</div>
    @else
    <form id="relief-form" class="card mt-4 space-y-5 p-5">
        <fieldset>
            <legend class="lbl">{{ __('relief.mode.lithophane') }} / {{ __('relief.mode.relief') }}</legend>
            <div class="mt-2 grid grid-cols-2 gap-2 text-sm" role="radiogroup">
                @foreach (['lithophane' => '💡', 'relief' => '🖼️'] as $k => $ico)
                    <label class="cursor-pointer rounded-xl border border-slate-300 bg-white p-3 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action">
                        <input type="radio" name="mode" value="{{ $k }}" @checked($k === 'lithophane') class="sr-only">
                        <div class="text-2xl" aria-hidden="true">{{ $ico }}</div>
                        <div class="font-semibold text-ink">{{ __('relief.mode.'.$k) }}</div>
                        <div class="text-xs text-muted">{{ __('relief.mode.'.$k.'.hint') }}</div>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label class="block cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 p-5 text-center hover:border-action hover:bg-action-soft">
            <input id="relief-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
            <img id="relief-preview" alt="" class="mx-auto hidden max-h-56 rounded-lg">
            <div id="relief-pick" class="text-ink"><div class="text-3xl" aria-hidden="true">📷</div><span class="font-semibold">{{ __('relief.pick') }}</span></div>
        </label>

        <label class="lbl">{{ __('relief.width') }} <span id="relief-w-val" class="font-normal text-action-dark">100 mm</span>
            <input id="relief-w" name="width" type="range" min="40" max="200" step="5" value="100" class="mt-1 w-full accent-action">
        </label>

        <div class="flex flex-wrap gap-4 text-sm text-ink">
            <label class="flex items-center gap-2"><input type="checkbox" name="frame" value="1" checked class="h-5 w-5 accent-action"> {{ __('relief.frame') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="invert" value="1" class="h-5 w-5 accent-action"> {{ __('relief.invert') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="stand" value="1" class="h-5 w-5 accent-action"> {{ __('relief.stand') }}</label>
        </div>

        <button class="btn-primary w-full">{{ __('relief.submit') }}</button>
        <p id="relief-msg" class="hint" aria-live="polite"></p>
        <p class="text-xs text-muted">{{ __('relief.privacy') }}</p>
    </form>
    @endunless
</div>
@endsection
