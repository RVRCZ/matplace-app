<x-mail::message>
# {{ $reminder ? 'Stále nepřečtené od lidí' : 'Napsal člověk' }} – {{ $address }}

@foreach($rows as $r)
**{{ $r['who'] }}** ({{ $r['from'] }}){{ $r['when'] !== '' ? ' · '.$r['when'] : '' }}<br>
**{{ $r['subject'] }}**<br>
{{ $r['snippet'] }}<br>
[Otevřít zprávu]({{ $r['url'] }})@if(! empty($r['draft_url'])) · [Koncept odpovědi ke schválení]({{ $r['draft_url'] }})@endif

@endforeach
<x-mail::button :url="$url">
Otevřít schránku
</x-mail::button>

Hlídač schránky, matplace.com
</x-mail::message>
