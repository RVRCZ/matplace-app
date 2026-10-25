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

    /** An HTML page (what a CSP is for); JSON answers, files and redirects without a body are left alone. */
    private static function isDocument(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' ? $response->getStatusCode() < 300 : str_starts_with($type, 'text/html');
    }
}
