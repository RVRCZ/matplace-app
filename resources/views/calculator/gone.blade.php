@extends('layouts.app', ['title' => __('calc.gone.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-xl py-16 text-center">
    <h1 class="text-2xl font-bold">{{ __('calc.gone.title') }}</h1>
    <p class="mt-3 text-slate-600">{{ __('calc.gone.text', ['days' => config('uploads.unordered_retention_days')]) }}</p>
    <p class="mt-6"><a href="{{ route('home') }}" class="btn-primary">{{ __('calc.gone.cta') }}</a></p>
</div>
@endsection
