@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Katalog">
    @foreach(['Inspirační katalog' => 'admin.catalog.index', 'Hledat a přidat' => 'admin.catalog.search', 'Ke kontrole' => 'admin.catalog.review', 'Soubory' => 'admin.catalog.files', 'Karty designérů' => 'admin.catalog.cards'] as $label => $route)
        <a href="{{ route($route) }}" class="chip {{ request()->routeIs($route) ? 'chip-on' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
@include('partials.flash')
