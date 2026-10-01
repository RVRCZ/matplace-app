{{--
    Measurement. Our own statistics run on the server and need nothing here. Google Analytics 4 and the Meta pixel
    are third parties: their scripts are on the page only when the visitor allowed them in the cookie bar
    (App\Support\Consent). Printed here when the consent is already known; given later, the script of the bar loads
    them without a reload (resources/js/site/measure.ts).
--}}
@php $measure = \App\Support\Consent::forScripts(); @endphp
<script>window.MP_MEASURE = {{ \Illuminate\Support\Js::from($measure) }};</script>
@if($measure['ga'] && \App\Support\Consent::allows('analytics'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measure['ga'] }}"></script>
@endif
