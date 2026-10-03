@php
    $text = $model->describe();
    $images = $model->imageUrls();
    $sourceName = __('models.source.'.(\Illuminate\Support\Facades\Lang::has('models.source.'.$model->source) ? $model->source : 'other'));
    $licenseName = $model->license && \Illuminate\Support\Facades\Lang::has('models.license.'.$model->license) ? __('models.license.'.$model->license) : __('models.license.unknown');
    $thing = array_filter([
        '@context' => 'https://schema.org', '@type' => 'CreativeWork', 'name' => $model->title,
        'description' => $text !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text), 300) : null,
        'image' => $images ?: null, 'url' => route('catalog.show', $model->slug),
        'author' => $model->author_name ? ['@type' => 'Person', 'name' => $model->author_name] : null,
        'isBasedOn' => $model->hasWebLink() ? $model->external_url : null,
        'keywords' => $model->tags ? implode(', ', $model->tags) : null,
    ]);
@endphp
@extends('layouts.app', [
    'title' => $model->title.' · '.__('models.inspiration.title_suffix').' · matplace',
    'description' => $text !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text), 155) : __('models.inspiration.description_one', ['title' => $model->title]),
    'ogImage' => $images[0] ?? null,
])

@push('head')
<x-jsonld :data="$thing" />
<x-jsonld :data="\App\Support\Schema::breadcrumbs(array_values(array_filter([['matplace', route('home')], [__('models.inspiration.title'), route('catalog.index')], $category ? [$category->label(), route('catalog.category', $category->slug)] : null, [$model->title, route('catalog.show', $model->slug)]])))" />
@endpush

@section('content')
<div class="mx-auto max-w-5xl">
    <nav class="text-sm text-muted" aria-label="{{ __('models.inspiration.title') }}">
        <a href="{{ route('catalog.index') }}" class="underline hover:text-ink">{{ __('models.inspiration.title') }}</a>
        @if($category) · <a href="{{ route('catalog.category', $category->slug) }}" class="underline hover:text-ink">{{ $category->label() }}</a>@endif
    </nav>

    <div class="mt-3 grid gap-6 lg:grid-cols-[1.25fr_1fr]">
        <div data-gallery>
            <div class="card overflow-hidden">
                @if($images)
                    <img data-gallery-main src="{{ $images[0] }}" alt="{{ $model->title }}" class="aspect-[4/3] w-full bg-slate-50 object-contain">
                @else
                    <div class="flex aspect-[4/3] items-center justify-center bg-slate-50 text-6xl text-slate-300">◇</div>
                @endif
            </div>
            @if(count($images) > 1)
                <ul class="mt-2 grid grid-cols-5 gap-2 sm:grid-cols-6">
                    @foreach($images as $url)
                        <li><button type="button" data-gallery-thumb="{{ $url }}" class="block overflow-hidden rounded-lg border border-line hover:border-action" aria-label="{{ __('models.picture', ['n' => $loop->iteration]) }}"><img src="{{ $url }}" alt="" loading="lazy" class="aspect-square w-full object-cover"></button></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h1 class="text-2xl font-extrabold leading-tight sm:text-3xl">{{ $model->title }}</h1>
            <p class="mt-1 text-sm text-muted">@if($model->author_name){{ __('models.inspiration.by', ['name' => $model->author_name]) }} · @endif{{ $sourceName }}</p>

            {{-- what can be done with the model here --}}
            @if($card)
                <section id="cta" class="card mt-4 border-action p-4" data-cta="author">
                    <h2 class="font-bold">{{ __('models.inspiration.author_here') }}</h2>
                    <p class="mt-1 text-sm text-slate-700">{{ __('models.inspiration.author_prints', ['name' => $card->profile->display_name]) }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <a href="{{ $card->publicUrl() }}" class="btn-primary">{{ __('models.order') }}</a>
                        <a href="{{ $card->profile->publicUrl() }}" class="text-action-dark underline">{{ __('models.inspiration.portfolio') }}</a>
                    </div>
                </section>
            @else
                {{-- the customer rents the printer and prints for themselves: every licence allows that, a non-commercial one says so --}}
                <section id="cta" class="card mt-4 border-action p-4" data-cta="print" data-use="{{ $model->license_restricted ? 'personal' : 'commercial' }}">
                    <h2 class="font-bold">{{ __($model->license_restricted ? 'models.inspiration.personal' : 'models.inspiration.have_file') }}</h2>
                    <p class="mt-1 text-sm text-slate-700">{{ __($model->license_restricted ? 'models.inspiration.personal_text' : 'models.inspiration.have_file_text', ['license' => $licenseName]) }}@if($model->needsAttribution()) {{ __('models.inspiration.attribution') }}@endif</p>
                    <a href="{{ route('farm.start', ['source' => $model->id]) }}" class="btn-primary mt-3" rel="nofollow">{{ __('models.inspiration.upload_print') }}</a>
                </section>
            @endif
            @if($author && ! $card)
                <p class="mt-3 text-sm">{{ __('models.inspiration.author_here') }} <a href="{{ $author->publicUrl() }}" class="text-action-dark underline">{{ $author->display_name }}</a></p>
            @endif

            <dl class="mt-4 space-y-2 text-sm">
                @if($model->hasWebLink())
                    <div><dt class="text-muted">{{ __('models.facts.origin') }}</dt><dd><a href="{{ $model->external_url }}" rel="nofollow noopener" target="_blank" class="font-semibold underline">{{ __('models.inspiration.open_at', ['source' => $sourceName]) }} ↗</a></dd></div>
                @endif
                <div><dt class="text-muted">{{ __('models.facts.license') }}</dt><dd class="font-semibold">{{ $licenseName }}</dd></div>
                @if($model->tags)
                    <div><dt class="text-muted">{{ __('models.inspiration.tags') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            @foreach($model->tags as $tag)<a href="{{ route('catalog.index', ['q' => $tag]) }}" rel="nofollow" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 hover:bg-slate-200">{{ $tag }}</a>@endforeach
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    @if($text !== '')
        <section class="mt-8 max-w-3xl">
            <h2 class="text-lg font-bold">{{ __('models.about') }}</h2>
            <p class="mt-2 whitespace-pre-line leading-relaxed text-slate-700" lang="{{ $model->describedIn() }}">{{ $text }}</p>
        </section>
    @endif

    @if($similar->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold">{{ __('models.inspiration.similar') }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach($similar as $other)
                    @include('inspiration._tile', ['model' => $other])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
