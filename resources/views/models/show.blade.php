@php
    $text = $card->describe();
    $textLocale = trim((string) ($card->description[app()->getLocale()] ?? '')) !== '' ? app()->getLocale() : ($card->source_locale ?: 'en');
    $summary = (array) $card->slice_summary;
    $images = $card->images;
    $cover = $card->cover();
    $downloadable = $card->download_allowed && $card->modelFile;
    $product = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $card->title,
        'description' => $text !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text), 300) : null,
        'image' => $images->map(fn ($i) => $i->url())->all() ?: null,
        'url' => $card->publicUrl(),
        'brand' => ['@type' => 'Brand', 'name' => 'matplace'],
        'offers' => $quote['available'] ? ['@type' => 'Offer', 'price' => number_format($quote['total'], 2, '.', ''), 'priceCurrency' => $quote['currency'], 'availability' => 'https://schema.org/InStock', 'url' => $card->publicUrl()] : null,
    ]);
    $person = array_filter(['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $designer->display_name, 'url' => $designer->publicUrl(), 'image' => $designer->avatarUrl()]);
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
@endphp
@extends('layouts.app', [
    'title' => $card->title.' · '.__('models.title_suffix').' · matplace',
    'description' => $text !== '' ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $text), 155) : __('models.description_one', ['title' => $card->title, 'name' => $designer->display_name]),
    'ogImage' => $cover?->url(),
    'noindex' => $preview,
])

@push('head')
<script type="application/ld+json">{!! json_encode($product, $flags) !!}</script>
<script type="application/ld+json">{!! json_encode($person, $flags) !!}</script>
@endpush

