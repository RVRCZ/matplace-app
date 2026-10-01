<x-mail::message>
@foreach($paragraphs as $paragraph)
{!! nl2br(e(trim($paragraph))) !!}

@endforeach
</x-mail::message>
