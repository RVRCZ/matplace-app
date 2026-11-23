{{-- A signed-in customer who has ticked the photo consent before (users.photo_consent_at) sees this line instead of the box;
     "change" withdraws it and shows the box again (figure.ts, accountConsent). Never rendered for a visitor: his box is never ticked for him. --}}
@php $given = auth()->user()?->photo_consent_at; @endphp
@if($given)
    <p data-consent-row class="{{ $class ?? 'text-sm' }} text-ink" data-url="{{ route('api.photo_consent.withdraw') }}">{{ __('figure.consent.given', ['date' => $given->locale(app()->getLocale())->isoFormat('L')]) }} · <button type="button" data-consent-change class="text-action-dark underline">{{ __('figure.consent.change') }}</button></p>
@endif
