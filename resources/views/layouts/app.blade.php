@php
    // a tool's page brings its own title, description and content from lang/<locale>/tools_seo/<tool>.php
    $toolSeo = ! empty($tool) ? \App\Support\ToolSeo::texts($tool) : null;
    $title = $toolSeo ? $toolSeo['title'].' · matplace' : ($title ?? 'matplace');
    $description = $toolSeo['description'] ?? ($description ?? __('app.subline'));
    $noindex = ! empty($noindex) || \App\Support\Locales::noindex() || ! config('seo.indexable', true);
    $canonical = $canonical ?? \App\Support\Locales::canonical();
    $alternates = $noindex ? [] : \App\Support\Locales::alternates();
    $ogImage = $ogImage ?? ($toolSeo ? \App\Http\Controllers\OgController::url('tool', str_replace('_', '-', $tool)) : \App\Http\Controllers\OgController::url('site', 'home'));
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:site_name" content="matplace">
    <meta property="og:locale" content="{{ ['cs' => 'cs_CZ', 'en' => 'en_GB', 'es' => 'es_ES'][app()->getLocale()] ?? 'cs_CZ' }}">
    @if($canonical)<meta property="og:url" content="{{ $canonical }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    @if($noindex)
        <meta name="robots" content="noindex, nofollow">
    @elseif($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    @foreach($alternates as $l => $href)
        <link rel="alternate" hreflang="{{ $l }}" href="{{ $href }}">
    @endforeach
    @if($alternates)
        <link rel="alternate" hreflang="x-default" href="{{ $alternates[\App\Support\Locales::DEFAULT] }}">
    @endif
    @if(config('seo.google_site_verification'))<meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}">@endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- amounts are printed by scripts the same way the server prints them (resources/js/site/money.ts) --}}
    <script>window.MP_MONEY = {{ \Illuminate\Support\Js::from(\App\Support\Currency::forScripts()) }};</script>
    @include('partials.measure')
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @if($toolSeo)
        @if($toolSeo['steps'])<x-jsonld :data="\App\Support\Schema::howTo($toolSeo['h1'], $toolSeo['description'], $toolSeo['steps'], \App\Support\ToolSeo::url($tool), array_column(\App\Support\ToolSeo::examples($tool), 'url'))" />@endif
        @if($toolSeo['faq'])<x-jsonld :data="\App\Support\Schema::faq($toolSeo['faq'])" />@endif
    @endif
    @stack('head')
