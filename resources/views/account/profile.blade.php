@extends('layouts.app', ['title' => __('account.profile').' · matplace'])

@section('content')
<div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6">
    <h1 class="text-2xl font-extrabold">{{ __('account.profile') }}</h1>
    @include('partials.flash')
    <form method="post" action="{{ route('account.profile.update') }}" class="mt-4 space-y-3">
        @csrf
        <label class="block text-sm font-semibold">{{ __('auth.name') }}<input name="name" required value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.email') }}<input value="{{ $user->email }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-normal text-slate-500"></label>
        <label class="block text-sm font-semibold">{{ __('account.phone') }}<input name="phone" value="{{ old('phone', $user->phone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('account.street') }}<input name="street" value="{{ old('street', $user->street) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <div class="grid grid-cols-3 gap-3">
            <label class="block text-sm font-semibold">{{ __('account.zip') }}<input name="zip" value="{{ old('zip', $user->zip) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-semibold">{{ __('account.city') }}<input name="city" value="{{ old('city', $user->city) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-semibold">{{ __('account.country') }}
                <select name="country" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">@foreach(['CZ', 'SK', 'DE', 'AT', 'PL', 'ES', 'GB', 'US'] as $c)<option value="{{ $c }}" @selected(old('country', $user->country ?? 'CZ') === $c)>{{ $c }}</option>@endforeach</select>
            </label>
        </div>
        <p class="text-xs text-slate-500">{{ __('account.address_hint') }}</p>
        <label class="block text-sm font-semibold">{{ __('account.locale') }}
            <select name="locale" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"><option value="cs" @selected($user->locale === 'cs')>Čeština</option><option value="en" @selected($user->locale === 'en')>English</option><option value="es" @selected($user->locale === 'es')>Español</option></select>
        </label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify_email" value="1" @checked($user->notify_email)> {{ __('account.notify_email') }}</label>
        <details class="text-sm"><summary class="cursor-pointer text-action-dark">{{ __('account.change_password') }}</summary>
            <div class="mt-2 grid gap-2">
                <input name="password" type="password" minlength="8" placeholder="{{ __('auth.password') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <input name="password_confirmation" type="password" minlength="8" placeholder="{{ __('auth.password_confirm') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
        </details>
        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white">{{ __('account.save') }}</button>
    </form>
</div>

@if($balance !== null)
<div class="mx-auto mt-4 max-w-lg rounded-2xl border border-slate-200 bg-white p-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold">🖨️ {{ __('farm.title') }}</h2>
        <a href="{{ route('account.credit') }}" class="rounded-full border border-line px-3 py-1 text-sm font-semibold">{{ __('farm.credit_balance') }}: {{ number_format($balance, 0, ',', ' ') }} Kč</a>
    </div>
    @if($orders->isEmpty())
        <p class="mt-2 text-sm text-slate-500">{{ __('farm.no_orders') }} <a href="{{ route('farm.start') }}" class="text-action-dark underline">{{ __('farm.order.new') }}</a></p>
    @else
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
            @foreach($orders as $o)
                <li class="flex items-center justify-between gap-2 py-2"><a href="{{ route('farm.orders.show', $o) }}" class="truncate font-medium hover:text-action-dark">{{ $o->modelFile?->original_name ?? '-' }}</a><span class="shrink-0 text-xs text-slate-500">{{ __('farm.status.'.$o->status) }}</span></li>
            @endforeach
        </ul>
        <a href="{{ route('farm.orders') }}" class="mt-2 inline-block text-sm text-action-dark underline">{{ __('farm.my_orders') }} →</a>
    @endif
</div>
@endif
@endsection
