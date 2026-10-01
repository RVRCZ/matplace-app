<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Post;
use App\Support\HtmlCleaner;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * /admin/content: the blog (articles in Markdown, a language version exists where it has a title and a text, Czech
 * always) and the banners of the home page. Static pages are lang files and are not edited here.
 */
class ContentController extends Controller
{
    // ── blog ─────────────────────────────────────────────────────────────────
    public function posts(): View
    {
        return view('admin.content.posts', ['posts' => Post::orderByRaw('published_at is null desc')->orderByDesc('published_at')->orderByDesc('id')->paginate(30)]);
    }

    public function editPost(?int $post = null): View
    {
        return view('admin.content.post', ['post' => $post ? Post::findOrFail($post) : new Post(['format' => Post::FORMAT_MARKDOWN, 'title' => [], 'body' => [], 'excerpt' => []])]);
    }

    public function savePost(Request $request, ?int $post = null): RedirectResponse
    {
        $post = $post ? Post::findOrFail($post) : new Post(['format' => Post::FORMAT_MARKDOWN]);
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9-]+$/', Rule::unique('posts', 'slug')->ignore($post->id)],
            'title' => ['required', 'array'], 'title.cs' => ['required', 'string', 'max:200'], 'title.*' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'array'], 'excerpt.*' => ['nullable', 'string', 'max:600'],
            'body' => ['required', 'array'], 'body.cs' => ['required', 'string', 'max:200000'], 'body.*' => ['nullable', 'string', 'max:200000'],
            'cover' => ['nullable', 'image', 'max:8192'],
            'published_at' => ['nullable', 'date'],
        ]);
        $clean = fn (array $values) => array_filter(array_map(fn ($v) => trim((string) $v), array_intersect_key($values, array_flip(Locales::SUPPORTED))), fn ($v) => $v !== '');
        $post->fill([
            // an article taken over keeps its address; a new one gets it from its Czech title
            'slug' => $data['slug'] ?? $post->slug ?: $this->slug($data['title']['cs'], $post->id),
            'title' => $clean($data['title']), 'excerpt' => $clean((array) ($data['excerpt'] ?? [])) ?: null,
            // Markdown is rendered without raw HTML; HTML taken over from the old site is cleaned again on every save
            'body' => $post->format === Post::FORMAT_HTML ? array_map(fn ($html) => HtmlCleaner::clean($html, null, false), $clean($data['body'])) : $clean($data['body']),
            'author_id' => $post->author_id ?: $request->user()->id,
            // "publish" stamps now, "withdraw" clears; a date typed in wins over both (an article can be scheduled)
            'published_at' => $request->input('action') === 'withdraw' ? null : (($data['published_at'] ?? null) ?: ($request->input('action') === 'publish' ? now() : $post->published_at)),
        ]);
        if ($request->hasFile('cover')) {
            if ($post->cover_path && ! preg_match('#^https?://#', $post->cover_path)) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $post->cover_path = $request->file('cover')->storeAs('blog', 'cover-'.Str::lower(Str::random(12)).'.'.$request->file('cover')->extension(), 'public');
        }
        $post->save();

        return redirect()->route('admin.content.posts.edit', $post->id)->with('status', $post->isPublished() ? 'Uloženo a zveřejněno.' : 'Uloženo jako koncept.');
    }

    public function deletePost(int $post): RedirectResponse
    {
        $post = Post::findOrFail($post);
        if ($post->cover_path && ! preg_match('#^https?://#', $post->cover_path)) {
            Storage::disk('public')->delete($post->cover_path);
        }
        $post->delete();

        return redirect()->route('admin.content.posts')->with('status', 'Článek smazán.');
    }

    /** A picture for an article: stored, and the Markdown to paste comes back. */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:8192']]);
        $path = $request->file('image')->storeAs('blog', Str::lower(Str::random(16)).'.'.$request->file('image')->extension(), 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json(['url' => $url, 'markdown' => '![]('.$url.')']);
    }

    // ── banners ──────────────────────────────────────────────────────────────
    public function banners(): View
    {
        return view('admin.content.banners', ['banners' => Banner::orderBy('position')->orderBy('id')->get()]);
    }

    public function saveBanner(Request $request, ?int $banner = null): RedirectResponse
    {
        $banner = $banner ? Banner::findOrFail($banner) : new Banner;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'url' => ['nullable', 'string', 'max:500', 'regex:#^(https://|/)#'],
            'locale' => ['nullable', Rule::in(Locales::SUPPORTED)],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => [$banner->exists ? 'nullable' : 'required', 'image', 'max:8192'],
        ]);
        $banner->fill(['title' => $data['title'], 'url' => ($data['url'] ?? null) ?: null, 'locale' => ($data['locale'] ?? null) ?: null, 'position' => (int) ($data['position'] ?? 0), 'active' => $request->boolean('active')]);
        if ($request->hasFile('image')) {
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $banner->image_path = $request->file('image')->storeAs('banners', Str::lower(Str::random(16)).'.'.$request->file('image')->extension(), 'public');
        }
        $banner->save();

        return redirect()->route('admin.content.banners')->with('status', 'Banner uložen.');
    }

    public function deleteBanner(int $banner): RedirectResponse
    {
        $banner = Banner::findOrFail($banner);
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return redirect()->route('admin.content.banners')->with('status', 'Banner smazán.');
    }

    private function slug(string $title, ?int $exceptId): string
    {
        $base = mb_substr(Str::slug($title) ?: 'clanek', 0, 150);
        $slug = $base;
        for ($n = 2; Post::where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
