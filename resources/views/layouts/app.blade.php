<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'matplace' }}</title>
    <meta name="description" content="{{ $description ?? __('app.subline') }}">
    @if(!empty($ogImage))<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta property="og:title" content="{{ $title ?? 'matplace' }}">
    @if(!empty($noindex))<meta name="robots" content="noindex, nofollow">@endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/favicon.ico">
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @stack('head')
</head>
<body class="min-h-full bg-page text-ink antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-3">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 text-xl font-extrabold tracking-tight text-ink sm:text-2xl" aria-label="matplace">matplace<span class="-ml-1.5 text-action" aria-hidden="true">.</span><span class="hidden rounded border border-line px-1.5 py-0.5 text-[0.6rem] font-bold uppercase tracking-widest text-muted sm:inline">beta</span></a>
            <nav class="flex min-w-0 flex-wrap items-center justify-end gap-x-2 gap-y-1 text-sm text-slate-600 sm:gap-x-4">
                <a href="{{ route('tools') }}" class="hover:text-slate-900">{{ __('footer.tools') }}</a>
                @auth
                    @if(config('features.marketplace') && auth()->user()->isPrinter())<a href="{{ route('printer.dashboard') }}" class="hover:text-slate-900">🖨️ {{ __('nav.printer') }}</a>@endif
                    <a href="{{ route('account') }}" class="font-medium hover:text-slate-900">{{ __('nav.account') }}</a>
                @else
                    @if(config('features.marketplace'))<a href="{{ route('register', ['role' => 'printer']) }}" class="hidden sm:inline hover:text-slate-900">{{ __('nav.for_printers') }}</a>@endif
                    <a href="{{ route('login') }}" class="font-medium hover:text-slate-900">{{ __('nav.login') }}</a>
                @endauth
                <span class="flex items-center gap-0.5 text-xs sm:gap-1">
                    @foreach(\App\Http\Middleware\SetLocale::SUPPORTED as $l)
                        <a href="{{ request()->fullUrlWithQuery(['lang' => $l]) }}" class="rounded px-1.5 py-0.5 uppercase {{ app()->getLocale() === $l ? 'bg-slate-800 text-white' : 'hover:text-slate-900' }}">{{ $l }}</a>
                    @endforeach
                </span>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 pb-16 pt-6">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-center text-sm text-slate-500">
            {{ \App\Support\NextStep::text('footer.promise') }}
            <nav class="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-2" aria-label="{{ __('privacy.title') }}">
                <a href="{{ route('privacy') }}" class="underline hover:text-slate-900">{{ __('privacy.footer.privacy') }}</a>
                <a href="{{ route('farm.terms') }}" class="underline hover:text-slate-900">{{ __('privacy.footer.terms') }}</a>
                <a href="{{ config('youtube.channel_url') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-slate-900">
                    {{-- YouTube icon, as the brand guidelines allow for linking to a channel --}}
                    <svg viewBox="0 0 28 20" class="h-4 w-auto" aria-hidden="true"><path fill="#FF0000" d="M27.4 3.1A3.5 3.5 0 0 0 24.9.6C22.7 0 14 0 14 0S5.3 0 3.1.6A3.5 3.5 0 0 0 .6 3.1C0 5.3 0 10 0 10s0 4.7.6 6.9a3.5 3.5 0 0 0 2.5 2.5C5.3 20 14 20 14 20s8.7 0 10.9-.6a3.5 3.5 0 0 0 2.5-2.5c.6-2.2.6-6.9.6-6.9s0-4.7-.6-6.9Z"/><path fill="#FFF" d="m11.2 14.3 7.3-4.3-7.3-4.3v8.6Z"/></svg>
                    <span class="underline">{{ __('privacy.footer.youtube') }}</span>
                </a>
            </nav>
        </div>
    </footer>
    @include('partials.lightbox')
</body>
</html>
