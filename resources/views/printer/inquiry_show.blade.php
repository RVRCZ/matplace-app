@extends('layouts.app', ['title' => __('inquiry.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-5xl">
    @include('printer.nav')
    @include('partials.flash')

    <div class="mt-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">{{ $inquiry->modelFile?->original_name ?? __('inquiry.title') }}</h1>
            <div class="text-sm text-slate-500">{{ $inquiry->material_code }} · {{ __('calc.quality.'.($inquiry->params['quality'] ?? 'standard')) }} · {{ $inquiry->params['infill'] ?? '' }} % · {{ $inquiry->quantity }} {{ __('inquiry.pcs') }}
                @if(!empty($inquiry->params['scale']) && (float)$inquiry->params['scale'] !== 1.0)· {{ (int)($inquiry->params['scale']*100) }} %@endif</div>
            <div class="text-sm text-slate-500">{{ $inquiry->contact_name }} · {{ $inquiry->city ?: $inquiry->zip }}@if($dispatch->distance_km !== null) · {{ round($dispatch->distance_km) }} km @endif · {{ __('inquiry.delivery.'.$inquiry->delivery_pref) }}@if($inquiry->wanted_by) · {{ __('inquiry.wanted_by') }} {{ $inquiry->wanted_by->format('j. n. Y') }}@endif</div>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm">{{ __('inquiry.status.'.$inquiry->status) }}</span>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_1fr]">
        <div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                @if($inquiry->modelFile && $inquiry->modelFile->isReady())
                    <canvas class="mini-viewer block h-72 w-full touch-none" data-stl="{{ route('api.files.stl', $inquiry->modelFile->uuid) }}"></canvas>
                @endif
                <div class="p-4 text-sm">
                    @if($inquiry->summary)
                        <div class="grid grid-cols-3 gap-2">
                            <div><div class="text-slate-500">{{ __('calc.size') }}</div><div class="font-semibold">{{ $inquiry->summary['dims']['x'] ?? '?' }}×{{ $inquiry->summary['dims']['y'] ?? '?' }}×{{ $inquiry->summary['dims']['z'] ?? '?' }} mm</div></div>
                            <div><div class="text-slate-500">{{ __('calc.weight') }}</div><div class="font-semibold">{{ $inquiry->summary['grams'] ?? '?' }} g</div></div>
                            <div><div class="text-slate-500">{{ __('calc.time') }}</div><div class="font-semibold">{{ $inquiry->summary['minutes'] ?? '?' }} min</div></div>
                        </div>
                        @if(empty($inquiry->summary['precise']))<div class="mt-1 text-xs text-amber-700">{{ __('inquiry.rough_only') }}</div>@endif
                    @endif
                    @if($inquiry->note)<p class="mt-3 whitespace-pre-line text-slate-600">{{ $inquiry->note }}</p>@endif
                    @if($inquiry->modelFile && $inquiry->modelFile->isReady())
                        <a href="{{ route('api.files.stl', $inquiry->modelFile->uuid) }}" download class="mt-3 inline-block rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700">⬇ STL</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-3">
            @if($offer)
                <div class="rounded-2xl border {{ $accepted ? 'border-teal-500' : 'border-slate-200' }} bg-white p-4">
                    <div class="flex items-center justify-between"><div class="font-bold">{{ __('inquiry.your_offer') }}</div><span class="text-xs text-slate-500">{{ __('quote.status.'.$offer->status) }}</span></div>
                    <div class="text-2xl font-extrabold">{{ number_format($offer->total, 0, ',', ' ') }} Kč</div>
                    @if($offer->lead_time_days !== null)<div class="text-sm text-slate-500">{{ __('calc.days', ['n' => $offer->lead_time_days]) }}</div>@endif
                    @if($accepted)<div class="mt-2 rounded-lg bg-teal-50 px-3 py-2 text-sm text-teal-800">✅ {{ __('inquiry.accepted_printer', ['name' => $inquiry->contact_name ?: $inquiry->contact_email, 'email' => $inquiry->contact_email, 'phone' => $inquiry->contact_phone ?: '—']) }}</div>@endif
                </div>
            @elseif($dispatch->declined_at)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-500">{{ __('inquiry.you_declined') }}</div>
            @elseif($inquiry->isOpenForOffers())
                <form method="post" action="{{ route('printer.inquiries.offer', $inquiry) }}" class="rounded-2xl border border-slate-200 bg-white p-4">@csrf
                    <div class="font-bold">{{ __('inquiry.make_offer') }}</div>
                    @if($dispatch->auto_breakdown)
                        <div class="mt-1 text-xs text-slate-500">{{ __('inquiry.auto_price_hint') }}: {{ __('calc.breakdown.material') }} {{ number_format($dispatch->auto_breakdown['unit']['material'] * $inquiry->quantity, 0, ',', ' ') }} · {{ __('calc.breakdown.time') }} {{ number_format($dispatch->auto_breakdown['unit']['time'] * $inquiry->quantity, 0, ',', ' ') }} · {{ __('calc.breakdown.setup') }} {{ number_format($dispatch->auto_breakdown['setup'], 0, ',', ' ') }}</div>
                    @endif
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <label class="text-sm font-semibold">{{ __('inquiry.price_total') }} <span class="font-normal text-slate-500">Kč</span><input name="total" type="number" min="1" step="1" required value="{{ old('total', $dispatch->auto_price ? (int) $dispatch->auto_price : '') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-lg font-bold"></label>
                        <label class="text-sm font-semibold">{{ __('quote.lead_time') }} <span class="font-normal text-slate-500">{{ __('quote.days_short') }}</span><input name="lead_time_days" type="number" min="0" value="{{ old('lead_time_days', $profile->defaultPricing()?->lead_time_days ?? $profile->lead_time_days) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                    </div>
                    <textarea name="note" rows="2" maxlength="2000" placeholder="{{ __('inquiry.offer_note') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('note') }}</textarea>
                    <button class="mt-3 w-full rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white">{{ __('inquiry.send_offer') }}</button>
                </form>
                <form method="post" action="{{ route('printer.inquiries.decline', $inquiry) }}" class="text-right">@csrf<input type="hidden" name="reason" value=""><button class="text-xs text-slate-500 hover:text-red-700">{{ __('inquiry.decline') }}</button></form>
            @else
                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-500">{{ __('inquiry.closed_for_offers') }}</div>
            @endif

            @if($thread)
                @include('partials.chat', ['thread' => $thread, 'side' => 'printer'])
            @endif
        </div>
    </div>
</div>
@endsection
