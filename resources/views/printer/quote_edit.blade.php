@extends('layouts.app', ['title' => $quote->number.' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    @include('printer.nav')
    @include('partials.flash')

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('quote.pdf.title') }} {{ $quote->number }} <span class="rounded-full bg-slate-100 px-2 py-0.5 align-middle text-xs font-medium text-slate-600">{{ __('quote.status.'.$quote->status) }}</span></h1>
        <div class="flex gap-2 text-sm">
            @if($quote->status !== 'draft')<a href="{{ route('quote.public', $quote) }}" target="_blank" class="rounded-full border border-slate-300 bg-white px-3 py-1.5">{{ __('quote.open_link') }}</a>@endif
            <a href="{{ route('printer.quotes.pdf', $quote) }}" target="_blank" class="rounded-full border border-slate-300 bg-white px-3 py-1.5">PDF</a>
            <form method="post" action="{{ route('printer.quotes.duplicate', $quote) }}">@csrf<button class="rounded-full border border-slate-300 bg-white px-3 py-1.5">{{ __('quote.repeat') }}</button></form>
        </div>
    </div>

    <form method="post" action="{{ route('printer.quotes.update', $quote) }}" id="quote-form" class="mt-4 grid gap-4 lg:grid-cols-[1fr_320px]">
        @csrf
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold">{{ __('quote.client_name') }}<input name="client_name" value="{{ old('client_name', $quote->client_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('quote.client_email') }}<input name="client_email" type="email" value="{{ old('client_email', $quote->client_email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold sm:col-span-2">{{ __('quote.title') }}<input name="title" value="{{ old('title', $quote->title) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            </div>

            <table class="mt-4 w-full text-sm" id="lines">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-1">{{ __('quote.pdf.item') }}</th><th class="w-20 py-1 text-right">{{ __('quote.pdf.qty') }}</th><th class="w-28 py-1 text-right">{{ __('quote.pdf.unit') }}</th><th class="w-24 py-1 text-right">{{ __('quote.pdf.total') }}</th><th class="w-8"></th></tr></thead>
                <tbody>
                @foreach(old('lines', $quote->lines) as $i => $l)
                    <tr class="line border-t border-slate-100">
                        <td class="py-1 pr-2"><input name="lines[{{ $i }}][label]" value="{{ $l['label'] }}" required class="w-full rounded border border-slate-200 px-2 py-1"></td>
                        <td class="py-1 pr-2"><input name="lines[{{ $i }}][qty]" type="number" step="0.01" value="{{ $l['qty'] }}" required class="w-full rounded border border-slate-200 px-2 py-1 text-right qty"></td>
                        <td class="py-1 pr-2"><input name="lines[{{ $i }}][unit_price]" type="number" step="0.01" value="{{ $l['unit_price'] }}" required class="w-full rounded border border-slate-200 px-2 py-1 text-right unit"></td>
                        <td class="py-1 text-right font-medium total-cell">{{ number_format((float) $l['qty'] * (float) $l['unit_price'], 0, ',', ' ') }}</td>
                        <td class="py-1 text-right"><button type="button" class="remove text-slate-400 hover:text-red-600" title="×">×</button></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="5" class="pt-2"><button type="button" id="add-line" class="text-sm text-teal-700">+ {{ __('quote.add_line') }}</button></td></tr>
                    <tr><td colspan="3" class="pt-3 text-right text-lg font-bold">{{ __('quote.pdf.sum') }}</td><td class="pt-3 text-right text-2xl font-extrabold" id="grand">{{ number_format($quote->total, 0, ',', ' ') }}</td><td></td></tr>
                </tfoot>
            </table>

            <label class="mt-4 block text-sm font-semibold">{{ __('quote.note') }}<textarea name="note" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal">{{ old('note', $quote->note) }}</textarea></label>
        </div>

        <div class="space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <label class="block text-sm font-semibold">{{ __('quote.valid_until') }}<input name="valid_until" type="date" value="{{ old('valid_until', $quote->valid_until?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="mt-3 block text-sm font-semibold">{{ __('quote.lead_time') }} ({{ __('quote.days_short') }})<input name="lead_time_days" type="number" min="0" value="{{ old('lead_time_days', $quote->lead_time_days) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                @if($quote->calculation)
                    <div class="mt-3 text-xs text-slate-500">
                        {{ __('quote.from_calc') }}: <a href="{{ route('printer.calculator.open', $quote->calculation) }}" class="text-teal-700">{{ $quote->calculation->token }}</a>
                        @if($quote->calculation->slicer)<br>{{ $quote->calculation->slicer['grams'] }} g · {{ $quote->calculation->slicer['minutes'] }} min · {{ $quote->calculation->slicer['dims']['x'] }}×{{ $quote->calculation->slicer['dims']['y'] }}×{{ $quote->calculation->slicer['dims']['z'] }} mm@endif
                    </div>
                @endif
            </div>
            <button class="w-full rounded-xl border border-teal-600 bg-white px-4 py-3 font-semibold text-teal-700">{{ __('quote.save') }}</button>
            <button type="submit" form="send-form" class="w-full rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white">{{ $quote->client_email ? __('quote.send_mail') : __('quote.send_link') }}</button>
            <p class="text-xs text-slate-500">{{ __('quote.send_hint') }}</p>
        </div>
    </form>
    <form method="post" action="{{ route('printer.quotes.send', $quote) }}" id="send-form" onsubmit="return window.__quoteSave ? window.__quoteSave(event) : true">@csrf</form>
</div>

<script>
(() => {
    const fmt = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 0 });
    const tbody = document.querySelector('#lines tbody');
    const recalc = () => {
        let sum = 0;
        tbody.querySelectorAll('tr.line').forEach((tr, i) => {
            tr.querySelectorAll('input').forEach((inp) => { inp.name = inp.name.replace(/lines\[\d+\]/, `lines[${i}]`); });
            const q = parseFloat(tr.querySelector('.qty').value) || 0, u = parseFloat(tr.querySelector('.unit').value) || 0;
            const t = Math.round(q * u * 100) / 100;
            tr.querySelector('.total-cell').textContent = fmt.format(t);
            sum += t;
        });
        document.getElementById('grand').textContent = fmt.format(Math.round(sum));
    };
    tbody.addEventListener('input', recalc);
    tbody.addEventListener('click', (e) => { if (e.target.classList.contains('remove') && tbody.querySelectorAll('tr.line').length > 1) { e.target.closest('tr').remove(); recalc(); } });
    document.getElementById('add-line').onclick = () => {
        const i = tbody.querySelectorAll('tr.line').length;
        const tr = document.createElement('tr'); tr.className = 'line border-t border-slate-100';
        tr.innerHTML = `<td class="py-1 pr-2"><input name="lines[${i}][label]" required class="w-full rounded border border-slate-200 px-2 py-1"></td><td class="py-1 pr-2"><input name="lines[${i}][qty]" type="number" step="0.01" value="1" required class="w-full rounded border border-slate-200 px-2 py-1 text-right qty"></td><td class="py-1 pr-2"><input name="lines[${i}][unit_price]" type="number" step="0.01" value="0" required class="w-full rounded border border-slate-200 px-2 py-1 text-right unit"></td><td class="py-1 text-right font-medium total-cell">0</td><td class="py-1 text-right"><button type="button" class="remove text-slate-400 hover:text-red-600">×</button></td>`;
        tbody.appendChild(tr); tr.querySelector('input').focus();
    };
    // "Send" first saves the edited form, then sends (two requests, one click).
    window.__quoteSave = async (e) => {
        e.preventDefault();
        const form = document.getElementById('quote-form');
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
        if (!res.ok && res.status !== 302) { alert('Nepodařilo se uložit.'); return false; }
        e.target.submit();
        return false;
    };
})();
</script>
@endsection
