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
    $printerUrl = auth()->check() ? (auth()->user()->isPrinter() ? route('printer.dashboard') : route('account')) : route('register', ['role' => 'printer']);
    // without the marketplace the copy speaks about downloading or printing on the farm, never about other printers
    $mp = (bool) config('features.marketplace');
    $tx = fn (string $key) => __($mp ? $key : 'home.farm.'.$key);
@endphp

<section id="hero">
    <div class="grid items-center gap-8 pt-2 lg:grid-cols-[1fr_1.05fr] lg:gap-14 lg:pt-8">
        <div class="min-w-0">
            <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-action"><span class="h-1.5 w-1.5 rounded-full bg-action" aria-hidden="true"></span>{{ __('home.eyebrow') }}</p>
            <h1 class="mt-4 text-[2.6rem] font-extrabold leading-[1.05] tracking-tight text-ink sm:text-6xl">{{ __('home.title.a') }}<br>{{ __('home.title.b') }} <span class="text-action">{{ __('home.title.c') }}</span></h1>
            <p class="mt-4 max-w-md text-base leading-relaxed text-muted sm:text-lg">{{ $tx('home.lead') }}</p>

            <div class="mt-5">
                @include('calculator.inputs')
            </div>

            <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted">
                @foreach($mp ? ['real_prices', 'no_signup', 'share'] : ['free_tools', 'download', 'farm'] as $b)
                    <li class="flex items-center gap-1.5"><svg class="h-3.5 w-3.5 text-action" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5l4 4 8-9"/></svg>{{ __('home.benefit.'.$b) }}</li>
                @endforeach
            </ul>
        </div>

        <figure class="m-0 min-w-0">
            <div class="overflow-hidden rounded-3xl bg-[#F3EEE6]">{!! $pic('hero', __('home.hero_alt'), '(min-width: 1024px) 560px, 100vw', true) !!}</div>
            <figcaption class="mt-3 flex items-center justify-between gap-3 text-xs text-muted"><span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-action" aria-hidden="true"></span>{{ __('home.caption') }}</span><span>{{ __('home.caption_note') }}</span></figcaption>
        </figure>
    </div>

    @include('calculator.search_results')

    {{-- three ways in, each one a real action --}}
    <nav aria-label="{{ __('tools.intents') }}" class="mt-10 grid divide-y divide-line border-y border-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        @foreach([
            ['file', 'M7 3h7l5 5v13H7zM14 3v5h5', '#', 'file'],
            ['idea', 'M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2z', '#', 'idea'],
            ['spare', 'M14.7 6.3a4 4 0 00-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 005.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z', route('tools.spare'), null],
        ] as [$k, $path, $href, $tile])
            <a href="{{ $href }}" @if($tile) data-tile="{{ $tile }}" @endif @if($k === 'file') onclick="document.getElementById('file-input').click();return false;" @endif
               class="group flex items-start gap-3 px-1 py-5 sm:px-5 sm:first:pl-0 sm:last:pr-0">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-action-soft text-action-dark" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg></span>
                <span class="min-w-0 flex-1"><span class="block font-bold text-ink">{{ __('home.start.'.$k) }}</span><span class="block text-sm text-muted">{{ __('home.start.'.$k.'.hint') }}</span></span>
                <span class="mt-1 text-ink transition group-hover:translate-x-0.5" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>

    {{-- what the tools make --}}
    <section class="mt-14" aria-labelledby="home-tools">
        <p class="text-xs font-bold uppercase tracking-[0.14em] text-action">{{ __('home.tools.kicker') }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="home-tools" class="text-3xl font-extrabold tracking-tight text-ink">{{ __('home.tools.title') }}</h2><p class="mt-1 text-muted">{{ __('home.tools.lead') }}</p></div>
            <a href="{{ route('tools') }}" class="font-semibold text-ink underline-offset-4 hover:underline">{{ __('home.tools.all') }} →</a>
        </div>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            @foreach([['modular', 'organizer', 'home'], ['vase', 'vases', 'craft']] as [$tool, $img, $cat])
                <a href="{{ route('tools.'.$tool) }}" class="group block rounded-3xl border border-line bg-card p-2 transition hover:border-action">
                    <div class="overflow-hidden rounded-2xl bg-[#F3EEE6]">{!! $pic($img, __('tools.'.$tool.'.title'), '(min-width: 768px) 540px, 100vw') !!}</div>
                    <div class="flex items-start justify-between gap-4 px-3 pb-3 pt-5">
                        <div><p class="text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-muted">{{ __('tools.cat.'.$cat) }}</p><h3 class="mt-1 text-2xl font-bold tracking-tight text-ink">{{ __('home.card.'.$tool) }}</h3><p class="mt-1 text-sm text-muted">{{ __('home.card.'.$tool.'.hint') }}</p></div>
                        <span class="mt-5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-line text-ink transition group-hover:border-action group-hover:bg-action group-hover:text-white" aria-hidden="true">→</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
            <span class="text-muted">{{ __('home.tools.more') }}</span>
            @foreach(array_filter(['figure' => $config['generator'] ?? false, 'lightbox' => true, 'box' => true, 'qr' => true, 'relief' => true]) as $tool => $on)
                <a href="{{ route('tools.'.$tool) }}" class="rounded-lg border border-line bg-card px-3 py-2 font-medium text-ink hover:border-action">{{ __('tools.'.$tool.'.title') }} →</a>
            @endforeach
        </div>
    </section>

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

    {{-- printers (marketplace only) --}}
    @if($mp)
    <section class="mt-12 grid gap-8 rounded-3xl bg-ink p-8 text-white md:grid-cols-2 md:p-12" aria-labelledby="home-printers">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#F2B79F]">{{ __('tools.printers.title') }}</p>
            <h2 id="home-printers" class="mt-3 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">{{ __('home.printers.title.a') }}<br>{{ __('home.printers.title.b') }}</h2>
            <p class="mt-3 max-w-md text-sm leading-relaxed text-white/80">{{ __('home.printers.lead') }}</p>
            <a href="{{ $printerUrl }}" class="mt-6 inline-flex min-h-11 items-center gap-3 rounded-xl bg-white px-5 py-3 font-semibold text-ink hover:bg-action-soft">{{ __('home.printers.cta') }} →</a>
        </div>
        <ul class="self-center divide-y divide-white/15 text-sm">
            @foreach(['cost', 'quote', 'direct'] as $f)
                <li class="flex items-center gap-3 py-4"><svg class="h-5 w-5 shrink-0 text-[#F2B79F]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>{{ __('home.printers.'.$f) }}</li>
            @endforeach
        </ul>
    </section>
    @endif
</section>
