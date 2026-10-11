{{-- The photos of the figure tools (a bust or a figure, a pet figurine): the front photo and the three optional sides, each with its
    preview and its remove button; resources/js/calc/figure.ts reads them. $pick, $tips, $betterHint, $viewsTips replace the texts. --}}
        <div class="relative">
            <label data-view-box="front" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-line px-4 py-6 text-center hover:border-ink">
                <img id="figure-preview" data-view-preview="front" src="" alt="" class="mb-2 hidden max-h-44 rounded-lg">
                <x-icon name="camera" class="h-7 w-7 text-muted" />
                <span class="mt-1 font-medium text-ink">{{ $pick ?? __('figure.pick_photo') }}</span>
                <span class="text-sm text-muted">{{ $tips ?? __('figure.photo_tips') }}</span>
                <input id="figure-photo" name="image" data-view="front" type="file" accept="image/*" class="sr-only">
            </label>
            <button type="button" data-view-remove="front" class="tool-icon-btn absolute right-2 top-2 hidden" aria-label="{{ __('figure.remove') }}" title="{{ __('figure.remove') }}"><x-icon name="x" class="h-4 w-4" /></button>
        </div>

        {{-- more sides are always on the screen: the likeness is clearly better with them --}}
        <fieldset id="figure-views" class="rounded-lg border border-line p-3">
            <legend class="px-1 text-sm font-medium text-ink">{{ __('figure.views') }}</legend>
            <p class="text-sm text-ink"><strong>{{ __('figure.views.better') }}</strong> {{ $betterHint ?? __('figure.views.better_hint') }}</p>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach(['left' => 'arrow-left', 'back' => 'refresh-cw', 'right' => 'arrow-right'] as $view => $ico)
                    <div class="relative">
                        <label data-view-box="{{ $view }}" class="flex h-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-line p-2 text-center text-sm hover:border-ink">
                            <img data-view-preview="{{ $view }}" src="" alt="" class="mb-1 hidden max-h-20 rounded">
                            <x-icon :name="$ico" class="h-4 w-4 text-muted" />
                            <span class="font-medium text-ink">{{ __('figure.view.'.$view) }}</span>
                            <input name="image_{{ $view }}" data-view="{{ $view }}" type="file" accept="image/*" class="sr-only">
                        </label>
                        <button type="button" data-view-remove="{{ $view }}" class="tool-icon-btn absolute right-1 top-1 hidden !h-7 !w-7" aria-label="{{ __('figure.remove') }}: {{ __('figure.view.'.$view) }}" title="{{ __('figure.remove') }}"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                    </div>
                @endforeach
            </div>
            <p id="figure-views-msg" class="mt-2 hidden text-sm text-warn" aria-live="polite"></p>
            <p class="mt-2 text-xs text-muted">{{ $viewsTips ?? __('figure.views.tips') }}</p>
        </fieldset>
