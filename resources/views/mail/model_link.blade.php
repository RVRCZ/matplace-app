<x-mail::message>
{{ $intro }}

@if($image)
<p style="text-align:center"><a href="{{ $url }}"><img src="{{ $image }}" alt="" width="320" style="max-width:100%;border-radius:12px"></a></p>
@endif

<x-mail::button :url="$url">
{{ $button }}
</x-mail::button>

{{ $after }}

<small>{{ $footer }}@if($unsubscribe) <a href="{{ $unsubscribe }}">{{ $unsubscribeLabel }}</a>@endif</small>
</x-mail::message>
