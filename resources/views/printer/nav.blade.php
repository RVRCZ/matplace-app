<nav class="flex flex-wrap gap-2 text-sm">
    @foreach(['printer.dashboard' => __('printer.nav.home'), 'printer.calculator' => __('printer.nav.calculator'), 'printer.quotes' => __('printer.nav.quotes'), 'printer.profile' => __('printer.nav.profile'), 'account' => __('printer.nav.account')] as $route => $label)
        <a href="{{ route($route) }}" class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs($route.($route === 'account' ? '' : '*')) ? 'bg-teal-600 text-white' : 'bg-white text-slate-700 border border-slate-200' }}">{{ $label }}</a>
    @endforeach
</nav>
