{{-- The files on the printer's disk (agent 0.2.9, docs/AM.md §10): the list the agent sent last, one file up to the
     server, delete, tidy. Every button is a command for the agent; the list refreshes when the agent answers. --}}
@php
    $listing = $files['listing'];
    $keep = $files['keep'];
    $fetched = collect($files['fetched'])->keyBy('name');
    $size = fn (?int $b) => \App\Domain\Farm\PrinterFiles::size($b);
    $ours = collect($listing['files'])->filter(fn ($f) => \App\Domain\Farm\PrinterFiles::isOurs($f['name']) && ! in_array($f['name'], $keep, true));
@endphp
<section id="files" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold">{{ __('farm.admin.files.title') }}</h2>
        <div class="flex flex-wrap items-center gap-2">
            <form method="post" action="{{ route('admin.farm.printers.files.list', $printer) }}">@csrf<button class="btn-quiet text-sm">{{ __('farm.admin.files.load') }}</button></form>
            <form method="post" action="{{ route('admin.farm.printers.files.cleanup', $printer) }}" onsubmit="return confirm(@js(__('farm.admin.files.cleanup_confirm', ['n' => $ours->count()])))">@csrf<button class="btn-quiet text-sm" @disabled($ours->isEmpty())>{{ __('farm.admin.files.cleanup') }}@if($ours->isNotEmpty()) ({{ $ours->count() }})@endif</button></form>
        </div>
    </div>
    <p class="mt-1 text-xs text-slate-500">
        @if($listing['at']){{ __('farm.admin.files.loaded_at', ['time' => \Illuminate\Support\Carbon::parse($listing['at'])->format('j. n. H:i')]) }}@if($listing['total']) · {{ __('farm.admin.files.free', ['free' => $size($listing['free']), 'total' => $size($listing['total'])]) }}@endif
        @else{{ __('farm.admin.files.none') }}@endif
        · {{ __('farm.admin.files.gcode_model_note') }}
    </p>
    @if(! empty($listing['deleted']) || ! empty($listing['cleaned_at']))
        <p class="mt-1 text-xs text-slate-600" data-cleanup-summary>{{ __('farm.admin.files.cleanup_done', ['time' => \Illuminate\Support\Carbon::parse($listing['cleaned_at'] ?? $listing['at'])->format('j. n. H:i'), 'n' => count($listing['deleted']), 'freed' => $size($listing['freed'])]) }}
            @if($listing['deleted']) {{ __('farm.admin.files.deleted_list') }} {{ collect($listing['deleted'])->pluck('name')->implode(', ') }}@endif</p>
    @endif
    @if($files['commands']->isNotEmpty())
        <ul class="mt-2 space-y-0.5 text-xs text-slate-500" data-file-commands>
            @foreach($files['commands'] as $c)
                <li>{{ $c->created_at->format('j. n. H:i') }} · {{ __('farm.admin.command_type.'.$c->type) }}@if($c->payload['name'] ?? null) {{ $c->payload['name'] }}@endif · {{ __('farm.admin.command_status.'.$c->status) }}@if($c->result) · {{ $c->result }}@endif</li>
            @endforeach
        </ul>
    @endif

    @if($listing['at'] && empty($listing['files']))
        <p class="mt-2 text-sm text-slate-500">{{ __('farm.admin.files.empty') }}</p>
    @elseif($listing['files'])
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-slate-500"><tr><th class="py-1 pr-2">{{ __('farm.admin.files.name') }}</th><th class="py-1 pr-2">{{ __('farm.admin.files.size') }}</th><th class="py-1 pr-2">{{ __('farm.admin.files.date') }}</th><th class="py-1"></th></tr></thead>
                <tbody>
                    @foreach($listing['files'] as $f)
                        @php $running = in_array($f['name'], $keep, true); $copy = $fetched->get($f['name']); @endphp
                        <tr class="border-t border-slate-100" data-file="{{ $f['name'] }}">
                            <td class="py-1 pr-2 font-mono text-xs">{{ $f['name'] }}
                                @if($running)<span class="ml-1 rounded-full bg-action-soft px-2 py-0.5 text-xs font-semibold text-action-dark">{{ __('farm.admin.files.running') }}</span>
                                @elseif(\App\Domain\Farm\PrinterFiles::isOurs($f['name']))<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ __('farm.admin.files.ours') }}</span>@endif</td>
                            <td class="py-1 pr-2 whitespace-nowrap text-xs">{{ $size($f['size']) }}</td>
                            <td class="py-1 pr-2 whitespace-nowrap text-xs">{{ $f['modified'] ? \Illuminate\Support\Carbon::createFromTimestamp($f['modified'])->format('j. n. Y H:i') : '—' }}</td>
                            <td class="py-1">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if($copy)
                                        <a href="{{ route('admin.farm.printers.files.download', [$printer, $f['name']]) }}" class="text-xs text-action-dark underline">{{ __('farm.admin.files.download') }} ({{ $size($copy['size']) }})</a>
                                    @else
                                        <form method="post" action="{{ route('admin.farm.printers.files.fetch', $printer) }}" class="inline">@csrf<input type="hidden" name="name" value="{{ $f['name'] }}"><button class="btn-quiet min-h-0 px-2 py-1 text-xs">{{ __('farm.admin.files.fetch') }}</button></form>
                                    @endif
                                    @if(! $running)
                                        <form method="post" action="{{ route('admin.farm.printers.files.delete', $printer) }}" class="inline" onsubmit="return confirm(@js(__('farm.admin.files.delete_confirm', ['name' => $f['name']])))">@csrf<input type="hidden" name="name" value="{{ $f['name'] }}"><button class="btn-quiet min-h-0 px-2 py-1 text-xs text-red-700">{{ __('farm.admin.files.delete') }}</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($fetched->isNotEmpty())
        <h3 class="mt-4 text-sm font-bold">{{ __('farm.admin.files.fetched_title') }}</h3>
        <ul class="mt-1 space-y-1 text-xs" data-fetched-files>
            @foreach($fetched as $c)
                <li class="flex flex-wrap items-center gap-2"><span class="font-mono">{{ $c['name'] }}</span> <span class="text-slate-500">{{ $size($c['size']) }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($c['modified'])->format('j. n. H:i') }}</span>
                    <a href="{{ route('admin.farm.printers.files.download', [$printer, $c['name']]) }}" class="text-action-dark underline">{{ __('farm.admin.files.download') }}</a>
                    <form method="post" action="{{ route('admin.farm.printers.files.forget', $printer) }}" class="inline">@csrf<input type="hidden" name="name" value="{{ $c['name'] }}"><button class="text-slate-500 underline">{{ __('farm.admin.files.forget') }}</button></form></li>
            @endforeach
        </ul>
    @endif
</section>
