<?php

/*
 * Response headers of the pages (App\Http\Middleware\SecurityHeaders, TODO #45). HSTS, nosniff and Referrer-Policy
 * are set by nginx (snippets/matplace-app-common.conf) and are not repeated here.
 *
 * The Content-Security-Policy starts in `report` mode: the browser sends what it would have blocked to /csp-report
 * (storage/logs/csp.log) and blocks nothing. After a week of clean reports the mode goes to `enforce` (then also
 * X-Frame-Options). The sources below are everything the pages load from elsewhere on 25 Oct 2026: Google Analytics 4
 * and the Meta pixel (only with consent, partials/measure.blade.php), Cloudflare Turnstile on the registration page,
 * the Packeta pickup-point widget on the order page, YouTube embeds of the farm's videos. Stripe Checkout and the OAuth
 * logins are redirects of the whole page, which the policy does not restrict. The 3D previews draw into canvases and
 * use blob: and data: pictures; inline scripts and handlers in the templates need 'unsafe-inline' for now.
 */
return [
    // always, on every page
    'headers' => [
        // camera: the photo tools take a picture on the phone; geolocation: Packeta's widget finds the nearest point
        'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(self "https://widget.packeta.com"), payment=(), usb=(), display-capture=()',
    ],
    // only once the policy is enforced (the old header has no report-only mode)
    'headers_enforced' => [
        'X-Frame-Options' => 'SAMEORIGIN',
    ],

    'csp' => [
        // off | report | enforce
        'mode' => env('CSP_MODE', 'report'),
        'report_uri' => '/csp-report',
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", 'https://www.googletagmanager.com', 'https://connect.facebook.net', 'https://challenges.cloudflare.com', 'https://widget.packeta.com'],
            'style-src' => ["'self'", "'unsafe-inline'"],
            // media.printables.com: pictures of the inspiration catalogue's models without a thumbnail of our own (CatalogModel::thumbUrl, the first report-only hit of 25 Oct 2026)
            'img-src' => ["'self'", 'data:', 'blob:', 'https://media.printables.com', 'https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://www.googletagmanager.com', 'https://www.facebook.com', 'https://i.ytimg.com', 'https://widget.packeta.com', 'https://*.packeta.com'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", 'https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://analytics.google.com', 'https://stats.g.doubleclick.net', 'https://www.googletagmanager.com', 'https://www.facebook.com', 'https://widget.packeta.com', 'https://*.packeta.com'],
            'frame-src' => ['https://www.youtube-nocookie.com', 'https://www.youtube.com', 'https://challenges.cloudflare.com', 'https://widget.packeta.com'],
            'media-src' => ["'self'", 'blob:'],
            'worker-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ],
    ],
];
