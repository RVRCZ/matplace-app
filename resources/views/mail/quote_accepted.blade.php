<x-mail::message>
# {{ __('quote.mail.accepted_heading') }}

{{ __('quote.mail.accepted_intro', ['number' => $quote->number, 'client' => $quote->client_name ?: $quote->client_email ?: '—', 'total' => number_format($quote->total, 0, ',', ' ')]) }}

<x-mail::button :url="$url">
{{ __('quote.mail.accepted_button') }}
</x-mail::button>

{{ __('quote.mail.accepted_footer') }}
</x-mail::message>
