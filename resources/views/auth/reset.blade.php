@extends('layouts.app', ['title' => __('auth.new_password').' · matplace'])

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6">
    <h1 class="text-2xl font-extrabold">{{ __('auth.new_password') }}</h1>
    @include('partials.flash')
    <form method="post" action="{{ route('password.update') }}" class="mt-4 space-y-3">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="block text-sm font-semibold">{{ __('auth.email') }}<input name="email" type="email" required value="{{ old('email', $email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.password') }}<input name="password" type="password" required minlength="8" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="block text-sm font-semibold">{{ __('auth.password_confirm') }}<input name="password_confirmation" type="password" required minlength="8" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white">{{ __('auth.save_password') }}</button>
    </form>
</div>
@endsection
