@php $untranslated = ($exception ?? null) instanceof \App\Exceptions\UntranslatedPage; @endphp
@extends('layouts.app', ['title' => __($untranslated ? 'site.not_found.untranslated_title' : 'site.not_found.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-xl py-16 text-center">
    <p class="text-5xl font-extrabold tracking-tight text-muted">404</p>
    @if($untranslated)
        <h1 class="mt-4 text-2xl font-bold">{{ __('site.not_found.untranslated_title') }}</h1>
        <p class="mt-3 text-slate-600">{{ __('site.not_found.untranslated_text') }}</p>
        <p class="mt-6"><a id="czech-version" href="{{ $exception->czechUrl }}" hreflang="cs" class="btn-primary">{{ __('site.not_found.czech_version') }}</a></p>
    @else
        <h1 class="mt-4 text-2xl font-bold">{{ __('site.not_found.title') }}</h1>
        <p class="mt-3 text-slate-600">{{ __('site.not_found.text') }}</p>
        <p class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('home') }}" class="btn-primary">{{ __('site.not_found.home') }}</a>
            <a href="{{ route('tools') }}" class="underline hover:text-slate-900">{{ __('site.not_found.tools') }}</a>
        </p>
    @endif
</div>
@endsection
