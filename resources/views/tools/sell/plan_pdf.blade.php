{{-- the selling plan as a PDF (dompdf): one page of figures, the months, the steps; $plan (clean), $result (Plan::calculate), $money (closure) --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $plan['name'] !== '' ? $plan['name'] : __('tools.plan.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #181e2c; margin: 28px 32px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        .muted { color: #787e8a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e3dfd8; text-align: right; }
        th:first-child, td:first-child { text-align: left; }
        th { font-size: 9px; text-transform: uppercase; color: #787e8a; }
        .big { font-size: 18px; font-weight: bold; }
        .box { display: inline-block; margin-right: 24px; }
        .ok { color: #54966f; } .bad { color: #c45a5a; }
        ul { padding-left: 16px; margin: 4px 0; }
    </style>
</head>
<body>
    <h1>{{ $plan['name'] !== '' ? $plan['name'] : __('tools.plan.title') }}</h1>
    <div class="muted">matplace.com · {{ now()->isoFormat('LL') }} · {{ __('sell.plan.pdf.start', ['month' => \Illuminate\Support\Carbon::create(null, $plan['start'], 1)->isoFormat('MMMM')]) }}@if($plan['channel'] !== '') · {{ __('sell.platform.'.$plan['channel']) }}@endif</div>
    <p>
        <span class="box"><span class="muted">{{ __('sell.plan.year_profit') }}</span><br><span class="big {{ $result['profit'] >= 0 ? 'ok' : 'bad' }}">{{ $money($result['profit']) }}</span></span>
        <span class="box"><span class="muted">{{ __('sell.plan.year_revenue') }}</span><br><span class="big">{{ $money($result['revenue']) }}</span></span>
        <span class="box"><span class="muted">{{ __('sell.plan.year_pieces') }}</span><br><span class="big">{{ number_format($result['pieces'], 0, ',', ' ') }}</span></span>
        <span class="box"><span class="muted">{{ __('sell.plan.col.break') }}</span><br><span class="big">{{ $result['break_even_month'] ? __('sell.plan.break.month', ['n' => $result['break_even_month']]) : __('sell.plan.break.never') }}</span></span>
    </p>

    <h2>{{ __('sell.plan.sec.products') }}</h2>
    <table>
        <thead><tr><th>{{ __('sell.plan.p.name') }}</th><th>{{ __('sell.plan.p.cost') }}</th><th>{{ __('sell.plan.p.price') }}</th><th>{{ __('sell.plan.p.qty') }}</th><th>{{ __('sell.plan.pdf.margin') }}</th></tr></thead>
        <tbody>
            @foreach($plan['products'] as $i => $p)
                <tr><td>{{ $p['name'] !== '' ? $p['name'] : '#'.($i + 1) }}</td><td>{{ $money($p['cost']) }}</td><td>{{ $money($p['price']) }}</td><td>{{ number_format($p['qty'], 0, ',', ' ') }}</td><td>{{ $money($p['price'] - $p['cost']) }}</td></tr>
            @endforeach
            <tr><td>{{ __('sell.plan.f.fixed') }}</td><td colspan="4">{{ $money($plan['fixed']) }} {{ __('sell.unit.per_month') }}</td></tr>
        </tbody>
    </table>

    <h2>{{ __('sell.plan.pdf.months') }} ({{ __('sell.plan.season.'.$plan['season']) }})</h2>
    <table>
        <thead><tr><th>{{ __('sell.plan.col.month') }}</th><th>{{ __('sell.plan.col.revenue') }}</th><th>{{ __('sell.plan.col.costs') }}</th><th>{{ __('sell.plan.col.profit') }}</th><th>{{ __('sell.plan.col.cumulative') }}</th></tr></thead>
        <tbody>
            @foreach($result['months'] as $m)
                <tr><td>{{ \Illuminate\Support\Carbon::create(null, $m['month'], 1)->isoFormat('MMMM') }}</td><td>{{ $money($m['revenue']) }}</td><td>{{ $money($m['variable'] + $m['fixed']) }}</td><td class="{{ $m['profit'] >= 0 ? 'ok' : 'bad' }}">{{ $money($m['profit']) }}</td><td>{{ $money($m['cumulative']) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    @if($result['steps'])
        <h2>{{ __('sell.plan.steps.title') }}</h2>
        <ul>
            @foreach($result['steps'] as $s)
                <li>{{ $s['done'] ? '☑' : '☐' }} {{ __('sell.plan.step.'.$s['key'], ['product' => $s['product'] ?? '']) }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
