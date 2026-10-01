@extends('layouts.app', ['title' => __('designer.bulk.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('designer.bulk.title') }}</h1>
        <a href="{{ route('designer.dashboard') }}" class="text-sm text-action-dark">← {{ __('designer.back') }}</a>
    </div>
    @include('partials.flash')
    <div class="card mt-4 space-y-4 p-5">
        <p class="text-sm text-slate-700">{{ __('designer.bulk.lead') }}</p>
        <p class="text-sm font-semibold">{{ trans_choice('designer.bulk.waiting', $waiting, ['n' => $waiting]) }}</p>
        @if($waiting > 0)
            <form method="post" action="{{ route('designer.bulk.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-line bg-slate-50 px-4 py-10 text-center text-sm hover:border-action" data-dropzone>
                    <span class="font-semibold" data-dropzone-label data-empty="{{ __('designer.bulk.drop') }}">{{ __('designer.bulk.drop') }}</span>
                    <span class="text-muted">{{ __('designer.bulk.formats', ['max' => $maxMb]) }}</span>
                    <input type="file" name="zip" required accept=".zip,application/zip" class="sr-only">
                </label>
                <button class="btn-primary">{{ __('designer.bulk.upload') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
