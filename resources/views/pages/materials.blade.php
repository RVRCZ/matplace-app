@extends('layouts.app', ['title' => __('site.materials.title').' · matplace', 'description' => __('site.materials.description')])

@section('content')
<div class="mx-auto max-w-4xl">
    <h1 class="text-3xl font-extrabold tracking-tight text-ink">{{ __('site.materials.title') }}</h1>
    <p class="mt-2 max-w-2xl text-lg leading-relaxed text-slate-700">{{ __('site.materials.lead') }}</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach($materials as $code => $m)
            <article class="card p-5" id="{{ strtolower($code) }}">
                <h2 class="text-xl font-bold text-ink">{{ __('materials.'.$code.'.label') }}</h2>
                <p class="text-xs text-muted">{{ __('site.materials.technical', ['code' => $code]) }}</p>
                <p class="mt-2 text-slate-700">{{ __('materials.'.$code.'.hint') }}</p>
                <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('site.materials.heat') }}</dt><dd class="font-semibold">{{ $m['props']['heat'] }} °C</dd>
                    <dt class="text-muted">{{ __('site.materials.strength') }}</dt>
                    <dd class="font-semibold" aria-label="{{ $m['props']['strength'] }}/3">{{ str_repeat('●', $m['props']['strength']) }}{{ str_repeat('○', 3 - $m['props']['strength']) }} {{ __('site.materials.strength_levels.'.$m['props']['strength']) }}@if($m['props']['flexible']), {{ __('site.materials.flexible') }}@endif</dd>
                    <dt class="text-muted">{{ __('site.materials.outdoor') }}</dt><dd class="font-semibold">{{ __('compare.outdoor.'.$m['props']['outdoor']) }}</dd>
                    <dt class="text-muted">{{ __('site.materials.food') }}</dt><dd class="font-semibold">{{ __('compare.food.'.$m['props']['food']) }}</dd>
                    <dt class="text-muted">{{ __('site.materials.price') }}</dt>
                    <dd class="font-semibold">{{ $m['props']['price'] <= 1.001 ? __('site.materials.price_same') : __('site.materials.price_more', ['n' => (int) round(($m['props']['price'] - 1) * 100)]) }}</dd>
                </dl>
            </article>
        @endforeach
    </div>

    <section class="card mt-6 p-5">
        <h2 class="text-lg font-bold text-ink">{{ __('site.materials.now_title') }}</h2>
        <p class="mt-1 text-slate-700">{{ $loaded->isNotEmpty() ? __('site.materials.now_text') : __('site.materials.now_none') }}</p>
        @if($loaded->isNotEmpty())
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach($loaded as $kind)<li class="chip">{{ $kind }}</li>@endforeach
            </ul>
        @endif
        <p class="mt-4 text-sm text-muted">{{ __('site.materials.compare_hint') }}</p>
        <a href="{{ route('home') }}" class="btn-primary mt-3 text-sm">{{ __('site.materials.cta') }}</a>
    </section>
</div>
@endsection
