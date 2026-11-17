@extends('layouts.app', ['title' => __('affiliate.account.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('affiliate.account.title') }}</h1>
        <a href="{{ route('account') }}" class="text-sm text-action-dark">← {{ __('user.profile.back') }}</a>
    </div>
    @include('partials.flash')

    <section class="card mt-4 p-5">
        <h2 class="font-bold">{{ __('affiliate.account.link') }}</h2>
        @unless($partner->active)
            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('affiliate.account.off') }}</p>
        @endunless
        <input readonly value="{{ $link }}" onclick="this.select()" aria-label="{{ __('affiliate.account.link') }}" class="field mt-2 font-mono text-sm" id="partner-link">
        <p class="hint mt-1">{{ __('affiliate.account.rate', ['percent' => rtrim(rtrim(number_format($partner->commission_percent, 2, '.', ''), '0'), '.'), 'days' => $partner->cookie_days]) }}</p>

        {{-- the link builder: a page of ours or any path; no script, the page just asks again with ?to= --}}
        <form method="get" action="{{ route('account.partner') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div class="sm:col-span-3"><h3 class="text-sm font-semibold">{{ __('affiliate.account.builder') }}</h3><p class="hint">{{ __('affiliate.account.builder_hint') }}</p></div>
            <label class="lbl">{{ __('affiliate.account.page_pick') }}
                <select onchange="this.form.to.value = this.value" class="field">
                    <option value="">—</option>
                    @foreach($pages as $path => $label)<option value="{{ $path }}" @selected($to === $path)>{{ __($label) }}</option>@endforeach
                </select></label>
            <label class="lbl">{{ __('affiliate.account.path') }}<input name="to" maxlength="200" value="{{ $to }}" placeholder="/tools/map" class="field font-mono"></label>
            <button class="btn-primary">{{ __('affiliate.account.make') }}</button>
        </form>
    </section>

    <section class="card mt-4 p-5">
        <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            @foreach([['affiliate.account.clicks_total', $stats['clicks']], ['affiliate.account.orders', $stats['orders']], ['affiliate.account.paid_orders', $stats['paid_orders']], ['affiliate.account.turnover', $money($stats['turnover'])], ['affiliate.account.pending', $money($stats['pending'])], ['affiliate.account.payable', $money($stats['payable'])], ['affiliate.account.paid', $money($stats['paid'])]] as [$label, $value])
                <div><dt class="text-xs text-slate-500">{{ __($label) }}</dt><dd class="text-lg font-bold">{{ $value }}</dd></div>
            @endforeach
        </dl>
        @php($po = $stats['payout'])
        @if($stats['payable'])
            <p class="mt-3 rounded-lg px-3 py-2 text-sm {{ $po['due'] ? 'bg-ok-soft text-ok' : 'bg-slate-100 text-slate-700' }}" data-payout="{{ $po['due'] ? 'due' : 'carried' }}">
                @if($po['due']){{ __('affiliate.account.payout_due', ['date' => \Illuminate\Support\Carbon::parse($po['next'])->locale(app()->getLocale())->isoFormat('LL')]) }}
                @else{{ __('affiliate.account.payout_carried', ['missing' => \App\Support\Money::czk($po['missing_czk'])->format(), 'min' => \App\Support\Money::czk($po['min_czk'])->format(), 'date' => \Illuminate\Support\Carbon::parse($po['next'])->locale(app()->getLocale())->isoFormat('LL')]) }}@endif
            </p>
        @endif
        @php($max = max(1, max($stats['clicks_by_day'] ?: [0])))
        <h3 class="mt-4 text-sm font-semibold">{{ __('affiliate.account.clicks') }}</h3>
        <div class="mt-1 flex h-16 items-end gap-px">
            @foreach($stats['clicks_by_day'] as $day => $n)
                <span class="w-full rounded-sm bg-action/70" style="height: {{ $n > 0 ? max(8, (int) round($n / $max * 100)) : 2 }}%" title="{{ $day }}: {{ $n }}"></span>
            @endforeach
        </div>
    </section>

    <section class="card mt-4 p-5">
        <h2 class="font-bold">{{ __('affiliate.account.commissions') }}</h2>
        @if($commissions->isEmpty())
            <p class="mt-2 text-sm text-slate-500">{{ __('affiliate.account.none') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="text-xs text-slate-500"><tr><th class="py-1 pr-3">{{ __('affiliate.account.col_order') }}</th><th class="pr-3">{{ __('affiliate.account.col_base') }}</th><th class="pr-3">{{ __('affiliate.account.col_amount') }}</th><th>{{ __('affiliate.account.col_state') }}</th></tr></thead>
                    <tbody>
                    @foreach($commissions as $c)
                        <tr class="border-t border-slate-100"><td class="py-1 pr-3">{{ $c->order?->number ?: '#'.$c->farm_order_id }}</td><td class="pr-3">@money($c->base_amount, $c->currency)</td><td class="pr-3 font-semibold">@money($c->amount, $c->currency)</td><td>{{ __('affiliate.state.'.$c->state) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
