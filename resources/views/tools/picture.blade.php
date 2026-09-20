{{-- Product picture of a tool (3:2). Decorative: the card names the tool right below it, so the alt text stays empty. Falls back to the small drawing when a tool has no picture yet, so a new tool never shows a hole. --}}
@php $base = 'img/tools/'.$key; @endphp
@if(is_file(public_path($base.'-800.jpg')))
    <picture>
        <source type="image/webp" srcset="{{ asset($base.'-480.webp') }} 480w, {{ asset($base.'-800.webp') }} 800w" sizes="{{ $sizes ?? '(min-width: 1024px) 340px, (min-width: 640px) 50vw, 100vw' }}">
        <img src="{{ asset($base.'-800.jpg') }}" alt="" width="800" height="533" loading="{{ !empty($eager) ? 'eager' : 'lazy' }}" decoding="async" class="block aspect-[3/2] h-auto w-full bg-[#F3EEE6] object-cover">
    </picture>
@else
    @include('tools.art', ['key' => $key])
@endif
