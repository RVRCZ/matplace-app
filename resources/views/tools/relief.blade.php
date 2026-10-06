@extends('tools.page', ['tool' => 'relief', 'module' => 'relief', 'lead' => __('relief.lead'), 'available' => $available, 'goLabel' => \App\Support\NextStep::text('toolpage.go'),
    'sections' => ['photo' => __('toolpage.section.photo'), 'settings' => __('toolpage.section.settings'), 'print' => \App\Support\NextStep::text('param.step.inquiry')]])

@push('head')
<script>
    window.MP_RELIEF = { url: @json(route('api.tools.relief')), home: @json(route('home')), files: @json(url('/api/files')), i18n: { working: @json(__('relief.working')), failed: @json(__('relief.failed')), not_image: @json(__('search.not_image')), photo_again: @json(__('relief.photo_again')) } };
</script>
@endpush

@section('viewer-empty'){{ __('relief.step.model') }}@endsection

@section('panel')
<form id="relief-form">
    <x-tool-section id="photo" :title="__('toolpage.section.photo')">
        <fieldset>
            <legend class="sr-only">{{ __('relief.mode.lithophane') }} / {{ __('relief.mode.relief') }}</legend>
            <div class="grid grid-cols-2 gap-2 text-sm" role="radiogroup">
                @foreach (['lithophane' => 'lightbulb', 'relief' => 'frame'] as $k => $ico)
                    <label class="cursor-pointer rounded-lg border border-slate-300 bg-white p-3 text-center has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action">
                        <input type="radio" name="mode" value="{{ $k }}" @checked($k === 'lithophane') class="sr-only">
                        <x-icon :name="$ico" class="mx-auto h-6 w-6 text-ink" />
                        <div class="mt-1 font-medium text-ink">{{ __('relief.mode.'.$k) }}</div>
                        <div class="text-xs text-muted">{{ __('relief.mode.'.$k.'.hint') }}</div>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label id="relief-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-5 text-center hover:border-ink">
            <input id="relief-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
            <img id="relief-preview" alt="" class="mx-auto hidden max-h-48 rounded-lg">
            <div id="relief-pick" class="text-ink"><x-icon name="camera" class="mx-auto h-7 w-7 text-muted" /><span class="mt-1 block font-medium">{{ __('relief.pick') }}</span></div>
        </label>
        <p class="text-xs text-muted">{{ __('relief.privacy') }}</p>
    </x-tool-section>

    <x-tool-section id="settings" :title="__('toolpage.section.settings')">
        <label class="lbl">{{ __('relief.width') }} <span id="relief-w-val" class="num font-normal text-muted">100 mm</span>
            <input id="relief-w" name="width" type="range" min="40" max="200" step="5" value="100" class="mt-1 w-full accent-ink">
        </label>
        <div class="grid gap-2 text-sm text-ink">
            <label class="flex items-center gap-2"><input type="checkbox" name="frame" value="1" checked class="h-5 w-5 accent-ink"> {{ __('relief.frame') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="invert" value="1" class="h-5 w-5 accent-ink"> {{ __('relief.invert') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="stand" value="1" class="h-5 w-5 accent-ink"> {{ __('relief.stand') }}</label>
        </div>
    </x-tool-section>

    <x-tool-section id="print" :title="\App\Support\NextStep::text('param.step.inquiry')">
        <button class="btn-ink w-full gap-1.5"><x-icon name="sparkles" class="h-4 w-4" />{{ __('relief.submit') }}</button>
        <p id="relief-msg" class="hint" aria-live="polite"></p>
    </x-tool-section>
</form>
@endsection
