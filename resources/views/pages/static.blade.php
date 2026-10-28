@extends('layouts.app', ['title' => $page['title'].' · matplace', 'description' => $page['description'] ?? null])
{{-- a section may carry `tools` (keys of config/tools.php, shown as cards, the ones on offer) and `prices` (rows of real calculations,
     config/site.php example_items in the visitor's currency): the page for businesses (docs/AN.md §8) --}}

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
{{-- who runs the site, in full (legal name, office, company number): the pages that say so (docs/AN.md §12.4) --}}
@if(in_array($key, ['about', 'contact'], true))<x-jsonld :data="\App\Support\Schema::organization(true)" />@endif
@endpush

@section('content')
<article class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 text-slate-700">
    <h1 class="text-2xl font-extrabold text-ink">{{ $page['h1'] ?? $page['title'] }}</h1>
    @if(in_array($key, ['terms', 'business_terms', 'cookies', 'complaints'], true) && $updated)<p class="text-xs text-slate-500">{{ __('site.pages.updated', ['date' => $updated]) }}</p>@endif
    @if(! empty($page['lead']))<p class="mt-3 text-lg leading-relaxed">{!! $linked($page['lead']) !!}</p>@endif

    @foreach($page['sections'] ?? [] as $section)
        <section class="mt-5">
            <h2 class="font-bold text-ink">{{ $section['h'] }}</h2>
            @foreach($section['p'] ?? [] as $p)
                <p class="mt-2 leading-relaxed">{!! $linked($p) !!}</p>
            @endforeach
            @if(! empty($section['tools']))
                @php $offered = array_intersect_key(\App\Domain\Tools\ToolVisibility::listed(), array_flip((array) $section['tools'])); @endphp
                <div class="mt-3 grid gap-2 sm:grid-cols-2" data-page-tools>
                    @foreach((array) $section['tools'] as $toolKey)
                        @if(isset($offered[$toolKey]) && \Illuminate\Support\Facades\Route::has($offered[$toolKey]['route']))@include('tools.card_row', ['key' => $toolKey, 'tool' => $offered[$toolKey], 'pick' => true])@endif
                    @endforeach
                </div>
            @endif
            @if(! empty($section['prices']))
                @php $currency = \App\Support\Currency::current(); $examples = collect(config('site.example_items', []))->filter(fn ($i) => \App\Domain\Tools\ToolVisibility::isPublic($i['tool']) && isset($i['price'][$currency]))->unique('tool'); @endphp
                @if($examples->isNotEmpty())
                    <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-line text-sm" data-page-prices>
                        @foreach($examples as $i)
                            <li class="flex items-center justify-between gap-3 px-3 py-2">
                                <a href="{{ url('/c/'.$i['calc']) }}" class="min-w-0 text-ink underline-offset-4 hover:underline"><span class="block font-medium">{{ __('site.examples.item.'.$i['tool'], ['mm' => $i['mm'], 'size' => $i['size'] ?? '']) }}</span><span class="block text-xs text-slate-500">{{ __('site.examples.meta', ['material' => $i['material'], 'g' => $i['grams'], 'time' => $i['minutes'] >= 60 ? intdiv($i['minutes'], 60).' h '.($i['minutes'] % 60).' min' : $i['minutes'].' min']) }}</span></a>
                                <span class="shrink-0 font-semibold text-ink">{{ \App\Support\Money::of($i['price'][$currency], $currency)->format() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-slate-500">{{ __('site.examples.other_currency') }}</p>
                @endif
            @endif
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
