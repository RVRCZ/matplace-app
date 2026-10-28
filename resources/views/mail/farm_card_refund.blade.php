<x-mail::message>
# {{ __('farm.refund.mail.subject', ['number' => $order?->number]) }}

{{ $why }}

{{ __('farm.refund.mail.body', ['amount' => $amount]) }}

<x-mail::button :url="$url">
{{ __('farm.mail.button') }}
</x-mail::button>

{{ __('farm.mail.footer') }}
</x-mail::message>
