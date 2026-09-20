@extends('layouts.app', ['title' => __('tools.spare.title').' · matplace'])

@push('head')
<script>
    window.MP_SPARE = { url: @json(route('api.spare')), i18n: { sending: @json(__('spare.sending')), failed: @json(__('spare.failed')), too_many: @json(__('spare.too_many')) } };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ __('tools.spare.title') }}</h1>
    <p class="hint">{{ __('spare.lead') }}</p>

    <div class="note-warn mt-3 text-sm">{{ __('spare.honest') }}</div>

    <form id="spare-form" class="card mt-4 space-y-5 p-5" enctype="multipart/form-data">
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

        <fieldset>
            <legend class="lbl">1 · {{ __('spare.photos') }}</legend>
            <p class="hint">{{ __('spare.photos.hint') }}</p>
            <input id="spare-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required class="mt-2 block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-action-soft file:px-4 file:py-2.5 file:font-semibold file:text-action-dark">
            <div id="spare-thumbs" class="mt-2 grid grid-cols-5 gap-2"></div>
        </fieldset>

        <fieldset>
            <legend class="lbl">2 · {{ __('spare.what') }}</legend>
            <textarea name="what" rows="3" required minlength="5" maxlength="1500" class="field" placeholder="{{ __('spare.what.ph') }}"></textarea>
            <label class="lbl mt-3">{{ __('spare.use') }}<textarea name="use" rows="2" maxlength="1000" class="field" placeholder="{{ __('spare.use.ph') }}"></textarea></label>
        </fieldset>

        <fieldset>
            <legend class="lbl">3 · {{ __('spare.dims') }} <span class="font-normal text-muted">mm · {{ __('spare.dims.hint') }}</span></legend>
            <div class="mt-1 grid grid-cols-3 gap-3">
                @foreach(['x' => 'param.f.width', 'y' => 'param.f.depth', 'z' => 'param.f.height'] as $axis => $label)
                    <label class="text-sm font-medium text-ink">{{ __($label) }}<input name="dim_{{ $axis }}" type="number" inputmode="decimal" min="0.5" max="2000" step="0.1" class="field"></label>
                @endforeach
            </div>
            <label class="mt-3 flex items-center gap-3 text-sm text-ink"><input type="checkbox" name="original_available" value="1" class="h-5 w-5 accent-action"> {{ __('spare.original') }}</label>
        </fieldset>

        <fieldset>
            <legend class="lbl">4 · {{ __('spare.load') }}</legend>
            <div class="mt-1 grid gap-2 sm:grid-cols-2">
                @foreach(['none', 'light', 'medium', 'heavy', 'unknown'] as $l)
                    <label class="flex items-start gap-2 rounded-xl border border-line p-3 text-sm has-[:checked]:border-action has-[:checked]:bg-action-soft"><input type="radio" name="load" value="{{ $l }}" class="mt-0.5 accent-action" @checked($l === 'unknown')><span><strong class="text-ink">{{ __('spare.load.'.$l) }}</strong><br><span class="text-muted">{{ __('spare.load.'.$l.'.hint') }}</span></span></label>
                @endforeach
            </div>
            <div class="mt-3 text-sm font-semibold text-ink">{{ __('spare.environment') }}</div>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-2 text-sm text-ink">
                @foreach(['outdoor', 'heat', 'water', 'food', 'flexible'] as $e)
                    <label class="flex items-center gap-2"><input type="checkbox" name="environment[]" value="{{ $e }}" class="h-5 w-5 accent-action"> {{ __('spare.env.'.$e) }}</label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="grid gap-3 sm:grid-cols-2">
            <legend class="lbl sm:col-span-2">5 · {{ __('spare.contact') }}</legend>
            <label class="lbl">{{ __('calc.quantity') }}<input name="quantity" type="number" inputmode="numeric" min="1" max="1000" value="1" class="field"></label>
            <label class="lbl">{{ __('calc.material') }}<select name="material" class="field"><option value="">{{ __('spare.material.unknown') }}</option>@foreach($materials as $m)<option value="{{ $m['code'] }}">{{ __('materials.'.$m['code'].'.label') }} ({{ $m['code'] }})</option>@endforeach</select></label>
            @guest
                <label class="lbl">{{ __('auth.email') }}<input name="email" type="email" required autocomplete="email" class="field"></label>
                <label class="lbl">{{ __('auth.name') }}<input name="name" autocomplete="name" class="field"></label>
            @else
                <label class="lbl">{{ __('auth.name') }}<input name="name" value="{{ auth()->user()->name }}" class="field"></label>
                <label class="lbl">{{ __('account.phone') }}<input name="phone" value="{{ auth()->user()->phone }}" class="field"></label>
            @endguest
            <label class="lbl">{{ __('account.zip') }}<input name="zip" required value="{{ auth()->user()?->zip }}" autocomplete="postal-code" class="field"></label>
            <label class="lbl">{{ __('account.city') }}<input name="city" value="{{ auth()->user()?->city }}" class="field"></label>
            <label class="lbl">{{ __('inquiry.form.wanted_by') }}<input name="wanted_by" type="date" class="field"></label>
            <label class="lbl">{{ __('printer.f.delivery') }}<select name="delivery_pref" class="field"><option value="any">{{ __('inquiry.delivery.any') }}</option><option value="pickup">{{ __('inquiry.delivery.pickup') }}</option><option value="shipping">{{ __('inquiry.delivery.shipping') }}</option></select></label>
        </fieldset>

        <p id="spare-error" class="note-error hidden" role="alert"></p>
        <button class="btn-primary w-full">{{ __('spare.submit') }}</button>
        <p class="text-xs text-muted">{{ __('spare.promise') }}</p>
    </form>
</div>
@endsection
