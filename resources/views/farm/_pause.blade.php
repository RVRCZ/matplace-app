{{--
    The honest sentence of a paused farm (App\Domain\Farm\FarmState, docs/AL.md §23): "The farm is paused right now.
    Printing starts on … at the earliest." with the admin's note, and that an order can still be made. Nothing in any
    other state. $order: only when the pause holds that order back (an admin's or the farm's own print goes on);
    $class: the spacing of the place it sits in.
--}}
@php
    $pauseText = isset($order)
        ? (\App\Domain\Farm\FarmState::holds($order) ? \App\Domain\Farm\FarmState::pauseLine() : null)
        : \App\Domain\Farm\FarmState::pauseLine();
@endphp
@if($pauseText)
<p data-farm-pause class="{{ $class ?? 'mt-2' }} rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" role="note">{{ $pauseText }} {{ __('farm.state.pause.order') }}</p>
@endif
