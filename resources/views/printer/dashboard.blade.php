@extends('layouts.app', ['title' => __('printer.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    @include('printer.nav')
    @include('partials.flash')

    @php $missing = array_keys(array_filter($checklist, fn ($v) => ! $v)); @endphp
    @if($missing)
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <div class="font-semibold text-amber-900">{{ __('printer.checklist.title') }}</div>
            <ul class="mt-1 list-disc pl-5 text-sm text-amber-800">
                @foreach($missing as $m)<li>{{ __('printer.checklist.'.$m) }}</li>@endforeach
            </ul>
            <a href="{{ route('printer.profile') }}" class="mt-2 inline-block text-sm font-semibold text-amber-900 underline">{{ __('printer.checklist.go') }}</a>
        </div>
    @endif

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <a href="{{ route('printer.calculator') }}" class="rounded-2xl bg-teal-600 p-5 text-white hover:bg-teal-700">
            <div class="text-lg font-bold">{{ __('printer.calc.title') }}</div>
            <div class="text-sm opacity-90">{{ __('printer.calc.hint') }}</div>
        </a>
        <a href="{{ route('printer.profile') }}" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-400">
            <div class="text-lg font-bold">{{ __('printer.profile.title') }}</div>
            <div class="text-sm text-slate-500">{{ __('printer.profile.hint') }}</div>
        </a>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-bold">{{ __('printer.quotes.recent') }}</h2>
        <a href="{{ route('printer.quotes') }}" class="text-sm text-teal-700">{{ __('printer.quotes.all') }} →</a>
    </div>
    @include('printer.quote_list', ['quotes' => $quotes])
</div>
@endsection
