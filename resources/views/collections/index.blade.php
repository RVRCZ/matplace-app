@extends('layouts.app', ['title' => __('site.collections.title').' · matplace', 'description' => __('site.collections.description')])

@push('head')
<x-jsonld :data="\App\Support\Schema::itemList(__('site.collections.title'), $collections->map(fn ($c) => ['name' => $c->text('title'), 'url' => $c->publicUrl(), 'image' => $c->coverUrl()])->all(), __('site.collections.description'))" />
@endpush

@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="text-3xl font-extrabold tracking-tight text-ink">{{ __('site.collections.title') }}</h1>
    <p class="mt-2 max-w-2xl text-muted">{{ __('site.collections.description') }}</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($collections as $collection)
            <a href="{{ $collection->publicUrl() }}" class="card block overflow-hidden transition hover:border-action">
                @if($collection->coverUrl())
                    <img src="{{ $collection->coverUrl() }}" alt="" loading="lazy" class="aspect-[4/3] w-full bg-slate-50 object-cover">
                @else
                    <div class="aspect-[4/3] bg-action-soft" aria-hidden="true"></div>
                @endif
                <div class="p-4">
                    <h2 class="text-lg font-bold text-ink">{{ $collection->text('title') }}</h2>
                    @if($collection->text('description') !== '')<p class="mt-1 text-sm text-slate-700">{{ $collection->text('description') }}</p>@endif
                    <p class="mt-2 text-xs text-muted">{{ trans_choice('site.collections.count', $collection->entries()->count(), ['n' => $collection->entries()->count()]) }}</p>
                </div>
            </a>
        @empty
            <p class="card p-6 text-center text-muted sm:col-span-2 lg:col-span-3">{{ __('site.collections.empty') }}</p>
        @endforelse
    </div>
</div>
@endsection
