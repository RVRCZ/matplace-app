@extends('layouts.app', ['title' => __('designer.bulk.match_title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('designer.bulk.match_title') }}</h1>
        <a href="{{ route('designer.dashboard') }}" class="text-sm text-action-dark">← {{ __('designer.back') }}</a>
    </div>
    <p class="mt-2 text-sm text-slate-700">{{ __('designer.bulk.match_lead') }}</p>
    @include('partials.flash')
    @if($remixes > 0)<p class="note-warn mt-3 text-sm">{{ trans_choice('designer.bulk.remixes', $remixes, ['n' => $remixes]) }}</p>@endif

    <form method="post" action="{{ route('designer.bulk.confirm', $token) }}" class="mt-4 space-y-4">
        @csrf
        <ul class="card divide-y divide-slate-100">
            @foreach($entries as $i => $entry)
                @php $m = $matches[$entry]; @endphp
                <li class="grid items-center gap-2 px-4 py-3 sm:grid-cols-[1fr_auto_1fr]" data-match-level="{{ $m['level'] }}">
                    <span class="min-w-0 truncate text-sm font-medium" title="{{ $entry }}">{{ basename($entry) }}</span>
                    <span class="rounded-full px-2 py-0.5 text-center text-xs font-semibold {{ ['sure' => 'bg-ok-soft text-ok', 'maybe' => 'bg-amber-50 text-amber-900', 'none' => 'bg-slate-100 text-slate-600'][$m['level']] }}">{{ __('designer.bulk.level.'.$m['level']) }}</span>
                    <select name="pairs[{{ $i }}]" class="field mt-0 text-sm" aria-label="{{ __('designer.bulk.card_for', ['file' => basename($entry)]) }}">
                        <option value="">{{ __('designer.bulk.no_card') }}</option>
                        @foreach($cards as $card)
                            <option value="{{ $card->id }}" @selected($m['card'] === $card->id)>{{ $card->title }}</option>
                        @endforeach
                    </select>
                </li>
            @endforeach
        </ul>
        <div class="card space-y-3 p-4">
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="author" value="1" required class="mt-1"> <span>{{ __('designer.file.author_all') }}</span></label>
            <button class="btn-primary">{{ __('designer.bulk.confirm') }}</button>
        </div>
    </form>
</div>
@endsection
