<?php

/*
 * Response headers of the pages (App\Http\Middleware\SecurityHeaders, TODO #45). HSTS, nosniff and Referrer-Policy
 * are set by nginx (snippets/matplace-app-common.conf) and are not repeated here.
 *
 * The Content-Security-Policy ran in `report` mode from 25 to 27 Oct 2026 (the browser sends what it would have blocked to
 * /csp-report, storage/logs/csp.log, and blocks nothing); since 27 Oct it is enforced (then also X-Frame-Options). The
 * reports keep coming in enforce mode too: a new third party shows up there first. The sources below are everything the pages load from elsewhere on 25 Oct 2026: Google Analytics 4
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

    // paths with rules of their own: the first pattern ($request->is) that fits overrides the directives below and, in enforce
    // mode, the headers above (a null header is left out). The calculator other sites embed (/embed/calc) may sit in any frame.
    'paths' => [
        'embed/*' => ['directives' => ['frame-ancestors' => ['*']], 'headers_enforced' => ['X-Frame-Options' => null]],
    ],

    'csp' => [
        // off | report | enforce
        'mode' => env('CSP_MODE', 'enforce'),   // enforced since 27 Oct 2026 (Roman: "zapni"); two days of reports showed only the sources below
        'report_uri' => '/csp-report',
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", 'https://www.googletagmanager.com', 'https://connect.facebook.net', 'https://challenges.cloudflare.com', 'https://widget.packeta.com'],
            'style-src' => ["'self'", "'unsafe-inline'"],
            // media.printables.com: pictures of the inspiration catalogue's models without a thumbnail of our own (CatalogModel::thumbUrl, the first report-only hit of 25 Oct 2026)
            'img-src' => ["'self'", 'data:', 'blob:', 'https://media.printables.com', 'https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://www.googletagmanager.com', 'https://www.facebook.com', 'https://connect.facebook.net', 'https://i.ytimg.com', 'https://widget.packeta.com', 'https://*.packeta.com'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", 'https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://analytics.google.com', 'https://stats.g.doubleclick.net', 'https://www.googletagmanager.com', 'https://www.facebook.com', 'https://widget.packeta.com', 'https://*.packeta.com'],
            // www.facebook.com in frame-src and form-action: the Meta pixel's own frame and form (reports of 26 Oct 2026 on /c/ pages)
            'frame-src' => ['https://www.youtube-nocookie.com', 'https://www.youtube.com', 'https://challenges.cloudflare.com', 'https://widget.packeta.com', 'https://www.facebook.com'],
            'media-src' => ["'self'", 'blob:'],
            'worker-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'", 'https://www.facebook.com'],
            'frame-ancestors' => ["'self'"],
        ],
    ],
];
