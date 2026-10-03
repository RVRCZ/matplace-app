@extends('layouts.app', ['title' => $user->name.' · uživatelé · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')
<a href="{{ route('admin.users.index') }}" class="text-sm text-action-dark underline">← Uživatelé</a>

<div class="mt-2 grid gap-4 lg:grid-cols-[2fr_1fr]">
    <div class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h1 class="text-xl font-bold">{{ $user->name }} <span class="text-sm font-normal text-slate-500">#{{ $user->id }}</span></h1>
            @if($user->isAnonymized())<p class="mt-1 font-semibold text-red-700">Smazaný (anonymizovaný) účet.</p>@endif
            <dl class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <dt class="text-xs uppercase text-slate-500">E-mail</dt><dd>{{ $user->email }} @if($user->email_verified_at)<span class="text-xs text-ok">ověřený {{ $user->email_verified_at->format('j. n. Y') }}</span>@else<span class="text-xs text-amber-700">neověřený</span>@endif</dd>
                <dt class="text-xs uppercase text-slate-500">Telefon</dt><dd>{{ $user->phone ?: '—' }}</dd>
                <dt class="text-xs uppercase text-slate-500">Adresa</dt><dd>{{ trim(implode(', ', array_filter([$user->street, $user->zip, $user->city, $user->country]))) ?: '—' }}</dd>
                <dt class="text-xs uppercase text-slate-500">Jazyk · měna účtu</dt><dd>{{ $user->locale ?: '—' }} · {{ $user->currency ?: 'zatím žádná' }}</dd>
                <dt class="text-xs uppercase text-slate-500">Registrace</dt><dd>{{ $user->created_at->format('j. n. Y H:i') }}</dd>
                <dt class="text-xs uppercase text-slate-500">Přihlášení</dt><dd>{{ $user->hasPassword() ? 'heslo' : 'bez hesla' }}@foreach($user->oauthIdentities as $i) · {{ $i->provider }}@endforeach</dd>
                <dt class="text-xs uppercase text-slate-500">Modely · kalkulace</dt><dd>{{ $filesCount }} · {{ $calculationsCount }}</dd>
                <dt class="text-xs uppercase text-slate-500">Designér</dt>
                <dd>@if($designer)<a href="{{ route('designers.show', $designer->slug) }}" class="text-action-dark underline">/d/{{ $designer->slug }}</a> ({{ $designer->getAttribute('visible') ? 'veřejný' : 'skrytý' }}) · <a href="{{ route('admin.catalog.cards', ['q' => $designer->display_name]) }}" class="underline">karty</a>@else—@endif</dd>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-bold">Kredit <span class="text-xl font-extrabold">@money($balance)</span></h2>
                <a href="{{ route('admin.farm.credit', ['email' => $user->email]) }}" class="btn-quiet min-h-0 px-3 py-1.5 text-xs">Upravit kredit / měnu</a>
            </div>
            @if($ledger->isEmpty())<p class="mt-2 text-slate-500">Žádný pohyb.</p>@else
                <table class="mt-2 w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100">
                        @foreach($ledger as $t)
                            <tr><td class="py-1 text-slate-500">{{ $t->created_at->format('j. n. Y H:i') }}</td><td class="py-1">{{ __('farm.credit.type.'.$t->type) }}</td><td class="py-1 text-slate-600">{{ $t->note }}</td><td class="py-1 text-right font-semibold {{ $t->amount < 0 ? 'text-red-700' : '' }}">{{ number_format((float) $t->amount, 2, ',', ' ') }} {{ $t->currency }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="font-bold">Tisky ({{ $ordersTotal }})</h2>
            @if($orders->isEmpty())<p class="mt-2 text-slate-500">Žádné.</p>@else
                <table class="mt-2 w-full text-left text-xs">
                    <tbody class="divide-y divide-slate-100">
                        @foreach($orders as $o)
                            <tr>
                                <td class="py-1"><a href="{{ route('admin.farm.orders.show', $o) }}" class="font-semibold text-action-dark underline">{{ $o->number ?? $o->token }}</a></td>
                                <td class="py-1">{{ $o->modelFile?->original_name ?? '—' }}</td>
                                <td class="py-1">{{ $o->statusText() }}</td>
                                <td class="py-1 text-right">@if($o->price_total)@money($o->total())@endif</td>
                                <td class="py-1 text-right text-slate-500">{{ $o->created_at->format('j. n. Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        @if($events->isNotEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
                <h2 class="font-bold">Události</h2>
                <p class="mt-1 text-xs text-slate-600">@foreach($events as $e){{ $e->type }} {{ $e->n }}×@if(! $loop->last) · @endif @endforeach</p>
            </section>
        @endif
    </div>

    <div class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="font-bold">Role</h2>
            @foreach(\App\Http\Controllers\Admin\UserController::ROLES as $r)
                @php $on = $user->roles->where('role', $r)->whereNull('disabled_at')->isNotEmpty(); @endphp
                <form method="post" action="{{ route('admin.users.role', $user) }}" class="mt-2 flex items-center justify-between gap-2">@csrf
                    <input type="hidden" name="role" value="{{ $r }}"><input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}">
                    <span><strong>{{ $r }}</strong>: {{ $on ? 'zapnutá' : 'vypnutá' }}</span>
                    <button class="btn-quiet min-h-0 px-3 py-1.5 text-xs" @disabled($user->isAnonymized() || ($r === 'admin' && $on && $isSelf))>{{ $on ? 'Vypnout' : 'Zapnout' }}</button>
                </form>
            @endforeach
            <p class="mt-2 text-xs text-slate-500">Designér: zapnutím vznikne profil, jako když si ho zapne sám; vypnutí ho skryje.</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="font-bold">Přihlášení</h2>
            <form method="post" action="{{ route('admin.users.reset', $user) }}" class="mt-2">@csrf
                <button class="btn-quiet min-h-0 px-3 py-1.5 text-xs" @disabled($user->isAnonymized())>Poslat odkaz na nové heslo</button>
            </form>
            <p class="mt-1 text-xs text-slate-500">Stejný e-mail jako „Zapomenuté heslo“; platí hodinu.</p>
        </section>

        @unless($user->isAnonymized() || $isSelf)
            <section class="rounded-2xl border border-red-200 bg-white p-4 text-sm">
                <h2 class="font-bold text-red-700">Smazat účet</h2>
                <p class="mt-1 text-xs text-slate-600">Stejně jako když si ho smaže sám: jméno, e-mail, adresa a soubory se odstraní, nevyčerpaný kredit propadne, zakázky a kniha kreditu zůstanou pod anonymním účtem. Nejde vrátit.</p>
                <form method="post" action="{{ route('admin.users.erase', $user) }}" class="mt-2" onsubmit="return confirm('Opravdu smazat účet {{ $user->email }}?')">@csrf
                    <label class="block text-xs font-semibold text-slate-600">Opište e-mail účtu<input name="confirm" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                    <button class="mt-2 rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" @disabled($user->isAdmin())>Smazat</button>
                    @if($user->isAdmin())<span class="ml-2 text-xs text-slate-500">nejdřív odeberte roli admin</span>@endif
                </form>
            </section>
        @endunless
    </div>
</div>
@endsection
