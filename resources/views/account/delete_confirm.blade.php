@extends('layouts.app', ['title' => __('user.delete.confirm_title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6">
    <h1 class="text-2xl font-extrabold">{{ __('user.delete.confirm_title') }}</h1>
    <p class="mt-1 text-sm font-semibold text-muted">{{ $user->email }}</p>
    <p class="mt-3 text-sm text-slate-600">{{ __('user.delete.lead') }}</p>
    @include('partials.flash')
    <form method="post" action="{{ $action }}" class="mt-4 space-y-3">
        @csrf
        @if($balance->isPositive())
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="credit" value="1" required class="mt-1"> <span>{{ __('user.delete.credit', ['amount' => $balance->format()]) }}</span></label>
        @endif
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="understand" value="1" required class="mt-1"> <span>{{ __('user.delete.understand') }}</span></label>
        <button class="w-full rounded-xl border border-red-300 bg-white px-4 py-3 font-semibold text-red-800 hover:bg-red-50">{{ __('user.delete.confirm_button') }}</button>
    </form>
    <p class="mt-4 text-center text-sm"><a href="{{ route('home') }}" class="text-action-dark underline">{{ __('user.delete.keep') }}</a></p>
</div>
@endsection
