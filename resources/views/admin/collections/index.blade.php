@extends('layouts.app', ['title' => 'Kolekce · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<div class="mt-3 flex flex-wrap items-end gap-3">
    <form method="post" action="{{ route('admin.collections.store') }}" class="flex flex-1 items-end gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-sm">@csrf
        <label class="flex-1 text-xs font-semibold text-slate-600">Nová kolekce (český název)<input name="title" required minlength="2" maxlength="160" placeholder="Organizéry na stůl" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
        <button class="btn-primary text-sm">Založit</button>
    </form>
    <a href="{{ route('admin.collections.suggestions') }}" class="btn-quiet text-sm">Návrhy od AI</a>
</div>

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Kolekce</th><th class="px-3 py-2">Modelů</th><th class="px-3 py-2">Jazyky</th><th class="px-3 py-2">Stav</th><th class="px-3 py-2">Pořadí</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($collections as $c)
                <tr>
                    <td class="px-3 py-2"><a href="{{ route('admin.collections.edit', $c->id) }}" class="font-semibold underline">{{ $c->title['cs'] ?? $c->slug }}</a><span class="block text-xs text-slate-500">/collections/{{ $c->slug }}</span></td>
                    <td class="px-3 py-2">{{ $c->items_count }}</td>
                    <td class="px-3 py-2 text-xs uppercase">{{ implode(' ', $c->locales()) }}</td>
                    <td class="px-3 py-2">@if($c->getAttribute('visible'))<a href="{{ route('collections.show', $c->slug) }}" target="_blank" class="text-ok underline">veřejná</a>@else skrytá @endif</td>
                    <td class="px-3 py-2">{{ $c->position }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Zatím žádná kolekce.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
