@extends('layouts.app', ['title' => __('affiliate.title').' · matplace', 'description' => __('affiliate.land.sub', ['percent' => $percent]), 'noindex' => true])

{{-- The partner programme's page (docs/AT.md): a landing page for people with an audience. noindex, not in the sitemap, linked from no public
     navigation: the owner sends the address himself. Every number on it comes from the farm's settings. --}}
@section('content')
<div class="mx-auto max-w-5xl">
    <header class="rounded-3xl bg-studio px-5 py-10 sm:px-10 sm:py-14">
        <p class="text-sm font-semibold uppercase tracking-wide text-action-dark">{{ __('affiliate.title') }}</p>
        <h1 class="mt-2 max-w-3xl text-3xl font-extrabold leading-tight text-ink sm:text-4xl">{{ __('affiliate.land.title') }}</h1>
        <p class="mt-4 max-w-2xl text-lg leading-relaxed text-muted">{{ __('affiliate.land.sub', ['percent' => $percent]) }}</p>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <a href="#register" class="btn-primary">{{ __('affiliate.land.cta') }}</a>
            <span class="text-sm text-muted">{{ __('affiliate.land.cta_note') }}</span>
        </div>
    </header>
    @include('partials.flash')

    <section class="mt-10" aria-labelledby="land-who">
        <h2 id="land-who" class="text-xl font-bold text-ink">{{ __('affiliate.land.who.h') }}</h2>
        <p class="mt-1 text-muted">{{ __('affiliate.land.who.lead') }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach(range(1, 5) as $n)
                <div class="card p-4">
                    <h3 class="font-semibold text-ink">{{ __('affiliate.land.who.'.$n.'.t') }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-muted">{{ __('affiliate.land.who.'.$n.'.p') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-10" aria-labelledby="land-earn">
        <h2 id="land-earn" class="text-xl font-bold text-ink">{{ __('affiliate.land.earn.h') }}</h2>
        <div class="card mt-3 p-5">
            <p class="text-lg leading-relaxed text-ink" data-example>{{ __('affiliate.land.earn.example', $example) }}</p>
            <p class="mt-2 text-sm text-muted">{{ __('affiliate.land.earn.note') }}</p>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="land-steps">
        <h2 id="land-steps" class="text-xl font-bold text-ink">{{ __('affiliate.land.steps.h') }}</h2>
        <ol class="mt-3 grid gap-3 sm:grid-cols-3">
            @foreach(range(1, 3) as $n)
                <li class="card p-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-action font-bold text-white" aria-hidden="true">{{ $n }}</span>
                    <h3 class="mt-2 font-semibold text-ink">{{ __('affiliate.land.steps.'.$n.'.t') }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-muted">{{ __('affiliate.land.steps.'.$n.'.p', $terms) }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="mt-10" aria-labelledby="land-share">
        <h2 id="land-share" class="text-xl font-bold text-ink">{{ __('affiliate.land.share.h') }}</h2>
        <p class="mt-1 text-muted">{{ __('affiliate.land.share.lead') }}</p>
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-3">
            @foreach($tools as $key)
                @include('tools.card', ['key' => $key, 'tool' => config('tools.'.$key)])
            @endforeach
        </div>
    </section>

    <section class="mt-10" aria-labelledby="land-faq">
        <h2 id="land-faq" class="text-xl font-bold text-ink">{{ __('affiliate.land.faq.h') }}</h2>
        <div class="mt-3 divide-y divide-line rounded-2xl border border-line bg-card">
            @foreach(range(1, 7) as $n)
                <details class="group px-4 py-1">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center font-semibold text-ink marker:hidden">
                        <span class="mr-2 inline-block text-action transition group-open:rotate-90" aria-hidden="true">›</span>{{ __('affiliate.land.faq.'.$n.'.q') }}
                    </summary>
                    <p class="pb-3 leading-relaxed">{{ __('affiliate.land.faq.'.$n.'.a', $terms) }}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section class="mt-10 grid gap-6 lg:grid-cols-2" id="register">
        <div class="card p-5">
            <h2 class="font-bold text-ink">{{ __('affiliate.form.h') }}</h2>
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
        </div>

        <div class="card p-5" id="terms">
            <h2 class="font-bold text-ink">{{ __('affiliate.terms.h') }}</h2>
            <p class="text-xs text-slate-500">{{ __('affiliate.terms.v', ['v' => $termsVersion]) }}</p>
            <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                @foreach(range(1, 8) as $n)
                    <li>{{ __('affiliate.terms.'.$n, $terms) }}</li>
                @endforeach
            </ol>
        </div>
    </section>
</div>
@endsection
