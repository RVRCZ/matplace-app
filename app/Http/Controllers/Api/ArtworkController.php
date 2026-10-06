<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\Artwork;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The picture window of the tools: our library of silhouettes and the visitor's own uploads. */
class ArtworkController extends Controller
{
    /** GET /api/artwork/library?q=&cat= → {cats: [{id, name, count}], items: [{ref, name, cat, url}]} */
    public function library(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:60'], 'cat' => ['nullable', 'in:'.implode(',', Artwork::CATEGORIES)]]);
        $found = Artwork::library((string) ($data['q'] ?? ''), (string) ($data['cat'] ?? ''));
        $found['items'] = array_map(fn ($i) => $i + ['url' => route('api.artwork.item', ['category' => $i['cat'], 'slug' => $i['slug']])], $found['items']);

        return response()->json($found)->header('Cache-Control', 'private, max-age=300');
    }

    /** GET /api/artwork/library/{category}/{slug}.svg → the silhouette itself (shown as a picture, never run) */
    public function item(string $category, string $slug): BinaryFileResponse
    {
        $path = Artwork::path('lib:'.$category.'/'.$slug);
        abort_unless($path !== null, 404);

        return response()->file($path, self::svgHeaders() + ['Cache-Control' => 'public, max-age=86400']);
    }

    /** GET /api/artwork/mine → the visitor's uploads of the last 30 days, newest first */
    public function mine(Request $request): JsonResponse
    {
        $items = array_map(fn ($i) => $i + ['url' => route('api.artwork.file', ['id' => $i['ref']])], Artwork::mine($this->owner($request)));

        return response()->json(['items' => $items, 'keep_days' => Artwork::KEEP_DAYS])->header('Cache-Control', 'no-store');
    }

    /** GET /api/artwork/file/{id} → one own upload; anybody else gets 404 */
    public function file(Request $request, string $id): BinaryFileResponse
    {
        $path = Artwork::ownPath($this->owner($request), $id);
        abort_unless($path !== null, 404);

        return response()->file($path, (str_ends_with($path, '.svg') ? self::svgHeaders() : []) + ['Cache-Control' => 'private, max-age=3600']);
    }

    private function owner(Request $request): ?string
    {
        return Artwork::owner($request->user(), $request->attributes->get('anon_session'));
    }

    /** An SVG is a document: opened on its own it must not run scripts or load anything. */
    private static function svgHeaders(): array
    {
        return ['Content-Type' => 'image/svg+xml', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox", 'X-Content-Type-Options' => 'nosniff'];
    }
}
