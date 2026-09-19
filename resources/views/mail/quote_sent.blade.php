<x-mail::message>
# {{ __('quote.mail.heading', ['printer' => $quote->printerProfile->display_name]) }}

{{ __('quote.mail.intro', ['number' => $quote->number, 'total' => number_format($quote->total, 0, ',', ' '), 'currency' => $quote->currency]) }}

<x-mail::button :url="$url">
{{ __('quote.mail.button') }}
</x-mail::button>

@if($quote->valid_until)
{{ __('quote.valid_until') }}: {{ $quote->valid_until->format('j. n. Y') }}
@endif

{{ __('quote.mail.footer') }}
</x-mail::message>
