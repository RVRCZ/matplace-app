@php
    $i18n = collect(['check.page.max', 'toolpage.status.empty', 'param.too_fast'])->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $config['max_upload_mb']])])
        ->merge(collect(['pick', 'bad_format', 'too_big', 'uploading', 'processing', 'model_failed', 'failed', 'unavailable', 'analysing', 'fits', 'plan', 'too_many', 'auto', 'plane.x', 'plane.y', 'plane.z', 'planes.reset',
            'working', 'stage.queued', 'stage.loading', 'stage.thinning', 'stage.repairing', 'stage.cutting', 'stage.joints', 'stage.numbers', 'stage.layout', 'stage.done',
            'report', 'report.keys', 'report.pins', 'report.none', 'report.repaired', 'map', 'map.level', 'piece', 'piece.size', 'piece.down.x', 'piece.down.y', 'piece.down.z', 'part.pins', 'part.keys', 'part.piece', 'glue', 'again',
            'warn.does_not_fit', 'warn.many_pieces', 'warn.no_room_for_pins', 'warn.key_as_pins', 'warn.too_big',
            'error.fits_already', 'error.too_heavy', 'error.too_small', 'error.too_big', 'error.not_watertight', 'error.edit_failed'])->mapWithKeys(fn ($k) => ['edit.'.$op.'.'.$k => \Illuminate\Support\Facades\Lang::has('edit.'.$op.'.'.$k) ? \App\Support\NextStep::text('edit.'.$op.'.'.$k) : \App\Support\NextStep::text('edit.'.$k)]))->all();
    $sections = ['file' => __('toolpage.section.file'), 'settings' => __('toolpage.section.settings'), 'result' => __('toolpage.section.result')];
@endphp

@extends('tools.page', ['tool' => $op, 'module' => 'edit', 'lead' => __('edit.'.$op.'.lead'), 'available' => $available, 'unavailable' => __('edit.unavailable'), 'goLabel' => \App\Support\NextStep::text('param.go'),
    'sections' => $sections])

@push('head')
<script>
    window.MP_EDIT = {
        op: @json($op),
        upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), parts: @json(url('/api/tools/edit')), home: @json(route('home')), from: @json($from),
        formats: {{ \Illuminate\Support\Js::from($config['formats']) }}, maxMb: {{ (int) $config['max_upload_mb'] }},
        beds: {{ \Illuminate\Support\Js::from($beds) }}, margin: {{ \App\Domain\Tools\ModelEditor::MARGIN }}, farmMargin: {{ (float) ($config['bed_margin_mm'] ?? 0) }},
        config: {{ \Illuminate\Support\Js::from(\Illuminate\Support\Arr::except($config, ['colors'])) }},
        i18n: {{ \Illuminate\Support\Js::from($i18n) }},
    };
</script>
@endpush

@section('viewer-empty'){{ __('edit.pick') }}@endsection

@section('price-note'){{ \App\Support\NextStep::text('param.estimate.note') }}@endsection

@section('stage')
    {{-- where the pieces of a split model sit in the whole: a map per level, with the numbers engraved on the pieces --}}
    <div id="edit-map" class="card hidden p-4 text-sm"></div>
    <p class="text-xs text-muted">{{ \App\Support\NextStep::text('edit.'.$op.'.tip') }}</p>
@endsection

@section('panel')
<form id="edit-form" novalidate>
    <x-tool-section id="file" :title="__('toolpage.section.file')">
        <label id="edit-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-ink">
            <input id="edit-file" type="file" class="sr-only" accept="{{ collect($config['formats'])->map(fn ($f) => '.'.$f)->join(',') }}">
            <x-icon name="upload" class="mx-auto h-7 w-7 text-muted" />
            <span class="mt-1 block font-medium text-ink">{{ __('edit.pick') }}</span>
            <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $config['formats'])) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
            <span id="edit-source" class="mt-2 hidden text-sm font-medium text-ink"></span>
        </label>
        <p id="edit-status" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
        <p id="edit-analysis" class="hidden text-sm text-ink" aria-live="polite"></p>
    </x-tool-section>

    <x-tool-section id="settings" :title="__('toolpage.section.settings')">
        @if($op === 'split')
            <fieldset>
                <legend class="lbl">{{ __('edit.c.bed') }}</legend>
                <div class="mt-2 grid gap-2" role="radiogroup">
                    @foreach($choices['bed'] as $i => $o)
                        <label class="tool-choice !justify-start"><input type="radio" name="c-bed" data-choice="bed" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.bed.'.$o) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid grid-cols-3 gap-3" data-when="bed=custom">
                @foreach(['bed_x', 'bed_y', 'bed_z'] as $key)
                    <label class="text-sm font-medium text-ink">{{ __('edit.f.'.$key) }}
                        <span class="tool-unit mt-1" data-unit="mm"><input data-param="{{ $key }}" type="number" inputmode="numeric" min="{{ $fields[$key][0] }}" max="{{ $fields[$key][1] }}" step="1" value="{{ $fields[$key][2] }}" class="field !mt-0"></span>
                    </label>
                @endforeach
            </div>
            {{-- the planes the model is cut by: as many as the bed needs, each one movable; the viewer shows them --}}
            <fieldset id="edit-planes-box" class="hidden">
                <legend class="lbl">{{ __('edit.split.planes') }}</legend>
                <div id="edit-planes" class="mt-2 grid gap-3"></div>
                <button type="button" id="edit-planes-reset" class="chip mt-2 hidden !py-1 text-sm">{{ __('edit.split.planes.reset') }}</button>
            </fieldset>
            <fieldset>
                <legend class="lbl">{{ __('edit.c.joint') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($choices['joint'] as $i => $o)
                        <label class="tool-choice"><input type="radio" name="c-joint" data-choice="joint" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.joint.'.$o) }}</label>
                    @endforeach
                </div>
                <p class="hint mt-1 !text-xs" id="edit-joint-hint"></p>
            </fieldset>
            @foreach($flags as $flag)
                <label class="flex items-start gap-3 text-sm text-ink">
                    <input data-flag="{{ $flag }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array($flag, $flagsOn, true))>
                    <span><span class="font-medium">{{ __('edit.flag.'.$flag) }}</span><br><span class="text-muted">{{ __('edit.flag.'.$flag.'.hint') }}</span></span>
                </label>
            @endforeach
        @endif
        <button id="edit-go" class="btn-ink w-full gap-1.5" disabled><x-icon name="scissors" class="h-4 w-4" />{{ __('edit.'.$op.'.apply') }}</button>
        <p class="text-xs text-muted">{{ __('edit.'.$op.'.hint') }}</p>
    </x-tool-section>

    <x-tool-section id="result" :title="__('toolpage.section.result')">
        <p id="edit-wait" class="text-sm text-muted">{{ __('toolpage.status.empty') }}</p>
        <div id="edit-result" class="hidden">
            <div id="edit-report" class="text-sm text-ink"></div>
            <button id="edit-again" type="button" class="btn-secondary mt-3 w-full">{{ __('edit.again') }}</button>
        </div>
    </x-tool-section>
</form>
@endsection
