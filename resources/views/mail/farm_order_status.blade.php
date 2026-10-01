<x-mail::message>
# {{ __('farm.mail.'.$status.'.subject', ['number' => $order->number]) }}

{{ __('farm.mail.'.$status.'.body', ['name' => $order->modelFile?->original_name, 'color' => $order->color?->name]) }}

@if($reason)
{{ $reason }}
@endif

@if($trackingUrl ?? null)
{{ __('farm.mail.shipped.barcode', ['barcode' => $order->packeta_barcode]) }}

<x-mail::button :url="$trackingUrl">
{{ __('farm.mail.shipped.track') }}
</x-mail::button>

[{{ __('farm.mail.button') }}]({{ $url }})
@else
<x-mail::button :url="$url">
{{ __('farm.mail.button') }}
</x-mail::button>
@endif

{{ __('farm.mail.footer') }}
</x-mail::message>
