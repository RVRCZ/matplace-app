@extends('layouts.app', ['title' => __('models.title').' · matplace', 'description' => __('models.description')])

@section('content')
@php
    // every filter link keeps the others
    $link = fn (array $change) => route('models.index', array_filter(array_merge(['category' => $category?->slug, 'size' => $size, 'sort' => $sort === 'new' ? null : $sort, 'download' => $download ? 1 : null], $change)));
@endphp
<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-extrabold sm:text-3xl">{{ __('models.title') }}</h1>
    <p class="mt-1 max-w-2xl text-slate-600">{{ __('models.lead') }}</p>

    <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('models.sort.label') }}">
            @foreach(\App\Http\Controllers\ModelCatalogController::SORTS as $s)
                <a href="{{ $link(['sort' => $s === 'new' ? null : $s]) }}" class="chip {{ $sort === $s ? 'chip-on' : '' }}" rel="nofollow">{{ __('models.sort.'.$s) }}</a>
            @endforeach
        </nav>
        <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('models.size.label') }}">
            <span class="text-muted">{{ __('models.size.label') }}</span>
            @foreach(\App\Http\Controllers\ModelCatalogController::SIZES as $s)
                <a href="{{ $link(['size' => $size === $s ? null : $s]) }}" class="chip {{ $size === $s ? 'chip-on' : '' }}" title="{{ __('models.size.'.$s.'_hint', ['s' => config('catalog.sizes.s'), 'm' => config('catalog.sizes.m')]) }}" rel="nofollow">{{ strtoupper($s) }}</a>
            @endforeach
        </nav>
        <a href="{{ $link(['download' => $download ? null : 1]) }}" class="chip {{ $download ? 'chip-on' : '' }}" rel="nofollow">{{ __('models.downloadable') }}</a>
    </div>
    @if($categories->isNotEmpty())
        <nav class="mt-3 flex flex-wrap gap-2 text-sm" aria-label="{{ __('models.category') }}">
            <a href="{{ $link(['category' => null]) }}" class="chip {{ $category ? '' : 'chip-on' }}">{{ __('models.all_categories') }}</a>
            @foreach($categories as $c)
                <a href="{{ $link(['category' => $c->slug]) }}" class="chip {{ $category?->id === $c->id ? 'chip-on' : '' }}">{{ $c->label() }}</a>
            @endforeach
        </nav>
    @endif

    @if($cards->isEmpty())
        <div class="card mt-6 p-8 text-center">
            <p class="text-slate-600">{{ __('models.empty') }}</p>
            <p class="mt-3 flex flex-wrap items-center justify-center gap-3 text-sm">
                <a href="{{ route('farm.start') }}" class="btn-primary">{{ __('models.own_file') }}</a>
                <a href="{{ route('catalog.index') }}" class="text-action-dark underline">{{ __('models.inspiration_link') }}</a>
            </p>
        </div>
    @else
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($cards as $card)
                @include('models._tile', ['card' => $card])
            @endforeach
        </div>
        <div class="mt-5">{{ $cards->links() }}</div>
    @endif

    <p class="mt-8 text-sm text-slate-600">{{ __('models.designers_pitch') }} <a href="{{ route('account') }}#designer" class="text-action-dark underline">{{ __('designer.enable') }}</a></p>
</div>
@endsection
