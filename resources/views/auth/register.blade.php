@extends('layouts.app', ['title' => __('auth.register').' · matplace'])

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6">
    <h1 class="text-2xl font-extrabold">{{ __('auth.register') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('auth.register_hint') }}</p>
    @include('partials.flash')
    @include('partials.oauth')
    <form method="post" action="{{ route('register') }}" class="mt-4 space-y-3">
        @csrf
        <input type="hidden" name="role" value="{{ request('role') }}">
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <label class="block text-sm font-semibold">{{ __('auth.name') }}<input name="name" required value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.email') }}<input name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.password') }}<input name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1"> <span>{{ __('auth.terms') }}</span></label>
        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white">{{ request('role') === 'printer' ? __('auth.register_printer') : __('auth.register') }}</button>
    </form>
    <div class="mt-4 text-sm"><a href="{{ route('login') }}" class="text-action-dark">{{ __('auth.have_account') }}</a></div>
</div>
@endsection
