<x-mail::message>
# {{ __('quote.mail.change_heading') }}

{{ __('quote.mail.change_intro', ['number' => $quote->number, 'version' => $quote->version, 'client' => $quote->client_name ?: $quote->client_email ?: '—']) }}

<x-mail::panel>
{{ $quote->change_request }}
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('quote.mail.change_button') }}
</x-mail::button>
</x-mail::message>
