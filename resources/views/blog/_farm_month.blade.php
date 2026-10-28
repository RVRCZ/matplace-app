{{--
    The farm's figures for one month inside an article ([farm: 2026-11], App\Support\BlogEmbeds → App\Support\FarmMonthStats):
    prints finished, the median from payment to handover, time-lapses published, the tools used most. Counted from the
    orders when the page is shown, so an article scheduled for the month after carries the month's real numbers.
--}}
@php
    $month = $stats['from']->locale(app()->getLocale())->translatedFormat('F Y');
    $median = $stats['handed_over_days_median'];
    $tools = collect($stats['tools'])
        ->map(fn ($t) => (\Illuminate\Support\Facades\Lang::has('tools.'.$t['kind'].'.title') ? __('tools.'.$t['kind'].'.title') : $t['kind']).' ('.$t['n'].')')
        ->implode(', ');
@endphp
<aside class="my-6 not-prose card flex flex-col gap-1 p-4" data-blog-farm="{{ $ym }}">
    <p class="font-semibold text-ink">{{ __('site.blog.farm.title', ['month' => $month]) }}</p>
    <ul class="list-disc pl-5 text-sm text-muted">
        <li>{{ __('site.blog.farm.printed', ['n' => $stats['printed']]) }}</li>
        @if($median !== null)
            <li>{{ __('site.blog.farm.median', ['days' => number_format((float) $median, 1, app()->getLocale() === 'en' ? '.' : ',', '')]) }}</li>
        @endif
        <li>{{ __('site.blog.farm.videos', ['n' => $stats['videos_published']]) }}</li>
        @if($tools !== '')
            <li>{{ __('site.blog.farm.tools', ['tools' => $tools]) }}</li>
        @endif
        @if($stats['uploads'] > 0)
            <li>{{ __('site.blog.farm.uploads', ['n' => $stats['uploads']]) }}</li>
        @endif
    </ul>
    <p class="text-xs text-muted">{{ __('site.blog.farm.note') }}</p>
</aside>
