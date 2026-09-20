@extends('layouts.app', ['title' => __('auth.login').' · matplace'])

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6">
    <h1 class="text-2xl font-extrabold">{{ __('auth.login') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('auth.login_hint') }}</p>
    @include('partials.flash')
    @include('partials.oauth')
    <form method="post" action="{{ route('login') }}" class="mt-4 space-y-3">
        @csrf
        <label class="block text-sm font-semibold">{{ __('auth.email') }}<input name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.password') }}<input name="password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" checked> {{ __('auth.remember') }}</label>
        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white">{{ __('auth.login') }}</button>
    </form>
    <div class="mt-4 flex justify-between text-sm">
        <a href="{{ route('register') }}" class="text-action-dark">{{ __('auth.no_account') }}</a>
        <a href="{{ route('password.request') }}" class="text-slate-500">{{ __('auth.forgot') }}</a>
    </div>
</div>
@endsection
