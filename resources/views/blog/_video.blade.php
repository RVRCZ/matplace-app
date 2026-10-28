{{--
    A published video of the farm inside an article ([video: …], App\Support\BlogEmbeds). Nothing of YouTube is loaded
    before the click: a dark box with the title, then the no-cookie player, the same way as on the tool pages and the
    home page (calculator/_trust).
--}}
<figure class="my-6 not-prose" data-blog-video="{{ $video->youtube_id }}">
    <div id="blog-video-{{ $video->youtube_id }}" class="relative aspect-video overflow-hidden rounded-xl bg-ink" data-youtube="{{ $video->youtube_id }}">
        <button type="button" id="blog-video-play-{{ $video->youtube_id }}" class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-4 text-white" aria-label="{{ __('tools.video.play') }}">
            <span class="rounded-full bg-white/90 px-5 py-3 text-sm font-semibold text-ink shadow">▶ {{ __('tools.video.play') }}</span>
            <span class="text-center text-sm">{{ $video->title }}</span>
        </button>
    </div>
    <figcaption class="mt-1 text-xs text-slate-500">{{ __('tools.video.note') }}</figcaption>
    <script>
        document.getElementById('blog-video-play-{{ $video->youtube_id }}').addEventListener('click', function () {
            var box = document.getElementById('blog-video-{{ $video->youtube_id }}');
            var f = document.createElement('iframe');
            f.src = 'https://www.youtube-nocookie.com/embed/' + box.dataset.youtube + '?autoplay=1&rel=0';
            f.allow = 'autoplay; encrypted-media; picture-in-picture'; f.allowFullscreen = true; f.className = 'absolute inset-0 h-full w-full'; f.title = 'YouTube';
            box.replaceChildren(f);
        });
    </script>
</figure>
