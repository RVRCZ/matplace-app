@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $id = $p->exists ? 'p'.$p->id : 'new';
@endphp
<label class="{{ $lb }}">Jméno<input name="name" required maxlength="120" value="{{ old('name', $p->name) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Kód (A–Z, 0–9, _ -, nejvýš 20)<input name="code" required maxlength="20" value="{{ old('code', $p->code) }}" class="{{ $in }} font-mono uppercase"></label>
<label class="{{ $lb }}">E-mail<input name="email" type="email" required maxlength="190" value="{{ old('email', $p->email) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Provize (% ze zakázky bez DPH)<input name="commission_percent" type="number" step="0.01" min="0" max="50" required value="{{ old('commission_percent', $p->commission_percent) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Odkaz platí (dní)<input name="cookie_days" type="number" min="1" max="365" required value="{{ old('cookie_days', $p->cookie_days) }}" class="{{ $in }}"></label>
<label class="{{ $lb }}">Účet partnera (e-mail účtu, nepovinné)<input name="user_email" type="email" maxlength="190" value="{{ old('user_email', $p->user?->email) }}" class="{{ $in }}"></label>
<label class="{{ $lb }} sm:col-span-2">Údaje pro výplatu<input name="payout_details" maxlength="1000" value="{{ old('payout_details', $p->payout_details) }}" class="{{ $in }}"></label>
<label class="flex items-center gap-2 pt-5 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $p->active)) class="h-4 w-4 accent-action"> Aktivní (vypnutý nepočítá kliky ani provize)</label>
<label class="{{ $lb }} sm:col-span-3">Poznámky<input name="notes" maxlength="2000" value="{{ old('notes', $p->notes) }}" class="{{ $in }}"></label>
