@extends('layouts.app', [
    'title' => $collection->text('title').' · '.__('site.collections.title').' · matplace',
    'description' => $collection->text('description') ?: __('site.collections.description_one', ['title' => $collection->text('title')]),
    'ogImage' => $collection->coverUrl(),
    'noindex' => $preview,
])

@push('head')
<x-jsonld :data="\App\Support\Schema::itemList($collection->text('title'), $entries->map(fn ($e) => ['name' => $e['title'], 'url' => $e['url'], 'image' => $e['image']])->all(), $collection->text('description'))" />
<x-jsonld :data="\App\Support\Schema::breadcrumbs([['matplace', route('home')], [__('site.collections.title'), route('collections.index')], [$collection->text('title'), $collection->publicUrl()]])" />
@endpush

@section('content')
<div class="mx-auto max-w-5xl">
    <nav class="text-sm text-muted"><a href="{{ route('collections.index') }}" class="underline hover:text-ink">{{ __('site.collections.title') }}</a></nav>
    @if($preview)<p class="note-warn mt-3 text-sm">{{ __('site.collections.preview') }}</p>@endif
    <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-ink">{{ $collection->text('title') }}</h1>
    @if($collection->text('description') !== '')<p class="mt-2 max-w-2xl text-lg text-slate-700">{{ $collection->text('description') }}</p>@endif

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @forelse($entries as $entry)
            <a href="{{ $entry['url'] }}" class="card block overflow-hidden text-sm transition hover:border-action">
                <div class="aspect-[4/3] overflow-hidden bg-slate-50">
                    @if($entry['image'])<img src="{{ $entry['image'] }}" alt="" loading="lazy" class="h-full w-full object-cover">@else<div class="flex h-full items-center justify-center text-4xl text-slate-300" aria-hidden="true">◇</div>@endif
                </div>
                <div class="p-3">
                    <span class="block font-semibold leading-snug text-ink">{{ $entry['title'] }}</span>
                    @if($entry['author'])<span class="block text-xs text-muted">{{ $entry['author'] }}</span>@endif
                    <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $entry['printable'] ? 'bg-ok-soft text-ok' : 'bg-slate-100 text-slate-600' }}">{{ __($entry['printable'] ? 'site.collections.printable' : 'site.collections.inspiration') }}</span>
                </div>
            </a>
        @empty
            <p class="card col-span-full p-6 text-center text-muted">{{ __('site.collections.empty_one') }}</p>
        @endforelse
    </div>

    <p class="mt-8 text-sm text-muted"><a href="{{ route('models.index') }}" class="font-semibold text-action-dark underline">{{ __('models.title') }}</a> · <a href="{{ route('catalog.index') }}" class="underline">{{ __('models.inspiration.title') }}</a></p>
</div>
@endsection
