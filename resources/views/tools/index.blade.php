@extends('layouts.app', ['title' => __('tools.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-extrabold">{{ __('tools.title') }}</h1>
    <p class="text-slate-600">{{ __('tools.lead') }}</p>
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <a href="{{ route('tools.figure') }}" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-400">
            <div class="text-2xl">🗿</div>
            <div class="mt-1 text-lg font-bold">{{ __('tools.figure.title') }}</div>
            <div class="text-sm text-slate-500">{{ __('tools.figure.hint') }}</div>
        </a>
        <a href="{{ route('home') }}" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-400">
            <div class="text-2xl">📄</div>
            <div class="mt-1 text-lg font-bold">{{ __('tools.calc.title') }}</div>
            <div class="text-sm text-slate-500">{{ __('tools.calc.hint') }}</div>
        </a>
        <a href="{{ route('tools.sign') }}" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-400">
            <div class="text-2xl">🏷️</div>
            <div class="mt-1 text-lg font-bold">{{ __('tools.sign.title') }}</div>
            <div class="text-sm text-slate-500">{{ __('tools.sign.hint') }}</div>
        </a>
        @foreach (['relief' => '🖼️'] as $k => $ico)
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 opacity-70">
                <div class="text-2xl">{{ $ico }}</div>
                <div class="mt-1 text-lg font-bold">{{ __('tools.'.$k.'.title') }} <span class="text-xs font-normal text-slate-400">({{ __('hero.soon') }})</span></div>
                <div class="text-sm text-slate-500">{{ __('tools.'.$k.'.hint') }}</div>
            </div>
        @endforeach
    </div>
</div>
@endsection
