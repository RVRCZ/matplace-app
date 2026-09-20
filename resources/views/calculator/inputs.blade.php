{{-- The one working entrance: drop a file, take a photo or describe it. Ids are used by calculator.ts and search.ts. --}}
        <label id="dropzone" class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-action bg-white px-4 py-8 text-center transition hover:border-action hover:bg-action-soft">
            <svg class="h-10 w-10 text-action" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            <span class="mt-3 text-lg font-semibold">{{ __('hero.drop') }}</span>
            <span class="mt-1 text-sm text-slate-500">{{ __('hero.formats', ['formats' => $formats, 'max' => $config['max_upload_mb']]) }}</span>
            <input id="file-input" type="file" class="sr-only" accept="{{ implode(',', array_map(fn ($f) => '.' . $f, $config['formats'])) }}">
        </label>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <button type="button" class="rounded-full bg-action px-4 py-2 font-semibold text-white" onclick="document.getElementById('file-input').click()">{{ __('hero.choose_file') }}</button>
            @if($mode !== 'printer')
                @if($config['vision'])
                    <button type="button" id="hero-photo-btn" class="rounded-full border border-action bg-white px-4 py-2 font-semibold text-action-dark">📷 {{ __('hero.photo') }}</button>
                    <input id="photo-input" type="file" accept="image/*" capture="environment" class="sr-only">
                @endif
                <button type="button" id="hero-text-btn" class="rounded-full border border-action bg-white px-4 py-2 font-semibold text-action-dark">✍️ {{ __('hero.text') }}</button>
            @endif
        </div>
        <form id="search-form" class="mt-3 hidden gap-2 sm:flex">
            <input id="search-input" type="search" maxlength="200" placeholder="{{ __('search.placeholder') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
            <button class="mt-2 rounded-xl bg-action px-5 py-3 font-semibold text-white sm:mt-0">{{ __('search.button') }}</button>
        </form>
        <div id="search-busy" class="mt-3 hidden items-center justify-center gap-2 text-sm text-slate-600"><span class="h-4 w-4 animate-spin rounded-full border-2 border-action border-t-transparent"></span><span id="search-busy-text"></span></div>
        <p id="hero-error" class="mt-3 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>
        <div id="describe-box" class="mt-4 hidden rounded-2xl border border-slate-200 bg-white p-4 text-left"></div>
