{{-- Chat panel; needs $thread (App\Models\Thread), $side ('customer'|'printer'), optional $inquiryToken for customers --}}
<div class="chat rounded-2xl border border-slate-200 bg-white" data-thread="{{ $thread->id }}" data-side="{{ $side }}" data-inquiry="{{ $inquiryToken ?? '' }}"
     data-url="{{ route('api.threads.messages', $thread) }}" data-post="{{ route('api.threads.post', $thread) }}">
    <div class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
        {{ $thread->header['model'] ?? '' }} · {{ $thread->header['material'] ?? '' }} · {{ $thread->header['quantity'] ?? 1 }} {{ __('inquiry.pcs') }}
        @if(!empty($thread->header['summary']['dims'])) · {{ $thread->header['summary']['dims']['x'] }}×{{ $thread->header['summary']['dims']['y'] }}×{{ $thread->header['summary']['dims']['z'] }} mm @endif
    </div>
    <div class="chat-log h-72 space-y-2 overflow-y-auto px-4 py-3 text-sm"></div>
    <form class="chat-form flex items-center gap-2 border-t border-slate-100 p-2">
        <label class="cursor-pointer rounded-lg px-2 py-2 text-slate-500 hover:bg-slate-100" title="{{ __('inquiry.chat.attach') }}">📎<input type="file" class="chat-file sr-only" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.stl,.3mf,.obj,.step,.stp,.zip"></label>
        <input type="text" class="chat-input w-full rounded-lg border border-slate-300 px-3 py-2" maxlength="4000" placeholder="{{ __('inquiry.chat.placeholder') }}">
        <button class="rounded-lg bg-teal-600 px-3 py-2 font-semibold text-white">{{ __('inquiry.chat.send') }}</button>
    </form>
</div>
