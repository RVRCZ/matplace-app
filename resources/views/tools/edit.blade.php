@php
    // the texts the page's script needs: the tool's own first (edit.<op>.<key>), the shared ones as a fallback (edit.<key>)
    $keys = ['pick', 'bad_format', 'too_big', 'uploading', 'processing', 'model_failed', 'failed', 'unavailable', 'analysing', 'fits', 'plan', 'plan.hollow', 'plan.solid', 'too_many', 'auto', 'plane.x', 'plane.y', 'plane.z', 'planes.reset',
        'working', 'stage.queued', 'stage.loading', 'stage.thinning', 'stage.repairing', 'stage.measuring', 'stage.hollowing', 'stage.cutting', 'stage.joints', 'stage.numbers', 'stage.layout', 'stage.done',
        'report', 'report.keys', 'report.pins', 'report.none', 'report.repaired', 'report.drains', 'report.coarse', 'report.hollow', 'report.solid', 'report.one', 'map', 'map.level', 'piece', 'piece.size', 'piece.down.x', 'piece.down.y', 'piece.down.z', 'part.pins', 'part.keys', 'part.piece', 'part.body', 'glue', 'again',
        'joint.pins', 'joint.dovetail', 'joint.none', 'report.tabs', 'report.frame', 'part.frame', 'report.wall', 'report.thin', 'report.floor', 'report.cork', 'report.label', 'report.solid', 'part.body', 'part.cork', 'part.label', 'part.segment', 'report.joints', 'report.none',
        'warn.does_not_fit', 'warn.many_pieces', 'warn.no_room_for_pins', 'warn.key_as_pins', 'warn.too_big', 'warn.nothing_to_hollow', 'warn.no_room_for_drain', 'warn.coarse_grid', 'warn.tall_gets_pins', 'warn.piece_split', 'warn.too_thin_for_pins',
        'error.fits_already', 'error.too_heavy', 'error.too_small', 'error.too_big', 'error.not_watertight', 'error.edit_failed', 'error.too_tall', 'error.pieces_too_small', 'warn.wall_thin', 'error.too_short', 'warn.small_foot', 'warn.solid_bottle', 'warn.label_failed', 'warn.joint_no_room', 'warn.segment_split', 'error.segments_too_short',
        'found', 'none', 'not_3mf', 'majority', 'filament', 'report.parts', 'part.color', 'warn.inlay_failed', 'warn.recess_failed', 'warn.body_open', 'warn.many_colors', 'error.no_colors', 'error.not_3mf', 'error.empty', 'error.empty_result', 'report.drain.grooves', 'report.drain.grid', 'report.drain.ribs', 'report.drain.none', 'report.already', 'report.cuts', 'warn.no_opening'];
    $i18n = collect(['check.page.max', 'toolpage.status.empty', 'param.too_fast'])->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $config['max_upload_mb']])])
        ->merge(collect($keys)->mapWithKeys(fn ($k) => ['edit.'.$op.'.'.$k => \Illuminate\Support\Facades\Lang::has('edit.'.$op.'.'.$k) ? \App\Support\NextStep::text('edit.'.$op.'.'.$k) : \App\Support\NextStep::text('edit.'.$k)]))->all();
    $sections = ['file' => __('toolpage.section.file'), 'settings' => __('toolpage.section.settings'), 'result' => __('toolpage.section.result')];
    $icon = ['split' => 'scissors', 'hollow' => 'box', 'life_size' => 'maximize', 'scale' => 'maximize', 'puzzle' => 'grid-3x3', 'holder' => 'box', 'potion' => 'sparkles', 'flexi_cut' => 'link', 'colors' => 'palette', 'soap' => 'box', 'wearable' => 'person-standing'][$op] ?? 'box';
    // only a 3MF carries colours: the splitter's page takes nothing else
    $formats = $op === 'colors' ? ['3mf'] : $config['formats'];
@endphp

