@extends('layouts.app', ['title' => __('user.orders.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold">{{ __('user.orders.title') }}</h1>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('farm.credit_balance') }}: @money($balance)</a>
            <a href="{{ route('farm.start') }}" class="btn-primary text-sm">{{ __('farm.order.new') }}</a>
        </div>
    </div>
    @include('partials.flash')
    @include('partials.verify_banner')

    <nav class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('user.orders.filter') }}">
        <a href="{{ route('account.orders') }}" class="chip {{ $filter === null ? 'chip-on' : '' }}" @if($filter === null) aria-current="page" @endif>{{ __('user.orders.all') }}</a>
        @foreach($filters as $f)
            <a href="{{ route('account.orders', ['status' => $f]) }}" class="chip {{ $filter === $f ? 'chip-on' : '' }}" @if($filter === $f) aria-current="page" @endif>{{ $f === 'open' ? __('user.orders.open') : __('farm.status.'.$f) }}</a>
        @endforeach
    </nav>

    @if($orders->isEmpty())
        <p class="card mt-4 p-6 text-center text-slate-500">{{ $filter ? __('user.orders.empty') : __('farm.no_orders') }}</p>
    @else
        <div class="card mt-4 divide-y divide-slate-100">
            @foreach($orders as $o)
                @include('account._order', ['o' => $o])
            @endforeach
        </div>
    @endif
    <div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection
