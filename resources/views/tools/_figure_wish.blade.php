{{-- what the customer wants changed on the photo (docs/AU.md §9; Roman, 26 Nov 2026): the assistant reads it, the photo is
     redrawn with it before the model is made. Not required. --}}
<label class="lbl">{{ __('figure.wish') }} <span class="font-normal text-muted">{{ __('figure.wish.optional') }}</span>
    <textarea name="wish" rows="2" maxlength="{{ \App\Domain\Generation\WishReader::MAX }}" class="field" placeholder="{{ $placeholder }}"></textarea>
    <span class="mt-1 block text-xs font-normal text-muted">{{ __('figure.wish.hint') }}</span>
</label>
