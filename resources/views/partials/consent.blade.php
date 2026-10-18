{{--
    The cookie bar. Shown until the visitor chooses; "Cookie settings" in the footer opens it again any time.
    One line (Z.md N5: three paragraphs took a quarter of a phone and covered the main action): a short sentence,
    "only necessary" as easy to press as "allow all", the choices behind "Set up", the long text on /cookies.
    Buttons keep a thumb's 44 px and the page keeps room for the bar under its end (resources/js/site/bar.ts), so it
    covers no last line and no field the keyboard moves to (audit 10/2026, bod 4).
    Nothing is ticked in advance. The choice is the cookie `consent` (a1m0 = analytics yes, marketing no), written
    by resources/js/site/measure.ts.
--}}
@php $consent = \App\Support\Consent::given(); @endphp
<section id="consent" class="{{ $consent === null ? '' : 'hidden' }} fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white px-4 py-2 shadow-[0_-8px_24px_rgba(23,43,77,0.12)]" role="dialog" aria-modal="false" aria-labelledby="consent-title">
    <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-x-4 gap-y-1.5 text-xs text-slate-700 sm:gap-y-2 sm:text-sm">
        <p class="min-w-0 flex-1 basis-56"><span id="consent-title" class="font-semibold text-ink">{{ __('site.consent.title') }}.</span> {{ __('site.consent.short') }}
            @if(\Illuminate\Support\Facades\Lang::has('pages.cookies.title'))<a href="{{ route('pages.cookies') }}" class="text-action-dark underline">{{ __('site.consent.more') }}</a>@endif
            <button type="button" class="text-action-dark underline" data-consent="settings">{{ __('site.consent.settings') }}</button>
        </p>
        <div id="consent-choices" class="hidden w-full space-y-1">
            <label class="flex items-start gap-2"><input type="checkbox" checked disabled class="mt-1 h-4 w-4"> <span><strong>{{ __('site.consent.necessary') }}</strong> {{ __('site.consent.necessary_hint') }}</span></label>
            <label class="flex items-start gap-2"><input type="checkbox" id="consent-analytics" class="mt-1 h-4 w-4 accent-action" @checked($consent['analytics'] ?? false)> <span><strong>{{ __('site.consent.analytics') }}</strong> {{ __('site.consent.analytics_hint') }}</span></label>
            <label class="flex items-start gap-2"><input type="checkbox" id="consent-marketing" class="mt-1 h-4 w-4 accent-action" @checked($consent['marketing'] ?? false)> <span><strong>{{ __('site.consent.marketing') }}</strong> {{ __('site.consent.marketing_hint') }}</span></label>
        </div>
        <div class="flex shrink-0 gap-2">
            <button type="button" class="btn-quiet hidden px-3 py-2 text-sm" data-consent="save">{{ __('site.consent.save') }}</button>
            <button type="button" class="btn-secondary px-3 py-2 text-sm" data-consent="necessary">{{ __('site.consent.only_necessary') }}</button>
            <button type="button" class="btn-ink px-3 py-2 text-sm" data-consent="all">{{ __('site.consent.all') }}</button>
        </div>
    </div>
</section>
