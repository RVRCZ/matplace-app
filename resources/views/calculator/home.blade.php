{{--
    Homepage for customers. The first screen asks "Co chcete vyrobit?" (docs/AN.md §23, the router of §22 behind it):
    words first, a photo as an addition, a 3D file as a small link → the assistant says which tool makes it, whether
    a person has to look at it, or the calculator takes the file.
    Under it the three ways it can go, the gifts made from a photo, the farm's photographed prints, the file's own
    entrance (the calculator's drop zone, which its scripts need on the page), then the page as it was: the five ways in,
    the tools people use most, the examples and the three steps. Everything here hides once a model is open.
--}}
@php
    // without the marketplace the copy speaks about downloading or printing on the farm, never about other printers
    $mp = (bool) config('features.marketplace');
    // a closed farm (App\Domain\Farm\FarmState, docs/AL.md §23.5) takes no orders from this visitor: the page then names
    // no price of a print, no order and no delivery. "<key>.closed" is the wording without them; titles, the meta
    // description and the h1 stay as they are in every state
    $closed = \App\Domain\Farm\FarmState::isClosed() && ! \App\Domain\Farm\FarmGate::offered(auth()->user());
    $cx = fn (string $key, array $replace = []) => __($closed && \Illuminate\Support\Facades\Lang::has($key.'.closed') ? $key.'.closed' : $key, $replace);
    $tx = fn (string $key) => $cx($mp ? $key : 'home.farm.'.$key);
    // the spare-part inquiry is a tool of the catalogue too: no tile where its page would not open (/admin/tools)
    $spare = $mp && \App\Domain\Tools\ToolVisibility::canOpen(auth()->user(), 'spare');
    // the router: the farm's numbers (the reply time, the Christmas deadline), the gift cards, the photographed prints
    $hours = \App\Domain\Intake\IntakePage::hours();
    $christmas = \App\Domain\Intake\IntakePage::christmas();
    $gifts = \App\Domain\Intake\IntakePage::gifts(auth()->user(), (bool) ($config['generator'] ?? false));
    $prints = \App\Domain\Intake\IntakePage::prints(3);
    // the examples under the field: the season's and the steady ones (config/home.php, /admin/intake/hints)
    $hints = \App\Domain\Intake\IntakeHints::chips();
    $pickup = in_array('pickup', (array) app(\App\Domain\Farm\FarmSettings::class)->get('delivery_modes'), true);
    $mainFormats = strtoupper(implode(', ', array_intersect(['stl', '3mf', 'step', 'obj'], $config['formats']))) ?: $formats;
    $routerTexts = collect(['router.kind.photo', 'router.kind.file', 'router.need_input', 'router.error', 'router.too_many', 'router.sending', 'router.submit.hint', 'router.assistant',
        'router.tag.tool', 'router.tag.human', 'router.tag.shape', 'router.tag.file', 'router.act.open_tool', 'router.act.open_tool_text', 'router.act.rather_human', 'router.act.open_calc',
        'router.act.send_request', 'router.act.sending', 'router.act.without_designer', 'router.act.rerouting', 'router.questions', 'router.request.title', 'router.request.lead', 'router.request.consent', 'router.request.sent',
        'router.request.need_consent', 'router.request.need_email', 'router.request.failed', 'router.tag.held', 'router.tag.price', 'router.tag.design', 'router.example_nudge',
        'router.mic', 'router.mic.on', 'router.mic.denied'])
        ->mapWithKeys(fn ($k) => [$k => __($k, ['hours' => ':hours', 'email' => ':email', 'tool' => ':tool'])])->all()
        + ['photo.too_big' => __('photo.too_big', ['mb' => ':mb']), 'auth.email' => __('auth.email')];
    // the script writes this sentence back under the button: the same one the page shows
    $routerTexts['router.submit.hint'] = $cx('router.submit.hint');
    $eyebrow = 'text-xs font-bold uppercase tracking-[0.14em] text-action';
    $h2 = 'mt-2 text-2xl font-extrabold tracking-tight text-ink sm:text-3xl';
@endphp

<section id="hero">
    {{-- ── the first screen (docs/AN.md §23.1): the question, the field for words, the examples, the one action; a photo is an addition, a 3D file a small link ── --}}
    <div class="mx-auto max-w-2xl pt-1 sm:pt-6 lg:pt-10" id="router">
        @php $titleTag = empty($initial) ? 'h1' : 'p'; @endphp{{-- a calculation (/c/<code>) has the model's name as its h1 --}}
        <{{ $titleTag }} class="break-words text-center text-[1.85rem] font-extrabold leading-[1.1] tracking-tight text-ink sm:text-5xl">{{ __('router.title') }}</{{ $titleTag }}>

        <form id="intake-form" class="mt-3 grid gap-3 sm:mt-6" novalidate enctype="multipart/form-data" method="post" action="{{ route('intake.store') }}"
              data-url="{{ route('intake.store') }}" data-event-url="{{ route('intake.event') }}" data-max-mb="{{ \App\Domain\Intake\IntakeService::PHOTO_MB }}" data-i18n="{{ json_encode($routerTexts, JSON_UNESCAPED_UNICODE) }}">
            @csrf
            {{-- against advertising (IntakeSpam, §23.2): the stamp of this page, and a field no person sees or fills --}}
            <input type="hidden" name="{{ \App\Domain\Intake\IntakeSpam::STAMP_FIELD }}" id="intake-started" value="{{ \App\Domain\Intake\IntakeSpam::stamp() }}">
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label>Topic <input type="text" name="{{ \App\Domain\Intake\IntakeSpam::honeypot() }}" id="intake-trap" tabindex="-1" autocomplete="off" value=""></label>
            </div>

            {{-- the field first; the photo and the dictation sit in its own bottom row --}}
            <div id="intake-box" class="rounded-2xl border-2 border-ink/60 bg-card shadow-sm transition focus-within:border-action">
                <label class="sr-only" for="intake-text">{{ __('router.text_label') }}</label>
                <textarea id="intake-text" name="text" maxlength="{{ \App\Domain\Intake\IntakeService::TEXT_MAX }}" rows="3" placeholder="{{ __('router.text_placeholder') }}"
                          class="block min-h-[9.5rem] w-full resize-none sm:min-h-[7.5rem] rounded-t-2xl border-0 bg-transparent px-4 pb-1 pt-3 text-lg leading-relaxed text-ink placeholder:text-slate-400 focus:outline-none focus:ring-0"></textarea>
                <div id="intake-preview" class="mx-3 mb-2 hidden items-center gap-3 rounded-xl bg-studio p-2 text-left">
                    <img id="intake-thumb" src="" alt="" class="hidden h-14 w-14 shrink-0 rounded-lg border border-line object-cover">
                    <div class="min-w-0 flex-1">
                        <div id="intake-file-name" class="truncate text-sm font-semibold text-ink"></div>
                        <div id="intake-file-kind" class="text-xs text-muted"></div>
                    </div>
                    <button type="button" id="intake-clear" class="inline-flex min-h-11 items-center px-2 text-sm text-muted underline hover:text-ink">{{ __('router.remove') }}</button>
                </div>
                <div class="flex items-center gap-1 border-t border-line px-2 py-1.5" id="intake-tools">
                    {{-- no `capture`: a phone then offers the camera and the gallery both; with it Android and iOS open the camera alone --}}
                    <label for="intake-photo" id="intake-photo-btn" title="{{ __('router.photo_title') }}" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl px-3 text-sm font-semibold text-ink hover:bg-studio"><x-icon name="camera" class="h-5 w-5" />{{ __('router.photo_button') }}</label>
                    {{-- dictation: shown by the script only where the browser can do it (SpeechRecognition) --}}
                    <button type="button" id="intake-mic" aria-pressed="false" class="hidden min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-ink hover:bg-studio">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/></svg>
                        <span data-mic-label>{{ __('router.mic') }}</span>
                    </button>
                    <input id="intake-photo" name="photo" type="file" accept="image/*,.heic,.heif" class="sr-only">
                    <input id="intake-model" name="model" type="file" accept="{{ implode(',', array_map(fn ($f) => '.'.$f, $config['formats'])) }}" class="sr-only">
                </div>
            </div>

            {{-- the examples (IntakeHints, §23.3): the season's first, then the steady ones; a click writes the wish into the field, nothing is sent.
                 On a phone two rows that scroll sideways, so the action stays on the first screen --}}
            <div id="intake-hints" class="-mx-4 grid auto-cols-max grid-flow-col grid-rows-2 gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex sm:flex-wrap sm:justify-center sm:overflow-visible sm:px-0" aria-label="{{ __('router.examples') }}">
                @foreach($hints as $chip)
                    <button type="button" class="chip min-h-11 whitespace-nowrap hover:border-action hover:text-action-dark {{ $chip['seasonal'] ? 'border-action/70' : '' }}" data-hint="{{ $chip['code'] }}" data-fill="{{ $chip['fill'] }}" @if($chip['seasonal']) data-seasonal @endif>{{ $chip['label'] }}</button>
                @endforeach
            </div>

            <div class="flex flex-col items-center gap-1 text-center">
                {{-- the one orange action of the screen: at least 60 px tall, the whole width on a phone --}}
                <button type="submit" id="intake-submit" class="btn-primary min-h-[60px] w-full rounded-2xl px-8 text-lg shadow-sm sm:w-auto sm:min-w-[320px]">{{ __('router.submit') }}</button>
                <label for="intake-model" id="intake-file-link" class="inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-muted underline underline-offset-4 hover:text-ink">{{ __('router.file_link') }}</label>
                <p id="intake-hint" class="max-w-md text-sm text-muted" aria-live="polite">{{ $cx('router.submit.hint') }}</p>
            </div>
            <p id="intake-error" class="note-error hidden text-sm" role="alert"></p>
            <div id="intake-assist" class="hidden border-t border-line pt-4" aria-live="polite"></div>
        </form>

        <ul class="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1.5 text-sm text-muted" aria-label="{{ __('router.trust.preview') }}">
            @foreach(array_filter(['preview' => $closed ? null : __('router.trust.preview'), 'no_signup' => __('router.trust.no_signup'), 'christmas' => $christmas ? __('router.trust.christmas', ['date' => $christmas]) : null]) as $k => $line)
                <li class="flex items-center gap-1.5" data-trust="{{ $k }}"><svg class="h-3.5 w-3.5 shrink-0 text-ok" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5l4 4 8-9"/></svg>{{ $line }}</li>
            @endforeach
        </ul>
        {{-- a paused farm says so here, with the day printing starts again; a closed one that it takes no orders --}}
        @include('farm._pause', ['class' => 'mt-3 text-center'])
        @if($closed)
            <p data-farm-closed class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-center text-sm text-amber-900" role="note">{{ __('farm.state.closed.note') }}</p>
        @endif
        <p class="mt-2 text-center text-xs text-muted">{{ __('router.privacy') }}</p>
    </div>

    {{-- ── what happens after sending: the three ways it can go (each ends with a price and an order: not while the farm is closed) ── --}}
    @if(! $closed)
    <section class="mt-14" aria-labelledby="router-paths-title" id="router-paths">
        <p class="{{ $eyebrow }}">{{ __('router.paths.eyebrow') }}</p>
        <h2 id="router-paths-title" class="{{ $h2 }}">{{ __('router.paths.title') }}</h2>
        <p class="mt-1 max-w-3xl text-muted">{{ __('router.paths.lead') }}</p>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            @foreach(['tool' => 'camera', 'shape' => 'box', 'human' => 'wrench'] as $path => $ico)
                <article class="card p-5" data-path="{{ $path }}">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-action-soft text-action-dark" aria-hidden="true"><x-icon :name="$ico" class="h-5 w-5" /></span>
                    <h3 class="mt-3 text-lg font-bold text-ink">{{ __('router.paths.'.$path.'.title') }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-muted">{{ __('router.paths.'.$path.'.text', ['hours' => $hours]) }}</p>
                    <p class="mt-3 rounded-lg bg-page px-3 py-2 text-sm text-ink">{{ __('router.paths.'.$path.'.example', ['hours' => $hours]) }}</p>
                </article>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ── the gifts made from a photo, a drawing or a name ── --}}
    @if($gifts)
    <section class="mt-14" aria-labelledby="router-gifts-title" id="router-gifts">
        <p class="{{ $eyebrow }}">{{ __('router.gifts.eyebrow') }}</p>
        <h2 id="router-gifts-title" class="{{ $h2 }}">{{ __('router.gifts.title') }}</h2>
        <p class="mt-1 max-w-3xl text-muted">{{ $cx('router.gifts.lead') }}</p>
        <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            @foreach($gifts as $gift)
                <a href="{{ $gift['url'] }}" data-gift="{{ $gift['key'] }}" class="card group flex flex-col overflow-hidden transition hover:border-ink">
                    <span class="block overflow-hidden bg-studio">@include('tools.picture', ['key' => $gift['key'], 'sizes' => '(min-width: 1024px) 270px, (min-width: 640px) 45vw, 50vw'])</span>
                    <span class="flex flex-1 flex-col p-3">
                        <span class="font-semibold leading-tight text-ink group-hover:underline">{{ $gift['title'] }}</span>
                        {{-- "from": the cheapest real calculation of the farm for the tool (config/site.php), nothing made up; without one the card says no price --}}
                        @if($gift['from'] && ! $closed)<span class="mt-1 text-sm text-muted">{{ __('router.gifts.from', ['price' => $gift['from']]) }}</span>@endif
                    </span>
                </a>
            @endforeach
        </div>
        @if($christmas)
            <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-studio px-4 py-3 text-sm" data-deadline>
                <b class="font-semibold text-ink">{{ __('router.deadline', ['date' => $christmas]) }}</b>
                <span class="text-muted">{{ __('router.deadline.note') }}@if($pickup) {{ __('router.deadline.pickup') }}@endif</span>
            </div>
        @endif
    </section>
    @endif

    {{-- ── the farm's photographed prints of these gifts (App\Domain\Tools\ToolPhotos): nothing until there are photos ── --}}
    @if($prints && ! $closed)
    <section class="mt-14" aria-labelledby="router-prints-title" id="router-prints">
        <p class="{{ $eyebrow }}">{{ __('router.prints.eyebrow') }}</p>
        <h2 id="router-prints-title" class="{{ $h2 }}">{{ __('router.prints.title') }}</h2>
        <ul class="mt-5 grid grid-cols-3 gap-3">
            @foreach($prints as $p)
                @php $when = $p['at'] ? \Carbon\Carbon::parse($p['at'])->format(app()->getLocale() === 'cs' ? 'j. n. Y' : 'j M Y') : null; @endphp
                <li class="card overflow-hidden">
                    <picture>
                        @if(! empty($p['webp']))<source type="image/webp" srcset="{{ $p['webp'] }}">@endif
                        <img src="{{ $p['url'] }}" alt="{{ trim(__('tools.photos.alt', ['tool' => __('tools.'.$p['tool'].'.title'), 'material' => $p['material'] ?? '', 'date' => $when ?? ''])) }}" width="600" height="450" loading="lazy" decoding="async" class="aspect-[4/3] w-full bg-studio object-cover">
                    </picture>
                    <p class="px-3 py-2 text-xs text-muted">{{ implode(' · ', array_filter([__('tools.'.$p['tool'].'.title'), $p['material'], $when])) }}</p>
                </li>
            @endforeach
        </ul>
    </section>
    @endif

    {{-- ── a model of one's own: the calculator's entrance (its scripts bind to the ids inside: file-input, dropzone, search-form) ── --}}
    <section class="card mt-14 p-4 sm:p-6" aria-labelledby="router-file-title" id="router-file">
        <p class="{{ $eyebrow }}">{{ __('router.file.eyebrow') }}</p>
        <h2 id="router-file-title" class="{{ $h2 }}">{{ $cx('router.file.title') }}</h2>
        <p class="mt-1 max-w-3xl text-muted">{{ $cx('router.file.lead') }}</p>
        <div class="mt-4">
            @include('calculator.inputs', ['centered' => false, 'fileOnly' => true])
        </div>
        <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted">
            {{-- "from 99 Kč": the cheapest of the priced examples (config site.example_items), only in crowns (audit 26 Oct 2026, D15) --}}
            @php $from = \App\Support\Currency::current() === \App\Support\Money::CZK ? collect((array) config('site.example_items'))->map(fn ($i) => $i['price']['CZK'] ?? null)->filter()->min() : null; @endphp
            @foreach($mp ? ['real_prices', 'no_signup', 'share'] : array_filter(['free_tools', 'download', $closed ? null : ($from ? 'farm_from' : 'farm')]) as $b)
                <li class="flex items-center gap-1.5"><svg class="h-3.5 w-3.5 text-action" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5l4 4 8-9"/></svg>{{ $b === 'free_tools' ? trans_choice('home.benefit.free_tools_n', $n = (int) config('ai.daily_limits.generate_guest'), ['n' => $n]) : __('home.benefit.'.$b, ['price' => $from ? \App\Support\Money::of((float) $from, \App\Support\Money::CZK)->format() : '']) }}</li>
            @endforeach
        </ul>
    </section>

    @include('calculator.search_results')

    {{-- five ways in, each one a real action (TODO #45): a file, a photo or an idea, a gift, a part, a business --}}
    {{-- a spare part is an inquiry to printers with the marketplace; without it the part leads to the part tools --}}
    @php
        $toolsUrl = route('tools');
        $wrench = 'M14.7 6.3a4 4 0 00-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 005.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z';
        $ways = array_filter([
            ['file', 'M7 3h7l5 5v13H7zM14 3v5h5', '#', 'file'],
            ['idea', 'M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2z', '#', 'idea'],
            \Illuminate\Support\Facades\Route::has('tools.gifts') ? ['gift', 'M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z', route('tools.gifts'), null] : null,
            $spare ? ['spare', $wrench, route('tools.spare'), null] : ['part', $wrench, $toolsUrl.'#parts', null],
            ['firm', 'M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16', \Illuminate\Support\Facades\Route::has('tools.business') ? route('tools.business') : $toolsUrl.'#signs', null],
        ]);
    @endphp
    <nav aria-label="{{ __('tools.intents') }}" class="mt-10 grid divide-y divide-line border-y border-line lg:grid-cols-5 lg:divide-x lg:divide-y-0" data-ways>
        @foreach($ways as [$k, $path, $href, $tile])
            {{-- a way that only runs a script (open the file field, open the search) is a button, not a link to "#" (audit 26 Oct 2026, A3) --}}
            @php $tag = $href === '#' ? 'button' : 'a'; @endphp
            <{{ $tag }} @if($tag === 'a') href="{{ $href }}" @else type="button" @endif data-way="{{ $k }}" @if($tile) data-tile="{{ $tile }}" @endif @if($k === 'file') onclick="document.getElementById('file-input').click();return false;" @endif
               class="group flex w-full items-start gap-3 px-1 py-4 text-left sm:px-5 lg:first:pl-0 lg:last:pr-0">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-action-soft text-action-dark" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg></span>
                <span class="min-w-0 flex-1"><span class="block font-bold text-ink">{{ __('home.start.'.$k) }}</span><span class="block text-sm text-muted">{{ $cx('home.start.'.$k.'.hint') }}</span></span>
                <span class="mt-1 text-ink transition group-hover:translate-x-0.5" aria-hidden="true">→</span>
            </{{ $tag }}>
        @endforeach
    </nav>

    {{-- the tools people use most: config/home.php and the admin's switches (ToolVisibility::onHome) --}}
    @php
        $homeTools = collect(\App\Domain\Tools\ToolVisibility::onHome())
            ->mapWithKeys(fn ($key) => [$key => config('tools.'.$key)])
            ->filter(fn ($t, $key) => ! in_array($key, ['figure', 'pet'], true) || ($config['generator'] ?? false))
            ->take((int) config('home.tools_shown', 8));
    @endphp
    <section class="mt-14" aria-labelledby="home-tools">
        <p class="text-xs font-bold uppercase tracking-[0.14em] text-action">{{ __('home.tools.kicker') }}</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div><h2 id="home-tools" class="text-3xl font-extrabold tracking-tight text-ink">{{ __('home.tools.title') }}</h2><p class="mt-1 text-muted">{{ __('home.tools.lead') }}</p></div>
            <a href="{{ route('tools') }}" class="inline-flex min-h-11 items-center font-semibold text-ink underline-offset-4 hover:underline">{{ __('home.tools.all') }} →</a>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($homeTools as $key => $tool)
                @include('tools.card', ['key' => $key, 'tool' => $tool, 'badge' => in_array($key, (array) config('home.popular'), true) ? __('home.tools.popular') : null])
            @endforeach
        </div>
        <p class="mt-6 text-center"><a href="{{ route('tools') }}" class="btn-secondary">{{ __('home.tools.all_count', ['n' => count(\App\Domain\Tools\ToolVisibility::listed())]) }} →</a></p>
    </section>

    {{-- prints with their price: off until the photos of the prints come (config/site.php) --}}
    @if(config('site.examples'))
        @include('calculator._examples')
    @endif

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
