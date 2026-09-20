@extends('layouts.app', ['title' => __('account.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('account.hello', ['name' => $user->name]) }}</h1>
        <a href="{{ route('account.profile') }}" class="text-sm text-action-dark">{{ __('account.edit_profile') }}</a>
    </div>
    @include('partials.flash')

    {{-- role switches --}}
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach(['printer' => '🖨️', 'designer' => '🧩'] as $role => $ico)
            @php $on = $user->hasRole($role); @endphp
            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                <div>
                    <div class="font-semibold">{{ $ico }} {{ __('account.role.'.$role) }}</div>
                    <div class="text-xs text-slate-500">{{ __('account.role.'.$role.'.hint') }}</div>
                    @if($on && $role === 'printer')<a href="{{ route('printer.dashboard') }}" class="mt-1 inline-block text-sm font-semibold text-action-dark">{{ __('account.open_printer') }} →</a>@endif
                    @if($on && $role === 'designer')<span class="mt-1 inline-block text-xs text-slate-400">{{ __('account.designer_soon') }}</span>@endif
                </div>
                <form method="post" action="{{ $on ? route('account.roles.disable', $role) : route('account.roles.enable', $role) }}">
                    @csrf
                    <button class="rounded-full px-4 py-1.5 text-sm font-semibold {{ $on ? 'bg-action text-white' : 'border border-slate-300 bg-white text-slate-700' }}">{{ $on ? __('account.on') : __('account.off') }}</button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- saved calculations --}}
    <h2 class="mt-8 text-lg font-bold">{{ __('account.calculations') }}</h2>
    @if($calculations->isEmpty())
        <p class="mt-2 text-sm text-slate-500">{{ __('account.no_calculations') }} <a href="{{ route('home') }}" class="text-action-dark">{{ __('account.start') }}</a></p>
    @else
        <div class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
            @foreach($calculations as $c)
                <a href="{{ route('calc.share', $c) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $c->modelFile?->original_name ?? '—' }}</div>
                        <div class="text-xs text-slate-500">{{ $c->params['material'] ?? '' }} · {{ $c->params['quantity'] ?? 1 }} ks · {{ $c->created_at->format('j. n. Y H:i') }}</div>
                    </div>
                    <div class="text-right text-sm font-semibold">
                        @php $tot = collect($c->prices ?? $c->rough['prices'] ?? [])->pluck('total'); @endphp
                        @if($tot->isNotEmpty()){{ number_format($tot->min(), 0, ',', ' ') }}@if($tot->count() > 1) – {{ number_format($tot->max(), 0, ',', ' ') }}@endif Kč@else —@endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('logout') }}" class="mt-8">@csrf<button class="text-sm text-slate-500 hover:text-slate-800">{{ __('auth.logout') }}</button></form>
</div>
@endsection
