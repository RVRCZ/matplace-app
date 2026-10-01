@extends('layouts.app', [
    'title' => $post->titleIn().' · matplace',
    'description' => \Illuminate\Support\Str::limit($post->excerptIn(), 155),
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
        <img src="{{ $post->coverUrl() }}" alt="" class="mt-4 max-h-[26rem] w-full rounded-2xl object-cover">
    @endif

    {{-- Markdown rendered without raw HTML, or the old site's HTML cleaned on import (App\Support\HtmlCleaner) --}}
    <div class="article mt-6">{!! $post->html() !!}</div>

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
