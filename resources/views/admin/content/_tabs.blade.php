@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Obsah">
    <a href="{{ route('admin.content.posts') }}" class="chip {{ request()->routeIs('admin.content.posts*') ? 'chip-on' : '' }}">Blog</a>
    <a href="{{ route('admin.content.banners') }}" class="chip {{ request()->routeIs('admin.content.banners*') ? 'chip-on' : '' }}">Bannery</a>
    <a href="{{ route('admin.meta.index') }}" class="chip">Facebook a Instagram</a>
</nav>
@include('partials.flash')
