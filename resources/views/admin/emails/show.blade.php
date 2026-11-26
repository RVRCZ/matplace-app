@extends('layouts.app', ['title' => 'E-mail · admin', 'noindex' => true])

@php $editable = in_array($email->status, ['draft', 'approved'], true); @endphp

@section('content')
@include('admin.nav')
@include('partials.flash')

<p class="mt-2 text-sm"><a href="{{ route('admin.emails.index', ['status' => $email->status]) }}" class="underline">← E-maily</a></p>
<p class="mt-2 text-sm text-slate-600">
    {{ ['draft' => 'Koncept, čeká na schválení', 'approved' => 'Schválený, odeslání se nepodařilo', 'sent' => 'Odeslaný', 'rejected' => 'Zamítnutý, neodešel'][$email->status] ?? $email->status }}
    · {{ $email->generated_by_ai ? 'napsala AI' : 'systémové oznámení' }} · jazyk {{ $email->locale }}
    @if($email->sent_at) · odesláno {{ $email->sent_at->format('j. n. Y H:i') }}@endif
    @if($email->approver) · {{ $email->status === 'rejected' ? 'zamítl' : 'schválil' }} {{ $email->approver->name }}@endif
</p>
@if($email->instruction)<p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">Zadání pro AI: {{ $email->instruction }}</p>@endif
@if($email->isReply())
    <section class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
        <h2 class="text-xs font-bold uppercase text-slate-500">Odpověď na zprávu ze schránky</h2>
        @if($original)
            <p class="mt-1"><strong>{{ $original['from_name'] ?: $original['from'] }}</strong> &lt;{{ $original['from'] }}&gt; · {{ $original['date'] }}</p>
            <p class="font-semibold">{{ $original['subject'] }}</p>
            <div class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap text-sm text-slate-800">{{ trim((string) ($original['fresh'] ?? '')) !== '' ? $original['fresh'] : $original['text'] }}</div>
            @php $pics = array_values(array_filter($original['attachments'] ?? [], fn ($f) => str_starts_with($f['mime'], 'image/'))); @endphp
            @if($pics)
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($pics as $f)
                        <a href="{{ route('admin.emails.inbox.file', [$original['id'], $f['id']]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.emails.inbox.file', [$original['id'], $f['id']]) }}" alt="{{ $f['filename'] }}" loading="lazy" class="h-28 w-28 rounded-lg border border-slate-200 bg-white object-cover"></a>
                    @endforeach
                </div>
            @endif
            @if(trim((string) ($original['quoted'] ?? '')) !== '')
                <details class="mt-2 text-xs text-slate-600"><summary class="cursor-pointer">Citovaný e-mail pod zprávou</summary><pre class="mt-1 max-h-60 overflow-auto whitespace-pre-wrap font-sans">{{ $original['quoted'] }}</pre></details>
            @endif
            <a href="{{ route('admin.emails.inbox.show', $original['id']) }}" class="mt-1 inline-block text-xs underline">zpráva ve schránce</a>
        @else
            <p class="mt-1 text-xs text-slate-500">Původní zprávu se teď nepodařilo načíst; odpověď odejde ve vlákně i tak.</p>
        @endif
    </section>
@endif

