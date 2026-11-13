<x-mail::message>
# {{ __('farm.guest.mail.subject', ['number' => $order->number]) }}

@php $pause = \App\Domain\Farm\FarmState::holds($order) ? \App\Domain\Farm\FarmState::holdLine() : null; @endphp
{{ __($pause ? 'farm.guest.mail.body_pause' : 'farm.guest.mail.body', ['name' => $order->modelFile?->original_name, 'total' => $total]) }}
@if($pause)

{{ $pause }}
@endif
@if($spools = $order->spoolText(false))

{{ __('farm.mail.colors', ['list' => $spools]) }}
@endif

<x-mail::button :url="$url">
{{ __('farm.mail.button') }}
</x-mail::button>

@if($passwordUrl)
{{ __('farm.guest.mail.created', ['email' => $order->user?->email, 'days' => \App\Domain\Farm\GuestCheckout::PASSWORD_LINK_DAYS]) }}

[{{ __('farm.guest.mail.password') }}]({{ $passwordUrl }})
@else
{{ __('farm.guest.mail.attached', ['email' => $order->user?->email]) }}
@endif

{{ __('farm.mail.footer') }}
</x-mail::message>
