<?php

namespace App\Http\Controllers\Api;

use App\Domain\Calculation\CalculationService;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SliceParams;
use App\Engines\Search\CompositeSearch;
use App\Engines\Vision\VisionDescriber;
use App\Http\Controllers\Controller;
use App\Models\GenerationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "I have an idea" / "Something broke": text or photo → what it is, rough size + price range, ready-made models.
 * Free for everyone; expensive AI calls are limited per day (count, not money).
 */
class SearchController extends Controller
{
    /** POST /api/search {q} */
    public function text(Request $request, CompositeSearch $search): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:200']]);
        $set = $search->byText($data['q'], new SearchOptions(limit: 12, locale: app()->getLocale()));

        return response()->json([
            'query' => $set->query,
            'sources' => $set->sources,
            'results' => array_map(fn ($c) => $c->toArray(), $set->items),
        ]);
    }

    /** POST /api/describe multipart {image} → description, size guess, price range, ready-made models */
    public function describe(Request $request, VisionDescriber $vision, CompositeSearch $search, CalculationService $calc): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:12288']]);
        if (! $vision->available()) {
            return response()->json(['error' => 'vision_unavailable'], 503);
        }

        $ip = $request->ip();
        $session = $request->attributes->get('anon_session');
        $user = $request->user();
        $limit = (int) config('ai.daily_limits.describe', 20);
        $used = GenerationRequest::where('type', 'describe')->where('created_at', '>=', now()->startOfDay())
            ->where(fn ($q) => $q->where('ip', $ip)->orWhere('anonymous_session_id', $session?->id))->count();
        if ($used >= $limit) {
            return response()->json(['error' => 'daily_limit', 'limit' => $limit], 429);
        }

        $rel = 'photos/'.now()->format('Y/m').'/'.Str::uuid().'.'.strtolower($request->file('image')->getClientOriginalExtension() ?: 'jpg');
        Storage::disk('local')->put($rel, file_get_contents($request->file('image')->getRealPath()));
        $abs = Storage::disk('local')->path($rel);

        $req = GenerationRequest::create([
            'token' => Str::lower(Str::random(12)), 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'ip' => $ip, 'type' => 'describe', 'image_path' => $rel, 'engine' => 'vision', 'status' => 'running',
        ]);

        try {
            $d = $vision->describe($abs, app()->getLocale());
        } catch (\Throwable $e) {
            $req->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return response()->json(['error' => 'vision_failed'], 502);
        }
        $req->update(['status' => 'done', 'description' => $d]);

        // size guess → rough weight/time/price range (box volume × typical fill of a printed part)
        $range = null;
        if ($d['bbox_mm']) {
            $b = $d['bbox_mm'];
            $volume = max(1, $b['x']) * max(1, $b['y']) * max(1, $b['z']) * (float) config('ai.photo_fill_factor', 0.35);
            $rough = $calc->roughFor($volume, null, SliceParams::fromArray(['material' => $d['material']]), 1);
            $range = ['grams' => $rough['grams'], 'minutes' => $rough['minutes'], 'price_min' => $rough['price_min'], 'price_max' => $rough['price_max']];
        }

        $set = new \App\Engines\DTO\SearchResultSet([], $d['query'], []);
        foreach (array_slice($d['queries'], 0, 3) as $q) {
            $set = $set->merge($search->byText($q, new SearchOptions(limit: 6, locale: app()->getLocale())));
        }

        return response()->json([
            'token' => $req->token,
            'description' => $d,
            'range' => $range,
            'results' => array_map(fn ($c) => $c->toArray(), array_slice($set->items, 0, 12)),
            'sources' => $set->sources,
            'generator' => app(ModelGenerator::class)->name() !== 'null',
        ]);
    }
}
