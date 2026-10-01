@extends('layouts.app', ['title' => $page['title'].' · matplace', 'description' => $page['description'] ?? null])

@php
    // plain text; an address or an e-mail written in it becomes a link
    $linked = fn (string $text) => preg_replace(
        ['~(https://[^\s)]+[^\s).,;])~', '~([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,})~'],
        ['<a href="$1" class="text-action-dark underline">$1</a>', '<a href="mailto:$1" class="text-action-dark underline">$1</a>'],
        e($text)
    );
@endphp

@push('head')
@if(! empty($page['items']))<x-jsonld :data="\App\Support\Schema::faq($page['items'])" />@endif
@endpush

@section('content')
<article class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 text-slate-700">
    <h1 class="text-2xl font-extrabold text-ink">{{ $page['title'] }}</h1>
    @if(in_array($key, ['terms', 'business_terms', 'cookies', 'complaints'], true) && $updated)<p class="text-xs text-slate-500">{{ __('site.pages.updated', ['date' => $updated]) }}</p>@endif
    @if(! empty($page['lead']))<p class="mt-3 text-lg leading-relaxed">{!! $linked($page['lead']) !!}</p>@endif

    @foreach($page['sections'] ?? [] as $section)
        <section class="mt-5">
            <h2 class="font-bold text-ink">{{ $section['h'] }}</h2>
            @foreach($section['p'] ?? [] as $p)
                <p class="mt-2 leading-relaxed">{!! $linked($p) !!}</p>
            @endforeach
            @if(! empty($section['li']))
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($section['li'] as $li)<li>{!! $linked($li) !!}</li>@endforeach
                </ul>
            @endif
        </section>
    @endforeach

    @if(! empty($page['items']))
        <div class="mt-5 divide-y divide-line rounded-2xl border border-line">
            @foreach($page['items'] as $item)
                <details class="group px-4 py-3">
                    <summary class="cursor-pointer list-none font-semibold text-ink"><span class="mr-1 inline-block text-action transition group-open:rotate-90" aria-hidden="true">›</span>{{ $item['q'] }}</summary>
                    <p class="mt-2 leading-relaxed">{!! $linked($item['a']) !!}</p>
                </details>
            @endforeach
        </div>
    @endif

    @if($key === 'cookies')
        {{-- the same choice the bar offers, any time later --}}
        <p class="mt-6"><button type="button" class="btn-quiet text-sm" data-consent-open>{{ __('site.consent.change') }}</button></p>
    @endif
</article>
@endsection
