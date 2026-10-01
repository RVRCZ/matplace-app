@extends('layouts.app', ['title' => __('user.models.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('user.models.title') }}</h1>
        <a href="{{ route('account') }}" class="text-sm text-action-dark">← {{ __('user.profile.back') }}</a>
    </div>
    @include('partials.flash')

    <nav class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('user.models.title') }}">
        <a href="{{ route('account.models') }}" class="chip {{ $origin === null ? 'chip-on' : '' }}" @if($origin === null) aria-current="page" @endif>{{ __('user.models.origin.all') }}</a>
        @foreach($origins as $o)
            <a href="{{ route('account.models', ['origin' => $o]) }}" class="chip {{ $origin === $o ? 'chip-on' : '' }}" @if($origin === $o) aria-current="page" @endif>{{ __('user.models.origin.'.$o) }}</a>
        @endforeach
    </nav>

    @if($models->isEmpty())
        <p class="card mt-4 p-6 text-center text-slate-500">{{ __('user.models.empty') }} <a href="{{ route('tools') }}" class="text-action-dark underline">{{ __('user.dash.models_start') }}</a></p>
    @else
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($models as $m)
                @include('account._model', ['m' => $m, 'lock' => $locks[$m->id] ?? null, 'farm' => (bool) config('farm.enabled')])
            @endforeach
        </div>
        <div class="mt-4">{{ $models->links() }}</div>
    @endif
</div>
@include('partials.printer_pick')
@endsection
