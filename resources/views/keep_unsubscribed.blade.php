@extends('layouts.app', ['title' => __('keep.off.title').' · matplace', 'noindex' => true])

@section('content')
<article class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 text-slate-700">
    <h1 class="text-2xl font-extrabold text-ink">{{ __('keep.off.title') }}</h1>
    <p class="mt-3">{{ __('keep.off.body') }}</p>
    <p class="mt-4"><a href="{{ route('home') }}" class="btn-secondary">{{ __('keep.off.home') }}</a></p>
</article>
@endsection
