{{-- Enlarged view of any image marked data-zoom (photo of a printed colour, test print…): click opens, click or Esc closes. --}}
<dialog id="mp-lightbox" class="m-auto max-h-[92vh] max-w-[92vw] rounded-2xl bg-white p-2 shadow-xl backdrop:bg-black/70" aria-label="{{ __('farm.order.photo') }}">
    <img src="" alt="" class="max-h-[85vh] max-w-[90vw] rounded-xl object-contain">
    <p class="mt-1 truncate px-1 text-center text-xs text-slate-600"></p>
</dialog>
<script>
    (function () {
        var box = document.getElementById('mp-lightbox');
        if (!box || !box.showModal) return;
        document.addEventListener('click', function (e) {
            var t = e.target instanceof Element ? e.target.closest('[data-zoom]') : null;
            if (!t) return;
            var src = t.getAttribute('data-zoom') || t.getAttribute('src');
            if (!src) return;
            e.preventDefault();
            box.querySelector('img').src = src;
            box.querySelector('p').textContent = t.getAttribute('data-zoom-title') || t.getAttribute('alt') || '';
            box.showModal();
        }, true);
        box.addEventListener('click', function () { box.close(); });
    })();
</script>