</head>
<body class="min-h-full bg-page text-ink antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-2">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2" aria-label="matplace"><img src="/img/logo-header.webp" alt="matplace" width="299" height="180" class="h-10 w-auto sm:h-12" fetchpriority="high"><span class="hidden rounded border border-line px-1.5 py-0.5 text-[0.6rem] font-bold uppercase tracking-widest text-muted sm:inline">beta</span></a>
            <nav class="flex min-w-0 flex-wrap items-center justify-end gap-x-2 gap-y-1 text-sm text-slate-600 sm:gap-x-4">
                <a href="{{ route('models.index') }}" class="hover:text-slate-900">{{ __('models.nav') }}</a>
                <a href="{{ route('tools') }}" class="hover:text-slate-900">{{ __('footer.tools') }}</a>
                @auth
                    <a href="{{ route('account') }}" class="font-medium hover:text-slate-900">{{ __('nav.account') }}</a>
                @else
                    <a href="{{ route('login') }}" class="font-medium hover:text-slate-900">{{ __('nav.login') }}</a>
                @endauth
                @if($languages = \App\Support\Locales::switcher())
                    <span class="flex items-center gap-0.5 text-xs sm:gap-1" aria-label="{{ __('site.language') }}">
                        @foreach($languages as $l => $href)
                            <a href="{{ $href }}" hreflang="{{ $l }}" lang="{{ $l }}" title="{{ __('site.languages.'.$l) }}" @if(app()->getLocale() === $l) aria-current="true" @endif class="rounded px-1.5 py-0.5 uppercase {{ app()->getLocale() === $l ? 'bg-slate-800 text-white' : 'hover:text-slate-900' }}">{{ $l }}</a>
                        @endforeach
                    </span>
                @endif
                {{-- prices in crowns or euros; gone once the account has a currency of its own (the first payment fixes it) --}}
                @unless(\App\Support\Currency::locked())
                    <form method="post" action="{{ route('currency') }}" class="flex items-center gap-0.5 text-xs sm:gap-1" aria-label="{{ __('site.currency') }}">
                        @csrf
                        @foreach(\App\Support\Money::CURRENCIES as $code)
                            <button type="submit" name="currency" value="{{ $code }}" title="{{ __('site.currencies.'.$code) }}" @if(\App\Support\Currency::current() === $code) aria-current="true" @endif class="rounded px-1.5 py-0.5 {{ \App\Support\Currency::current() === $code ? 'bg-slate-800 text-white' : 'hover:text-slate-900' }}">{{ \App\Support\Money::symbol($code) }}</button>
                        @endforeach
                    </form>
                @endunless
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 pb-16 pt-6">
        @yield('content')
        @if(($tool ?? null) === 'calc')
            @include('partials.banners')
        @endif
        @if($toolSeo)
            @include('tools._content', ['tool' => $tool, 'seo' => $toolSeo])
        @endif
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-center text-sm text-slate-500">
            {{ \App\Support\NextStep::text('footer.promise') }}
            <nav class="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-2" aria-label="{{ __('site.footer.about_nav') }}">
                @foreach(['about', 'contact', 'faq'] as $page)
                    @if(\Illuminate\Support\Facades\Lang::has('pages.'.$page.'.title'))<a href="{{ route('pages.'.$page) }}" class="underline hover:text-slate-900">{{ __('site.footer.'.$page) }}</a>@endif
                @endforeach
                <a href="{{ route('materials') }}" class="underline hover:text-slate-900">{{ __('site.materials.title') }}</a>
                <a href="{{ route('catalog.index') }}" class="underline hover:text-slate-900">{{ __('models.inspiration.title') }}</a>
                @if(\App\Http\Controllers\CollectionPageController::has(app()->getLocale()))<a href="{{ route('collections.index') }}" class="underline hover:text-slate-900">{{ __('site.collections.title') }}</a>@endif
                @if(in_array(app()->getLocale(), \App\Http\Controllers\BlogController::languages(), true))<a href="{{ route('blog.index') }}" class="underline hover:text-slate-900">{{ __('site.blog.title') }}</a>@endif
                <a href="{{ config('youtube.channel_url') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-slate-900">
                    {{-- YouTube icon, as the brand guidelines allow for linking to a channel --}}
                    <svg viewBox="0 0 28 20" class="h-4 w-auto" aria-hidden="true"><path fill="#FF0000" d="M27.4 3.1A3.5 3.5 0 0 0 24.9.6C22.7 0 14 0 14 0S5.3 0 3.1.6A3.5 3.5 0 0 0 .6 3.1C0 5.3 0 10 0 10s0 4.7.6 6.9a3.5 3.5 0 0 0 2.5 2.5C5.3 20 14 20 14 20s8.7 0 10.9-.6a3.5 3.5 0 0 0 2.5-2.5c.6-2.2.6-6.9.6-6.9s0-4.7-.6-6.9Z"/><path fill="#FFF" d="m11.2 14.3 7.3-4.3-7.3-4.3v8.6Z"/></svg>
                    <span class="underline">{{ __('privacy.footer.youtube') }}</span>
                </a>
            </nav>
            <nav class="mt-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs" aria-label="{{ __('privacy.title') }}">
                <a href="{{ route('privacy') }}" class="underline hover:text-slate-900">{{ __('privacy.footer.privacy') }}</a>
                <a href="{{ route('farm.terms') }}" class="underline hover:text-slate-900">{{ __('privacy.footer.terms') }}</a>
                @foreach(['business_terms', 'terms', 'complaints', 'cookies'] as $page)
                    @if(\Illuminate\Support\Facades\Lang::has('pages.'.$page.'.title'))<a href="{{ route('pages.'.$page) }}" class="underline hover:text-slate-900">{{ __('site.footer.'.$page) }}</a>@endif
                @endforeach
                <button type="button" class="underline hover:text-slate-900" data-consent-open>{{ __('site.consent.change') }}</button>
            </nav>
        </div>
    </footer>
    @include('partials.consent')
    @include('partials.lightbox')
</body>
</html>
