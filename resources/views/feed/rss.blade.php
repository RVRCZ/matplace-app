<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ __('site.feed.title') }}</title>
    <link>{{ url('/') }}</link>
    <description>{{ __('site.feed.description') }}</description>
    <language>{{ $locale }}</language>
    <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml"/>
@foreach($items as $item)
    <item>
        <title>{{ $item['title'] }}</title>
        <link>{{ $item['link'] }}</link>
        <guid isPermaLink="true">{{ $item['link'] }}</guid>
        <pubDate>{{ $item['date']->toRssString() }}</pubDate>
        <description>{{ $item['description'] }}</description>
    </item>
@endforeach
</channel>
</rss>
