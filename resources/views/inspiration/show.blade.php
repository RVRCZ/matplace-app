@php
    $text = $model->describe();
    // a text of ours (catalog:enrich) in this language: shown first, the source's own text folded under it
    $ours = $model->isEnriched() && trim((string) (((array) $model->description)[app()->getLocale()] ?? '')) !== '';
    $original = $ours ? $model->original() : null;
    $quote = $quote ?? ['available' => false];
    $sliced = $sliced ?? null;
    // what the page says to search engines: our value, not the source's name alone (App\Domain\Catalog\InspirationSeo, docs/AN.md §16)
    $seo = \App\Domain\Catalog\InspirationSeo::texts($model, $sliced, $ours ? $text : '');
    // a sale is offered only where the licence allows it: the order goes through the calculation's own page (/c/<token>)
    $sellable = $sliced && ! $model->license_restricted && $model->sliceCalculation;
    $dur = fn (int $m) => $m >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min';
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
    'title' => $seo['title'],
    'description' => $seo['description'],
    'ogImage' => $images[0] ?? null,
    // only a page with something of its own is for search engines; the rest stays reachable and keeps its links followed
    'robots' => ($indexable ?? false) ? null : 'noindex, follow',
])

@push('head')
<x-jsonld :data="$thing" />
@if($sellable)<x-jsonld :data="\App\Support\Schema::product($model->title, $thing['description'] ?? null, $images, route('catalog.show', $model->slug), array_column($sliced['rows'], 'total'), $sliced['currency'])" />@endif
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

            {{-- the print time and the price in three materials from the slice of the model's own file (catalog:slice, docs/AN.md §13) --}}
            @if($sliced)
                <section id="model-calc" class="card mt-4 p-4" data-sliced>
                    <h2 class="font-bold">{{ __('models.inspiration.calc_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-700">{{ __('models.inspiration.calc_lead', ['infill' => 15, 'grams' => round((float) $model->slice_grams)]) }}</p>
                    <table class="mt-3 w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-muted"><th class="py-1 pr-2 font-normal">{{ __('models.inspiration.calc_material') }}</th><th class="py-1 pr-2 font-normal">{{ __('models.inspiration.calc_time') }}</th><th class="py-1 text-right font-normal">{{ __('models.inspiration.calc_price') }}</th></tr></thead>
                        <tbody>
                            @foreach($sliced['rows'] as $row)
                                <tr class="border-t border-line" data-material="{{ $row['code'] }}">
                                    <td class="py-1.5 pr-2"><span class="font-semibold text-ink">{{ $row['label'] }}</span> <span class="text-muted">{{ $row['code'] }}</span></td>
                                    <td class="py-1.5 pr-2">{{ $dur((int) $row['minutes']) }}</td>
                                    <td class="py-1.5 text-right font-semibold text-ink">{{ $row['total_text'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-muted">{{ __('models.inspiration.calc_note', ['date' => $model->sliced_at->format(app()->getLocale() === 'cs' ? 'j. n. Y' : 'j M Y')]) }}</p>
                    @if($sellable)
                        <a href="{{ url('/c/'.$model->sliceCalculation->token) }}" class="btn-primary mt-3" rel="nofollow" data-calc-open>{{ __('models.inspiration.calc_open') }}</a>
                    @endif
                </section>
            @endif

            <dl class="mt-4 space-y-2 text-sm">
                @if($model->hasWebLink())
                    <div><dt class="text-muted">{{ __('models.facts.origin') }}</dt><dd><a href="{{ $model->external_url }}" rel="nofollow noopener" target="_blank" class="font-semibold underline">{{ __('models.inspiration.open_at', ['source' => $sourceName]) }} ↗</a></dd></div>
                @endif
                <div><dt class="text-muted">{{ __('models.facts.license') }}</dt><dd class="font-semibold">{{ $licenseName }}</dd></div>
                {{-- what we know of the thing itself: the size, the slicer's estimate, the price of one piece with us --}}
                @if($model->max_mm)
                    <div><dt class="text-muted">{{ __('models.inspiration.size') }}</dt><dd class="font-semibold">{{ __('models.inspiration.size_value', ['mm' => $model->max_mm]) }}</dd></div>
                @endif
                @if($model->est_grams && $model->est_minutes && ! $sliced)
                    <div><dt class="text-muted">{{ __('models.inspiration.estimate') }}</dt><dd class="font-semibold">{{ __('models.inspiration.estimate_value', ['grams' => $model->est_grams, 'time' => $dur((int) $model->est_minutes)]) }}</dd></div>
                @endif
                @if($quote['available'])
                    <div data-price><dt class="text-muted">{{ __('models.inspiration.price') }}</dt><dd class="font-semibold">{{ __('models.inspiration.price_value', ['price' => $quote['total_text'], 'material' => $quote['material']]) }}<span class="block text-xs font-normal text-muted">{{ __('models.inspiration.price_note') }}</span></dd></div>
                @endif
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
        <section class="mt-8 max-w-3xl" @if($ours) data-our-text @endif>
            <h2 class="text-lg font-bold">{{ __('models.about') }}</h2>
            <p class="mt-2 whitespace-pre-line leading-relaxed text-slate-700" lang="{{ $model->describedIn() }}">{{ $text }}</p>
            @if($original)
                <details class="mt-4 rounded-xl border border-line p-3 text-sm" data-original-text>
                    <summary class="cursor-pointer font-semibold text-slate-700">{{ __('models.inspiration.original', ['language' => \Illuminate\Support\Facades\Lang::has('site.languages.'.$original['locale']) ? __('site.languages.'.$original['locale']) : strtoupper($original['locale'])]) }}</summary>
                    <p class="mt-2 whitespace-pre-line leading-relaxed text-slate-600" lang="{{ $original['locale'] }}">{{ $original['text'] }}</p>
                </details>
            @endif
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
