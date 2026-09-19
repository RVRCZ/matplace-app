@extends('layouts.app', ['title' => __('quote.pdf.title').' '.$quote->number.' · '.$profile->display_name])

@section('content')
<div class="mx-auto max-w-4xl">
    @if(session('status'))<div class="mb-4 rounded-lg bg-teal-50 px-4 py-3 text-teal-800">{{ session('status') }}</div>@endif

    <div class="grid gap-4 lg:grid-cols-[1.1fr_1fr]">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @if($quote->modelFile && $quote->modelFile->isReady())
                <canvas id="quote-viewer" data-stl="{{ route('api.files.stl', $quote->modelFile->uuid) }}" class="block h-[40vh] w-full touch-none lg:h-[60vh]"></canvas>
            @else
                <div class="flex h-64 items-center justify-center text-slate-400">{{ $quote->title ?? '—' }}</div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    @if($logo)<img src="{{ $logo }}" alt="" class="mb-2 max-h-14">@endif
                    <div class="text-lg font-bold">{{ $profile->company ?: $profile->display_name }}</div>
                    <div class="text-sm text-slate-500">{{ $profile->contact_email }} {{ $profile->contact_phone ? '· '.$profile->contact_phone : '' }}</div>
                </div>
                <div class="text-right text-sm text-slate-500">
                    <div class="font-semibold text-slate-800">{{ __('quote.pdf.title') }} {{ $quote->number }}</div>
                    @if($quote->valid_until)<div>{{ __('quote.valid_until') }} {{ $quote->valid_until->format('j. n. Y') }}</div>@endif
                    @if($quote->lead_time_days !== null)<div>{{ __('quote.lead_time') }}: {{ __('calc.days', ['n' => $quote->lead_time_days]) }}</div>@endif
                </div>
            </div>

            @if($quote->client_name || $quote->client_email)<div class="mt-3 text-sm text-slate-500">{{ __('quote.client') }}: <span class="font-medium text-slate-800">{{ $quote->client_name }}</span> {{ $quote->client_email ? '· '.$quote->client_email : '' }}</div>@endif
            @if($quote->title)<div class="mt-2 font-semibold">{{ $quote->title }}</div>@endif
            @if($quote->params)
                <div class="text-sm text-slate-500">{{ $quote->params['material'] ?? '' }} · {{ __('calc.quality.'.($quote->params['quality'] ?? 'standard')) }} · {{ $quote->params['infill'] ?? '' }} % · {{ $quote->params['quantity'] ?? 1 }} ks</div>
            @endif

            <table class="mt-4 w-full text-sm">
                @foreach($quote->lines as $l)
                    <tr class="border-b border-slate-100"><td class="py-1.5">{{ $l['label'] }}</td><td class="py-1.5 text-right text-slate-500">{{ rtrim(rtrim(number_format($l['qty'], 2, ',', ' '), '0'), ',') }} × {{ number_format($l['unit_price'], 0, ',', ' ') }}</td><td class="py-1.5 text-right font-medium">{{ number_format($l['total'], 0, ',', ' ') }}</td></tr>
                @endforeach
                <tr><td class="pt-3 text-lg font-bold" colspan="2">{{ __('quote.pdf.sum') }}</td><td class="pt-3 text-right text-2xl font-extrabold">{{ number_format($quote->total, 0, ',', ' ') }} Kč</td></tr>
            </table>

            @if($quote->note)<div class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm">{{ $quote->note }}</div>@endif

            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                @if($quote->isOpen())
                    <form method="post" action="{{ route('quote.accept', $quote) }}">@csrf<button class="w-full rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white">{{ __('quote.btn.accept') }}</button></form>
                    <form method="post" action="{{ route('quote.decline', $quote) }}">@csrf<button class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700">{{ __('quote.btn.decline') }}</button></form>
                @elseif($quote->status === 'accepted')
                    <div class="rounded-xl bg-teal-50 px-4 py-3 text-center font-semibold text-teal-800 sm:col-span-2">✅ {{ __('quote.status.accepted') }}</div>
                @elseif($quote->status === 'declined')
                    <div class="rounded-xl bg-slate-100 px-4 py-3 text-center text-slate-600 sm:col-span-2">{{ __('quote.status.declined') }}</div>
                @else
                    <div class="rounded-xl bg-slate-100 px-4 py-3 text-center text-slate-600 sm:col-span-2">{{ __('quote.status.expired') }}</div>
                @endif
                <a href="{{ route('quote.public.pdf', $quote) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-center font-semibold text-slate-700 sm:col-span-2">⬇ PDF</a>
            </div>
            <p class="mt-4 text-xs text-slate-400">{{ __('quote.public.footer') }}</p>
        </div>
    </div>
</div>
@endsection
