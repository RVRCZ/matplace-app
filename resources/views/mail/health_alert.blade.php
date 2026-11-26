<x-mail::message>
# Hlídač serveru

@foreach($rows as $c)
- **{{ $c['what'] }} – {{ $c['label'] }}:** {{ $c['message'] }}
@endforeach

Stav všech kontrol teď:

@foreach($all as $c)
- {{ $c['ok'] ? 'OK' : 'PROBLÉM' }} – {{ $c['label'] }}: {{ $c['message'] }}
@endforeach

<x-mail::button :url="$url">
Otevřít hlídač
</x-mail::button>

matplace.com
</x-mail::message>
