{{--
    The page of the selling and planning tools (session 4, docs/S.md): the panel of numbered sections on the left,
    as on the tool page, and on the right the result the page computes on the client: a breakdown, a chart, a map.
    No viewer and no file. A tool's own view extends this one and fills @section('panel') with <x-tool-section>s
    and @section('stage') with its result; it passes tool, lead, sections (id => label) and payload (window.MP_SELL).
--}}
@extends('layouts.app', ['title' => __('tools.'.$tool.'.title').' · matplace', 'tool' => $tool, 'wide' => true])

@push('head')
<script>window.MP_SELL = {{ \Illuminate\Support\Js::from(($payload ?? []) + ['tool' => $tool, 'locale' => app()->getLocale(), 'home' => route('home'), 'tools' => route('tools'),
    'i18n' => collect(\Illuminate\Support\Arr::dot(['sell' => trans('sell')]))->all()]) }};</script>
@endpush

@section('content')
<div id="sell-page" data-sell="{{ $tool }}" class="grid gap-4 lg:grid-cols-[380px_minmax(0,1fr)] lg:items-start">
    <div id="sell-panel" class="order-2 min-w-0 lg:order-1" style="counter-reset: tool-section">
        <a href="{{ route('tools') }}" class="inline-flex items-center gap-1 text-sm text-muted hover:text-ink"><x-icon name="arrow-left" class="h-4 w-4" />{{ __('tools.title') }}</a>
        <h1 class="mt-1 text-2xl font-semibold leading-tight text-ink">{{ __('tools.'.$tool.'.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $lead }}</p>
        @if(!empty($unavailable))<div class="note-warn mt-4 text-sm">{{ $unavailable }}</div>@endif
        <nav id="sell-nav" class="sticky top-0 z-10 -mx-1 mt-3 flex flex-wrap gap-1 bg-page px-1 py-2" aria-label="{{ __('param.steps') }}">
            @foreach($sections as $id => $label)
                <a href="#sec-{{ $id }}" data-nav="{{ $id }}" class="tool-nav-item"><span class="tool-nav-no">{{ $loop->iteration }}</span>{{ $label }}</a>
            @endforeach
        </nav>
        @yield('panel')
    </div>
    <div id="sell-stage" class="order-1 flex min-w-0 flex-col gap-3 lg:sticky lg:top-3 lg:order-2">
        @yield('stage')
    </div>
</div>
@endsection
