{{--
    Where the page stands, as a line a visitor can read and follow (docs/AP.md §15): matplace › … › this page.
    <x-crumbs :trail="[[name, address], …]" /> without the home page; the last one is the page itself and is no link.
    The layout prints the same trail as BreadcrumbList structured data when the view hands it over as `crumbs`.
--}}
@props(['trail'])
<nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-1.5 text-sm text-muted']) }} aria-label="{{ __('nav.crumbs') }}" data-crumbs>
    <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center underline-offset-4 hover:text-ink hover:underline">matplace</a>
    @foreach($trail as [$name, $address])
        <span aria-hidden="true">›</span>
        @if($loop->last)
            <span class="inline-flex min-h-11 items-center text-ink" aria-current="page">{{ $name }}</span>
        @else
            <a href="{{ $address }}" class="inline-flex min-h-11 items-center underline-offset-4 hover:text-ink hover:underline">{{ $name }}</a>
        @endif
    @endforeach
</nav>
