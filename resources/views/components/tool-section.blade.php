{{-- One numbered section of a tool's panel (resources/views/tools/page.blade.php). Warnings that belong to it are put into [data-warnings] by the script. --}}
@props(['id', 'title'])
<section id="sec-{{ $id }}" data-section="{{ $id }}" {{ $attributes->merge(['class' => 'card mt-3 scroll-mt-14 p-4 first-of-type:mt-0']) }} aria-labelledby="sec-{{ $id }}-title">
    <h2 id="sec-{{ $id }}-title" class="flex items-center gap-2 text-base font-semibold text-ink"><span class="tool-sec-no" aria-hidden="true"></span>{{ $title }}</h2>
    <ul data-warnings="{{ $id }}" class="mt-3 hidden space-y-1.5 text-sm"></ul>
    <div class="mt-3 space-y-4">{{ $slot }}</div>
</section>
