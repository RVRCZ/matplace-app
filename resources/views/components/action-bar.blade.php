{{--
    The bar fixed to the bottom of the window with the price and the one main action (docs/AN.md §18, docs/AO.md §5).
    It mirrors the page's own button (`for` = its id: label, or its data-bar-label; disabled, aria-disabled, hidden; the
    click goes to it) and its price element (`price` = its id, `suffix` printed after the number, `sub` = a second line's
    id), so the page's script changes nothing for the bar; resources/js/site/sticky.ts mounts it, flashes the number when
    it changes, keeps the page's bottom free (--action-bar-h, body.has-action-bar) and sits above the cookie bar
    (--consent-h). Pass `class="lg:hidden"` for a phone-only bar, `hidden` to show it later from a script.
--}}
@props(['for', 'price' => null, 'suffix' => '', 'sub' => null, 'label' => null])
<div {{ $attributes->merge(['class' => 'action-bar']) }} data-action-bar data-for="{{ $for }}"
    @if($price) data-price-from="{{ $price }}" data-price-suffix="{{ $suffix }}" @endif
    @if($sub) data-sub-from="{{ $sub }}" @endif
    @if($label) data-label="{{ $label }}" @endif>
    <div class="action-bar-inner">
        <div class="min-w-0 flex-1">
            <div class="action-bar-price" data-bar-price hidden></div>
            <div class="action-bar-sub" data-bar-sub hidden></div>
            {{ $slot }}
        </div>
        <button type="button" class="btn-primary shrink-0" data-bar-button disabled>{{ $label ?? '' }}</button>
    </div>
</div>
