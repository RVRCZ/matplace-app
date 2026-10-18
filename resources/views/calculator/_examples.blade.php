{{--
    Prints with their price (the customer audit, bod 6): real calculations of the farm (config/site.php, docs/AG.md).
    Shown only with config('site.examples'); until Roman's photos come every picture says it is a preview, not a print.
--}}
@php
    $currency = \App\Support\Currency::current();
    $items = collect(config('site.example_items', []))->filter(fn ($i) => \App\Domain\Tools\ToolVisibility::isPublic($i['tool']));
@endphp
@if($items->isNotEmpty())
<section id="home-examples" class="mt-14" aria-labelledby="home-examples-title">
    <h2 id="home-examples-title" class="text-3xl font-extrabold tracking-tight text-ink">{{ __('site.examples.title') }}</h2>
    <p class="mt-1 text-muted">{{ __('site.examples.lead') }}</p>
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach($items as $i)
            <article class="card flex flex-col overflow-hidden">
                <figure class="relative bg-studio">
                    @if($i['photo'])
                        <img src="{{ asset($i['photo']) }}" alt="{{ __('site.examples.item.'.$i['tool'], ['mm' => $i['mm']]) }}" width="800" height="800" loading="lazy" class="aspect-square w-full object-cover">
                    @else
                        @include('tools.picture', ['key' => $i['tool']])
                        <figcaption class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-ink shadow-sm">{{ __('site.examples.preview') }}</figcaption>
                    @endif
                </figure>
                <div class="flex flex-1 flex-col p-4">
                    <h3 class="text-lg font-semibold text-ink">{{ __('site.examples.item.'.$i['tool'], ['mm' => $i['mm']]) }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ __('site.examples.meta', ['material' => $i['material'], 'g' => $i['grams'], 'time' => $i['minutes'] >= 60 ? intdiv($i['minutes'], 60).' h '.($i['minutes'] % 60).' min' : $i['minutes'].' min']) }}</p>
                    @if(isset($i['price'][$currency]))
                        <p class="mt-2 text-2xl font-extrabold text-ink">{{ \App\Support\Money::of($i['price'][$currency], $currency)->format() }} <span class="text-sm font-normal text-muted">{{ __('site.examples.price_note') }}</span></p>
                    @else
                        <p class="mt-2 text-sm text-muted">{{ __('site.examples.other_currency') }}</p>
                    @endif
                    <a href="{{ url('/c/'.$i['calc']) }}" class="mt-3 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-ink underline-offset-4 hover:underline">{{ __('site.examples.open') }}<x-icon name="arrow-right" class="h-4 w-4" /></a>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif
