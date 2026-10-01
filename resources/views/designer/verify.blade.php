@extends('layouts.app', ['title' => __('designer.verify.title', ['source' => __('designer.source.'.$source)]).' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('designer.verify.title', ['source' => __('designer.source.'.$source)]) }}</h1>
        <a href="{{ route('designer.dashboard') }}" class="text-sm text-action-dark">← {{ __('designer.back') }}</a>
    </div>
    @include('partials.flash')

    @if($verified)
        <p class="note-ok mt-4 text-sm">{{ __('designer.verify.verified_as', ['handle' => $handle]) }} <a href="{{ route('designer.import', $source) }}" class="font-semibold underline">{{ __('designer.import.from', ['source' => __('designer.source.'.$source)]) }} →</a></p>
    @endif

    <div class="card mt-4 space-y-4 p-5">
        <p class="text-sm text-slate-700">{{ __('designer.verify.why') }}</p>
        <ol class="space-y-3 text-sm">
            <li class="flex gap-3"><span class="font-bold text-action-dark">1</span>
                <div class="min-w-0 flex-1">
                    {{ __('designer.verify.step1.'.$source) }}
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <code id="verify-token" class="rounded-lg bg-slate-100 px-3 py-2 text-base font-semibold">{{ $token }}</code>
                        <button type="button" class="btn-quiet min-h-0 px-3 py-2 text-sm" data-copy="{{ $token }}" data-copied="{{ __('designer.share.copied') }}">{{ __('designer.share.copy') }}</button>
                    </div>
                </div>
            </li>
            <li class="flex gap-3"><span class="font-bold text-action-dark">2</span><div>{{ __('designer.verify.step2.'.$source) }}</div></li>
            <li class="flex gap-3"><span class="font-bold text-action-dark">3</span><div>{{ __('designer.verify.step3') }}</div></li>
        </ol>
        <form method="post" action="{{ route('designer.verify.check', $source) }}" class="space-y-3">
            @csrf
            <label class="lbl">{{ __('designer.verify.url.'.$source) }}
                <input name="url" type="url" required maxlength="400" value="{{ old('url') }}" placeholder="{{ $source === 'printables' ? 'https://www.printables.com/@jmeno_123456' : 'https://makerworld.com/en/models/123456-nazev' }}" class="field">
            </label>
            <button class="btn-primary">{{ __('designer.verify.check') }}</button>
        </form>
        <p class="hint">{{ __('designer.verify.privacy') }}</p>
    </div>
</div>
@endsection
