<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Farm admin">
    @foreach(['dashboard' => 'admin.farm.dashboard', 'orders' => 'admin.farm.orders', 'printers' => 'admin.farm.printers', 'materials' => 'admin.farm.materials', 'tuning' => 'admin.farm.tuning', 'photobox' => 'admin.farm.photobox', 'videos' => 'admin.youtube.index','settings' => 'admin.farm.settings', 'agents' => 'admin.farm.agents', 'credit' => 'admin.farm.credit'] as $key => $route)
        <a href="{{ route($route) }}" class="chip {{ request()->routeIs($route) || ($key !== 'dashboard' && request()->routeIs($route.'.*')) ? 'chip-on' : '' }}">{{ __('farm.admin.nav.'.$key) }}</a>
    @endforeach
</nav>
@include('partials.flash')
