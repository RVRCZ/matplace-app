<?php

return [
    /*
     * May search engines keep this site at all? false on a staging copy: every page then says "noindex" and
     * robots.txt disallows everything.
     */
    'indexable' => (bool) env('SEO_INDEXABLE', true),

    // where `php artisan matplace:sitemap` writes the files the site serves at /sitemap.xml and /sitemap-*.xml
    'sitemap_dir' => storage_path('app/sitemaps'),

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

    // profiles of the brand elsewhere, for the Organization structured data (the YouTube channel is added from config/youtube.php)
    'same_as' => [
        'facebook' => env('SEO_FACEBOOK_URL'),
        'instagram' => env('SEO_INSTAGRAM_URL'),
    ],
];
