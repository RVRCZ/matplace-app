{{--
    Homepage for customers. The look comes from the approved concept (cream, ink blue, orange, product photography);
    the first screen is still the working entrance: file, photo or words → price. Everything here hides once a model is open.
--}}
@php
    $pic = function (string $name, string $alt, string $sizes, bool $eager = false) {
        $base = asset('img/home/'.$name);
        return '<picture><source type="image/webp" srcset="'.$base.'-640.webp 640w, '.$base.'-1024.webp 1024w, '.$base.'-1536.webp 1536w" sizes="'.$sizes.'">'
            .'<img src="'.$base.'-1024.jpg" alt="'.e($alt).'" width="1536" height="1024" '.($eager ? 'fetchpriority="high"' : 'loading="lazy"').' decoding="async" class="h-auto w-full"></picture>';
    };
    // without the marketplace the copy speaks about downloading or printing on the farm, never about other printers
    $mp = (bool) config('features.marketplace');
    $tx = fn (string $key) => __($mp ? $key : 'home.farm.'.$key);
    // the spare-part inquiry is a tool of the catalogue too: no tile where its page would not open (/admin/tools)
    $spare = $mp && \App\Domain\Tools\ToolVisibility::canOpen(auth()->user(), 'spare');
@endphp

<section id="hero">
    {{-- one centred column: the file, the photo and the words are the page (the illustration left on 16 Oct 2026) --}}
    <div class="mx-auto max-w-2xl pt-2 text-center lg:pt-8">
        <div class="min-w-0">
            <p class="flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-action"><span class="h-1.5 w-1.5 rounded-full bg-action" aria-hidden="true"></span>{{ __('home.eyebrow') }}</p>
            @php $titleTag = empty($initial) ? 'h1' : 'p'; @endphp{{-- a calculation (/c/<code>) has the model's name as its h1 --}}
            <{{ $titleTag }} class="mt-3 break-words text-[2.4rem] font-extrabold leading-[1.05] tracking-tight text-ink sm:mt-4 sm:text-6xl">{{ __('home.title.a') }}<br><span class="text-action">{{ __('home.title.c') }}</span></{{ $titleTag }}>
            <p class="mx-auto mt-3 max-w-md text-base leading-relaxed text-muted sm:mt-4 sm:text-lg">{{ $tx('home.lead') }}</p>
            {{-- the quieter second way (audit 10/2026, bod 3): the model can be downloaded for one's own printer --}}
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ __('home.printer_note') }}</p>
            {{-- one line on a phone too: the first screen has the file field and the price promise on it (audit 10/2026, bod 4) --}}
            <ol class="mt-3 flex flex-wrap items-center justify-center gap-x-1 gap-y-1 text-xs font-semibold text-ink sm:mt-4 sm:gap-x-2 sm:text-sm" aria-label="{{ __('home.path') }}">
                @foreach(['idea', 'design', 'product'] as $i => $stage)
                    @if($i)<li class="text-action" aria-hidden="true">→</li>@endif
                    <li class="rounded-full border border-line bg-card px-2 py-0.5 sm:px-3 sm:py-1">{{ __('home.path.'.$stage) }}</li>
                @endforeach
            </ol>

            <div class="mt-4 sm:mt-5">
                @include('calculator.inputs', ['centered' => true])
            </div>

            <ul class="mt-4 flex flex-wrap justify-center gap-x-5 gap-y-1 text-xs text-muted">
                {{-- "from 99 Kč": the cheapest of the priced examples (config site.example_items), only in crowns (audit 26 Oct 2026, D15) --}}
                @php $from = \App\Support\Currency::current() === \App\Support\Money::CZK ? collect((array) config('site.example_items'))->map(fn ($i) => $i['price']['CZK'] ?? null)->filter()->min() : null; @endphp
                @foreach($mp ? ['real_prices', 'no_signup', 'share'] : ['free_tools', 'download', $from ? 'farm_from' : 'farm'] as $b)
                    <li class="flex items-center gap-1.5"><svg class="h-3.5 w-3.5 text-action" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5l4 4 8-9"/></svg>{{ $b === 'free_tools' ? trans_choice('home.benefit.free_tools_n', $n = (int) config('ai.daily_limits.generate_guest'), ['n' => $n]) : __('home.benefit.'.$b, ['price' => $from ? \App\Support\Money::of((float) $from, \App\Support\Money::CZK)->format() : '']) }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    @include('calculator.search_results')

    {{-- five ways in, each one a real action (TODO #45): a file, a photo or an idea, a gift, a part, a business --}}
    {{-- a spare part is an inquiry to printers with the marketplace; without it the part leads to the part tools --}}
    @php
        $toolsUrl = route('tools');
        $wrench = 'M14.7 6.3a4 4 0 00-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 005.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z';
        $ways = array_filter([
            ['file', 'M7 3h7l5 5v13H7zM14 3v5h5', '#', 'file'],
            ['idea', 'M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2z', '#', 'idea'],
            \Illuminate\Support\Facades\Route::has('tools.gifts') ? ['gift', 'M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z', route('tools.gifts'), null] : null,
            $spare ? ['spare', $wrench, route('tools.spare'), null] : ['part', $wrench, $toolsUrl.'#parts', null],
            ['firm', 'M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16', \Illuminate\Support\Facades\Route::has('tools.business') ? route('tools.business') : $toolsUrl.'#signs', null],
        ]);
    @endphp
    <nav aria-label="{{ __('tools.intents') }}" class="mt-10 grid divide-y divide-line border-y border-line lg:grid-cols-5 lg:divide-x lg:divide-y-0" data-ways>
        @foreach($ways as [$k, $path, $href, $tile])
            {{-- a way that only runs a script (open the file field, open the search) is a button, not a link to "#" (audit 26 Oct 2026, A3) --}}
            @php $tag = $href === '#' ? 'button' : 'a'; @endphp
            <{{ $tag }} @if($tag === 'a') href="{{ $href }}" @else type="button" @endif data-way="{{ $k }}" @if($tile) data-tile="{{ $tile }}" @endif @if($k === 'file') onclick="document.getElementById('file-input').click();return false;" @endif
               class="group flex w-full items-start gap-3 px-1 py-4 text-left sm:px-5 lg:first:pl-0 lg:last:pr-0">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-action-soft text-action-dark" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg></span>
                <span class="min-w-0 flex-1"><span class="block font-bold text-ink">{{ __('home.start.'.$k) }}</span><span class="block text-sm text-muted">{{ __('home.start.'.$k.'.hint') }}</span></span>
                <span class="mt-1 text-ink transition group-hover:translate-x-0.5" aria-hidden="true">→</span>
            </{{ $tag }}>
        @endforeach
    </nav>

    {{-- the tools people use most: config/home.php --}}
    @php
        $homeTools = collect(config('home.tools'))
            ->mapWithKeys(fn ($key) => [$key => config('tools.'.$key)])
            ->filter(fn ($t, $key) => $t && \App\Domain\Tools\ToolVisibility::isPublic($key) && \Illuminate\Support\Facades\Route::has($t['route']) && (! in_array($key, ['figure', 'pet'], true) || ($config['generator'] ?? false)))
            ->take((int) config('home.tools_shown', 8));
    @endphp
    <section class="mt-14" aria-labelledby="home-tools">
        <p class="text-xs font-bold uppercase tracking-[0.14em] text-action">{{ __('home.tools.kicker') }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="home-tools" class="text-3xl font-extrabold tracking-tight text-ink">{{ __('home.tools.title') }}</h2><p class="mt-1 text-muted">{{ __('home.tools.lead') }}</p></div>
            <a href="{{ route('tools') }}" class="inline-flex min-h-11 items-center font-semibold text-ink underline-offset-4 hover:underline">{{ __('home.tools.all') }} →</a>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($homeTools as $key => $tool)
                @include('tools.card', ['key' => $key, 'tool' => $tool, 'badge' => in_array($key, (array) config('home.popular'), true) ? __('home.tools.popular') : null])
            @endforeach
        </div>
        <p class="mt-6 text-center"><a href="{{ route('tools') }}" class="btn-secondary">{{ __('home.tools.all_count', ['n' => count(\App\Domain\Tools\ToolVisibility::listed())]) }} →</a></p>
    </section>

    {{-- prints with their price: off until the photos of the prints come (config/site.php) --}}
    @if(config('site.examples'))
        @include('calculator._examples')
    @endif

    {{-- how it goes --}}
    <section class="-mx-4 mt-14 bg-white/60 px-4 py-12" aria-labelledby="home-steps">
        <p class="text-xs font-bold uppercase tracking-[0.14em] text-action">{{ __('home.steps.kicker') }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3"><h2 id="home-steps" class="text-3xl font-extrabold tracking-tight text-ink">{{ __('home.steps.title') }}</h2><p class="max-w-xs text-sm text-muted">{{ $tx('home.steps.note') }}</p></div>
        <ol class="mt-8 grid gap-8 md:grid-cols-3">
            @foreach([1, 2, 3] as $n)
                <li><div class="flex items-center gap-3 text-xs font-bold text-action">0{{ $n }}<span class="h-px flex-1 bg-line" aria-hidden="true"></span></div><h3 class="mt-4 text-lg font-bold text-ink">{{ $tx('home.step'.$n) }}</h3><p class="mt-1 text-sm leading-relaxed text-muted">{{ $tx('home.step'.$n.'.text') }}</p></li>
            @endforeach
        </ol>
    </section>
</section>
