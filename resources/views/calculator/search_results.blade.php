        <section id="search-section" class="mt-8 hidden text-left">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-bold">{{ __('search.results_title') }} <span id="search-query" class="font-normal text-slate-500"></span></h2>
            </div>
            <p id="search-hint" class="hidden text-sm text-slate-500">{{ __('search.results_hint') }}</p>
            <div id="search-results" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"></div>
        </section>
