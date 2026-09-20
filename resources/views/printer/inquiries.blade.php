@extends('layouts.app', ['title' => __('printer.nav.inquiries').' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    @include('printer.nav')
    @include('partials.flash')
    <h1 class="mt-5 text-2xl font-extrabold">{{ __('printer.nav.inquiries') }}</h1>
    <p class="text-sm text-slate-500">{{ __('inquiry.printer_hint') }}</p>

    @if($dispatches->isEmpty())
        <p class="mt-4 text-sm text-slate-500">{{ __('inquiry.none_for_you') }}</p>
    @else
        <div class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
            @foreach($dispatches as $d)
                @php $i = $d->inquiry; $mine = $i->offers->first(); $th = $threads[$i->id] ?? null; $unread = $th ? $th->unreadFor('printer') : 0; @endphp
                <a href="{{ route('printer.inquiries.show', $i) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 {{ $d->seen_at ? '' : 'bg-action-soft/40' }}">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $i->modelFile?->original_name ?? '—' }} <span class="text-slate-500">· {{ $i->material_code }} · {{ $i->quantity }} {{ __('inquiry.pcs') }}</span></div>
                        <div class="text-xs text-slate-500">{{ $i->city ?: $i->zip }}@if($d->distance_km !== null) · {{ round($d->distance_km) }} km @endif · {{ $i->created_at->format('j. n.') }}@if($i->wanted_by) · {{ __('inquiry.wanted_by') }} {{ $i->wanted_by->format('j. n.') }}@endif
                            @if($unread)<span class="ml-1 rounded-full bg-red-600 px-1.5 text-[10px] text-white">{{ $unread }}</span>@endif</div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold">{{ $mine ? number_format($mine->total, 0, ',', ' ').' Kč' : ($d->auto_price ? '≈ '.number_format($d->auto_price, 0, ',', ' ').' Kč' : '') }}</div>
                        <span class="text-xs text-slate-500">
                            @if($d->declined_at) {{ __('inquiry.declined_status') }}
                            @elseif($i->accepted_quote_id && $mine && $i->accepted_quote_id === $mine->id) ✅ {{ __('inquiry.status.accepted') }}
                            @elseif($mine) {{ __('quote.status.'.$mine->status) }}
                            @else {{ __('inquiry.status.'.$i->status) }} @endif
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $dispatches->links() }}</div>
    @endif
</div>
@endsection
