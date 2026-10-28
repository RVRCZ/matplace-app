{{--
    A shared calculation inside an article ([calc: <token>], App\Support\BlogEmbeds): the file, its size, grams, print
    time and the farm's price with a link to /c/<token>. While the calculation is not in this database (or not done),
    only the link. Prices are stored in crowns (the farm's list).
--}}
@php
    $url = route('calc.share', $token);
    $file = $calc?->modelFile;
    $bbox = (array) ($file?->bbox ?? []);
    $size = isset($bbox['x'], $bbox['y'], $bbox['z']) ? implode(' × ', array_map(fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.'), [$bbox['x'], $bbox['y'], $bbox['z']])).' mm' : null;
    $prices = $calc ? (array) \App\Http\Controllers\Api\CalculationController::describe($calc)['prices'] : [];
    $price = collect($prices)->first(fn ($p) => isset($p['total']));
    $minutes = (int) ($price['minutes'] ?? $calc?->slicer['minutes'] ?? 0);
    $grams = $calc?->slicer['grams'] ?? null;
@endphp
@if($calc)
    <aside class="my-6 not-prose card flex flex-col gap-1 p-4" data-blog-calc="{{ $token }}">
        <p class="font-semibold text-ink">{{ $file?->original_name ?? $token }}@if($size) <span class="font-normal text-muted">· {{ $size }}</span>@endif</p>
        @if($grams !== null)
            <p class="text-sm text-muted">{{ __('site.examples.meta', ['material' => (string) ($calc->params['material'] ?? 'PLA'), 'g' => rtrim(rtrim(number_format((float) $grams, 1, ',', ''), '0'), ','), 'time' => $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min']) }}</p>
        @endif
        @if($price)
            <p class="text-2xl font-extrabold text-ink">{{ \App\Support\Money::of((float) $price['total'], 'CZK')->format() }} <span class="text-sm font-normal text-muted">{{ __('site.examples.price_note') }}</span></p>
        @endif
        <a href="{{ $url }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-action-dark underline-offset-4 hover:underline">{{ __('site.examples.open') }}<x-icon name="arrow-right" class="h-4 w-4" /></a>
    </aside>
@else
    <p data-blog-calc="{{ $token }}"><a href="{{ $url }}">{{ __('site.examples.open') }}</a></p>
@endif
