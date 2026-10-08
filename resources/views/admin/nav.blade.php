{{-- The sections of the admin. Every admin page starts with this line; the farm has its own second line under it. --}}
@php
    $sections = [
        'Statistiky' => ['admin.stats.funnel', ['admin.stats.*']],
        'Farma' => ['admin.farm.dashboard', ['admin.farm.*', 'admin.youtube.*']],
        'Katalog' => ['admin.catalog.index', ['admin.catalog.*']],
        'Kolekce' => ['admin.collections.index', ['admin.collections.*']],
        'Akce' => ['admin.events.index', ['admin.events.*']],
        'Obsah' => ['admin.content.posts', ['admin.content.*']],
        'Meta' => ['admin.meta.index', ['admin.meta.*']],
        'AI' => ['admin.ai.index', ['admin.ai.*']],
        'E-maily' => ['admin.emails.index', ['admin.emails.*']],
        'Uživatelé' => ['admin.users.index', ['admin.users.*']],
    ];
    $drafts = \App\Models\OutgoingEmail::where('status', 'draft')->count();
@endphp
<nav class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-line pb-2 text-sm font-semibold" aria-label="Admin">
    @foreach($sections as $label => [$route, $patterns])
        <a href="{{ route($route) }}" @if(request()->routeIs(...$patterns)) aria-current="page" @endif class="{{ request()->routeIs(...$patterns) ? 'text-action-dark underline' : 'text-slate-600 hover:text-ink' }}">{{ $label }}@if($label === 'E-maily' && $drafts > 0) <span class="rounded-full bg-action px-1.5 text-xs text-white">{{ $drafts }}</span>@endif</a>
    @endforeach
</nav>
