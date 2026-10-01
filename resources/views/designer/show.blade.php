@php
    $bio = trim((string) $designer->bio);
    $ogPath = app()->getLocale() === 'cs' ? route('og.designer', $designer->slug) : route('og.designer.localized', ['locale' => app()->getLocale(), 'slug' => $designer->slug]);
    $person = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $designer->display_name,
        'url' => $designer->publicUrl(), 'image' => $designer->avatarUrl(), 'description' => $bio !== '' ? \Illuminate\Support\Str::limit($bio, 300) : null,
        'sameAs' => array_values($designer->linkList()) ?: null,
    ]);
@endphp
@extends('layouts.app', [
    'title' => $designer->display_name.' · '.__('designer.public.title_suffix').' · matplace',
    'description' => $bio !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $bio), 155) : __('designer.public.description', ['name' => $designer->display_name]),
    'ogImage' => $designer->visible ? $ogPath : null,
    'noindex' => $preview,
])

@push('head')
<script type="application/ld+json">{!! json_encode($person, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<div class="mx-auto max-w-5xl">
    @if($preview)<p class="note-warn mb-4 text-sm">{{ __('designer.public.preview') }} <a href="{{ route('designer.profile') }}" class="font-semibold underline">{{ __('designer.profile.edit') }}</a></p>@endif

    <header class="card overflow-hidden">
        @if($designer->coverUrl())
            <img src="{{ $designer->coverUrl() }}" alt="" class="h-40 w-full object-cover sm:h-56">
        @else
            <div class="h-20 bg-action-soft sm:h-28" aria-hidden="true"></div>
        @endif
        <div class="flex flex-wrap items-end gap-4 px-5 pb-5">
            @if($designer->avatarUrl())
                <img src="{{ $designer->avatarUrl() }}" alt="" class="-mt-10 h-24 w-24 rounded-full border-4 border-white object-cover sm:-mt-12 sm:h-28 sm:w-28">
            @else
                <span class="-mt-10 flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-ink text-3xl font-extrabold text-white sm:-mt-12 sm:h-28 sm:w-28" aria-hidden="true">{{ mb_strtoupper(mb_substr($designer->display_name, 0, 1)) }}</span>
            @endif
            <div class="min-w-0 flex-1 pt-3">
                <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $designer->display_name }}</h1>
                <p class="text-sm text-muted">{{ __('designer.public.role') }}</p>
            </div>
            @if($links = $designer->linkList())
                <ul class="flex flex-wrap gap-2 text-sm">
                    @foreach($links as $name => $url)
                        <li><a href="{{ $url }}" rel="nofollow noopener me" target="_blank" class="chip">{{ __('designer.profile.link.'.$name) }} ↗</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
        @if($bio !== '')<p class="whitespace-pre-line border-t border-line px-5 py-4 text-sm leading-relaxed text-slate-700">{{ $bio }}</p>@endif
    </header>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-bold">{{ __('designer.public.models') }}</h2>
        @if($printableCount > 0)
            <nav class="flex gap-2" aria-label="{{ __('designer.public.models') }}">
                <a href="{{ route('designers.show', $designer->slug) }}" class="chip {{ $printable ? '' : 'chip-on' }}">{{ __('designer.cards.filter.all') }}</a>
                <a href="{{ route('designers.show', [$designer->slug, 'printable' => 1]) }}" class="chip {{ $printable ? 'chip-on' : '' }}" rel="nofollow">{{ __('designer.badge.printable') }} ({{ $printableCount }})</a>
            </nav>
        @endif
    </div>
    @if($cards->isEmpty())
        <p class="card mt-3 p-6 text-center text-slate-500">{{ __('designer.public.empty') }}</p>
    @else
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($cards as $card)
                @include('designer._tile', ['card' => $card])
            @endforeach
        </div>
        <div class="mt-4">{{ $cards->links() }}</div>
    @endif
</div>
@endsection
