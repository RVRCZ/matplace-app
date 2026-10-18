{{--
    The blocks of a landing page under its tool (docs/AH.md §11): how it turned out (real prints, only with
    config('site.examples')), before you order (questions; the FAQPage data of the head carries them too), who it is for.
    "Where next" is the partial's second part ($part = 'next'). App\Support\LandingPage, config/landing.php.
--}}
@php $part ??= 'top'; @endphp
@if($part === 'top')
    @if(config('site.examples') && $tool !== 'calc')
        @include('calculator._examples', ['only' => $tool])
    @endif

    @if($order = \App\Support\LandingPage::order($tool))
        <section id="landing-order" class="mt-10" aria-labelledby="landing-order-title">
            <h3 id="landing-order-title" class="text-lg font-bold text-ink">{{ __('landing.titles.order') }}</h3>
            <div class="mt-3 divide-y divide-line rounded-2xl border border-line bg-card">
                @foreach($order as $item)
                    <details class="group px-4 py-1" @if($loop->first) open @endif>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center font-semibold text-ink marker:hidden">
                            <span class="mr-2 inline-block text-action transition group-open:rotate-90" aria-hidden="true">›</span>{{ $item['q'] }}
                        </summary>
                        <p class="pb-3 leading-relaxed">{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    @if($who = \App\Support\LandingPage::who($tool))
        <section id="landing-who" class="mt-10" aria-labelledby="landing-who-title">
            <h3 id="landing-who-title" class="text-lg font-bold text-ink">{{ __('landing.titles.who') }}</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach($who as $w)
                    <div class="card p-4">
                        <h4 class="font-semibold text-ink">{{ $w['t'] }}</h4>
                        <p class="mt-1 text-sm leading-relaxed text-muted">{{ $w['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@else
    @php
        $related = \App\Support\LandingPage::related($tool);
        $posts = \App\Support\LandingPage::posts($tool);
    @endphp
    @if($related || $posts)
        <section id="landing-next" class="mt-10" aria-labelledby="landing-next-title">
            <h3 id="landing-next-title" class="text-lg font-bold text-ink">{{ __('landing.titles.next') }}</h3>
            <div class="mt-3 grid gap-6 sm:grid-cols-2">
                @if($related)
                    <div>
                        <h4 class="text-sm font-semibold text-muted">{{ __('landing.titles.next_tools') }}</h4>
                        <ul class="mt-1">
                            @foreach($related as $r)
                                <li><a href="{{ $r['url'] }}" class="inline-flex min-h-11 items-center gap-1 font-medium text-action-dark underline-offset-4 hover:underline">{{ __('tools.'.$r['key'].'.title') }}<x-icon name="arrow-right" class="h-4 w-4" /></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($posts)
                    <div>
                        <h4 class="text-sm font-semibold text-muted">{{ __('landing.titles.next_blog') }}</h4>
                        <ul class="mt-1">
                            @foreach($posts as $p)
                                <li><a href="{{ $p['url'] }}" class="inline-flex min-h-11 items-center gap-1 font-medium text-action-dark underline-offset-4 hover:underline">{{ $p['title'] }}<x-icon name="arrow-right" class="h-4 w-4" /></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endif
@endif
