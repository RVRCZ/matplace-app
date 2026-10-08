@extends('layouts.app', ['title' => __('gifts.title').' · matplace', 'description' => __('gifts.lead'), 'tool' => 'gifts'])

@php
    // occasion → the products that suit it; every link opens the sign tool with a preset and a sample text
    $occasions = [
        'christmas' => ['name', 'ornament', 'keyring', 'door'],
        'valentine' => ['name', 'keyring', 'ornament'],
        'school' => ['name', 'nametag', 'keyring'],
        'birthday' => ['name', 'door', 'keyring', 'nametag'],
    ];
    // gifts that have a tool of their own: a picture or a name in the colours of filaments (the ones that are in the catalogue)
    $more = array_values(array_filter(['gingerbread', 'name_letter', 'topper', 'keychain', 'ornament', 'charm', 'earrings', 'magnet', 'coaster', 'cookie', 'tray', 'name_cup', 'beads', 'badge', 'bag_charm', 'medallion', 'photo_organizer', 'nameplate', 'text'], fn ($key) => ! empty(config('tools.'.$key.'.available'))));
@endphp

@section('content')
<div class="mx-auto max-w-5xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-3xl font-extrabold text-ink">{{ __('gifts.title') }}</h1>
    <p class="hint max-w-3xl">{{ __('gifts.lead') }}</p>

    <div class="mt-5 grid items-center gap-5 rounded-2xl border border-line bg-card p-5 sm:grid-cols-[260px_1fr]">
        <div class="overflow-hidden rounded-2xl">@include('tools.picture', ['key' => 'sign', 'sizes' => '(min-width: 640px) 260px, 100vw', 'eager' => true])</div>
        <div>
            <h2 class="text-xl font-bold text-ink">{{ __('gifts.how.title') }}</h2>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-ink">
                @foreach(['text', 'look', 'price'] as $step)<li>{{ __('gifts.how.'.$step) }}</li>@endforeach
            </ol>
            <a href="{{ route('tools.sign') }}" class="btn-primary mt-4">{{ __('gifts.start') }}</a>
        </div>
    </div>

    @foreach($occasions as $occasion => $products)
        <section class="mt-8" aria-labelledby="gift-{{ $occasion }}">
            <h2 id="gift-{{ $occasion }}" class="text-xl font-bold text-ink">{{ __('gifts.occasion.'.$occasion) }}</h2>
            <p class="hint">{{ __('gifts.occasion.'.$occasion.'.text') }}</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($products as $product)
                    <a href="{{ route('tools.sign', ['preset' => $product, 'line1' => __('gifts.sample.'.$occasion.'.'.$product)]) }}" class="card flex flex-col p-4 hover:border-action">
                        <span class="text-lg font-bold text-ink">{{ __('param.preset.'.$product) }}</span>
                        <span class="mt-1 flex-1 text-sm text-muted">{{ __('gifts.product.'.$product) }}</span>
                        <span class="mt-2 rounded-lg bg-page px-3 py-2 text-center font-semibold text-ink">„{{ __('gifts.sample.'.$occasion.'.'.$product) }}“</span>
                        <span class="mt-3 font-semibold text-action-dark">{{ __('gifts.try') }} →</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

    @if($more)
        <section class="mt-8" aria-labelledby="gift-more">
            <h2 id="gift-more" class="text-xl font-bold text-ink">{{ __('tools.gifts.more') }}</h2>
            <p class="hint">{{ __('tools.gifts.more.text') }}</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($more as $key)
                    <a href="{{ \App\Support\ToolSeo::url($key) }}" class="card flex flex-col overflow-hidden hover:border-action">
                        <span class="block bg-studio">@include('tools.picture', ['key' => $key, 'sizes' => '(min-width: 1024px) 320px, (min-width: 640px) 50vw, 100vw'])</span>
                        <span class="flex flex-1 flex-col p-4">
                            <span class="text-lg font-bold text-ink">{{ __('tools.'.$key.'.title') }}</span>
                            <span class="mt-1 flex-1 text-sm text-muted">{{ __('tools.'.$key.'.hint') }}</span>
                            <span class="mt-3 font-semibold text-action-dark">{{ __('tools.'.$key.'.action') }} →</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <p class="mt-8 text-sm text-muted">{{ \App\Support\NextStep::text('gifts.after') }}</p>
</div>
@endsection
