@extends('layouts.app', ['title' => __('farm.admin.nav.credit').' · admin', 'noindex' => true])

@section('content')
@include('admin.farm.nav')

<form method="get" class="mt-4 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4">
    <label class="block flex-1 text-xs font-semibold text-slate-600">E-mail zákazníka<input type="email" name="email" required value="{{ $email }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
    <button class="btn-quiet text-sm">Najít</button>
</form>

@if($email !== '' && ! $user)
    <p class="mt-3 text-sm text-red-700">Účet s tímto e-mailem neexistuje.</p>
@endif

@if($user)
    <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">{{ $user->name }} · {{ $user->email }}</h2>
        <p class="text-3xl font-extrabold">{{ number_format($balance, 2, ',', ' ') }} Kč</p>

        <form method="post" action="{{ route('admin.farm.credit.adjust') }}" class="mt-3 grid items-end gap-2 sm:grid-cols-[10rem_1fr_auto]">
            @csrf
            <input type="hidden" name="user_id" value="{{ $user->id }}">
            <label class="block text-xs font-semibold text-slate-600">Částka (+ přidat, − ubrat)<input type="number" step="0.01" name="amount" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
            <label class="block text-xs font-semibold text-slate-600">Důvod (uloží se do historie)<input name="note" required maxlength="300" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
            <button class="btn-primary text-sm">Zapsat</button>
        </form>

        <table class="mt-4 w-full text-left text-sm">
            <tbody class="divide-y divide-slate-100">
                @foreach($transactions as $t)
                    <tr><td class="py-1 text-xs text-slate-500">{{ $t->created_at->format('j. n. Y H:i') }}</td><td>{{ $t->type }}</td><td class="text-right font-semibold">{{ number_format($t->amount, 2, ',', ' ') }}</td><td class="pl-3 text-xs text-slate-600">{{ $t->note }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
@endsection
