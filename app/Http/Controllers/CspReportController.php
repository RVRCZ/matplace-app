<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * POST /csp-report: what a browser would have blocked under the Content-Security-Policy (config/security.php). Both
 * shapes arrive here: the old `application/csp-report` ({"csp-report": {...}}) and the Reporting API's list of reports.
 * Each distinct violation (page, directive, blocked address, source line) is written once an hour to the `csp` log
 * channel (storage/logs/csp.log) with how many times it came since; browser extensions and other pages' noise are
 * dropped. Nothing of the visitor is kept: no cookies, no address, no user agent beyond the browser family.
 */
class CspReportController extends Controller
{
    public const CACHE_SECONDS = 3600;

    public function __invoke(Request $request): Response
    {
        foreach (self::reportsIn($request) as $report) {
            $blocked = (string) ($report['blocked-uri'] ?? $report['blockedURL'] ?? '');
            if (self::noise($blocked)) {
                continue;
            }
            $row = [
                'page' => self::path((string) ($report['document-uri'] ?? $report['documentURL'] ?? '')),
                'directive' => (string) ($report['effective-directive'] ?? $report['effectiveDirective'] ?? $report['violated-directive'] ?? ''),
                'blocked' => mb_substr($blocked, 0, 200),
                'source' => self::path((string) ($report['source-file'] ?? $report['sourceFile'] ?? '')).':'.(int) ($report['line-number'] ?? $report['lineNumber'] ?? 0),
                'sample' => mb_substr((string) ($report['script-sample'] ?? $report['sample'] ?? ''), 0, 80),
                'disposition' => (string) ($report['disposition'] ?? ''),
            ];
            if ($row['directive'] === '') {
                continue;
            }
            $key = 'csp:'.sha1(implode('|', [$row['page'], $row['directive'], $row['blocked'], $row['source']]));
            if (Cache::add($key, 1, self::CACHE_SECONDS)) {
                Log::channel('csp')->info('csp violation', $row + ['browser' => self::browser((string) $request->userAgent())]);
            } else {
                Cache::increment($key);
            }
        }

        return response()->noContent();
    }

    /** @return list<array<string, mixed>> */
    private static function reportsIn(Request $request): array
    {
        $raw = (string) $request->getContent();
        if ($raw === '' || strlen($raw) > 65536) {
            return [];
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['csp-report']) && is_array($data['csp-report'])) {
            return [$data['csp-report']];
        }
        $out = [];
        foreach (array_is_list($data) ? $data : [$data] as $item) {
            if (is_array($item) && isset($item['body']) && is_array($item['body'])) {
                $out[] = $item['body'];
            }
        }

        return array_slice($out, 0, 20);
    }

    /** Extensions, other schemes and empty reports say nothing about our pages. */
    private static function noise(string $blocked): bool
    {
        foreach (['chrome-extension', 'moz-extension', 'safari-extension', 'safari-web-extension', 'ms-browser-extension', 'about:', 'chrome:', 'edge:', 'opera:', 'file:'] as $prefix) {
            if (str_starts_with($blocked, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** The path of an address, without the query string (which can carry codes and names). */
    private static function path(string $url): string
    {
        if ($url === '' || in_array($url, ['inline', 'eval', 'self'], true)) {
            return $url;
        }
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? ($host !== '' ? '/' : ''));

        return ($host !== '' && $host !== parse_url((string) config('app.url'), PHP_URL_HOST) ? $host : '').$path;
    }

    private static function browser(string $agent): string
    {
        foreach (['Firefox', 'Edg', 'OPR', 'Chrome', 'Safari'] as $name) {
            if (str_contains($agent, $name.'/')) {
                return $name === 'Edg' ? 'Edge' : ($name === 'OPR' ? 'Opera' : $name);
            }
        }

        return 'other';
    }
}
