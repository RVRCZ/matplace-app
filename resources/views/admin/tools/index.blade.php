@extends('layouts.app', ['title' => __('tools.admin.title').' · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<h1 class="mt-2 text-2xl font-semibold text-ink">{{ __('tools.admin.title') }}</h1>
<p class="hint max-w-3xl">{{ __('tools.admin.lead') }}</p>

<div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="{{ __('tools.filter') }}">
    @foreach(\App\Http\Controllers\Admin\ToolController::FILTERS as $filter)
        <a href="{{ route('admin.tools.index', $filter === 'all' ? [] : ['show' => $filter]) }}" class="chip {{ $show === $filter ? 'chip-on' : '' }}" @if($show === $filter) aria-current="true" @endif>{{ __('tools.admin.filter.'.$filter) }} · {{ $counts[$filter] }}</a>
    @endforeach
</div>

<div class="card mt-3 overflow-x-auto">
    <table class="w-full text-left text-sm" id="admin-tools">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>
            @foreach(['tool', 'cats', 'verified', 'config', 'public', 'note', 'changed'] as $column)
                <th class="px-3 py-2">{{ __('tools.admin.col.'.$column) }}</th>
            @endforeach
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                @php $flag = $row['flag']; $form = 'tool-form-'.$row['key']; @endphp
                <tr id="tool-{{ $row['key'] }}" data-tool="{{ $row['key'] }}" class="align-top target:bg-warn-soft">
                    <td class="px-3 py-2">
                        <span class="font-semibold text-ink">{{ $row['title'] }}</span>
                        <span class="block text-xs text-slate-500">
                            @if($row['url'])<a href="{{ $row['url'] }}" class="text-action-dark underline">{{ $row['path'] }}</a>@else{{ __('tools.admin.no_page') }}@endif
                            · <span data-state>{{ __('tools.admin.state.'.($row['listed'] ? 'public' : 'hidden')) }}</span>
                        </span>
                        @if($row['key'] === 'calc')<span class="block text-xs text-slate-500">{{ __('tools.admin.calc') }}</span>@endif
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-600">{{ collect($row['categories'])->map(fn ($c) => __('tools.cats.'.$c))->implode(', ') }}</td>
                    <td class="px-3 py-2 text-xs">@if($row['verified'])<span class="text-ok">{{ \Illuminate\Support\Carbon::parse($row['verified'])->format('j. n. Y') }}</span>@else<span class="text-slate-400">—</span>@endif</td>
                    <td class="px-3 py-2 text-xs">
                        @if($row['config']){{ __('tools.admin.config.on') }}@else<span class="font-semibold text-warn" title="{{ __('tools.admin.config.off.hint') }}">{{ __('tools.admin.config.off') }}</span>@endif
                    </td>
                    <td class="px-3 py-2">
                        <form id="{{ $form }}" method="post" action="{{ route('admin.tools.update', $row['key']) }}">@csrf</form>
                        <input type="hidden" name="public" value="0" form="{{ $form }}">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="public" value="1" form="{{ $form }}" @checked($flag?->public ?? true) class="h-5 w-5 shrink-0 accent-action" aria-label="{{ __('tools.admin.col.public') }}: {{ $row['title'] }}">
                            @unless($row['config'])<span class="text-xs text-slate-500">{{ __('tools.admin.config.off.hint') }}</span>@endunless
                        </label>
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                            <input type="text" name="note" form="{{ $form }}" value="{{ $flag?->note }}" maxlength="1000" placeholder="{{ __('tools.admin.note.placeholder') }}" aria-label="{{ __('tools.admin.col.note') }}: {{ $row['title'] }}" class="w-56 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <button form="{{ $form }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-semibold text-slate-700 hover:border-ink">{{ __('tools.admin.save') }}</button>
                        </div>
                        <span class="mt-1 block min-h-4 text-xs" data-result role="status"></span>
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-500" data-changed>{{ \App\Http\Controllers\Admin\ToolController::changed($flag) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">{{ __('tools.admin.none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
(() => {
    // a row is saved where it stands: the switch at once, the note when it is left or Enter is pressed
    const texts = {{ \Illuminate\Support\Js::from(['saved' => __('tools.admin.saved'), 'failed' => __('tools.admin.failed'), 'public' => __('tools.admin.state.public'), 'hidden' => __('tools.admin.state.hidden')]) }};
    document.querySelectorAll('#admin-tools tr[data-tool]').forEach((row) => {
        const form = row.querySelector('form');
        const result = row.querySelector('[data-result]');
        const note = row.querySelector('input[name=note]');
        let noteSaved = note.value;     // what the server has; null after a save that failed
        const save = async () => {
            noteSaved = note.value;
            result.textContent = '…';
            result.className = 'mt-1 block min-h-4 text-xs text-slate-500';
            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error(String(res.status));
                const data = await res.json();
                row.querySelector('[data-state]').textContent = data.listed ? texts.public : texts.hidden;
                row.querySelector('[data-changed]').textContent = data.changed;
                result.textContent = texts.saved;
                result.classList.replace('text-slate-500', 'text-ok');
            } catch (e) {
                noteSaved = null;
                result.textContent = texts.failed;
                result.classList.replace('text-slate-500', 'text-red-700');
            }
        };
        form.addEventListener('submit', (e) => { e.preventDefault(); save(); });
        row.querySelector('input[type=checkbox]').addEventListener('change', save);
        note.addEventListener('blur', () => { if (note.value !== noteSaved) save(); });
    });
})();
</script>
@endsection
