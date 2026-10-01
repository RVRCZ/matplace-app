@extends('layouts.app', ['title' => __('account.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('account.hello', ['name' => $user->name]) }}</h1>
        <div class="flex items-center gap-4 text-sm">
            @if($user->isAdmin() && $farm)<a href="{{ route('admin.farm.dashboard') }}" class="font-semibold text-action-dark">{{ __('user.dash.admin') }} →</a>@endif
            <a href="{{ route('account.profile') }}" class="text-action-dark">{{ __('account.edit_profile') }}</a>
        </div>
    </div>
    @include('partials.flash')
    @include('partials.verify_banner')

    {{-- the designer profile card (step B) sits above everything else --}}
    @includeIf('account._designer_card')

    {{-- 1. prints --}}
    @if($farm)
        <section id="prints" class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold">{{ __('user.dash.prints') }}</h2>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('user.dash.credit') }}: {{ number_format($balance, 0, ',', ' ') }} Kč</a>
                    <a href="{{ route('farm.start') }}" class="btn-primary min-h-0 px-3 py-1.5 text-sm">{{ __('user.dash.new_print') }}</a>
                </div>
            </div>
            @if($orders->isEmpty())
                <p class="card mt-2 p-4 text-sm text-slate-500">{{ __('user.dash.prints_empty') }}</p>
            @else
                <div class="card mt-2 divide-y divide-slate-100">
                    @foreach($orders as $o)
                        @include('account._order', ['o' => $o])
                    @endforeach
                </div>
                @if($ordersTotal > $orders->count())<a href="{{ route('account.orders') }}" class="mt-2 inline-block text-sm text-action-dark underline">{{ __('user.dash.prints_all') }} ({{ $ordersTotal }}) →</a>@endif
            @endif
        </section>
    @endif

    {{-- 2. models --}}
    <section id="models" class="mt-8">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">{{ __('user.dash.models') }}</h2>
            <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">{{ __('user.dash.models_start') }}</a>
        </div>
        @if($models->isEmpty())
            <p class="card mt-2 p-4 text-sm text-slate-500">{{ __('user.dash.models_empty') }}</p>
        @else
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($models as $m)
                    @include('account._model', ['m' => $m, 'lock' => $locks[$m->id] ?? null, 'farm' => $farm])
                @endforeach
            </div>
            @if($modelsTotal > $models->count())<a href="{{ route('account.models') }}" class="mt-2 inline-block text-sm text-action-dark underline">{{ __('user.dash.models_all') }} ({{ $modelsTotal }}) →</a>@endif
        @endif
    </section>

    {{-- 3. calculations --}}
    <section id="calculations" class="mt-8">
        <h2 class="text-lg font-bold">{{ __('user.dash.calculations') }}</h2>
        @if($calculations->isEmpty())
            <p class="mt-2 text-sm text-slate-500">{{ __('user.dash.calculations_empty') }} <a href="{{ route('home') }}" class="text-action-dark">{{ __('account.start') }}</a></p>
        @else
            <div class="card mt-2 divide-y divide-slate-100">
                @foreach($calculations as $c)
                    @include('account._calculation', ['c' => $c])
                @endforeach
            </div>
            @if($calculationsTotal > $calculations->count())<a href="{{ route('account.calculations') }}" class="mt-2 inline-block text-sm text-action-dark underline">{{ __('user.dash.calculations_all') }} ({{ $calculationsTotal }}) →</a>@endif
        @endif
    </section>

    <form method="post" action="{{ route('logout') }}" class="mt-8">@csrf<button class="text-sm text-slate-500 hover:text-slate-800">{{ __('auth.logout') }}</button></form>
</div>
@include('partials.printer_pick')
@endsection
