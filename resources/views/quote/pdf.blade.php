<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 22mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .muted { color: #6b7280; }
    .head { width: 100%; border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 14px; }
    .head td { vertical-align: top; }
    .logo { max-height: 60px; max-width: 180px; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.lines th { text-align: left; background: #f1f5f9; padding: 6px; font-size: 10px; text-transform: uppercase; color: #475569; }
    table.lines td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
    .num { text-align: right; white-space: nowrap; }
    .total td { font-weight: bold; font-size: 14px; border-top: 2px solid #0f766e; border-bottom: none; }
    .box { border: 1px solid #e5e7eb; padding: 8px 10px; margin-top: 12px; }
    .preview { max-width: 200px; max-height: 160px; }
    .foot { margin-top: 24px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
</style>
</head>
<body>
<table class="head">
    <tr>
        <td style="width:55%">
            @if($logo)<img class="logo" src="{{ $logo }}" alt=""><br>@endif
            <strong style="font-size:14px">{{ $profile->company ?: $profile->display_name }}</strong><br>
            @if($profile->company && $profile->company !== $profile->display_name){{ $profile->display_name }}<br>@endif
            @if($profile->ico)IČO {{ $profile->ico }}<br>@endif
            @if($profile->contact_email){{ $profile->contact_email }}<br>@endif
            @if($profile->contact_phone){{ $profile->contact_phone }}<br>@endif
            @if($profile->pickup_address)<span class="muted">{{ $profile->pickup_address }}</span>@endif
        </td>
        <td class="num">
            <h1>{{ __('quote.pdf.title') }}</h1>
            <div>{{ __('quote.number') }}: <strong>{{ $quote->number }}</strong></div>
            <div>{{ __('quote.date') }}: {{ ($quote->sent_at ?? $quote->created_at)->format('j. n. Y') }}</div>
            @if($quote->valid_until)<div>{{ __('quote.valid_until') }}: {{ $quote->valid_until->format('j. n. Y') }}</div>@endif
            @if($quote->lead_time_days !== null)<div>{{ __('quote.lead_time') }}: {{ __('calc.days', ['n' => $quote->lead_time_days]) }}</div>@endif
        </td>
    </tr>
</table>

@if($quote->client_name || $quote->client_email)
<div><span class="muted">{{ __('quote.client') }}:</span> <strong>{{ $quote->client_name }}</strong> {{ $quote->client_email ? '· '.$quote->client_email : '' }}</div>
@endif

<table style="width:100%;margin-top:10px"><tr>
    <td style="vertical-align:top">
        @if($quote->title)<div><span class="muted">{{ __('quote.model') }}:</span> <strong>{{ $quote->title }}</strong></div>@endif
        @if($quote->params)
            <div class="muted" style="margin-top:4px">
                {{ __('calc.material') }}: {{ $quote->params['material'] ?? '' }} ·
                {{ __('calc.quality') }}: {{ __('calc.quality.'.($quote->params['quality'] ?? 'standard')) }} ·
                {{ __('calc.infill') }}: {{ $quote->params['infill'] ?? '' }} % ·
                {{ __('calc.quantity') }}: {{ $quote->params['quantity'] ?? 1 }}
                @if(!empty($quote->params['scale']) && (float)$quote->params['scale'] !== 1.0) · {{ __('calc.scale') }}: {{ (int)($quote->params['scale']*100) }} % @endif
            </div>
        @endif
        @if($quote->calculation && $quote->calculation->slicer)
            <div class="muted">{{ __('calc.size') }}: {{ $quote->calculation->slicer['dims']['x'] }} × {{ $quote->calculation->slicer['dims']['y'] }} × {{ $quote->calculation->slicer['dims']['z'] }} mm @if($quote->color) · {{ __('param.color') }}: {{ $quote->color }}@endif</div>
        @endif
    </td>
    @if($preview)<td class="num" style="width:210px"><img class="preview" src="{{ $preview }}" alt=""></td>@endif
</tr></table>

<table class="lines">
    <thead><tr><th>{{ __('quote.pdf.item') }}</th><th class="num">{{ __('quote.pdf.qty') }}</th><th class="num">{{ __('quote.pdf.unit') }}</th><th class="num">{{ __('quote.pdf.total') }}</th></tr></thead>
    <tbody>
    @foreach($quote->customerLines() as $l)
        <tr><td>{{ $l['label'] }}</td><td class="num">{{ rtrim(rtrim(number_format($l['qty'], 2, ',', ' '), '0'), ',') }}</td><td class="num">{{ number_format($l['unit_price'], 2, ',', ' ') }}</td><td class="num">{{ number_format($l['total'], 2, ',', ' ') }}</td></tr>
    @endforeach
    <tr class="total"><td colspan="3">{{ __('quote.pdf.sum') }}</td><td class="num">{{ number_format($quote->total, 0, ',', ' ') }} {{ $quote->currency === 'CZK' ? 'Kč' : $quote->currency }}</td></tr>
    </tbody>
</table>

@if($quote->note)<div class="box">{!! nl2br(e($quote->note)) !!}</div>@endif

<div class="box">
    {{ __('quote.pdf.online') }}: <strong>{{ route('quote.public', $quote) }}</strong>
</div>

<div class="foot">{{ __('quote.pdf.footer') }}</div>
</body>
</html>
