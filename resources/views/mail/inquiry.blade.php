<x-mail::message>
# {{ __('inquiry.mail.'.$key.'.heading', $vars) }}

{{ __('inquiry.mail.'.$key.'.body', $vars) }}

<x-mail::button :url="$url">
{{ __('inquiry.mail.'.$key.'.button') }}
</x-mail::button>

{{ __('inquiry.mail.footer') }}
</x-mail::message>
