<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tools\MapBuilder;
use App\Domain\Tools\MapData;
use App\Domain\Tools\MapDataUnavailable;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The 3D map of a city or a landscape (/tools/map): places, the picture of the area, the design made in the queue. */
class MapApiController extends Controller
{
    /** POST /api/tools/map/places {q} → {places: [{name, kind, country, display, lat, lon}]} the places a name may mean */
    public function places(Request $request, MapData $data): JsonResponse
    {
        $q = (string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']])['q'];
        try {
            return response()->json(['places' => $data->places($q, app()->getLocale())]);
        } catch (MapDataUnavailable $e) {
            return response()->json(['error' => $e->reason, 'message' => __('map.error.'.$e->reason)], 503);
        }
    }

    /** POST /api/tools/map/preview {params} → PNG of the area from above (600 × 600) + X-Map-Meta {buildings, roads_m, scale} */
    public function preview(Request $request, MapBuilder $maps): BinaryFileResponse|JsonResponse
    {
        if (! $maps->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        $clean = MapBuilder::clean((array) $request->validate(MapBuilder::rules())['params']);
        $png = storage_path('app/tmp/map/'.Str::uuid().'.png');
        try {
            $meta = $maps->preview($maps->sources($clean), $png);
        } catch (MapDataUnavailable $e) {
            return response()->json(['error' => $e->reason, 'message' => __('map.error.'.$e->reason)], 503);
        } catch (EngineException $e) {
            @unlink($png);

            return response()->json(['error' => 'map_failed', 'message' => self::explain($e->getMessage())], 422);
        }

        return response()->file($png, ['Content-Type' => 'image/png', 'X-Map-Meta' => (string) json_encode($meta), 'Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }

    /** POST /api/tools/map {params} → a model file made in the queue (ask /api/files/{uuid}; `map.stage` says the phase) */
    public function create(Request $request, MapBuilder $maps): JsonResponse
    {
        if (! $maps->available()) {
            return response()->json(['error' => 'tool_unavailable'], 503);
        }
        $params = (array) $request->validate(MapBuilder::rules())['params'];
        $user = $request->user();
        // a public service behind every map: a few a day for a visitor, more for an account, no limit for the admin
        $key = 'map:'.($user ? 'u'.$user->id : 'ip'.$request->ip());
        $most = $user ? MapBuilder::DAILY['user'] : MapBuilder::DAILY['anon'];
        if (! $user?->isAdmin() && RateLimiter::tooManyAttempts($key, $most)) {
            $hours = max(1, (int) ceil(RateLimiter::availableIn($key) / 3600));

            return response()->json(['error' => 'daily_limit', 'retry_in' => RateLimiter::availableIn($key), 'message' => __('map.error.daily_limit', ['n' => $most, 'h' => $hours])], 429);
        }
        $file = $maps->create($params, $request->attributes->get('anon_session'), $user);
        $user?->isAdmin() || RateLimiter::hit($key, 86400);
        File::ensureDirectoryExists(storage_path('app/tmp/map'));

        return response()->json(['file' => UploadController::describe($file)], 201);
    }

    /** The builder's code in the visitor's words; an unknown one gets the general line. */
    public static function explain(string $code): string
    {
        $key = 'map.error.'.preg_replace('/:.*$/', '', $code);

        return __($key) === $key ? __('map.error.map_failed') : __($key);
    }
}