<form method="post" action="{{ route('admin.emails.update', $email->id) }}" enctype="multipart/form-data" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
    <label class="lbl">Komu<input name="to" type="email" required maxlength="190" value="{{ old('to', $email->to) }}" @disabled(! $editable) class="field"></label>
    <label class="lbl mt-3">Předmět<input name="subject" required maxlength="250" value="{{ old('subject', $email->subject) }}" @disabled(! $editable) class="field"></label>
    <label class="lbl mt-3">Text<textarea id="mail-body" name="body" rows="14" required maxlength="20000" @disabled(! $editable) class="field">{{ old('body', $email->body) }}</textarea></label>

    {{-- links and pictures of the orders this e-mail is about (docs/AU.md §8) --}}
    @if($editable && $orders->isNotEmpty())
        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-slate-500">Vložit odkaz na zakázku:</span>
            @foreach($orders as $o)
                <button type="button" class="chip" data-insert="{{ route('farm.orders.show', $o) }}">{{ $o->number }}</button>
            @endforeach
        </div>
    @endif

    @php $attached = $email->files(); @endphp
    @if($attached)
        <div class="mt-4">
            <h2 class="text-xs font-bold uppercase text-slate-500">Přílohy, které odejdou</h2>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach($attached as $f)
                    <div class="w-32 text-xs">
                        <a href="{{ route('admin.emails.file', [$email->id, $f['name']]) }}" target="_blank" rel="noopener">
                            @if(str_starts_with($f['mime'], 'image/'))<img src="{{ route('admin.emails.file', [$email->id, $f['name']]) }}" alt="{{ $f['name'] }}" class="h-28 w-32 rounded-lg border border-slate-200 bg-slate-50 object-cover">@else<span class="flex h-28 w-32 items-center justify-center rounded-lg border border-slate-200 bg-slate-50">📄 PDF</span>@endif
                        </a>
                        <span class="block truncate">{{ $f['name'] }}</span>
                        @if($editable)<label class="flex items-center gap-1 text-red-700"><input type="checkbox" name="remove[]" value="{{ $f['name'] }}"> odebrat</label>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($editable)
        <div class="mt-4 rounded-xl bg-slate-50 p-3">
            <h2 class="text-xs font-bold uppercase text-slate-500">Přiložit</h2>
            @foreach($orders as $o)
                @php $pics = $pictures[$o->id] ?? []; @endphp
                @if($pics)
                    <p class="mt-2 text-xs font-semibold">{{ $o->number }}</p>
                    <div class="mt-1 flex flex-wrap gap-3">
                        @foreach($pics as $kind => $path)
                            <label class="w-32 cursor-pointer text-xs">
                                <img src="{{ route('admin.emails.order_picture', [$o, $kind]) }}" alt="{{ $kinds[$kind] }}" loading="lazy" class="h-28 w-32 rounded-lg border border-slate-200 bg-white object-cover">
                                <span class="mt-1 flex items-start gap-1"><input type="checkbox" name="pictures[]" value="{{ $o->id }}:{{ $kind }}" class="mt-0.5"> {{ $kinds[$kind] }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            @endforeach
            @if($orders->isNotEmpty() && collect($pictures)->flatten()->isEmpty())
                <p class="mt-1 text-xs text-slate-500">K objednávkám {{ $orders->pluck('number')->implode(', ') }} zatím nejsou uložené obrázky (náhled modelu se kreslí, když přijde e-mail, nebo při úklidu objednávky).</p>
            @endif
            <label class="lbl mt-3">Vlastní obrázek nebo PDF z počítače <span class="font-normal text-muted">nejvýš {{ \App\Models\OutgoingEmail::MAX_FILES }} příloh, každá do {{ (int) (\App\Models\OutgoingEmail::MAX_FILE_KB / 1024) }} MB</span>
                <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" class="field">
            </label>
            <p class="mt-1 text-xs text-slate-500">Zaškrtnuté obrázky a vybrané soubory se přidají po „Jen uložit změny“ (nebo rovnou odejdou se „Schválit a odeslat“).</p>
        </div>
    @endif
    @if($editable)
        <div class="mt-3 flex flex-wrap gap-2">
            <button name="action" value="approve" class="btn-primary text-sm">Schválit a odeslat</button>
            <button name="action" value="save" class="btn-quiet text-sm">Jen uložit změny</button>
            <button name="action" value="reject" class="btn-quiet text-sm text-red-700" onclick="return confirm('Zamítnout? E-mail neodejde.')">Zamítnout</button>
        </div>
        <p class="mt-2 text-xs text-slate-500">Odejde text, který je teď ve formuláři, s přílohami výše.</p>
    @endif
</form>
<script>
    // "insert the order's link": at the cursor, else at the end before the signature line
    document.querySelectorAll('[data-insert]').forEach(function (b) {
        b.addEventListener('click', function () {
            var t = document.getElementById('mail-body'); if (!t) return;
            var link = b.getAttribute('data-insert'), s = t.selectionStart, e = t.selectionEnd;
            var text = (s !== undefined && s !== t.value.length) ? link : '\n\nZakázku najdete zde: ' + link;
            t.value = t.value.slice(0, s) + text + t.value.slice(e); t.focus(); t.selectionStart = t.selectionEnd = s + text.length;
        });
    });
</script>
@endsection
