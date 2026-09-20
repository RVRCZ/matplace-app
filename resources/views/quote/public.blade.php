@extends('layouts.app', ['title' => __('quote.pdf.title').' '.$quote->number.' · '.$profile->display_name, 'noindex' => true])

@php $nf = fn ($v, $d = 0) => number_format((float) $v, $d, app()->getLocale() === 'en' ? '.' : ',', app()->getLocale() === 'en' ? ',' : ' '); @endphp

@section('content')
<div class="mx-auto max-w-4xl">
    @if(session('status'))<div class="note-ok mb-4" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="note-warn mb-4" role="alert">{{ $errors->first() }}</div>@endif

    <div class="grid gap-4 lg:grid-cols-[1.1fr_1fr]">
        <div class="card overflow-hidden">
            @if($quote->modelFile && $quote->modelFile->isReady())
                <canvas id="quote-viewer" data-stl="{{ route('api.files.stl', $quote->modelFile->uuid) }}" class="block h-[40vh] w-full touch-none lg:h-[60vh]" role="img" aria-label="{{ __('param.viewer') }}"></canvas>
            @else
                <div class="flex h-64 items-center justify-center text-muted">{{ $quote->title ?? '—' }}</div>
            @endif
        </div>

        <div class="card p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    @if($logo)<img src="{{ $logo }}" alt="" class="mb-2 max-h-14">@endif
                    <div class="text-lg font-bold text-ink">@if($profile->visible)<a class="underline decoration-line" href="{{ route('printers.show', $profile->slug) }}" target="_blank">{{ $profile->company ?: $profile->display_name }}</a>@else{{ $profile->company ?: $profile->display_name }}@endif</div>
                    <div class="text-sm text-muted">{{ $profile->contact_email }} {{ $profile->contact_phone ? '· '.$profile->contact_phone : '' }}</div>
                </div>
                <div class="text-right text-sm text-muted">
                    <div class="font-semibold text-ink">{{ __('quote.pdf.title') }} {{ $quote->number }}</div>
                    <div>{{ __('quote.version', ['n' => $quote->version]) }}</div>
                </div>
            </div>

            @if($quote->client_name)<div class="mt-3 text-sm text-muted">{{ __('quote.client') }}: <span class="font-medium text-ink">{{ $quote->client_name }}</span></div>@endif
            @if($quote->title)<h1 class="mt-2 text-xl font-bold text-ink">{{ $quote->title }}</h1>@endif

            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                @if(!empty($quote->params['material']))<div><dt class="text-muted">{{ __('calc.material') }}</dt><dd class="font-semibold text-ink">{{ $quote->params['material'] }}</dd></div>@endif
                @if($quote->color)<div><dt class="text-muted">{{ __('param.color') }}</dt><dd class="font-semibold text-ink">{{ $quote->color }}</dd></div>@endif
                <div><dt class="text-muted">{{ __('calc.quantity') }}</dt><dd class="font-semibold text-ink">{{ $quote->params['quantity'] ?? 1 }} {{ __('inquiry.pcs') }}</dd></div>
                @if($quote->lead_time_days !== null)<div><dt class="text-muted">{{ __('quote.lead_time') }}</dt><dd class="font-semibold text-ink">{{ __('calc.days', ['n' => $quote->lead_time_days]) }}</dd></div>@endif
                @if($quote->valid_until)<div><dt class="text-muted">{{ __('quote.valid_until') }}</dt><dd class="font-semibold text-ink">{{ $quote->valid_until->format('j. n. Y') }}</dd></div>@endif
            </dl>

            <table class="mt-4 w-full text-sm">
                <caption class="sr-only">{{ __('quote.pdf.title') }}</caption>
                @foreach($lines as $l)
                    <tr class="border-b border-line"><td class="py-1.5 text-ink">{{ $l['label'] }}</td><td class="py-1.5 text-right text-muted">{{ rtrim(rtrim($nf($l['qty'], 2), '0'), ',.') }} × {{ $nf($l['unit_price']) }}</td><td class="py-1.5 text-right font-medium text-ink">{{ $nf($l['total']) }}</td></tr>
                @endforeach
                <tr><td class="pt-3 text-lg font-bold text-ink" colspan="2">{{ __('quote.pdf.sum') }}</td><td class="pt-3 text-right text-2xl font-extrabold text-ink">{{ $nf($quote->total) }} Kč</td></tr>
            </table>

            @if($quote->note)<div class="mt-3 whitespace-pre-line rounded-lg bg-page p-3 text-sm text-ink">{{ $quote->note }}</div>@endif

            <div class="mt-5 space-y-2">
                @if($quote->isOpen())
                    <form method="post" action="{{ route('quote.accept', $quote) }}">@csrf<input type="hidden" name="version" value="{{ $quote->version }}"><button class="btn-primary w-full">{{ __('quote.btn.accept') }}</button></form>
                    <details class="rounded-xl border border-line p-3">
                        <summary class="cursor-pointer font-semibold text-action-dark">{{ __('quote.btn.change') }}</summary>
                        <form method="post" action="{{ route('quote.change', $quote) }}" class="mt-2">@csrf
                            <input type="hidden" name="version" value="{{ $quote->version }}"><input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                            <label class="lbl">{{ __('quote.change.label') }}<textarea name="message" rows="3" required minlength="3" maxlength="1000" class="field" placeholder="{{ __('quote.change.ph') }}"></textarea></label>
                            <button class="btn-secondary mt-2 w-full">{{ __('quote.change.send') }}</button>
                        </form>
                    </details>
                    <form method="post" action="{{ route('quote.decline', $quote) }}">@csrf<button class="btn-quiet w-full">{{ __('quote.btn.decline') }}</button></form>
                @elseif($quote->status === 'accepted')
                    <div class="note-ok text-center font-semibold">✓ {{ __('quote.public.accepted_version', ['n' => $quote->accepted_version]) }}</div>
                @elseif($quote->status === 'change')
                    <div class="note-warn text-center">{{ __('quote.public.change_pending') }}</div>
                @elseif($quote->status === 'declined')
                    <div class="rounded-xl bg-slate-100 px-4 py-3 text-center text-muted">{{ __('quote.status.declined') }}</div>
                @else
                    <div class="rounded-xl bg-slate-100 px-4 py-3 text-center text-muted">{{ __('quote.status.expired') }}</div>
                @endif
                <a href="{{ route('quote.public.pdf', $quote) }}" class="btn-quiet w-full">⬇ PDF</a>
            </div>
            <p class="mt-4 text-xs text-muted">{{ __('quote.public.footer') }}</p>
        </div>
    </div>
</div>
@endsection
