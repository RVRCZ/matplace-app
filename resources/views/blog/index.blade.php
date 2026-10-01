@extends('layouts.app', ['title' => __('site.blog.title').' · matplace', 'description' => __('site.blog.description')])

@push('head')
<x-jsonld :data="\App\Support\Schema::itemList(__('site.blog.title'), $posts->map(fn ($p) => ['name' => $p->titleIn(), 'url' => $p->publicUrl(), 'image' => $p->coverUrl()])->all(), __('site.blog.description'))" />
@endpush

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-3xl font-extrabold tracking-tight text-ink">{{ __('site.blog.title') }}</h1>
    <p class="mt-2 text-muted">{{ __('site.blog.description') }}</p>

    <div class="mt-6 space-y-4">
        @forelse($posts as $post)
            <article class="card overflow-hidden sm:grid sm:grid-cols-[220px_1fr]">
                @if($post->coverUrl())
                    <a href="{{ $post->publicUrl() }}" tabindex="-1" aria-hidden="true"><img src="{{ $post->coverUrl() }}" alt="" loading="lazy" class="h-44 w-full object-cover sm:h-full"></a>
                @else
                    <div class="hidden bg-action-soft sm:block" aria-hidden="true"></div>
                @endif
                <div class="p-4">
                    <h2 class="text-lg font-bold leading-snug text-ink"><a href="{{ $post->publicUrl() }}" class="hover:underline">{{ $post->titleIn() }}</a></h2>
                    <p class="mt-1 text-xs text-muted"><time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j. n. Y') }}</time> · {{ __('site.blog.minutes', ['n' => $post->readingMinutes()]) }}</p>
                    <p class="mt-2 text-sm text-slate-700">{{ $post->excerptIn() }}</p>
                    <a href="{{ $post->publicUrl() }}" class="mt-3 inline-block text-sm font-semibold text-action-dark underline">{{ __('site.blog.read') }}</a>
                </div>
            </article>
        @empty
            <p class="card p-6 text-center text-muted">{{ __('site.blog.empty') }}</p>
        @endforelse
    </div>
</div>
@endsection
