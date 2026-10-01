{{--
    The cookie bar. Shown until the visitor chooses; "Cookie settings" in the footer opens it again any time.
    Nothing is ticked in advance and "only necessary" is as easy to press as "allow all". The choice is the cookie
    `consent` (a1m0 = analytics yes, marketing no), written by resources/js/site/measure.ts.
--}}
@php $consent = \App\Support\Consent::given(); @endphp
<section id="consent" class="{{ $consent === null ? '' : 'hidden' }} fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white p-4 shadow-[0_-8px_24px_rgba(23,43,77,0.12)]" role="dialog" aria-modal="false" aria-labelledby="consent-title">
    <div class="mx-auto flex max-w-4xl flex-col gap-3 text-sm text-slate-700 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
            <h2 id="consent-title" class="font-bold text-ink">{{ __('site.consent.title') }}</h2>
            <p class="mt-1">{{ __('site.consent.text') }} @if(\Illuminate\Support\Facades\Lang::has('pages.cookies.title'))<a href="{{ route('pages.cookies') }}" class="text-action-dark underline">{{ __('site.consent.more') }}</a>@endif</p>
            <div id="consent-choices" class="mt-2 hidden space-y-1">
                <label class="flex items-start gap-2"><input type="checkbox" checked disabled class="mt-1 h-4 w-4"> <span><strong>{{ __('site.consent.necessary') }}</strong> {{ __('site.consent.necessary_hint') }}</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" id="consent-analytics" class="mt-1 h-4 w-4 accent-action" @checked($consent['analytics'] ?? false)> <span><strong>{{ __('site.consent.analytics') }}</strong> {{ __('site.consent.analytics_hint') }}</span></label>
                <label class="flex items-start gap-2"><input type="checkbox" id="consent-marketing" class="mt-1 h-4 w-4 accent-action" @checked($consent['marketing'] ?? false)> <span><strong>{{ __('site.consent.marketing') }}</strong> {{ __('site.consent.marketing_hint') }}</span></label>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <button type="button" class="btn-quiet min-h-0 px-3 py-2 text-sm" data-consent="settings">{{ __('site.consent.settings') }}</button>
            <button type="button" class="btn-quiet hidden min-h-0 px-3 py-2 text-sm" data-consent="save">{{ __('site.consent.save') }}</button>
            <button type="button" class="btn-secondary min-h-0 px-3 py-2 text-sm" data-consent="necessary">{{ __('site.consent.only_necessary') }}</button>
            <button type="button" class="btn-primary min-h-0 px-3 py-2 text-sm" data-consent="all">{{ __('site.consent.all') }}</button>
        </div>
    </div>
</section>
