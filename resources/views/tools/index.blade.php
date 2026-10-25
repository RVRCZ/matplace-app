@php
    // the spare-part inquiry needs printers to answer it: marketplace only, by the live switch (the admin toggles it at runtime)
    // what is on offer: the config and the admin's switch (/admin/tools) together
    $all = collect(\App\Domain\Tools\ToolVisibility::listed())->filter(fn ($t, $k) => \Illuminate\Support\Facades\Route::has($t['route']) && ($k !== 'figure' || $generator) && ($k !== 'spare' || config('features.marketplace')));
    $by = fn (string $intent) => $all->filter(fn ($t) => $t['intent'] === $intent);
    // the filter offers a category only when a listed tool is in it
    $cats = collect(['images', 'names', 'home', 'parts', 'toys', 'signs', 'craft', 'edit', 'sell'])->filter(fn ($c) => $all->contains(fn ($t) => in_array($c, $t['categories'], true)));
    $card = function (string $key, array $t) {
        return view('tools.card', ['key' => $key, 'tool' => $t, 'input' => true])->render();
    };
    // "our picks" (Roman, 17 Oct 2026, the audit point 8): chosen by hand until there are data on what people use;
    // the ones that are not on offer here are left out
    $picks = collect(['gifts', 'organizer', 'box', 'qr', 'figure'])->filter(fn ($k) => $all->has($k));
    // what the visitor comes for (config/tool_intents.php): a chip with the keys of its tools, only intents with something behind them
    $aims = \App\Domain\Tools\ToolIntents::all($all->all());
    $sections = ['file' => 'have-file', 'create' => 'create', 'spare' => 'spare'];
