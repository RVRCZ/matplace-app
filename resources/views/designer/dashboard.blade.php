@extends('layouts.app', ['title' => __('designer.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-extrabold">{{ $profile->display_name }}</h1>
            <p class="text-sm text-muted">{{ __('designer.title') }} ·
                @if($profile->visible)<a href="{{ $profile->publicUrl() }}" class="text-action-dark underline">{{ __('designer.public_page') }}</a>
                @else{{ __('designer.state.private') }} · <a href="{{ $profile->publicUrl() }}" class="text-action-dark underline">{{ __('designer.preview') }}</a>@endif
            </p>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <a href="{{ route('designer.profile') }}" class="text-action-dark">{{ __('designer.profile.edit') }}</a>
            <a href="{{ route('account') }}" class="text-action-dark">← {{ __('user.profile.back') }}</a>
        </div>
    </div>
    @include('partials.flash')
    @unless($profile->visible)<p class="note-warn mt-3 text-sm">{{ __('designer.hidden_note') }}</p>@endunless

    {{-- what the portfolio brings --}}
    <dl class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4"><dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('designer.stats.visits_7') }}</dt><dd class="mt-1 text-2xl font-extrabold">{{ $week['visits'] }}</dd><dd class="text-xs text-muted">{{ __('designer.stats.via_ref', ['n' => $week['via_ref']]) }}</dd></div>
        <div class="card p-4"><dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('designer.stats.visits_30') }}</dt><dd class="mt-1 text-2xl font-extrabold">{{ $month['visits'] }}</dd><dd class="text-xs text-muted">{{ __('designer.stats.via_ref', ['n' => $month['via_ref']]) }} · {{ __('designer.stats.arrivals', ['n' => $month['ref_visits']]) }}</dd></div>
        <div class="card p-4"><dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('designer.stats.prints') }}</dt><dd class="mt-1 text-2xl font-extrabold">{{ $prints['orders'] }}</dd><dd class="text-xs text-muted">{{ __('designer.stats.pieces', ['n' => $prints['pieces']]) }}</dd></div>
        <div class="card p-4"><dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('designer.stats.rewards') }}</dt><dd class="mt-1 text-2xl font-extrabold">{{ number_format($rewards, 0, ',', ' ') }} Kč</dd><dd class="text-xs text-muted"><a href="{{ route('account.credit') }}" class="underline">{{ __('designer.stats.credit', ['amount' => number_format($balance, 0, ',', ' ').' Kč']) }}</a></dd></div>
    </dl>

    {{-- adding cards --}}
    <div class="mt-5 flex flex-wrap gap-2">
        <a href="{{ route('designer.import', 'printables') }}" class="btn-primary min-h-0 px-4 py-2 text-sm">{{ __('designer.import.from', ['source' => 'Printables']) }}</a>
        <a href="{{ route('designer.import', 'makerworld') }}" class="btn-primary min-h-0 px-4 py-2 text-sm">{{ __('designer.import.from', ['source' => 'MakerWorld']) }}</a>
        <a href="{{ route('designer.models.create') }}" class="btn-secondary min-h-0 px-4 py-2 text-sm">{{ __('designer.card.add') }}</a>
        <a href="{{ route('designer.bulk') }}" class="btn-secondary min-h-0 px-4 py-2 text-sm">{{ __('designer.bulk.title') }}</a>
    </div>

    {{-- the link to hand out --}}
    <section class="card mt-5 p-4">
        <h2 class="font-bold">{{ __('designer.share.title') }}</h2>
        <p class="hint">{{ __('designer.share.lead') }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <input readonly value="{{ $share['profile'] }}" class="field mt-0 min-w-0 flex-1 text-sm" aria-label="{{ __('designer.share.title') }}" onfocus="this.select()">
            <button type="button" class="btn-quiet min-h-0 px-3 py-2 text-sm" data-copy="{{ $share['profile'] }}" data-copied="{{ __('designer.share.copied') }}">{{ __('designer.share.copy') }}</button>
        </div>
        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-action-dark">{{ __('designer.share.text_title') }}</summary>
            <div class="mt-2 space-y-2">
                @foreach($share['texts'] as $l => $text)
                    <div class="flex items-start gap-2">
                        <span class="mt-2 w-7 shrink-0 text-xs font-bold uppercase text-muted">{{ $l }}</span>
                        <textarea readonly rows="2" class="field mt-0 flex-1 text-sm" onfocus="this.select()">{{ $text }}</textarea>
                        <button type="button" class="btn-quiet min-h-0 px-3 py-2 text-sm" data-copy="{{ $text }}" data-copied="{{ __('designer.share.copied') }}">{{ __('designer.share.copy') }}</button>
                    </div>
                @endforeach
            </div>
        </details>
    </section>

    {{-- rewards --}}
    @if($lastRewards->isNotEmpty())
        <section class="mt-5">
            <h2 class="font-bold">{{ __('designer.rewards.title') }}</h2>
            <ul class="card mt-2 divide-y divide-slate-100 text-sm">
                @foreach($lastRewards as $t)
                    <li class="flex items-center justify-between gap-3 px-4 py-2">
                        <span class="min-w-0 truncate">{{ $t->created_at->format('j. n. Y') }} · {{ $t->order?->number ?? '' }} {{ $t->note }}</span>
                        <span class="shrink-0 font-semibold {{ $t->amount < 0 ? 'text-red-700' : 'text-ok' }}">{{ $t->amount > 0 ? '+' : '' }}{{ number_format($t->amount, 2, ',', ' ') }} {{ $t->currency === 'EUR' ? '€' : 'Kč' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- the cards --}}
    <section class="mt-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">{{ __('designer.cards.title') }}</h2>
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('designer.cards.title') }}">
                <a href="{{ route('designer.dashboard') }}" class="chip {{ $filter === null ? 'chip-on' : '' }}">{{ __('designer.cards.filter.all') }} ({{ $counts['all'] }})</a>
                @foreach(\App\Http\Controllers\Designer\ProfileController::FILTERS as $f)
                    <a href="{{ route('designer.dashboard', ['show' => $f]) }}" class="chip {{ $filter === $f ? 'chip-on' : '' }}">{{ __('designer.cards.filter.'.$f) }} ({{ $counts[$f] }})</a>
                @endforeach
            </nav>
        </div>
        @if($cards->isEmpty())
            <p class="card mt-3 p-6 text-center text-slate-500">{{ $counts['all'] ? __('designer.cards.none_here') : __('designer.cards.empty') }}</p>
        @else
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($cards as $card)
                    @include('designer._tile', ['card' => $card, 'manage' => true])
                @endforeach
            </div>
            <div class="mt-4">{{ $cards->links() }}</div>
        @endif
    </section>
</div>
@endsection
