@extends('layouts.app', ['title' => __('affiliate.title').' · matplace', 'description' => __('affiliate.lead'), 'noindex' => true])

@section('content')
<article class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-extrabold">{{ __('affiliate.title') }}</h1>
    <p class="mt-2 text-slate-700">{{ __('affiliate.lead') }}</p>
    @include('partials.flash')

    <section class="card mt-5 p-5">
        <h2 class="font-bold">{{ __('affiliate.how.h') }}</h2>
        <ol class="mt-2 list-decimal space-y-2 pl-5 text-slate-700">
            <li>{{ __('affiliate.how.1') }}</li>
            <li>{{ __('affiliate.how.2', ['days' => $days]) }}</li>
            <li>{{ __('affiliate.how.3', ['percent' => $percent]) }}</li>
        </ol>
    </section>

    <section class="card mt-5 p-5" id="terms">
        <h2 class="font-bold">{{ __('affiliate.terms.h') }}</h2>
        <p class="text-xs text-slate-500">{{ __('affiliate.terms.v', ['v' => $termsVersion]) }}</p>
        <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            @foreach(range(1, 8) as $n)
                <li>{{ __('affiliate.terms.'.$n, ['percent' => $percent, 'days' => $days]) }}</li>
            @endforeach
        </ol>
    </section>

    <section class="card mt-5 p-5" id="register">
        <h2 class="font-bold">{{ __('affiliate.form.h') }}</h2>
        @if($partner)
            <p class="mt-2 text-sm">{{ __('affiliate.already') }} <a href="{{ route('account.partner') }}" class="font-semibold text-action-dark underline">{{ __('affiliate.open_account') }}</a></p>
        @elseif(! auth()->check())
            <p class="mt-2 text-sm text-slate-700">{{ __('affiliate.form.signin') }}</p>
            <a href="{{ route('login') }}" class="btn-primary mt-3 inline-block">{{ __('affiliate.form.signin_btn') }}</a>
        @elseif(! auth()->user()->hasVerifiedEmail())
            <p class="mt-2 text-sm text-slate-700">{{ __('affiliate.form.signin') }}</p>
            @include('partials.verify_banner')
        @else
            <form method="post" action="{{ route('affiliate.register') }}" class="mt-3 space-y-3">
                @csrf
                <label class="lbl">{{ __('affiliate.form.name') }}<input name="name" required maxlength="120" value="{{ old('name', auth()->user()->name) }}" class="field"></label>
                <label class="lbl">{{ __('affiliate.form.code') }}<input name="code" required maxlength="20" pattern="[A-Za-z0-9_\-]{1,20}" value="{{ old('code', $suggested) }}" class="field font-mono uppercase"></label>
                <label class="lbl">{{ __('affiliate.form.channels') }}<input name="channels" required maxlength="300" value="{{ old('channels') }}" class="field"></label>
                <label class="lbl">{{ __('affiliate.form.payout') }}<textarea name="payout_details" required rows="2" maxlength="1000" class="field">{{ old('payout_details') }}</textarea></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-1 h-4 w-4 accent-action"><span>{{ __('affiliate.form.accept') }}</span></label>
                <button class="btn-primary">{{ __('affiliate.form.submit') }}</button>
            </form>
        @endif
    </section>
</article>
@endsection
