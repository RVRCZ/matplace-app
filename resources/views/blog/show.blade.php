@extends('layouts.app', [
    // the heading is the reader's; a search result gets a title and a description that fit (App\Support\BlogSeo, docs/AP.md §15)
    'title' => \App\Support\BlogSeo::title($post),
    'description' => \App\Support\BlogSeo::description($post),
    'ogImage' => route('og', ['type' => 'article', 'id' => $post->slug]),
    'ogType' => 'article',
    'noindex' => $draft,
])

@push('head')
<x-jsonld :data="\App\Support\Schema::article($post)" />
<x-jsonld :data="\App\Support\Schema::breadcrumbs([['matplace', route('home')], [__('site.blog.title'), route('blog.index')], [$post->titleIn(), $post->publicUrl()]])" />
@endpush

@section('content')
<article class="mx-auto max-w-3xl">
    <nav class="text-sm text-muted"><a href="{{ route('blog.index') }}" class="underline hover:text-ink">{{ __('site.blog.title') }}</a></nav>
    @if($draft)<p class="note-warn mt-3 text-sm">{{ __('site.blog.draft') }}</p>@endif
    <h1 class="mt-2 text-3xl font-extrabold leading-tight tracking-tight text-ink">{{ $post->titleIn() }}</h1>
    <p class="mt-2 text-sm text-muted">
        @if($post->published_at)<time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j. n. Y') }}</time> · @endif
        {{ $post->author?->name ?: ($post->author_name ?: 'matplace') }} · {{ __('site.blog.minutes', ['n' => $post->readingMinutes()]) }}
    </p>
    @if($post->coverUrl())
        {{-- the article's photo is the largest thing on the first screen: fetched first, with its size, so nothing below it jumps --}}
        @php $coverSize = \App\Support\BlogLinks::size($post->coverUrl()); @endphp
        <img src="{{ $post->coverUrl() }}" alt="{{ $post->titleIn() }}" @if($coverSize) width="{{ $coverSize[0] }}" height="{{ $coverSize[1] }}" @endif fetchpriority="high" decoding="async" class="mt-4 max-h-[26rem] w-full rounded-2xl object-cover">
    @endif

    {{-- Markdown rendered without raw HTML, or the old site's HTML cleaned on import (App\Support\HtmlCleaner) --}}
    {{-- a link to a tool that is switched off stays as plain words (App\Support\BlogLinks, docs/AP.md §15) --}}
    <div class="article mt-6">{!! \App\Support\BlogLinks::pictures(\App\Support\BlogLinks::live($post->html())) !!}</div>

    {{-- the tools the article is about (the ones whose page names it under "where next"): the way from reading to making --}}
    @if($tools = \App\Support\BlogSeo::tools($post))
        <section id="article-tools" class="mt-10" aria-labelledby="article-tools-title">
            <h2 id="article-tools-title" class="text-lg font-bold text-ink">{{ __('site.blog.tools_title') }}</h2>
            <ul class="mt-1 grid gap-x-6 sm:grid-cols-2">
                @foreach($tools as $t)
                    <li><a href="{{ $t['url'] }}" class="inline-flex min-h-11 items-center gap-1 font-medium text-action-dark underline-offset-4 hover:underline">{{ __('tools.'.$t['key'].'.title') }}<x-icon name="arrow-right" class="h-4 w-4" /></a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <aside class="card mt-10 p-5">
        <h2 class="text-lg font-bold text-ink">{{ __('site.blog.cta_title') }}</h2>
        <p class="mt-1 text-sm text-slate-700">{{ __('site.blog.cta_text') }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="btn-primary text-sm">{{ __('site.blog.cta_price') }}</a>
            <a href="{{ route('tools') }}" class="btn-quiet text-sm">{{ __('footer.tools') }}</a>
        </div>
    </aside>

    @if($others->isNotEmpty())
        <h2 class="mt-10 text-lg font-bold text-ink">{{ __('site.blog.more') }}</h2>
        <ul class="mt-2 space-y-1">
            @foreach($others as $other)
                <li><a href="{{ $other->publicUrl() }}" class="text-action-dark underline">{{ $other->titleIn() }}</a></li>
            @endforeach
        </ul>
    @endif
</article>
@endsection
