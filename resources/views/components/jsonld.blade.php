{{--
    Structured data (schema.org) for search engines: <x-jsonld :data="$thing" />. Empty values are left out, the
    schema.org context is added and "<" is escaped so a text can never close the script (App\Support\Schema::json).
--}}
@props(['data'])
@php $json = \App\Support\Schema::json((array) $data); @endphp
@if($json !== null)
<script type="application/ld+json">{!! $json !!}</script>
@endif