@extends('tools.page', ['tool' => ['holder' => 'holder_model', 'soap' => 'soap_model'][$op] ?? $op, 'module' => 'edit', 'lead' => __('edit.'.$op.'.lead'), 'available' => $available, 'unavailable' => __('edit.unavailable'), 'goLabel' => \App\Support\NextStep::text('param.go'),
    'sections' => $sections])

@push('head')
<script>
    window.MP_EDIT = {
        op: @json($op),
        upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), parts: @json(url('/api/tools/edit')), home: @json(route('home')), from: @json($from),
        formats: {{ \Illuminate\Support\Js::from($formats) }}, maxMb: {{ (int) $config['max_upload_mb'] }},
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
            <input id="edit-file" type="file" class="sr-only" accept="{{ collect($formats)->map(fn ($f) => '.'.$f)->join(',') }}">
            <x-icon name="upload" class="mx-auto h-7 w-7 text-muted" />
            <span class="mt-1 block font-medium text-ink">{{ __('edit.pick') }}</span>
            <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $formats)) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
            <span id="edit-source" class="mt-2 hidden text-sm font-medium text-ink"></span>
        </label>
        <p id="edit-status" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
        <p id="edit-analysis" class="hidden text-sm text-ink" aria-live="polite"></p>
    </x-tool-section>

    <x-tool-section id="settings" :title="__('toolpage.section.settings')">
        @if($op === 'life_size')
            @include('tools._num', ['key' => 'height_cm', 'f' => $fields['height_cm'], 'label' => __('edit.f.height_cm'), 'unit' => 'cm', 'when' => ''])
        @endif
        @if($op === 'hollow')
            <div class="grid gap-3">
                @include('tools._num', ['key' => 'wall', 'f' => $fields['wall'], 'label' => __('edit.f.wall'), 'unit' => 'mm', 'when' => ''])
            </div>
        @endif
        @if($op === 'holder')
            <fieldset>
                <legend class="lbl">{{ __('edit.c.cavity') }}</legend>
                <div class="mt-2 grid gap-2" role="radiogroup">
                    @foreach($choices['cavity'] as $i => $o)
                        <label class="tool-choice !justify-start"><input type="radio" name="c-cavity" data-choice="cavity" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.cavity.'.$o) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid grid-cols-2 gap-3" data-when="cavity=custom">
                @foreach(['cav_d', 'cav_d2'] as $key)
                    <label class="text-sm font-medium text-ink">{{ __('edit.f.'.$key) }}
                        <span class="tool-unit mt-1" data-unit="mm"><input data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $fields[$key][0] }}" max="{{ $fields[$key][1] }}" step="{{ $fields[$key][3] }}" value="{{ $fields[$key][2] }}" class="field !mt-0"></span>
                    </label>
                @endforeach
            </div>
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="cav_depth_own" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink">
                <span><span class="font-medium">{{ __('edit.flag.cav_depth_own') }}</span><br><span class="text-muted">{{ __('edit.flag.cav_depth_own.hint') }}</span></span>
            </label>
            <div class="grid gap-3" data-when="cav_depth_own=on">
                @include('tools._num', ['key' => 'cav_depth', 'f' => $fields['cav_depth'], 'label' => __('edit.f.cav_depth'), 'unit' => 'mm', 'when' => ''])
            </div>
            <div class="grid gap-3">
                @include('tools._num', ['key' => 'height', 'f' => $fields['height'], 'label' => __('edit.f.height.holder'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'clearance', 'f' => $fields['clearance'], 'label' => __('edit.f.clearance.holder'), 'unit' => 'mm', 'when' => ''])
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach(['cav_x', 'cav_y'] as $key)
                    <label class="text-sm font-medium text-ink">{{ __('edit.f.'.$key) }}
                        <span class="tool-unit mt-1" data-unit="mm"><input data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $fields[$key][0] }}" max="{{ $fields[$key][1] }}" step="1" value="{{ $fields[$key][2] }}" class="field !mt-0"></span>
                    </label>
                @endforeach
            </div>
        @endif
        @if($op === 'flexi_cut')
            <fieldset>
                <legend class="lbl">{{ __('edit.c.axis') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($choices['axis'] as $i => $o)
                        <label class="tool-choice"><input type="radio" name="c-axis" data-choice="axis" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.axis.'.$o) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid gap-3">
                @include('tools._num', ['key' => 'segments', 'f' => $fields['segments'], 'label' => __('edit.f.segments'), 'unit' => '', 'when' => ''])
                @include('tools._num', ['key' => 'ball_d', 'f' => $fields['ball_d'], 'label' => __('edit.f.ball_d'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'clearance', 'f' => $fields['clearance'], 'label' => __('edit.f.clearance.flexi'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'height', 'f' => $fields['height'], 'label' => __('edit.f.height.flexi'), 'unit' => 'mm', 'when' => ''])
            </div>
        @endif
        @if($op === 'colors')
            <div class="grid gap-3">
                @include('tools._num', ['key' => 'depth', 'f' => $fields['depth'], 'label' => __('edit.f.depth'), 'unit' => 'mm', 'when' => ''])
            </div>
            <p class="text-sm text-muted">{{ __('edit.colors.depth.hint') }}</p>
        @endif
        @if($op === 'soap')
            @foreach(['foot', 'drain'] as $choice)
                <fieldset>
                    <legend class="lbl">{{ __('edit.c.'.$choice) }}</legend>
                    <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                        @foreach($choices[$choice] as $i => $o)
                            <label class="tool-choice"><input type="radio" name="c-{{ $choice }}" data-choice="{{ $choice }}" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.'.$choice.'.'.$o) }}</label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <div class="grid gap-3">
                @include('tools._num', ['key' => 'height', 'f' => $fields['height'], 'label' => __('edit.f.height.soap'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'clearance', 'f' => $fields['clearance'], 'label' => __('edit.f.clearance.soap'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'wall', 'f' => $fields['wall'], 'label' => __('edit.f.wall.soap'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'floor', 'f' => $fields['floor'], 'label' => __('edit.f.floor'), 'unit' => 'mm', 'when' => ''])
            </div>
        @endif
        @if($op === 'wearable')
            <fieldset>
                <legend class="lbl">{{ __('edit.c.measure') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($choices['measure'] as $i => $o)
                        <label class="tool-choice"><input type="radio" name="c-measure" data-choice="measure" data-preset="{{ \App\Domain\Tools\ModelEditor::MEASURES[$o] ?? '' }}" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.measure.'.$o) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid gap-3" data-when="measure=head,chest,waist,arm,forearm,wrist,thigh,calf">
                @include('tools._num', ['key' => 'circumference', 'f' => $fields['circumference'], 'label' => __('edit.f.circumference'), 'unit' => 'cm', 'when' => ''])
                @include('tools._num', ['key' => 'play', 'f' => $fields['play'], 'label' => __('edit.f.play'), 'unit' => 'mm', 'when' => ''])
                <p class="hint !text-xs">{{ __('edit.wearable.measure.hint') }}</p>
            </div>
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="hollow" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" checked>
                <span><span class="font-medium">{{ __('edit.wearable.flag.hollow') }}</span><br><span class="text-muted">{{ __('edit.wearable.flag.hollow.hint') }}</span></span>
            </label>
            <div class="grid gap-3" data-when="hollow=on">
                @include('tools._num', ['key' => 'wall', 'f' => $fields['wall'], 'label' => __('edit.f.wall.wearable'), 'unit' => 'mm', 'when' => ''])
            </div>
            <fieldset>
                <legend class="lbl">{{ __('edit.c.windows') }}</legend>
                <p class="hint !text-xs">{{ __('edit.wearable.windows.hint') }}</p>
                <div class="mt-2 grid gap-2">
                    @for($i = 0; $i < 4; $i++)
                        <div class="rounded-lg border border-line p-2 text-sm" data-window="{{ $i }}">
                            <label class="flex items-center gap-2 font-medium text-ink"><input type="checkbox" data-win="on" class="h-5 w-5 accent-ink" @checked($i === 0)> {{ __('edit.wearable.window', ['n' => $i + 1]) }}</label>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <label class="text-xs text-muted">{{ __('edit.c.side') }}<select data-win="side" class="field !mt-0.5">@foreach(\App\Domain\Tools\ModelEditor::SIDES as $s)<option value="{{ $s }}">{{ __('edit.o.side.'.$s) }}</option>@endforeach</select></label>
                                <label class="text-xs text-muted">{{ __('edit.c.wshape') }}<select data-win="shape" class="field !mt-0.5"><option value="rect">{{ __('edit.o.wshape.rect') }}</option><option value="ellipse">{{ __('edit.o.wshape.ellipse') }}</option></select></label>
                                @foreach(['w' => 60, 'h' => 30, 'dx' => 0, 'dy' => 0] as $k => $v)
                                    <label class="text-xs text-muted">{{ __('edit.f.win_'.$k) }}<span class="tool-unit mt-0.5" data-unit="mm"><input data-win="{{ $k }}" type="number" inputmode="decimal" min="{{ in_array($k, ['w', 'h'], true) ? 5 : -150 }}" max="{{ in_array($k, ['w', 'h'], true) ? 300 : 150 }}" step="1" value="{{ $v }}" class="field !mt-0"></span></label>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
                <input type="hidden" data-text="windows" value="">
            </fieldset>
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="straps" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink">
                <span><span class="font-medium">{{ __('edit.flag.straps') }}</span><br><span class="text-muted">{{ __('edit.flag.straps.hint') }}</span></span>
            </label>
            <div class="grid gap-3" data-when="straps=on">
                @include('tools._num', ['key' => 'strap_h', 'f' => $fields['strap_h'], 'label' => __('edit.f.strap_h'), 'unit' => '%', 'when' => ''])
            </div>
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="split" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" checked>
                <span><span class="font-medium">{{ __('edit.flag.split') }}</span><br><span class="text-muted">{{ __('edit.flag.split.hint') }}</span></span>
            </label>
            <div class="grid gap-3" data-when="split=on">
                <fieldset>
                    <legend class="lbl">{{ __('edit.c.joint') }}</legend>
                    <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                        @foreach($choices['joint'] as $i => $o)
                            <label class="tool-choice"><input type="radio" name="c-joint" data-choice="joint" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.joint.'.$o) }}</label>
                        @endforeach
                    </div>
                </fieldset>
                @foreach(['numbers', 'lay'] as $flag)
                    <label class="flex items-start gap-3 text-sm text-ink">
                        <input data-flag="{{ $flag }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" checked>
                        <span><span class="font-medium">{{ __('edit.flag.'.$flag) }}</span><br><span class="text-muted">{{ __('edit.flag.'.$flag.'.hint') }}</span></span>
                    </label>
                @endforeach
            </div>
        @endif
        @if($op === 'potion')
            <div class="grid gap-3">
                @include('tools._num', ['key' => 'height', 'f' => $fields['height'], 'label' => __('edit.f.height.potion'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'neck_d', 'f' => $fields['neck_d'], 'label' => __('edit.f.neck_d'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'neck_h', 'f' => $fields['neck_h'], 'label' => __('edit.f.neck_h'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'wall', 'f' => $fields['wall'], 'label' => __('edit.f.wall'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'cut', 'f' => $fields['cut'], 'label' => __('edit.f.cut'), 'unit' => '%', 'when' => ''])
            </div>
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="label" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" checked>
                <span><span class="font-medium">{{ __('edit.flag.label') }}</span><br><span class="text-muted">{{ __('edit.flag.label.hint') }}</span></span>
            </label>
            <label class="text-sm font-medium text-ink" data-when="label=on">{{ __('edit.t.text') }}
                <input data-text="text" maxlength="20" value="{{ __('edit.t.text.sample') }}" class="field">
            </label>
        @endif
        @if($op === 'puzzle')
            <div class="grid grid-cols-2 gap-3">
                @foreach(['cols', 'rows'] as $key)
                    <label class="text-sm font-medium text-ink">{{ __('edit.f.'.$key) }}
                        <span class="tool-unit mt-1" data-unit=""><input data-param="{{ $key }}" type="number" inputmode="numeric" min="{{ $fields[$key][0] }}" max="{{ $fields[$key][1] }}" step="1" value="{{ $fields[$key][2] }}" class="field !mt-0"></span>
                    </label>
                @endforeach
            </div>
            <fieldset>
                <legend class="lbl">{{ __('edit.c.lock') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($choices['lock'] as $i => $o)
                        <label class="tool-choice"><input type="radio" name="c-lock" data-choice="lock" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.lock.'.$o) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid gap-3" data-when="lock=tabs">
                @include('tools._num', ['key' => 'knob', 'f' => $fields['knob'], 'label' => __('edit.f.knob'), 'unit' => '%', 'when' => ''])
                @include('tools._num', ['key' => 'clearance', 'f' => $fields['clearance'], 'label' => __('edit.f.clearance'), 'unit' => 'mm', 'when' => ''])
            </div>
        @endif
        @if(isset($choices['bed']))
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
        @endif
        @if($op === 'split')
            {{-- the planes the model is cut by: as many as the bed needs, each one movable; the viewer shows them --}}
            <fieldset id="edit-planes-box" class="hidden">
                <legend class="lbl">{{ __('edit.split.planes') }}</legend>
                <div id="edit-planes" class="mt-2 grid gap-3"></div>
                <button type="button" id="edit-planes-reset" class="chip mt-2 hidden !py-1 text-sm">{{ __('edit.split.planes.reset') }}</button>
            </fieldset>
        @endif
        @if(isset($choices['joint']))
            <fieldset>
                <legend class="lbl">{{ __('edit.c.joint') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($choices['joint'] as $i => $o)
                        <label class="tool-choice"><input type="radio" name="c-joint" data-choice="joint" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.joint.'.$o) }}</label>
                    @endforeach
                </div>
                <p class="hint mt-1 !text-xs" id="edit-joint-hint"></p>
            </fieldset>
        @endif
        @if($op === 'hollow' || $op === 'holder' || $op === 'potion' || $op === 'flexi_cut' || $op === 'colors' || $op === 'soap' || $op === 'wearable')
            @if($op === 'hollow')
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="drain" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" checked>
                <span><span class="font-medium">{{ __('edit.flag.drain') }}</span><br><span class="text-muted">{{ __('edit.flag.drain.hint') }}</span></span>
            </label>
            <div class="grid grid-cols-2 gap-3" data-when="drain=on">
                @foreach(['drain_d', 'drains'] as $key)
                    <label class="text-sm font-medium text-ink">{{ __('edit.f.'.$key) }}
                        <span class="tool-unit mt-1" data-unit="{{ $key === 'drains' ? '' : 'mm' }}"><input data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $fields[$key][0] }}" max="{{ $fields[$key][1] }}" step="{{ $fields[$key][3] }}" value="{{ $fields[$key][2] }}" class="field !mt-0"></span>
                    </label>
                @endforeach
            </div>
            @endif
        @else
            @foreach($flags as $flag)
                <label class="flex items-start gap-3 text-sm text-ink">
                    <input data-flag="{{ $flag }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array($flag, $flagsOn, true))>
                    <span><span class="font-medium">{{ __('edit.flag.'.$flag) }}</span><br><span class="text-muted">{{ __('edit.flag.'.$flag.'.hint') }}</span></span>
                </label>
            @endforeach
        @endif
        <button id="edit-go" class="btn-ink w-full gap-1.5" disabled><x-icon :name="$icon" class="h-4 w-4" />{{ __('edit.'.$op.'.apply') }}</button>
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
