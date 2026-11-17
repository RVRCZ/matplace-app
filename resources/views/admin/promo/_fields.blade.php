@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
@endphp
<label class="{{ $lb }}">Kód<input name="code" required maxlength="32" value="{{ old('code', $c->code) }}" class="{{ $in }} font-mono uppercase"></label>
<label class="{{ $lb }}">Druh
    <select name="kind" class="{{ $in }}"><option value="percent" @selected(old('kind', $c->kind) === 'percent')>procento z ceny tisku</option><option value="fixed" @selected(old('kind', $c->kind) === 'fixed')>pevná částka v Kč</option></select></label>
<label class="{{ $lb }}">Hodnota (% nebo Kč)<input name="value" type="number" step="0.01" min="0.01" required value="{{ old('value', $c->value) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Nejmenší cena tisku (Kč, bez slevy)<input name="min_order_czk" type="number" step="1" min="0" value="{{ old('min_order_czk', $c->min_order_czk) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Platí od<input name="valid_from" type="date" value="{{ old('valid_from', $c->valid_from?->format('Y-m-d')) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Platí do (včetně)<input name="valid_until" type="date" value="{{ old('valid_until', $c->valid_until?->format('Y-m-d')) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Nejvýš použití (prázdné = bez limitu)<input name="max_uses" type="number" min="1" value="{{ old('max_uses', $c->max_uses) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Patří partnerovi (provize za zakázku jde jemu)
    <select name="partner_id" class="{{ $in }}"><option value="">nikomu</option>@foreach($partners as $p)<option value="{{ $p->id }}" @selected((int) old('partner_id', $c->partner_id) === $p->id)>{{ $p->name }} ({{ $p->code }})</option>@endforeach</select></label>
<label class="flex items-center gap-2 pt-5 text-sm"><input type="checkbox" name="once_per_account" value="1" @checked(old('once_per_account', $c->once_per_account)) class="h-4 w-4 accent-action"> Jednou na účet</label>
<label class="flex items-center gap-2 pt-5 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $c->active)) class="h-4 w-4 accent-action"> Aktivní</label>
<label class="{{ $lb }} sm:col-span-2">Poznámka<input name="note" maxlength="300" value="{{ old('note', $c->note) }}" class="{{ $in }}"></label>
