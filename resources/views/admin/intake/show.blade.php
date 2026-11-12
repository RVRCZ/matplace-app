@extends('layouts.app', ['title' => 'Zadání z rozcestníku · admin', 'noindex' => true])

@php
    $made = $intake->request;
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
@endphp

@section('content')
@include('admin.nav')
@include('partials.flash')

<a href="{{ route('admin.intake.index') }}" class="inline-flex min-h-11 items-center gap-1 text-sm text-slate-500 hover:text-ink"><x-icon name="arrow-left" class="h-4 w-4" />Rozcestník</a>
<h1 class="text-2xl font-extrabold text-ink">Zadání z {{ $intake->created_at->format('j. n. Y H:i') }} <span class="text-base font-normal text-slate-500">· {{ $intake->locale }} · {{ ['photo' => 'fotka', 'file' => 'soubor', 'text' => 'text'][$intake->kind] ?? $intake->kind }}</span></h1>

{{-- the rules against advertising (docs/AN.md §23.2): why it was held, and the way back; any other wish can be held by hand --}}
@if($intake->status === 'spam')
    <section class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm" id="intake-spam">
        <p class="font-bold text-ink">Zadrženo jako spam, modelu se neposlalo.</p>
        <p class="mt-1 text-slate-600">Pravidla: {{ implode(', ', array_map(fn ($r) => \App\Domain\Intake\IntakeSpam::LABELS[$r] ?? $r, (array) ($intake->answer['spam']['rules'] ?? []))) ?: '—' }}
            · bodů {{ (int) ($intake->answer['spam']['points'] ?? 0) }} · odesílatel dostal {{ ($intake->answer['spam']['reply'] ?? 'held') === 'price' ? 'odpověď o ceně' : 'neutrální odpověď' }}: „{{ $intake->summary }}“</p>
        <form method="post" action="{{ route('admin.intake.spam', $intake) }}" class="mt-3">@csrf<input type="hidden" name="spam" value="0">
            <button class="btn-secondary">Není to spam: vrátit{{ $intake->route === null && $intake->kind !== 'file' ? ' a nechat vyhodnotit' : '' }}</button>
            @if($intake->route === null && $intake->kind !== 'file')<span class="ml-2 text-xs text-slate-500">Vyhodnocení je jedno placené volání modelu.</span>@endif
        </form>
    </section>
