@extends('layouts.app', ['title' => __('auth.register').' · matplace'])

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6">
    <h1 class="text-2xl font-extrabold">{{ __('auth.register') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('auth.register_hint') }}</p>
    @include('partials.flash')
    {{-- too many registrations from this address (named limiter `register`): said here, the form stays --}}
    @if(! empty($limited))<p class="note-warn mt-3 text-sm" role="alert">{{ __('auth.guard.limited') }}</p>@endif
    @include('partials.oauth')
    <form method="post" action="{{ route('register') }}" class="mt-4 space-y-3">
        @csrf
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        {{-- when the form was shown, signed (App\Support\RegisterGuard): sent too soon or hours later, the form comes back --}}
        <input type="hidden" name="t" value="{{ $stamp ?? \App\Support\RegisterGuard::stamp() }}">
        <label class="block text-sm font-semibold">{{ __('auth.name') }}<input name="name" required value="{{ old('name') }}" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.email') }}<input name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.password') }}<input name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1"> <span>{{ __('auth.terms') }}</span></label>
        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white">{{ __('auth.register') }}</button>
    </form>
    <div class="mt-4 text-sm"><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-action-dark">{{ __('auth.have_account') }}</a></div>
</div>
@endsection
