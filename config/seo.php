<?php

return [
    /*
     * May search engines keep this site at all? false on a staging copy: every page then says "noindex" and
     * robots.txt disallows everything.
     */
    'indexable' => (bool) env('SEO_INDEXABLE', true),

    // where `php artisan matplace:sitemap` writes the files the site serves at /sitemap.xml and /sitemap-*.xml
    'sitemap_dir' => storage_path('app/sitemaps'),
    // exports of Google Search Console, search-console-<date>.csv, read by /admin/stats/tools while the API has no rows (App\Domain\Stats\SearchConsole)
    'search_console_dir' => env('SEARCH_CONSOLE_DIR', base_path('docs/data')),

    /*
     * Search Console through the API (docs/AN.md §14): the same Google Cloud OAuth client as YouTube unless its own is
     * set; the callback https://matplace.com/admin/search-console/callback is registered in that client. The rows of
     * one request (the API's most is 25 000), how far the first pull goes (the API keeps about 16 months), and how long
     * a request waits after a 429 before its one retry.
     */
    'search_console' => [
        'client_id' => env('SEARCH_CONSOLE_CLIENT_ID', env('YOUTUBE_CLIENT_ID')),
        'client_secret' => env('SEARCH_CONSOLE_CLIENT_SECRET', env('YOUTUBE_CLIENT_SECRET')),
        'row_limit' => 25000,
        'first_pull_months' => 16,
        'retry_seconds' => 60,
    ],

    // <meta name="google-site-verification"> for Search Console
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),

    /*
     * Route names (without the language prefix "l.") that search engines must not keep: private pages and
     * one-off links. They still exist in every language, they just carry "noindex" and no hreflang.
     */
    'noindex' => [
        'calc.share',
        'quote.*',
        'inquiry.*',
        'login', 'register', 'password.*',
        'account', 'account.*',
        'farm.start', 'farm.orders', 'farm.orders.*',
        'designer.*',
        'printer.*',
        'admin.*',
    ],

    // robots.txt: where crawlers need not go at all (the pages themselves say noindex as well)
    'robots_disallow' => ['/admin/', '/account/', '/api/', '/farm/orders/', '/webhooks/'],

    // who runs the site, for the Organization structured data of the about and contact pages (the facts the contact page prints, lang/*/pages.php)
    'organization' => [
        'legal_name' => 'matplace s.r.o.',
        'street' => 'Rybná 716/24',
        'locality' => 'Praha 1',
        'postal_code' => '110 00',
        'country' => 'CZ',
        'ico' => '22499008',
        'vat_id' => env('SEO_VAT_ID'),
        'email' => 'info@matplace.com',
    ],

    // profiles of the brand elsewhere, for the Organization structured data (the YouTube channel is added from config/youtube.php)
    'same_as' => [
        'facebook' => env('SEO_FACEBOOK_URL'),
        'instagram' => env('SEO_INSTAGRAM_URL'),
    ],
];
