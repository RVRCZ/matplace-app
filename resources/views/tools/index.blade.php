@extends('layouts.app', ['title' => __('tools.title').' · matplace'])

@php
    // the spare-part inquiry needs printers to answer it: marketplace only, by the live switch (the admin toggles it at runtime)
    $all = collect(config('tools'))->filter(fn ($t, $k) => $t['available'] && \Illuminate\Support\Facades\Route::has($t['route']) && ($k !== 'figure' || $generator) && ($k !== 'spare' || config('features.marketplace')));
    $by = fn (string $intent) => $all->filter(fn ($t) => $t['intent'] === $intent);
    $cats = ['gifts', 'home', 'signs', 'craft'];
    $card = function (string $key, array $t) {
        return view('tools.card', ['key' => $key, 'tool' => $t])->render();
    };
@endphp

@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="text-3xl font-extrabold text-ink">{{ __('tools.title') }}</h1>
    <p class="hint">{{ __('tools.lead') }}</p>

    @php $entrances = collect(['file' => '#have-file', 'create' => '#create', 'spare' => '#spare'])->filter(fn ($href, $intent) => $by($intent)->isNotEmpty()); @endphp
    <nav aria-label="{{ __('tools.intents') }}" class="mt-4 grid gap-2 {{ $entrances->count() === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
        @foreach($entrances as $intent => $href)
            <a href="{{ $href }}" class="card flex items-center gap-3 p-4 hover:border-action">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-action-soft text-lg font-bold text-action-dark" aria-hidden="true">{{ ['file' => '1', 'create' => '2', 'spare' => '3'][$intent] }}</span>
                <span><span class="block font-bold text-ink">{{ __('tools.intent.'.$intent) }}</span><span class="block text-sm text-muted">{{ __('tools.intent.'.$intent.'.hint') }}</span></span>
            </a>
        @endforeach
    </nav>

    <section id="have-file" class="mt-8 scroll-mt-4" aria-labelledby="h-file">
        <h2 id="h-file" class="text-xl font-bold text-ink">{{ __('tools.intent.file') }}</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($by('file') as $key => $t){!! $card($key, $t) !!}@endforeach
        </div>
    </section>

    <section id="create" class="mt-8 scroll-mt-4" aria-labelledby="h-create">
        <h2 id="h-create" class="text-xl font-bold text-ink">{{ __('tools.intent.create') }}</h2>
        <p class="hint">{{ \App\Support\NextStep::text('tools.create.lead') }}</p>
        <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="{{ __('tools.filter') }}">
            <button type="button" class="chip chip-on" data-filter="all" aria-pressed="true">{{ __('tools.cat.all') }}</button>
            @foreach($cats as $c)
                @continue($by('create')->filter(fn ($t) => in_array($c, $t['categories'], true))->isEmpty())
                <button type="button" class="chip" data-filter="{{ $c }}" aria-pressed="false">{{ __('tools.cat.'.$c) }}</button>
            @endforeach
        </div>
        <div id="tool-grid" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($by('create') as $key => $t){!! $card($key, $t) !!}@endforeach
        </div>
    </section>

    @if($by('spare')->isNotEmpty())
    <section id="spare" class="mt-8 scroll-mt-4" aria-labelledby="h-spare">
        <h2 id="h-spare" class="text-xl font-bold text-ink">{{ __('tools.intent.spare') }}</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($by('spare') as $key => $t){!! $card($key, $t) !!}@endforeach
        </div>
    </section>
    @endif

    @if(config('features.marketplace'))
    <section class="mt-10 rounded-2xl border border-line bg-card p-5" aria-labelledby="h-printers">
        <div class="grid items-center gap-5 sm:grid-cols-[220px_1fr_auto]">
            <div class="overflow-hidden rounded-2xl">@include('tools.picture', ['key' => 'printer_tools', 'sizes' => '(min-width: 640px) 220px, 100vw'])</div>
            <div>
                <h2 id="h-printers" class="text-xl font-bold text-ink">{{ __('tools.printers.title') }}</h2>
                <p class="hint">{{ __('tools.printers.lead') }}</p>
            </div>
            @auth
                @if(auth()->user()->isPrinter())<a href="{{ route('printer.dashboard') }}" class="btn-secondary">{{ __('tools.printers.open') }}</a>
                @else<a href="{{ route('account') }}" class="btn-secondary">{{ __('tools.printers.enable') }}</a>@endif
            @else
                <a href="{{ route('register') }}" class="btn-secondary">{{ __('tools.printers.join') }}</a>
            @endauth
        </div>
    </section>
    @endif
</div>

<script>
document.querySelectorAll('[data-filter]').forEach((b) => b.addEventListener('click', () => {
    const f = b.dataset.filter;
    document.querySelectorAll('[data-filter]').forEach((o) => { const on = o === b; o.classList.toggle('chip-on', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    document.querySelectorAll('#tool-grid [data-cats]').forEach((c) => { c.hidden = f !== 'all' && !c.dataset.cats.split(' ').includes(f); });
}));
</script>
@endsection
