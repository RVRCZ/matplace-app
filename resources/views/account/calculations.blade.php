@extends('layouts.app', ['title' => __('user.dash.calculations').' · matplace'])

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('user.dash.calculations') }}</h1>
        <a href="{{ route('account') }}" class="text-sm text-action-dark">← {{ __('user.profile.back') }}</a>
    </div>
    @if($calculations->isEmpty())
        <p class="card mt-4 p-6 text-center text-slate-500">{{ __('user.dash.calculations_empty') }} <a href="{{ route('home') }}" class="text-action-dark underline">{{ __('account.start') }}</a></p>
    @else
        <div class="card mt-4 divide-y divide-slate-100">
            @foreach($calculations as $c)
                @include('account._calculation', ['c' => $c])
            @endforeach
        </div>
        <div class="mt-4">{{ $calculations->links() }}</div>
    @endif
</div>
@endsection
