<x-mail::message>
# {{ __('youtube.mail.title') }}

{{ __('youtube.mail.body', ['name' => $order?->modelFile?->original_name ?? '']) }}

<x-mail::button :url="$watchUrl">
{{ __('youtube.mail.watch') }}
</x-mail::button>

{{ __('youtube.mail.share') }}

{{ __('youtube.mail.next', ['url' => $link]) }}

[{{ __('farm.mail.button') }}]({{ $orderUrl }})

{{ __('farm.mail.footer') }}
</x-mail::message>
