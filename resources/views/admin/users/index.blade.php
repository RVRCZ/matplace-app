@extends('layouts.app', ['title' => 'Uživatelé · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<div class="mt-2 flex flex-wrap gap-3 text-sm text-slate-600">
    <span>Účtů: <strong>{{ $counts['all'] }}</strong></span>
    <span>ověřených: <strong>{{ $counts['verified'] }}</strong></span>
    <span>designérů: <strong>{{ $counts['designers'] }}</strong></span>
    <span>nových za 30 dní: <strong>{{ $counts['month'] }}</strong></span>
</div>

<form method="get" class="mt-3 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-sm">
    <label class="flex-1 text-xs font-semibold text-slate-600">Jméno, e-mail nebo telefon<input name="q" value="{{ $q }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal" placeholder="novak@…"></label>
    <label class="text-xs font-semibold text-slate-600">Role
        <select name="role" class="mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-2 font-normal">
            <option value="">všechny</option>
            @foreach(\App\Http\Controllers\Admin\UserController::ROLES as $r)<option value="{{ $r }}" @selected($role === $r)>{{ $r }}</option>@endforeach
        </select>
    </label>
    <label class="flex items-center gap-1 pb-2"><input type="checkbox" name="deleted" value="1" @checked($deleted) class="h-4 w-4 accent-action"> smazané účty</label>
    <button class="btn-quiet min-h-0 px-3 py-2 text-sm">Hledat</button>
</form>

<div class="card mt-3 overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>
            <th class="px-3 py-2">Účet</th><th class="px-3 py-2">Země · měna</th><th class="px-3 py-2">Role</th>
            <th class="px-3 py-2 text-right">Kredit</th><th class="px-3 py-2 text-right">Tisky</th><th class="px-3 py-2 text-right">Modely</th><th class="px-3 py-2">Registrace</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($users as $u)
                <tr>
                    <td class="px-3 py-2">
                        <a href="{{ route('admin.users.show', $u) }}" class="font-semibold text-action-dark underline">{{ $u->name }}</a>
                        <span class="block text-xs text-slate-500">{{ $u->email }}@if(! $u->email_verified_at) · <span class="text-amber-700">neověřený</span>@endif</span>
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-600">{{ $u->country ?: '—' }} · {{ $u->currency ?: '—' }}</td>
                    <td class="px-3 py-2 text-xs">{{ $u->roles->whereNull('disabled_at')->pluck('role')->implode(', ') ?: '—' }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format((float) $u->balance, 2, ',', ' ') }}</td>
                    <td class="px-3 py-2 text-right">{{ $u->orders_count }}</td>
                    <td class="px-3 py-2 text-right">{{ $u->model_files_count }}</td>
                    <td class="px-3 py-2 text-xs text-slate-500">{{ $u->created_at->format('j. n. Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Nikdo nenalezen.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
