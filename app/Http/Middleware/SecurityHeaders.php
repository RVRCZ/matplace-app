<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers of the pages (config/security.php, TODO #45): Permissions-Policy on everything, the
 * Content-Security-Policy on HTML documents in the mode the config says (report-only first, enforced later), and the
 * headers that make sense only once the policy is enforced. A header another part of the app already set is left alone.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $cfg = (array) config('security');
        $mode = (string) ($cfg['csp']['mode'] ?? 'off');

        // the same app under another name (beta.matplace.com) must not be indexed as a copy of the site (audit 26 Oct 2026, A1)
        if (! self::canonicalHost($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        // how long the app took, for the browser's timing panel (audit B6)
        $start = defined('LARAVEL_START') ? (float) LARAVEL_START : (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0);
        if ($start > 0 && ! $response->headers->has('Server-Timing')) {
            $response->headers->set('Server-Timing', sprintf('app;dur=%.1f', (microtime(true) - $start) * 1000));
        }

        $headers = (array) ($cfg['headers'] ?? []);
        if ($mode === 'enforce') {
            $headers += (array) ($cfg['headers_enforced'] ?? []);
        }
        foreach ($headers as $name => $value) {
            if (is_string($value) && $value !== '' && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if (in_array($mode, ['report', 'enforce'], true) && self::isDocument($response)) {
            $header = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
            if (! $response->headers->has($header)) {
                $response->headers->set($header, self::policy($cfg['csp']));
                if (! empty($cfg['csp']['report_uri'])) {
                    $response->headers->set('Reporting-Endpoints', 'csp="'.url((string) $cfg['csp']['report_uri']).'"');
                }
            }
        }

        return $response;
    }

    /** The policy line: every directive with its sources, then where the browser reports what it blocked. */
    public static function policy(array $csp): string
    {
        $parts = [];
        foreach ((array) ($csp['directives'] ?? []) as $directive => $sources) {
            $parts[] = trim($directive.' '.implode(' ', array_filter((array) $sources, fn ($s) => is_string($s) && $s !== '')));
        }
        if (! empty($csp['report_uri'])) {
            $parts[] = 'report-uri '.$csp['report_uri'];
            $parts[] = 'report-to csp';
        }

        return implode('; ', $parts);
    }

    /** The request came to the site's own name (config app.url); any other name is a copy that search engines must not keep. */
    public static function canonicalHost(Request $request): bool
    {
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $host = strtolower($request->getHost());

        return $own === '' || $host === $own || $host === 'localhost' || $host === '127.0.0.1';
    }

    /** An HTML page (what a CSP is for); JSON answers, files and redirects without a body are left alone. */
    private static function isDocument(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' ? $response->getStatusCode() < 300 : str_starts_with($type, 'text/html');
    }
}