@section('content')
<div class="mx-auto max-w-5xl">
    @if($preview)<p class="note-warn mb-4 text-sm">{{ __('models.preview') }}</p>@endif
    <nav class="text-sm text-muted" aria-label="{{ __('models.title') }}">
        <a href="{{ route('models.index') }}" class="underline hover:text-ink">{{ __('models.title') }}</a>
        @if($card->categoryRow) · <a href="{{ route('models.index', ['category' => $card->categoryRow->slug]) }}" class="underline hover:text-ink">{{ $card->categoryRow->label() }}</a>@endif
    </nav>

    <div class="mt-3 grid gap-6 lg:grid-cols-[1.25fr_1fr]">
        {{-- pictures --}}
        <div data-gallery>
            <div class="card overflow-hidden">
                @if($cover)
                    <img data-gallery-main src="{{ $cover->url() }}" alt="{{ $card->title }}" class="aspect-[4/3] w-full bg-slate-50 object-contain">
                @elseif($card->modelFile?->previewUrl())
                    <img src="{{ $card->modelFile->previewUrl() }}" alt="{{ $card->title }}" class="aspect-[4/3] w-full bg-slate-50 object-contain">
                @else
                    <div class="flex aspect-[4/3] items-center justify-center bg-slate-50 text-6xl text-slate-300">◇</div>
                @endif
            </div>
            @if($images->count() > 1)
                <ul class="mt-2 grid grid-cols-5 gap-2 sm:grid-cols-6">
                    @foreach($images as $image)
                        <li><button type="button" data-gallery-thumb="{{ $image->url() }}" class="block overflow-hidden rounded-lg border border-line hover:border-action" aria-label="{{ __('models.picture', ['n' => $loop->iteration]) }}"><img src="{{ $image->url(true) }}" alt="" loading="lazy" class="aspect-square w-full object-cover"></button></li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- name, designer, price, order --}}
        <div>
            <h1 class="text-2xl font-extrabold leading-tight sm:text-3xl">{{ $card->title }}</h1>
            <a href="{{ $designer->publicUrl() }}" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold hover:text-action-dark">
                @if($designer->avatarUrl())<img src="{{ $designer->avatarUrl() }}" alt="" class="h-8 w-8 rounded-full object-cover">
                @else<span class="flex h-8 w-8 items-center justify-center rounded-full bg-ink text-xs font-bold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($designer->display_name, 0, 1)) }}</span>@endif
                {{ $designer->display_name }}
            </a>

            <section class="card mt-4 p-4" data-quote="{{ route('api.models.quote', $card->slug) }}" data-order="{{ route('farm.start', ['designer_model' => $card->id]) }}" aria-live="polite">
                @if($quote['available'])
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm text-muted">{{ __('models.price.label') }}</span>
                        <span class="text-3xl font-extrabold tracking-tight" data-quote-total>{{ $quote['total_text'] }}</span>
                    </div>
                    <p class="text-right text-sm text-muted {{ $quote['royalty'] > 0 ? '' : 'hidden' }}" data-quote-royalty-line>{{ __('models.price.to_author') }} <span data-quote-royalty>{{ $quote['royalty_text'] }}</span></p>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <label class="lbl">{{ __('models.price.material') }}
                            <select data-quote-material class="field">
                                @foreach($materials as $m)<option value="{{ $m->code }}" @selected($m->code === $quote['material'])>{{ $m->label() }}</option>@endforeach
                            </select>
                        </label>
                        <label class="lbl">{{ __('models.price.copies') }}<input data-quote-copies type="number" min="1" max="64" value="1" inputmode="numeric" class="field"></label>
                    </div>
                    <a data-quote-go href="{{ route('farm.start', ['designer_model' => $card->id]) }}" class="btn-primary mt-4 w-full">{{ __('models.order') }}</a>
                    <p class="hint mt-2">{{ __('models.price.note') }}</p>
                @else
                    <p class="text-sm text-slate-600">{{ __('models.price.unavailable') }}</p>
                    <a href="{{ route('farm.start', ['designer_model' => $card->id]) }}" class="btn-primary mt-3 w-full">{{ __('models.order') }}</a>
                @endif
            </section>

            @if($downloadable)
                <section class="card mt-3 p-4">
                    <h2 class="font-bold">{{ __('models.download.title') }}</h2>
                    <p class="hint">{{ __('models.download.license', ['license' => __('designer.license.'.$card->download_license)]) }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <button type="button" class="btn-secondary min-h-0 px-4 py-2" data-pick-printer="{{ $card->modelFile->uuid }}" data-pick-name="{{ $card->title }}" data-pick-stl="{{ route('api.files.stl', $card->modelFile) }}?download=1">{{ __('models.download.for_printer') }}</button>
                        <a href="{{ route('api.files.stl', $card->modelFile) }}?download=1" download="{{ \Illuminate\Support\Str::slug($card->title) }}.stl" class="text-action-dark underline">{{ __('models.download.stl') }}</a>
                    </div>
                </section>
            @endif

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                @if(! empty($summary['dims']))<div><dt class="text-muted">{{ __('models.facts.size') }}</dt><dd class="font-semibold">{{ round($summary['dims']['x']) }} × {{ round($summary['dims']['y']) }} × {{ round($summary['dims']['z']) }} mm</dd></div>@endif
                @if(! empty($summary['grams']))<div><dt class="text-muted">{{ __('models.facts.weight') }}</dt><dd class="font-semibold">{{ round($summary['grams']) }} g</dd></div>@endif
                @if(! empty($summary['minutes']))<div><dt class="text-muted">{{ __('models.facts.time') }}</dt><dd class="font-semibold">{{ intdiv((int) $summary['minutes'], 60) }} h {{ (int) $summary['minutes'] % 60 }} min</dd></div>@endif
                <div><dt class="text-muted">{{ __('models.facts.origin') }}</dt>
                    <dd class="font-semibold">
                        @if($card->external_url)<a href="{{ $card->external_url }}" rel="nofollow noopener" target="_blank" class="underline">{{ __('designer.source.'.$card->source) }} ↗</a>@else{{ __('models.facts.original') }}@endif
                    </dd>
                </div>
                <div class="col-span-2"><dt class="text-muted">{{ __('models.facts.license') }}</dt>
                    <dd class="font-semibold">{{ $downloadable ? __('designer.license.'.$card->download_license) : __('models.facts.print_only') }}
                        @if($card->is_remix && $card->remix_source_url) · <a href="{{ $card->remix_source_url }}" rel="nofollow noopener" target="_blank" class="font-normal underline">{{ __('models.facts.remix_of') }} ↗</a>@endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    @if($text !== '')
        <section class="mt-8 max-w-3xl">
            <h2 class="text-lg font-bold">{{ __('models.about') }}</h2>
            <p class="mt-2 whitespace-pre-line leading-relaxed text-slate-700" lang="{{ $textLocale }}">{{ $text }}</p>
        </section>
    @endif

    @if($more->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold">{{ __('models.more_from', ['name' => $designer->display_name]) }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach($more as $other)
                    @include('models._tile', ['card' => $other])
                @endforeach
            </div>
        </section>
    @endif
</div>
@if($downloadable)@include('partials.printer_pick')@endif
@endsection