@else
    <form method="post" action="{{ route('admin.intake.spam', $intake) }}" class="mt-2 text-right">@csrf<input type="hidden" name="spam" value="1">
        <button class="text-xs text-slate-500 underline hover:text-ink">Označit jako spam</button>
    </form>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-[1fr_1.2fr]">
    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Co přišlo</h2>
        @if($intake->hasPhoto())
            <a href="{{ route('admin.intake.photo', $intake) }}" target="_blank" rel="noopener"><img src="{{ route('admin.intake.photo', $intake) }}" alt="" class="mt-2 max-h-80 rounded-xl border border-line object-contain"></a>
        @elseif($intake->photos_pruned_at)
            <p class="mt-2 text-sm text-slate-500">Fotka smazána {{ $intake->photos_pruned_at->format('j. n. Y') }} (po {{ \App\Models\Intake::PHOTO_DAYS }} dnech).</p>
        @endif
        @if($intake->modelFile)
            <p class="mt-2 text-sm">Soubor: <span class="font-medium">{{ $intake->modelFile->original_name }}</span> <span class="text-slate-500">({{ $intake->modelFile->status }})</span></p>
        @endif
        <p class="mt-3 whitespace-pre-wrap text-sm text-ink">{{ $intake->text ?: '— bez popisu —' }}</p>
        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
            <dt>Účet</dt><dd>{{ $intake->user?->email ?? 'host' }}</dd>
            <dt>Token</dt><dd class="font-mono">{{ $intake->token }}</dd>
            <dt>Stav</dt><dd>{{ ['new' => 'nové', 'opened' => 'nástroj otevřen', 'human' => 'u člověka', 'closed' => 'uzavřeno', 'spam' => 'spam'][$intake->status] ?? $intake->status }}@if($intake->opened_at) · otevřeno {{ $intake->opened_at->format('j. n. H:i') }}@endif</dd>
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Rozhodnutí asistenta</h2>
        <p class="mt-2 text-sm"><span class="font-medium">{{ ['tool' => 'nástroj', 'human' => 'člověk', 'shape' => 'tvar', 'file' => 'kalkulačka'][$intake->route] ?? '—' }}</span>@if($intake->tool_key) · {{ __('tools.'.$intake->tool_key.'.title') }} (<code>{{ $intake->tool_key }}</code>)@endif @if($intake->wants_shape)· <span class="text-warn">chce tvar podle fotky</span>@endif</p>
        <p class="mt-2 rounded-xl bg-studio p-3 text-sm">{{ $intake->summary ?: '—' }}</p>
        @if($intake->params)<p class="mt-2 text-xs text-slate-500">Parametry: <code>{{ json_encode($intake->params, JSON_UNESCAPED_UNICODE) }}</code></p>@endif
        @if(! empty($intake->answer['questions']))<p class="mt-1 text-xs text-slate-500">Otázky: {{ implode(' · ', (array) $intake->answer['questions']) }}</p>@endif
        <p class="mt-2 text-xs text-slate-500">
            model {{ $intake->model ?? '—' }} · jistota {{ $intake->confidence !== null ? number_format((float) $intake->confidence, 2) : '—' }} · {{ $intake->duration_ms ?? '—' }} ms ·
            tokeny {{ $intake->tokens_in ?? '—' }} / {{ $intake->tokens_out ?? '—' }} · {{ $intake->cost_czk !== null ? number_format((float) $intake->cost_czk, 3).' Kč' : '—' }}
            @if(($intake->answer['route'] ?? null) && $intake->answer['route'] !== $intake->route) · model řekl <code>{{ $intake->answer['route'] }}</code>, pravidla rozhodla <code>{{ $intake->route }}</code>@endif
            @if(! empty($intake->answer['failed'])) · <span class="text-danger">volání selhalo: {{ $intake->answer['error'] ?? '' }}</span>@endif
        </p>
        @if(! empty($intake->answer['reason']))<p class="mt-2 text-xs text-slate-500">Proč: {{ $intake->answer['reason'] }}</p>@endif
        @if(! empty($intake->answer['alternatives']))<p class="mt-1 text-xs text-slate-500">Šlo by i: {{ implode(' · ', array_map(fn ($k) => __('tools.'.$k.'.title').' ('.$k.')', (array) $intake->answer['alternatives'])) }}</p>@endif
        @if(($intake->answer['origin'] ?? null) === \App\Domain\Intake\IntakeRouter::ORIGIN_REROUTE)
            <p class="mt-2 text-xs font-semibold text-action-dark">Po tlačítku „Zkusit hned bez návrháře“@if(! empty($intake->answer['forced'])) · model trval na člověku, nástroj vybrán podle {{ ['alternatives' => 'alternativ', 'words' => 'slov zadání', 'kind' => 'druhu vstupu'][$intake->answer['forced']] ?? $intake->answer['forced'] }}@endif</p>
        @endif
        @if(is_array($intake->answer['summary'] ?? null) && $intake->answer['summary'])
            <details class="mt-2 text-xs text-slate-500"><summary class="cursor-pointer">Věty ve třech jazycích</summary>@foreach($intake->answer['summary'] as $l => $s)<p class="mt-1"><b>{{ $l }}</b> {{ $s }}</p>@endforeach</details>
        @endif
        {{-- the decisions before this one (round 2: the original, then every "without a designer") --}}
        @if(! empty($intake->answer['history']))
            <h3 class="mt-3 text-sm font-bold">Historie rozhodnutí</h3>
            <ol class="mt-1 space-y-1 text-xs text-slate-600" id="intake-history">
                @foreach((array) $intake->answer['history'] as $h)
                    <li class="rounded-lg bg-slate-50 p-2">
                        <span class="font-medium">{{ ['initial' => 'původní', 'reroute' => 'po tlačítku bez návrháře'][$h['origin'] ?? ''] ?? ($h['origin'] ?? '') }}</span>
                        @if(! empty($h['at'])) · {{ \Carbon\Carbon::parse($h['at'])->format('j. n. H:i') }}@endif
                        · {{ ['tool' => 'nástroj', 'human' => 'člověk', 'shape' => 'tvar', 'file' => 'kalkulačka'][$h['route'] ?? ''] ?? '—' }}@if(! empty($h['tool_key'])) · {{ __('tools.'.$h['tool_key'].'.title') }} (<code>{{ $h['tool_key'] }}</code>)@endif
                        @if(! empty($h['wants_shape'])) · chtěl tvar podle fotky @endif
                        · jistota {{ number_format((float) ($h['confidence'] ?? 0), 2) }}
                        @if(! empty($h['answer']['reason'])) · proč: {{ $h['answer']['reason'] }}@endif
                        @if(! empty($h['answer']['alternatives'])) · šlo by i: {{ implode(', ', (array) $h['answer']['alternatives']) }}@endif
                        <br><span class="text-slate-500">{{ $h['summary'] ?? '' }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>

<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4" id="intake-request">
    <h2 class="font-bold">Poptávka člověku</h2>
    @if(! $made)
        <p class="mt-2 text-sm text-slate-500">Zákazník nenechal e-mail (nástroj otevřel sám, nebo odešel).</p>
    @else
        <p class="mt-2 text-sm"><span class="font-medium">{{ $made->email }}</span>@if($made->name) · {{ $made->name }}@endif @if($made->phone) · {{ $made->phone }}@endif · souhlas {{ $made->consent_at->format('j. n. Y H:i') }} ·
            <span class="{{ $made->status === 'new' ? 'font-semibold text-action-dark' : '' }}">{{ ['new' => 'čeká na odpověď', 'answered' => 'odpovězeno', 'closed' => 'uzavřeno'][$made->status] ?? $made->status }}</span></p>
        @if($made->reply)
            <div class="mt-3 rounded-xl bg-ok-soft p-3 text-sm"><p class="text-xs text-ok">Odpověď {{ $made->replied_at?->format('j. n. Y H:i') }} · {{ $made->repliedBy?->name ?? '' }}</p><p class="mt-1 whitespace-pre-wrap">{{ $made->reply }}</p></div>
        @endif
        <form method="post" action="{{ route('admin.intake.reply', $intake) }}" class="mt-3 grid gap-2">
            @csrf
            <label class="block text-xs font-semibold text-slate-600">Předmět<input name="subject" required maxlength="150" value="{{ old('subject', __('router.mail.reply.subject', [], $made->locale)) }}" class="{{ $in }}"></label>
            <label class="block text-xs font-semibold text-slate-600">Text e-mailu ({{ $made->locale }}; odstavce prázdným řádkem)<textarea name="text" required rows="8" maxlength="5000" class="{{ $in }}">{{ old('text') }}</textarea></label>
            <div class="flex flex-wrap items-center gap-2">
                <button class="btn-primary text-sm">Odeslat odpověď</button>
                <span class="text-xs text-slate-500">Jeden e-mail zákazníkovi na {{ $made->email }}; nabídku s cenou napište do textu.</span>
            </div>
        </form>
        <form method="post" action="{{ route('admin.intake.status', $intake) }}" class="mt-3 flex flex-wrap gap-2">
            @csrf
            @if($made->status !== 'closed')
                <button name="status" value="closed" class="btn-quiet text-sm">Uzavřít bez odpovědi</button>
            @else
                <button name="status" value="new" class="btn-quiet text-sm">Otevřít znovu</button>
            @endif
        </form>
    @endif
</section>
@endsection
