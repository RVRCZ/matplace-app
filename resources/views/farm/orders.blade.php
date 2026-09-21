@extends('layouts.app', ['title' => __('farm.my_orders').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold">{{ __('farm.my_orders') }}</h1>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('farm.credit_balance') }}: {{ number_format($balance, 0, ',', ' ') }} Kč</a>
            <a href="{{ route('farm.start') }}" class="btn-primary text-sm">{{ __('farm.order.new') }}</a>
        </div>
    </div>
    @include('partials.flash')

    <div class="mt-4 space-y-2">
        @forelse($orders as $o)
            <a href="{{ route('farm.orders.show', $o) }}" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white p-4 hover:border-action">
                <span>
                    <span class="font-semibold">{{ $o->modelFile?->original_name ?? '—' }}</span>
                    <span class="block text-xs text-slate-500">{{ $o->number ?? $o->created_at->format('j. n. Y H:i') }}@if($o->color) · {{ $o->color->name }}@endif</span>
                </span>
                <span class="text-right text-sm">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ in_array($o->status, ['failed', 'cancelled']) ? 'bg-slate-100 text-slate-600' : ($o->status === 'done' || $o->status === 'handed_over' ? 'bg-ok-soft text-ok' : 'bg-action-soft text-action-dark') }}">{{ __('farm.status.'.$o->status) }}</span>
                    @if($o->price_total)<span class="mt-1 block font-semibold">{{ number_format($o->price_total, 0, ',', ' ') }} Kč</span>@endif
                </span>
            </a>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-slate-500">{{ __('farm.no_orders') }}</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection
