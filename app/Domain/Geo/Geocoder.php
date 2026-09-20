<?php

namespace App\Domain\Geo;

use App\Models\GeocodeCache;
use Illuminate\Support\Facades\Http;

/**
 * Postcode / town → coordinates through Nominatim (OpenStreetMap), cached forever in geocode_cache.
 * Nominatim policy: identify the app, ≤ 1 request/s; we only call it when a user saves a new location.
 */
final class Geocoder
{
    private const URL = 'https://nominatim.openstreetmap.org/search';

    /** @return array{lat: float, lng: float}|null */
    public function resolve(?string $zip, ?string $city, string $country = 'CZ'): ?array
    {
        $zip = trim((string) $zip);
        $city = trim((string) $city);
        if ($zip === '' && $city === '') {
            return null;
        }
        $query = strtolower($country.'|'.preg_replace('/\s+/', '', $zip).'|'.mb_strtolower($city));
        $cached = GeocodeCache::where('query', $query)->first();
        if ($cached) {
            return $cached->lat !== null ? ['lat' => $cached->lat, 'lng' => $cached->lng] : null;
        }

        $params = ['format' => 'json', 'limit' => 1, 'countrycodes' => strtolower($country)];
        if ($zip !== '') {
            $params['postalcode'] = $zip;
        }
        if ($city !== '') {
            $params['city'] = $city;
        }
        $hit = null;
        try {
            $res = Http::timeout(8)->withHeaders(['User-Agent' => 'matplace.com geocoder (info@matplace.cz)'])->get(self::URL, $params);
            $row = $res->ok() ? ($res->json('0') ?? null) : null;
            if (! $row && $zip !== '' && $city !== '') {
                // retry with the postcode only (town spelling varies)
                unset($params['city']);
                $res = Http::timeout(8)->withHeaders(['User-Agent' => 'matplace.com geocoder (info@matplace.cz)'])->get(self::URL, $params);
                $row = $res->ok() ? ($res->json('0') ?? null) : null;
            }
            if ($row && isset($row['lat'], $row['lon'])) {
                $hit = ['lat' => round((float) $row['lat'], 6), 'lng' => round((float) $row['lon'], 6), 'display' => (string) ($row['display_name'] ?? '')];
            }
        } catch (\Throwable) {
            return null; // transient failure: do not cache
        }

        GeocodeCache::create(['query' => $query, 'lat' => $hit['lat'] ?? null, 'lng' => $hit['lng'] ?? null, 'display' => isset($hit['display']) ? mb_substr($hit['display'], 0, 200) : null]);

        return $hit ? ['lat' => $hit['lat'], 'lng' => $hit['lng']] : null;
    }

    /** Great-circle distance in km. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }
}
