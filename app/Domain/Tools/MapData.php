<?php

namespace App\Domain\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The open data a map is made of, fetched by PHP and put on disk for the Python builder (which never touches the
 * network): places from Nominatim, the buildings, roads and water of OpenStreetMap from Overpass, the heights from
 * the Terrarium tiles of AWS Open Data. Every service is public and has its rules: a User-Agent that names us, one
 * request a second at Nominatim, one every two seconds at Overpass (from the whole application), and answers kept
 * 30 days (Nominatim in the cache, the rest in storage/app/maps). Tests never come here: Http::fake().
 */
final class MapData
{
    public const USER_AGENT = 'matplace.com tools (info@matplace.com)';

    public const NOMINATIM = 'https://nominatim.openstreetmap.org/search';

    /** the main Overpass server and the one that answers when it is down */
    public const OVERPASS = ['https://overpass-api.de/api/interpreter', 'https://overpass.openstreetmap.fr/api/interpreter', 'https://overpass.kumi.systems/api/interpreter'];     // the main server first (2 slots per address), then the public mirrors (the French one answered in 0.4 s from the server on 10 Oct 2026, kumi.systems 500 on everything)

    public const TERRARIUM = 'https://s3.amazonaws.com/elevation-tiles-prod/terrarium/%d/%d/%d.png';

    public const KEEP_DAYS = 30;

    /** no more height tiles than this for one map (a landscape of 20 km at zoom 12 needs about 16) */
    public const MAX_TILES = 36;

    /** where the answers are kept (storage/app/maps); the tests point it elsewhere so they never wipe a real cache */
    public static ?string $root = null;

    public static function root(): string
    {
        return self::$root ?? storage_path('app/maps');
    }

    public const ATTRIBUTION = 'Data © OpenStreetMap contributors (ODbL) · Výšky: Mapzen / AWS Open Data';

    /**
     * Places a name may mean, at most six: what Nominatim says, in the visitor's language, kept 30 days. A pair of
     * coordinates ("50.0875, 14.4213") is a place by itself and asks nobody.
     *
     * @return list<array{name: string, kind: string, country: string, display: string, lat: float, lon: float}>
     */
    public function places(string $q, string $lang): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');
        if ($q === '') {
            return [];
        }
        if (preg_match('/^(-?\d{1,2}(?:[.,]\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:[.,]\d+)?)$/', $q, $m)) {
            $lat = (float) str_replace(',', '.', $m[1]);
            $lon = (float) str_replace(',', '.', $m[2]);
            if (abs($lat) <= 85 && abs($lon) <= 180) {
                return [['name' => sprintf('%.5f, %.5f', $lat, $lon), 'kind' => 'point', 'country' => '', 'display' => sprintf('%.5f, %.5f', $lat, $lon), 'lat' => $lat, 'lon' => $lon]];
            }
        }
        $lang = in_array($lang, ['cs', 'en', 'es'], true) ? $lang : 'en';

        return Cache::remember('map:places:'.md5($lang.'|'.mb_strtolower($q)), now()->addDays(self::KEEP_DAYS), function () use ($q, $lang) {
            $this->slot('map:nominatim', 1, 1.1);
            $res = Http::timeout(60)->retry(2, 800, throw: false)->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => $lang])
                ->get(self::NOMINATIM, ['q' => $q, 'format' => 'jsonv2', 'limit' => 6, 'addressdetails' => 1, 'accept-language' => $lang]);
            if (! $res->ok() || ! is_array($res->json())) {
                throw new MapDataUnavailable('places_down', (string) $res->status());
            }
            $out = [];
            foreach ($res->json() as $r) {
                if (! is_array($r) || ! isset($r['lat'], $r['lon'])) {
                    continue;
                }
                $address = (array) ($r['address'] ?? []);
                $display = (string) ($r['display_name'] ?? '');
                $out[] = [
                    'name' => (string) ($r['name'] ?: (explode(',', $display)[0] ?? $q)),
                    'kind' => (string) ($r['addresstype'] ?? $r['type'] ?? ''),
                    'country' => (string) ($address['country'] ?? ''),
                    'display' => mb_substr($display, 0, 160),
                    'lat' => round((float) $r['lat'], 6), 'lon' => round((float) $r['lon'], 6),
                ];
            }

