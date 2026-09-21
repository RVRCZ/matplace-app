@extends('layouts.app', ['title' => __('farm.credit.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-extrabold">{{ __('farm.credit.title') }}</h1>
    <p class="mt-1 text-slate-600">{{ __('farm.credit.lead') }}</p>
    @include('partials.flash')

    @if($pending && $pending->status === 'pending')
        <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">{{ __('farm.credit.processing') }}</p>
    @endif

    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="text-sm text-slate-500">{{ __('farm.credit_balance') }}</div>
        <div class="text-4xl font-extrabold tracking-tight">{{ number_format($balance, 0, ',', ' ') }} <span class="text-lg font-semibold text-slate-500">Kč</span></div>
        @if($need > 0)<p class="mt-2 text-sm text-amber-800">{{ __('farm.credit.need', ['n' => number_format($need, 0, ',', ' ')]) }}</p>@endif
        @if($back)<a href="{{ route('farm.orders.show', $back) }}" class="mt-2 inline-block text-sm text-action-dark underline">{{ __('farm.credit.back_to_order') }}</a>@endif
    </div>

    <form method="post" action="{{ route('account.credit.topup') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        @csrf
        @if($back)<input type="hidden" name="back" value="{{ $back->token }}">@endif
        <div class="text-sm font-semibold text-slate-700">{{ __('farm.credit.amount') }}</div>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach($amounts as $a)
                <button type="submit" name="preset" value="{{ $a }}" formnovalidate class="chip">{{ number_format($a, 0, ',', ' ') }} Kč</button>
            @endforeach
        </div>
        <label class="mt-4 block text-sm font-semibold text-slate-700">{{ __('farm.credit.other') }}
            <span class="mt-1 flex gap-2">
                <input type="number" name="amount" min="{{ $min }}" max="{{ $max }}" step="1" value="{{ max($min, $need) ?: '' }}" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 font-normal" inputmode="numeric">
                <button type="submit" class="btn-primary text-sm">{{ __('farm.credit.pay') }}</button>
            </span>
        </label>
        @error('amount')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </form>

    <h2 class="mt-6 font-bold">{{ __('farm.credit.history') }}</h2>
    <div class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
        @forelse($transactions as $t)
            <div class="flex items-center justify-between gap-2 px-4 py-2 text-sm">
                <span>
                    {{ __('farm.credit.type.'.$t->type) }}
                    @if($t->order)<a href="{{ route('farm.orders.show', $t->order) }}" class="text-action-dark underline">{{ $t->order->number ?? '' }}</a>@endif
                    <span class="block text-xs text-slate-500">{{ $t->created_at->format('j. n. Y H:i') }}</span>
                </span>
                <span class="font-semibold {{ $t->amount < 0 ? 'text-slate-700' : 'text-ok' }}">{{ $t->amount > 0 ? '+' : '' }}{{ number_format($t->amount, 0, ',', ' ') }} Kč</span>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-500">{{ __('farm.credit.empty') }}</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
@endsection
