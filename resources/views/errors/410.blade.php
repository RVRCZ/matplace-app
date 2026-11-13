{{-- An address of the old site without a successor (config/legacy.php `gone`): search engines drop it, a person gets somewhere useful. --}}
@extends('layouts.app', ['title' => __('site.gone.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-xl py-16 text-center">
    <p class="text-5xl font-extrabold tracking-tight text-muted">410</p>
    <h1 class="mt-4 text-2xl font-bold">{{ __('site.gone.title') }}</h1>
    <p class="mt-3 text-slate-600">{{ __('site.gone.text') }}</p>
    <p class="mt-6 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('home') }}" class="btn-primary">{{ __('site.not_found.home') }}</a>
        <a href="{{ route('tools') }}" class="underline hover:text-slate-900">{{ __('site.not_found.tools') }}</a>
    </p>
</div>
@endsection