            return array_slice($out, 0, 6);
        });
    }

    /**
     * The OpenStreetMap elements of a square `sideM` across round a point, as the file of Overpass's JSON answer
     * (kept 30 days under the square's own name). A city asks for everything it draws (buildings and their parts,
     * towers, every road, rail, water, green); a landscape only for what lies on its relief (water, the roads, the built-up areas), and over
     * 6 km only for the main roads – a square of 20 km holds hundreds of thousands of buildings, which would be
     * fetched and thrown away. The main server first, the other when it is down.
     *
     * @throws MapDataUnavailable
     */
    public function osm(float $lat, float $lon, int $sideM, string $kind = 'city'): string
    {
        [$south, $west, $north, $east] = self::bbox($lat, $lon, $sideM);
        $kind = $kind === 'landscape' ? 'landscape' : 'city';
        $path = self::root().'/osm/'.sha1(sprintf('%.5f|%.5f|%d|%s', $lat, $lon, $sideM, $kind === 'city' ? 'parts' : $kind)).'.json';     // the name says what the answer holds: a city since it has parts and towers
        if ($this->fresh($path)) {
            return $path;
        }
        $bbox = sprintf('%.6f,%.6f,%.6f,%.6f', $south, $west, $north, $east);
        $query = $kind === 'city' ? '[out:json][timeout:60];('
            .'way["building"]('.$bbox.');relation["building"]('.$bbox.');'
            .'way["building:part"]('.$bbox.');way["man_made"="tower"]('.$bbox.');node["man_made"="tower"]('.$bbox.');'
            .'way["highway"]('.$bbox.');way["railway"]('.$bbox.');'
            .'way["waterway"]('.$bbox.');way["natural"~"^(water|wood|wetland)$"]('.$bbox.');relation["natural"="water"]('.$bbox.');'
            .'way["landuse"~"^(forest|grass|meadow|park|residential|reservoir|basin|orchard|village_green|recreation_ground|cemetery)$"]('.$bbox.');'
            .'way["leisure"~"^(park|garden|pitch|playground)$"]('.$bbox.');relation["landuse"="residential"]('.$bbox.');'
            .');out body;>;out skel qt;'
            : '[out:json][timeout:80];('
            .($sideM > 6000 ? 'way["highway"~"^(motorway|motorway_link|trunk|trunk_link|primary|primary_link|secondary|secondary_link|tertiary)$"]('.$bbox.');'
                : 'way["highway"]["highway"!~"^(path|footway|steps|cycleway|bridleway|track|service)$"]('.$bbox.');')
            .'way["waterway"~"^(river|canal)$"]('.$bbox.');way["natural"="water"]('.$bbox.');relation["natural"="water"]('.$bbox.');'
            .'way["landuse"~"^(residential|reservoir|basin)$"]('.$bbox.');relation["landuse"="residential"]('.$bbox.');'
            .');out body;>;out skel qt;';
        $failed = '';
        foreach (self::OVERPASS as $server) {
            $this->slot('map:overpass', 1, 2.2);
            try {
                $res = Http::timeout(90)->withHeaders(['User-Agent' => self::USER_AGENT])->asForm()->post($server, ['data' => $query]);
            } catch (\Throwable $e) {
                $failed .= parse_url($server, PHP_URL_HOST).': '.mb_substr($e->getMessage(), 0, 80).'; ';

                continue;
            }
            $body = $res->body();
            $doc = $res->ok() ? json_decode($body, true) : null;
            // a server under load answers 200 with a remark and nothing in it: that is a failure too, the other server is asked
            if (is_array($doc) && isset($doc['elements']) && ! (empty($doc['elements']) && isset($doc['remark']))) {
                self::put($path, $body);

                return $path;
            }
            // what each server said, for the log: a remark ("runtime error: ... timed out"), or the status (429 = our slots are used up, 504 = overloaded)
            $failed .= parse_url($server, PHP_URL_HOST).': '.(is_array($doc) ? mb_substr((string) ($doc['remark'] ?? 'empty'), 0, 80) : $res->status().' '.mb_substr(trim(strip_tags($body)), 0, 80)).'; ';
        }
        throw new MapDataUnavailable('osm_down', rtrim($failed, '; '));
    }

    /**
     * The Terrarium height tiles that cover a square, at a zoom (12 for a landscape, 14 for a city): [{z, x, y, path}],
     * each kept on disk for 30 days.
     *
     * @return list<array{z: int, x: int, y: int, path: string}>
     *
     * @throws MapDataUnavailable
     */
    public function dem(float $lat, float $lon, int $sideM, int $zoom): array
    {
        [$south, $west, $north, $east] = self::bbox($lat, $lon, $sideM);
        [$x0, $y0] = self::tile($north, $west, $zoom);
        [$x1, $y1] = self::tile($south, $east, $zoom);
        $n = 1 << $zoom;
        $tiles = [];
        for ($y = (int) floor($y0); $y <= (int) floor($y1); $y++) {
            for ($x = (int) floor($x0); $x <= (int) floor($x1); $x++) {
                $tiles[] = ['z' => $zoom, 'x' => (($x % $n) + $n) % $n, 'y' => max(0, min($n - 1, $y))];
            }
        }
        if (count($tiles) > self::MAX_TILES) {
            throw new MapDataUnavailable('area_too_big', (string) count($tiles));
        }
        foreach ($tiles as $i => $t) {
            $path = self::root().sprintf('/dem/%d/%d/%d.png', $t['z'], $t['x'], $t['y']);
            if (! $this->fresh($path, 365)) {
                $res = Http::timeout(60)->retry(2, 800, throw: false)->withHeaders(['User-Agent' => self::USER_AGENT])->get(sprintf(self::TERRARIUM, $t['z'], $t['x'], $t['y']));
                if (! $res->ok() || strlen($res->body()) < 100) {
                    throw new MapDataUnavailable('dem_down', (string) $res->status());
                }
                self::put($path, $res->body());
            }
            $tiles[$i]['path'] = $path;
        }

        return $tiles;
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} south, west, north, east of a square `sideM` across round a point */
    public static function bbox(float $lat, float $lon, int $sideM): array
    {
        $dLat = $sideM / 2 / 111320.0;
        $dLon = $sideM / 2 / (111320.0 * max(0.05, cos(deg2rad($lat))));

        return [$lat - $dLat, $lon - $dLon, $lat + $dLat, $lon + $dLon];
    }

    /** @return array{0: float, 1: float} the tile a point lies in at a zoom, with the fraction inside it (Web Mercator) */
    public static function tile(float $lat, float $lon, int $zoom): array
    {
        $n = 1 << $zoom;
        $latR = deg2rad(max(-85.05, min(85.05, $lat)));

        return [($lon + 180.0) / 360.0 * $n, (1.0 - log(tan($latR) + 1 / cos($latR)) / M_PI) / 2.0 * $n];
    }

    /** One request at a time at a public service: waits for a free slot, gives up after a few seconds. */
    private function slot(string $key, int $perSlot, float $seconds): void
    {
        for ($i = 0; $i < 6; $i++) {
            if (RateLimiter::attempt($key, $perSlot, fn () => true, (int) ceil($seconds))) {
                return;
            }
            usleep((int) ($seconds * 1_000_000 / 2));
        }
        throw new MapDataUnavailable('busy', $key);
    }

    private function fresh(string $path, int $days = self::KEEP_DAYS): bool
    {
        return is_file($path) && filesize($path) > 0 && filemtime($path) > time() - $days * 86400;
    }

    private static function put(string $path, string $body): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path.'.tmp', $body);
        File::move($path.'.tmp', $path);
    }
}
