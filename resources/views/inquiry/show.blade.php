@extends('layouts.app', ['title' => __('inquiry.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-5xl">
    @include('partials.flash')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">{{ __('inquiry.title') }} <span class="text-slate-400">#{{ $inquiry->token }}</span></h1>
            <div class="text-sm text-slate-500">{{ $inquiry->modelFile?->original_name }} · {{ $inquiry->material_code }} · {{ $inquiry->quantity }} {{ __('inquiry.pcs') }}@if($inquiry->color) · {{ $inquiry->color }}@endif · {{ $inquiry->city ?: $inquiry->zip }}</div>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ ['pending' => 'bg-amber-50 text-amber-800', 'open' => 'bg-blue-50 text-blue-800', 'offered' => 'bg-blue-50 text-blue-800', 'accepted' => 'bg-action-soft text-action-dark', 'done' => 'bg-action-soft text-action-dark', 'cancelled' => 'bg-slate-100 text-slate-600', 'expired' => 'bg-slate-100 text-slate-600'][$inquiry->status] }}">{{ __('inquiry.status.'.$inquiry->status) }}</span>
    </div>

    @if($inquiry->status === 'pending')
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('inquiry.pending_hint', ['email' => $inquiry->contact_email]) }}</div>
    @endif

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_1.2fr]">
        <div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                @if($inquiry->kind === 'spare_part')@include('inquiry.spare_details')@endif
                @if($inquiry->modelFile && $inquiry->modelFile->isReady())
                    <canvas class="mini-viewer block h-64 w-full touch-none" data-stl="{{ route('api.files.stl', $inquiry->modelFile->uuid) }}"></canvas>
                @endif
                <div class="p-4 text-sm">
                    @if($inquiry->summary)
                        <div class="grid grid-cols-3 gap-2">
                            <div><div class="text-slate-500">{{ __('calc.size') }}</div><div class="font-semibold">{{ $inquiry->summary['dims']['x'] ?? '?' }}×{{ $inquiry->summary['dims']['y'] ?? '?' }}×{{ $inquiry->summary['dims']['z'] ?? '?' }} mm</div></div>
                            <div><div class="text-slate-500">{{ __('calc.weight') }}</div><div class="font-semibold">{{ $inquiry->summary['grams'] ?? '?' }} g</div></div>
                            <div><div class="text-slate-500">{{ __('calc.time') }}</div><div class="font-semibold">{{ $inquiry->summary['minutes'] ?? '?' }} min</div></div>
                        </div>
                    @endif
                    @if($inquiry->note)<p class="mt-3 whitespace-pre-line text-slate-600">{{ $inquiry->note }}</p>@endif
                    <div class="mt-3 text-xs text-slate-500">{{ __('inquiry.sent_to', ['n' => $inquiry->dispatches->count()]) }}
                        @if($inquiry->calculation)· <a href="{{ route('calc.share', $inquiry->calculation) }}" class="text-action-dark">{{ __('inquiry.open_calc') }}</a>@endif
                    </div>
                </div>
            </div>
            @if(in_array($inquiry->status, ['pending', 'open', 'offered']))
                <form method="post" action="{{ route('inquiry.cancel', $inquiry) }}" class="mt-2 text-right">@csrf<button class="text-xs text-slate-500 hover:text-red-700">{{ __('inquiry.cancel') }}</button></form>
            @endif
        </div>

        <div class="space-y-3">
            <h2 class="text-lg font-bold">{{ __('inquiry.offers') }} ({{ $offers->count() }})</h2>
            @if($offers->isEmpty())
                <p class="text-sm text-slate-500">{{ $inquiry->status === 'pending' ? '' : __('inquiry.no_offers_yet') }}</p>
            @endif
            @foreach($offers as $o)
                @php $p = $o->printerProfile; $th = $threads[$p->id] ?? null; $isAccepted = $inquiry->accepted_quote_id === $o->id; @endphp
                <div class="rounded-2xl border {{ $isAccepted ? 'border-action' : 'border-slate-200' }} bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="font-bold">@if($p->visible)<a class="underline decoration-slate-300 hover:text-action-dark" target="_blank" href="{{ route('printers.show', $p->slug) }}">{{ $p->display_name }}</a>@else{{ $p->display_name }}@endif @if($p->user->rating_count) <span class="text-sm font-normal text-amber-600">★ {{ number_format($p->user->rating_avg, 1) }} ({{ $p->user->rating_count }})</span>@endif</div>
                            <div class="text-xs text-slate-500">{{ $p->user->city ?: '' }} @php $d = $inquiry->dispatches->firstWhere('printer_profile_id', $p->id); @endphp @if($d?->distance_km !== null)· {{ round($d->distance_km) }} km @endif @if($o->lead_time_days !== null)· {{ __('calc.days', ['n' => $o->lead_time_days]) }}@endif</div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-extrabold">{{ number_format($o->total, 0, ',', ' ') }} Kč</div>
                            <span class="text-xs text-slate-500">{{ __('quote.status.'.$o->status) }}</span>
                        </div>
                    </div>
                    @if($o->note)<p class="mt-2 text-sm text-slate-600">{{ $o->note }}</p>@endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($inquiry->isOpenForOffers() && in_array($o->status, ['sent', 'viewed']))
                            <form method="post" action="{{ route('inquiry.accept', [$inquiry, $o]) }}">@csrf<button class="rounded-xl bg-action px-4 py-2 font-semibold text-white">{{ __('inquiry.accept') }}</button></form>
                        @elseif($isAccepted)
                            <span class="rounded-xl bg-action-soft px-4 py-2 font-semibold text-action-dark">✅ {{ __('inquiry.you_accepted') }}</span>
                        @endif
                        <a href="{{ route('quote.public.pdf', $o) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">PDF</a>
                    </div>
                    @if($th)
                        <div class="mt-3">@include('partials.chat', ['thread' => $th, 'side' => 'customer', 'inquiryToken' => $inquiry->token])</div>
                    @endif
                </div>
            @endforeach

            @if($inquiry->status === 'accepted')
                <form method="post" action="{{ route('inquiry.done', $inquiry) }}" class="rounded-2xl border border-slate-200 bg-white p-4">@csrf
                    <div class="text-sm text-slate-600">{{ __('inquiry.done_hint') }}</div>
                    <button class="mt-2 rounded-xl border border-action px-4 py-2 font-semibold text-action-dark">{{ __('inquiry.mark_done') }}</button>
                </form>
            @endif
            @if($inquiry->status === 'done' && !$rated)
                <form method="post" action="{{ route('inquiry.rate', $inquiry) }}" class="rounded-2xl border border-slate-200 bg-white p-4">@csrf
                    <div class="font-semibold">{{ __('inquiry.rate_title', ['printer' => $inquiry->acceptedQuote?->printerProfile?->display_name]) }}</div>
                    <div class="mt-2 flex gap-2">@for($i = 5; $i >= 1; $i--)<label class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2"><input type="radio" name="score" value="{{ $i }}" required class="mr-1">{{ $i }} ★</label>@endfor</div>
                    <textarea name="comment" rows="2" maxlength="1000" placeholder="{{ __('inquiry.rate_comment') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                    <button class="mt-2 rounded-xl bg-action px-4 py-2 font-semibold text-white">{{ __('inquiry.rate_submit') }}</button>
                </form>
            @elseif($rated)
                <div class="text-sm text-slate-500">{{ __('inquiry.rated') }}</div>
            @endif
        </div>
    </div>
    <p class="mt-6 text-xs text-slate-400">{{ __('inquiry.promise') }}</p>
</div>
@endsection
