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
    'robots_disallow' => [
        '/admin/', '/account/', '/api/', '/farm/orders/', '/webhooks/',
        // docs/AP.md §14 (12 Nov 2026): what a crawler only wastes its visits on. The order's entrance with parameters
        // (a link on every model page; a crawler following it wrote 942 "order started" a day), sign-in redirects to
        // Google and others, private calculation pages, the catalogue's search and the lists' sorting. Nothing here is
        // a page for the index or a file a page needs to render; /login itself stays open so its "noindex" can be read.
        '/farm?', '/en/farm?', '/es/farm?',
        '/auth/',
        '/c/', '/en/c/', '/es/c/',
        '/*?q=', '/*&q=', '/*?sort=', '/*&sort=',
    ],

    /*
     * The footer's row "what we make" (resources/views/partials/footer_make.blade.php): the tools that bring customers,
     * linked from every page, so the pages search engines visit most (the catalogue, the blog) lead to them. Only
     * tools that are public with a page of their own are shown; the services beside them are fixed in the partial.
     */
    'footer_tools' => ['figure', 'pet', 'charm', 'medallion', 'keychain', 'organizer'],

    /*
     * `searchconsole:inspect` (docs/AP.md §14): the addresses whose index state is asked first, by importance (route
     * names in Czech, then the addresses of the sitemap up to the cap). Google allows 2 000 inspections a day for a
     * property; `cap` is ours for one run, `daily_cap` for a calendar day (counted from the stored rows).
     */
    'inspect' => [
        'cap' => (int) env('SEARCH_CONSOLE_INSPECT_CAP', 150),
        'daily_cap' => 1800,
        'routes' => ['home', 'tools', 'tools.gifts', 'pages.praha', 'tools.figure', 'tools.pet', 'tools.charm', 'tools.medallion', 'tools.organizer', 'blog.index', 'tools.business', 'materials', 'pages.about', 'catalog.index'],
        // further addresses by hand (paths), e.g. the two articles of the list for "Request indexing"
        'paths' => ['/blog/kolik-stoji-3d-tisk-na-zakazku-v-roce-2026', '/blog/vanocni-ozdoby-se-jmenem-z-3d-tiskarny'],
    ],

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

    /*
     * Where the farms print, for the Organization structured data of the about and contact pages (Schema::organization,
     * `location`). Empty until the addresses are public (Roman): each ['name' => 'matplace farma Útušice', 'street' => '…',
     * 'locality' => 'Útušice', 'postal_code' => '…', 'country' => 'CZ'].
     */
    'farms' => [],

    // profiles of the brand elsewhere, for the Organization structured data (the YouTube channel is added from config/youtube.php)
    'same_as' => [
        'facebook' => env('SEO_FACEBOOK_URL'),
        'instagram' => env('SEO_INSTAGRAM_URL'),
    ],
];
