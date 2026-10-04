<x-mail::message>
# {{ $report['subject'] }}

{{ $report['intro'] }}

@foreach($report['sections'] as $section)
## {{ $section['title'] }}

@foreach($section['lines'] as $line)
- {{ $line }}
@endforeach

@endforeach
<x-mail::button :url="$url">
Celý přehled
</x-mail::button>
</x-mail::message>
