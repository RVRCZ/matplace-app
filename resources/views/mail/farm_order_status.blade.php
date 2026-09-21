<x-mail::message>
# {{ __('farm.mail.'.$status.'.subject', ['number' => $order->number]) }}

{{ __('farm.mail.'.$status.'.body', ['name' => $order->modelFile?->original_name, 'color' => $order->color?->name]) }}

@if($reason)
{{ $reason }}
@endif

<x-mail::button :url="$url">
{{ __('farm.mail.button') }}
</x-mail::button>

{{ __('farm.mail.footer') }}
</x-mail::message>
