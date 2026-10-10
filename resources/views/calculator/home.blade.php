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
    <div class="grid items-center gap-8 pt-2 lg:grid-cols-[1fr_1.05fr] lg:gap-14 lg:pt-8">
        <div class="min-w-0">
            <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-action"><span class="h-1.5 w-1.5 rounded-full bg-action" aria-hidden="true"></span>{{ __('home.eyebrow') }}</p>
            <h1 class="mt-4 text-[2.6rem] font-extrabold leading-[1.05] tracking-tight text-ink sm:text-6xl">{{ __('home.title.a') }}<br><span class="text-action">{{ __('home.title.c') }}</span></h1>
            <p class="mt-4 max-w-md text-base leading-relaxed text-muted sm:text-lg">{{ $tx('home.lead') }}</p>
            <ol class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-semibold text-ink" aria-label="{{ __('home.path') }}">
                @foreach(['idea', 'design', 'product'] as $i => $stage)
                    @if($i)<li class="text-action" aria-hidden="true">→</li>@endif
                    <li class="rounded-full border border-line bg-card px-3 py-1">{{ __('home.path.'.$stage) }}</li>
                @endforeach
            </ol>

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
    {{-- a spare part is an inquiry to printers: only with the marketplace --}}
    <nav aria-label="{{ __('tools.intents') }}" class="mt-10 grid divide-y divide-line border-y border-line {{ $spare ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} sm:divide-x sm:divide-y-0">
        @foreach(array_filter([
            ['file', 'M7 3h7l5 5v13H7zM14 3v5h5', '#', 'file'],
            ['idea', 'M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2z', '#', 'idea'],
            $spare ? ['spare', 'M14.7 6.3a4 4 0 00-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 005.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z', route('tools.spare'), null] : null,
        ]) as [$k, $path, $href, $tile])
            <a href="{{ $href }}" @if($tile) data-tile="{{ $tile }}" @endif @if($k === 'file') onclick="document.getElementById('file-input').click();return false;" @endif
               class="group flex items-start gap-3 px-1 py-5 sm:px-5 sm:first:pl-0 sm:last:pr-0">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-action-soft text-action-dark" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg></span>
                <span class="min-w-0 flex-1"><span class="block font-bold text-ink">{{ __('home.start.'.$k) }}</span><span class="block text-sm text-muted">{{ __('home.start.'.$k.'.hint') }}</span></span>
                <span class="mt-1 text-ink transition group-hover:translate-x-0.5" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>

    {{-- the tools people use most: config/home.php --}}
    @php
        $homeTools = collect(config('home.tools'))
            ->mapWithKeys(fn ($key) => [$key => config('tools.'.$key)])
            ->filter(fn ($t, $key) => $t && \App\Domain\Tools\ToolVisibility::isPublic($key) && \Illuminate\Support\Facades\Route::has($t['route']) && ($key !== 'figure' || ($config['generator'] ?? false)))
            ->take((int) config('home.tools_shown', 8));
    @endphp
    <section class="mt-14" aria-labelledby="home-tools">
        <p class="text-xs font-bold uppercase tracking-[0.14em] text-action">{{ __('home.tools.kicker') }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="home-tools" class="text-3xl font-extrabold tracking-tight text-ink">{{ __('home.tools.title') }}</h2><p class="mt-1 text-muted">{{ __('home.tools.lead') }}</p></div>
            <a href="{{ route('tools') }}" class="font-semibold text-ink underline-offset-4 hover:underline">{{ __('home.tools.all') }} →</a>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($homeTools as $key => $tool)
                @include('tools.card', ['key' => $key, 'tool' => $tool])
            @endforeach
        </div>
        <p class="mt-6 text-center"><a href="{{ route('tools') }}" class="btn-secondary">{{ __('home.tools.all_count', ['n' => count(\App\Domain\Tools\ToolVisibility::listed())]) }} →</a></p>
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
</section>
