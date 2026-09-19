<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'matplace' }}</title>
    <meta name="description" content="{{ $description ?? __('app.subline') }}">
    @if(!empty($ogImage))<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta property="og:title" content="{{ $title ?? 'matplace' }}">
    <link rel="icon" href="/favicon.ico">
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @stack('head')
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="text-xl font-extrabold tracking-tight text-teal-700">matplace</a>
            <nav class="flex items-center gap-4 text-sm text-slate-600">
                <a href="#tools" class="hidden sm:inline hover:text-slate-900">{{ __('footer.tools') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['lang' => app()->getLocale() === 'cs' ? 'en' : 'cs']) }}" class="hover:text-slate-900">{{ __('lang.switch') }}</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 pb-16 pt-6">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-center text-sm text-slate-500">
            {{ __('footer.promise') }}
        </div>
    </footer>
</body>
</html>
