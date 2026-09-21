<x-mail::message>
# {{ $subjectLine }}

@foreach($lines as $line)
- {{ $line }}
@endforeach

<x-mail::button :url="$url">
{{ __('farm.admin.mail.button') }}
</x-mail::button>
</x-mail::message>
