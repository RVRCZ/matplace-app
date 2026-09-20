@extends('layouts.app', ['title' => $quote->number.' · matplace'])

@php
    $locked = $quote->status === 'accepted';
    $c = fn (string $k, $d = 0) => old('cost.'.$k, $sheet[$k] ?? $d);
    $L = ['cost' => __('quote.sheet.cost'), 'reserve' => __('quote.sheet.reserve'), 'labour' => __('quote.sheet.labour'), 'machine' => __('quote.sheet.machine'), 'suggested' => __('quote.sheet.suggested'), 'profit' => __('quote.sheet.profit'), 'markup' => __('quote.cost.markup'), 'margin' => __('quote.cost.margin'), 'min' => __('quote.sheet.min_applied'), 'unit' => __('quote.sheet.unit')];
    $money = ['material_cost' => 'Kč', 'machine_hours' => 'h', 'machine_rate' => 'Kč/h', 'setup_cost' => 'Kč', 'labour_minutes' => 'min', 'labour_rate' => 'Kč/h', 'failure_pct' => '%'];
@endphp

@section('content')
<div class="mx-auto max-w-5xl">
    @include('printer.nav')
    @include('partials.flash')
    @if($errors->any())<div class="note-error mt-3" role="alert">{{ $errors->first() }}</div>@endif

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold text-ink">{{ __('quote.pdf.title') }} {{ $quote->number }}
            <span class="rounded-full bg-slate-100 px-2 py-0.5 align-middle text-xs font-medium text-muted">{{ __('quote.status.'.$quote->status) }}</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 align-middle text-xs font-medium text-muted">{{ __('quote.version', ['n' => $quote->version]) }}</span>
        </h1>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('printer.quotes.pdf', $quote) }}" target="_blank" class="rounded-full border border-line bg-card px-3 py-1.5">PDF</a>
            <form method="post" action="{{ route('printer.quotes.duplicate', $quote) }}">@csrf<button class="rounded-full border border-line bg-card px-3 py-1.5">{{ __('quote.repeat') }}</button></form>
        </div>
    </div>

    @if($quote->status === 'change' && $quote->change_request)
        <div class="note-warn mt-3"><strong>{{ __('quote.change_received', ['n' => $quote->version]) }}</strong><br>{{ $quote->change_request }}<br><span class="text-sm">{{ __('quote.change_received_hint') }}</span></div>
    @elseif($quote->status === 'accepted')
        <div class="note-ok mt-3">✓ {{ __('quote.accepted_version', ['n' => $quote->accepted_version, 'date' => $quote->accepted_at?->format('j. n. Y H:i')]) }}</div>
    @endif

    <form method="post" action="{{ route('printer.quotes.update', $quote) }}" id="quote-form" class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        <fieldset class="space-y-4" @disabled($locked)>
            {{-- what the customer sees --}}
            <section class="card p-4">
                <h2 class="text-lg font-bold text-ink">{{ __('quote.section.customer') }}</h2>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <label class="lbl">{{ __('quote.client_name') }}<input name="client_name" value="{{ old('client_name', $quote->client_name) }}" class="field"></label>
                    <label class="lbl">{{ __('quote.client_email') }}<input name="client_email" type="email" value="{{ old('client_email', $quote->client_email) }}" class="field"></label>
                    <label class="lbl sm:col-span-2">{{ __('quote.title') }}<input name="title" value="{{ old('title', $quote->title) }}" class="field"></label>
                    <label class="lbl">{{ __('calc.material') }}<input name="material" value="{{ old('material', $quote->params['material'] ?? '') }}" class="field" placeholder="PLA, PETG…"></label>
                    <label class="lbl">{{ __('param.color') }}<input name="color" value="{{ old('color', $quote->color) }}" class="field"></label>
                    <label class="lbl">{{ __('quote.shipping_label') }}<input name="shipping_label" value="{{ old('shipping_label', $quote->shipping_label) }}" class="field" placeholder="{{ __('quote.shipping_ph') }}"></label>
                    <label class="lbl">{{ __('quote.shipping_price') }} <span class="font-normal text-muted">Kč</span><input name="shipping_price" id="shipping-price" type="number" inputmode="decimal" min="0" step="1" value="{{ old('shipping_price', (float) $quote->shipping_price) }}" class="field"></label>
                </div>
                <label class="lbl mt-3">{{ __('quote.note') }} <span class="font-normal text-muted">{{ __('quote.note_hint') }}</span><textarea name="note" rows="3" class="field">{{ old('note', $quote->note) }}</textarea></label>

                <h3 class="mt-4 font-semibold text-ink">{{ __('quote.extras') }} <span class="font-normal text-muted">{{ __('quote.extras_hint') }}</span></h3>
                <table class="mt-1 w-full text-sm" id="lines">
                    <tbody>
                    @foreach(old('lines', $extras) as $i => $l)
                        <tr class="line border-t border-line">
                            <td class="py-1 pr-2"><input name="lines[{{ $i }}][label]" value="{{ $l['label'] }}" required aria-label="{{ __('quote.pdf.item') }}" class="w-full rounded border border-slate-300 px-2 py-1.5"></td>
                            <td class="w-20 py-1 pr-2"><input name="lines[{{ $i }}][qty]" type="number" step="0.01" value="{{ $l['qty'] }}" required aria-label="{{ __('quote.pdf.qty') }}" class="qty w-full rounded border border-slate-300 px-2 py-1.5 text-right"></td>
                            <td class="w-28 py-1 pr-2"><input name="lines[{{ $i }}][unit_price]" type="number" step="0.01" value="{{ $l['unit_price'] }}" required aria-label="{{ __('quote.pdf.unit') }}" class="unit w-full rounded border border-slate-300 px-2 py-1.5 text-right"></td>
                            <td class="w-8 py-1 text-right"><button type="button" class="remove px-2 text-muted hover:text-red-700" aria-label="{{ __('param.hole.remove') }}">×</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button type="button" id="add-line" class="mt-1 text-sm font-semibold text-action-dark underline">+ {{ __('quote.add_line') }}</button>
            </section>

            {{-- what only the printer sees --}}
            <section class="card border-dashed p-4">
                <h2 class="text-lg font-bold text-ink">{{ __('quote.section.internal') }} <span class="rounded-full bg-slate-100 px-2 py-0.5 align-middle text-xs font-medium text-muted">{{ __('quote.internal_badge') }}</span></h2>
                <p class="hint">{{ __('quote.internal_hint') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <label class="lbl">{{ __('calc.quantity') }}<input name="cost[quantity]" data-cost="quantity" type="number" inputmode="numeric" min="1" step="1" value="{{ $c('quantity', 1) }}" class="field"></label>
                    @foreach($money as $k => $u)
                        <label class="lbl">{{ __('quote.cost.'.$k) }} <span class="font-normal text-muted">{{ $u }}</span><input name="cost[{{ $k }}]" data-cost="{{ $k }}" type="number" inputmode="decimal" min="0" step="0.01" value="{{ $c($k) }}" class="field"></label>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr]">
                    <fieldset class="rounded-xl border border-line p-3">
                        <legend class="px-1 text-sm font-semibold text-ink">{{ __('quote.cost.mode') }}</legend>
                        <label class="flex items-start gap-2 text-sm"><input type="radio" name="cost[mode]" value="markup" data-cost="mode" class="mt-1 accent-action" @checked($c('mode', 'markup') === 'markup')><span><strong>{{ __('quote.cost.markup') }}</strong><br><span class="text-muted">{{ __('quote.cost.markup_hint') }}</span></span></label>
                        <label class="mt-2 flex items-start gap-2 text-sm"><input type="radio" name="cost[mode]" value="margin" data-cost="mode" class="mt-1 accent-action" @checked($c('mode', 'markup') === 'margin')><span><strong>{{ __('quote.cost.margin') }}</strong><br><span class="text-muted">{{ __('quote.cost.margin_hint') }}</span></span></label>
                        <label class="lbl mt-2">{{ __('quote.cost.pct') }} <span class="font-normal text-muted">%</span><input name="cost[pct]" data-cost="pct" type="number" inputmode="decimal" min="0" max="1000" step="0.5" value="{{ $c('pct') }}" class="field"></label>
                    </fieldset>
                    <div class="space-y-3">
                        <label class="lbl">{{ __('quote.cost.min_price') }} <span class="font-normal text-muted">Kč</span><input name="cost[min_price]" data-cost="min_price" type="number" inputmode="decimal" min="0" step="1" value="{{ $c('min_price') }}" class="field"></label>
                        <label class="lbl">{{ __('quote.cost.final_price') }} <span class="font-normal text-muted">Kč · {{ __('quote.cost.final_hint') }}</span><input name="cost[final_price]" data-cost="final_price" type="number" inputmode="decimal" min="0" step="1" value="{{ old('cost.final_price', ($sheet['overridden'] ?? false) ? $sheet['final_price'] : '') }}" class="field" placeholder="{{ __('quote.cost.final_ph') }}"></label>
                    </div>
                </div>

                <dl id="sheet" class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1 rounded-xl bg-page p-3 text-sm sm:grid-cols-3" aria-live="polite"></dl>
            </section>
        </fieldset>

        <aside class="space-y-3">
            <div class="card p-4">
                <div class="text-sm text-muted">{{ __('quote.customer_total') }}</div>
                <div id="grand" class="text-3xl font-extrabold text-ink">{{ number_format($quote->total, 0, ',', ' ') }} Kč</div>
                <label class="lbl mt-3">{{ __('quote.valid_until') }}<input name="valid_until" form="quote-form" type="date" value="{{ old('valid_until', $quote->valid_until?->format('Y-m-d')) }}" class="field" @disabled($locked)></label>
                <label class="lbl mt-3">{{ __('quote.lead_time') }} ({{ __('quote.days_short') }})<input name="lead_time_days" form="quote-form" type="number" min="0" value="{{ old('lead_time_days', $quote->lead_time_days) }}" class="field" @disabled($locked)></label>
                @if($quote->calculation)
                    <div class="mt-3 text-xs text-muted">
                        {{ __('quote.from_calc') }}: <a href="{{ route('printer.calculator.open', $quote->calculation) }}" class="text-action-dark underline">{{ $quote->calculation->token }}</a>
                        @if($quote->calculation->slicer)<br>{{ $quote->calculation->slicer['grams'] }} g · {{ $quote->calculation->slicer['minutes'] }} min @endif
                    </div>
                @endif
            </div>

            @unless($locked)
                <button form="quote-form" class="btn-secondary w-full">{{ __('quote.save') }}</button>
                <button type="submit" form="send-form" class="btn-primary w-full">{{ $quote->client_email ? __('quote.send_mail') : __('quote.send_link') }}</button>
                <p class="text-xs text-muted">{{ __('quote.send_hint') }}</p>
            @endunless

            @if($quote->status !== 'draft')
                <div class="card p-4 text-sm">
                    <div class="font-semibold text-ink">{{ __('quote.link.title') }}</div>
                    @if($quote->isRevoked())
                        <p class="mt-1 text-red-800">{{ __('quote.link.revoked') }}</p>
                    @else
                        <input readonly value="{{ route('quote.public', $quote) }}" aria-label="{{ __('quote.link.title') }}" class="field text-xs" onfocus="this.select()">
                        <p class="mt-1 text-xs text-muted">{{ __('quote.link.hint') }}</p>
                    @endif
                    <div class="mt-2 flex flex-wrap gap-2">
                        @unless($quote->isRevoked())<form method="post" action="{{ route('printer.quotes.revoke', $quote) }}">@csrf<button class="rounded-full border border-line bg-card px-3 py-1.5">{{ __('quote.link.revoke') }}</button></form>@endunless
                        <form method="post" action="{{ route('printer.quotes.relink', $quote) }}">@csrf<button class="rounded-full border border-line bg-card px-3 py-1.5">{{ __('quote.link.new') }}</button></form>
                    </div>
                </div>
            @endif

            @if($quote->versions->isNotEmpty())
                <div class="card p-4 text-sm">
                    <div class="font-semibold text-ink">{{ __('quote.history') }}</div>
                    <ul class="mt-1 space-y-1 text-muted">
                        @foreach($quote->versions->sortByDesc('version') as $v)
                            <li>{{ __('quote.version', ['n' => $v->version]) }} · {{ number_format($v->snapshot['total'] ?? 0, 0, ',', ' ') }} Kč · {{ $v->sent_at?->format('j. n. H:i') }}@if($v->accepted_at) · <span class="text-ok">✓ {{ __('quote.status.accepted') }}</span>@elseif($v->change_request) · {{ __('quote.status.change') }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </form>
    <form method="post" action="{{ route('printer.quotes.send', $quote) }}" id="send-form" onsubmit="return window.__quoteSave ? window.__quoteSave(event) : true">@csrf</form>
</div>

<script>
(() => {
    const roundTo = {{ $roundTo }};
    const L = {{ \Illuminate\Support\Js::from($L) }};
    const fmt = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 0 });
    const pct = new Intl.NumberFormat(document.documentElement.lang || 'cs', { maximumFractionDigits: 1 });
    const form = document.getElementById('quote-form');
    const tbody = document.querySelector('#lines tbody');
    const val = (k) => { const el = form.querySelector(`[data-cost="${k}"]${k === 'mode' ? ':checked' : ''}`); return el ? el.value : ''; };
    const num = (k) => Math.max(0, parseFloat(val(k)) || 0);

    // mirrors App\Domain\Quote\CostSheet::compute (the server recomputes on save)
    const recalc = () => {
        const qty = Math.max(1, Math.round(num('quantity')) || 1);
        const machine = num('machine_hours') * num('machine_rate');
        const production = num('material_cost') + machine;
        const reserve = production * num('failure_pct') / 100;
        const labour = num('labour_minutes') / 60 * num('labour_rate');
        const cost = production + reserve + num('setup_cost') + labour;
        const mode = val('mode') || 'markup';
        const p = Math.min(mode === 'margin' ? 95 : 1000, num('pct')) / 100;
        const price = mode === 'margin' ? cost / (1 - p) : cost * (1 + p);
        const rounded = Math.ceil(Math.round(price * 10000) / 10000 / roundTo) * roundTo;
        const suggested = Math.max(num('min_price'), rounded);
        const typed = val('final_price');
        const final = typed !== '' && !isNaN(parseFloat(typed)) ? Math.max(0, parseFloat(typed)) : suggested;
        const profit = final - cost;
        const rows = [
            [L.machine, fmt.format(machine)], [L.reserve, fmt.format(reserve)], [L.labour, fmt.format(labour)],
            [L.cost, `<strong>${fmt.format(cost)}</strong>`], [L.suggested, `<strong>${fmt.format(suggested)}</strong>${num('min_price') > rounded ? ` <span class="text-muted">(${L.min})</span>` : ''}`], [L.unit, fmt.format(final / qty)],
            [L.profit, fmt.format(profit)], [L.markup, cost > 0 ? `${pct.format(profit / cost * 100)} %` : '—'], [L.margin, final > 0 ? `${pct.format(profit / final * 100)} %` : '—'],
        ];
        document.getElementById('sheet').innerHTML = rows.map(([k, v]) => `<div class="flex justify-between gap-2"><dt class="text-muted">${k}</dt><dd class="text-ink">${v}</dd></div>`).join('');
        let extras = 0;
        tbody.querySelectorAll('tr.line').forEach((tr, i) => {
            tr.querySelectorAll('input').forEach((inp) => { inp.name = inp.name.replace(/lines\[\d+\]/, `lines[${i}]`); });
            extras += Math.round((parseFloat(tr.querySelector('.qty').value) || 0) * (parseFloat(tr.querySelector('.unit').value) || 0) * 100) / 100;
        });
        const shipping = Math.max(0, parseFloat(document.getElementById('shipping-price').value) || 0);
        document.getElementById('grand').textContent = `${fmt.format(Math.round(final + extras) + Math.round(shipping))} Kč`;
    };
    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);
    tbody.addEventListener('click', (e) => { if (e.target.classList.contains('remove')) { e.target.closest('tr').remove(); recalc(); } });
    document.getElementById('add-line').onclick = () => {
        const i = tbody.querySelectorAll('tr.line').length;
        const tr = document.createElement('tr'); tr.className = 'line border-t border-line';
        tr.innerHTML = `<td class="py-1 pr-2"><input name="lines[${i}][label]" required class="w-full rounded border border-slate-300 px-2 py-1.5"></td><td class="w-20 py-1 pr-2"><input name="lines[${i}][qty]" type="number" step="0.01" value="1" required class="qty w-full rounded border border-slate-300 px-2 py-1.5 text-right"></td><td class="w-28 py-1 pr-2"><input name="lines[${i}][unit_price]" type="number" step="0.01" value="0" required class="unit w-full rounded border border-slate-300 px-2 py-1.5 text-right"></td><td class="w-8 py-1 text-right"><button type="button" class="remove px-2 text-muted hover:text-red-700">×</button></td>`;
        tbody.appendChild(tr); tr.querySelector('input').focus();
    };
    recalc();
    // "Send" first saves the edited form, then sends (two requests, one click).
    window.__quoteSave = async (e) => {
        e.preventDefault();
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
        if (!res.ok && res.status !== 302) { alert({{ \Illuminate\Support\Js::from(__('quote.save_failed')) }}); return false; }
        e.target.submit();
        return false;
    };
})();
</script>
@endsection
