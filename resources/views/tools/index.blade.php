@extends('layouts.app', ['title' => __('tools.title').' · matplace'])

@php
    // the spare-part inquiry needs printers to answer it: marketplace only, by the live switch (the admin toggles it at runtime)
    $all = collect(config('tools'))->filter(fn ($t, $k) => $t['available'] && \Illuminate\Support\Facades\Route::has($t['route']) && ($k !== 'figure' || $generator) && ($k !== 'spare' || config('features.marketplace')));
    $by = fn (string $intent) => $all->filter(fn ($t) => $t['intent'] === $intent);
    // the filter offers a category only when a listed tool is in it
    $cats = collect(['images', 'names', 'home', 'parts', 'toys', 'signs', 'craft', 'edit', 'sell'])->filter(fn ($c) => $all->contains(fn ($t) => in_array($c, $t['categories'], true)));
    $card = function (string $key, array $t) {
        return view('tools.card', ['key' => $key, 'tool' => $t])->render();
    };
    $sections = ['file' => 'have-file', 'create' => 'create', 'spare' => 'spare'];
@endphp

@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="text-3xl font-semibold text-ink">{{ __('tools.title') }}</h1>
    <p class="hint">{{ __('tools.lead') }}</p>

    {{-- find a tool by what it makes: the name, the sentence under it and its keywords, in the visitor's language --}}
    <div class="relative mt-4">
        <label for="tool-search" class="sr-only">{{ __('tools.search') }}</label>
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" />
        <input id="tool-search" type="search" class="field !mt-0 !pl-10" placeholder="{{ __('tools.search') }} · {{ __('tools.search.hint') }}" autocomplete="off">
    </div>
    <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="{{ __('tools.filter') }}">
        <button type="button" class="chip chip-on" data-filter="all" aria-pressed="true">{{ __('tools.cat.all') }}</button>
        @foreach($cats as $c)
            <button type="button" class="chip" data-filter="{{ $c }}" aria-pressed="false">{{ __('tools.cats.'.$c) }}</button>
        @endforeach
    </div>
    <p id="tool-none" class="note-warn mt-4 hidden text-sm" role="status">{{ __('tools.search.none') }}</p>

    @php $entrances = collect($sections)->filter(fn ($id, $intent) => $by($intent)->isNotEmpty()); @endphp
    <nav aria-label="{{ __('tools.intents') }}" class="mt-5 grid gap-2 {{ $entrances->count() === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}" data-when-unfiltered>
        @foreach($entrances as $intent => $id)
            <a href="#{{ $id }}" class="card flex items-center gap-3 p-4 transition hover:border-ink">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-ink" aria-hidden="true"><x-icon :name="['file' => 'file-box', 'create' => 'shapes', 'spare' => 'wrench'][$intent]" class="h-5 w-5" /></span>
                <span><span class="block font-semibold text-ink">{{ __('tools.intent.'.$intent) }}</span><span class="block text-sm text-muted">{{ __('tools.intent.'.$intent.'.hint') }}</span></span>
            </a>
        @endforeach
    </nav>

    @foreach($entrances as $intent => $id)
        <section id="{{ $id }}" class="mt-8 scroll-mt-4" aria-labelledby="h-{{ $intent }}" data-tool-section>
            <h2 id="h-{{ $intent }}" class="text-xl font-semibold text-ink">{{ __('tools.intent.'.$intent) }}</h2>
            @if($intent === 'create')<p class="hint">{{ \App\Support\NextStep::text('tools.create.lead') }}</p>@endif
            <div @if($intent === 'create') id="tool-grid" @endif class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($by($intent) as $key => $t){!! $card($key, $t) !!}@endforeach
            </div>
        </section>
    @endforeach
</div>

<script>
(() => {
    // one filter for the whole page: a category (also as /tools#<category>) and the words typed into the search
    const plain = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const chips = [...document.querySelectorAll('[data-filter]')];
    const cards = [...document.querySelectorAll('[data-tool-section] [data-cats]')];
    const search = document.getElementById('tool-search');
    let cat = 'all';
    const apply = () => {
        const words = plain(search.value).split(/\s+/).filter(Boolean);
        let shown = 0;
        cards.forEach((c) => {
            const on = (cat === 'all' || c.dataset.cats.split(' ').includes(cat)) && words.every((w) => c.dataset.words.includes(w));
            c.hidden = !on;
            if (on) shown++;
        });
        document.querySelectorAll('[data-tool-section]').forEach((s) => { s.hidden = !s.querySelector('[data-cats]:not([hidden])'); });
        document.querySelectorAll('[data-when-unfiltered]').forEach((n) => { n.hidden = cat !== 'all' || words.length > 0; });
        document.getElementById('tool-none').classList.toggle('hidden', shown > 0);
        chips.forEach((o) => { const on = o.dataset.filter === cat; o.classList.toggle('chip-on', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    };
    const pick = (to, remember = true) => {
        cat = chips.some((o) => o.dataset.filter === to) ? to : 'all';
        if (remember) history.replaceState(null, '', cat === 'all' ? location.pathname + location.search : '#' + cat);
        apply();
    };
    chips.forEach((b) => b.addEventListener('click', () => pick(b.dataset.filter)));
    search.addEventListener('input', apply);
    const fromHash = () => { const h = location.hash.slice(1); if (chips.some((o) => o.dataset.filter === h)) pick(h, false); };
    window.addEventListener('hashchange', fromHash);
    fromHash();
})();
</script>
@endsection