@endphp
{{-- the title and the description say what is here and for whom, with today's count of tools (docs/AN.md §7) --}}
@extends('layouts.app', ['title' => __('tools.seo.title').' · matplace', 'description' => __('tools.seo.description', ['n' => $all->count()])])

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
    @if($aims)
    {{-- the intents first (a gift, from a photo, the home, a spare part, for a business): one row, scrolling on a phone like the categories --}}
    <div class="-mx-4 mt-3 flex items-center gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0 lg:pb-0" role="group" aria-label="{{ __('tools.aims') }}">
        <span class="shrink-0 text-sm text-muted">{{ __('tools.aims') }}</span>
        @foreach($aims as $aim => $keys)
            <button type="button" class="chip inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center whitespace-nowrap" data-aim="{{ $aim }}" data-tools="{{ implode(' ', $keys) }}" aria-pressed="false">{{ __('tools.aim.'.$aim) }}</button>
        @endforeach
    </div>
    {{-- an intent with a page of its own (businesses): named while that intent is on --}}
    @if(isset($aims['business']) && \Illuminate\Support\Facades\Route::has('pages.business'))
        <p class="mt-2 text-sm" data-aim-page="business" hidden><a href="{{ route('pages.business') }}" class="text-action-dark underline">{{ __('tools.aim.business_page') }}</a></p>
    @endif
    @endif
    <div class="-mx-4 mt-2 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0 lg:pb-0" role="group" aria-label="{{ __('tools.filter') }}">
        <button type="button" class="chip chip-on inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center" data-filter="all" aria-pressed="true">{{ __('tools.cat.all') }}</button>
        @foreach($cats as $c)
            <button type="button" class="chip inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center whitespace-nowrap" data-filter="{{ $c }}" aria-pressed="false">{{ __('tools.cats.'.$c) }}</button>
        @endforeach
    </div>
    {{-- nothing found: say so, offer the way back and the tools the words come closest to (tools/index script) --}}
    <div id="tool-none" class="note-warn mt-4 hidden text-sm" role="status">
        <p>{{ __('tools.search.none') }}</p>
        <button type="button" id="tool-none-clear" class="btn-secondary mt-3 !min-h-11 !py-2 text-sm">{{ __('tools.search.clear') }}</button>
        <p class="mt-3 font-medium">{{ __('tools.search.related') }}</p>
        <ul id="tool-none-related" class="mt-1 flex flex-wrap gap-2"></ul>
    </div>

    @if($picks->isNotEmpty())
        <section id="tool-picks" class="mt-5" aria-labelledby="tool-picks-h" data-when-unfiltered>
            <h2 id="tool-picks-h" class="text-xl font-semibold text-ink">{{ __('tools.picks') }}</h2>
            <p class="hint">{{ __('tools.picks.note') }}</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($picks as $key)@include('tools.card_row', ['key' => $key, 'tool' => $all[$key], 'pick' => true])@endforeach
            </div>
        </section>
    @endif

    {{-- a phone: the categories folded, each with its count, the first one open; a row per tool (docs/AF.md §2).
         A tool is in every category it has, as the chips filter it; the search shows it once. --}}
    <div id="tool-cats" class="mt-4 lg:hidden">
        @foreach($cats as $c)
            @php $in = $all->filter(fn ($t) => in_array($c, $t['categories'], true)); @endphp
            <section data-tool-section data-cat="{{ $c }}" class="border-b border-line first:border-t" aria-labelledby="cat-{{ $c }}-h">
                <h2 id="cat-{{ $c }}-h">
                    <button type="button" data-cat-toggle="{{ $c }}" class="flex min-h-12 w-full items-center justify-between gap-3 py-3 text-left font-semibold text-ink" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="cat-{{ $c }}">
                        <span>{{ __('tools.cats.'.$c) }} <span class="font-normal text-muted">· <span data-cat-count data-total="{{ $in->count() }}">{{ $in->count() }}</span></span></span>
                        <x-icon name="chevron-down" class="h-5 w-5 text-muted transition-transform" />
                    </button>
                </h2>
                <div id="cat-{{ $c }}" class="grid gap-2 pb-4" @unless($loop->first) hidden @endunless>
                    @foreach($in as $key => $t)@include('tools.card_row', ['key' => $key, 'tool' => $t])@endforeach
                </div>
            </section>
        @endforeach
    </div>

    @php $entrances = collect($sections)->filter(fn ($id, $intent) => $by($intent)->isNotEmpty()); @endphp
    <div class="hidden lg:block">
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
</div>

<script>
(() => {
    // one filter for the whole page: a category (also as /tools#<category>) and the words typed into the search
    const plain = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    // words that mean the same here (jmenovka = cedulka = štítek, krabička = box…): a word typed finds the tools of all of them
    const same = {{ \Illuminate\Support\Js::from(collect((array) trans('tools.synonyms'))->map(fn ($g) => collect($g)->map(fn ($w) => \App\Domain\Farm\Palette::plain($w))->values())->values()) }};
    const alike = (w) => (w.length < 3 ? [w] : [w, ...same.filter((g) => g.some((s) => s.startsWith(w) || (s.length > 3 && w.startsWith(s)))).flat()]);
    const chips = [...document.querySelectorAll('[data-filter]')];
    const cards = [...document.querySelectorAll('[data-tool-section] [data-cats]')];
    // the words of a tool live on its big card only; a phone row looks them up by key (a third less HTML)
    const wordsOf = new Map(cards.filter((c) => c.dataset.words !== undefined).map((c) => [c.dataset.key, c.dataset.words]));
    const wordsIn = (c) => c.dataset.words ?? wordsOf.get(c.dataset.key) ?? '';
    // an intent (a gift, from a photo…) is a filter like a category, by the keys of its tools; one of the two is on at a time
    const aims = [...document.querySelectorAll('[data-aim]')];
    const aimTools = new Map(aims.map((b) => [b.dataset.aim, new Set(b.dataset.tools.split(' '))]));
    const search = document.getElementById('tool-search');
    let cat = 'all';
    let aim = null;
    // the folds of the phone: what the visitor opened stays open; a filter opens what it shows and gives the rest back after
    const folds = [...document.querySelectorAll('#tool-cats [data-cat]')];
    const opened = new Set(folds.slice(0, 1).map((s) => s.dataset.cat));
    const fold = (s, open) => {
        s.querySelector('[data-cat-toggle]').setAttribute('aria-expanded', open ? 'true' : 'false');
        s.querySelector('[data-cat-toggle] svg').classList.toggle('rotate-180', open);
        document.getElementById('cat-' + s.dataset.cat).hidden = !open;
    };
    folds.forEach((s) => fold(s, opened.has(s.dataset.cat)));
    folds.forEach((s) => s.querySelector('[data-cat-toggle]').addEventListener('click', () => {
        const open = s.querySelector('[data-cat-toggle]').getAttribute('aria-expanded') !== 'true';
        if (open) opened.add(s.dataset.cat); else opened.delete(s.dataset.cat);
        fold(s, open);
    }));
    const apply = () => {
        const words = plain(search.value).split(/\s+/).filter(Boolean);
        const filtered = cat !== 'all' || aim !== null || words.length > 0;
        let shown = 0;
        const once = new Set();      // a phone row of a tool already shown in an earlier category (while searching)
        const wanted = words.map(alike);
        cards.forEach((c) => {
            const text = wordsIn(c);
            let on = (cat === 'all' || c.dataset.cats.split(' ').includes(cat)) && (aim === null || aimTools.get(aim).has(c.dataset.key)) && wanted.every((alts) => alts.some((w) => text.includes(w)));
            const group = c.closest('[data-cat]');
            if (group && cat !== 'all' && group.dataset.cat !== cat) on = false;
            if (group && on && (words.length || aim !== null)) { if (once.has(c.dataset.key)) on = false; else once.add(c.dataset.key); }
            c.hidden = !on;
            if (on && !group) shown++;
        });
        document.querySelectorAll('[data-tool-section]').forEach((s) => { s.hidden = !s.querySelector('[data-cats]:not([hidden])'); });
        folds.forEach((s) => {
            const count = s.querySelector('[data-cat-count]');
            count.textContent = filtered ? s.querySelectorAll('[data-cats]:not([hidden])').length : count.dataset.total;
            fold(s, filtered || opened.has(s.dataset.cat));
        });
        document.querySelectorAll('[data-when-unfiltered]').forEach((n) => { n.hidden = filtered; });
        document.getElementById('tool-none').classList.toggle('hidden', shown > 0);
        if (shown === 0) related(wanted);
        chips.forEach((o) => { const on = o.dataset.filter === cat; o.classList.toggle('chip-on', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        aims.forEach((o) => { const on = o.dataset.aim === aim; o.classList.toggle('chip-on', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        document.querySelectorAll('[data-aim-page]').forEach((n) => { n.hidden = n.dataset.aimPage !== aim; });
    };
    // nothing found: the tools that match any one of the words (any category), else our picks
    const related = (wanted) => {
        const list = document.getElementById('tool-none-related');
        const big = cards.filter((c) => !c.closest('[data-cat]'));
        let near = big.filter((c) => wanted.some((alts) => alts.some((w) => w.length > 2 && wordsIn(c).includes(w))));
        if (!near.length) near = [...document.querySelectorAll('#tool-picks [data-key]')].map((p) => big.find((c) => c.dataset.key === p.dataset.key)).filter(Boolean);
        list.innerHTML = '';
        near.slice(0, 5).forEach((c) => {
            const a = document.createElement('a');
            a.href = c.getAttribute('href'); a.className = 'chip inline-flex min-h-11 items-center';
            a.textContent = c.dataset.title;
            const li = document.createElement('li'); li.appendChild(a); list.appendChild(li);
        });
    };
    // the category and the words live in the address (#names, ?q=box): the step back from a tool finds the list as it was left
    const remember = () => {
        const q = search.value.trim();
        history.replaceState(null, '', location.pathname + (q ? '?q=' + encodeURIComponent(q) : '') + (aim !== null ? '#' + aim : cat === 'all' ? '' : '#' + cat));
    };
    // a category or an intent, never both: the chip pressed again gives the whole list back
    const pick = (to, keep = true) => {
        if (aimTools.has(to)) { aim = aim === to ? null : to; cat = 'all'; }
        else { cat = chips.some((o) => o.dataset.filter === to) ? to : 'all'; aim = null; }
        if (keep) remember();
        apply();
    };
    chips.forEach((b) => b.addEventListener('click', () => pick(b.dataset.filter)));
    aims.forEach((b) => b.addEventListener('click', () => pick(b.dataset.aim)));
    search.addEventListener('input', () => { apply(); remember(); });
    document.getElementById('tool-none-clear').addEventListener('click', () => { search.value = ''; pick('all'); search.focus(); });
    const asked = new URLSearchParams(location.search).get('q');
    if (asked) { search.value = asked.slice(0, 80); apply(); }
    const fromHash = () => { const h = location.hash.slice(1); if (chips.some((o) => o.dataset.filter === h) || aimTools.has(h)) pick(h, false); };
    window.addEventListener('hashchange', fromHash);
    fromHash();
})();
</script>
@endsection
