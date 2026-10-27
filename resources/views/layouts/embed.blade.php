{{-- The small page inside another site's iframe (EmbedController::calc): no header, no footer, no cookie bar and no
     third-party script; our own statistics run on the server. Links open matplace.com in a new tab. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'matplace' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <script>window.MP_MONEY = {{ \Illuminate\Support\Js::from(\App\Support\Currency::forScripts()) }};</script>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/embed.ts'])
    @stack('head')
</head>
<body class="min-h-full bg-page text-ink antialiased">
    @yield('content')
</body>
</html>
