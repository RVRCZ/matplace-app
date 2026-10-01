@extends('layouts.app', ['title' => 'Blog · admin', 'noindex' => true])

@section('content')
@include('admin.content._tabs')

<a href="{{ route('admin.content.posts.new') }}" class="btn-primary mt-3 text-sm">Nový článek</a>

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Článek</th><th class="px-3 py-2">Jazyky</th><th class="px-3 py-2">Stav</th><th class="px-3 py-2">Formát</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($posts as $p)
                <tr>
                    <td class="px-3 py-2"><a href="{{ route('admin.content.posts.edit', $p->id) }}" class="font-semibold underline">{{ $p->title['cs'] ?? $p->slug }}</a><span class="block text-xs text-slate-500">/blog/{{ $p->slug }}</span></td>
                    <td class="px-3 py-2 text-xs uppercase">{{ implode(' ', $p->locales()) }}</td>
                    <td class="px-3 py-2">@if($p->isPublished())<a href="{{ route('blog.show', $p->slug) }}" target="_blank" class="text-ok underline">zveřejněno {{ $p->published_at->format('j. n. Y') }}</a>@elseif($p->published_at)naplánováno na {{ $p->published_at->format('j. n. Y H:i') }}@else koncept @endif</td>
                    <td class="px-3 py-2 text-xs">{{ $p->format === 'html' ? 'převzaté HTML' : 'Markdown' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Zatím žádný článek. Staré články přenese <code>php artisan matplace:import-blog</code>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
